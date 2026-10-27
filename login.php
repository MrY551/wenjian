<?php
// 文件编号登记系统 - 登录页
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
ini_set('error_log', __DIR__ . '/logs/php_errors.log');

// 确保日志目录存在
$logDir = __DIR__ . '/logs';
if (!file_exists($logDir)) {
    mkdir($logDir, 0755, true);
}

// 已登录直接进入系统；?force=1 可强制显示登录页用于切换账号
if (isset($_SESSION['user_id']) && !isset($_GET['force'])) {
    header('Location: index.php');
    exit;
}

// 静态资源版本号，避免浏览器缓存旧样式（mtime 变化即刷新）
$ver = function ($relativePath) {
    $abs = __DIR__ . '/' . $relativePath;
    return file_exists($abs) ? filemtime($abs) : time();
};
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="theme-color" content="#4b5ea7">
    <title>登录 - 文件编号登记系统</title>
    <link rel="icon" href="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24'%3E%3Crect width='24' height='24' rx='5' fill='%234b5ea7'/%3E%3Cpath d='M8 6.5h5.5L17 10v7.5H8z' fill='none' stroke='white' stroke-width='1.4' stroke-linejoin='round'/%3E%3Cpath d='M9.6 12.2h5M9.6 14.8h3.4' stroke='white' stroke-width='1.2' stroke-linecap='round'/%3E%3C/svg%3E">
    <link rel="stylesheet" href="css/theme.css?v=<?php echo $ver('css/theme.css'); ?>">
    <link rel="stylesheet" href="css/login.css?v=<?php echo $ver('css/login.css'); ?>">
</head>
<body>
<div class="login-overlay">
    <div class="login-grid">
        <!-- 左侧：动画角色 -->
        <div class="login-left">
            <div class="login-left__brand">
                <svg class="login-brand-mark login-left__brand-mark" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
                    <path d="M7 3.5h7.5L19 7.9V20a1 1 0 0 1-1 1H7a1 1 0 0 1-1-1V4.5a1 1 0 0 1 1-1Z" stroke="currentColor" stroke-width="1.5" stroke-linejoin="round"/>
                    <path d="M14 3.5V8h4.5" stroke="currentColor" stroke-width="1.5" stroke-linejoin="round"/>
                    <path d="M9 12.5h6M9 16h4" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/>
                </svg>
                <span class="login-left__brand-wordmark">文件编号登记系统</span>
            </div>

            <div class="login-left__characters" id="animated-characters-root"></div>

            <div class="login-left__deco login-left__deco--blur1"></div>
            <div class="login-left__deco login-left__deco--blur2"></div>
        </div>

        <!-- 右侧：登录表单 -->
        <div class="login-right">
            <div class="login-form-wrapper">
                <div class="login-mobile-logo">
                    <svg class="login-brand-mark login-logo" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
                        <path d="M7 3.5h7.5L19 7.9V20a1 1 0 0 1-1 1H7a1 1 0 0 1-1-1V4.5a1 1 0 0 1 1-1Z" stroke="currentColor" stroke-width="1.5" stroke-linejoin="round"/>
                        <path d="M14 3.5V8h4.5" stroke="currentColor" stroke-width="1.5" stroke-linejoin="round"/>
                        <path d="M9 12.5h6M9 16h4" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/>
                    </svg>
                    <span class="login-brand-text">文件编号登记系统</span>
                </div>

                <div class="login-brand-mark-wrap">
                    <svg class="login-brand-mark" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
                        <path d="M7 3.5h7.5L19 7.9V20a1 1 0 0 1-1 1H7a1 1 0 0 1-1-1V4.5a1 1 0 0 1 1-1Z" stroke="currentColor" stroke-width="1.5" stroke-linejoin="round"/>
                        <path d="M14 3.5V8h4.5" stroke="currentColor" stroke-width="1.5" stroke-linejoin="round"/>
                        <path d="M9 12.5h6M9 16h4" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/>
                    </svg>
                </div>

                <div class="login-header">
                    <h1 class="login-title">欢迎回来</h1>
                    <p class="login-subtitle">请使用您的账号登录文件编号登记系统</p>
                </div>

                <form class="login-form" id="login-form" method="post" autocomplete="on" novalidate>
                    <div class="login-field">
                        <label for="login-account" class="login-label">账号</label>
                        <input
                            id="login-account"
                            type="text"
                            class="login-input"
                            name="username"
                            autocomplete="username"
                            autocapitalize="off"
                            spellcheck="false"
                            placeholder="请输入账号"
                            required
                        />
                    </div>

                    <div class="login-field">
                        <label for="login-password" class="login-label">密码</label>
                        <div class="login-input-wrap">
                            <input
                                id="login-password"
                                class="login-input"
                                name="password"
                                autocomplete="current-password"
                                placeholder="请输入密码"
                                type="password"
                                required
                            />
                            <button
                                type="button"
                                class="login-input-suffix"
                                id="toggle-password"
                                aria-label="显示密码"
                            >
                                <svg class="login-icon login-icon--view" viewBox="0 0 1024 1024" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
                                    <path fill="currentColor" d="M512 256c-168 0-329.6 106.4-384 256 54.4 149.6 216 256 384 256 168 0 329.6-106.4 384-256-54.4-149.6-216-256-384-256zm0 416c-88 0-160-72-160-160s72-160 160-160 160 72 160 160-72 160-160 160zm0-256a96 96 0 1 0 0 192 96 96 0 0 0 0-192z"/>
                                </svg>
                                <svg class="login-icon login-icon--hide" hidden viewBox="0 0 1024 1024" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
                                    <path fill="currentColor" d="M942.3 486.4l-0.1-0.1-0.1-0.1c-36.4-82.2-89.4-154.1-156.5-213.5l129.7-129.7c6.3-6.3 6.3-16.5 0-22.8L816.7 86.7c-6.3-6.3-16.5-6.3-22.8 0L664.2 216.4C602.1 176.8 529.5 152 451.8 152 283.8 152 122.2 258.4 67.7 408c-4.1 11.3-4.1 23.7 0 35 36.4 82.2 89.4 154.1 156.5 213.5l-129.7 129.7c-6.3 6.3-6.3 16.5 0 22.8l45.3 45.3c6.3 6.3 16.5 6.3 22.8 0l129.7-129.7c62.1 39.6 134.7 64.4 212.4 64.4 168 0 329.6-106.4 384-256 4.1-11.3 4.1-23.7 0-35zM512 672c-88 0-160-72-160-160s72-160 160-160 160 72 160 160-72 160-160 160z"/>
                                </svg>
                            </button>
                        </div>
                    </div>

                    <div class="login-error" id="login-error" role="alert" hidden></div>

                    <button type="submit" class="login-btn login-btn--primary" id="login-submit">
                        <span class="login-btn__text">登录</span>
                        <span class="login-btn__hover">
                            <span>登录</span>
                            <svg class="login-btn__arrow" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                                <path d="M5 12h14M12 5l7 7-7 7"/>
                            </svg>
                        </span>
                    </button>
                </form>

                <div class="login-footer">
                    <span>没有账户？</span><a href="register.php">立即注册</a>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="js/login-characters.js?v=<?php echo $ver('js/login-characters.js'); ?>"></script>
<script src="js/login.js?v=<?php echo $ver('js/login.js'); ?>"></script>
</body>
</html>
