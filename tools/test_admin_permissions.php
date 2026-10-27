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
// 测试admin权限撤销
session_start();

function testAdminPermissions($username, $password, $expectedRole) {
    echo "测试用户: $username (期望角色: $expectedRole)\n";
    
    // 模拟登录
    $loginData = [
        'username' => $username,
        'password' => $password
    ];
    
    $ch = curl_init('http://localhost/api/auth.php/login');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($loginData));
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    
    $response = curl_exec($ch);
    $data = json_decode($response, true);
    
    if ($data['success']) {
        $actualRole = $data['user']['role'];
        echo "✓ 登录成功，用户角色: $actualRole\n";
        
        if ($actualRole === $expectedRole) {
            echo "✓ 角色验证成功\n";
            
            // 测试管理员权限访问
            $ch = curl_init('http://localhost/api/admin.php/check');
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
            
            $adminResponse = curl_exec($ch);
            $adminData = json_decode($adminResponse, true);
            
            if ($username === 'superadmin') {
                // superadmin应该能访问
                if ($adminData && $adminData['success']) {
                    echo "✓ superadmin可以访问管理功能\n";
                    return true;
                } else {
                    echo "✗ superadmin无法访问管理功能\n";
                    return false;
                }
            } else {
                // admin和普通用户应该不能访问
                if ($adminData && isset($adminData['error'])) {
                    echo "✓ $username 无法访问管理功能 (正确)\n";
                    return true;
                } else {
                    echo "✗ $username 意外获得了管理权限访问\n";
                    return false;
                }
            }
        } else {
            echo "✗ 角色验证失败: 期望 $expectedRole, 实际 $actualRole\n";
            return false;
        }
    } else {
        echo "✗ 登录失败: " . ($data['error'] ?? '未知错误') . "\n";
        return false;
    }
}

echo "开始测试管理员权限撤销...\n\n";

// 测试超级管理员（应该有管理权限）
$superadminTest = testAdminPermissions('superadmin', '123456', 'superadmin');
echo "\n";

// 测试admin用户（现在应该是普通用户，没有管理权限）
$adminTest = testAdminPermissions('admin', '123456', 'user');
echo "\n";

// 测试普通用户（应该没有管理权限）
$userTest = testAdminPermissions('user1', '123456', 'user');
echo "\n";

if ($superadminTest && $adminTest && $userTest) {
    echo "✅ 所有权限测试通过！\n";
    echo "- superadmin 拥有完整管理权限\n";
    echo "- admin 已降级为普通用户，无管理权限\n";
    echo "- user1 作为普通用户，无管理权限\n";
} else {
    echo "❌ 权限测试失败！\n";
}
?>
