# 文件编号登记系统 - 部署文档

## 一、系统要求

### 1.1 服务器环境

| 组件 | 版本要求 |
|------|----------|
| PHP | 7.0 或更高版本 |
| MySQL | 5.7 或更高版本 |
| Web服务器 | Apache / Nginx / IIS |
| 操作系统 | Windows / Linux |

### 1.2 PHP 扩展要求

- PDO
- PDO_MySQL
- JSON
- Session
- mbstring

---

## 二、部署步骤

### 2.1 上传文件

将项目文件上传到服务器的 Web 根目录，例如：
- **Apache/XAMPP**: `C:\xampp\htdocs\wenjian\`
- **Linux**: `/var/www/html/wenjian/`
- **phpStudy**: `G:\phpstudy_pro\WWW\wenjian\`

### 2.2 创建数据库

#### 方法一：通过 phpMyAdmin

1. 登录 phpMyAdmin（通常是 `http://localhost/phpmyadmin`）
2. 点击"新建"创建数据库
3. 输入数据库名称：`wenjian`
4. 选择字符集：`utf8mb4_unicode_ci`
5. 点击"创建"

#### 方法二：通过命令行

```sql
CREATE DATABASE IF NOT EXISTS `wenjian` 
DEFAULT CHARACTER SET utf8mb4 
COLLATE utf8mb4_unicode_ci;
```

### 2.3 创建数据库用户

#### 方法一：通过 phpMyAdmin

1. 点击"权限"或"用户账户"
2. 点击"添加用户账户"
3. 填写信息：
   - 用户名：`wenjian`
   - 主机：`localhost`
   - 密码：`123456`（建议使用强密码）
4. 在"数据库权限"中选择 `wenjian` 数据库
5. 勾选"全部权限"
6. 点击"执行"

#### 方法二：通过命令行

```sql
-- 创建用户
CREATE USER 'wenjian'@'localhost' IDENTIFIED BY '123456';

-- 授权
GRANT ALL PRIVILEGES ON wenjian.* TO 'wenjian'@'localhost';

-- 刷新权限
FLUSH PRIVILEGES;
```

### 2.4 配置数据库连接

编辑 `config/database.php` 文件：

```php
<?php
// 数据库配置文件
class Database {
    private $host = 'localhost';        // 数据库主机（如有端口：localhost:3306）
    private $db_name = 'wenjian';       // 数据库名
    private $username = 'wenjian';      // 数据库用户名
    private $password = '123456';       // 数据库密码
    private $charset = 'utf8mb4';       // 字符集
    public $conn;

    // 获取数据库连接
    public function getConnection() {
        $this->conn = null;
        try {
            $dsn = 'mysql:host=' . $this->host . ';dbname=' . $this->db_name . ';charset=' . $this->charset;
            $options = array(
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            );
            $this->conn = new PDO($dsn, $this->username, $this->password, $options);
        } catch(PDOException $exception) {
            error_log('数据库连接失败: ' . $exception->getMessage());
        }
        return $this->conn;
    }
}
?>
```

### 2.5 初始化数据库

访问 `http://your-domain.com/setup.php`

填写数据库配置信息：
- 数据库主机：`localhost`（如有端口：`localhost:3306`）
- 数据库名称：`wenjian`
- 用户名：`wenjian`
- 密码：`123456`

点击"测试连接并配置"按钮，系统会自动：
1. 创建数据库表
2. 插入默认用户
3. 更新配置文件

---

## 三、默认账户

| 用户名 | 密码 | 角色 | 说明 |
|--------|------|------|------|
| superadmin | 123456 | 超级管理员 | 拥有所有权限 |
| admin | 123456 | 普通用户 | 基本操作权限 |
| user1 | 123456 | 普通用户 | 基本操作权限 |
| user2 | 123456 | 普通用户 | 基本操作权限 |

**重要：首次登录后请立即修改默认密码！**

---

## 四、目录权限

### 4.1 Linux 服务器

```bash
# 设置目录权限
chmod -R 755 /var/www/html/wenjian/
chmod -R 777 /var/www/html/wenjian/logs/

# 设置文件所有者
chown -R www-data:www-data /var/www/html/wenjian/
```

### 4.2 Windows 服务器

确保以下目录可写：
- `logs/` - 存储错误日志

---

## 五、Web 服务器配置

### 5.1 Apache 配置

创建 `.htaccess` 文件（项目根目录）：

