<?php
// 设置响应头
header('Access-Control-Allow-Origin: *');
header('Content-Type: application/json; charset=utf-8');

// 引入数据库配置
require_once '../config/database.php';

try {
    // 获取数据库连接
    $database = new Database();
    $db = $database->getConnection();

    if ($db) {
        // 测试数据库连接
        $stmt = $db->query("SELECT 1 AS test");
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($result && $result['test'] == 1) {
            http_response_code(200);
            echo json_encode([
                'status' => 'ok',
                'message' => '数据库连接正常',
                'database' => $db->query('SELECT DATABASE()')->fetchColumn(),
                'timestamp' => date('Y-m-d H:i:s')
            ]);
        } else {
            http_response_code(500);
            echo json_encode([
                'status' => 'error',
                'message' => '数据库连接测试失败'
            ]);
        }
    } else {
        http_response_code(500);
        echo json_encode([
            'status' => 'error',
            'message' => '无法连接到数据库'
        ]);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => '系统错误'
    ]);
}
?>
