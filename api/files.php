<?php
// 配置Session
ini_set('session.cookie_httponly', 1);
ini_set('session.use_only_cookies', 1);
ini_set('session.cookie_samesite', 'Lax');

// 启动Session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// 启用错误日志
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/../logs/php_errors.log');

// 确保日志目录存在
$logDir = __DIR__ . '/../logs';
if (!file_exists($logDir)) {
    mkdir($logDir, 0755, true);
}

// 设置响应头
header('Access-Control-Allow-Origin: *');
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

// 处理预检请求
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// 检查用户是否登录
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => '未登录']);
    exit;
}

// 引入数据库配置
require_once '../config/database.php';

// 获取请求方法和路径
$method = $_SERVER['REQUEST_METHOD'];
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$pathParts = explode('/', trim($path, '/'));

// 获取数据库连接
$database = new Database();
$db = $database->getConnection();

// 根据请求方法处理不同的操作
switch ($method) {
    case 'GET':
        $action = isset($_GET['action']) ? $_GET['action'] : '';
        if ($action === 'generateNumber') {
            $prefix = isset($_GET['prefix']) ? $_GET['prefix'] : '';
            generateFileNumber($db, $prefix);
        } elseif ($action === 'get' && isset($_GET['fileNumber'])) {
            getFileByNumber($db, $_GET['fileNumber']);
        } elseif (isset($pathParts[2]) && is_numeric($pathParts[2])) {
            getFile($db, $pathParts[2]);
        } else {
            getFiles($db);
        }
        break;

    case 'POST':
        // 支持 query 参数方式的操作
        $action = isset($_GET['action']) ? $_GET['action'] : '';
        if ($action === 'delete') {
            $data = json_decode(file_get_contents('php://input'), true);
            if (isset($data['fileNumber'])) {
                deleteFileByNumber($db, $data['fileNumber']);
            } elseif (isset($pathParts[2]) && is_numeric($pathParts[2])) {
                deleteFile($db, $pathParts[2]);
            } else {
                http_response_code(400);
                echo json_encode(['error' => '无效的文件ID']);
            }
        } elseif ($action === 'update' && isset($_GET['fileNumber'])) {
            updateFileByNumber($db, $_GET['fileNumber']);
        } elseif ($action === 'create') {
            createFile($db);
        } else {
            createFile($db);
        }
        break;

    case 'PUT':
        if (isset($pathParts[2]) && is_numeric($pathParts[2])) {
            updateFile($db, $pathParts[2]);
        } else {
            http_response_code(400);
            echo json_encode(['error' => '无效的文件ID']);
        }
        break;

    case 'DELETE':
        if (isset($pathParts[2]) && is_numeric($pathParts[2])) {
            deleteFile($db, $pathParts[2]);
        } else {
            http_response_code(400);
            echo json_encode(['error' => '无效的文件ID']);
        }
        break;

    default:
        http_response_code(405);
        echo json_encode(['error' => '不允许的请求方法']);
        break;
}

