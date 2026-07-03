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
ini_set('error_log', __DIR__ . '/../logs/php_errors.log');

// 确保日志目录存在
$logDir = __DIR__ . '/../logs';
if (!file_exists($logDir)) {
    mkdir($logDir, 0755, true);
}

require_once '../config/database.php';

// 检查用户是否登录
error_log("export.php - 检查登录状态 - Session ID: " . session_id());
error_log("export.php - Session数据: " . print_r($_SESSION, true));

if (!isset($_SESSION['user_id'])) {
    error_log("export.php - 用户未登录");
    http_response_code(401);
    echo json_encode(['error' => '未授权']);
    exit;
}

error_log("export.php - 用户已登录: " . $_SESSION['username']);

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

// 查询文件数据
$query = "SELECT fileName, fileContent, leaderInstruction, issueDate FROM files $whereClause ORDER BY createdAt DESC";
$stmt = $db->prepare($query);
$stmt->execute($params);
$files = $stmt->fetchAll();

// 生成Excel文件
header('Content-Type: application/vnd.ms-excel');
header('Content-Disposition: attachment; filename="文件呈办情况_' . date('YmdHis') . '.xls"');
header('Pragma: no-cache');
header('Expires: 0');

// 输出Excel内容
echo '<html xmlns:o="urn:schemas-microsoft-com:office:office" xmlns:x="urn:schemas-microsoft-com:office:excel" xmlns="http://www.w3.org/TR/REC-html40">';
echo '<head><meta http-equiv="Content-Type" content="text/html; charset=utf-8"></head>';
echo '<body>';
echo '<table border="1">';
echo '<thead>';
echo '<tr>';
echo '<th colspan="4" style="text-align:center;font-size:16px;font-weight:bold;">文件呈办情况</th>';
echo '</tr>';
echo '<tr>';
echo '<th style="text-align:center;">文件名</th>';
echo '<th style="text-align:center;">文件内容</th>';
echo '<th style="text-align:center;">下发时间</th>';
echo '<th style="text-align:center;">批示</th>';
echo '</tr>';
echo '</thead>';
echo '<tbody>';

foreach ($files as $file) {
    echo '<tr>';
    echo '<td>' . htmlspecialchars($file['fileName']) . '</td>';
    echo '<td>' . htmlspecialchars($file['fileContent']) . '</td>';
    echo '<td>' . htmlspecialchars($file['issueDate'] ?? '') . '</td>';
    echo '<td>' . htmlspecialchars($file['leaderInstruction'] ?? '无') . '</td>';
    echo '</tr>';
}

echo '</tbody>';
echo '</table>';
echo '</body>';
echo '</html>';
?>