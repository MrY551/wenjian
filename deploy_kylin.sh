#!/bin/bash

# 文件编号登记系统 - 银河麒麟部署脚本

set -e

# 颜色定义
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

# 打印带颜色的消息
print_info() {
    echo -e "${GREEN}[信息]${NC} $1"
}

print_error() {
    echo -e "${RED}[错误]${NC} $1"
}

print_warning() {
    echo -e "${YELLOW}[警告]${NC} $1"
}

# 检查是否以root权限运行
check_root() {
    if [ "$EUID" -ne 0 ]; then 
        print_error "请使用root权限运行此脚本"
        print_info "使用方法: sudo $0"
        exit 1
    fi
}

# 检查系统是否为银河麒麟
check_system() {
    if [ ! -f /etc/.kyinfo ]; then
        print_warning "未检测到银河麒麟系统，脚本可能无法正常工作"
        read -p "是否继续? (y/n): " continue
        if [ "$continue" != "y" ]; then
            exit 1
        fi
    fi
}

# 检查必要的命令
check_commands() {
    local commands=("apt" "systemctl")
    for cmd in "${commands[@]}"; do
        if ! command -v $cmd &> /dev/null; then
            print_error "$cmd 命令未找到，请先安装"
            exit 1
        fi
    done
}

# 更新软件源
update_sources() {
    print_info "更新软件源..."
    apt-get update
}

# 安装必要的软件包
install_packages() {
    print_info "安装必要的软件包..."

    # 检查是否已安装
    if dpkg -l | grep -q apache2; then
        print_info "Apache2 已安装"
    else
        apt-get install -y apache2
    fi

    if dpkg -l | grep -q php; then
        print_info "PHP 已安装"
    else
        apt-get install -y php php-mysql php-json php-mbstring
    fi

    if dpkg -l | grep -q mariadb-server; then
        print_info "MariaDB 已安装"
    else
        apt-get install -y mariadb-server
    fi

    # 启动服务
    systemctl enable apache2 mariadb
    systemctl start apache2 mariadb
}

# 配置数据库
configure_database() {
    print_info "配置数据库..."

    # 数据库配置
    read -p "请输入数据库主机(默认localhost): " db_host
    db_host=${db_host:-localhost}

    read -p "请输入数据库名(默认file_management): " db_name
    db_name=${db_name:-file_management}

    read -p "请输入数据库用户名(默认root): " db_user
    db_user=${db_user:-root}

    read -s -p "请输入数据库密码(默认123456): " db_pass
    db_pass=${db_pass:-123456}
    echo

    # 创建数据库配置文件
    cat > config/database.php <<EOF
<?php
// 数据库配置文件
class Database {
    private $host = '$db_host';
    private $db_name = '$db_name';
    private $username = '$db_user';
    private $password = '$db_pass';
    private $charset = 'utf8mb4';
    public $conn;

    // 获取数据库连接
    public function getConnection() {
        \$this->conn = null;

        try {
            \$dsn = 'mysql:host=' . \$this->host . ';dbname=' . \$this->db_name . ';charset=' . \$this->charset;
            \$options = array(
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            );

            \$this->conn = new PDO(\$dsn, \$this->username, \$this->password, \$options);
        } catch(PDOException \$exception) {
            echo '连接错误: ' . \$exception->getMessage();
        }

        return \$this->conn;
    }
}
?>
EOF

    # 创建数据库
    mysql -h $db_host -u$db_user -p$db_pass -e "CREATE DATABASE IF NOT EXISTS $db_name DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"

    # 导入数据库结构
    mysql -h $db_host -u$db_user -p$db_pass $db_name < database/init.sql
}

# 配置Apache
configure_apache() {
    print_info "配置Apache..."

    # 设置网站根目录
    local web_root="/var/www/html/wenjian"

    # 创建目录
    mkdir -p $web_root

    # 复制文件
    cp -r . $web_root/

    # 设置权限
    chown -R www-data:www-data $web_root
    chmod -R 755 $web_root

    # 创建必要的目录
    mkdir -p $web_root/uploads
    mkdir -p $web_root/logs
    chown -R www-data:www-data $web_root/uploads
    chown -R www-data:www-data $web_root/logs

    # 配置PHP
    if [ -f /etc/php/*/apache2/php.ini ]; then
        # 修改上传文件大小限制
        sed -i 's/upload_max_filesize = .*/upload_max_filesize = 50M/' /etc/php/*/apache2/php.ini
        sed -i 's/post_max_size = .*/post_max_size = 50M/' /etc/php/*/apache2/php.ini
    fi

    # 重启Apache
    systemctl restart apache2
}

# 创建启动脚本
create_start_script() {
    print_info "创建启动脚本..."

    cat > /usr/local/bin/wenjian-start.sh <<'EOF'
#!/bin/bash
# 启动文件编号登记系统服务

systemctl start apache2
systemctl start mariadb

echo "文件编号登记系统服务已启动"
EOF

    chmod +x /usr/local/bin/wenjian-start.sh

    cat > /usr/local/bin/wenjian-stop.sh <<'EOF'
#!/bin/bash
# 停止文件编号登记系统服务

systemctl stop apache2
systemctl stop mariadb

echo "文件编号登记系统服务已停止"
EOF

    chmod +x /usr/local/bin/wenjian-stop.sh
}

# 显示完成信息
show_completion() {
    echo
    echo "========================================"
    echo -e "${GREEN}部署成功完成！${NC}"
    echo "========================================"
    echo
    echo "默认账号信息："
    echo "超级管理员: superadmin / 123456"
    echo "管理员: admin / 123456"
    echo "普通用户: user1 / 123456"
    echo "普通用户: user2 / 123456"
    echo
    echo "访问地址: http://localhost/wenjian"
    echo
    echo "服务管理："
    echo "启动服务: wenjian-start.sh"
    echo "停止服务: wenjian-stop.sh"
    echo
    echo -e "${YELLOW}重要提示: 首次登录后请立即修改默认密码！${NC}"
    echo
}

# 主函数
main() {
    echo "========================================"
    echo "文件编号登记系统 - 银河麒麟部署脚本"
    echo "========================================"
    echo

    check_root
    check_system
    check_commands
    update_sources
    install_packages
    configure_database
    configure_apache
    create_start_script
    show_completion
}

# 执行主函数
main