// 获取文件列表（带分页）
function getFiles($db) {
    $searchTerm = isset($_GET['search']) ? $_GET['search'] : '';
    $filter = isset($_GET['filter']) ? $_GET['filter'] : 'all';
    $prefixFilter = isset($_GET['prefix']) ? $_GET['prefix'] : 'all';
    $yearFilter = isset($_GET['year']) ? $_GET['year'] : 'all';
    $page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
    $limit = isset($_GET['limit']) ? max(1, min(100, intval($_GET['limit']))) : 20;
    $offset = ($page - 1) * $limit;

    // 超级管理员可以查看所有文件，其他用户只能查看自己的文件
    $isSuperAdmin = isset($_SESSION['role']) && $_SESSION['role'] === 'superadmin';

    // 使用单次 LEFT JOIN 查询获取文件和最新批示
    $query = "SELECT f.*, 
                   i.content as leaderInstruction
              FROM files f
              LEFT JOIN (
                  SELECT i1.fileNumber, i1.content
                  FROM instructions i1
                  INNER JOIN (
                      SELECT fileNumber, MAX(date) as max_date
                      FROM instructions
                      GROUP BY fileNumber
                  ) latest ON i1.fileNumber = latest.fileNumber AND i1.date = latest.max_date
              ) i ON f.fileNumber = i.fileNumber
              WHERE 1=1";
    $params = [];

    // 如果不是超级管理员，只显示当前用户的文件
    if (!$isSuperAdmin) {
        $query .= " AND f.userId = ?";
        $params[] = $_SESSION['user_id'];
    }

    // 添加搜索条件
    if (!empty($searchTerm)) {
        $query .= " AND (f.fileNumber LIKE ? OR f.fileName LIKE ? OR f.fileContent LIKE ?)";
        $searchPattern = "%{$searchTerm}%";
        $params[] = $searchPattern;
        $params[] = $searchPattern;
        $params[] = $searchPattern;
    }

    // 添加筛选条件
    if ($filter !== 'all') {
        switch ($filter) {
            case 'all-approved':
                $query .= " AND f.approver01 = '已签批' AND f.approver02 = '已签批' AND f.approver03 = '已签批' AND f.approver04 = '已签批' AND f.approver05 = '已签批' AND f.approver06 = '已签批' AND f.approver07 = '已签批'";
                break;
            case 'partial-approved':
                $query .= " AND (f.approver01 = '已签批' OR f.approver02 = '已签批' OR f.approver03 = '已签批' OR f.approver04 = '已签批' OR f.approver05 = '已签批' OR f.approver06 = '已签批' OR f.approver07 = '已签批') AND NOT (f.approver01 = '已签批' AND f.approver02 = '已签批' AND f.approver03 = '已签批' AND f.approver04 = '已签批' AND f.approver05 = '已签批' AND f.approver06 = '已签批' AND f.approver07 = '已签批')";
                break;
            case 'none-approved':
                $query .= " AND f.approver01 = '未签批' AND f.approver02 = '未签批' AND f.approver03 = '未签批' AND f.approver04 = '未签批' AND f.approver05 = '未签批' AND f.approver06 = '未签批' AND f.approver07 = '未签批'";
                break;
            case 'no-instruction':
                $query .= " AND (i.content IS NULL OR i.content = '') AND f.fileNumber NOT IN (SELECT fileNumber FROM instructions)";
                break;
        }
    }

    // 添加前缀筛选条件
    if ($prefixFilter !== 'all') {
        $query .= " AND f.fileNumber LIKE ?";
        $params[] = $prefixFilter . "%";
    }

    // 添加年份筛选条件
    if ($yearFilter !== 'all') {
        $query .= " AND YEAR(f.createdAt) = ?";
        $params[] = $yearFilter;
    }

    $query .= " ORDER BY f.fileNumber DESC";

    // 计算总记录数
    $countQuery = "SELECT COUNT(*) as total FROM (" . $query . ") as count_subquery";
    try {
        $countStmt = $db->prepare($countQuery);
        $countStmt->execute($params);
        $total = $countStmt->fetch(PDO::FETCH_ASSOC)['total'];
    } catch (PDOException $e) {
        $total = 0;
    }

    // 添加分页
    $query .= " LIMIT ? OFFSET ?";
    $params[] = $limit;
    $params[] = $offset;

    try {
        $stmt = $db->prepare($query);
        $stmt->execute($params);
        $files = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $totalPages = ceil($total / $limit);

        // 确保leaderInstruction字段存在
        foreach ($files as &$file) {
            if (!isset($file['leaderInstruction']) || $file['leaderInstruction'] === null) {
                $file['leaderInstruction'] = '';
            }
        }

        http_response_code(200);
        echo json_encode([
            'data' => $files,
            'pagination' => [
                'total' => (int)$total,
                'page' => $page,
                'limit' => $limit,
                'totalPages' => (int)$totalPages
            ]
        ], JSON_UNESCAPED_UNICODE);
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['error' => '获取文件列表失败'], JSON_UNESCAPED_UNICODE);
    }
}

