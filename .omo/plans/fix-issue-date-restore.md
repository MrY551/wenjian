# 修复：编辑文件时保留下发时间

## 问题描述

在编辑文件时，下发时间 (issueDate) 字段没有保留原值，需要用户重新选择。而前缀 (filePrefix) 字段已正确保留原值。

**问题位置**: `script-php.js` 第1285-1463行的 `editFile` 函数

## 修复方案

在 `script-php.js` 的 `editFile` 函数中，设置前缀后添加恢复下发时间的代码。

**修改位置**: 第1332行后（在 `filePrefix.value = prefix;` 之后）

**添加代码**:
```javascript
// 恢复下发时间
const issueDate = document.getElementById('issueDate');
if (issueDate) {
    issueDate.value = file.issueDate || '';
}
```

## 修改后的代码结构

```javascript
if (filePrefix) {
    filePrefix.value = prefix;
}
// 恢复下发时间 ← 新增
const issueDate = document.getElementById('issueDate');  ← 新增
if (issueDate) {  ← 新增
    issueDate.value = file.issueDate || '';  ← 新增
}  ← 新增
if (fileNumber) {
    fileNumber.value = file.fileNumber;
}
// ... 后续代码不变
```

## 验收标准

- [x] 编辑文件时，下发时间自动填充为原值
- [x] 用户可以直接修改下发时间
- [x] 其他字段（文件名、文件内容等）功能正常
