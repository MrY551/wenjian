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
header('Access-Control-Allow-Methods: GET, POST, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

// 处理预检请求
if (isset($_SERVER['REQUEST_METHOD']) && $_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
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
require_once __DIR__ . '/csrf.php';
$method = isset($_SERVER['REQUEST_METHOD']) ? $_SERVER['REQUEST_METHOD'] : 'GET';
csrf_verify($method); // 写操作必须带 X-CSRF-Token
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
// parse_url 不解码 percent-encoding，文件编号含中文【】时必须自行解码
$pathParts = array_map('urldecode', explode('/', trim($path, '/')));

// 获取数据库连接
$database = new Database();
$db = $database->getConnection();

if (!$db) {
    http_response_code(503);
    echo json_encode(['success' => false, 'message' => '数据库连接失败，请检查配置或数据库服务'], JSON_UNESCAPED_UNICODE);
    exit;
}

// ---------------------------------------------------------------------------
// ?action= 兼容层
// 前端 script-php.js 通过 query 参数调用（list/get/create/update/delete），
// 早期实现用的是 REST 风格（method + PATH_INFO）。两套并存，
// 这里统一返回 { success, ... }，避免前端读到 undefined。
// ---------------------------------------------------------------------------
$queryAction = isset($_GET['action']) ? $_GET['action'] : '';
if ($queryAction !== '') {
    switch ($queryAction) {
        case 'list':
            if ($method === 'GET') {
                actionListInstructions($db);
            } else {
                http_response_code(405);
                echo json_encode(['success' => false, 'message' => '不允许的请求方法'], JSON_UNESCAPED_UNICODE);
            }
            break;

        case 'get':
            if ($method === 'GET') {
                actionGetInstruction($db, isset($_GET['fileNumber']) ? $_GET['fileNumber'] : '');
            } else {
                http_response_code(405);
                echo json_encode(['success' => false, 'message' => '不允许的请求方法'], JSON_UNESCAPED_UNICODE);
            }
            break;

        case 'create':
            if ($method === 'POST') {
                actionCreateInstruction($db);
            } else {
                http_response_code(405);
                echo json_encode(['success' => false, 'message' => '不允许的请求方法'], JSON_UNESCAPED_UNICODE);
            }
            break;

        case 'update':
            if ($method === 'POST') {
                actionUpdateInstruction($db);
            } else {
                http_response_code(405);
                echo json_encode(['success' => false, 'message' => '不允许的请求方法'], JSON_UNESCAPED_UNICODE);
            }
            break;

        case 'delete':
            if ($method === 'POST') {
                actionDeleteInstruction($db, isset($_GET['id']) ? $_GET['id'] : '');
            } else {
                http_response_code(405);
                echo json_encode(['success' => false, 'message' => '不允许的请求方法'], JSON_UNESCAPED_UNICODE);
            }
            break;

        default:
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => '未知操作: ' . $queryAction], JSON_UNESCAPED_UNICODE);
            break;
    }
    exit;
}

// 根据请求方法处理不同的操作
switch ($method) {
    case 'GET':
        // 获取批示列表或特定文件的批示
        if (isset($pathParts[2]) && $pathParts[2] === 'file' && isset($pathParts[3])) {
            // 获取特定文件的批示
            getFileInstructions($db, $pathParts[3]);
        } else {
            // 获取所有批示
            getInstructions($db);
        }
        break;

    case 'POST':
        // 创建新批示
        createInstruction($db);
        break;

    case 'DELETE':
        // 删除批示
        if (isset($pathParts[2]) && is_numeric($pathParts[2])) {
            deleteInstruction($db, $pathParts[2]);
        } else {
            http_response_code(400);
            echo json_encode(['error' => '无效的批示ID']);
        }
        break;

    default:
        http_response_code(405);
        echo json_encode(['error' => '不允许的请求方法']);
        break;
}

// 获取所有批示
/**
 * 批示归属范围。
 * superadmin 可管理全部批示；其他用户仅限自己文件上的批示。
 * 返回 [SQL 片段, 绑定参数]，片段接在 WHERE 条件之后（以 AND 开头）。
 */
function instructionScopeClause() {
    if (isset($_SESSION['role']) && $_SESSION['role'] === 'superadmin') {
        return ['', []];
    }
    return [
        ' AND fileNumber IN (SELECT fileNumber FROM files WHERE userId = ?)',
        [(int) $_SESSION['user_id']]
    ];
}

/**
 * 校验文件是否存在且属于当前用户（superadmin 跳过归属判断）。
 */
function fileBelongsToCurrentUser($db, $fileNumber) {
    if (isset($_SESSION['role']) && $_SESSION['role'] === 'superadmin') {
        $stmt = $db->prepare("SELECT 1 FROM files WHERE fileNumber = ?");
        $stmt->execute([$fileNumber]);
        return (bool) $stmt->fetchColumn();
    }

    $stmt = $db->prepare("SELECT 1 FROM files WHERE fileNumber = ? AND userId = ?");
    $stmt->execute([$fileNumber, (int) $_SESSION['user_id']]);
    return (bool) $stmt->fetchColumn();
}

