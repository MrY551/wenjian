/**
 * CSRF：给所有非 GET 的同源请求自动附加 X-CSRF-Token。
 * token 由页面 <meta name="csrf-token"> 下发（服务端 api/csrf.php 生成）。
 * 这样各处原有的 fetch 调用不用改。
 */
(function () {
    var meta = document.querySelector('meta[name="csrf-token"]');
    var token = meta ? meta.getAttribute('content') : '';
    if (!token || typeof window.fetch !== 'function') {
        return;
    }

    var originalFetch = window.fetch;

    window.fetch = function (input, init) {
        init = init || {};

        var method = init.method ||
            (typeof input === 'object' && input && input.method) ||
            'GET';
        method = String(method).toUpperCase();

        if (method !== 'GET' && method !== 'HEAD' && method !== 'OPTIONS') {
            var headers;
            if (typeof Headers !== 'undefined') {
                headers = new Headers(init.headers || {});
                if (!headers.has('X-CSRF-Token')) {
                    headers.set('X-CSRF-Token', token);
                }
            } else {
                headers = init.headers || {};
                if (!headers['X-CSRF-Token']) {
                    headers['X-CSRF-Token'] = token;
                }
            }
            init.headers = headers;
        }

        return originalFetch.call(this, input, init);
    };
})();
