# AGENTS.md - Agent Coding Guide

## Project Overview

**File Number Registration System** (文件编号登记系统) - PHP web app for managing government documents with approval workflows.

- **Stack**: PHP 7.x + MySQL + jQuery 3.7.1
- **Architecture**: Monolithic PHP with REST API backend
- **Database**: MySQL via PDO (no ORM)
- **Build system**: None - direct PHP execution

---

## Quick Start Commands

```bash
# Setup (REQUIRED first time)
# Visit http://localhost/setup.php in browser to configure database

# Run application
php -S localhost:8000  # Then visit http://localhost:8000

# Database migrations (if needed)
php migrate_database.php

# Manual testing (no automated tests)
php test_api.php
php test_admin_permissions.php
```

---

## Critical Patterns

### Session Configuration (REQUIRED in every PHP file)

```php
ini_set('session.cookie_httponly', 1);
ini_set('session.use_only_cookies', 1);
ini_set('session.cookie_samesite', 'Lax');
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
```

### Database Queries (ALWAYS use prepared statements)

```php
// CORRECT
$stmt = $db->prepare("SELECT * FROM files WHERE id = ?");
$stmt->execute([$id]);

// WRONG - SQL injection risk!
$db->query("SELECT * FROM files WHERE id = " . $id);
```

### API Response Format

```php
header('Content-Type: application/json; charset=utf-8');
http_response_code(200);
echo json_encode($data, JSON_UNESCAPED_UNICODE);
```

### Redirect Pattern (MUST exit after redirect)

```php
header('Location: login.php');
exit;
```

---

## Directory Structure

```
/
├── index.php          # Main application (requires login)
├── login.php          # Login page
├── admin.php          # Admin panel (superadmin only)
├── setup.php          # Database configuration wizard
├── api/               # REST API endpoints
│   ├── auth.php       # Authentication (login/logout/register)
│   ├── files.php      # File CRUD operations
│   ├── admin.php      # User management (superadmin)
│   ├── prefixes.php   # File prefix management
│   ├── instructions.php # Approval instructions
│   └── export.php     # Data export
├── config/
│   └── database.php   # Database configuration
├── database/
│   └── init.sql       # Database schema
├── logs/              # PHP error logs
├── styles.css         # Main stylesheet
├── script-php.js      # Main JavaScript
└── test*.php          # Manual test scripts
```

---

## Database Schema

### Tables

- **users**: id, username, password (hashed), prefix (unique), role, createdAt
- **files**: id, userId (FK), fileNumber (unique), fileName, fileContent, submitter, issueDate, approver01-07, leaderInstruction, timestamps
- **instructions**: id, fileNumber (FK), leaderName, content, date, createdAt
- **prefixes**: id, prefix (unique), description, timestamps

### User Roles

- **superadmin**: Full access, can manage users and prefixes
- **admin**: Legacy role, now treated as regular user
- **user**: Can only view/edit own files

---

## API Patterns

### Authentication Check (required in protected endpoints)

```php
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => '未登录']);
    exit;
}
```

### Superadmin Check

```php
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'superadmin') {
    http_response_code(403);
    echo json_encode(['error' => '无权访问']);
    exit;
}
```

### File Number Format

`{prefix}{year}【{number}】` - Example: `站参2024【001】`

---

## Frontend Conventions

### JavaScript

- Use jQuery 3.7.1 (included locally)
- Use fetch API for AJAX (not $.ajax)
- Cache DOM elements to avoid repeated queries
- Show notifications: `showNotification('message', isError)`

### HTML/CSS

- Bootstrap-style classes: `.btn`, `.form-group`, `.table`
- Font Awesome 6.4.0 via CDN
- Escape user output: `<?php echo htmlspecialchars($value, ENT_QUOTES, 'UTF-8'); ?>`

### Naming Conventions

| Type | Convention | Example |
|------|------------|---------|
| PHP files | snake_case.php | `login.php`, `api/files.php` |
| JS files | snake_case.js | `script-php.js` |
| PHP functions | camelCase | `function getFiles($db)` |
| JS functions | camelCase | `function loadFiles()` |
| Database tables | snake_case | `files`, `instructions` |
| Variables | camelCase | `$fileNumber`, `$db` |

---

## Security Requirements

1. **SQL Injection**: Always use prepared statements
2. **XSS**: Escape output with `htmlspecialchars()`
3. **Passwords**: Use `password_hash()` and `password_verify()`
4. **Session Security**: Cookie settings in every PHP file

---

## Common Pitfalls

1. **Missing session config**: Every PHP file needs the session configuration block
2. **Forgetting exit after redirect**: Always `exit` after `header('Location: ...')`
3. **String concatenation in SQL**: Always use prepared statements
4. **Missing JSON_UNESCAPED_UNICODE**: Required for Chinese characters in API responses
5. **Role checks**: Only `superadmin` role has admin privileges (not `admin`)

---

## Testing

No automated test framework. Manual test scripts available:

- `test_api.php` - API endpoint tests
- `test_admin_permissions.php` - Admin permission tests
- `test_account_isolation.php` - Account isolation tests
- `test_isolation.php` - Data isolation tests

Run via browser or command line: `php test_api.php`

---

## Dependencies

- **PHP**: 7.0+
- **MySQL**: 5.7+
- **jQuery**: 3.7.1 (local)
- **Font Awesome**: 6.4.0 (CDN)
- **No Composer**: No package manager used