```apache
# 启用 URL 重写
RewriteEngine On

# 强制 HTTPS（可选）
# RewriteCond %{HTTPS} off
# RewriteRule ^(.*)$ https://%{HTTP_HOST}%{REQUEST_URI} [L,R=301]

# 禁止访问配置文件
<FilesMatch "\.(php)$">
    <IfModule mod_rewrite.c>
        RewriteRule ^config/ - [F,L]
    </IfModule>
</FilesMatch>

# 禁止目录列表
Options -Indexes

# 设置默认字符集
AddDefaultCharset UTF-8
```

### 5.2 Nginx 配置

```nginx
server {
    listen 80;
    server_name your-domain.com;
    root /var/www/html/wenjian;
    index index.php index.html;

    # 字符集
    charset utf-8;

    # 日志
    access_log /var/log/nginx/wenjian_access.log;
    error_log /var/log/nginx/wenjian_error.log;

    # 禁止访问配置文件
    location ~ /config/ {
        deny all;
        return 404;
    }

    # 禁止访问日志文件
    location ~ /logs/ {
        deny all;
        return 404;
    }

    # PHP 处理
    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php7.4-fpm.sock;
        fastcgi_index index.php;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        include fastcgi_params;
    }

    # 禁止访问隐藏文件
    location ~ /\. {
        deny all;
        access_log off;
        log_not_found off;
    }
}
```

### 5.3 IIS 配置

创建 `web.config` 文件（项目根目录）：

```xml
<?xml version="1.0" encoding="UTF-8"?>
<configuration>
    <system.webServer>
        <rewrite>
            <rules>
                <rule name="Protect config" stopProcessing="true">
                    <match url="^config/" />
                    <action type="CustomResponse" statusCode="403" />
                </rule>
                <rule name="Protect logs" stopProcessing="true">
                    <match url="^logs/" />
                    <action type="CustomResponse" statusCode="403" />
                </rule>
            </rules>
        </rewrite>
        <directoryBrowse enabled="false" />
    </system.webServer>
</configuration>
```

---

## 六、安全配置

### 6.1 修改默认密码

登录后立即修改所有默认用户的密码。

### 6.2 限制访问 IP（可选）

在 `.htaccess` 中添加：

```apache
# 只允许特定 IP 访问管理页面
<Files "admin.php">
    Order Deny,Allow
    Deny from all
    Allow from 192.168.1.100
</Files>
```

### 6.3 启用 HTTPS

1. 获取 SSL 证书
2. 配置 Web 服务器启用 HTTPS
3. 强制 HTTP 重定向到 HTTPS

### 6.4 定期备份

建议每天自动备份数据库：

```bash
# 创建备份脚本
#!/bin/bash
DATE=$(date +%Y%m%d_%H%M%S)
mysqldump -u wenjian -p123456 wenjian > /backup/wenjian_$DATE.sql

# 保留最近 30 天的备份
find /backup -name "wenjian_*.sql" -mtime +30 -delete
```

---

## 七、故障排查

### 7.1 数据库连接失败

**症状**：登录时显示 500 错误

**检查步骤**：
1. 确认 MySQL 服务已启动
2. 确认数据库用户名和密码正确
3. 确认数据库名正确
4. 检查端口是否正确（默认 3306）

**测试数据库连接**：
访问 `http://your-domain.com/check_config.php`

### 7.2 页面显示 500 错误

**检查错误日志**：
- PHP 错误日志：`logs/php_errors.log`
- Web 服务器错误日志

**常见原因**：
1. PHP 语法错误
2. 数据库连接失败
3. 文件权限问题

### 7.3 页面显示 403 错误

**检查文件权限**：
- 确保 Web 服务器用户有读取权限
- 确保目录权限为 755

### 7.4 页面显示 404 错误

**检查 URL 重写**：
- 确认 `.htaccess` 文件存在
- 确认 Apache 已启用 `mod_rewrite`

---

## 八、系统功能

### 8.1 文件管理

- 创建文件记录
- 编辑文件信息
- 删除文件
- 搜索和筛选
- 导出 Excel

### 8.2 批示管理

- 添加批示
- 编辑批示
- 删除批示

### 8.3 用户管理（超级管理员）

- 添加用户
- 编辑用户信息
- 重置密码
- 删除用户
- 数据库备份与恢复

### 8.4 前缀管理（超级管理员）

- 添加前缀
- 编辑前缀
- 删除前缀

---

## 九、技术支持

如有问题，请检查：
1. 错误日志文件
2. 浏览器控制台
3. PHP 错误日志
4. Web 服务器日志

---

## 十、更新日志

### 版本 1.0
- 初始版本
- 文件管理功能
- 用户管理功能
- 数据库备份与恢复功能
- 支持中文前缀
