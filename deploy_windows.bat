@echo off
chcp 65001 >nul
setlocal enabledelayedexpansion

echo ========================================
echo 文件编号登记系统 - Windows部署脚本
echo ========================================
echo.

:: 检查PHP是否已安装
where php >nul 2>&1
if %errorlevel% neq 0 (
    echo [错误] 未检测到PHP，请先安装PHPStudy或单独安装PHP
    echo 下载地址: https://www.xp.cn/download.html
    pause
    exit /b 1
)

:: 检查MySQL是否已安装
where mysql >nul 2>&1
if %errorlevel% neq 0 (
    echo [错误] 未检测到MySQL，请先安装MySQL
    echo 如果使用PHPStudy，请确保MySQL服务已启动
    pause
    exit /b 1
)

echo [步骤1] 配置数据库...
set /p db_host="请输入数据库主机(默认localhost): "
if "!db_host!"=="" set db_host=localhost

set /p db_name="请输入数据库名(默认file_management): "
if "!db_name!"=="" set db_name=file_management

set /p db_user="请输入数据库用户名(默认root): "
if "!db_user!"=="" set db_user=root

set /p db_pass="请输入数据库密码(默认123456): "
if "!db_pass!"=="" set db_pass=123456

:: 创建数据库配置文件
(
echo ^<?php
echo // 数据库配置文件
echo class Database {
echo     private $host = '!db_host!';
echo     private $db_name = '!db_name!';
echo     private $username = '!db_user!';
echo     private $password = '!db_pass!';
echo     private $charset = 'utf8mb4';
echo     public $conn;
echo 
echo     // 获取数据库连接
echo     public function getConnection^(^) {
echo         $this-^>conn = null;
echo 
echo         try {
echo             $dsn = 'mysql:host=' . $this-^>host . ';dbname=' . $this-^>db_name . ';charset=' . $this-^>charset;
echo             $options = array^(
echo                 PDO::ATTR_ERRMODE =^> PDO::ERRMODE_EXCEPTION,
echo                 PDO::ATTR_DEFAULT_FETCH_MODE =^> PDO::FETCH_ASSOC,
echo                 PDO::ATTR_EMULATE_PREPARES =^> false,
echo             ^);
echo 
echo             $this-^>conn = new PDO^($dsn, $this-^>username, $this-^>password, $options^);
echo         } catch^(PDOException $exception^) {
echo             echo '连接错误: ' . $exception-^>getMessage^(^);
echo         }
echo 
echo         return $this-^>conn;
echo     }
echo }
echo ?^>
) > config\database.php

echo [步骤2] 创建数据库...
mysql -h !db_host! -u!db_user! -p!db_pass! -e "CREATE DATABASE IF NOT EXISTS !db_name! DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;" 2>nul
if %errorlevel% neq 0 (
    echo [错误] 数据库创建失败，请检查数据库配置
    pause
    exit /b 1
)

echo [步骤3] 导入数据库结构...
mysql -h !db_host! -u!db_user! -p!db_pass! !db_name! < database\init.sql 2>nul
if %errorlevel% neq 0 (
    echo [错误] 数据库结构导入失败
    pause
    exit /b 1
)

echo [步骤4] 设置目录权限...
if not exist "uploads" mkdir uploads
if not exist "logs" mkdir logs

echo [步骤5] 配置完成！
echo.
echo ========================================
echo 部署成功完成！
echo ========================================
echo.
echo 默认账号信息：
echo 超级管理员: superadmin / 123456
echo 管理员: admin / 123456
echo 普通用户: user1 / 123456
echo 普通用户: user2 / 123456
echo.
echo 访问地址: http://localhost
echo.
echo 重要提示: 首次登录后请立即修改默认密码！
echo.
pause
