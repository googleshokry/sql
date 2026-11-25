/* تنقل بالريموت (أسهم + OK + رجوع) لشاشات VIDAA والتلفزيونات */
var Nav = (function () {
  var KEYS = {
    LEFT: [37], UP: [38], RIGHT: [39], DOWN: [40],
    ENTER: [13],
    BACK: [8, 27, 461, 10009, 166],
    PLAY: [415, 250], PAUSE: [19], PLAYPAUSE: [179, 463],
    STOP: [413, 178], FF: [417, 228], RW: [412, 227],
    CH_UP: [427, 33], CH_DOWN: [428, 34]
  };
  function keyName(code) {
    for (var k in KEYS) if (KEYS[k].indexOf(code) >= 0) return k;
    return null;
  }

  var scopeFn = function () { return document.body; };
  var handler = null;     // دالة الشاشة الحالية تستقبل المفاتيح أولاً
  var onBack = null;
  var onFocusHook = null;

  function visible(el) {
    if (!el.offsetParent && el.tagName !== 'BODY') return false;
    var r = el.getBoundingClientRect();
    return r.width > 0 && r.height > 0;
  }
  function focusables(scope) {
    var all = scope.querySelectorAll('.focusable');
    var out = [];
    for (var i = 0; i < all.length; i++) if (visible(all[i])) out.push(all[i]);
    return out;
  }

  function focus(el) {
    if (!el) return;
    el.focus({ preventScroll: true });
    scrollIntoViewIfNeeded(el);
    if (onFocusHook) onFocusHook(el);
  }

  function scrollIntoViewIfNeeded(el) {
    var p = el.parentElement;
    while (p && p !== document.body) {
      var s = getComputedStyle(p);
      if (/(auto|scroll)/.test(s.overflowY + s.overflowX)) break;
      p = p.parentElement;
    }
    if (!p || p === document.body) return;
    var r = el.getBoundingClientRect(), pr = p.getBoundingClientRect();
    var m = 20;
    if (r.top < pr.top) p.scrollTop -= (pr.top - r.top) + m;
    else if (r.bottom > pr.bottom) p.scrollTop += (r.bottom - pr.bottom) + m;
    if (r.left < pr.left) p.scrollLeft -= (pr.left - r.left) + m;
    else if (r.right > pr.right) p.scrollLeft += (r.right - pr.right) + m;
  }

  function move(dir) {
    var scope = scopeFn();
    var list = focusables(scope);
    if (!list.length) return false;
    var cur = document.activeElement;
    if (!cur || list.indexOf(cur) < 0) { focus(list[0]); return true; }

    var r = cur.getBoundingClientRect();
    var cx = r.left + r.width / 2, cy = r.top + r.height / 2;
    var best = null, bestScore = Infinity;
    for (var i = 0; i < list.length; i++) {
      var el = list[i];
      if (el === cur) continue;
      var e = el.getBoundingClientRect();
      var ex = e.left + e.width / 2, ey = e.top + e.height / 2;
      var main, side;
      if (dir === 'LEFT') { if (e.right > r.left + 1 && ex >= cx - 1) continue; main = cx - ex; side = Math.abs(ey - cy); if (e.bottom > r.top && e.top < r.bottom) side = 0; }
      else if (dir === 'RIGHT') { if (e.left < r.right - 1 && ex <= cx + 1) continue; main = ex - cx; side = Math.abs(ey - cy); if (e.bottom > r.top && e.top < r.bottom) side = 0; }
      else if (dir === 'UP') { if (e.bottom > r.top + 1 && ey >= cy - 1) continue; main = cy - ey; side = Math.abs(ex - cx); if (e.right > r.left && e.left < r.right) side = 0; }
      else { if (e.top < r.bottom - 1 && ey <= cy + 1) continue; main = ey - cy; side = Math.abs(ex - cx); if (e.right > r.left && e.left < r.right) side = 0; }
      if (main <= 0) continue;
      var score = main + side * 3;
      if (score < bestScore) { bestScore = score; best = el; }
    }
    if (best) { focus(best); return true; }
    return false;
  }

  function isTextInput(el) {
    return el && el.tagName === 'INPUT' && /^(text|search|url|password|number|email)$/i.test(el.type);
  }

  document.addEventListener('keydown', function (ev) {
    var k = keyName(ev.keyCode);
    if (!k) return;
    var el = document.activeElement;

    // داخل خانة الكتابة: نسمح بالحذف والتحرك بين الحروف
    if (isTextInput(el)) {
      if (k === 'BACK' && ev.keyCode === 8 && el.value) return;
      if (k === 'LEFT' || k === 'RIGHT') {
        var atStart = el.selectionStart === 0, atEnd = el.selectionEnd === el.value.length;
        var rtl = getComputedStyle(el).direction === 'rtl';
        var towardStart = (k === 'LEFT') !== rtl;
        if (el.value && ((towardStart && !atStart) || (!towardStart && !atEnd))) return;
      }
      if (k === 'ENTER') { el.blur(); el.focus(); }
    }

    if (handler && handler(k, ev) === true) { ev.preventDefault(); return; }

    if (k === 'LEFT' || k === 'RIGHT' || k === 'UP' || k === 'DOWN') {
      ev.preventDefault();
      move(k);
    } else if (k === 'ENTER') {
      if (el && el.classList.contains('focusable') && !isTextInput(el)) { ev.preventDefault(); el.click(); }
    } else if (k === 'BACK') {
      ev.preventDefault();
      if (onBack) onBack();
    }
  }, true);

  return {
    setScope: function (fn) { scopeFn = fn; },
    setHandler: function (fn) { handler = fn; },
    setBack: function (fn) { onBack = fn; },
    onFocus: function (fn) { onFocusHook = fn; },
    focus: focus,
    focusFirst: function (sel) {
      var scope = scopeFn();
      var el = sel ? scope.querySelector(sel) : null;
      if (!el || !visible(el)) el = focusables(scope)[0];
      focus(el);
    },
    move: move
  };
})();
