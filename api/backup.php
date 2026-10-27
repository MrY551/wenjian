<?php
ini_set('session.cookie_httponly', 1);
ini_set('session.use_only_cookies', 1);
ini_set('session.cookie_samesite', 'Lax');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if (isset($_SERVER['REQUEST_METHOD']) && $_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => '未登录']);
    exit;
}

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'superadmin') {
    http_response_code(403);
    echo json_encode(['error' => '无权访问']);
    exit;
}

require_once __DIR__ . '/csrf.php';
$method = isset($_SERVER['REQUEST_METHOD']) ? $_SERVER['REQUEST_METHOD'] : 'GET';
csrf_verify($method); // 写操作必须带 X-CSRF-Token
$action = isset($_GET['action']) ? $_GET['action'] : '';

require_once '../config/database.php';

switch ($action) {
    case 'export':
        if ($method === 'GET') {
            exportDatabase();
        }
        break;
    case 'import':
        if ($method === 'POST') {
            importDatabase();
        }
        break;
    default:
        http_response_code(400);
        echo json_encode(['error' => '无效的操作']);
        break;
}

function exportDatabase() {
    try {
        $database = new Database();
        $db = $database->getConnection();


        if (!$db) {
            throw new Exception('数据库连接失败');
        }

        // 纯 PDO 导出，不依赖外部 mysqldump。
        // 旧实现用 shell_exec('mysqldump ... 2>&1')，Windows 上 mysqldump 通常不在 PATH，
        // 命令失败时提示文本会被当作 SQL 内容下发（HTTP 200），看起来像备份成功。
        $dbName = $db->query('SELECT DATABASE()')->fetchColumn();
        $tables = $db->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);

        if (empty($tables)) {
            throw new Exception('数据库中没有可导出的表');
        }

        $lines = array();
        $lines[] = '-- 文件编号登记系统 数据库备份';
        $lines[] = '-- 数据库: ' . $dbName;
        $lines[] = '-- 导出时间: ' . date('Y-m-d H:i:s');
        $lines[] = '-- 表数量: ' . count($tables);
        $lines[] = '';
        $lines[] = 'SET NAMES utf8mb4;';
        $lines[] = 'SET CHARACTER_SET_CONNECTION=utf8mb4;';
        $lines[] = 'SET CHARACTER_SET_CLIENT=utf8mb4;';
        $lines[] = 'SET FOREIGN_KEY_CHECKS=0;';
        $lines[] = '';

        foreach ($tables as $table) {
            $createRow = $db->query('SHOW CREATE TABLE `' . $table . '`')->fetch(PDO::FETCH_NUM);
            if (!$createRow || !isset($createRow[1])) {
                throw new Exception('无法读取表结构: ' . $table);
            }

            $lines[] = '-- ----------------------------';
            $lines[] = '-- 表结构: ' . $table;
            $lines[] = '-- ----------------------------';
            $lines[] = 'DROP TABLE IF EXISTS `' . $table . '`;';
            $lines[] = $createRow[1] . ';';
            $lines[] = '';

            $rows = $db->query('SELECT * FROM `' . $table . '`')->fetchAll(PDO::FETCH_ASSOC);
            if (!empty($rows)) {
                $columns = array_keys($rows[0]);
                $columnList = '`' . implode('`, `', $columns) . '`';

                foreach ($rows as $row) {
                    $values = array();
                    foreach ($columns as $column) {
                        $value = $row[$column];
                        $values[] = ($value === null) ? 'NULL' : $db->quote($value);
                    }
                    $lines[] = 'INSERT INTO `' . $table . '` (' . $columnList . ') VALUES (' . implode(', ', $values) . ');';
                }

                $lines[] = '-- 共 ' . count($rows) . ' 行';
                $lines[] = '';
            }
        }

        $lines[] = 'SET FOREIGN_KEY_CHECKS=1;';
        $sql = implode("\n", $lines) . "\n";

        $filename = 'backup_' . date('Y-m-d_H-i-s') . '.sql';

        header('Content-Type: application/sql; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Content-Length: ' . strlen($sql));

        echo $sql;
        exit;

    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode([
            'error' => '导出失败',
            'message' => $e->getMessage()
        ]);
    }
}

