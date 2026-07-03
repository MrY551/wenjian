// 文件编号登记系统 - JavaScript
// 超时辅助函数
async function fetchWithTimeout(url, options = {}, timeout = 10000) {
    const controller = new AbortController();
    const id = setTimeout(() => controller.abort(), timeout);
    try {
        const response = await fetch(url, { ...options, signal: controller.signal });
        clearTimeout(id);
        return response;
    } catch (error) {
        clearTimeout(id);
        if (error.name === 'AbortError') {
            throw new Error('请求超时，请稍后重试');
        }
        throw error;
    }
}

// DOM元素缓存
const domCache = {};

// 编辑状态：记录正在编辑的文件编号，为空表示新建
let editingFileNumber = '';

// 初始化DOM缓存
function initDomCache() {
    // 标签页
    domCache.tabButtons = document.querySelectorAll('.tab-button');
    domCache.tabPanes = document.querySelectorAll('.tab-pane');
    
    // 文件表单
    domCache.fileForm = document.getElementById('fileForm');
    domCache.filePrefix = document.getElementById('filePrefix');
    domCache.fileNumber = document.getElementById('fileNumber');
    
    // 搜索
    domCache.searchInput = document.getElementById('searchInput');
    domCache.searchButton = document.getElementById('searchButton');
    domCache.exportButton = document.getElementById('exportButton');
    domCache.approvalFilter = document.getElementById('approvalFilter');
    domCache.prefixFilter = document.getElementById('prefixFilter');
    domCache.yearFilter = document.getElementById('yearFilter');
    domCache.startDate = document.getElementById('startDate');
    domCache.endDate = document.getElementById('endDate');
    
    // 表格
    domCache.filesTable = document.getElementById('filesTable').querySelector('tbody');
    
    // 退出登录
    domCache.logoutBtn = document.getElementById('logoutBtn');
    
    // 批示相关
    domCache.saveInstructionButton = document.getElementById('saveInstructionButton');
    domCache.clearInstructionForm = document.getElementById('clearInstructionForm');
    domCache.instructionList = document.querySelector('.instruction-list');
    
    // 模态框
    domCache.editInstructionModal = document.getElementById('editInstructionModal');
    domCache.fileSelectionModal = document.getElementById('fileSelectionModal');
    domCache.selectFileButton = document.getElementById('selectFileButton');
    domCache.modalSearchInput = document.getElementById('modalSearchInput');
    domCache.modalSearchButton = document.getElementById('modalSearchButton');
    domCache.fileList = document.querySelector('.file-list');
}

// 显示通知消息
function showNotification(message, isError = false) {
    const notification = document.createElement('div');
    notification.className = 'notification ' + (isError ? 'error' : 'success');
    notification.textContent = message;
    document.body.appendChild(notification);
    
    setTimeout(() => {
        notification.classList.add('show');
    }, 10);
    
    setTimeout(() => {
        notification.classList.remove('show');
        setTimeout(() => notification.remove(), 300);
    }, 3000);
}

// 标签页切换
function initTabs() {
    domCache.tabButtons.forEach(button => {
        button.addEventListener('click', function() {
            const tabId = this.getAttribute('data-tab');
            if (!tabId) return;
            
            domCache.tabButtons.forEach(btn => btn.classList.remove('active'));
            domCache.tabPanes.forEach(pane => pane.classList.remove('active'));
            
            this.classList.add('active');
            document.getElementById(tabId).classList.add('active');
            
            if (tabId === 'search') {
                loadFiles();
            } else if (tabId === 'leader-instructions') {
                loadInstructions();
            }
        });
    });
}

// 加载文件前缀
async function loadPrefixes() {
    try {
        const response = await fetchWithTimeout('api/prefixes.php?action=list');
        const data = await response.json();
        
        if (data.success) {
            domCache.filePrefix.innerHTML = '<option value="">请选择前缀</option>';
            data.prefixes.forEach(item => {
                const option = document.createElement('option');
                option.value = item.prefix;
                option.textContent = item.prefix;
                domCache.filePrefix.appendChild(option);
            });
            
            domCache.prefixFilter.innerHTML = '<option value="all">全部前缀</option>';
            data.prefixes.forEach(item => {
                const option = document.createElement('option');
                option.value = item.prefix;
                option.textContent = item.prefix;
                domCache.prefixFilter.appendChild(option);
            });
        }
    } catch (error) {
        showNotification('加载前缀失败: ' + error.message, true);
    }
}

