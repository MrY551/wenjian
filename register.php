<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" href="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24'%3E%3Crect width='24' height='24' rx='5' fill='%233f51b5'/%3E%3Cpath d='M8 6.5h5.5L17 10v7.5H8z' fill='none' stroke='white' stroke-width='1.4' stroke-linejoin='round'/%3E%3Cpath d='M9.6 12.2h5M9.6 14.8h3.4' stroke='white' stroke-width='1.2' stroke-linecap='round'/%3E%3C/svg%3E">
    <title>文件编号登记系统 - 注册</title>
    <link rel="stylesheet" href="css/theme.css?v=<?php echo filemtime(__DIR__ . '/css/theme.css'); ?>">
    <link rel="stylesheet" href="styles.css?v=<?php echo filemtime(__DIR__ . '/styles.css'); ?>">
    <link rel="stylesheet" href="css/register.css?v=<?php echo filemtime(__DIR__ . '/css/register.css'); ?>">
</head>
<body>
    <div class="register-container">
        <div class="register-header">
            <h1>文件编号登记系统</h1>
            <p>创建新账户</p>
        </div>
        <form id="registerForm">
            <div class="form-group">
                <label for="username">用户名</label>
                <input type="text" id="username" name="username" required autocomplete="username">
            </div>
            <div class="form-group">
                <label for="password">密码</label>
                <input type="password" id="password" name="password" required autocomplete="new-password">
            </div>
            <div class="form-group">
                <label for="confirmPassword">确认密码</label>
                <input type="password" id="confirmPassword" name="confirmPassword" required autocomplete="new-password">
            </div>
            <p class="form-note">编号前缀由系统自动分配（U01-、U02- …），注册完成后即可登记文件。</p>
            <button type="submit" class="btn">注册</button>
            <div class="error-message" id="errorMessage"></div>
            <div class="success-message" id="successMessage"></div>
            <div class="loading" id="loading">注册中</div>
        </form>
        <div class="login-link">
            <a href="login.php">已有账户？点击登录</a>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // 检查是否已登录
            fetch('api/auth.php/check')
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        window.location.href = 'index.php';
                    }
                })
                .catch(() => {
                    // 未登录，显示注册表单
                });

            // 处理注册表单提交
            const registerForm = document.getElementById('registerForm');
            if (registerForm) {
                registerForm.addEventListener('submit', function(e) {
                    e.preventDefault();

                    const username = document.getElementById('username').value.trim();
                    const password = document.getElementById('password').value;
                    const confirmPassword = document.getElementById('confirmPassword').value;

                    // 验证输入（编号前缀已改为系统自动分配，不再校验）
                    if (!username || !password || !confirmPassword) {
                        showError('请填写所有字段');
                        return;
                    }

                    if (password !== confirmPassword) {
                        showError('两次输入的密码不一致');
                        return;
                    }

                    if (password.length < 6) {
                        showError('密码长度至少为6位');
                        return;
                    }

                    // 显示加载状态
                    document.getElementById('loading').style.display = 'block';
                    document.getElementById('errorMessage').style.display = 'none';
                    document.getElementById('successMessage').style.display = 'none';

                    // 发送注册请求
                    fetch('api/auth.php/register', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json'
                        },
                        body: JSON.stringify({
                            username: username,
                            password: password
                        })
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            const successMessage = document.getElementById('successMessage');
                            successMessage.textContent = '注册成功！编号前缀已自动分配为 ' +
                                (data.prefix || '—') + '，正在跳转到登录页面...';
                            successMessage.style.display = 'block';
                            setTimeout(function() {
                                window.location.href = 'login.php';
                            }, 2000);
                        } else {
                            showError(data.error || '注册失败');
                        }
                    })
                    .catch(error => {
                        console.error('注册错误:', error);
                        showError('注册失败，请重试');
                    })
                    .finally(() => {
                        document.getElementById('loading').style.display = 'none';
                    });
                });
            }

            // 显示错误消息
            function showError(message) {
                const errorMessage = document.getElementById('errorMessage');
                errorMessage.textContent = message;
                errorMessage.style.display = 'block';
            }
        });
    </script>
</body>
</html>
