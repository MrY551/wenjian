@echo off
chcp 65001 >nul
echo ========================================
echo   文件编号登记系统 - 数据库迁移工具
echo ========================================
echo.

REM 检查PHP是否可用
php -v >nul 2>&1
if %errorlevel% neq 0 (
    echo ❌ 错误：未找到PHP，请先安装PHP或将其添加到系统PATH
    pause
    exit /b 1
)

echo ✅ 正在执行数据库迁移...
php migrate_database.php

if %errorlevel% equ 0 (
    echo.
    echo ✅ 迁移完成！
) else (
    echo.
    echo ❌ 迁移过程中出现错误
)

echo.
pause