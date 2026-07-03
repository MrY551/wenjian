<?php
// 初始化前缀表
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
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/logs/php_errors.log');

// 确保日志目录存在
$logDir = __DIR__ . '/logs';
if (!file_exists($logDir)) {
    mkdir($logDir, 0755, true);
}

// 引入数据库配置
require_once 'config/database.php';

// 检查用户是否登录
if (!isset($_SESSION['user_id'])) {
    die('<div style="padding: 20px; color: red; font-weight: bold;">未登录</div>');
}

// 检查是否是超级管理员
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'superadmin') {
    die('<div style="padding: 20px; color: red; font-weight: bold;">无权访问</div>');
}

$database = new Database();
$db = $database->getConnection();

if (!$db) {
    die('<div style="padding: 20px; color: red; font-weight: bold;">数据库连接失败，请检查配置</div>');
}

// 创建前缀表
try {
    $db->exec("CREATE TABLE IF NOT EXISTS prefixes (
        id INT AUTO_INCREMENT PRIMARY KEY,
        prefix VARCHAR(10) NOT NULL UNIQUE,
        description VARCHAR(100),
        createdAt DATETIME DEFAULT CURRENT_TIMESTAMP,
        updatedAt DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    echo '<div style="padding: 20px; color: green; font-weight: bold;">前缀表创建成功</div>';
} catch (PDOException $e) {
    echo '<div style="padding: 20px; color: red; font-weight: bold;">前缀表创建失败: ' . $e->getMessage() . '</div>';
}

// 检查是否已有默认前缀
$stmt = $db->prepare("SELECT COUNT(*) as count FROM prefixes");
$stmt->execute();
$result = $stmt->fetch(PDO::FETCH_ASSOC);

if ($result['count'] == 0) {
    // 插入默认前缀
    try {
        // 插入第一个默认前缀
        $stmt = $db->prepare("INSERT INTO prefixes (prefix, description) VALUES (?, ?)");
        $stmt->execute(['站参', '站参文件前缀']);
        
        // 插入第二个默认前缀
        $stmt = $db->prepare("INSERT INTO prefixes (prefix, description) VALUES (?, ?)");
        $stmt->execute(['参保', '参保文件前缀']);

        echo '<div style="padding: 20px; color: green; font-weight: bold;">默认前缀插入成功</div>';
    } catch (PDOException $e) {
        echo '<div style="padding: 20px; color: red; font-weight: bold;">默认前缀插入失败: ' . $e->getMessage() . '</div>';
    }
} else {
    echo '<div style="padding: 20px; color: blue; font-weight: bold;">前缀表已有数据，跳过插入默认前缀</div>';
}

echo '<div style="padding: 20px;"><a href="admin.php">返回管理页面</a></div>';
?>
