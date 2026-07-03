<?php
/**
 * 将所有文件划分到admin用户下
 * 
 * 用途：
 * - 迁移现有文件到指定用户
 * - 批量修改文件归属
 */

header('Content-Type: text/plain; charset=utf-8');
echo "<pre>\n";

require_once 'config/database.php';

echo "========================================\n";
echo "  文件归属管理脚本\n";
echo "========================================\n\n";

try {
    $database = new Database();
    $db = $database->getConnection();
    
    // 1. 获取admin用户信息
    echo "📌 查找admin用户...\n";
    $stmt = $db->prepare("SELECT id, username, prefix, role FROM users WHERE username = 'admin'");
    $stmt->execute();
    $adminUser = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$adminUser) {
        die("❌ 错误：找不到 admin 用户\n");
    }
    
    $adminId = $adminUser['id'];
    echo "✅ 找到 admin 用户 (ID: $adminId)\n\n";
    
    // 2. 获取当前文件归属情况
    echo "📌 当前文件归属情况：\n";
    $stmt = $db->prepare("
        SELECT u.username, COUNT(*) as file_count 
        FROM files f 
        JOIN users u ON f.userId = u.id 
        GROUP BY u.id
    ");
    $stmt->execute();
    $currentOwnership = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($currentOwnership as $owner) {
        echo "   - {$owner['username']}: {$owner['file_count']} 个文件\n";
    }
    
    // 3. 执行归属更新
    echo "\n📌 开始将所有文件划分到 admin 用户下...\n";
    $db->exec("UPDATE files SET userId = $adminId");
    
    // 4. 验证更新结果
    echo "✅ 文件归属更新完成！\n\n";
    
    echo "📌 更新后的文件归属情况：\n";
    $stmt = $db->prepare("
        SELECT u.username, COUNT(*) as file_count 
        FROM files f 
        JOIN users u ON f.userId = u.id 
        GROUP BY u.id
    ");
    $stmt->execute();
    $newOwnership = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $totalFiles = 0;
    foreach ($newOwnership as $owner) {
        echo "   - {$owner['username']}: {$owner['file_count']} 个文件\n";
        $totalFiles += $owner['file_count'];
    }
    
    echo "\n📌 文件详情列表：\n";
    $stmt = $db->prepare("
        SELECT f.id, f.fileNumber, f.fileName, u.username as owner 
        FROM files f 
        JOIN users u ON f.userId = u.id 
        ORDER BY f.id
    ");
    $stmt->execute();
    $files = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($files as $file) {
        echo "   - [{$file['id']}] {$file['fileNumber']}: {$file['fileName']} (归属: {$file['owner']})\n";
    }
    
    echo "\n========================================\n";
    echo "✅ 操作完成！\n";
    echo "   总文件数: $totalFiles\n";
    echo "   全部归属: admin\n";
    echo "========================================\n\n";
    
    echo "📋 权限说明：\n";
    echo "  - superadmin: 完整管理权限，查看所有文件\n";
    echo "  - admin: 普通用户，只能查看自己的文件 (共 {$totalFiles} 个)\n";
    echo "  - 其他用户: 普通用户，无文件\n\n";
    
} catch (PDOException $e) {
    echo "\n❌ 操作失败：\n";
    echo htmlspecialchars($e->getMessage()) . "\n\n";
    
    echo "📌 可能的原因：\n";
    echo "  1. 数据库连接失败\n";
    echo "  2. admin 用户不存在\n";
    echo "  3. 外键约束问题\n\n";
}
?>