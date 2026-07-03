# 数据库备份上传功能

## 概述

在 superadmin 管理界面添加数据库备份上传和导入功能。

---

## 一、创建 API 端点

### 创建 api/backup.php

功能：
1. 上传 .sql 备份文件
2. 执行 SQL 导入到数据库
3. 只有 superadmin 权限才能访问

```php
// 主要功能
- handleUpload() - 上传并导入 SQL 文件
- handleRestore() - 执行 SQL 导入
```

## 二、修改 admin.php

### 添加上传界面

在管理面板添加"数据库备份"标签页：
- 上传表单（支持 .sql 文件）
- 导入进度显示
- 操作结果反馈

## 三、具体任务清单

- [ ] 1. 创建 api/backup.php 处理备份上传
- [ ] 2. 修改 admin.php 添加备份管理界面
- [ ] 3. 添加前端上传和导入功能

## 四、安全要求

- 只有 superadmin 可以访问
- 验证上传文件类型（.sql）
- 限制文件大小（最大 50MB）
- 执行前确认操作
