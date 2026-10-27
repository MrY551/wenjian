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
// Session诊断脚本
session_start();

echo "Session诊断信息\n";
echo "================\n\n";

echo "Session ID: " . session_id() . "\n";
echo "Session状态: " . session_status() . " (0=PHP_SESSION_DISABLED, 1=PHP_SESSION_NONE, 2=PHP_SESSION_ACTIVE)\n";
echo "Session名称: " . session_name() . "\n";
echo "Session保存路径: " . session_save_path() . "\n";

// 检查Session目录权限
$savePath = session_save_path();
if ($savePath && is_dir($savePath)) {
    echo "Session目录存在: 是\n";
    echo "Session目录可写: " . (is_writable($savePath) ? "是" : "否") . "\n";
} else {
    echo "Session目录存在: 否\n";
}

echo "\n当前Session数据:\n";
print_r($_SESSION);

// 设置测试Session
$_SESSION['test_timestamp'] = time();
$_SESSION['test_string'] = 'Hello Session';

echo "\n已设置测试Session数据\n";

// 检查Cookie
echo "\nCookie信息:\n";
if (isset($_COOKIE[session_name()])) {
    echo "Session Cookie: " . $_COOKIE[session_name()] . "\n";
} else {
    echo "Session Cookie: 未找到\n";
}

echo "\n所有Cookie:\n";
print_r($_COOKIE);

// 测试Session持久化
session_write_close();
session_start();

echo "\n重新读取Session数据:\n";
echo "test_timestamp: " . ($_SESSION['test_timestamp'] ?? '未找到') . "\n";
echo "test_string: " . ($_SESSION['test_string'] ?? '未找到') . "\n";

echo "\n诊断完成！\n";
?>