# 文件编号登记系统

PHP + MySQL 的单体应用。登记公文编号、记录呈办情况、填写领导批示，支持按用户隔离数据、按前缀自动生成编号、导出 Excel。

## 功能

- 文件登记：选前缀自动生成编号（`前缀2026【001】`），记录文件名、内容、呈办人、下发时间、逐级签批状态
- 文件查询：按编号/文件名搜索，按签批状态、前缀、年份、日期区间筛选，分页浏览
- 领导批示：在查询列表点「查看」填写或修改批示，批示人取当前登录账号、日期取当天
- 用户管理：超级管理员可增删用户、重置密码、维护编号前缀
- 数据库备份：导出完整 SQL、导入 `.sql` 备份（导入前自动快照）
- 导出 Excel：生成真正的 xlsx（表头样式、边框、列宽、冻结首行、自动筛选）

## 环境要求

| 组件 | 版本 | 必需扩展 |
|---|---|---|
| PHP | 7.3+ | `pdo_mysql`、`mbstring`；`zip` 或 `zlib`（任一即可，导出 xlsx 用） |
| MySQL | 5.7+ / 8.0 | — |

没有 `zip` 扩展也能导出 xlsx：`api/xlsx.php` 内置纯 PHP 的 ZIP 打包器，有 `zlib` 走 deflate，没有就 STORED。

## 部署

### 1. 建库

```bash
mysql -u root -p < database/init.sql
```

`database/init.sql` 会创建数据库 `wenjian`、四张表（`users` / `files` / `instructions` / `prefixes`）和索引。

### 2. 改配置

编辑 `config/database.php`：

```php
private $host     = 'localhost:3306';
private $db_name  = 'wenjian';
private $username = 'root';
private $password = '你的密码';
```

### 3. 建首个账号

系统没有默认账号，首次使用走注册页 `register.php`，注册出来的用户是普通用户。把某个账号提成超级管理员：

```sql
UPDATE users SET role = 'superadmin' WHERE username = '你的用户名';
```

### 4. 启动

```bash
# 开发
php -S 0.0.0.0:8123 -t .

# 生产：把站点根目录指向本项目，PHP-FPM + Nginx/Apache
```

## 目录结构

```
├── index.php            主界面（登记 / 查询两个标签页）
├── login.php            登录页（左侧动态角色 + 右侧表单）
├── register.php         注册页
├── admin.php            管理后台（用户 / 前缀 / 备份），仅超级管理员
├── api/
│   ├── auth.php         登录、登出、注册、会话检查
│   ├── files.php        文件的增删改查、编号生成
│   ├── instructions.php 批示的增删改查
│   ├── admin.php        用户管理接口
│   ├── prefixes.php     前缀管理接口
│   ├── backup.php       数据库导出 / 导入
│   ├── export.php       导出 Excel
│   ├── health.php       健康检查
│   ├── csrf.php         CSRF token 生成与校验
│   └── xlsx.php         零依赖 xlsx 生成器
├── config/database.php  数据库连接
├── database/init.sql    建表语句
├── css/
│   ├── theme.css        设计令牌（颜色、圆角、阴影的唯一来源）
│   ├── login.css        登录页
│   └── register.css     注册页
├── styles.css           主界面样式
├── js/
│   ├── csrf.js          给所有非 GET 请求自动附加 CSRF token
│   ├── login.js         登录表单逻辑
│   └── login-characters.js  登录页动态角色
├── script-php.js        主界面逻辑
├── tools/               运维脚本（迁移、诊断、初始化），不在请求路径上
├── logs/                运行日志与备份快照
└── LICENSE
```

## 设计约定

**颜色只有一个来源。** 所有颜色、圆角、阴影定义在 `css/theme.css` 的 `:root` 里，其他 CSS 一律 `var()` 引用。改主色只需要改 `--c-accent` 一处。

**页面必须引 `theme.css`。** 缺这个文件时所有 `var()` 失效，页面会退化成裸 HTML（无背景、无边框、按钮透明）。

**写操作必须带 CSRF token。** 页面通过 `<meta name="csrf-token">` 下发，`js/csrf.js` 自动附加到 `fetch`。直接调 API 时记得带 `X-CSRF-Token` 头，否则返回 403。

**文件归属按用户隔离。** 普通用户只能读写自己的文件，超级管理员不受限。所有单条操作都带 `userId` 条件。

**静态资源带版本号。** 页面引用 CSS/JS 时带 `?v=filemtime`，改完文件浏览器自动拿新的。

## 导出 Excel

「导出表格」按钮生成 `.xlsx`，包含文件编号、文件名、文件内容、下发时间、批示五列，带表头底色、全表边框、列宽、首行冻结、自动筛选。

格式细节见 `api/xlsx.php`，纯 PHP 拼 OOXML，不引第三方库。

## 运维脚本

`tools/` 下的脚本不在 Web 请求路径上，浏览器访问需要登录（`reset_database.php` 等改库脚本只允许命令行）：

```bash
php tools/migrate_database.php      # 表结构迁移
php tools/reset_database.php        # 重置数据库（会清空数据）
php tools/check_config.php          # 环境自检
```

## 许可证

MIT，见 [LICENSE](LICENSE)。
