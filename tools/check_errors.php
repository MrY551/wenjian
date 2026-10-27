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
// 检查PHP错误日志
$logFile = __DIR__ . '/../logs/php_errors.log';

echo "正在检查PHP错误日志...\n\n";

if (file_exists($logFile)) {
    $logs = file($logFile);
    $recentLogs = array_slice($logs, -20); // 获取最后20行
    echo "最近的错误日志：\n";
    echo "========================================\n";
    foreach ($recentLogs as $log) {
        echo $log;
    }
} else {
    echo "❌ 错误日志文件不存在: logs/php_errors.log\n";
    echo "尝试开启错误显示...\n";
    
    // 临时开启错误显示
    ini_set('display_errors', 1);
    error_reporting(E_ALL);
    
    echo "✅ 已开启错误显示，请刷新页面查看具体错误信息\n";
}

// 检查数据库连接
echo "\n========================================\n";
echo "检查数据库连接...\n";

try {
    require_once __DIR__ . '/../config/database.php';
    $database = new Database();
    $db = $database->getConnection();
    echo "✅ 数据库连接成功\n";
} catch (PDOException $e) {
    echo "❌ 数据库连接失败: " . $e->getMessage() . "\n";
}

echo "\n========================================\n";
echo "检查Session状态...\n";
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
echo "Session ID: " . session_id() . "\n";
echo "Session 数据: " . print_r($_SESSION, true) . "\n";
?>