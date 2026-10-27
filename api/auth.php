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
            // 登录成功后换一个新的 session id，避免 session fixation
            session_regenerate_id(true);

            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['prefix'] = $user['prefix'];
            $_SESSION['role'] = $user['role'];
            // 登录后重新签发 CSRF token（旧 token 随旧会话作废）
            $_SESSION['csrf_token'] = bin2hex(random_bytes(16));

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

    $username = isset($data['username']) ? trim($data['username']) : '';
    $password = isset($data['password']) ? $data['password'] : '';
    // 编号前缀改为选填：注册页不再让用户填，不传时由系统自动分配
    $prefix = isset($data['prefix']) ? trim($data['prefix']) : '';

    if ($username === '' || $password === '') {
        http_response_code(400);
        echo json_encode(['error' => '用户名和密码不能为空'], JSON_UNESCAPED_UNICODE);
        return;
    }

    // 只有显式传了前缀才做格式校验（兼容旧调用方）
    if ($prefix !== '' && !preg_match('/^[\x{4e00}-\x{9fa5}A-Za-z0-9\-]{2,10}$/u', $prefix)) {
        http_response_code(400);
        echo json_encode(['error' => '编号前缀格式不正确，支持中文、字母、数字和横杠，长度2-10个字符'], JSON_UNESCAPED_UNICODE);
        return;
    }

    try {
        $stmt = $db->prepare("SELECT id FROM users WHERE username = ?");
        $stmt->execute([$username]);

        if ($stmt->fetch()) {
            http_response_code(400);
            echo json_encode(['error' => '用户名已存在'], JSON_UNESCAPED_UNICODE);
            return;
        }

        if ($prefix !== '') {
            $stmt = $db->prepare("SELECT id FROM users WHERE prefix = ?");
            $stmt->execute([$prefix]);

            if ($stmt->fetch()) {
                http_response_code(400);
                echo json_encode(['error' => '编号前缀已被使用'], JSON_UNESCAPED_UNICODE);
                return;
            }
        }

        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
        $autoAssign = ($prefix === '');
        $finalPrefix = $prefix;
        $attempts = 0;

        // 自动分配时若撞上唯一键（并发注册），换下一个序号重试
        while (true) {
            $attempts++;
            if ($autoAssign) {
                $finalPrefix = generateUserPrefix($db);
            }

            try {
                $stmt = $db->prepare("INSERT INTO users (username, password, prefix) VALUES (?, ?, ?)");
                $stmt->execute([$username, $hashedPassword, $finalPrefix]);
                break;
            } catch (PDOException $e) {
                if ($autoAssign && $e->getCode() == '23000' && $attempts < 5) {
                    continue;
                }
                throw $e;
            }
        }

        // 新前缀同步进前缀池，登记文件时下拉里能直接选到
        $stmt = $db->prepare("INSERT IGNORE INTO prefixes (prefix, description) VALUES (?, ?)");
        $stmt->execute([$finalPrefix, '用户注册自动分配']);

        echo json_encode([
            'success' => true,
            'message' => '用户创建成功',
            'prefix' => $finalPrefix
        ], JSON_UNESCAPED_UNICODE);
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['error' => '数据库错误'], JSON_UNESCAPED_UNICODE);
    }
}

/**
 * 自动分配未被占用的用户编号前缀：U01-、U02-、U03- …
 * users 与 prefixes 两张表都查，避免与已存在的前缀冲突。
 */
function generateUserPrefix($db) {
    $used = array();

    foreach (array('SELECT prefix FROM users', 'SELECT prefix FROM prefixes') as $sql) {
        try {
            $rows = $db->query($sql)->fetchAll(PDO::FETCH_COLUMN);
        } catch (PDOException $e) {
            continue; // prefixes 表缺失时只按 users 判断
        }

        foreach ($rows as $p) {
            if (preg_match('/^U(\d+)-$/i', (string) $p, $m)) {
                $used[(int) $m[1]] = true;
            }
        }
    }

    for ($n = 1; $n <= 999; $n++) {
        if (!isset($used[$n])) {
            return sprintf('U%02d-', $n);
        }
    }

    // 兜底：U 段用满时退回时间戳
    return 'U' . substr((string) time(), -6) . '-';
}
?>