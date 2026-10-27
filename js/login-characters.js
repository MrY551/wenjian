/**
 * 登录页动画角色：指针追踪 + 四个角色的表情渲染
 * 导出：window.LoginCharacters(root, getState) => { render, destroy }
 *
 * getState() 需返回 { isTyping, showPassword, passwordLength }
 *
 * 设计要点：
 * - 指针位置用 requestAnimationFrame 节流，一帧内多次 mousemove 只做一次布局读取；
 * - 角色不可见（移动端 display:none）时跳过几何计算，避免无谓的 getBoundingClientRect；
 * - 所有定时器可被 destroy() 终止，不会在页面切走后继续空转。
 */
(function (global) {
  'use strict';

  /* ------------------------------ 指针追踪 ------------------------------ */

  var pointer = { x: 0, y: 0, active: false };
  var pending = { x: 0, y: 0 };
  var rafId = null;
  var dirty = false;
  var pointerListeners = new Set();

  function flush() {
    rafId = null;
    if (!dirty) return;
    dirty = false;
    pointer.x = pending.x;
    pointer.y = pending.y;
    pointer.active = true;
    // 复制一份再遍历：回调里可能取消订阅
    Array.from(pointerListeners).forEach(function (fn) {
      fn(pointer.x, pointer.y);
    });
  }

  function schedule(x, y) {
    pending.x = x;
    pending.y = y;
    dirty = true;
    if (rafId === null) {
      rafId = global.requestAnimationFrame(flush);
    }
  }

  function onMouseMove(e) {
    schedule(e.clientX, e.clientY);
  }

  function onTouchMove(e) {
    var t = e.touches && e.touches[0];
    if (t) schedule(t.clientX, t.clientY);
  }

  global.addEventListener('mousemove', onMouseMove, { passive: true });
  global.addEventListener('touchmove', onTouchMove, { passive: true });

  function getPointer() {
    return pointer;
  }

  /* ------------------------------ 眼球 / 瞳孔 ------------------------------ */

  /**
   * 眼球：白色底座 + 深色瞳孔，瞳孔朝指针方向偏移
   * @param {{size:number, pupilSize:number, maxDistance:number, eyeColor:string, pupilColor:string}} config
   */
  function createEyeBall(config) {
    var el = document.createElement('div');
    el.className = 'eyeball';
    var pupil = document.createElement('div');
    pupil.className = 'eyeball-pupil';
    el.appendChild(pupil);

    var isBlinking = false;
    var forceLookX;
    var forceLookY;

    function applySize() {
      el.style.width = config.size + 'px';
      el.style.height = (isBlinking ? 2 : config.size) + 'px';
      el.style.backgroundColor = config.eyeColor;
      pupil.style.width = config.pupilSize + 'px';
      pupil.style.height = config.pupilSize + 'px';
      pupil.style.backgroundColor = config.pupilColor;
      pupil.style.display = isBlinking ? 'none' : 'block';
    }

    function update() {
      if (isBlinking) return;

      var x = 0;
      var y = 0;

      if (forceLookX !== undefined && forceLookY !== undefined) {
        x = forceLookX;
        y = forceLookY;
      } else {
        if (!pointer.active) {
          pupil.style.transform = 'translate(0px, 0px)';
          return;
        }
        var rect = el.getBoundingClientRect();
        var centerX = rect.left + rect.width / 2;
        var centerY = rect.top + rect.height / 2;
        var deltaX = pointer.x - centerX;
        var deltaY = pointer.y - centerY;
        var distance = Math.min(
          Math.sqrt(deltaX * deltaX + deltaY * deltaY),
          config.maxDistance
        );
        var angle = Math.atan2(deltaY, deltaX);
        x = Math.cos(angle) * distance;
        y = Math.sin(angle) * distance;
      }

      pupil.style.transform = 'translate(' + x + 'px, ' + y + 'px)';
    }

    applySize();

    return {
      element: el,
      setBlinking: function (value) {
        isBlinking = value;
        applySize();
        update();
      },
      setForceLook: function (x, y) {
        forceLookX = x;
        forceLookY = y;
      },
      update: update
    };
  }

  /**
   * 独立瞳孔：橙色 / 黄色角色的眼睛本体就是瞳孔
   * @param {{size:number, maxDistance:number, pupilColor:string}} config
   */
  function createPupil(config) {
    var el = document.createElement('div');
    el.className = 'pupil';
    el.style.width = config.size + 'px';
    el.style.height = config.size + 'px';
    el.style.backgroundColor = config.pupilColor;

    var forceLookX;
    var forceLookY;

    function update() {
      var x = 0;
      var y = 0;

      if (forceLookX !== undefined && forceLookY !== undefined) {
        x = forceLookX;
        y = forceLookY;
      } else {
        if (!pointer.active) {
          el.style.transform = 'translate(0px, 0px)';
          return;
        }
        var rect = el.getBoundingClientRect();
        var centerX = rect.left + rect.width / 2;
        var centerY = rect.top + rect.height / 2;
        var deltaX = pointer.x - centerX;
        var deltaY = pointer.y - centerY;
        var distance = Math.min(
          Math.sqrt(deltaX * deltaX + deltaY * deltaY),
          config.maxDistance
        );
        var angle = Math.atan2(deltaY, deltaX);
        x = Math.cos(angle) * distance;
        y = Math.sin(angle) * distance;
      }

      el.style.transform = 'translate(' + x + 'px, ' + y + 'px)';
    }

    return {
      element: el,
      setForceLook: function (x, y) {
        forceLookX = x;
        forceLookY = y;
      },
      update: update
    };
  }

  /* ------------------------------ 角色组合 ------------------------------ */

  function LoginCharacters(root, getState) {
    var container = document.createElement('div');
    container.className = 'animated-characters';

    // 紫色：高个方块，带两只眼球
    var purple = document.createElement('div');
    purple.className = 'character character-purple';
    var purpleEyesWrap = document.createElement('div');
    purpleEyesWrap.className = 'character-eyes';
    var purpleEye1 = createEyeBall({ size: 18, pupilSize: 7, maxDistance: 5, eyeColor: 'white', pupilColor: '#2D2D2D' });
    var purpleEye2 = createEyeBall({ size: 18, pupilSize: 7, maxDistance: 5, eyeColor: 'white', pupilColor: '#2D2D2D' });
    purpleEyesWrap.append(purpleEye1.element, purpleEye2.element);
    purple.appendChild(purpleEyesWrap);

    // 黑色：细高个，两只小眼球
    var black = document.createElement('div');
    black.className = 'character character-black';
    var blackEyesWrap = document.createElement('div');
    blackEyesWrap.className = 'character-eyes';
    var blackEye1 = createEyeBall({ size: 16, pupilSize: 6, maxDistance: 4, eyeColor: 'white', pupilColor: '#2D2D2D' });
    var blackEye2 = createEyeBall({ size: 16, pupilSize: 6, maxDistance: 4, eyeColor: 'white', pupilColor: '#2D2D2D' });
    blackEyesWrap.append(blackEye1.element, blackEye2.element);
    black.appendChild(blackEyesWrap);

    // 橙色：矮胖拱形，只有瞳孔
    var orange = document.createElement('div');
    orange.className = 'character character-orange';
    var orangeEyesWrap = document.createElement('div');
    orangeEyesWrap.className = 'character-eyes character-eyes--pupils';
    var orangePupil1 = createPupil({ size: 12, maxDistance: 5, pupilColor: '#2D2D2D' });
    var orangePupil2 = createPupil({ size: 12, maxDistance: 5, pupilColor: '#2D2D2D' });
    orangeEyesWrap.append(orangePupil1.element, orangePupil2.element);
    orange.appendChild(orangeEyesWrap);

    // 黄色：拱形 + 嘴
    var yellow = document.createElement('div');
    yellow.className = 'character character-yellow';
    var yellowEyesWrap = document.createElement('div');
    yellowEyesWrap.className = 'character-eyes character-eyes--pupils';
    var yellowPupil1 = createPupil({ size: 12, maxDistance: 5, pupilColor: '#2D2D2D' });
    var yellowPupil2 = createPupil({ size: 12, maxDistance: 5, pupilColor: '#2D2D2D' });
    yellowEyesWrap.append(yellowPupil1.element, yellowPupil2.element);
    var yellowMouth = document.createElement('div');
    yellowMouth.className = 'character-mouth';
    yellow.append(yellowEyesWrap, yellowMouth);

    container.append(purple, black, orange, yellow);
    root.appendChild(container);

    var destroyed = false;
    var isLookingAtEachOther = false;
    var isPurplePeeking = false;
    var isPurpleBlinking = false;
    var isBlackBlinking = false;
    var prevIsTyping = false;
    var prevPasswordKey = '';

    var purpleBlinkTimeout = null;
    var blackBlinkTimeout = null;
    var purpleBlinkEndTimeout = null;
    var blackBlinkEndTimeout = null;
    var lookAtTimer = null;
    var purplePeekTimeout = null;
    var purplePeekEndTimeout = null;

    // 角色被 CSS 隐藏（移动端）时不必做几何计算
    function isVisible() {
      return !destroyed && root.clientWidth > 0 && root.clientHeight > 0;
    }

    function calculatePosition(el) {
      if (!pointer.active) {
        // 指针尚未移动：保持立正姿态，避免页面一打开角色就是歪的
        return { faceX: 0, faceY: 0, bodySkew: 0 };
      }
      var rect = el.getBoundingClientRect();
      var centerX = rect.left + rect.width / 2;
      var centerY = rect.top + rect.height / 3;
      var deltaX = pointer.x - centerX;
      var deltaY = pointer.y - centerY;
      var faceX = Math.max(-15, Math.min(15, deltaX / 20));
      var faceY = Math.max(-10, Math.min(10, deltaY / 30));
      var bodySkew = Math.max(-6, Math.min(6, -deltaX / 120));
      return { faceX: faceX, faceY: faceY, bodySkew: bodySkew };
    }

    function schedulePurpleBlink() {
      if (destroyed) return;
      purpleBlinkTimeout = setTimeout(function () {
        if (destroyed) return;
        isPurpleBlinking = true;
        purpleEye1.setBlinking(true);
        purpleEye2.setBlinking(true);
        purpleBlinkEndTimeout = setTimeout(function () {
          if (destroyed) return;
          isPurpleBlinking = false;
          purpleEye1.setBlinking(false);
          purpleEye2.setBlinking(false);
          schedulePurpleBlink();
        }, 150);
      }, Math.random() * 4000 + 3000);
    }

    function scheduleBlackBlink() {
      if (destroyed) return;
      blackBlinkTimeout = setTimeout(function () {
        if (destroyed) return;
        isBlackBlinking = true;
        blackEye1.setBlinking(true);
        blackEye2.setBlinking(true);
        blackBlinkEndTimeout = setTimeout(function () {
          if (destroyed) return;
          isBlackBlinking = false;
          blackEye1.setBlinking(false);
          blackEye2.setBlinking(false);
          scheduleBlackBlink();
        }, 150);
      }, Math.random() * 4000 + 3000);
    }

    // 密码可见且有内容时，紫色角色会"偷看"
    function schedulePurplePeek(state) {
      clearTimeout(purplePeekTimeout);
      clearTimeout(purplePeekEndTimeout);
      if (destroyed) return;
      if (state.passwordLength > 0 && state.showPassword) {
        purplePeekTimeout = setTimeout(function () {
          if (destroyed) return;
          isPurplePeeking = true;
          render();
          purplePeekEndTimeout = setTimeout(function () {
            if (destroyed) return;
            isPurplePeeking = false;
            schedulePurplePeek(getState());
          }, 800);
        }, Math.random() * 3000 + 2000);
      } else {
        isPurplePeeking = false;
      }
    }

    function updateLookAtEachOther(state) {
      if (state.isTyping) {
        isLookingAtEachOther = true;
        clearTimeout(lookAtTimer);
        lookAtTimer = setTimeout(function () {
          isLookingAtEachOther = false;
        }, 800);
      } else {
        isLookingAtEachOther = false;
      }
    }

    function setPosition(el, left, top) {
      el.style.left = left + 'px';
      el.style.top = top + 'px';
    }

    function setForceLook(eyes, x, y) {
      eyes.forEach(function (eye) {
        eye.setForceLook(x, y);
      });
    }

    function syncStateSideEffects(state) {
      if (state.isTyping !== prevIsTyping) {
        prevIsTyping = state.isTyping;
        updateLookAtEachOther(state);
      }

      var passwordKey = state.passwordLength + ':' + state.showPassword;
      if (passwordKey !== prevPasswordKey) {
        prevPasswordKey = passwordKey;
        schedulePurplePeek(state);
      }
    }

    function render() {
      if (destroyed) return;
      var state = getState();
      syncStateSideEffects(state);

      // 移动端角色整体隐藏，直接返回（状态副作用已在上一步处理）
      if (!isVisible()) return;

      var isHidingPassword = state.passwordLength > 0 && !state.showPassword;
      var showPwd = state.passwordLength > 0 && state.showPassword;
      var typing = state.isTyping;

      var purplePos = calculatePosition(purple);
      var blackPos = calculatePosition(black);
      var orangePos = calculatePosition(orange);
      var yellowPos = calculatePosition(yellow);

      // --- 身体倾斜（输入时身体向前探） ---
      var purpleTransform = 'skewX(' + purplePos.bodySkew + 'deg)';
      if (showPwd) {
        purpleTransform = 'skewX(0deg)';
      } else if (typing || isHidingPassword) {
        purpleTransform = 'skewX(' + (purplePos.bodySkew - 12) + 'deg) translateX(40px)';
      }
      purple.style.height = typing || isHidingPassword ? '440px' : '400px';
      purple.style.transform = purpleTransform;
      purple.style.transformOrigin = 'bottom center';

      var blackTransform = 'skewX(' + blackPos.bodySkew + 'deg)';
      if (showPwd) {
        blackTransform = 'skewX(0deg)';
      } else if (isLookingAtEachOther) {
        blackTransform = 'skewX(' + (blackPos.bodySkew * 1.5 + 10) + 'deg) translateX(20px)';
      } else if (typing || isHidingPassword) {
        blackTransform = 'skewX(' + blackPos.bodySkew * 1.5 + 'deg)';
      }
      black.style.transform = blackTransform;
      black.style.transformOrigin = 'bottom center';

      orange.style.transform = showPwd ? 'skewX(0deg)' : 'skewX(' + orangePos.bodySkew + 'deg)';
      orange.style.transformOrigin = 'bottom center';

      yellow.style.transform = showPwd ? 'skewX(0deg)' : 'skewX(' + yellowPos.bodySkew + 'deg)';
      yellow.style.transformOrigin = 'bottom center';

      // --- 眼睛位置 ---
      var purpleLeft = 45 + purplePos.faceX;
      var purpleTop = 40 + purplePos.faceY;
      if (showPwd) {
        purpleLeft = 20;
        purpleTop = 35;
      } else if (isLookingAtEachOther) {
        purpleLeft = 55;
        purpleTop = 65;
      }
      setPosition(purpleEyesWrap, purpleLeft, purpleTop);

      var blackLeft = 26 + blackPos.faceX;
      var blackTop = 32 + blackPos.faceY;
      if (showPwd) {
        blackLeft = 10;
        blackTop = 28;
      } else if (isLookingAtEachOther) {
        blackLeft = 32;
        blackTop = 12;
      }
      setPosition(blackEyesWrap, blackLeft, blackTop);

      setPosition(orangeEyesWrap, showPwd ? 50 : 82 + orangePos.faceX, showPwd ? 85 : 90 + orangePos.faceY);
      setPosition(yellowEyesWrap, showPwd ? 20 : 52 + yellowPos.faceX, showPwd ? 35 : 40 + yellowPos.faceY);
      setPosition(yellowMouth, showPwd ? 10 : 40 + yellowPos.faceX, showPwd ? 88 : 88 + yellowPos.faceY);

      // --- 视线 ---
      var purpleEyes = [purpleEye1, purpleEye2];
      var blackEyes = [blackEye1, blackEye2];
      var orangeEyes = [orangePupil1, orangePupil2];
      var yellowEyes = [yellowPupil1, yellowPupil2];

      if (showPwd) {
        // 密码明文时大家把目光移开，紫色偷看一眼
        setForceLook(purpleEyes, isPurplePeeking ? 4 : -4, isPurplePeeking ? 5 : -4);
        setForceLook(blackEyes, -4, -4);
        setForceLook(orangeEyes, -5, -4);
        setForceLook(yellowEyes, -5, -4);
      } else {
        if (isLookingAtEachOther) {
          setForceLook(purpleEyes, 3, 4);
          setForceLook(blackEyes, 0, -4);
        } else {
          setForceLook(purpleEyes, undefined, undefined);
          setForceLook(blackEyes, undefined, undefined);
        }
        setForceLook(orangeEyes, undefined, undefined);
        setForceLook(yellowEyes, undefined, undefined);
      }

      purpleEye1.update();
      purpleEye2.update();
      blackEye1.update();
      blackEye2.update();
      orangePupil1.update();
      orangePupil2.update();
      yellowPupil1.update();
      yellowPupil2.update();
    }

    function onPointerMove() {
      if (!isVisible()) return;
      render();
    }

    pointerListeners.add(onPointerMove);
    schedulePurpleBlink();
    scheduleBlackBlink();
    render();

    return {
      render: render,
      destroy: function () {
        destroyed = true;
        clearTimeout(purpleBlinkTimeout);
        clearTimeout(blackBlinkTimeout);
        clearTimeout(purpleBlinkEndTimeout);
        clearTimeout(blackBlinkEndTimeout);
        clearTimeout(lookAtTimer);
        clearTimeout(purplePeekTimeout);
        clearTimeout(purplePeekEndTimeout);
        pointerListeners.delete(onPointerMove);
        if (container.parentNode) container.parentNode.removeChild(container);
      }
    };
  }

  global.LoginCharacters = LoginCharacters;
})(window);
