<?php
/**
 * 数据库迁移脚本 (Web版)
 */
header('Content-Type: text/plain; charset=utf-8');
echo "<pre>\n";

require_once 'config/database.php';

echo "========================================\n";
echo "  文件编号登记系统 - 数据库迁移脚本\n";
echo "========================================\n\n";

try {
    $database = new Database();
    $db = $database->getConnection();
    
    echo "📌 检查当前数据库结构...\n\n";
    
    // 1. 检查 users 表是否存在
    $stmt = $db->prepare("SHOW TABLES LIKE 'users'");
    $stmt->execute();
    if (!$stmt->fetch()) {
        die("❌ 错误：找不到 users 表，请先运行 setup.php 或 reset_database.php 创建表结构\n");
    }
    echo "✅ users 表存在\n";
    
    // 2. 检查 files 表是否存在
    $stmt = $db->prepare("SHOW TABLES LIKE 'files'");
    $stmt->execute();
    if (!$stmt->fetch()) {
        die("❌ 错误：找不到 files 表，请先运行 setup.php 或 reset_database.php 创建表结构\n");
    }
    echo "✅ files 表存在\n";
    
    // 3. 检查是否已有 userId 字段
    $stmt = $db->prepare("SHOW COLUMNS FROM files LIKE 'userId'");
    $stmt->execute();
    $hasUserId = $stmt->fetch();
    
    if (!$hasUserId) {
        echo "\n📌 开始迁移 files 表...\n";
        
        // 获取所有现有文件
        $stmt = $db->prepare("SELECT id, fileNumber, fileName FROM files");
        $stmt->execute();
        $existingFiles = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo "   找到 " . count($existingFiles) . " 个现有文件\n";
        
        // 获取 superadmin 用户ID
        $stmt = $db->prepare("SELECT id FROM users WHERE username = 'superadmin'");
        $stmt->execute();
        $superadmin = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$superadmin) {
            die("❌ 错误：找不到 superadmin 用户，无法迁移文件\n");
        }
        
        $superadminId = $superadmin['id'];
        echo "   superadmin ID: $superadminId\n";
        
        // 添加 userId 字段
        $db->exec("ALTER TABLE files ADD COLUMN userId INT NOT NULL DEFAULT $superadminId AFTER id");
        echo "   ✅ 添加 userId 字段成功\n";
        
        // 添加外键约束
        $db->exec("ALTER TABLE files ADD CONSTRAINT fk_files_userId FOREIGN KEY (userId) REFERENCES users(id) ON DELETE CASCADE");
        echo "   ✅ 添加外键约束成功\n";
        
        // 如果有现有文件，更新它们的归属
        if (!empty($existingFiles)) {
            // 获取admin用户ID
            $stmt = $db->prepare("SELECT id FROM users WHERE username = 'admin'");
            $stmt->execute();
            $adminUser = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($adminUser) {
                $adminId = $adminUser['id'];
                
                // 更新所有文件归属于admin用户
                $db->exec("UPDATE files SET userId = $adminId");
                echo "   ✅ 所有文件已划分到 admin 用户下 (userId: $adminId)\n";
                
                // 显示文件列表
                foreach ($existingFiles as $file) {
                    echo "      - {$file['fileNumber']}: {$file['fileName']}\n";
                }
            } else {
                // 如果没有admin用户，则归属于superadmin
                $db->exec("UPDATE files SET userId = $superadminId");
                echo "   ⚠️  未找到admin用户，所有文件将归属于 superadmin\n";
            }
        }
        
    } else {
        echo "\n✅ files 表已包含 userId 字段，无需迁移\n";
    }
    
    // 4. 检查 prefixes 表是否存在，不存在则创建
    echo "\n📌 检查前缀表...\n";
    $stmt = $db->prepare("SHOW TABLES LIKE 'prefixes'");
    $stmt->execute();
    if (!$stmt->fetch()) {
        $db->exec("CREATE TABLE IF NOT EXISTS prefixes (
            id INT AUTO_INCREMENT PRIMARY KEY,
            prefix VARCHAR(10) NOT NULL UNIQUE,
            description VARCHAR(100),
            createdAt DATETIME DEFAULT CURRENT_TIMESTAMP,
            updatedAt DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        echo "✅ 创建 prefixes 表成功\n";
        
        // 插入默认前缀
        $db->exec("INSERT INTO prefixes (prefix, description) VALUES
            ('站参', '站参文件前缀'),
            ('参保', '参保文件前缀')");
        echo "✅ 插入默认前缀成功\n";
    } else {
        echo "✅ prefixes 表已存在\n";
    }
    
    // 5. 检查并修改 admin 用户角色
    echo "\n📌 检查用户角色...\n";
    $stmt = $db->prepare("SELECT id, username, role FROM users WHERE username = 'admin'");
    $stmt->execute();
    $adminUser = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($adminUser) {
        if ($adminUser['role'] === 'admin') {
            $db->exec("UPDATE users SET role = 'user' WHERE username = 'admin'");
            echo "✅ admin 用户角色已从 'admin' 修改为 'user'\n";
        } else {
            echo "✅ admin 用户已是普通用户 (role: {$adminUser['role']})\n";
        }
    } else {
        echo "⚠️  未找到 admin 用户，无需修改\n";
    }
    
    // 6. 确保 superadmin 存在且角色正确
    echo "\n?? 检查超级管理员...\n";
    $stmt = $db->prepare("SELECT id, role FROM users WHERE username = 'superadmin'");
    $stmt->execute();
    $superadminUser = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($superadminUser) {
        if ($superadminUser['role'] !== 'superadmin') {
            $db->exec("UPDATE users SET role = 'superadmin' WHERE username = 'superadmin'");
            echo "✅ superadmin 用户角色已修正为 'superadmin'\n";
        } else {
            echo "✅ superadmin 用户角色正确\n";
        }
    } else {
        echo "⚠️  未找到 superadmin 用户，建议运行 reset_database.php 重新初始化\n";
    }
    
    // 7. 验证迁移结果
    echo "\n========================================\n";
    echo "  迁移完成 - 验证结果\n";
    echo "========================================\n\n";
    
    // 验证用户角色
    echo "用户角色验证：\n";
    $stmt = $db->prepare("SELECT username, role FROM users ORDER BY id");
    $stmt->execute();
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($users as $user) {
        $status = '';
        if ($user['username'] === 'superadmin' && $user['role'] === 'superadmin') {
            $status = '✅ 超级管理员';
        } elseif ($user['username'] === 'admin' && $user['role'] === 'user') {
            $status = '✅ 已降级为普通用户';
        } else {
            $status = '✅ 普通用户';
        }
        echo "  - {$user['username']}: {$user['role']} $status\n";
    }
    
    // 验证文件归属
    echo "\n文件归属验证：\n";
    $stmt = $db->prepare("
        SELECT f.fileNumber, f.fileName, u.username as owner 
        FROM files f 
        JOIN users u ON f.userId = u.id 
        ORDER BY f.id
    ");
    $stmt->execute();
    $files = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($files as $file) {
        echo "  - {$file['fileNumber']}: {$file['fileName']} ({$file['owner']})\n";
    }
    
    echo "\n========================================\n";
    echo "✅ 数据库迁移成功完成！\n";
    echo "========================================\n\n";
    
    echo "📋 权限说明：\n";
    echo "  - superadmin: 完整管理权限，查看所有文件\n";
    echo "  - admin: 普通用户，只能查看自己的文件\n";
    echo "  - 其他用户: 普通用户，只能查看自己的文件\n\n";
    
echo "🚀 迁移完成，请检查上方结果。\n";
echo "</pre>\n";
    
} catch (PDOException $e) {
    echo "\n❌ 迁移失败：\n";
    echo htmlspecialchars($e->getMessage()) . "\n\n";
    
    echo "📌 可能的原因和解决方法：\n";
    echo "  1. 数据库连接失败 - 检查 config/database.php 配置\n";
    echo "  2. 表结构不兼容 - 确保运行过 setup.php\n";
    echo "  3. 外键约束冲突 - 检查是否有文件引用不存在的用户\n\n";
    
    echo "📌 建议操作：\n";
    echo "  1. 备份当前数据库\n";
    echo "  2. 运行 reset_database.php 重置数据库\n";
    echo "  3. 手动迁移重要数据\n";
}
?>