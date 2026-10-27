<?php
/**
 * CSRF 防护。
 * 会话里保存一个随机 token，页面通过 <meta name="csrf-token"> 下发，
 * 前端 js/csrf.js 会自动给所有非 GET 的同源请求加上 X-CSRF-Token 头。
 */

if (!function_exists('csrf_token')) {
    /** 取当前会话的 token，没有就生成一个 */
    function csrf_token() {
        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(16));
        }
        return $_SESSION['csrf_token'];
    }
}

if (!function_exists('csrf_verify')) {
    /**
     * 校验写操作的 token。GET/HEAD/OPTIONS 直接放行。
     * 校验失败返回 403 并终止请求。
     */
    function csrf_verify($method = null) {
        if ($method === null) {
            $method = isset($_SERVER['REQUEST_METHOD']) ? $_SERVER['REQUEST_METHOD'] : 'GET';
        }

        if (in_array($method, array('GET', 'HEAD', 'OPTIONS'), true)) {
            return;
        }

        $sent = isset($_SERVER['HTTP_X_CSRF_TOKEN']) ? $_SERVER['HTTP_X_CSRF_TOKEN'] : '';
        $expected = isset($_SESSION['csrf_token']) ? $_SESSION['csrf_token'] : '';

        if ($sent === '' || $expected === '' || !hash_equals($expected, $sent)) {
            http_response_code(403);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(array(
                'success' => false,
                'message' => 'CSRF 校验失败，请刷新页面后重试'
            ), JSON_UNESCAPED_UNICODE);
            exit;
        }
    }
}
