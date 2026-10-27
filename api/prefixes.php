<?php
// 前缀管理API
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

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
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

// 检查是否是超级管理员（添加、删除、更新前缀需要超级管理员权限）
require_once __DIR__ . '/csrf.php';
$method = isset($_SERVER['REQUEST_METHOD']) ? $_SERVER['REQUEST_METHOD'] : 'GET';
csrf_verify($method); // 写操作必须带 X-CSRF-Token

// 获取请求路径
$path = '/';
if (isset($_SERVER['PATH_INFO'])) {
    $path = $_SERVER['PATH_INFO'];
} elseif (isset($_SERVER['REQUEST_URI'])) {
    $uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
    $scriptName = $_SERVER['SCRIPT_NAME'];
    $path = str_replace($scriptName, '', $uri);
    if (empty($path)) {
        $path = '/';
    }
}

// 对于非GET请求，需要超级管理员权限
if ($method !== 'GET') {
    if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'superadmin') {
        http_response_code(403);
        echo json_encode(['error' => '无权访问']);
        exit;
    }
}

require_once '../config/database.php';

$database = new Database();
$db = $database->getConnection();

if (!$db) {
    http_response_code(503);
    echo json_encode(['success' => false, 'message' => '数据库连接失败，请检查配置或数据库服务'], JSON_UNESCAPED_UNICODE);
    exit;
}

// 路由处理
$action = isset($_GET['action']) ? $_GET['action'] : '';

// 处理 action 参数
if ($action === 'sync' && $method === 'GET') {
    syncPrefixes($db);
    exit;
}

switch ($path) {
    case '/':
    case '':
        if ($method === 'GET') {
            getPrefixes($db);
        } elseif ($method === 'POST') {
            createPrefix($db);
        }
        break;
    default:
        // 处理 /{id} 格式的路径
        if (preg_match('#^/(\d+)$#', $path, $matches)) {
            $prefixId = $matches[1];
            if ($method === 'GET') {
                getPrefix($db, $prefixId);
            } elseif ($method === 'PUT') {
                updatePrefix($db, $prefixId);
            } elseif ($method === 'DELETE') {
                deletePrefix($db, $prefixId);
            }
        } else {
            http_response_code(404);
            echo json_encode(['error' => '未找到请求的资源']);
        }
        break;
}

// 获取所有前缀
function getPrefixes($db) {
    try {
        $query = "SELECT id, prefix, description, createdAt, updatedAt FROM prefixes ORDER BY prefix";
        $stmt = $db->prepare($query);
        $stmt->execute();
        $prefixes = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode(['success' => true, 'prefixes' => $prefixes]);
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['error' => '获取前缀列表失败']);
    }
}

// 获取单个前缀
function getPrefix($db, $prefixId) {
    try {
        $query = "SELECT id, prefix, description, createdAt, updatedAt FROM prefixes WHERE id = ?";
        $stmt = $db->prepare($query);
        $stmt->execute([$prefixId]);
        $prefix = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($prefix) {
            echo json_encode(['success' => true, 'prefix' => $prefix]);
        } else {
            http_response_code(404);
            echo json_encode(['error' => '前缀不存在']);
        }
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['error' => '获取前缀信息失败']);
    }
}

// 创建前缀
function createPrefix($db) {
    $data = json_decode(file_get_contents('php://input'), true);

    if (!isset($data['prefix'])) {
        http_response_code(400);
        echo json_encode(['error' => '缺少必填字段']);
        return;
    }

    $prefix = $data['prefix'];
    $description = isset($data['description']) ? $data['description'] : '';

    // 验证前缀格式（支持中文、字母、数字，长度2-10个字符）
    $pattern = '/^[\x{4e00}-\x{9fa5}A-Za-z0-9]{2,10}$/u';
    if (!preg_match($pattern, $prefix)) {
        http_response_code(400);
        echo json_encode(['error' => '前缀格式不正确，支持中文、字母、数字，长度2-10个字符']);
        return;
    }

    try {
        // 检查前缀是否已存在
        $stmt = $db->prepare("SELECT id FROM prefixes WHERE prefix = ?");
        $stmt->execute([$prefix]);

        if ($stmt->fetch()) {
            http_response_code(400);
            echo json_encode(['error' => '前缀已存在']);
            return;
        }

        // 创建新前缀
        $stmt = $db->prepare("INSERT INTO prefixes (prefix, description) VALUES (?, ?)");
        $stmt->execute([$prefix, $description]);

        echo json_encode(['success' => true, 'message' => '前缀创建成功']);
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['error' => '创建前缀失败']);
    }
}

