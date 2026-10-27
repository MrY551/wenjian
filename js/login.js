/**
 * 登录页表单逻辑
 * 接口：POST api/auth.php/login  { username, password }
 *  成功 -> { success: true, user: {...} }
 *  失败 -> HTTP 4xx/5xx，{ error: "..." }
 */
(function () {
  'use strict';

  var LOGIN_ENDPOINT = 'api/auth.php/login';
  var HOME_URL = 'index.php';

  var form = document.getElementById('login-form');
  if (!form) {
    return;
  }

  var accountInput = document.getElementById('login-account');
  var passwordInput = document.getElementById('login-password');
  var togglePasswordBtn = document.getElementById('toggle-password');
  var errorEl = document.getElementById('login-error');
  var submitBtn = document.getElementById('login-submit');
  var viewIcon = togglePasswordBtn.querySelector('.login-icon--view');
  var hideIcon = togglePasswordBtn.querySelector('.login-icon--hide');
  var charactersRoot = document.getElementById('animated-characters-root');

  var loading = false;
  var showPassword = false;
  var isTyping = false;
  var characters = null;

  var SUBMIT_IDLE_HTML =
    '<span class="login-btn__text">登录</span>' +
    '<span class="login-btn__hover">' +
    '<span>登录</span>' +
    '<svg class="login-btn__arrow" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">' +
    '<path d="M5 12h14M12 5l7 7-7 7"/>' +
    '</svg></span>';

  function getState() {
    return {
      isTyping: isTyping,
      showPassword: showPassword,
      passwordLength: passwordInput.value.length
    };
  }

  function refreshCharacters() {
    if (characters) {
      characters.render();
    }
  }

  /**
   * 显隐 SVG 图标：SVG 元素不支持 hidden 属性反射，
   * 统一走 attribute + CSS `.login-icon[hidden]{display:none}`。
   */
  function setIconVisible(icon, visible) {
    if (!icon) {
      return;
    }
    if (visible) {
      icon.removeAttribute('hidden');
    } else {
      icon.setAttribute('hidden', '');
    }
  }

  function showError(message) {
    errorEl.textContent = message;
    errorEl.hidden = !message;
    accountInput.classList.toggle('is-error', Boolean(message));
    passwordInput.classList.toggle('is-error', Boolean(message));
  }

  function clearError() {
    if (!errorEl.hidden) {
      showError('');
    }
  }

  function setLoading(value) {
    loading = value;
    accountInput.disabled = value;
    passwordInput.disabled = value;
    togglePasswordBtn.disabled = value;
    submitBtn.disabled = value;
    submitBtn.setAttribute('aria-busy', value ? 'true' : 'false');

    if (value) {
      submitBtn.innerHTML =
        '<span class="login-btn__spinner" aria-hidden="true"></span>' +
        '<span class="login-btn__text">登录中...</span>';
    } else {
      submitBtn.innerHTML = SUBMIT_IDLE_HTML;
    }
  }

  /**
   * 调用登录接口
   * @param {string} username
   * @param {string} password
   * @returns {Promise<{ok: boolean, error?: string}>}
   */
  function requestLogin(username, password) {
    return fetch(LOGIN_ENDPOINT, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      credentials: 'same-origin',
      body: JSON.stringify({ username: username, password: password })
    }).then(
      function (response) {
        return response
          .json()
          .catch(function () {
            // 后端异常（PHP 报错 / 空白输出）时不是合法 JSON
            return { error: response.ok ? '服务返回异常，请稍后重试' : '登录失败（HTTP ' + response.status + '）' };
          })
          .then(function (data) {
            if (response.ok && data && data.success) {
              return { ok: true };
            }
            return {
              ok: false,
              error: (data && (data.error || data.message)) || '账号或密码错误'
            };
          });
      },
      function () {
        return { ok: false, error: '网络连接失败，请检查服务是否正常运行' };
      }
    );
  }

  function onSubmit(event) {
    event.preventDefault();
    if (loading) {
      return;
    }

    clearError();

    var username = accountInput.value.trim();
    var password = passwordInput.value;

    if (!username) {
      showError('请输入账号');
      accountInput.focus();
      return;
    }
    if (!password) {
      showError('请输入密码');
      passwordInput.focus();
      return;
    }

    setLoading(true);
    requestLogin(username, password)
      .then(function (result) {
        if (!result.ok) {
          setLoading(false);
          showError(result.error);
          passwordInput.focus();
          passwordInput.select();
          return;
        }
        // 成功后保持按钮禁用状态，避免二次提交
        submitBtn.innerHTML =
          '<span class="login-btn__spinner" aria-hidden="true"></span>' +
          '<span class="login-btn__text">正在进入...</span>';
        window.location.replace(HOME_URL);
      })
      .catch(function () {
        setLoading(false);
        showError('登录失败，请稍后重试');
      });
  }

  /* ------------------------------ 交互绑定 ------------------------------ */

  accountInput.addEventListener('focus', function () {
    isTyping = true;
    refreshCharacters();
  });

  accountInput.addEventListener('blur', function () {
    isTyping = false;
    refreshCharacters();
  });

  accountInput.addEventListener('input', clearError);

  passwordInput.addEventListener('focus', function () {
    isTyping = true;
    refreshCharacters();
  });

  passwordInput.addEventListener('blur', function () {
    isTyping = false;
    refreshCharacters();
  });

  passwordInput.addEventListener('input', function () {
    clearError();
    refreshCharacters();
  });

  // 阻止按钮夺取密码框焦点（否则点一下眼睛图标输入框就失焦）
  togglePasswordBtn.addEventListener('mousedown', function (e) {
    e.preventDefault();
  });

  togglePasswordBtn.addEventListener('click', function () {
    showPassword = !showPassword;
    passwordInput.type = showPassword ? 'text' : 'password';
    togglePasswordBtn.setAttribute('aria-label', showPassword ? '隐藏密码' : '显示密码');
    // 注意：这两个元素是 SVG，SVGElement 没有 hidden 的 IDL 反射属性，
    // 直接写 el.hidden = true 不会产生 DOM 属性，必须显式操作 attribute。
    setIconVisible(viewIcon, !showPassword);
    setIconVisible(hideIcon, showPassword);
    refreshCharacters();
  });

  form.addEventListener('submit', onSubmit);

  /* ------------------------------ 初始化 ------------------------------ */

  if (charactersRoot && typeof window.LoginCharacters === 'function') {
    characters = window.LoginCharacters(charactersRoot, getState);
  }

  // 页面隐藏时停止重绘，回到前台补一次
  document.addEventListener('visibilitychange', function () {
    if (!document.hidden) {
      refreshCharacters();
    }
  });
})();
