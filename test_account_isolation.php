<?php
// 测试账号隔离功能
session_start();

// 模拟不同用户登录
function testUserIsolation($username, $password) {
    echo "测试用户: $username\n";
    
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
        echo "✓ 登录成功\n";
        $userId = $data['user']['id'];
        
        // 测试获取文件列表
        $ch = curl_init('http://localhost/api/files.php');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        
        $fileResponse = curl_exec($ch);
        $fileData = json_decode($fileResponse, true);
        
        if (is_array($fileData)) {
            echo "✓ 获取到 " . count($fileData) . " 个文件\n";
            
            // 检查文件是否属于该用户（对于普通用户）
            if ($username !== 'superadmin') {
                foreach ($fileData as $file) {
                    if ($file['userId'] != $userId) {
                        echo "✗ 错误：用户 $username 看到了其他用户的文件\n";
                        return false;
                    }
                }
                echo "✓ 文件隔离验证通过\n";
            } else {
                echo "✓ 超级管理员可以看到所有文件\n";
            }
            
            return true;
        } else {
            echo "✗ 获取文件失败\n";
            return false;
        }
    } else {
        echo "✗ 登录失败: " . ($data['error'] ?? '未知错误') . "\n";
        return false;
    }
}

// 测试用例
echo "开始测试账号隔离功能...\n\n";

// 测试超级管理员
testUserIsolation('superadmin', '123456');
echo "\n";

// 测试普通用户
testUserIsolation('user1', '123456');
echo "\n";

// 测试另一个普通用户
testUserIsolation('user2', '123456');
echo "\n";

echo "测试完成！\n";
?>