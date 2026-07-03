<?php
// 清除数据库中的所有文件
// 启动会话
session_start();

// 引入必要的文件
require_once 'config/database.php';

// 检查用户是否登录
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

// 检查是否为超级管理员
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'superadmin') {
    die('<div style="padding: 20px; color: red; font-weight: bold;">只有超级管理员可以执行此操作</div>');
}

// 获取数据库连接
$database = new Database();
$db = $database->getConnection();

// 检查数据库连接
if (!$db) {
    die('<div style="padding: 20px; color: red; font-weight: bold;">数据库连接失败，请检查配置</div>');
}

// 处理表单提交
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // 开始事务
        $db->beginTransaction();

        // 删除所有文件（级联删除相关的批示记录）
        $stmt = $db->prepare("DELETE FROM files");
        $stmt->execute();

        // 提交事务
        $db->commit();

        $message = "所有文件已成功清除！";
    } catch (PDOException $e) {
        // 回滚事务
        $db->rollBack();
        $message = "清除文件时出错: " . $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>清除文件</title>
    <link rel="stylesheet" href="styles.css">
</head>
<body>
    <div class="container">
        <header>
            <h1>清除文件</h1>
        </header>

        <div class="tab-content">
            <div class="tab-pane active">
                <?php if (isset($message)): ?>
                    <div style="padding: 15px; margin-bottom: 20px; background-color: <?php echo strpos($message, '成功') !== false ? '#d4edda' : '#f8d7da'; ?>; border-radius: 4px;">
                        <?php echo htmlspecialchars($message); ?>
                    </div>
                <?php endif; ?>

                <div style="background-color: #f9f9f9; padding: 20px; border-radius: 8px; border: 1px solid #eee;">
                    <h2>警告：此操作将删除所有文件记录！</h2>
                    <p>此操作不可逆，请谨慎操作。</p>

                    <form method="POST">
                        <div class="form-group">
                            <label>
                                <input type="checkbox" name="confirm" value="1" required>
                                我确认要清除所有文件记录
                            </label>
                        </div>

                        <div class="form-actions">
                            <button type="submit" class="btn btn-danger">确认清除所有文件</button>
                            <a href="index.php" class="btn btn-secondary">返回首页</a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