// 同步前缀（从 files 表提取前缀到 prefixes 表）
async function syncPrefixes() {
    try {
        showNotification('正在同步前缀...');
        const response = await fetchWithTimeout('api/prefixes.php?action=sync');
        const data = await response.json();

        if (data.success) {
            showNotification(data.message);
            // 重新加载前缀列表
            loadPrefixes();
        } else {
            showNotification(data.error || '同步失败', true);
        }
    } catch (error) {
        showNotification('同步前缀失败: ' + error.message, true);
    }
}

// 生成文件编号
async function generateFileNumber() {
    const prefix = domCache.filePrefix.value;
    if (!prefix) {
        domCache.fileNumber.value = '';
        return;
    }
    
    try {
        const response = await fetchWithTimeout('api/files.php?action=generateNumber&prefix=' + encodeURIComponent(prefix));
        const data = await response.json();
        
        if (data.success) {
            domCache.fileNumber.value = data.fileNumber;
        } else {
            showNotification(data.message || '生成编号失败', true);
        }
    } catch (error) {
        showNotification('生成编号请求失败: ' + error.message, true);
    }
}

// 文件前缀变更时生成新编号
function initFilePrefixListener() {
    domCache.filePrefix.addEventListener('change', generateFileNumber);
}

// 提交文件表单
async function submitFileForm(formData) {
    try {
        // 判断是创建还是更新
        const isEdit = editingFileNumber !== '';
        const url = isEdit 
            ? 'api/files.php?action=update&fileNumber=' + encodeURIComponent(editingFileNumber)
            : 'api/files.php?action=create';
        
        const response = await fetchWithTimeout(url, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(formData)
        });
        
        const data = await response.json();
        
        if (data.success) {
            showNotification(isEdit ? '文件更新成功' : '文件登记成功');
            domCache.fileForm.reset();
            domCache.filePrefix.value = '';
            domCache.fileNumber.value = '';
            editingFileNumber = ''; // 重置编辑状态
            loadPrefixes();
        } else {
            showNotification(data.message || (isEdit ? '更新失败' : '登记失败'), true);
        }
        
        return data;
    } catch (error) {
        showNotification('提交失败: ' + error.message, true);
        throw error;
    }
}

// 初始化文件表单
function initFileForm() {
    domCache.fileForm.addEventListener('submit', async function(e) {
        e.preventDefault();
        
        const formData = {
            fileNumber: domCache.fileNumber.value,
            fileName: document.getElementById('fileName').value,
            submitter: document.getElementById('submitter').value,
            issueDate: document.getElementById('issueDate').value,
            fileContent: document.getElementById('fileContent').value,
            leaderInstruction: document.getElementById('leaderInstruction').value,
            approver01: document.getElementById('approver01').value,
            approver02: document.getElementById('approver02').value,
            approver03: document.getElementById('approver03').value,
            approver04: document.getElementById('approver04').value,
            approver05: document.getElementById('approver05').value,
            approver06: document.getElementById('approver06').value,
            approver07: document.getElementById('approver07').value
        };
        
        await submitFileForm(formData);
    });
}

// 加载文件列表
async function loadFiles() {
    const search = domCache.searchInput.value;
    const approval = domCache.approvalFilter.value;
    const prefix = domCache.prefixFilter.value;
    const year = domCache.yearFilter.value;
    const startDate = domCache.startDate.value;
    const endDate = domCache.endDate.value;
    
    const params = new URLSearchParams({
        action: 'list',
        search: search,
        approval: approval,
        prefix: prefix,
        year: year,
        startDate: startDate,
        endDate: endDate
    });
    
    try {
        const response = await fetchWithTimeout('api/files.php?' + params.toString());
        const result = await response.json();
        
        // 兼容分页格式
        const files = result.data || result.files || [];
        
        renderFilesTable(files);
    } catch (error) {
        showNotification('加载文件请求失败: ' + error.message, true);
    }
}