// 获取单个文件
function getFile($db, $id) {
    try {
        $query = "SELECT * FROM files WHERE id = ?";
        $stmt = $db->prepare($query);
        $stmt->execute([$id]);
        $file = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($file) {
            // 确保leaderInstruction字段存在且正确编码
            if (!isset($file['leaderInstruction'])) {
                $file['leaderInstruction'] = '';
            }

            // 从instructions表中获取最新的批示
            $instructionQuery = "SELECT content FROM instructions WHERE fileNumber = ? ORDER BY date DESC LIMIT 1";
            $instructionStmt = $db->prepare($instructionQuery);
            $instructionStmt->execute([$file['fileNumber']]);
            $instruction = $instructionStmt->fetch(PDO::FETCH_ASSOC);

            if ($instruction && !empty($instruction['content'])) {
                $file['leaderInstruction'] = $instruction['content'];
            }

            http_response_code(200);
            echo json_encode($file, JSON_UNESCAPED_UNICODE);
        } else {
            http_response_code(404);
            echo json_encode(['error' => '文件不存在'], JSON_UNESCAPED_UNICODE);
        }
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['error' => '获取文件失败'], JSON_UNESCAPED_UNICODE);
    }
}

// 根据文件编号获取单个文件
function getFileByNumber($db, $fileNumber) {
    try {
        $query = "SELECT * FROM files WHERE fileNumber = ?";
        $stmt = $db->prepare($query);
        $stmt->execute([$fileNumber]);
        $file = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($file) {
            // 确保leaderInstruction字段存在
            if (!isset($file['leaderInstruction'])) {
                $file['leaderInstruction'] = '';
            }

            // 从instructions表中获取最新的批示
            $instructionQuery = "SELECT content FROM instructions WHERE fileNumber = ? ORDER BY date DESC LIMIT 1";
            $instructionStmt = $db->prepare($instructionQuery);
            $instructionStmt->execute([$file['fileNumber']]);
            $instruction = $instructionStmt->fetch(PDO::FETCH_ASSOC);

            if ($instruction && !empty($instruction['content'])) {
                $file['leaderInstruction'] = $instruction['content'];
            }

            http_response_code(200);
            echo json_encode(['success' => true, 'file' => $file], JSON_UNESCAPED_UNICODE);
        } else {
            http_response_code(404);
            echo json_encode(['success' => false, 'message' => '文件不存在'], JSON_UNESCAPED_UNICODE);
        }
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => '获取文件失败'], JSON_UNESCAPED_UNICODE);
    }
}

// 创建新文件
function createFile($db) {
    $data = json_decode(file_get_contents('php://input'), true);

    // 验证必填字段不为空
    $requiredFields = ['fileNumber', 'fileName', 'fileContent', 'issueDate'];
    foreach ($requiredFields as $field) {
        if (!isset($data[$field]) || trim($data[$field]) === '') {
            http_response_code(400);
            echo json_encode(['error' => $field . '不能为空']);
            return;
        }
    }

    // 验证日期格式 (YYYY-MM-DD)
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $data['issueDate'])) {
        http_response_code(400);
        echo json_encode(['error' => '日期格式无效，请使用 YYYY-MM-DD 格式']);
        return;
    }

    try {
        $query = "INSERT INTO files (userId, fileNumber, fileName, fileContent, submitter, issueDate, approver01, approver02, approver03, approver04, approver05, approver06, approver07, leaderInstruction)
                  VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

        $stmt = $db->prepare($query);
        $result = $stmt->execute([
            $_SESSION['user_id'], // 添加用户ID
            $data['fileNumber'],
            $data['fileName'],
            $data['fileContent'],
            $data['submitter'] ?? null,
            $data['issueDate'],
            $data['approver01'] ?? '未签批',
            $data['approver02'] ?? '未签批',
            $data['approver03'] ?? '未签批',
            $data['approver04'] ?? '未签批',
            $data['approver05'] ?? '未签批',
            $data['approver06'] ?? '未签批',
            $data['approver07'] ?? '未签批',
            $data['leaderInstruction'] ?? null
        ]);

        if ($result) {
            $newId = $db->lastInsertId();
            http_response_code(201);
            echo json_encode(['id' => $newId, 'message' => '文件创建成功']);
        } else {
            http_response_code(500);
            echo json_encode(['error' => '文件创建失败']);
        }
    } catch (PDOException $e) {
        if ($e->getCode() == 23000) {
            http_response_code(409);
            echo json_encode(['error' => '文件编号已存在']);
        } else {
            http_response_code(500);
            echo json_encode(['error' => '文件创建失败']);
        }
    }
}

