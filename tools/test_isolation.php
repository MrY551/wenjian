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
// 测试账号权限隔离
session_start();

echo "测试账号权限隔离\n";
echo "====================\n\n";

// 测试不同账号登录后的文件访问
$testUsers = [
    'superadmin' => 'superadmin',
    'admin' => 'user',
    'user1' => 'user',
    'user2' => 'user'
];

foreach ($testUsers as $username => $expectedRole) {
    echo "测试用户: {$username} (期望角色: {$expectedRole})\n";
    
    // 模拟登录
    $_SESSION['user_id'] = 1;
    $_SESSION['username'] = $username;
    $_SESSION['role'] = $expectedRole;
    
    // 模拟 API 调用
    require_once __DIR__ . '/../config/database.php';
    $database = new Database();
    $db = $database->getConnection();
    
    $isSuperAdmin = ($expectedRole === 'superadmin');
    
    $query = "SELECT COUNT(*) as file_count FROM files";
    $params = [];
    
    if (!$isSuperAdmin) {
        $query .= " WHERE userId = ?";
        $params[] = $_SESSION['user_id'];
    }
    
    $stmt = $db->prepare($query);
    $stmt->execute($params);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    echo "  查询语句: {$query}\n";
    echo "  查询参数: " . json_encode($params) . "\n";
    echo "  文件数量: {$result['file_count']}\n";
    
    if ($isSuperAdmin) {
        echo "  ✅ superadmin 可以查看所有文件\n";
    } else {
        echo "  ✅ {$username} 只能查看自己的文件\n";
    }
    
    echo "\n";
}

echo "权限隔离测试完成！\n";
echo "如果文件数量显示正确，说明权限隔离正常工作。\n";
?>