// 渲染文件表格
function renderFilesTable(files) {
    domCache.filesTable.innerHTML = '';
    
    if (files.length === 0) {
        domCache.filesTable.innerHTML = '<tr><td colspan="6" style="text-align: center;">暂无数据</td></tr>';
        return;
    }
    
    files.forEach(file => {
        const tr = document.createElement('tr');
        
        // 签批状态
        let approvalStatus = [];
        if (file.approver01 === '已签批') approvalStatus.push('01');
        if (file.approver02 === '已签批') approvalStatus.push('02');
        if (file.approver03 === '已签批') approvalStatus.push('03');
        if (file.approver04 === '已签批') approvalStatus.push('04');
        if (file.approver05 === '已签批') approvalStatus.push('05');
        if (file.approver06 === '已签批') approvalStatus.push('06');
        if (file.approver07 === '已签批') approvalStatus.push('07');
        
        const approvalText = approvalStatus.length > 0 ? approvalStatus.join(', ') : '未签批';
        
        tr.innerHTML = `
            <td>${escapeHtml(file.fileNumber)}</td>
            <td>${escapeHtml(file.fileName)}</td>
            <td>${escapeHtml(file.issueDate || '')}</td>
            <td>${file.leaderInstruction ? '<button class="btn-link" onclick="editInstruction(\'' + escapeHtml(file.fileNumber) + '\')">查看</button>' : '-'}</td>
            <td>${escapeHtml(approvalText)}</td>
            <td>
                <button class="btn-link" onclick="editFile('${escapeHtml(file.fileNumber)}')">编辑</button>
                <button class="btn-link" onclick="deleteFile('${escapeHtml(file.fileNumber)}')">删除</button>
            </td>
        `;
        
        domCache.filesTable.appendChild(tr);
    });
}

// HTML转义
function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

// 编辑文件
async function editFile(fileNumber) {
    try {
        const response = await fetchWithTimeout('api/files.php?action=get&fileNumber=' + encodeURIComponent(fileNumber));
        const data = await response.json();
        
        if (data.success) {
            const file = data.file;
            // 设置编辑状态
            editingFileNumber = file.fileNumber;
            // 提取前缀：年份前面的部分（如 "站参2024【001】" → "站参"）
            const prefixMatch = file.fileNumber.match(/^(.+?)\d{4}【/);
            if (prefixMatch) {
                document.getElementById('filePrefix').value = prefixMatch[1];
            }
            document.getElementById('fileName').value = file.fileName;
            document.getElementById('submitter').value = file.submitter;
            document.getElementById('issueDate').value = file.issueDate;
            document.getElementById('fileContent').value = file.fileContent;
            document.getElementById('leaderInstruction').value = file.leaderInstruction || '';
            document.getElementById('approver01').value = file.approver01;
            document.getElementById('approver02').value = file.approver02;
            document.getElementById('approver03').value = file.approver03;
            document.getElementById('approver04').value = file.approver04;
            document.getElementById('approver05').value = file.approver05;
            document.getElementById('approver06').value = file.approver06;
            document.getElementById('approver07').value = file.approver07;
            
            domCache.fileNumber.value = file.fileNumber;
            
            // 切换到登记标签
            document.querySelector('[data-tab="register"]').click();
        } else {
            showNotification(data.message || '获取文件失败', true);
        }
    } catch (error) {
        showNotification('请求失败: ' + error.message, true);
    }
}

// 删除文件
async function deleteFile(fileNumber) {
    if (!confirm('确定要删除文件 ' + fileNumber + ' 吗？')) {
        return;
    }
    
    try {
        const response = await fetchWithTimeout('api/files.php?action=delete', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ fileNumber: fileNumber })
        });
        
        const data = await response.json();
        
        if (data.success) {
            showNotification('删除成功');
            loadFiles();
        } else {
            showNotification(data.message || '删除失败', true);
        }
    } catch (error) {
        showNotification('删除请求失败: ' + error.message, true);
    }
}

