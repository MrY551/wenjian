<?php
// 文件编号登记系统 - PHP版本
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

// 引入必要的文件
require_once 'config/database.php';

// 检查数据库连接
$database = new Database();
$db = $database->getConnection();

// 检查用户是否登录
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

// 如果数据库连接失败，显示错误信息
if (!$db) {
    die('<div style="padding: 20px; color: red; font-weight: bold;">数据库连接失败，请检查配置</div>');
}

// 尝试创建数据库表（如果不存在）
try {
    // 创建文件表
    $db->exec("CREATE TABLE IF NOT EXISTS files (
        id INT AUTO_INCREMENT PRIMARY KEY,
        fileNumber VARCHAR(100) NOT NULL UNIQUE,
        fileName VARCHAR(255) NOT NULL,
        fileContent TEXT NOT NULL,
        submitter VARCHAR(100) COMMENT '呈办人',
        issueDate DATE NOT NULL COMMENT '下发时间',
        approver01 VARCHAR(20) DEFAULT '未签批',
        approver02 VARCHAR(20) DEFAULT '未签批',
        approver03 VARCHAR(20) DEFAULT '未签批',
        approver04 VARCHAR(20) DEFAULT '未签批',
        approver05 VARCHAR(20) DEFAULT '未签批',
        approver06 VARCHAR(20) DEFAULT '未签批',
        approver07 VARCHAR(20) DEFAULT '未签批',
        leaderInstruction TEXT,
        createdAt DATETIME DEFAULT CURRENT_TIMESTAMP,
        updatedAt DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    // 创建批示表
    $db->exec("CREATE TABLE IF NOT EXISTS instructions (
        id INT AUTO_INCREMENT PRIMARY KEY,
        fileNumber VARCHAR(100) NOT NULL,
        leaderName VARCHAR(100) NOT NULL,
        content TEXT NOT NULL,
        date DATE NOT NULL,
        createdAt DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (fileNumber) REFERENCES files(fileNumber) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
} catch (PDOException $e) {
    // 表创建失败，可能是已存在，忽略错误
}
?>

<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>文件编号登记系统</title>
    <link rel="stylesheet" href="styles.css">
    <link rel="stylesheet" href="css/all.min.css">
</head>
<body>
    <div class="container">
        <header>
            <h1>文件编号登记系统</h1>
            <div class="user-info">
                <span>用户: <strong><?php echo isset($_SESSION['username']) ? htmlspecialchars($_SESSION['username']) : '未登录'; ?></strong></span>
                <button id="logoutBtn" class="btn btn-sm btn-danger">退出登录</button>
            </div>
        </header>

        <div class="tabs">
            <button class="tab-button active" data-tab="register">登记文件</button>
            <button class="tab-button" data-tab="search">查询文件</button>
            <button class="tab-button" data-tab="leader-instructions">首长批示</button>
            <?php if (isset($_SESSION['role']) && $_SESSION['role'] === 'superadmin'): ?>
            <button class="tab-button" onclick="window.location.href='admin.php'">用户管理</button>
            <?php endif; ?>
        </div>

        <div class="tab-content">
            <div id="register" class="tab-pane active">
                <h2>文件登记</h2>
                <form id="fileForm">
                    <div class="form-group">
                        <label for="fileNumber">文件编号</label>
                        <div class="file-number-input">
                            <select id="filePrefix" name="filePrefix" required>
                                <option value="">请选择前缀</option>
                                <!-- 前缀选项将通过JavaScript动态加载 -->
                            </select>
                            <input type="text" id="fileNumber" name="fileNumber" placeholder="自动生成" readonly required>
                            <button type="button" id="syncPrefixesBtn" class="btn btn-secondary btn-sm" onclick="syncPrefixes()" title="从已登记文件中同步前缀">
                                <i class="fas fa-sync-alt"></i> 同步前缀
                            </button>
                        </div>
                        <small class="form-hint">文件编号格式：前缀+年份+【编号】（例如：站参2023【001】）</small>
                    </div>

                    <div class="form-group">
                        <label for="fileName">文件名 <span style="color: red;">*</span></label>
                        <input type="text" id="fileName" name="fileName" required>
                    </div>

                    <div class="form-group">
                        <label for="submitter">呈办人 <span style="color: red;">*</span></label>
                        <input type="text" id="submitter" name="submitter" placeholder="请输入呈办人姓名" required>
                    </div>

                    <div class="form-group">
                        <label for="issueDate">下发时间 <span style="color: red;">*</span></label>
                        <input type="date" id="issueDate" name="issueDate" required>
                    </div>

                    <div class="form-group">
                        <label for="fileContent">文件内容 <span style="color: red;">*</span></label>
                        <textarea id="fileContent" name="fileContent" rows="5" required></textarea>
                    </div>

                    <div class="form-group">
                        <label for="leaderInstruction">首长批示</label>
                        <textarea id="leaderInstruction" name="leaderInstruction" rows="3"></textarea>
                    </div>

                    <div class="form-group">
                        <label>签批情况</label>
                        <div class="approval-grid">
                            <div class="approval-item">
                                <label for="approver01">01号签批人</label>
                                <select id="approver01" name="approver01">
                                    <option value="未签批">未签批</option>
                                    <option value="已签批">已签批</option>
                                </select>
                            </div>

                            <div class="approval-item">
                                <label for="approver02">02号签批人</label>
                                <select id="approver02" name="approver02">
                                    <option value="未签批">未签批</option>
                                    <option value="已签批">已签批</option>
                                </select>
                            </div>

                            <div class="approval-item">
                                <label for="approver03">03号签批人</label>
                                <select id="approver03" name="approver03">
                                    <option value="未签批">未签批</option>
                                    <option value="已签批">已签批</option>
                                </select>
                            </div>

                            <div class="approval-item">
                                <label for="approver04">04号签批人</label>
                                <select id="approver04" name="approver04">
                                    <option value="未签批">未签批</option>
                                    <option value="已签批">已签批</option>
                                </select>
                            </div>

                            <div class="approval-item">
                                <label for="approver05">05号签批人</label>
                                <select id="approver05" name="approver05">
                                    <option value="未签批">未签批</option>
                                    <option value="已签批">已签批</option>
                                </select>
                            </div>

                            <div class="approval-item">
                                <label for="approver06">06号签批人</label>
                                <select id="approver06" name="approver06">
                                    <option value="未签批">未签批</option>
                                    <option value="已签批">已签批</option>
                                </select>
                            </div>

                            <div class="approval-item">
                                <label for="approver07">07号签批人</label>
                                <select id="approver07" name="approver07">
                                    <option value="未签批">未签批</option>
                                    <option value="已签批">已签批</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <div class="form-actions">
                        <button type="submit" class="btn btn-primary">保存</button>
                        <button type="reset" class="btn btn-secondary">重置</button>
                    </div>
                </form>
            </div>

            <div id="search" class="tab-pane">
                <h2>文件查询</h2>
                <div class="search-container">
                    <div class="search-form">
                        <input type="text" id="searchInput" placeholder="输入文件编号或文件名...">
                        <button id="searchButton" class="btn btn-primary">搜索</button>
                        <button id="exportButton" class="btn btn-secondary">导出表格</button>
                    </div>

                    <div class="filter-options">
                        <label for="approvalFilter">签批状态筛选:</label>
                        <select id="approvalFilter">
                            <option value="all">全部</option>
                            <option value="all-approved">全部已签批</option>
                            <option value="partial-approved">部分签批</option>
                            <option value="none-approved">全部未签批</option>
                            <option value="no-instruction">无首长批示</option>
                        </select>
                        
                        <label for="prefixFilter">前缀筛选:</label>
                        <select id="prefixFilter">
                            <option value="all">全部前缀</option>
                        </select>

                        <label for="yearFilter">年份筛选:</label>
                        <select id="yearFilter">
                            <option value="all">全部年份</option>
                        </select>

                        <label for="startDate">开始日期:</label>
                        <input type="date" id="startDate">

                        <label for="endDate">结束日期:</label>
                        <input type="date" id="endDate">
                    </div>
                </div>

                <div class="table-container">
                    <table id="filesTable">
                        <thead>
                            <tr>
                                <th>文件编号</th>
                                <th>文件名</th>
                                <th>下发时间</th>
                                <th>首长批示</th>
                                <th>签批情况</th>
                                <th>操作</th>
                            </tr>
                        </thead>
                        <tbody>
                            <!-- 表格内容将通过JavaScript动态生成 -->
                        </tbody>
                    </table>
                    <div id="pagination"></div>
                </div>
            </div>

            <!-- 首长批示编辑模态框 -->
            <div id="editInstructionModal" class="modal">
                <div class="modal-content">
                    <div class="modal-header">
                        <h3>编辑首长批示</h3>
                        <span class="close">&times;</span>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" id="editInstructionFileId">
                        <div class="form-group">
                            <label for="editInstructionFileNumber">文件编号</label>
                            <input type="text" id="editInstructionFileNumber" readonly>
                        </div>
                        <div class="form-group">
                            <label for="editInstructionContent">首长批示内容</label>
                            <textarea id="editInstructionContent" rows="5" placeholder="输入首长批示内容"></textarea>
                        </div>
                        <div class="form-actions">
                            <button id="saveEditInstructionButton" class="btn btn-primary">保存批示</button>
                            <button id="cancelEditInstructionButton" class="btn btn-secondary">取消</button>
                        </div>
                    </div>
                </div>
            </div>

            <div id="leader-instructions" class="tab-pane">
                <h2>首长批示</h2>
                <div class="instruction-container">
                    <div class="instruction-form">
                        <div class="form-group">
                            <label for="instructionFileNumber">文件编号</label>
                            <input type="text" id="instructionFileNumber" placeholder="选择或输入文件编号">
                            <button id="selectFileButton" class="btn btn-secondary">选择文件</button>
                        </div>

                        <div class="form-group">
                            <label for="leaderName">首长姓名</label>
                            <input type="text" id="leaderName" placeholder="输入首长姓名">
                        </div>

                        <div class="form-group">
                            <label for="instructionContent">批示内容</label>
                            <textarea id="instructionContent" rows="5" placeholder="输入批示内容"></textarea>
                        </div>

                        <div class="form-group">
                            <label for="instructionDate">批示日期</label>
                            <input type="date" id="instructionDate">
                        </div>

                        <div class="form-actions">
                            <button id="saveInstructionButton" class="btn btn-primary">保存批示</button>
                            <button id="clearInstructionForm" class="btn btn-secondary">清除</button>
                        </div>
                    </div>

                    <div class="instruction-history">
                        <h3>批示历史记录</h3>
                        <div class="instruction-list">
                            <!-- 批示历史将通过JavaScript动态生成 -->
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <footer>
            <p>文件编号登记系统 &copy; 开发者：黄镒  15359927763 开发时间： 2026年1月9日</p>
        </footer>
    </div>

    <!-- 文件选择模态框 -->
    <div id="fileSelectionModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>选择文件</h3>
                <span class="close">&times;</span>
            </div>
            <div class="modal-body">
                <div class="search-form">
                    <input type="text" id="modalSearchInput" placeholder="输入文件编号或文件名...">
                    <button id="modalSearchButton" class="btn btn-primary">搜索</button>
                </div>
                <div class="file-list">
                    <!-- 文件列表将通过JavaScript动态生成 -->
                </div>
            </div>
        </div>
    </div>

    <script src="jquery-3.7.1.js"></script>
    <script src="script-php.js?v=<?php echo time(); ?>"></script>
</body>
</html>
