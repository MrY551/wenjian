<?php
// 测试数据库连接配置
require_once 'config/database.php';

echo "<h2>数据库配置检查</h2>";
echo "<pre>";

$database = new Database();

// 使用反射获取私有属性
$reflection = new ReflectionClass($database);

echo "当前配置:\n";
$hostProp = $reflection->getProperty('host');
$hostProp->setAccessible(true);
echo "Host: " . $hostProp->getValue($database) . "\n";

$dbNameProp = $reflection->getProperty('db_name');
$dbNameProp->setAccessible(true);
echo "DB Name: " . $dbNameProp->getValue($database) . "\n";

$usernameProp = $reflection->getProperty('username');
$usernameProp->setAccessible(true);
echo "Username: " . $usernameProp->getValue($database) . "\n";

$passwordProp = $reflection->getProperty('password');
$passwordProp->setAccessible(true);
echo "Password: " . $passwordProp->getValue($database) . "\n";

echo "\n<h3>测试连接</h3>";
$db = $database->getConnection();
if ($db) {
    echo "<span style='color:green;'>✓ 数据库连接成功</span>";
} else {
    echo "<span style='color:red;'>✗ 数据库连接失败</span>";
    echo "<br>检查日志: logs/php_errors.log";
}

echo "</pre>";
?>
