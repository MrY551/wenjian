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
// 直接测试数据库中的用户权限
require_once __DIR__ . '/../config/database.php';

echo "开始测试管理员权限...\n\n";

try {
    $database = new Database();
    $db = $database->getConnection();
    
    // 查询所有用户
    $stmt = $db->prepare("SELECT id, username, prefix, role FROM users ORDER BY id");
    $stmt->execute();
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($users)) {
        echo "❌ 数据库中没有用户，请先运行 reset_database.php 重置数据库\n";
        exit;
    }
    
    echo "查询到 " . count($users) . " 个用户：\n";
    echo str_repeat("=", 50) . "\n";
    
    $allPassed = true;
    
    foreach ($users as $user) {
        echo "\n用户: " . $user['username'] . "\n";
        echo "  ID: " . $user['id'] . "\n";
        echo "  Prefix: " . $user['prefix'] . "\n";
        echo "  Role: " . $user['role'] . "\n";
        
        // 验证角色
        if ($user['username'] === 'superadmin') {
            if ($user['role'] === 'superadmin') {
                echo "  ✅ superadmin 角色正确\n";
            } else {
                echo "  ❌ superadmin 角色错误，期望 superadmin，实际 " . $user['role'] . "\n";
                $allPassed = false;
            }
        } else {
            // admin 和其他用户应该是普通用户
            if ($user['role'] === 'user') {
                echo "  ✅ " . $user['username'] . " 是普通用户，无管理权限\n";
            } else {
                echo "  ❌ " . $user['username'] . " 角色错误，应该是 user，实际 " . $user['role'] . "\n";
                $allPassed = false;
            }
        }
    }
    
    echo "\n" . str_repeat("=", 50) . "\n";
    
    if ($allPassed) {
        echo "✅ 所有权限测试通过！\n\n";
        echo "权限说明：\n";
        echo "- superadmin: 超级管理员，拥有完整管理权限\n";
        echo "- admin: 普通用户，已撤销管理权限\n";
        echo "- user1: 普通用户\n";
        echo "- user2: 普通用户\n";
    } else {
        echo "❌ 权限测试失败！\n";
    }
    
    // 测试文件归属
    echo "\n" . str_repeat("=", 50) . "\n";
    echo "测试文件归属：\n\n";
    
    $stmt = $db->prepare("
        SELECT f.fileNumber, f.fileName, u.username as owner 
        FROM files f 
        JOIN users u ON f.userId = u.id 
        ORDER BY f.id
    ");
    $stmt->execute();
    $files = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($files as $file) {
        echo "- " . $file['fileNumber'] . ": " . $file['fileName'] . " (属于 " . $file['owner'] . ")\n";
    }
    
} catch (PDOException $e) {
    echo "❌ 测试失败: " . $e->getMessage() . "\n";
    echo "\n请确保：\n";
    echo "1. 数据库已正确配置 (运行 setup.php)\n";
    echo "2. 数据库表已创建 (运行 reset_database.php)\n";
}
?>