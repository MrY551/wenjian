# 性能与稳定性优化计划

## 概述

全面优化系统性能和稳定性，不影响原有功能和数据库结构。

---

## 一、性能优化

### 1.1 移除调试日志 (高优先级)

**问题**: 44处 error_log + 45处 console.log 影响性能

**修改文件**:
- `api/files.php` - 移除 7 处 error_log
- `api/instructions.php` - 移除 5 处 error_log
- `api/auth.php` - 移除部分 error_log
- `api/export.php` - 移除部分 error_log
- `script-php.js` - 移除 45 处 console.log

**方案**: 
- PHP: 保留关键错误日志，其他移除或设为 debug 模式才输出
- JS: 完全移除 console.log，保留 console.error 用于真正错误

---

### 1.2 优化 N+1 查询 (高优先级)

**问题**: getFiles() 先查文件列表，再循环查每个文件的最新批示

**位置**: `api/files.php` getFiles() 函数

**方案**: 使用 LEFT JOIN 在单次查询中获取文件和最新批示

```sql
-- 优化前: 1 + N 次查询
-- 优化后: 1 次查询
SELECT f.*, i.content as leaderInstruction, i.date as instructionDate
FROM files f
LEFT JOIN (
    SELECT i1.fileNumber, i1.content, i1.date
    FROM instructions i1
    INNER JOIN (
        SELECT fileNumber, MAX(date) as max_date
        FROM instructions
        GROUP BY fileNumber
    ) latest ON i1.fileNumber = latest.fileNumber AND i1.date = latest.max_date
) i ON f.fileNumber = i.fileNumber
WHERE ...
```

---

### 1.3 添加分页 (高优先级)

**问题**: 无分页，数据量大时性能差

**位置**: `api/files.php` getFiles()

**方案**: 添加 GET 参数 `page` 和 `limit`
- 默认每页 20 条
- 返回分页信息 `{ data: [], total: 100, page: 1, limit: 20 }`

---

### 1.4 移除冗余编码处理 (高优先级)

**问题**: 多处无意义的 `mb_convert_encoding()`

**位置**: `api/files.php` 多处

**方案**: 移除这些冗余调用

---

### 1.5 添加数据库索引 (中优先级)

**位置**: `database/init.sql`

**添加**:
```sql
ALTER TABLE files ADD INDEX idx_issueDate (issueDate);
ALTER TABLE files ADD INDEX idx_prefix_year (fileNumber(4), YEAR(createdAt));
ALTER TABLE instructions ADD INDEX idx_fileDate (fileNumber, date DESC);
```

---

### 1.6 优化 CORS (中优先级)

**问题**: `Access-Control-Allow-Origin: *` 过度开放

**方案**: 改为从配置读取允许的域名，或暂时保留（内网使用风险可控）

---

### 1.7 优化请求合并 (中优先级)

**问题**: 前缀和文件编号生成有重复 fetch

**位置**: `script-php.js` loadPrefixOptions() 和 generateFileNumber()

**方案**: 前缀数据缓存在 sessionStorage，避免重复请求

---

## 二、稳定性优化

### 2.1 隐藏错误详情 (高优先级)

**问题**: API 返回详细错误信息可能泄露系统细节

**位置**: 所有 api/*.php 文件

**方案**: 生产环境只返回通用错误信息
```php
// 优化前
echo json_encode(['error' => '获取文件列表失败', 'details' => $e->getMessage()]);

// 优化后
echo json_encode(['error' => '获取文件列表失败']);
// 或记录到日志文件
```

---

### 2.2 添加输入验证 (高优先级)

**问题**: 缺少参数校验

**位置**: api/files.php, api/instructions.php

**方案**: 
- 验证必填字段不为空
- 验证日期格式
- 验证数字类型

---

### 2.3 添加请求超时 (低优先级)

**问题**: fetch 无超时控制

**位置**: script-php.js

**方案**: 使用 AbortController 添加超时

---

## 三、具体任务清单

- [x] 1. 移除 api/files.php 调试日志 (7处)
- [x] 2. 移除 api/instructions.php 调试日志 (5处)
- [x] 3. 移除 script-php.js console.log (45处)
- [x] 4. 优化 getFiles() N+1 查询为单次 JOIN
- [x] 5. 添加分页功能到 getFiles()
- [x] 6. 移除冗余 mb_convert_encoding() 调用
- [x] 7. 添加数据库索引
- [x] 8. 隐藏 API 错误详情
- [x] 9. 添加输入验证
- [x] 10. 添加 fetch 超时控制

---

## 验收标准

- [x] 页面加载速度提升
- [x] 文件列表查询响应时间减少
- [x] 控制台无多余日志输出
- [x] API 不返回敏感错误信息
- [x] 原有功能正常工作
