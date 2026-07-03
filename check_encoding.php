<?php
// 检查数据库编码和数据
require_once 'config/database.php';

echo "<h2>数据库编码检查</h2>";
echo "<pre>";

$database = new Database();
$db = $database->getConnection();

if (!$db) {
    die("数据库连接失败");
}

// 检查数据库字符集
$stmt = $db->query("SELECT @@character_set_database, @@collation_database");
$charset = $stmt->fetch(PDO::FETCH_ASSOC);
echo "数据库字符集: " . $charset['@@character_set_database'] . "\n";
echo "数据库排序规则: " . $charset['@@collation_database'] . "\n\n";

// 检查表字符集
$stmt = $db->query("SELECT TABLE_NAME, TABLE_COLLATION FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE()");
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    echo "表 " . $row['TABLE_NAME'] . " 字符集: " . $row['TABLE_COLLATION'] . "\n";
}

echo "\n";

// 检查 users 表中的前缀数据
$stmt = $db->query("SELECT username, prefix, HEX(prefix) as hex_prefix FROM users LIMIT 5");
$users = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "Users 表前缀数据:\n";
foreach ($users as $user) {
    echo $user['username'] . ": prefix=" . $user['prefix'] . " hex=" . $user['hex_prefix'] . "\n";
}

echo "\n";

// 检查 files 表中的前缀数据
$stmt = $db->query("SELECT fileNumber, HEX(fileNumber) as hex_fileNumber FROM files LIMIT 5");
$files = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "Files 表文件编号数据:\n";
foreach ($files as $file) {
    echo "fileNumber=" . $file['fileNumber'] . " hex=" . $file['hex_fileNumber'] . "\n";
}

echo "\n";

// 检查 prefixes 表
$stmt = $db->query("SELECT prefix, HEX(prefix) as hex_prefix FROM prefixes LIMIT 5");
$prefixes = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "Prefixes 表数据:\n";
foreach ($prefixes as $prefix) {
    echo "prefix=" . $prefix['prefix'] . " hex=" . $prefix['hex_prefix'] . "\n";
}

echo "</pre>";
?>