// 编辑批示
async function editInstruction(fileNumber) {
    document.getElementById('editInstructionFileNumber').value = fileNumber;
    
    try {
        const response = await fetchWithTimeout('api/instructions.php?action=get&fileNumber=' + encodeURIComponent(fileNumber));
        const data = await response.json();
        
        if (data.success) {
            document.getElementById('editInstructionContent').value = data.instruction ? data.instruction.content : '';
        } else {
            document.getElementById('editInstructionContent').value = '';
        }
    } catch (error) {
        document.getElementById('editInstructionContent').value = '';
    }
    
    domCache.editInstructionModal.style.display = 'block';
}

// 保存编辑后的批示
async function saveEditInstruction() {
    const fileNumber = document.getElementById('editInstructionFileNumber').value;
    const content = document.getElementById('editInstructionContent').value;
    
    try {
        const response = await fetchWithTimeout('api/instructions.php?action=update', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ fileNumber: fileNumber, content: content })
        });
        
        const data = await response.json();
        
        if (data.success) {
            showNotification('保存成功');
            domCache.editInstructionModal.style.display = 'none';
            loadFiles();
        } else {
            showNotification(data.message || '保存失败', true);
        }
    } catch (error) {
        showNotification('保存请求失败: ' + error.message, true);
    }
}

// 初始化搜索功能
function initSearch() {
    domCache.searchButton.addEventListener('click', loadFiles);
    domCache.searchInput.addEventListener('keypress', function(e) {
        if (e.key === 'Enter') loadFiles();
    });
    
    domCache.approvalFilter.addEventListener('change', loadFiles);
    domCache.prefixFilter.addEventListener('change', loadFiles);
    domCache.yearFilter.addEventListener('change', loadFiles);
    domCache.startDate.addEventListener('change', loadFiles);
    domCache.endDate.addEventListener('change', loadFiles);
    
    // 导出功能
    domCache.exportButton.addEventListener('click', async function() {
        const search = domCache.searchInput.value;
        const approval = domCache.approvalFilter.value;
        const prefix = domCache.prefixFilter.value;
        const year = domCache.yearFilter.value;
        const startDate = domCache.startDate.value;
        const endDate = domCache.endDate.value;
        
        const params = new URLSearchParams({
            search: search,
            approval: approval,
            prefix: prefix,
            year: year,
            startDate: startDate,
            endDate: endDate
        });
        
        window.location.href = 'api/export.php?' + params.toString();
    });
}

// 加载批示历史
async function loadInstructions() {
    try {
        const response = await fetchWithTimeout('api/instructions.php?action=list');
        const data = await response.json();
        
        if (data.success) {
            renderInstructions(data.instructions);
        } else {
            showNotification('加载批示失败: ' + data.message, true);
        }
    } catch (error) {
        showNotification('加载批示请求失败: ' + error.message, true);
    }
}

// 渲染批示列表
function renderInstructions(instructions) {
    domCache.instructionList.innerHTML = '';
    
    if (instructions.length === 0) {
        domCache.instructionList.innerHTML = '<p style="text-align: center; color: #999;">暂无批示记录</p>';
        return;
    }
    
    instructions.forEach(inst => {
        const div = document.createElement('div');
        div.className = 'instruction-item';
        div.innerHTML = `
            <div class="instruction-header">
                <strong>${escapeHtml(inst.fileNumber)}</strong> - ${escapeHtml(inst.leaderName)}
                <span class="instruction-date">${escapeHtml(inst.date)}</span>
            </div>
            <div class="instruction-content">${escapeHtml(inst.content)}</div>
        `;
        domCache.instructionList.appendChild(div);
    });
}

// 保存批示
async function saveInstruction() {
    const fileNumber = document.getElementById('instructionFileNumber').value;
    const leaderName = document.getElementById('leaderName').value;
    const content = document.getElementById('instructionContent').value;
    const date = document.getElementById('instructionDate').value;
    
    if (!fileNumber || !leaderName || !content || !date) {
        showNotification('请填写完整信息', true);
        return;
    }
    
    try {
        const response = await fetchWithTimeout('api/instructions.php?action=create', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                fileNumber: fileNumber,
                leaderName: leaderName,
                content: content,
                date: date
            })
        });
        
        const data = await response.json();
        
        if (data.success) {
            showNotification('保存成功');
            document.getElementById('instructionFileNumber').value = '';
            document.getElementById('leaderName').value = '';
            document.getElementById('instructionContent').value = '';
            document.getElementById('instructionDate').value = '';
            loadInstructions();
            loadFiles();
        } else {
            showNotification(data.message || '保存失败', true);
        }
    } catch (error) {
        showNotification('保存请求失败: ' + error.message, true);
    }
}