// 更新前缀
function updatePrefix($db, $prefixId) {
    $data = json_decode(file_get_contents('php://input'), true);

    try {
        // 检查前缀是否存在
        $stmt = $db->prepare("SELECT id FROM prefixes WHERE id = ?");
        $stmt->execute([$prefixId]);
        $prefix = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$prefix) {
            http_response_code(404);
            echo json_encode(['error' => '前缀不存在']);
            return;
        }

        // 构建更新语句
        $updates = [];
        $params = [];

        if (isset($data['prefix'])) {
            // 验证前缀格式（支持中文、字母、数字和横杠，长度2-10个字符）
            $pattern = '/^[\x{4e00}-\x{9fa5}A-Za-z0-9-]{2,10}$/u';
            if (!preg_match($pattern, $data['prefix'])) {
                http_response_code(400);
                echo json_encode(['error' => '前缀格式不正确，支持中文、字母、数字和横杠，长度2-10个字符']);
                return;
            }

            // 检查前缀是否已被其他前缀使用
            $stmt = $db->prepare("SELECT id FROM prefixes WHERE prefix = ? AND id != ?");
            $stmt->execute([$data['prefix'], $prefixId]);

            if ($stmt->fetch()) {
                http_response_code(400);
                echo json_encode(['error' => '前缀已存在']);
                return;
            }

            $updates[] = "prefix = ?";
            $params[] = $data['prefix'];
        }

        if (isset($data['description'])) {
            $updates[] = "description = ?";
            $params[] = $data['description'];
        }

        if (empty($updates)) {
            http_response_code(400);
            echo json_encode(['error' => '没有要更新的字段']);
            return;
        }

        $params[] = $prefixId;
        $query = "UPDATE prefixes SET " . implode(', ', $updates) . " WHERE id = ?";
        $stmt = $db->prepare($query);
        $stmt->execute($params);

        echo json_encode(['success' => true, 'message' => '前缀更新成功']);
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['error' => '更新前缀失败']);
    }
}

// 删除前缀
function deletePrefix($db, $prefixId) {
    try {
        // 检查前缀是否存在
        $stmt = $db->prepare("SELECT id, prefix FROM prefixes WHERE id = ?");
        $stmt->execute([$prefixId]);
        $prefix = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$prefix) {
            http_response_code(404);
            echo json_encode(['error' => '前缀不存在']);
            return;
        }

        // 检查是否有文件使用该前缀
        $stmt = $db->prepare("SELECT COUNT(*) as count FROM files WHERE fileNumber LIKE ?");
        $stmt->execute([$prefix['prefix'] . '%']);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($result['count'] > 0) {
            http_response_code(400);
            echo json_encode(['error' => '无法删除，有文件正在使用该前缀']);
            return;
        }

        // 删除前缀
        $stmt = $db->prepare("DELETE FROM prefixes WHERE id = ?");
        $stmt->execute([$prefixId]);

        echo json_encode(['success' => true, 'message' => '前缀删除成功']);
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['error' => '删除前缀失败']);
    }
}

// 从 files 表同步前缀到 prefixes 表
function syncPrefixes($db) {
    try {
        // 从 files 表中提取所有不重复的文件编号
        $stmt = $db->prepare("SELECT DISTINCT fileNumber FROM files");
        $stmt->execute();
        $files = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // 提取前缀（格式：前缀+年份+【编号】）
        $prefixes = [];
        foreach ($files as $file) {
            $fileNumber = $file['fileNumber'];
            // 匹配前缀部分：年份前的所有内容
            if (preg_match('/^(.+?)(\d{4})【/', $fileNumber, $matches)) {
                $prefix = $matches[1];
                if (!in_array($prefix, $prefixes)) {
                    $prefixes[] = $prefix;
                }
            }
        }

        // 获取已存在的前缀
        $stmt = $db->prepare("SELECT prefix FROM prefixes");
        $stmt->execute();
        $existingPrefixes = $stmt->fetchAll(PDO::FETCH_COLUMN);

        // 插入不存在的前缀
        $insertedCount = 0;
        $insertStmt = $db->prepare("INSERT INTO prefixes (prefix, description) VALUES (?, ?)");
        foreach ($prefixes as $prefix) {
            if (!in_array($prefix, $existingPrefixes)) {
                $insertStmt->execute([$prefix, '从文件表自动同步']);
                $insertedCount++;
            }
        }

        echo json_encode([
            'success' => true,
            'message' => "同步完成，新增 {$insertedCount} 个前缀",
            'syncedPrefixes' => $prefixes,
            'insertedCount' => $insertedCount
        ]);
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['error' => '同步前缀失败: ' . $e->getMessage()]);
    }
}
