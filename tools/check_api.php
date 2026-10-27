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
// 测试 API 配置
echo "<h2>API 配置检查</h2>";
echo "<pre>";

// 检查 config/database.php
echo "1. 检查 config/database.php:\n";
if (file_exists('config/database.php')) {
    echo "   ✓ 文件存在\n";
    require_once __DIR__ . '/../config/database.php';
    $database = new Database();
    $db = $database->getConnection();
    if ($db) {
        echo "   ✓ 数据库连接成功\n";
    } else {
        echo "   ✗ 数据库连接失败\n";
    }
} else {
    echo "   ✗ 文件不存在\n";
}

// 检查 api/auth.php
echo "\n2. 检查 api/auth.php:\n";
if (file_exists('api/auth.php')) {
    echo "   ✓ 文件存在\n";
    $content = file_get_contents('api/auth.php');
    if (strpos($content, 'handleLogin') !== false) {
        echo "   ✓ 包含 handleLogin 函数\n";
    } else {
        echo "   ✗ 缺少 handleLogin 函数\n";
    }
    if (strpos($content, 'require_once') !== false) {
        echo "   ✓ 包含 require_once\n";
    } else {
        echo "   ✗ 缺少 require_once\n";
    }
} else {
    echo "   ✗ 文件不存在\n";
}

echo "</pre>";
?>