function importDatabase() {
    try {
        if (!isset($_FILES['backup_file'])) {
            throw new Exception('请选择要上传的备份文件');
        }

        $file = $_FILES['backup_file'];
        
        if ($file['error'] !== UPLOAD_ERR_OK) {
            throw new Exception('文件上传失败，错误码：' . $file['error']);
        }

        $fileExtension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if ($fileExtension !== 'sql') {
            throw new Exception('只支持 .sql 格式的备份文件');
        }

        $maxSize = 50 * 1024 * 1024;
        if ($file['size'] > $maxSize) {
            throw new Exception('文件大小不能超过 50MB');
        }

        $sqlContent = file_get_contents($file['tmp_name']);
        if ($sqlContent === false) {
            throw new Exception('无法读取上传的文件');
        }

        $encoding = detectEncoding($sqlContent);
        if ($encoding !== 'UTF-8') {
            $sqlContent = mb_convert_encoding($sqlContent, 'UTF-8', $encoding);
        }

        $database = new Database();
        $db = $database->getConnection();


        if (!$db) {
            throw new Exception('数据库连接失败');
        }

        $db->exec("SET NAMES utf8mb4");
        $db->exec("SET CHARACTER_SET_CONNECTION=utf8mb4");
        $db->exec("SET CHARACTER_SET_CLIENT=utf8mb4");
        $db->exec("SET CHARACTER_SET_RESULTS=utf8mb4");

        // 导入前先把现有数据导出到 logs/。备份文件通常带 DROP TABLE，
        // 直接执行会覆盖现有数据，留一份快照才有回滚余地。
        $snapshotDir = __DIR__ . '/../logs';
        if (!is_dir($snapshotDir)) {
            @mkdir($snapshotDir, 0775, true);
        }
        $snapshotFailed = false;
        try {
            $snapshot = "-- 导入前自动快照 " . date('Y-m-d H:i:s') . "\nSET NAMES utf8mb4;\nSET FOREIGN_KEY_CHECKS=0;\n";
            foreach ($db->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN) as $table) {
                $create = $db->query("SHOW CREATE TABLE `$table`")->fetch(PDO::FETCH_NUM);
                $snapshot .= "DROP TABLE IF EXISTS `$table`;\n" . $create[1] . ";\n";
                $rows = $db->query("SELECT * FROM `$table`")->fetchAll(PDO::FETCH_ASSOC);
                if (!empty($rows)) {
                    $colList = '`' . implode('`, `', array_keys($rows[0])) . '`';
                    foreach ($rows as $row) {
                        $vals = array_map(function ($v) use ($db) {
                            return $v === null ? 'NULL' : $db->quote($v);
                        }, array_values($row));
                        $snapshot .= "INSERT INTO `$table` ($colList) VALUES (" . implode(', ', $vals) . ");\n";
                    }
                }
            }
            $snapshot .= "SET FOREIGN_KEY_CHECKS=1;\n";
            file_put_contents($snapshotDir . '/backup-auto-' . date('Ymd-His') . '.sql', $snapshot);
        } catch (Exception $snapshotError) {
            $snapshotFailed = true;
        }

        $db->exec("SET FOREIGN_KEY_CHECKS=0");

        ensureTableFields($db);

        $lines = explode("\n", $sqlContent);
        $currentQuery = '';
        $errorLines = [];
        $lineNumber = 0;

        foreach ($lines as $line) {
            $lineNumber++;
            $trimmedLine = trim($line);
            
            if (empty($trimmedLine) || strpos($trimmedLine, '--') === 0 || strpos($trimmedLine, '/*') === 0) {
                continue;
            }

            $currentQuery .= $line . "\n";

            if (substr(rtrim($line), -1) === ';') {
                try {
                    $db->exec($currentQuery);
                } catch (PDOException $e) {
                    $errorCode = $e->getCode();
                    if ($errorCode != '42S01' && $errorCode != '42000') {
                        $errorLines[] = [
                            'line' => $lineNumber,
                            'error' => $e->getMessage()
                        ];
                    }
                }
                $currentQuery = '';
            }
        }

        if (!empty(trim($currentQuery))) {
            try {
                $db->exec($currentQuery);
            } catch (PDOException $e) {
            }
        }

        $db->exec("SET FOREIGN_KEY_CHECKS=1");

        if (!empty($errorLines)) {
            http_response_code(200);
            echo json_encode([
                'success' => true,
                'message' => '数据库导入完成，但有部分警告',
                'warnings' => $errorLines
            ]);
        } else {
            http_response_code(200);
            echo json_encode([
                'success' => true,
                'message' => '数据库导入成功'
            ]);
        }

    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode([
            'error' => '导入失败',
            'message' => $e->getMessage()
        ]);
    }
}

function ensureTableFields($db) {
    try {
        $stmt = $db->prepare("SHOW TABLES LIKE 'files'");
        $stmt->execute();
        if (!$stmt->fetch()) {
            return;
        }

        $fieldsToCheck = [
            'userId' => "INT NOT NULL DEFAULT 1",
            'issueDate' => "DATE NOT NULL DEFAULT '2024-01-01'",
            'submitter' => "VARCHAR(100)",
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

        foreach ($fieldsToCheck as $field => $definition) {
            $stmt = $db->prepare("SHOW COLUMNS FROM files LIKE ?");
            $stmt->execute([$field]);
            if (!$stmt->fetch()) {
                $db->exec("ALTER TABLE files ADD COLUMN $field $definition");
            }
        }

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
        }

        $stmt = $db->prepare("SHOW TABLES LIKE 'instructions'");
        $stmt->execute();
        if (!$stmt->fetch()) {
            $db->exec("CREATE TABLE IF NOT EXISTS instructions (
                id INT AUTO_INCREMENT PRIMARY KEY,
                fileNumber VARCHAR(100) NOT NULL,
                leaderName VARCHAR(100) NOT NULL,
                content TEXT NOT NULL,
                date DATE NOT NULL,
                createdAt DATETIME DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (fileNumber) REFERENCES files(fileNumber) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        }

    } catch (PDOException $e) {
        error_log('ensureTableFields: ' . $e->getMessage());
    }
}

function detectEncoding($content) {
    if (substr($content, 0, 3) === "\xEF\xBB\xBF") {
        return 'UTF-8';
    }
    if (substr($content, 0, 2) === "\xFF\xFE") {
        return 'UTF-16LE';
    }
    if (substr($content, 0, 2) === "\xFE\xFF") {
        return 'UTF-16BE';
    }

    $encoding = mb_detect_encoding($content, ['UTF-8', 'GBK', 'GB2312', 'BIG5', 'ISO-8859-1'], true);
    
    return $encoding ?: 'UTF-8';
}
?>
