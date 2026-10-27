<?php
// 超级管理员API
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

// 确保日志目录存在
$logDir = __DIR__ . '/../logs';
if (!file_exists($logDir)) {
    mkdir($logDir, 0755, true);
}

header('Content-Type: application/json');
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


// 检查是否是超级管理员（只允许superadmin访问）
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'superadmin') {
    http_response_code(403);
    echo json_encode(['error' => '只有超级管理员可以访问此功能']);
    exit;
}

require_once '../config/database.php';

$database = new Database();
$db = $database->getConnection();

if (!$db) {
    http_response_code(503);
    echo json_encode(['success' => false, 'message' => '数据库连接失败，请检查配置或数据库服务'], JSON_UNESCAPED_UNICODE);
    exit;
}

// 获取请求方法和路径
require_once __DIR__ . '/csrf.php';
$method = isset($_SERVER['REQUEST_METHOD']) ? $_SERVER['REQUEST_METHOD'] : 'GET';
csrf_verify($method); // 写操作必须带 X-CSRF-Token
$path = isset($_SERVER['PATH_INFO']) ? $_SERVER['PATH_INFO'] : '/';
if ($path === '/' && isset($_SERVER['REQUEST_URI'])) {
    $uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
    $path = str_replace('/api/admin.php', '', $uri);
}

// 路由处理
switch ($path) {
    case '/check':
        if ($method === 'GET') {
            checkAdmin($db);
        }
        break;
    case '/users':
        if ($method === 'GET') {
            getUsers($db);
        } elseif ($method === 'POST') {
            createUser($db);
        }
        break;
    case '/users/reset-password':
        if ($method === 'POST') {
            resetPassword($db);
        }
        break;
    case '/users/delete':
        if ($method === 'POST') {
            deleteUser($db);
        }
        break;
    default:
        // 处理 /users/{id} 格式的路径
        if (preg_match('#^/users/(\d+)$#', $path, $matches)) {
            $userId = $matches[1];
            if ($method === 'GET') {
                getUser($db, $userId);
            } elseif ($method === 'PUT') {
                updateUser($db, $userId);
            } elseif ($method === 'DELETE') {
                deleteUser($db, $userId);
            }
        } else {
            http_response_code(404);
            echo json_encode(['error' => '未找到请求的资源']);
        }
        break;
}

// 检查管理员权限
function checkAdmin($db) {
    echo json_encode([
        'success' => true,
        'role' => $_SESSION['role']
    ]);
}

// 获取所有用户
function getUsers($db) {
    try {
        $query = "SELECT id, username, prefix, role, createdAt FROM users ORDER BY createdAt DESC";
        $stmt = $db->prepare($query);
        $stmt->execute();
        $users = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // 标记超级管理员
        foreach ($users as &$user) {
            $user['isSuperAdmin'] = ($user['role'] === 'superadmin');
        }

        echo json_encode(['success' => true, 'users' => $users]);
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['error' => '获取用户列表失败']);
    }
}



// 获取单个用户
function getUser($db, $userId) {
    try {
        $query = "SELECT id, username, prefix, role, createdAt FROM users WHERE id = ?";
        $stmt = $db->prepare($query);
        $stmt->execute([$userId]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user) {
            $user['isSuperAdmin'] = ($user['role'] === 'superadmin');
            echo json_encode(['success' => true, 'user' => $user]);
        } else {
            http_response_code(404);
            echo json_encode(['error' => '用户不存在']);
        }
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['error' => '获取用户信息失败']);
    }
}



// 创建用户
function createUser($db) {
    $data = json_decode(file_get_contents('php://input'), true);

    if (!isset($data['username']) || !isset($data['password']) || !isset($data['prefix'])) {
        http_response_code(400);
        echo json_encode(['error' => '缺少必填字段']);
        return;
    }

    $username = $data['username'];
    $password = $data['password'];
    $prefix = $data['prefix'];
    $role = isset($data['role']) ? $data['role'] : 'user';

    // 验证编号前缀格式（支持中文、字母、数字，长度2-10个字符）
    $pattern = '/^[\x{4e00}-\x{9fa5}A-Za-z0-9\-]{2,10}$/u';
    if (!preg_match($pattern, $prefix)) {
        http_response_code(400);
        echo json_encode(['error' => '编号前缀格式不正确，支持中文、字母、数字和横杠，长度2-10个字符']);
        return;
    }

    try {
        // 检查用户名是否已存在
        $stmt = $db->prepare("SELECT id FROM users WHERE username = ?");
        $stmt->execute([$username]);

        if ($stmt->fetch()) {
            http_response_code(400);
            echo json_encode(['error' => '用户名已存在']);
            return;
        }

        // 检查编号前缀是否已存在
        $stmt = $db->prepare("SELECT id FROM users WHERE prefix = ?");
        $stmt->execute([$prefix]);

        if ($stmt->fetch()) {
            http_response_code(400);
            echo json_encode(['error' => '编号前缀已被使用']);
            return;
        }

        // 创建新用户
        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $db->prepare("INSERT INTO users (username, password, prefix, role) VALUES (?, ?, ?, ?)");
        $stmt->execute([$username, $hashedPassword, $prefix, $role]);

        echo json_encode(['success' => true, 'message' => '用户创建成功']);
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['error' => '创建用户失败']);
    }
}



