<?php
// 仅允许命令行执行：本脚本会 DROP/重建表或批量改写数据
if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('403 此脚本只能通过命令行执行');
}
// 数据库修复脚本
require_once __DIR__ . '/../config/database.php';

$database = new Database();
$db = $database->getConnection();

if (!$db) {
    die("数据库连接失败<br>");
}

try {
    // 删除旧表
    echo "正在删除旧表...<br>";
    $db->exec("DROP TABLE IF EXISTS instructions");
    $db->exec("DROP TABLE IF EXISTS files");
    echo "旧表已删除<br><br>";

    // 创建文件表
    echo "正在创建files表...<br>";
    $db->exec("CREATE TABLE files (
        id INT AUTO_INCREMENT PRIMARY KEY,
        fileNumber VARCHAR(100) NOT NULL UNIQUE,
        fileName VARCHAR(255) NOT NULL,
        fileContent TEXT NOT NULL,
        submitter VARCHAR(100) COMMENT '呈办人',
        approver01 VARCHAR(20) DEFAULT '未签批',
        approver02 VARCHAR(20) DEFAULT '未签批',
        approver03 VARCHAR(20) DEFAULT '未签批',
        approver04 VARCHAR(20) DEFAULT '未签批',
        approver05 VARCHAR(20) DEFAULT '未签批',
        approver06 VARCHAR(20) DEFAULT '未签批',
        approver07 VARCHAR(20) DEFAULT '未签批',
        leaderInstruction TEXT,
        createdAt DATETIME DEFAULT CURRENT_TIMESTAMP,
        updatedAt DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_fileNumber (fileNumber),
        INDEX idx_updatedAt (updatedAt)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    echo "files表创建成功<br><br>";

    // 创建批示表
    echo "正在创建instructions表...<br>";
    $db->exec("CREATE TABLE instructions (
        id INT AUTO_INCREMENT PRIMARY KEY,
        fileNumber VARCHAR(100) NOT NULL,
        leaderName VARCHAR(100) NOT NULL,
        content TEXT NOT NULL,
        date DATE NOT NULL,
        createdAt DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (fileNumber) REFERENCES files(fileNumber) ON DELETE CASCADE,
        INDEX idx_fileNumber (fileNumber),
        INDEX idx_date (date)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    echo "instructions表创建成功<br><br>";

    // 插入测试数据
    echo "正在插入测试数据...<br>";
    $stmt = $db->prepare("INSERT INTO files (fileNumber, fileName, fileContent, submitter, approver01, approver02, approver03, approver04, approver05, approver06, approver07, leaderInstruction)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");

    $stmt->execute(['TEST-001', '测试文件1', '这是测试文件1的内容', null, '已签批', '已签批', '未签批', '未签批', '未签批', '未签批', '未签批', '请尽快处理']);
    $stmt->execute(['TEST-002', '测试文件2', '这是测试文件2的内容', null, '未签批', '未签批', '未签批', '未签批', '未签批', '未签批', '未签批', null]);

    $stmt = $db->prepare("INSERT INTO instructions (fileNumber, leaderName, content, date) VALUES (?, ?, ?, ?)");
    $stmt->execute(['TEST-001', '张局长', '同意，请按照规定办理', '2024-01-01']);
    $stmt->execute(['TEST-001', '李主任', '已阅，同意执行', '2024-01-02']);

    echo "测试数据插入成功<br><br>";

    echo "<strong>数据库修复完成！</strong><br>";
    echo "<a href='index.php'>返回首页</a>";

} catch (PDOException $e) {
    die("错误: " . $e->getMessage() . "<br>");
}
?>