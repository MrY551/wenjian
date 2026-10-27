<?php
// 浏览器访问需要登录；命令行可直接运行
if (PHP_SAPI !== 'cli') {
    ini_set('session.cookie_httponly', 1);
    ini_set('session.use_only_cookies', 1);
    ini_set('session.cookie_samesite', 'Lax');
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    if (!isset($_SESSION['user_id'])) {
        http_response_code(403);
        exit('403 需要登录');
    }
}
// 测试登录功能
header('Content-Type: application/json; charset=utf-8');

echo "<h2>登录测试</h2>";
echo "<pre>";

// 包含数据库配置
require_once __DIR__ . '/../config/database.php';

// 创建数据库连接
$database = new Database();
$db = $database->getConnection();

if (!$db) {
    die("数据库连接失败");
}

echo "✓ 数据库连接成功\n\n";

// 测试查询用户
$username = 'superadmin';
$password = '123456';

echo "测试用户: $username\n";
echo "测试密码: $password\n\n";

try {
    $stmt = $db->prepare("SELECT id, username, password, prefix, role FROM users WHERE username = :username");
    $stmt->bindParam(':username', $username);
    $stmt->execute();
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($user) {
        echo "✓ 找到用户\n";
        echo "  ID: " . $user['id'] . "\n";
        echo "  用户名: " . $user['username'] . "\n";
        echo "  前缀: " . $user['prefix'] . "\n";
        echo "  角色: " . $user['role'] . "\n";
        echo "  密码哈希: " . substr($user['password'], 0, 20) . "...\n\n";

        // 验证密码
        if (password_verify($password, $user['password'])) {
            echo "✓ 密码验证成功\n";
        } else {
            echo "✗ 密码验证失败\n";
        }
    } else {
        echo "✗ 未找到用户\n";
    }
} catch (PDOException $e) {
    echo "✗ 数据库错误: " . $e->getMessage() . "\n";
}

echo "</pre>";
?>