function getInstructions($db) {
    try {
        list($scopeSql, $scopeParams) = instructionScopeClause();
        $query = "SELECT * FROM instructions WHERE 1=1" . $scopeSql . " ORDER BY date DESC";
        $stmt = $db->prepare($query);
        $stmt->execute($scopeParams);
        $instructions = $stmt->fetchAll(PDO::FETCH_ASSOC);

        http_response_code(200);
        echo json_encode($instructions);
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['error' => '获取批示列表失败']);
    }
}

// 获取特定文件的批示
function getFileInstructions($db, $fileNumber) {
    try {
        $query = "SELECT * FROM instructions WHERE fileNumber = ? ORDER BY date DESC";
        $stmt = $db->prepare($query);
        $stmt->execute([$fileNumber]);
        $instructions = $stmt->fetchAll(PDO::FETCH_ASSOC);

        http_response_code(200);
        echo json_encode($instructions);
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['error' => '获取文件批示失败']);
    }
}

// 创建新批示
function createInstruction($db) {
    $data = json_decode(file_get_contents('php://input'), true);




    // 验证必填字段不为空
    $requiredFields = ['fileNumber', 'leaderName', 'content', 'date'];
    foreach ($requiredFields as $field) {
        if (!isset($data[$field]) || trim($data[$field]) === '') {
            http_response_code(400);
            echo json_encode(['error' => $field . '不能为空']);
            return;
        }
    }

    // 验证日期格式 (YYYY-MM-DD)
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $data['date'])) {
        http_response_code(400);
        echo json_encode(['error' => '日期格式无效，请使用 YYYY-MM-DD 格式']);
        return;
    }

    try {
        // 只能给自己可见的文件加批示（superadmin 不受限）
        if (!fileBelongsToCurrentUser($db, $data['fileNumber'])) {
            http_response_code(404);
            echo json_encode(['error' => '文件不存在']);
            return;
        }

        $query = "INSERT INTO instructions (fileNumber, leaderName, content, date) 
                  VALUES (?, ?, ?, ?)";

        $stmt = $db->prepare($query);
        $result = $stmt->execute([
            $data['fileNumber'],
            $data['leaderName'],
            $data['content'],
            $data['date']
        ]);

        if ($result) {
            $newId = $db->lastInsertId();
            http_response_code(201);
            echo json_encode(['id' => $newId, 'message' => '批示创建成功']);
        } else {
            http_response_code(500);
            echo json_encode(['error' => '批示创建失败']);
        }
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['error' => '批示创建失败']);
    }
}

// 删除批示
function deleteInstruction($db, $id) {
    try {
        list($scopeSql, $scopeParams) = instructionScopeClause();
        $query = "DELETE FROM instructions WHERE id = ?" . $scopeSql;
        $stmt = $db->prepare($query);
        $result = $stmt->execute(array_merge([$id], $scopeParams));

        if ($result && $stmt->rowCount() > 0) {
            http_response_code(200);
            echo json_encode(['message' => '批示删除成功']);
        } elseif ($result) {
            http_response_code(404);
            echo json_encode(['error' => '批示不存在'], JSON_UNESCAPED_UNICODE);
        } else {
            http_response_code(500);
            echo json_encode(['error' => '批示删除失败']);
        }
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['error' => '批示删除失败']);
    }
}

// ===========================================================================
// ?action= 兼容层实现（供 script-php.js 使用，统一 { success, ... } 返回）
// ===========================================================================

/** 批示列表 */
function actionListInstructions($db) {
    try {
        list($scopeSql, $scopeParams) = instructionScopeClause();
        $stmt = $db->prepare("SELECT * FROM instructions WHERE 1=1" . $scopeSql . " ORDER BY date DESC, id DESC");
        $stmt->execute($scopeParams);
        echo json_encode([
            'success' => true,
            'instructions' => $stmt->fetchAll(PDO::FETCH_ASSOC)
        ], JSON_UNESCAPED_UNICODE);
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => '获取批示列表失败'], JSON_UNESCAPED_UNICODE);
    }
}

/** 取某个文件最新一条批示（编辑弹窗回填用） */
function actionGetInstruction($db, $fileNumber) {
    if ($fileNumber === '') {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => '缺少文件编号'], JSON_UNESCAPED_UNICODE);
        return;
    }

    try {
        $stmt = $db->prepare("SELECT * FROM instructions WHERE fileNumber = ? ORDER BY id DESC LIMIT 1");
        $stmt->execute([$fileNumber]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        echo json_encode([
            'success' => true,
            'instruction' => $row ? $row : null
        ], JSON_UNESCAPED_UNICODE);
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => '获取批示失败'], JSON_UNESCAPED_UNICODE);
    }
}

