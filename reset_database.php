<?php
// 重置数据库脚本
require_once 'config/database.php';

try {
    $database = new Database();
    $db = $database->getConnection();
    
    echo "开始重置数据库...\n";
    
    // 删除所有表（按正确的外键约束顺序）
    $db->exec("DROP TABLE IF EXISTS instructions");
    $db->exec("DROP TABLE IF EXISTS files"); 
    $db->exec("DROP TABLE IF EXISTS users");
    $db->exec("DROP TABLE IF EXISTS prefixes");
    
    echo "✓ 删除所有表\n";
    
    // 重新创建表结构（按正确的顺序）
    // 先创建用户表
    $db->exec("CREATE TABLE IF NOT EXISTS users (
        id INT AUTO_INCREMENT PRIMARY KEY,
        username VARCHAR(50) NOT NULL UNIQUE,
        password VARCHAR(255) NOT NULL,
        prefix VARCHAR(10) NOT NULL UNIQUE,
        role VARCHAR(20) DEFAULT 'user',
        createdAt DATETIME DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    
    echo "✓ 创建用户表\n";
    
    // 创建前缀表
    $db->exec("CREATE TABLE IF NOT EXISTS prefixes (
        id INT AUTO_INCREMENT PRIMARY KEY,
        prefix VARCHAR(10) NOT NULL UNIQUE,
        description VARCHAR(100),
        createdAt DATETIME DEFAULT CURRENT_TIMESTAMP,
        updatedAt DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    
    echo "✓ 创建前缀表\n";
    
    // 创建文件表（包含外键约束）
    $db->exec("CREATE TABLE IF NOT EXISTS files (
        id INT AUTO_INCREMENT PRIMARY KEY,
        userId INT NOT NULL,
        fileNumber VARCHAR(100) NOT NULL UNIQUE,
        fileName VARCHAR(255) NOT NULL,
        fileContent TEXT NOT NULL,
        submitter VARCHAR(100),
        issueDate DATE NOT NULL COMMENT '下发时间',
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
        INDEX idx_userId (userId),
        INDEX idx_fileNumber (fileNumber),
        FOREIGN KEY (userId) REFERENCES users(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    
    echo "✓ 创建文件表\n";
    
    // 创建批示表
    $db->exec("CREATE TABLE IF NOT EXISTS instructions (
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
    
    echo "✓ 创建批示表\n";
    
    // 插入默认数据
    $defaultPassword = password_hash('123456', PASSWORD_DEFAULT);
    
    // 插入默认用户（admin现在只是普通用户，没有管理权限）
    $db->exec("INSERT INTO users (username, password, prefix, role) VALUES
        ('superadmin', '$defaultPassword', 'SA-', 'superadmin'),
        ('admin', '$defaultPassword', 'AD-', 'user'),
        ('user1', '$defaultPassword', 'U01-', 'user'),
        ('user2', '$defaultPassword', 'U02-', 'user')");
    
    echo "✓ 插入默认用户\n";
    
    // 插入默认前缀
    $db->exec("INSERT INTO prefixes (prefix, description) VALUES
        ('站参', '站参文件前缀'),
        ('参保', '参保文件前缀')");
    
    echo "✓ 插入默认前缀\n";
    
    // 插入测试文件（先获取用户ID）
    $stmt = $db->prepare("SELECT id FROM users WHERE username = 'superadmin'");
    $stmt->execute();
    $superadminId = $stmt->fetchColumn();
    
    if ($superadminId) {
        $db->exec("INSERT INTO files (userId, fileNumber, fileName, fileContent, submitter, issueDate, approver01, approver02, approver03, approver04, approver05, approver06, approver07, leaderInstruction)
            VALUES 
            ($superadminId, 'SA-001', '超级管理员文件1', '这是超级管理员的第一个文件', '超级管理员', '2024-01-01', '已签批', '已签批', '未签批', '未签批', '未签批', '未签批', '未签批', '重要文件请尽快处理'),
            ($superadminId, 'SA-002', '超级管理员文件2', '这是超级管理员的第二个文件', '超级管理员', '2024-01-02', '未签批', '未签批', '未签批', '未签批', '未签批', '未签批', '未签批', NULL)");
        
        // 为user1插入文件
        $stmt = $db->prepare("SELECT id FROM users WHERE username = 'user1'");
        $stmt->execute();
        $user1Id = $stmt->fetchColumn();
        
        if ($user1Id) {
            $db->exec("INSERT INTO files (userId, fileNumber, fileName, fileContent, submitter, issueDate, approver01, approver02, approver03, approver04, approver05, approver06, approver07, leaderInstruction)
                VALUES 
                ($user1Id, 'U01-001', '用户1文件1', '这是用户1的第一个文件', '用户1', '2024-01-03', '已签批', '未签批', '未签批', '未签批', '未签批', '未签批', '未签批', '用户1的文件请处理'),
                ($user1Id, 'U01-002', '用户1文件2', '这是用户1的第二个文件', '用户1', '2024-01-04', '未签批', '未签批', '未签批', '未签批', '未签批', '未签批', '未签批', NULL)");
        }
        
        // 为user2插入文件
        $stmt = $db->prepare("SELECT id FROM users WHERE username = 'user2'");
        $stmt->execute();
        $user2Id = $stmt->fetchColumn();
        
        if ($user2Id) {
            $db->exec("INSERT INTO files (userId, fileNumber, fileName, fileContent, submitter, issueDate, approver01, approver02, approver03, approver04, approver05, approver06, approver07, leaderInstruction)
                VALUES 
                ($user2Id, 'U02-001', '用户2文件1', '这是用户2的第一个文件', '用户2', '2024-01-05', '已签批', '已签批', '已签批', '未签批', '未签批', '未签批', '未签批', '用户2的重要文件'),
                ($user2Id, 'U02-002', '用户2文件2', '这是用户2的第二个文件', '用户2', '2024-01-06', '未签批', '未签批', '未签批', '未签批', '未签批', '未签批', '未签批', NULL)");
        }
        
        echo "✓ 插入测试文件\n";
        
        // 插入批示数据
        $db->exec("INSERT INTO instructions (fileNumber, leaderName, content, date)
            VALUES 
            ('SA-001', '张局长', '同意，请按照规定办理', '2024-01-01'),
            ('SA-001', '李主任', '已阅，同意执行', '2024-01-02'),
            ('U01-001', '王处长', '用户1的文件处理得很好', '2024-01-03'),
            ('U02-001', '赵部长', '用户2的文件需要进一步审查', '2024-01-05')");
        
        echo "✓ 插入批示数据\n";
    }
    
    echo "\n✅ 数据库重置完成！\n";
    echo "默认用户账号：\n";
    echo "- superadmin / 123456 (超级管理员)\n";
    echo "- admin / 123456 (管理员)\n"; 
    echo "- user1 / 123456 (普通用户)\n";
    echo "- user2 / 123456 (普通用户)\n";
    
} catch (PDOException $e) {
    echo "❌ 重置数据库失败: " . $e->getMessage() . "\n";
}
?>