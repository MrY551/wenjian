<?php
// 数据库配置辅助页面
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>数据库配置 - 文件编号登记系统</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            max-width: 800px;
            margin: 50px auto;
            padding: 20px;
            background-color: #f5f5f5;
        }
        .container {
            background-color: white;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        h1 {
            color: #333;
            margin-bottom: 30px;
        }
        .form-group {
            margin-bottom: 20px;
        }
        label {
            display: block;
            margin-bottom: 5px;
            font-weight: bold;
            color: #555;
        }
        input[type="text"],
        input[type="password"] {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 4px;
            box-sizing: border-box;
        }
        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 16px;
            margin-right: 10px;
        }
        .btn-primary {
            background-color: #3498db;
            color: white;
        }
        .btn-secondary {
            background-color: #95a5a6;
            color: white;
        }
        .message {
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 4px;
        }
        .success {
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        .error {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        .info {
            background-color: #d1ecf1;
            color: #0c5460;
            border: 1px solid #bee5eb;
        }
        .phpstudy-info {
            background-color: #fff3cd;
            color: #856404;
            border: 1px solid #ffeeba;
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 4px;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>数据库配置</h1>

        <div class="phpstudy-info">
            <h3>phpStudy默认数据库信息</h3>
            <p><strong>主机：</strong>localhost</p>
            <p><strong>用户名：</strong>root</p>
            <p><strong>密码：</strong>123456（phpStudy默认密码）</p>
            <p>如果以上密码不正确，请在phpStudy中查看您的MySQL密码。</p>
        </div>

        <?php
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $host = $_POST['host'];
            $db_name = $_POST['db_name'];
            $username = $_POST['username'];
            $password = $_POST['password'];

            try {
                // 尝试连接数据库
                $dsn = "mysql:host=" . $host . ";charset=utf8mb4";
                $options = array(
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                );
                $conn = new PDO($dsn, $username, $password, $options);

                // 尝试创建数据库（如果不存在）
                $conn->exec("CREATE DATABASE IF NOT EXISTS `" . $db_name . "` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");

                // 选择数据库
                $conn->exec("USE `" . $db_name . "`");

                // 检查表是否存在
                function tableExists($conn, $tableName) {
                    $stmt = $conn->prepare("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ?");
                    $stmt->execute([$tableName]);
                    return $stmt->fetchColumn() > 0;
                }

                // 检查字段是否存在
                function columnExists($conn, $tableName, $columnName) {
                    $stmt = $conn->prepare("SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = ? AND column_name = ?");
                    $stmt->execute([$tableName, $columnName]);
                    return $stmt->fetchColumn() > 0;
                }

                // 创建或修复用户表
                if (!tableExists($conn, 'users')) {
                    $conn->exec("CREATE TABLE users (
                        id INT AUTO_INCREMENT PRIMARY KEY,
                        username VARCHAR(50) NOT NULL UNIQUE,
                        password VARCHAR(255) NOT NULL,
                        prefix VARCHAR(10) NOT NULL UNIQUE,
                        role VARCHAR(20) DEFAULT 'user',
                        createdAt DATETIME DEFAULT CURRENT_TIMESTAMP
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
                    echo "✓ 创建 users 表<br>";
                } else {
                    echo "✓ users 表已存在<br>";
                }

                // 创建或修复文件表
                if (!tableExists($conn, 'files')) {
                    $conn->exec("CREATE TABLE files (
                        id INT AUTO_INCREMENT PRIMARY KEY,
                        userId INT NOT NULL,
                        fileNumber VARCHAR(100) NOT NULL UNIQUE,
                        fileName VARCHAR(255) NOT NULL,
                        fileContent TEXT NOT NULL,
                        submitter VARCHAR(100),
                        issueDate DATE NOT NULL COMMENT '下发时间',
                        approver01 VARCHAR(20) DEFAULT '未签批',
                        approver02 VARCHAR(20) DEFAULT '未签批',
                        approver03 VARCHAR(20) DEFAULT '未签批',
                        approver04 VARCHAR(20) DEFAULT '未签批',
                        approver05 VARCHAR(20) DEFAULT '未签批',
                        approver06 VARCHAR(20) DEFAULT '未签批',
                        approver07 VARCHAR(20) DEFAULT '未签批',
                        leaderInstruction TEXT,
                        createdAt DATETIME DEFAULT CURRENT_TIMESTAMP,
                        updatedAt DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                        INDEX idx_userId (userId),
                        INDEX idx_fileNumber (fileNumber),
                        FOREIGN KEY (userId) REFERENCES users(id) ON DELETE CASCADE
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
                    echo "✓ 创建 files 表<br>";
                } else {
                    echo "✓ files 表已存在，检查字段...<br>";
                    
                    // 检查并添加缺少的字段
                    $fieldsToAdd = [
                        'userId' => "INT NOT NULL",
                        'fileNumber' => "VARCHAR(100) NOT NULL UNIQUE",
                        'fileName' => "VARCHAR(255) NOT NULL",
                        'fileContent' => "TEXT NOT NULL",
                        'submitter' => "VARCHAR(100)",
                        'issueDate' => "DATE NOT NULL",
                        'approver01' => "VARCHAR(20) DEFAULT '未签批'",
                        'approver02' => "VARCHAR(20) DEFAULT '未签批'",
                        'approver03' => "VARCHAR(20) DEFAULT '未签批'",
                        'approver04' => "VARCHAR(20) DEFAULT '未签批'",
                        'approver05' => "VARCHAR(20) DEFAULT '未签批'",
                        'approver06' => "VARCHAR(20) DEFAULT '未签批'",
                        'approver07' => "VARCHAR(20) DEFAULT '未签批'",
                        'leaderInstruction' => "TEXT",
                        'createdAt' => "DATETIME DEFAULT CURRENT_TIMESTAMP",
                        'updatedAt' => "DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP"
                    ];
                    
                    foreach ($fieldsToAdd as $field => $definition) {
                        if (!columnExists($conn, 'files', $field)) {
                            try {
                                $conn->exec("ALTER TABLE files ADD COLUMN $field $definition");
                                echo "  + 添加字段: $field<br>";
                            } catch (PDOException $e) {
                                echo "  ! 字段 $field 添加失败: " . $e->getMessage() . "<br>";
                            }
                        }
                    }
                }

                // 创建或修复批示表
                if (!tableExists($conn, 'instructions')) {
                    $conn->exec("CREATE TABLE instructions (
                        id INT AUTO_INCREMENT PRIMARY KEY,
                        fileNumber VARCHAR(100) NOT NULL,
                        leaderName VARCHAR(100) NOT NULL,
                        content TEXT NOT NULL,
                        date DATE NOT NULL,
                        createdAt DATETIME DEFAULT CURRENT_TIMESTAMP,
                        FOREIGN KEY (fileNumber) REFERENCES files(fileNumber) ON DELETE CASCADE
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
                    echo "✓ 创建 instructions 表<br>";
                } else {
                    echo "✓ instructions 表已存在<br>";
                }


                // 插入测试数据（先获取用户ID）
                $stmt = $conn->prepare("SELECT COUNT(*) FROM files");
                $stmt->execute();
                $count = $stmt->fetchColumn();

                if ($count == 0) {
                    // 获取superadmin用户ID
                    $stmt = $conn->prepare("SELECT id FROM users WHERE username = 'superadmin'");
                    $stmt->execute();
                    $superadminId = $stmt->fetchColumn();
                    
                    if ($superadminId) {
                        $conn->exec("INSERT INTO files (userId, fileNumber, fileName, fileContent, submitter, issueDate, approver01, approver02, approver03, approver04, approver05, approver06, approver07, leaderInstruction)
                            VALUES 
                            ($superadminId, 'TEST-001', '测试文件1', '这是测试文件1的内容', '系统管理员', '2024-01-01', '已签批', '已签批', '未签批', '未签批', '未签批', '未签批', '未签批', '请尽快处理'),
                            ($superadminId, 'TEST-002', '测试文件2', '这是测试文件2的内容', '系统管理员', '2024-01-02', '未签批', '未签批', '未签批', '未签批', '未签批', '未签批', '未签批', NULL)");

                        $conn->exec("INSERT INTO instructions (fileNumber, leaderName, content, date)
                            VALUES 
                            ('TEST-001', '张局长', '同意，请按照规定办理', '2024-01-01'),
                            ('TEST-001', '李主任', '已阅，同意执行', '2024-01-02')");
                    }
                }

                // 检查用户表是否为空，如果为空则插入默认用户
                $stmt = $conn->prepare("SELECT COUNT(*) FROM users");
                $stmt->execute();
                $userCount = $stmt->fetchColumn();

                if ($userCount == 0) {
                    // 插入默认用户（admin现在只是普通用户，没有管理权限）
                    $defaultPassword = password_hash('123456', PASSWORD_DEFAULT);

                    $conn->exec("INSERT INTO users (username, password, prefix, role) VALUES
                        ('superadmin', '$defaultPassword', 'SA-', 'superadmin'),
                        ('admin', '$defaultPassword', 'AD-', 'user'),
                        ('user1', '$defaultPassword', 'U01-', 'user'),
                        ('user2', '$defaultPassword', 'U02-', 'user')");
                }

                // 更新配置文件
                $configContent = "<?php
";
                $configContent .= "// 数据库配置文件
";
                $configContent .= "class Database {
";
                $configContent .= "    private \$host = '" . $host . "';
";
                $configContent .= "    private \$db_name = '" . $db_name . "';
";
                $configContent .= "    private \$username = '" . $username . "';
";
                $configContent .= "    private \$password = '" . $password . "';
";
                $configContent .= "    private \$charset = 'utf8mb4';
";
                $configContent .= "    public \$conn;

";
                $configContent .= "    // 获取数据库连接
";
                $configContent .= "    public function getConnection() {
";
                $configContent .= "        \$this->conn = null;

";
                $configContent .= "        try {
";
                $configContent .= "            \$dsn = 'mysql:host=' . \$this->host . ';dbname=' . \$this->db_name . ';charset=' . \$this->charset;
";
                $configContent .= "            \$options = array(
";
                $configContent .= "                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
";
                $configContent .= "                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
";
                $configContent .= "                PDO::ATTR_EMULATE_PREPARES => false,
";
                $configContent .= "            );

";
                $configContent .= "            \$this->conn = new PDO(\$dsn, \$this->username, \$this->password, \$options);
";
                $configContent .= "        } catch(PDOException \$exception) {
";
                $configContent .= "            echo '连接错误: ' . \$exception->getMessage();
";
                $configContent .= "        }

";
                $configContent .= "        return \$this->conn;
";
                $configContent .= "    }
";
                $configContent .= "}
";
                $configContent .= "?>";

                file_put_contents('config/database.php', $configContent);

                echo '<div class="message success">';
                echo '<h3>配置成功！</h3>';
                echo '<p>数据库连接成功，数据库表已创建。</p>';
                echo '<p>配置文件已更新。</p>';
                echo '<p><a href="index.php" class="btn btn-primary">进入系统</a></p>';
                echo '</div>';
            } catch (PDOException $e) {
                echo '<div class="message error">';
                echo '<h3>连接失败</h3>';
                echo '<p>错误信息：' . $e->getMessage() . '</p>';
                echo '<p>请检查您的数据库配置信息是否正确。</p>';
                echo '</div>';
            }
        }
        ?>

        <div class="message info">
            <p>请输入您的数据库连接信息。如果您使用的是phpStudy，默认密码通常是"root"。</p>
        </div>

        <form method="POST" action="">
            <div class="form-group">
                <label for="host">数据库主机</label>
                <input type="text" id="host" name="host" value="localhost" required>
            </div>

            <div class="form-group">
                <label for="db_name">数据库名称</label>
                <input type="text" id="db_name" name="db_name" value="file_management" required>
            </div>

            <div class="form-group">
                <label for="username">用户名</label>
                <input type="text" id="username" name="username" value="root" required>
            </div>

            <div class="form-group">
                <label for="password">密码</label>
                <input type="password" id="password" name="password" value="123456" required>
            </div>

            <div class="form-group">
                <button type="submit" class="btn btn-primary">测试连接并配置</button>
                <a href="index.php" class="btn btn-secondary">返回系统</a>
            </div>
        </form>
    </div>
</body>
</html>