/** 新增批示 */
function actionCreateInstruction($db) {
    $data = json_decode(file_get_contents('php://input'), true);
    if (!is_array($data)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => '请求体不是合法 JSON'], JSON_UNESCAPED_UNICODE);
        return;
    }

    $requiredFields = ['fileNumber', 'leaderName', 'content', 'date'];
    foreach ($requiredFields as $field) {
        if (!isset($data[$field]) || trim($data[$field]) === '') {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => $field . '不能为空'], JSON_UNESCAPED_UNICODE);
            return;
        }
    }

    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $data['date'])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => '日期格式无效，请使用 YYYY-MM-DD'], JSON_UNESCAPED_UNICODE);
        return;
    }

    try {
        // 文件必须存在且属于当前用户，否则批示会变成孤儿记录或越权写入
        if (!fileBelongsToCurrentUser($db, $data['fileNumber'])) {
            http_response_code(404);
            echo json_encode(['success' => false, 'message' => '文件编号不存在: ' . $data['fileNumber']], JSON_UNESCAPED_UNICODE);
            return;
        }

        $stmt = $db->prepare("INSERT INTO instructions (fileNumber, leaderName, content, date) VALUES (?, ?, ?, ?)");
        $stmt->execute([$data['fileNumber'], $data['leaderName'], $data['content'], $data['date']]);

        echo json_encode([
            'success' => true,
            'id' => $db->lastInsertId(),
            'message' => '批示创建成功'
        ], JSON_UNESCAPED_UNICODE);
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => '批示创建失败'], JSON_UNESCAPED_UNICODE);
    }
}

/** 更新某文件最新一条批示的内容 */
/**
 * 保存批示：有则更新，无则新建（upsert）。
 *
 * 去掉独立的批示页面后，查询页的编辑弹窗成了唯一入口，
 * 它既要能改已有的批示，也要能给还没有批示的文件补一条。
 */
function actionUpdateInstruction($db) {
    $data = json_decode(file_get_contents('php://input'), true);
    if (!is_array($data) || !isset($data['fileNumber']) || $data['fileNumber'] === '') {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => '缺少文件编号'], JSON_UNESCAPED_UNICODE);
        return;
    }

    $content = isset($data['content']) ? trim($data['content']) : '';
    if ($content === '') {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => '批示内容不能为空'], JSON_UNESCAPED_UNICODE);
        return;
    }

    if (!fileBelongsToCurrentUser($db, $data['fileNumber'])) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => '文件不存在'], JSON_UNESCAPED_UNICODE);
        return;
    }

    try {
        // 先定位最新一条：MySQL 不允许 UPDATE 时对同表做子查询（归属校验加在 files 子查询里）
        list($scopeSql, $scopeParams) = instructionScopeClause();
        $find = $db->prepare("SELECT id FROM instructions WHERE fileNumber = ?" . $scopeSql . " ORDER BY id DESC LIMIT 1");
        $find->execute(array_merge([$data['fileNumber']], $scopeParams));
        $row = $find->fetch(PDO::FETCH_ASSOC);

        if ($row) {
            $stmt = $db->prepare("UPDATE instructions SET content = ? WHERE id = ?");
            $stmt->execute([$content, $row['id']]);
            $message = '批示更新成功';
        } else {
            // 还没有批示记录：用当前登录人作为批示人，日期取今天
            $leaderName = isset($_SESSION['username']) && $_SESSION['username'] !== ''
                ? $_SESSION['username']
                : '未具名';
            $date = isset($data['date']) && $data['date'] !== '' ? $data['date'] : date('Y-m-d');

            $stmt = $db->prepare("INSERT INTO instructions (fileNumber, leaderName, content, date) VALUES (?, ?, ?, ?)");
            $stmt->execute([$data['fileNumber'], $leaderName, $content, $date]);
            $message = '批示保存成功';
        }

        // files 表里的冗余字段同步一份，兼容只读 files 的旧查询路径
        try {
            $sync = $db->prepare("UPDATE files SET leaderInstruction = ? WHERE fileNumber = ?");
            $sync->execute([$content, $data['fileNumber']]);
        } catch (PDOException $syncError) {
            // 同步失败不影响主流程
        }

        echo json_encode(['success' => true, 'message' => $message], JSON_UNESCAPED_UNICODE);
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => '批示保存失败'], JSON_UNESCAPED_UNICODE);
    }
}

/** 按 id 删除批示 */
function actionDeleteInstruction($db, $id) {
    if (!is_numeric($id)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => '无效的批示ID'], JSON_UNESCAPED_UNICODE);
        return;
    }

    try {
        list($scopeSql, $scopeParams) = instructionScopeClause();
        $stmt = $db->prepare("DELETE FROM instructions WHERE id = ?" . $scopeSql);
        $stmt->execute(array_merge([$id], $scopeParams));

        if ($stmt->rowCount() > 0) {
            echo json_encode(['success' => true, 'message' => '批示删除成功'], JSON_UNESCAPED_UNICODE);
        } else {
            // 不存在或不属于当前用户，统一按不存在处理
            http_response_code(404);
            echo json_encode(['success' => false, 'message' => '批示不存在'], JSON_UNESCAPED_UNICODE);
        }
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => '批示删除失败'], JSON_UNESCAPED_UNICODE);
    }
}
?>
