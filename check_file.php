<?php
echo "<h2>服务器文件检查</h2>";
echo "<pre>";

// 检查 api/auth.php 文件的前几行
$file = 'api/auth.php';
if (file_exists($file)) {
    echo "文件: $file\n";
    echo "大小: " . filesize($file) . " bytes\n";
    echo "修改时间: " . date('Y-m-d H:i:s', filemtime($file)) . "\n\n";
    
    echo "文件内容（前50行）:\n";
    echo str_repeat('-', 50) . "\n";
    $lines = file($file);
    for ($i = 0; $i < min(50, count($lines)); $i++) {
        echo ($i + 1) . ": " . htmlspecialchars($lines[$i]);
    }
    echo str_repeat('-', 50) . "\n";
} else {
    echo "文件不存在: $file\n";
}

echo "</pre>";
?>
