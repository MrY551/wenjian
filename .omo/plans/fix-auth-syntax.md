# 紧急修复：api/auth.php 语法错误

## 问题描述

登录时返回 `SyntaxError: Unexpected token '<'`，原因是 `api/auth.php` 有语法错误：
- 重复的 catch 代码块导致 PHP 解析失败
- 17处调试日志需要清理

## 修复步骤

### 1. 删除重复的 catch 块（行136-141）

删除以下代码：
```php
}
        error_log("数据库错误: " . $e->getMessage());
        http_response_code(500);
        echo json_encode(['error' => '数据库错误: ' . $e->getMessage()]);
    }
}
```

### 2. 删除重复的 catch 块（行232-236）

删除以下代码：
```php
}
        http_response_code(500);
        echo json_encode(['error' => '数据库错误: ' . $e->getMessage()]);
    }
}
```

### 3. 移除所有 error_log 调用

移除以下行的 error_log：
- 行50, 51, 75, 83, 85, 88, 96, 104, 108, 114, 115, 127, 132, 151, 152, 155, 165

## 验收标准

- [x] 登录功能正常工作
- [x] PHP 无语法错误
- [x] 无调试日志输出