// 更新文件
function updateFile($db, $id) {
    $data = json_decode(file_get_contents('php://input'), true);

    try {
        // 先获取当前文件的文件编号
        $stmt = $db->prepare("SELECT fileNumber FROM files WHERE id = ?");
        $stmt->execute([$id]);
        $currentFile = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$currentFile) {
            http_response_code(404);
            echo json_encode(['error' => '文件不存在']);
            return;
        }

        // 如果文件编号没有改变，则不需要检查唯一性
        $currentFileNumber = $currentFile['fileNumber'];
        $newFileNumber = $data['fileNumber'];

        if ($currentFileNumber === $newFileNumber) {
            // 文件编号没有改变，直接更新
            $query = "UPDATE files
                      SET fileName = ?, fileContent = ?, submitter = ?, issueDate = ?,
                          approver01 = ?, approver02 = ?, approver03 = ?, approver04 = ?,
                          approver05 = ?, approver06 = ?, approver07 = ?, leaderInstruction = ?
                      WHERE id = ?";

            $stmt = $db->prepare($query);
            $result = $stmt->execute([
                $data['fileName'],
                $data['fileContent'],
                $data['submitter'] ?? null,
                $data['issueDate'],
                $data['approver01'] ?? '未签批',
                $data['approver02'] ?? '未签批',
                $data['approver03'] ?? '未签批',
                $data['approver04'] ?? '未签批',
                $data['approver05'] ?? '未签批',
                $data['approver06'] ?? '未签批',
                $data['approver07'] ?? '未签批',
                $data['leaderInstruction'] ?? null,
                $id
            ]);
        } else {
            // 文件编号改变了，需要检查唯一性
            $query = "UPDATE files
                      SET fileNumber = ?, fileName = ?, fileContent = ?, submitter = ?, issueDate = ?,
                          approver01 = ?, approver02 = ?, approver03 = ?, approver04 = ?,
                          approver05 = ?, approver06 = ?, approver07 = ?, leaderInstruction = ?
                      WHERE id = ?";

            $stmt = $db->prepare($query);
            $result = $stmt->execute([
                $data['fileNumber'],
                $data['fileName'],
                $data['fileContent'],
                $data['submitter'] ?? null,
                $data['issueDate'],
                $data['approver01'] ?? '未签批',
                $data['approver02'] ?? '未签批',
                $data['approver03'] ?? '未签批',
                $data['approver04'] ?? '未签批',
                $data['approver05'] ?? '未签批',
                $data['approver06'] ?? '未签批',
                $data['approver07'] ?? '未签批',
                $data['leaderInstruction'] ?? null,
                $id
            ]);
        }

        if ($result) {
            http_response_code(200);
            echo json_encode(['message' => '文件更新成功']);
        } else {
            http_response_code(500);
            echo json_encode(['error' => '文件更新失败']);
        }
    } catch (PDOException $e) {
        if ($e->getCode() == 23000) {
            http_response_code(409);
            echo json_encode(['error' => '文件编号已存在']);
        } else {
            http_response_code(500);
            echo json_encode(['error' => '文件更新失败']);
        }
    }
}

