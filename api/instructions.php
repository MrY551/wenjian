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
function getInstructions($db) {
    try {
        $query = "SELECT * FROM instructions ORDER BY date DESC";
        $stmt = $db->prepare($query);
        $stmt->execute();
        $instructions = $stmt->fetchAll(PDO::FETCH_ASSOC);

        http_response_code(200);
        echo json_encode($instructions);
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['error' => '获取批示列表失败']);
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
        $query = "DELETE FROM instructions WHERE id = ?";
        $stmt = $db->prepare($query);
        $result = $stmt->execute([$id]);

        if ($result) {
            http_response_code(200);
            echo json_encode(['message' => '批示删除成功']);
        } else {
            http_response_code(500);
            echo json_encode(['error' => '批示删除失败']);
        }
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['error' => '批示删除失败']);
    }
}
?>
