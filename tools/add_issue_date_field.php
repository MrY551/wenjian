<?php
// 浏览器访问需要登录；命令行可直接运行
if (PHP_SAPI !== 'cli') {
    ini_set('session.cookie_httponly', 1);
    ini_set('session.use_only_cookies', 1);
    ini_set('session.cookie_samesite', 'Lax');
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    if (!isset($_SESSION['user_id'])) {
        http_response_code(403);
        exit('403 需要登录');
    }
}
// 添加issueDate字段到files表
// 配置Session
ini_set('session.cookie_httponly', 1);
ini_set('session.use_only_cookies', 1);
ini_set('session.cookie_samesite', 'Lax');

// 启动Session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// 启用错误日志
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/logs/php_errors.log');

// 确保日志目录存在
$logDir = __DIR__ . '/logs';
if (!file_exists($logDir)) {
    mkdir($logDir, 0755, true);
}

// 引入数据库配置
require_once __DIR__ . '/../config/database.php';

// 检查用户是否登录（超级管理员）
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'superadmin') {
    die('<div style="padding: 20px; color: red; font-weight: bold;">无权访问此页面，请以超级管理员身份登录</div>');
}

// 获取数据库连接
$database = new Database();
$db = $database->getConnection();

// 检查数据库连接
if (!$db) {
    die('<div style="padding: 20px; color: red; font-weight: bold;">数据库连接失败，请检查配置</div>');
}

// 检查issueDate字段是否已存在
try {
    $query = "SHOW COLUMNS FROM files LIKE 'issueDate'";
    $stmt = $db->prepare($query);
    $stmt->execute();
    $result = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($result) {
        echo '<div style="padding: 20px; color: green; font-weight: bold;">issueDate字段已存在，无需添加</div>';
        echo '<a href="index.php">返回首页</a>';
        exit;
    }
} catch (PDOException $e) {
    die('<div style="padding: 20px; color: red; font-weight: bold;">检查字段失败: ' . htmlspecialchars($e->getMessage()) . '</div>');
}

// 添加issueDate字段（先设置为可空）
try {
    $query = "ALTER TABLE files ADD COLUMN issueDate DATE NULL COMMENT '下发时间'";
    $stmt = $db->prepare($query);
    $result = $stmt->execute();

    if ($result) {
        echo '<div style="padding: 20px; color: green; font-weight: bold;">issueDate字段添加成功！</div>';
        
        // 更新现有数据，将NULL值设置为当前日期
        $updateQuery = "UPDATE files SET issueDate = CURDATE() WHERE issueDate IS NULL";
        $updateStmt = $db->prepare($updateQuery);
        $updateResult = $updateStmt->execute();
        
        if ($updateResult) {
            echo '<div style="padding: 20px; color: green; font-weight: bold;">现有数据已更新，下发时间设置为当前日期！</div>';
        }
        
        // 将字段设置为NOT NULL
        $alterQuery = "ALTER TABLE files MODIFY COLUMN issueDate DATE NOT NULL COMMENT '下发时间'";
        $alterStmt = $db->prepare($alterQuery);
        $alterResult = $alterStmt->execute();
        
        if ($alterResult) {
            echo '<div style="padding: 20px; color: green; font-weight: bold;">issueDate字段已设置为必填项！</div>';
        }
        
        echo '<a href="index.php">返回首页</a>';
    } else {
        echo '<div style="padding: 20px; color: red; font-weight: bold;">issueDate字段添加失败</div>';
        echo '<a href="index.php">返回首页</a>';
    }
} catch (PDOException $e) {
    echo '<div style="padding: 20px; color: red; font-weight: bold;">添加字段失败: ' . htmlspecialchars($e->getMessage()) . '</div>';
    echo '<a href="index.php">返回首页</a>';
}
?>