// 初始化批示功能
function initInstructions() {
    domCache.saveInstructionButton.addEventListener('click', saveInstruction);
    domCache.clearInstructionForm.addEventListener('click', function() {
        document.getElementById('instructionFileNumber').value = '';
        document.getElementById('leaderName').value = '';
        document.getElementById('instructionContent').value = '';
        document.getElementById('instructionDate').value = '';
    });
    
    // 打开文件选择模态框
    domCache.selectFileButton.addEventListener('click', function() {
        domCache.fileSelectionModal.style.display = 'block';
        loadModalFiles();
    });
    
    // 模态框搜索
    domCache.modalSearchButton.addEventListener('click', loadModalFiles);
    domCache.modalSearchInput.addEventListener('keypress', function(e) {
        if (e.key === 'Enter') loadModalFiles();
    });
}

// 加载模态框文件列表
async function loadModalFiles() {
    const search = domCache.modalSearchInput.value;
    
    try {
        const response = await fetchWithTimeout('api/files.php?action=list&search=' + encodeURIComponent(search));
        const result = await response.json();
        
        // 兼容分页格式
        const files = result.data || result.files || [];
        renderModalFileList(files);
    } catch (error) {
        showNotification('加载文件请求失败: ' + error.message, true);
    }
}

// 渲染模态框文件列表
function renderModalFileList(files) {
    domCache.fileList.innerHTML = '';
    
    if (files.length === 0) {
        domCache.fileList.innerHTML = '<p style="text-align: center; color: #999;">暂无文件</p>';
        return;
    }
    
    files.forEach(file => {
        const div = document.createElement('div');
        div.className = 'file-item';
        div.innerHTML = `
            <strong>${escapeHtml(file.fileNumber)}</strong> - ${escapeHtml(file.fileName)}
        `;
        div.addEventListener('click', function() {
            document.getElementById('instructionFileNumber').value = file.fileNumber;
            domCache.fileSelectionModal.style.display = 'none';
        });
        domCache.fileList.appendChild(div);
    });
}

// 初始化模态框
function initModals() {
    // 关闭按钮
    document.querySelectorAll('.close').forEach(btn => {
        btn.addEventListener('click', function() {
            this.closest('.modal').style.display = 'none';
        });
    });
    
    // 点击外部关闭
    window.addEventListener('click', function(e) {
        if (e.target.classList.contains('modal')) {
            e.target.style.display = 'none';
        }
    });
    
    // 保存编辑批示
    document.getElementById('saveEditInstructionButton').addEventListener('click', saveEditInstruction);
    document.getElementById('cancelEditInstructionButton').addEventListener('click', function() {
        domCache.editInstructionModal.style.display = 'none';
    });
}

// 初始化年份筛选
function initYearFilter() {
    const currentYear = new Date().getFullYear();
    let options = '<option value="all">全部年份</option>';
    for (let year = currentYear; year >= currentYear - 10; year--) {
        options += '<option value="' + year + '">' + year + '</option>';
    }
    domCache.yearFilter.innerHTML = options;
}

// 退出登录
async function logout() {
    try {
        const response = await fetchWithTimeout('api/auth.php/logout', {
            method: 'POST'
        });
        const data = await response.json();
        
        if (data.success) {
            window.location.href = 'login.php';
        } else {
            showNotification('退出失败', true);
        }
    } catch (error) {
        showNotification('退出请求失败: ' + error.message, true);
    }
}

// 初始化退出按钮
function initLogout() {
    domCache.logoutBtn.addEventListener('click', logout);
}

// DOM加载完成
document.addEventListener('DOMContentLoaded', function() {
    initDomCache();
    initTabs();
    loadPrefixes();
    initFilePrefixListener();
    initFileForm();
    initSearch();
    initYearFilter();
    initInstructions();
    initModals();
    initLogout();
});