// 更新用户
function updateUser($db, $userId) {
    $data = json_decode(file_get_contents('php://input'), true);

    try {
        // 检查用户是否存在
        $stmt = $db->prepare("SELECT id, role FROM users WHERE id = ?");
        $stmt->execute([$userId]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$user) {
            http_response_code(404);
            echo json_encode(['error' => '用户不存在']);
            return;
        }

        // 不允许修改超级管理员的角色
        if ($user['role'] === 'superadmin' && isset($data['role']) && $data['role'] !== 'superadmin') {
            http_response_code(403);
            echo json_encode(['error' => '不能修改超级管理员的角色']);
            return;
        }

        // 构建更新语句
        $updates = [];
        $params = [];

        if (isset($data['username'])) {
            $updates[] = "username = ?";
            $params[] = $data['username'];
        }

        if (isset($data['prefix'])) {
    // 验证编号前缀格式（支持中文、字母、数字，长度2-10个字符）
    $pattern = '/^[\x{4e00}-\x{9fa5}A-Za-z0-9\-]{2,10}$/u';
    if (!preg_match($pattern, $prefix)) {
        http_response_code(400);
        echo json_encode(['error' => '编号前缀格式不正确，支持中文、字母、数字和横杠，长度2-10个字符']);
        return;
    }

            // 检查编号前缀是否已被其他用户使用
            $stmt = $db->prepare("SELECT id FROM users WHERE prefix = ? AND id != ?");
            $stmt->execute([$data['prefix'], $userId]);

            if ($stmt->fetch()) {
                http_response_code(400);
                echo json_encode(['error' => '编号前缀已被使用']);
                return;
            }

            $updates[] = "prefix = ?";
            $params[] = $data['prefix'];
        }

        if (isset($data['role'])) {
            $updates[] = "role = ?";
            $params[] = $data['role'];
        }

        if (empty($updates)) {
            http_response_code(400);
            echo json_encode(['error' => '没有要更新的字段']);
            return;
        }

        $params[] = $userId;
        $query = "UPDATE users SET " . implode(', ', $updates) . " WHERE id = ?";
        $stmt = $db->prepare($query);
        $stmt->execute($params);

        echo json_encode(['success' => true, 'message' => '用户更新成功']);
    } catch (PDOException $e) {
        http_response_code(500);
    }
}





// 重置密码
function resetPassword($db) {
    $data = json_decode(file_get_contents('php://input'), true);

    if (!isset($data['userId']) || !isset($data['newPassword'])) {
        http_response_code(400);
        echo json_encode(['error' => '缺少必填字段']);
        return;
    }

    $userId = $data['userId'];
    $newPassword = $data['newPassword'];

    try {
        // 检查用户是否存在
        $stmt = $db->prepare("SELECT id FROM users WHERE id = ?");
        $stmt->execute([$userId]);

        if (!$stmt->fetch()) {
            http_response_code(404);
            echo json_encode(['error' => '用户不存在']);
            return;
        }

        // 更新密码
        $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
        $stmt = $db->prepare("UPDATE users SET password = ? WHERE id = ?");
        $stmt->execute([$hashedPassword, $userId]);

        echo json_encode(['success' => true, 'message' => '密码重置成功']);
    } catch (PDOException $e) {
        http_response_code(500);
    }
}





// 删除用户
function deleteUser($db, $userId = null) {
    // 如果通过POST请求删除，从请求体获取userId
    if ($userId === null) {
        $data = json_decode(file_get_contents('php://input'), true);
        if (!isset($data['userId'])) {
            http_response_code(400);
            echo json_encode(['error' => '缺少用户ID']);
            return;
        }
        $userId = $data['userId'];
    }

    try {
        // 检查用户是否存在
        $stmt = $db->prepare("SELECT id, role FROM users WHERE id = ?");
        $stmt->execute([$userId]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$user) {
            http_response_code(404);
            echo json_encode(['error' => '用户不存在']);
            return;
        }

        // 不允许删除超级管理员
        if ($user['role'] === 'superadmin') {
            http_response_code(403);
            echo json_encode(['error' => '不能删除超级管理员']);
            return;
        }

        // 删除用户
        $stmt = $db->prepare("DELETE FROM users WHERE id = ?");
        $stmt->execute([$userId]);

        echo json_encode(['success' => true, 'message' => '用户删除成功']);
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['error' => '删除用户失败']);
    }
}

