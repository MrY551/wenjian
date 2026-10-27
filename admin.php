<?php
ini_set('session.cookie_httponly', 1);
ini_set('session.use_only_cookies', 1);
ini_set('session.cookie_samesite', 'Lax');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/api/csrf.php';

// 未登录先跳登录页
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

// 只有超级管理员可以访问
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'superadmin') {
    http_response_code(403);
    die('<div style="padding: 20px; color: #d92d20; font-weight: bold;">无权访问此页面</div>');
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" href="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24'%3E%3Crect width='24' height='24' rx='5' fill='%233f51b5'/%3E%3Cpath d='M8 6.5h5.5L17 10v7.5H8z' fill='none' stroke='white' stroke-width='1.4' stroke-linejoin='round'/%3E%3Cpath d='M9.6 12.2h5M9.6 14.8h3.4' stroke='white' stroke-width='1.2' stroke-linecap='round'/%3E%3C/svg%3E">
    <title>用户管理 - 超级管理员</title>
    <meta name="csrf-token" content="<?php echo htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8'); ?>">
    <link rel="stylesheet" href="css/theme.css?v=<?php echo filemtime(__DIR__ . '/css/theme.css'); ?>">
    <link rel="stylesheet" href="styles.css?v=<?php echo filemtime(__DIR__ . '/styles.css'); ?>">
    <script src="js/csrf.js?v=<?php echo filemtime(__DIR__ . '/js/csrf.js'); ?>"></script>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>系统管理</h1>
            <div>
                <a href="index.php" class="btn btn-secondary">返回首页</a>
            </div>
        </div>

        <div class="tabs">
            <button class="tab-button active" data-tab="users">用户管理</button>
            <button class="tab-button" data-tab="prefixes">前缀管理</button>
            <button class="tab-button" data-tab="backup">数据库备份</button>
        </div>

        <div class="tab-content">
            <div id="users" class="tab-pane active">
                <div class="sub-header">
                    <h2>用户管理</h2>
                    <button id="addUserBtn" class="btn btn-primary">添加用户</button>
                </div>
                <div class="user-table">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>用户名</th>
                                <th>编号前缀</th>
                                <th>角色</th>
                                <th>创建时间</th>
                                <th>操作</th>
                            </tr>
                        </thead>
                        <tbody id="usersTableBody">
                            <!-- 用户列表将通过JavaScript动态生成 -->
                        </tbody>
                    </table>
                </div>
                <div class="loading" id="loading">加载中</div>
            </div>

            <div id="prefixes" class="tab-pane">
                <div class="sub-header">
                    <h2>前缀管理</h2>
                    <button id="addPrefixBtn" class="btn btn-primary">添加前缀</button>
                </div>
                <div class="prefix-table">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>前缀</th>
                                <th>描述</th>
                                <th>创建时间</th>
                                <th>操作</th>
                            </tr>
                        </thead>
                        <tbody id="prefixesTableBody">
                            <!-- 前缀列表将通过JavaScript动态生成 -->
                        </tbody>
                    </table>
                </div>
                <div class="loading" id="prefixLoading">加载中</div>
            </div>

            <div id="backup" class="tab-pane">
                <div class="sub-header">
                    <h2>数据库备份与恢复</h2>
                </div>
                <div class="backup-container">
                    <div class="backup-section">
                        <h3><svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 3v11"/><path d="M8 10l4 4 4-4"/><path d="M4 17v2a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2v-2"/></svg> 导出备份</h3>
                        <p>点击按钮下载当前数据库的完整备份文件</p>
                        <button id="exportBtn" class="btn btn-primary">
                            <svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M14 3H7a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V8z"/><path d="M14 3v5h5"/><path d="M12 11v7"/><path d="M9 15l3 3 3-3"/></svg> 导出数据库
                        </button>
                    </div>
                    <div class="backup-section">
                        <h3><svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 17V6"/><path d="M8 10l4-4 4 4"/><path d="M4 17v2a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2v-2"/></svg> 导入备份</h3>
                        <p>选择 .sql 备份文件导入数据库</p>
                        <form id="importForm">
                            <div class="form-group">
                                <input type="file" id="backupFile" accept=".sql" required>
                            </div>
                            <button type="submit" class="btn btn-warning">
                                <svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M14 3H7a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V8z"/><path d="M14 3v5h5"/><path d="M12 18v-7"/><path d="M9 14l3-3 3 3"/></svg> 导入数据库
                            </button>
                        </form>
                    </div>
                </div>
                <div id="backupMessage" class="message" style="display:none;"></div>
            </div>
        </div>
    </div>

    <!-- 添加用户模态框 -->
    <div class="modal" id="addUserModal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>添加新用户</h3>
                <span class="close">&times;</span>
            </div>
            <form id="addUserForm">
                <div class="form-group">
                    <label for="username">用户名</label>
                    <input type="text" id="username" name="username" required>
                </div>
                <div class="form-group">
                    <label for="password">密码</label>
                    <input type="password" id="password" name="password" required>
                </div>
                <div class="form-group">
                    <label for="confirmPassword">确认密码</label>
                    <input type="password" id="confirmPassword" name="confirmPassword" required>
                </div>
                <div class="form-group">
                    <label for="prefix">编号前缀</label>
                    <input type="text" id="prefix" name="prefix" required placeholder="例如：A01-">
                    <div class="form-hint">格式：支持中文、字母、数字和横杠，长度2-10个字符，例如：部门A-</div>
                </div>
                <div class="form-group">
                    <label for="role">角色</label>
                    <select id="role" name="role">
                        <option value="user">普通用户</option>
                        <option value="admin">管理员</option>
                    </select>
                </div>
                <button type="submit" class="btn btn-primary">创建用户</button>
            </form>
        </div>
    </div>

    <!-- 重置密码模态框 -->
    <div class="modal" id="resetPasswordModal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>重置密码</h3>
                <span class="close">&times;</span>
            </div>
            <form id="resetPasswordForm">
                <input type="hidden" id="resetUserId" name="userId">
                <div class="form-group">
                    <label>用户名</label>
                    <input type="text" id="resetUsername" name="username" readonly>
                </div>
                <div class="form-group">
                    <label for="newPassword">新密码</label>
                    <input type="password" id="newPassword" name="newPassword" required>
                </div>
                <div class="form-group">
                    <label for="confirmNewPassword">确认新密码</label>
                    <input type="password" id="confirmNewPassword" name="confirmNewPassword" required>
                </div>
                <button type="submit" class="btn btn-primary">重置密码</button>
            </form>
        </div>
    </div>

    <!-- 添加前缀模态框 -->
    <div class="modal" id="addPrefixModal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>添加前缀</h3>
                <span class="close">&times;</span>
            </div>
            <form id="addPrefixForm">
                <div class="form-group">
                    <label for="newPrefix">前缀</label>
                    <input type="text" id="newPrefix" name="prefix" required placeholder="例如：站参">
                    <div class="form-hint">格式：支持中文、字母、数字和横杠，长度2-10个字符，例如：站参</div>
                </div>
                <div class="form-group">
                    <label for="prefixDescription">描述</label>
                    <input type="text" id="prefixDescription" name="prefixDescription" placeholder="例如：站参文件前缀">
                </div>
                <button type="submit" class="btn btn-primary">创建前缀</button>
            </form>
        </div>
    </div>

    <!-- 编辑前缀模态框 -->
    <div class="modal" id="editPrefixModal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>编辑前缀</h3>
                <span class="close">&times;</span>
            </div>
            <form id="editPrefixForm">
                <input type="hidden" id="editPrefixId" name="prefixId">
                <div class="form-group">
                    <label for="editPrefix">前缀</label>
                    <input type="text" id="editPrefix" name="prefix" required>
                    <div class="form-hint">格式：支持中文、字母、数字和横杠，长度2-10个字符</div>
                </div>
                <div class="form-group">
                    <label for="editPrefixDescription">描述</label>
                    <input type="text" id="editPrefixDescription" name="description" placeholder="例如：站参文件前缀">
                </div>
                <button type="submit" class="btn btn-primary">更新前缀</button>
            </form>
        </div>
    </div>

    <div class="notification" id="notification"></div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // 检查是否是超级管理员
            checkAdminAccess();

            // 加载用户列表
            loadUsers();

            // 加载前缀列表
            loadPrefixes();

            // 标签页切换功能
            const tabButtons = document.querySelectorAll('.tab-button');
            tabButtons.forEach(function(button) {
                button.addEventListener('click', function(e) {
                    e.preventDefault();
                    const tabId = this.dataset.tab;

                    // 移除所有标签按钮的active类
                    document.querySelectorAll('.tab-button').forEach(function(btn) {
                        btn.classList.remove('active');
                    });
                    // 隐藏所有标签页内容
                    document.querySelectorAll('.tab-pane').forEach(function(pane) {
                        pane.classList.remove('active');
                    });

                    // 激活当前标签按钮
                    this.classList.add('active');
                    // 显示当前标签页内容
                    const tabPane = document.getElementById(tabId);
                    if (tabPane) {
                        tabPane.classList.add('active');
                    }

                    // 根据标签页ID执行相应操作
                    if (tabId === 'users') {
                        loadUsers();
                    } else if (tabId === 'prefixes') {
                        loadPrefixes();
                    }
                });
            });

            // 添加用户按钮
            const addUserBtn = document.getElementById('addUserBtn');
            if (addUserBtn) {
                addUserBtn.addEventListener('click', function() {
                    document.getElementById('addUserModal').style.display = 'block';
                });
            }

            // 关闭模态框
            const closeButtons = document.querySelectorAll('.close');
            closeButtons.forEach(function(button) {
                button.addEventListener('click', function() {
                    const modal = this.closest('.modal');
                    if (modal) {
                        modal.style.display = 'none';
                    }
                });
            });

            // 点击模态框外部关闭
            window.addEventListener('click', function(event) {
                if (event.target.classList.contains('modal')) {
                    event.target.style.display = 'none';
                }
            });

            // 添加用户表单提交
            const addUserForm = document.getElementById('addUserForm');
            if (addUserForm) {
                addUserForm.addEventListener('submit', function(e) {
                    e.preventDefault();

                    const username = document.getElementById('username').value.trim();
                    const password = document.getElementById('password').value;
                    const confirmPassword = document.getElementById('confirmPassword').value;
                    const prefix = document.getElementById('prefix').value.trim();
                    const role = document.getElementById('role').value;

                    // 验证输入
                    if (!username || !password || !confirmPassword || !prefix) {
                        showNotification('请填写所有字段', true);
                        return;
                    }

                    if (password !== confirmPassword) {
                        showNotification('两次输入的密码不一致', true);
                        return;
                    }

                    if (password.length < 6) {
                        showNotification('密码长度至少为6位', true);
                        return;
                    }

                    // 发送创建用户请求
                    fetch('api/admin.php/users', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json'
                        },
                        body: JSON.stringify({
                            username: username,
                            password: password,
                            prefix: prefix,
                            role: role
                        })
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            showNotification('用户创建成功');
                            document.getElementById('addUserModal').style.display = 'none';
                            addUserForm.reset();
                            loadUsers();
                        } else {
                            showNotification(data.error || '创建用户失败', true);
                        }
                    })
                    .catch(error => {
                        console.error('创建用户错误:', error);
                        showNotification('创建用户失败，请重试', true);
                    });
                });
            }

            // 重置密码按钮
            document.addEventListener('click', function(event) {
                if (event.target.classList.contains('reset-password-btn')) {
                    const userId = event.target.dataset.userId;
                    const username = event.target.dataset.username;

                    document.getElementById('resetUserId').value = userId;
                    document.getElementById('resetUsername').value = username;
                    document.getElementById('resetPasswordModal').style.display = 'block';
                }
            });

            // 重置密码表单提交
            const resetPasswordForm = document.getElementById('resetPasswordForm');
            if (resetPasswordForm) {
                resetPasswordForm.addEventListener('submit', function(e) {
                    e.preventDefault();

                    const userId = document.getElementById('resetUserId').value;
                    const newPassword = document.getElementById('newPassword').value;
                    const confirmNewPassword = document.getElementById('confirmNewPassword').value;

                    // 验证输入
                    if (!newPassword || !confirmNewPassword) {
                        showNotification('请填写所有字段', true);
                        return;
                    }

                    if (newPassword !== confirmNewPassword) {
                        showNotification('两次输入的密码不一致', true);
                        return;
                    }

                    if (newPassword.length < 6) {
                        showNotification('密码长度至少为6位', true);
                        return;
                    }

                    // 发送重置密码请求
                    fetch('api/admin.php/users/reset-password', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json'
                        },
                        body: JSON.stringify({
                            userId: userId,
                            newPassword: newPassword
                        })
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            showNotification('密码重置成功');
                            document.getElementById('resetPasswordModal').style.display = 'none';
                            resetPasswordForm.reset();
                        } else {
                            showNotification(data.error || '重置密码失败', true);
                        }
                    })
                    .catch(error => {
                        console.error('重置密码错误:', error);
                        showNotification('重置密码失败', true);
                    });
                });
            }

            // 删除用户按钮
            document.addEventListener('click', function(event) {
                if (event.target.classList.contains('delete-user-btn')) {
                    const userId = event.target.dataset.userId;
                    const username = event.target.dataset.username;

                    if (confirm(`确定要删除用户 ${username} 吗？`)) {
                        fetch('api/admin.php/users/delete', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json'
                            },
                            body: JSON.stringify({
                                userId: userId
                            })
                        })
                        .then(response => response.json())
                        .then(data => {
                            if (data.success) {
                                showNotification('用户删除成功');
                                loadUsers();
                            } else {
                                showNotification(data.error || '删除用户失败', true);
                            }
                        })
                        .catch(error => {
                            console.error('删除用户错误:', error);
                            showNotification('删除用户失败', true);
                        });
                    }
                }
            });

            // 添加前缀按钮
            const addPrefixBtn = document.getElementById('addPrefixBtn');
            if (addPrefixBtn) {
                addPrefixBtn.addEventListener('click', function() {
                    document.getElementById('addPrefixModal').style.display = 'block';
                });
            }

            // 添加前缀表单提交
            const addPrefixForm = document.getElementById('addPrefixForm');
            if (addPrefixForm) {
                addPrefixForm.addEventListener('submit', function(e) {
                    e.preventDefault();

                    // 获取表单元素
                    const prefixInput = document.querySelector('#addPrefixForm #newPrefix');
                    const descriptionInput = document.querySelector('#addPrefixForm #prefixDescription');
                    
                    // 获取输入值
                    const prefix = prefixInput ? prefixInput.value.trim() : '';
                    const description = descriptionInput ? descriptionInput.value.trim() : '';

                    // 验证输入
                    if (!prefix) {
                        showNotification('请输入前缀', true);
                        return;
                    }

                    // 发送创建前缀请求
                    fetch('api/prefixes.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json'
                        },
                        body: JSON.stringify({
                            prefix: prefix,
                            description: description
                        })
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            showNotification('前缀创建成功');
                            document.getElementById('addPrefixModal').style.display = 'none';
                            addPrefixForm.reset();
                            loadPrefixes();
                        } else {
                            showNotification(data.error || '创建前缀失败', true);
                        }
                    })
                    .catch(error => {
                        console.error('创建前缀错误:', error);
                        showNotification('创建前缀失败，请重试', true);
                    });
                });
            }

            // 编辑前缀按钮
            document.addEventListener('click', function(event) {
                if (event.target.classList.contains('edit-prefix-btn')) {
                    const prefixId = event.target.dataset.prefixId;
                    const prefix = event.target.dataset.prefix;
                    const description = event.target.dataset.description;

                    document.getElementById('editPrefixId').value = prefixId;
                    document.getElementById('editPrefix').value = prefix;
                    document.getElementById('editPrefixDescription').value = description;
                    document.getElementById('editPrefixModal').style.display = 'block';
                }
            });

            // 编辑前缀表单提交
            const editPrefixForm = document.getElementById('editPrefixForm');
            if (editPrefixForm) {
                editPrefixForm.addEventListener('submit', function(e) {
                    e.preventDefault();

                    const prefixId = document.getElementById('editPrefixId').value;
                    const prefix = document.getElementById('editPrefix').value.trim();
                    const description = document.getElementById('editPrefixDescription').value.trim();

                    // 验证输入
                    if (!prefix) {
                        showNotification('请输入前缀', true);
                        return;
                    }

                    // 发送更新前缀请求
                    fetch('api/prefixes.php/' + prefixId, {
                        method: 'PUT',
                        headers: {
                            'Content-Type': 'application/json'
                        },
                        body: JSON.stringify({
                            prefix: prefix,
                            description: description
                        })
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            showNotification('前缀更新成功');
                            document.getElementById('editPrefixModal').style.display = 'none';
                            editPrefixForm.reset();
                            loadPrefixes();
                        } else {
                            showNotification(data.error || '更新前缀失败', true);
                        }
                    })
                    .catch(error => {
                        console.error('更新前缀错误:', error);
                        showNotification('更新前缀失败，请重试', true);
                    });
                });
            }

            // 删除前缀按钮
            document.addEventListener('click', function(event) {
                if (event.target.classList.contains('delete-prefix-btn')) {
                    const prefixId = event.target.dataset.prefixId;
                    const prefix = event.target.dataset.prefix;

                    if (confirm(`确定要删除前缀 ${prefix} 吗？`)) {
                        fetch('api/prefixes.php/' + prefixId, {
                            method: 'DELETE',
                            headers: {
                                'Content-Type': 'application/json'
                            }
                        })
                        .then(response => response.json())
                        .then(data => {
                            if (data.success) {
                                showNotification('前缀删除成功');
                                loadPrefixes();
                            } else {
                                showNotification(data.error || '删除前缀失败', true);
                            }
                        })
                        .catch(error => {
                            console.error('删除前缀错误:', error);
                            showNotification('删除前缀失败，请重试', true);
                        });
                    }
                }
            });

            // 检查管理员权限
            function checkAdminAccess() {
                fetch('api/admin.php/check', {
                    method: 'GET'
                })
                .then(response => response.json())
                .then(data => {
                    if (!data.success || (data.role !== 'admin' && data.role !== 'superadmin')) {
                        showNotification('您没有管理员权限', true);
                        setTimeout(function() {
                            window.location.href = 'index.php';
                        }, 2000);
                    }
                })
                .catch(() => {
                    showNotification('您没有管理员权限', true);
                    setTimeout(function() {
                        window.location.href = 'login.php';
                    }, 2000);
                });
            }

            // 加载用户列表
            function loadUsers() {
                document.getElementById('loading').style.display = 'block';
                document.getElementById('usersTableBody').innerHTML = '';

                fetch('api/admin.php/users', {
                    method: 'GET'
                })
                .then(response => response.json())
                .then(data => {
                    document.getElementById('loading').style.display = 'none';
                    if (data.users && data.users.length > 0) {
                        data.users.forEach(user => {
                            let roleBadge;
                            if (user.role === 'superadmin') {
                                roleBadge = '<span class="badge badge-superadmin">超级管理员</span>';
                            } else if (user.role === 'admin') {
                                roleBadge = '<span class="badge badge-admin">管理员</span>';
                            } else {
                                roleBadge = '<span class="badge badge-user">普通用户</span>';
                            }

                            const actions = user.role === 'superadmin'
                                ? '<span class="text-muted">超级管理员</span>'
                                : `<button class="btn btn-sm btn-danger reset-password-btn" data-user-id="${user.id}" data-username="${user.username}">重置密码</button>
                                   <button class="btn btn-sm btn-danger delete-user-btn" data-user-id="${user.id}" data-username="${user.username}">删除</button>`;

                            const row = document.createElement('tr');
                            row.innerHTML = `
                                <td>${user.id}</td>
                                <td>${user.username}</td>
                                <td>${user.prefix}</td>
                                <td>${roleBadge}</td>
                                <td>${user.createdAt}</td>
                                <td>${actions}</td>
                            `;
                            document.getElementById('usersTableBody').appendChild(row);
                        });
                    } else {
                        const row = document.createElement('tr');
                        row.innerHTML = '<td colspan="6" class="table-empty">暂无用户</td>';
                        document.getElementById('usersTableBody').appendChild(row);
                    }
                })
                .catch(() => {
                    document.getElementById('loading').style.display = 'none';
                    showNotification('加载用户列表失败', true);
                });
            }

            // 加载前缀列表
            function loadPrefixes() {
                document.getElementById('prefixLoading').style.display = 'block';
                document.getElementById('prefixesTableBody').innerHTML = '';

                fetch('api/prefixes.php', {
                    method: 'GET'
                })
                .then(response => response.json())
                .then(data => {
                    document.getElementById('prefixLoading').style.display = 'none';
                    if (data.prefixes && data.prefixes.length > 0) {
                        data.prefixes.forEach(prefix => {
                            const row = document.createElement('tr');
                            row.innerHTML = `
                                <td>${prefix.id}</td>
                                <td>${prefix.prefix}</td>
                                <td>${prefix.description || '-'}</td>
                                <td>${prefix.createdAt}</td>
                                <td>
                                    <button class="btn btn-sm btn-secondary edit-prefix-btn" data-prefix-id="${prefix.id}" data-prefix="${prefix.prefix}" data-description="${prefix.description || ''}">编辑</button>
                                    <button class="btn btn-sm btn-danger delete-prefix-btn" data-prefix-id="${prefix.id}" data-prefix="${prefix.prefix}">删除</button>
                                </td>
                            `;
                            document.getElementById('prefixesTableBody').appendChild(row);
                        });
                    } else {
                        const row = document.createElement('tr');
                        row.innerHTML = '<td colspan="5" class="table-empty">暂无前缀</td>';
                        document.getElementById('prefixesTableBody').appendChild(row);
                    }
                })
                .catch(() => {
                    document.getElementById('prefixLoading').style.display = 'none';
                    showNotification('加载前缀列表失败', true);
                });
            }

            // 显示通知
            function showNotification(message, isError = false) {
                const notification = document.getElementById('notification');
                notification.classList.remove('error');
                if (isError) {
                    notification.classList.add('error');
                }
                notification.textContent = message;
                notification.classList.add('show');

                setTimeout(function() {
                    notification.classList.remove('show');
                }, 3000);
            }

            // 显示备份消息
            function showBackupMessage(message, isError = false) {
                const msg = document.getElementById('backupMessage');
                msg.style.display = 'block';
                msg.className = 'message ' + (isError ? 'error' : 'success');
                msg.textContent = message;
                setTimeout(() => {
                    msg.style.display = 'none';
                }, 5000);
            }

            // 导出数据库
            document.getElementById('exportBtn').addEventListener('click', function() {
                showNotification('正在导出数据库...');
                window.location.href = 'api/backup.php?action=export';
            });

            // 导入数据库
            document.getElementById('importForm').addEventListener('submit', function(e) {
                e.preventDefault();
                const fileInput = document.getElementById('backupFile');
                if (!fileInput.files[0]) {
                    showBackupMessage('请选择要导入的备份文件', true);
                    return;
                }
                if (!confirm('确定要导入数据库吗？这将覆盖当前数据库的所有数据！')) {
                    return;
                }
                const formData = new FormData();
                formData.append('backup_file', fileInput.files[0]);
                showBackupMessage('正在导入数据库...');
                fetch('api/backup.php?action=import', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        showBackupMessage('数据库导入成功');
                        fileInput.value = '';
                    } else {
                        showBackupMessage(data.message || '导入失败', true);
                    }
                })
                .catch(error => {
                    showBackupMessage('导入失败: ' + error.message, true);
                });
            });
        });
    </script>
</body>
</html>
