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
// 检查和修复首长批示数据的脚本
require_once __DIR__ . '/../config/database.php';

$database = new Database();
$db = $database->getConnection();

echo "<h2>检查文件表中的首长批示数据</h2>";

try {
    // 查询所有文件及其首长批示
    $query = "SELECT id, fileNumber, fileName, leaderInstruction FROM files";
    $stmt = $db->prepare($query);
    $stmt->execute();
    $files = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
    echo "<tr><th>ID</th><th>文件编号</th><th>文件名</th><th>首长批示</th></tr>";

    foreach ($files as $file) {
        echo "<tr>";
        echo "<td>" . htmlspecialchars($file['id']) . "</td>";
        echo "<td>" . htmlspecialchars($file['fileNumber']) . "</td>";
        echo "<td>" . htmlspecialchars($file['fileName']) . "</td>";
        echo "<td>" . (isset($file['leaderInstruction']) ? htmlspecialchars($file['leaderInstruction']) : 'NULL') . "</td>";
        echo "</tr>";
    }

    echo "</table>";

    // 检查是否有测试数据
    echo "<h2>检查测试数据</h2>";
    $query = "SELECT COUNT(*) as count FROM files WHERE fileNumber LIKE 'TEST-%'";
    $stmt = $db->prepare($query);
    $stmt->execute();
    $result = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($result['count'] > 0) {
        echo "<p>找到 {$result['count']} 条测试数据</p>";

        // 显示测试数据的详细信息
        $query = "SELECT * FROM files WHERE fileNumber LIKE 'TEST-%'";
        $stmt = $db->prepare($query);
        $stmt->execute();
        $testFiles = $stmt->fetchAll(PDO::FETCH_ASSOC);

        echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
        echo "<tr><th>ID</th><th>文件编号</th><th>文件名</th><th>首长批示</th></tr>";

        foreach ($testFiles as $file) {
            echo "<tr>";
            echo "<td>" . htmlspecialchars($file['id']) . "</td>";
            echo "<td>" . htmlspecialchars($file['fileNumber']) . "</td>";
            echo "<td>" . htmlspecialchars($file['fileName']) . "</td>";
            echo "<td>" . (isset($file['leaderInstruction']) ? htmlspecialchars($file['leaderInstruction']) : 'NULL') . "</td>";
            echo "</tr>";
        }

        echo "</table>";
    } else {
        echo "<p>没有找到测试数据，正在创建...</p>";

        // 创建测试数据
        $query = "INSERT INTO files (fileNumber, fileName, fileContent, approver01, approver02, approver03, approver04, approver05, approver06, approver07, leaderInstruction)
                  VALUES
                  ('TEST-001', '测试文件1', '这是测试文件1的内容', '已签批', '已签批', '未签批', '未签批', '未签批', '未签批', '未签批', '请尽快处理'),
                  ('TEST-002', '测试文件2', '这是测试文件2的内容', '未签批', '未签批', '未签批', '未签批', '未签批', '未签批', '未签批', '同意执行')";

        $stmt = $db->prepare($query);
        $stmt->execute();

        echo "<p>测试数据创建成功！</p>";
    }

    // 检查instructions表
    echo "<h2>检查批示历史表</h2>";
    $query = "SELECT * FROM instructions";
    $stmt = $db->prepare($query);
    $stmt->execute();
    $instructions = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (count($instructions) > 0) {
        echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
        echo "<tr><th>ID</th><th>文件编号</th><th>首长姓名</th><th>批示内容</th><th>批示日期</th></tr>";

        foreach ($instructions as $instruction) {
            echo "<tr>";
            echo "<td>" . htmlspecialchars($instruction['id']) . "</td>";
            echo "<td>" . htmlspecialchars($instruction['fileNumber']) . "</td>";
            echo "<td>" . htmlspecialchars($instruction['leaderName']) . "</td>";
            echo "<td>" . htmlspecialchars($instruction['content']) . "</td>";
            echo "<td>" . htmlspecialchars($instruction['date']) . "</td>";
            echo "</tr>";
        }

        echo "</table>";
    } else {
        echo "<p>批示历史表为空</p>";
    }

} catch (PDOException $e) {
    echo "<p style='color: red;'>错误: " . htmlspecialchars($e->getMessage()) . "</p>";
}
?>
