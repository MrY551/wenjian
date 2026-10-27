<?php
// 导出文件API
// 配置Session
ini_set('session.cookie_httponly', 1);
ini_set('session.use_only_cookies', 1);
ini_set('session.cookie_samesite', 'Lax');

// 启动Session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// 启用错误日志
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

// 确保日志目录存在
$logDir = __DIR__ . '/../logs';
if (!file_exists($logDir)) {
    mkdir($logDir, 0755, true);
}

require_once '../config/database.php';

// 检查用户是否登录

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => '未授权']);
    exit;
}


// 检查数据库连接
$database = new Database();
$db = $database->getConnection();

if (!$db) {
    http_response_code(500);
    echo json_encode(['error' => '数据库连接失败']);
    exit;
}

// 获取查询参数
$searchTerm = $_GET['search'] ?? '';
$approvalFilter = $_GET['approval'] ?? 'all';
$prefixFilter = $_GET['prefix'] ?? 'all';
$yearFilter = $_GET['year'] ?? 'all';
$startDate = $_GET['startDate'] ?? '';
$endDate = $_GET['endDate'] ?? '';

// 构建查询条件
$whereConditions = [];
$params = [];

if (!empty($searchTerm)) {
    $whereConditions[] = "(fileNumber LIKE ? OR fileName LIKE ?)";
    $params[] = "%$searchTerm%";
    $params[] = "%$searchTerm%";
}

if ($approvalFilter === 'all-approved') {
    $whereConditions[] = "approver01 = '已签批' AND approver02 = '已签批' AND approver03 = '已签批' AND approver04 = '已签批' AND approver05 = '已签批' AND approver06 = '已签批' AND approver07 = '已签批'";
} elseif ($approvalFilter === 'partial-approved') {
    $whereConditions[] = "(approver01 = '已签批' OR approver02 = '已签批' OR approver03 = '已签批' OR approver04 = '已签批' OR approver05 = '已签批' OR approver06 = '已签批' OR approver07 = '已签批')";
} elseif ($approvalFilter === 'none-approved') {
    $whereConditions[] = "approver01 = '未签批' AND approver02 = '未签批' AND approver03 = '未签批' AND approver04 = '未签批' AND approver05 = '未签批' AND approver06 = '未签批' AND approver07 = '未签批'";
}

if ($prefixFilter !== 'all') {
    $whereConditions[] = "fileNumber LIKE ?";
    $params[] = $prefixFilter . "%";
}

if ($yearFilter !== 'all') {
    $whereConditions[] = "fileNumber LIKE ?";
    $params[] = "%$yearFilter%";
}

if (!empty($startDate)) {
    $whereConditions[] = "DATE(createdAt) >= ?";
    $params[] = $startDate;
}

if (!empty($endDate)) {
    $whereConditions[] = "DATE(createdAt) <= ?";
    $params[] = $endDate;
}

$whereClause = '';
if (!empty($whereConditions)) {
    $whereClause = 'WHERE ' . implode(' AND ', $whereConditions);
}

// 查询文件数据（多取 fileNumber 作为首列，导出表更好认）
$query = "SELECT fileNumber, fileName, fileContent, leaderInstruction, issueDate FROM files $whereClause ORDER BY createdAt DESC";
$stmt = $db->prepare($query);
$stmt->execute($params);
$files = $stmt->fetchAll();

require_once __DIR__ . '/xlsx.php';

$columns = array(
    array('title' => '文件编号', 'width' => 22, 'align' => 'center'),
    array('title' => '文件名', 'width' => 30),
    array('title' => '文件内容', 'width' => 50),
    array('title' => '下发时间', 'width' => 14, 'align' => 'center'),
    array('title' => '批示', 'width' => 40),
);

$rows = array();
foreach ($files as $file) {
    $instruction = isset($file['leaderInstruction']) ? trim((string) $file['leaderInstruction']) : '';
    $rows[] = array(
        isset($file['fileNumber']) ? $file['fileNumber'] : '',
        $file['fileName'],
        $file['fileContent'],
        isset($file['issueDate']) ? $file['issueDate'] : '',
        $instruction === '' ? '无' : $instruction,
    );
}

$binary = xlsx_build($columns, $rows, '文件呈办情况');

if ($binary === false) {
    // 打包失败时退回 CSV，至少能拿到数据
    $filename = '文件呈办情况_' . date('YmdHis') . '.csv';
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    $out = fopen('php://output', 'wb');
    fwrite($out, "\xEF\xBB\xBF");
    fputcsv($out, array('文件编号', '文件名', '文件内容', '下发时间', '批示'));
    foreach ($rows as $row) {
        fputcsv($out, $row);
    }
    fclose($out);
    exit;
}

$stamp = date('YmdHis');
$utf8Name = '文件呈办情况_' . $stamp . '.xlsx';
$asciiName = 'export_' . $stamp . '.xlsx';

header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
// 中文名走 RFC 5987 的 filename*，同时给一个 ASCII 兜底名，避免旧浏览器乱码
header('Content-Disposition: attachment; filename="' . $asciiName . '"; filename*=UTF-8\'\'' . rawurlencode($utf8Name));
header('Content-Length: ' . strlen($binary));
header('Cache-Control: no-store, no-cache, must-revalidate');
header('Pragma: no-cache');

echo $binary;
?>
