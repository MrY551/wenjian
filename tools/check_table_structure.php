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
require_once __DIR__ . '/../config/database.php';

$database = new Database();
$db = $database->getConnection();

echo "<h2>检查files表的结构</h2>";

try {
    $stmt = $db->query("DESCRIBE files");
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
    echo "<tr><th>字段名</th><th>类型</th><th>允许NULL</th><th>键</th><th>默认值</th><th>额外</th></tr>";

    foreach ($columns as $column) {
        echo "<tr>";
        echo "<td>" . htmlspecialchars($column['Field']) . "</td>";
        echo "<td>" . htmlspecialchars($column['Type']) . "</td>";
        echo "<td>" . htmlspecialchars($column['Null']) . "</td>";
        echo "<td>" . htmlspecialchars($column['Key']) . "</td>";
        echo "<td>" . htmlspecialchars($column['Default']) . "</td>";
        echo "<td>" . htmlspecialchars($column['Extra']) . "</td>";
        echo "</tr>";
    }

    echo "</table>";
} catch (PDOException $e) {
    echo "<p style='color: red;'>错误: " . htmlspecialchars($e->getMessage()) . "</p>";
}

echo "<h2>检查instructions表的结构</h2>";

try {
    $stmt = $db->query("DESCRIBE instructions");
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
    echo "<tr><th>字段名</th><th>类型</th><th>允许NULL</th><th>键</th><th>默认值</th><th>额外</th></tr>";

    foreach ($columns as $column) {
        echo "<tr>";
        echo "<td>" . htmlspecialchars($column['Field']) . "</td>";
        echo "<td>" . htmlspecialchars($column['Type']) . "</td>";
        echo "<td>" . htmlspecialchars($column['Null']) . "</td>";
        echo "<td>" . htmlspecialchars($column['Key']) . "</td>";
        echo "<td>" . htmlspecialchars($column['Default']) . "</td>";
        echo "<td>" . htmlspecialchars($column['Extra']) . "</td>";
        echo "</tr>";
    }

    echo "</table>";
} catch (PDOException $e) {
    echo "<p style='color: red;'>错误: " . htmlspecialchars($e->getMessage()) . "</p>";
}
?>
