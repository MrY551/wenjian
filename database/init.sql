-- 创建数据库
CREATE DATABASE IF NOT EXISTS file_management DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

USE file_management;

-- 文件表
CREATE TABLE IF NOT EXISTS files (
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
    INDEX idx_fileNumber (fileNumber),
    INDEX idx_userId (userId),
    INDEX idx_updatedAt (updatedAt),
    FOREIGN KEY (userId) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 首长批示表
CREATE TABLE IF NOT EXISTS instructions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    fileNumber VARCHAR(100) NOT NULL,
    leaderName VARCHAR(100) NOT NULL,
    content TEXT NOT NULL,
    date DATE NOT NULL,
    createdAt DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (fileNumber) REFERENCES files(fileNumber) ON DELETE CASCADE,
    INDEX idx_fileNumber (fileNumber),
    INDEX idx_date (date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 插入测试数据（需要先创建用户后才能插入，这里暂时注释掉）
-- INSERT INTO files (userId, fileNumber, fileName, fileContent, approver01, approver02, approver03, approver04, approver05, approver06, approver07, leaderInstruction)
-- VALUES 
-- (1, 'TEST-001', '测试文件1', '这是测试文件1的内容', '已签批', '已签批', '未签批', '未签批', '未签批', '未签批', '未签批', '请尽快处理'),
-- (1, 'TEST-002', '测试文件2', '这是测试文件2的内容', '未签批', '未签批', '未签批', '未签批', '未签批', '未签批', '未签批', NULL);

INSERT INTO instructions (fileNumber, leaderName, content, date)
VALUES 
('TEST-001', '张局长', '同意，请按照规定办理', '2024-01-01'),
('TEST-001', '李主任', '已阅，同意执行', '2024-01-02');
