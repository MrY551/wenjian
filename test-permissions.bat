@echo off
chcp 65001 >nul
echo ========================================
echo   权限验证工具
echo ========================================
echo.

REM 检查PHP是否可用
php -v >nul 2>&1
if %errorlevel% neq 0 (
    echo ❌ 错误：未找到PHP，请先安装PHP或将其添加到系统PATH
    pause
    exit /b 1
)

echo ✅ 正在验证用户权限设置...
php test_admin_direct.php

echo.
pause