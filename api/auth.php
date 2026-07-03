<?php
// 用户认证API
ini_set('session.cookie_httponly', 1);
ini_set('session.use_only_cookies', 1);
ini_set('session.cookie_samesite', 'Lax');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/../logs/php_errors.log');

$logDir = __DIR__ . '/../logs';
if (!file_exists($logDir)) {
    mkdir($logDir, 0755, true);
}

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

require_once '../config/database.php';

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

$database = new Database();
$db = $database->getConnection();

$method = $_SERVER['REQUEST_METHOD'];
$path = isset($_SERVER['PATH_INFO']) ? $_SERVER['PATH_INFO'] : '/';
if ($path === '/' && isset($_SERVER['REQUEST_URI'])) {
    $uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
    $path = str_replace('/api/auth.php', '', $uri);
}

switch ($path) {
    case '/login':
        if ($method === 'POST') {
            handleLogin($db);
        }
        break;
    case '/logout':
        if ($method === 'POST') {
            handleLogout();
        }
        break;
    case '/check':
        if ($method === 'GET') {
            handleCheckAuth();
        }
        break;
    case '/register':
        if ($method === 'POST') {
            handleRegister($db);
        }
        break;
    default:
        http_response_code(404);
        echo json_encode(['error' => '未找到请求的资源']);
        break;
}

function handleLogin($db) {
    $data = json_decode(file_get_contents('php://input'), true);

    if (!isset($data['username']) || !isset($data['password'])) {
        http_response_code(400);
        echo json_encode(['error' => '用户名和密码不能为空']);
        return;
    }

    $username = $data['username'];
    $password = $data['password'];

    try {
        $stmt = $db->prepare("SELECT id, username, password, prefix, role FROM users WHERE username = :username");
        $stmt->bindParam(':username', $username);
        $stmt->execute();
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user && password_verify($password, $user['password'])) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['prefix'] = $user['prefix'];
            $_SESSION['role'] = $user['role'];

            echo json_encode([
                'success' => true,
                'user' => [
                    'id' => $user['id'],
                    'username' => $user['username'],
                    'prefix' => $user['prefix'],
                    'role' => $user['role']
                ]
            ]);
        } else {
            http_response_code(401);
            echo json_encode(['error' => '用户名或密码错误']);
        }
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['error' => '数据库错误']);
    }
}

function handleLogout() {
    session_destroy();
    echo json_encode(['success' => true]);
}

function handleCheckAuth() {
    if (isset($_SESSION['user_id'])) {
        echo json_encode([
            'success' => true,
            'user' => [
                'id' => $_SESSION['user_id'],
                'username' => $_SESSION['username'],
                'prefix' => $_SESSION['prefix']
            ]
        ]);
    } else {
        http_response_code(401);
        echo json_encode(['error' => '未登录']);
    }
}

function handleRegister($db) {
    $data = json_decode(file_get_contents('php://input'), true);

    if (!isset($data['username']) || !isset($data['password']) || !isset($data['prefix'])) {
        http_response_code(400);
        echo json_encode(['error' => '用户名、密码和编号前缀不能为空']);
        return;
    }

    $username = $data['username'];
    $password = $data['password'];
    $prefix = $data['prefix'];

    if (!preg_match('/^[\x{4e00}-\x{9fa5}A-Za-z0-9]{2,10}$/u', $prefix)) {
        http_response_code(400);
        echo json_encode(['error' => '编号前缀格式不正确，支持中文、字母、数字，长度2-10个字符']);
        return;
    }

    try {
        $stmt = $db->prepare("SELECT id FROM users WHERE username = :username");
        $stmt->bindParam(':username', $username);
        $stmt->execute();

        if ($stmt->fetch()) {
            http_response_code(400);
            echo json_encode(['error' => '用户名已存在']);
            return;
        }

        $stmt = $db->prepare("SELECT id FROM users WHERE prefix = :prefix");
        $stmt->bindParam(':prefix', $prefix);
        $stmt->execute();

        if ($stmt->fetch()) {
            http_response_code(400);
            echo json_encode(['error' => '编号前缀已被使用']);
            return;
        }

        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $db->prepare("INSERT INTO users (username, password, prefix) VALUES (:username, :password, :prefix)");
        $stmt->bindParam(':username', $username);
        $stmt->bindParam(':password', $hashedPassword);
        $stmt->bindParam(':prefix', $prefix);

        if ($stmt->execute()) {
            echo json_encode(['success' => true, 'message' => '用户创建成功']);
        } else {
            http_response_code(500);
            echo json_encode(['error' => '创建用户失败']);
        }
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['error' => '数据库错误']);
    }
}
?>