// 删除文件
function deleteFile($db, $id) {
    try {
        $query = "DELETE FROM files WHERE id = ?";
        $stmt = $db->prepare($query);
        $result = $stmt->execute([$id]);

        if ($result) {
            http_response_code(200);
            echo json_encode(['message' => '文件删除成功']);
        } else {
            http_response_code(500);
            echo json_encode(['error' => '文件删除失败']);
        }
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['error' => '文件删除失败']);
    }
}

// 通过文件编号删除文件
function deleteFileByNumber($db, $fileNumber) {
    try {
        $query = "DELETE FROM files WHERE fileNumber = ?";
        $stmt = $db->prepare($query);
        $result = $stmt->execute([$fileNumber]);

        if ($result) {
            if ($stmt->rowCount() > 0) {
                http_response_code(200);
                echo json_encode(['success' => true, 'message' => '文件删除成功']);
            } else {
                http_response_code(404);
                echo json_encode(['error' => '文件不存在']);
            }
        } else {
            http_response_code(500);
            echo json_encode(['error' => '文件删除失败']);
        }
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['error' => '文件删除失败']);
    }
}

// 通过文件编号更新文件
function updateFileByNumber($db, $fileNumber) {
    $data = json_decode(file_get_contents('php://input'), true);

    try {
        // 先检查文件是否存在
        $stmt = $db->prepare("SELECT id FROM files WHERE fileNumber = ?");
        $stmt->execute([$fileNumber]);
        $file = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$file) {
            http_response_code(404);
            echo json_encode(['success' => false, 'message' => '文件不存在']);
            return;
        }

        $query = "UPDATE files
                  SET fileName = ?, fileContent = ?, submitter = ?, issueDate = ?,
                      approver01 = ?, approver02 = ?, approver03 = ?, approver04 = ?,
                      approver05 = ?, approver06 = ?, approver07 = ?, leaderInstruction = ?
                  WHERE fileNumber = ?";

        $stmt = $db->prepare($query);
        $result = $stmt->execute([
            $data['fileName'],
            $data['fileContent'],
            $data['submitter'] ?? null,
            $data['issueDate'],
            $data['approver01'] ?? '未签批',
            $data['approver02'] ?? '未签批',
            $data['approver03'] ?? '未签批',
            $data['approver04'] ?? '未签批',
            $data['approver05'] ?? '未签批',
            $data['approver06'] ?? '未签批',
            $data['approver07'] ?? '未签批',
            $data['leaderInstruction'] ?? null,
            $fileNumber
        ]);

        if ($result) {
            echo json_encode(['success' => true, 'message' => '文件更新成功']);
        } else {
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => '文件更新失败']);
        }
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => '文件更新失败']);
    }
}

// 生成文件编号
function generateFileNumber($db, $prefix) {
    if (empty($prefix)) {
        http_response_code(400);
        echo json_encode(['error' => '前缀不能为空']);
        return;
    }

    try {
        $year = date('Y');
        $pattern = $prefix . $year . '%';
        
        // 查询当前前缀和年份的最大编号
        $stmt = $db->prepare("SELECT fileNumber FROM files WHERE fileNumber LIKE ? ORDER BY fileNumber DESC LIMIT 1");
        $stmt->execute([$pattern]);
        $lastFile = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($lastFile) {
            // 提取【】中的编号部分
            preg_match('/【(\d+)】/', $lastFile['fileNumber'], $matches);
            $lastNumber = isset($matches[1]) ? intval($matches[1]) : 0;
            $newNumber = $lastNumber + 1;
        } else {
            $newNumber = 1;
        }
        
        // 格式：前缀+年份+【编号】
        $fileNumber = $prefix . $year . '【' . str_pad($newNumber, 3, '0', STR_PAD_LEFT) . '】';
        
        http_response_code(200);
        echo json_encode(['success' => true, 'fileNumber' => $fileNumber]);
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['error' => '生成编号失败']);
    }
}
