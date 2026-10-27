<?php
// 浏览器访问需要登录；命令行可直接运行
if (PHP_SAPI !== 'cli') {
    ini_set('session.cookie_httponly', 1);
    ini_set('session.use_only_cookies', 1);
    ini_set('session.cookie_samesite', 'Lax');
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    if (!isset($_SESSION['user_id'])) {
        http_response_code(403);
        exit('403 需要登录');
    }
}
// 测试API返回的数据
header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <title>API测试</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 20px;
        }
        .section {
            margin-bottom: 30px;
            padding: 15px;
            border: 1px solid #ddd;
            border-radius: 5px;
        }
        .section h2 {
            margin-top: 0;
        }
        pre {
            background: #f5f5f5;
            padding: 10px;
            border-radius: 4px;
            overflow-x: auto;
        }
        button {
            padding: 8px 15px;
            background: #007bff;
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
        }
        button:hover {
            background: #0056b3;
        }
    </style>
</head>
<body>
    <h1>API测试页面</h1>

    <div class="section">
        <h2>测试获取文件列表API</h2>
        <button onclick="testGetFiles()">获取文件列表</button>
        <pre id="filesResult">点击按钮测试API...</pre>
    </div>

    <div class="section">
        <h2>测试获取单个文件API</h2>
        <input type="number" id="fileId" placeholder="输入文件ID" value="1">
        <button onclick="testGetFile()">获取文件详情</button>
        <pre id="fileResult">点击按钮测试API...</pre>
    </div>

    <div class="section">
        <h2>测试获取批示历史API</h2>
        <input type="text" id="fileNumber" placeholder="输入文件编号" value="TEST-001">
        <button onclick="testGetInstructions()">获取批示历史</button>
        <pre id="instructionsResult">点击按钮测试API...</pre>
    </div>

    <script>
        function testGetFiles() {
            const resultElement = document.getElementById('filesResult');
            resultElement.textContent = '正在加载...';

            fetch('api/files.php')
                .then(response => {
                    console.log('响应状态:', response.status);
                    return response.json();
                })
                .then(data => {
                    console.log('文件列表数据:', data);
                    resultElement.textContent = JSON.stringify(data, null, 2);

                    // 检查第一个文件是否有leaderInstruction
                    if (data.length > 0) {
                        const firstFile = data[0];
                        console.log('第一个文件的leaderInstruction:', firstFile.leaderInstruction);
                        resultElement.textContent += '\n\n第一个文件的leaderInstruction: ' + (firstFile.leaderInstruction || '无');
                    }
                })
                .catch(error => {
                    console.error('错误:', error);
                    resultElement.textContent = '错误: ' + error.message;
                });
        }

        function testGetFile() {
            const fileId = document.getElementById('fileId').value;
            const resultElement = document.getElementById('fileResult');
            resultElement.textContent = '正在加载...';

            fetch('api/files.php/' + fileId)
                .then(response => {
                    console.log('响应状态:', response.status);
                    return response.json();
                })
                .then(data => {
                    console.log('文件详情数据:', data);
                    resultElement.textContent = JSON.stringify(data, null, 2);

                    // 检查文件的leaderInstruction
                    console.log('文件的leaderInstruction:', data.leaderInstruction);
                    resultElement.textContent += '\n\n文件的leaderInstruction: ' + (data.leaderInstruction || '无');
                })
                .catch(error => {
                    console.error('错误:', error);
                    resultElement.textContent = '错误: ' + error.message;
                });
        }

        function testGetInstructions() {
            const fileNumber = document.getElementById('fileNumber').value;
            const resultElement = document.getElementById('instructionsResult');
            resultElement.textContent = '正在加载...';

            fetch('api/instructions.php/file/' + fileNumber)
                .then(response => {
                    console.log('响应状态:', response.status);
                    return response.json();
                })
                .then(data => {
                    console.log('批示历史数据:', data);
                    resultElement.textContent = JSON.stringify(data, null, 2);
                })
                .catch(error => {
                    console.error('错误:', error);
                    resultElement.textContent = '错误: ' + error.message;
                });
        }
    </script>
</body>
</html>
