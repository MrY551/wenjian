-- 数据库索引优化脚本
-- 为 files 表添加索引
CREATE INDEX idx_issueDate ON files(issueDate);
CREATE INDEX idx_createdAt ON files(createdAt);

-- 为 instructions 表添加复合索引
CREATE INDEX idx_fileDate ON instructions(fileNumber, date DESC);
