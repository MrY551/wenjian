<?php
// 数据库配置文件
class Database {
    private $host = 'localhost:3306';
    private $db_name = 'wenjian';
    private $username = 'root';
    private $password = '123456';
    private $charset = 'utf8mb4';
    public $conn;

    // 获取数据库连接
    public function getConnection() {
        $this->conn = null;

        try {
            $dsn = 'mysql:host=' . $this->host . ';dbname=' . $this->db_name . ';charset=' . $this->charset;
            $options = array(
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            );

            $this->conn = new PDO($dsn, $this->username, $this->password, $options);
        } catch(PDOException $exception) {
            error_log('数据库连接失败: ' . $exception->getMessage());
        }

        return $this->conn;
    }
}
?>