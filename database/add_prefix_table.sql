-- 创建前缀表
CREATE TABLE IF NOT EXISTS prefixes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    prefix VARCHAR(10) NOT NULL UNIQUE,
    description VARCHAR(100),
    createdAt DATETIME DEFAULT CURRENT_TIMESTAMP,
    updatedAt DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 插入默认前缀
INSERT INTO prefixes (prefix, description) VALUES
('站参', '站参文件前缀'),
('参保', '参保文件前缀');
