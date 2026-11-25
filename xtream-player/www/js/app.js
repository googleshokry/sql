/* منطق التطبيق الرئيسي */
var App = (function () {
  var $ = function (id) { return document.getElementById(id); };
  var CHUNK = 120;

  var S = {
    acc: null, info: null,
    section: 'live',
    data: {},          // data[type] = { cats: [], items: [] }
    catId: 'all',
    list: [],          // العناصر المعروضة حالياً
    rendered: 0,
    query: '',
    lastFocus: null,
    detail: null,
    exitArmed: 0
  };

  /* ---------------- أدوات ---------------- */
  var toastTimer;
  function toast(msg) {
    var t = $('toast');
    t.textContent = msg;
    t.classList.add('show');
    clearTimeout(toastTimer);
    toastTimer = setTimeout(function () { t.classList.remove('show'); }, 2800);
  }
  function el(tag, cls, text) {
    var e = document.createElement(tag);
    if (cls) e.className = cls;
    if (text != null) e.textContent = text;
    return e;
  }
  function img(src, cls) {
    var i = el('img', cls);
    i.loading = 'lazy';
    i.alt = '';
    if (src) i.src = src;
    i.onerror = function () { i.onerror = null; i.removeAttribute('src'); };
    return i;
  }

  /* ---------------- النطاق الحالي للتنقل ---------------- */
  Nav.setScope(function () {
    if (Player.isOpen()) return $('player');
    var order = ['settings', 'details', 'main', 'login'];
    for (var i = 0; i < order.length; i++) if (!$(order[i]).classList.contains('hidden')) return $(order[i]);
    return document.body;
  });
  Nav.setHandler(function (k) {
    if (Player.isOpen()) return Player.keys(k);
    return false;
  });
  Nav.onFocus(function (e) {
    // تحميل المزيد عند الاقتراب من نهاية القائمة
    if (e.parentElement === $('grid')) {
      var idx = Array.prototype.indexOf.call($('grid').children, e);
      if (idx > S.rendered - 25) renderMore();
    }
  });
  Nav.setBack(back);

  function back() {
    if (Player.isOpen()) { Player.close(); return; }
    if (!$('settings').classList.contains('hidden')) { closeSettings(); return; }
    if (!$('details').classList.contains('hidden')) { closeDetails(); return; }
    if (!$('main').classList.contains('hidden')) {
      var a = document.activeElement;
      if (a && $('grid').contains(a)) { Nav.focusFirst('#cats .cat.active'); return; }
      if (a && $('cats').contains(a)) { Nav.focusFirst('#tabs .tab.active'); return; }
    }
    if (Date.now() - S.exitArmed < 2500) { exitApp(); return; }
    S.exitArmed = Date.now();
    toast('اضغط رجوع مرة أخرى للخروج');
  }

  function exitApp() {
    if (Api.isNative) {
      window.capacitorExports.registerPlugin('App').exitApp();
    } else {
      try { window.close(); } catch (e) {}
    }
  }

  /* ---------------- تسجيل الدخول ---------------- */
  function renderSaved() {
    var box = $('saved-accounts');
    box.innerHTML = '';
    Store.accounts().forEach(function (a) {
      var w = el('div', 'acc');
      var b = el('button', 'btn focusable', '👤 ' + a.user + ' @ ' + a.server.replace(/^https?:\/\//, ''));
      b.onclick = function () { doLogin(a); };
      var x = el('button', 'btn focusable danger', '✕');
      x.onclick = function () { Store.removeAccount(a); renderSaved(); Nav.focusFirst(); };
      w.appendChild(b); w.appendChild(x);
      box.appendChild(w);
    });
  }

  function loginMsg(m, err) {
    var e = $('login-msg');
    e.textContent = m || '';
    e.className = 'msg' + (err ? ' err' : '');
  }

  function doLogin(acc) {
    acc = { server: Api.normServer(acc.server), user: (acc.user || '').trim(), pass: acc.pass || '' };
    if (!acc.server || !acc.user) { loginMsg('من فضلك أدخل السيرفر واسم المستخدم', true); return; }
    loginMsg('جارِ الاتصال...');
    Api.login(acc).then(function (d) {
      S.acc = acc; S.info = d; S.data = {};
      Store.addAccount(acc);
      Store.setCurrent(acc);
      loginMsg('');
      enterMain();
    }).catch(function (e) {
      loginMsg(e.message, true);
      $('login').classList.remove('hidden');
      $('main').classList.add('hidden');
    });
  }

  $('f-server').addEventListener('change', function () {
    var p = Api.parseM3uLink(this.value);
    if (p) { this.value = p.server; $('f-user').value = p.user; $('f-pass').value = p.pass; }
  });
  $('btn-login').onclick = function () {
    var p = Api.parseM3uLink($('f-server').value);
    doLogin(p || { server: $('f-server').value, user: $('f-user').value, pass: $('f-pass').value });
  };

  /* ---------------- الرئيسية ---------------- */
  function enterMain() {
    $('login').classList.add('hidden');
    $('main').classList.remove('hidden');
    switchSection('live');
  }

  Array.prototype.forEach.call(document.querySelectorAll('#tabs .tab'), function (t) {
    t.onclick = function () {
      var s = t.getAttribute('data-section');
      if (s === 'settings') openSettings(); else switchSection(s);
    };
  });

  function setActiveTab() {
    Array.prototype.forEach.call(document.querySelectorAll('#tabs .tab'), function (t) {
      t.classList.toggle('active', t.getAttribute('data-section') === S.section);
    });
  }

  function loadType(type) {
    if (S.data[type]) return Promise.resolve(S.data[type]);
    return Promise.all([Api.categories(S.acc, type), Api.streams(S.acc, type)]).then(function (r) {
      var d = { cats: r[0], items: r[1] };
      d.items.forEach(function (x, i) {
        x._type = type;
        x._id = type === 'series' ? x.series_id : x.stream_id;
        x._name = x.name || x.title || '';
        x._lname = x._name.toLowerCase();
        x._icon = type === 'live' ? x.stream_icon : (x.stream_icon || x.cover);
        x._num = x.num || i + 1;
      });
      S.data[type] = d;
      return d;
    });
  }

  function switchSection(sec) {
    S.section = sec;
    S.catId = 'all';
    S.query = '';
    $('search').value = '';
    setActiveTab();
    $('grid').innerHTML = '';
    $('cats').innerHTML = '';
    $('empty').classList.add('hidden');

    if (sec === 'fav') { renderFavCats(); applyFilter(); return; }

    $('empty').textContent = 'جارِ التحميل...';
    $('empty').classList.remove('hidden');
    loadType(sec).then(function () {
      if (S.section !== sec) return;
      renderCats();
      applyFilter();
      var a = document.activeElement;
      if (!a || !$('main').contains(a) || a === document.body) Nav.focusFirst('#grid .card');
    }).catch(function (e) {
      $('empty').textContent = 'خطأ: ' + e.message;
      $('empty').className = 'msg err';
    });
  }

  function catButton(id, name, count) {
    var b = el('button', 'cat focusable' + (S.catId === id ? ' active' : ''), name + (count != null ? '  (' + count + ')' : ''));
    b.onclick = function () {
      S.catId = id;
      Array.prototype.forEach.call($('cats').children, function (c) { c.classList.remove('active'); });
      b.classList.add('active');
      applyFilter();
    };
    return b;
  }

  function renderCats() {
    var d = S.data[S.section], box = $('cats');
    box.innerHTML = '';
    var counts = {};
    d.items.forEach(function (x) { counts[x.category_id] = (counts[x.category_id] || 0) + 1; });
    box.appendChild(catButton('all', 'الكل', d.items.length));
    d.cats.forEach(function (c) {
      if (!counts[c.category_id]) return;
      box.appendChild(catButton(String(c.category_id), c.category_name, counts[c.category_id]));
    });
  }

  function renderFavCats() {
    var box = $('cats');
    box.innerHTML = '';
    box.appendChild(catButton('all', 'الكل'));
    box.appendChild(catButton('live', '📺 قنوات'));
    box.appendChild(catButton('movie', '🎬 أفلام'));
    box.appendChild(catButton('series', '🎞️ مسلسلات'));
  }

  function favItems() {
    var f = Store.favs(S.acc);
    return Object.keys(f).map(function (k) { return f[k]; });
  }

  function applyFilter() {
    var q = S.query.toLowerCase().trim(), list;
    if (S.section === 'fav') {
      list = favItems().filter(function (x) {
        return (S.catId === 'all' || x._type === S.catId) && (!q || x._lname.indexOf(q) >= 0);
      });
    } else {
      var d = S.data[S.section];
      if (!d) return;
      list = d.items.filter(function (x) {
        return (q ? true : (S.catId === 'all' || String(x.category_id) === S.catId)) &&
          (!q || x._lname.indexOf(q) >= 0);
      });
    }
    S.list = list;
    S.rendered = 0;
    var g = $('grid');
    g.innerHTML = '';
    g.className = (S.section === 'live' || (S.section === 'fav' && S.catId === 'live')) ? 'live' : '';
    $('content').scrollTop = 0;
    $('empty').className = 'msg' + (list.length ? ' hidden' : '');
    $('empty').textContent = S.section === 'fav' && !q ? 'لا توجد عناصر في المفضلة بعد — اضغط ☆ من صفحة الفيلم/المسلسل أو مطولاً على القناة' : 'لا يوجد محتوى';
    renderMore();
  }

  function itemType(x) { return x._type || S.section; }

  function renderMore() {
    var g = $('grid'), end = Math.min(S.list.length, S.rendered + CHUNK);
    var frag = document.createDocumentFragment();
    for (var i = S.rendered; i < end; i++) frag.appendChild(card(S.list[i], i));
    g.appendChild(frag);
    S.rendered = end;
  }

  $('content').addEventListener('scroll', function () {
    var c = $('content');
    if (c.scrollTop + c.clientHeight > c.scrollHeight - 600 && S.rendered < S.list.length) renderMore();
  });

  function card(x, i) {
    var type = itemType(x);
    var fav = Store.isFav(S.acc, type, x._id);
    var b;
    if (type === 'live') {
      b = el('button', 'card ch focusable');
      b.appendChild(el('span', 'num', String(x._num)));
      b.appendChild(img(x._icon));
      b.appendChild(el('span', 'name', (fav ? '★ ' : '') + x._name));
    } else {
      b = el('button', 'card focusable');
      b.appendChild(img(x._icon));
      b.appendChild(el('span', 'name', (fav ? '★ ' : '') + x._name));
    }
    b.title = x._name;
    b.onclick = function () { openItem(x, i); };
    // ضغط مطول / زر أحمر = إضافة للمفضلة
    var pressT;
    b.addEventListener('contextmenu', function (e) { e.preventDefault(); toggleFav(x, b); });
    b.addEventListener('keydown', function (e) {
      if (e.keyCode === 403 /* زر أحمر */) { toggleFav(x, b); e.preventDefault(); }
    });
    b.addEventListener('touchstart', function () { pressT = setTimeout(function () { pressT = null; toggleFav(x, b); }, 700); }, { passive: true });
    b.addEventListener('touchend', function (e) { if (pressT) clearTimeout(pressT); else e.preventDefault(); });
    b.addEventListener('touchmove', function () { clearTimeout(pressT); }, { passive: true });
    return b;
  }

  function favRecord(x) {
    return {
      type: itemType(x), _type: itemType(x), id: x._id, _id: x._id, _name: x._name, _lname: x._lname, _icon: x._icon, _num: x._num,
      category_id: x.category_id, container_extension: x.container_extension,
      stream_id: x.stream_id, series_id: x.series_id, name: x._name
    };
  }

  function toggleFav(x, b) {
    var on = Store.toggleFav(S.acc, favRecord(x));
    toast(on ? '⭐ أُضيف إلى المفضلة' : 'أُزيل من المفضلة');
    if (b) {
      var n = b.querySelector('.name');
      n.textContent = (on ? '★ ' : '') + x._name;
    }
    return on;
  }

  function liveEntry(x) {
    return {
      url: Api.streamUrl(S.acc, 'live', x._id),
      title: x._name, type: 'live', num: x._num,
      epg: function () { return Api.shortEpg(S.acc, x._id); }
    };
  }

  function openItem(x, i) {
    var type = itemType(x);
    S.lastFocus = document.activeElement;
    if (type === 'live') {
      var lives = S.list.filter(function (y) { return itemType(y) === 'live'; });
      var idx = lives.indexOf(x);
      Player.open(lives.map(liveEntry), Math.max(0, idx), restoreFocus);
    } else {
      openDetails(x, type);
    }
  }

  function restoreFocus() {
    if (S.lastFocus && document.body.contains(S.lastFocus)) Nav.focus(S.lastFocus);
    else Nav.focusFirst();
  }

  /* ---------------- البحث ---------------- */
  var searchT;
  $('search').addEventListener('input', function () {
    clearTimeout(searchT);
    var v = this.value;
    searchT = setTimeout(function () { S.query = v; applyFilter(); }, 300);
  });

  /* ---------------- التفاصيل ---------------- */
  function openDetails(x, type) {
    S.detail = { x: x, type: type };
    $('details').classList.remove('hidden');
    $('det-title').textContent = x._name;
    $('det-meta').textContent = 'جارِ التحميل...';
    $('det-plot').textContent = '';
    $('det-poster').src = x._icon || '';
    $('seasons').innerHTML = '';
    $('episodes').innerHTML = '';
    $('det-play').classList.toggle('hidden', type === 'series');
    updateFavBtn();
    Nav.focusFirst(type === 'series' ? '#det-fav' : '#det-play');
    $('details').scrollTop = 0;

    var req = type === 'movie' ? Api.vodInfo(S.acc, x._id) : Api.seriesInfo(S.acc, x._id);
    req.then(function (d) {
      if (!S.detail || S.detail.x !== x) return;
      var info = (d && d.info) || {};
      if (Array.isArray(info)) info = {};
      var meta = [info.releasedate || info.releaseDate, info.genre, info.duration,
        info.rating ? '⭐ ' + info.rating : ''].filter(Boolean).join('  ·  ');
      $('det-meta').textContent = meta;
      $('det-plot').textContent = info.plot || info.description || '';
      var poster = info.movie_image || info.cover || x._icon;
      if (poster) $('det-poster').src = poster;
      if (type === 'movie') {
        S.detail.ext = (d.movie_data && d.movie_data.container_extension) || x.container_extension || 'mp4';
      } else {
        renderSeasons(d);
      }
    }).catch(function (e) { $('det-meta').textContent = 'خطأ: ' + e.message; });
  }

  function renderSeasons(d) {
    var eps = d.episodes || {};
    var keys = Object.keys(eps).sort(function (a, b) { return a - b; });
    var box = $('seasons');
    box.innerHTML = '';
    if (!keys.length) { $('episodes').textContent = 'لا توجد حلقات'; return; }
    keys.forEach(function (k, i) {
      var b = el('button', 'btn focusable', 'الموسم ' + k);
      b.onclick = function () {
        Array.prototype.forEach.call(box.children, function (c) { c.classList.remove('opt', 'active'); });
        b.classList.add('opt', 'active');
        renderEpisodes(eps[k]);
      };
      box.appendChild(b);
      if (i === 0) { b.classList.add('opt', 'active'); renderEpisodes(eps[k]); }
    });
  }

  function epKey(e) { return 'series:' + e.id; }

  function renderEpisodes(list) {
    var box = $('episodes');
    box.innerHTML = '';
    var entries = list.map(function (e) {
      return {
        url: Api.streamUrl(S.acc, 'series', e.id, e.container_extension || 'mp4'),
        title: S.detail.x._name + ' - ' + (e.title || ('حلقة ' + e.episode_num)),
        type: 'series', posKey: epKey(e)
      };
    });
    list.forEach(function (e, i) {
      var watched = Store.getPos(epKey(e)) > 0 ? ' ⏱' : '';
      var b = el('button', 'btn focusable ep', (e.episode_num || i + 1) + '. ' + (e.title || '') + watched);
      b.onclick = function () {
        S.lastFocus = b;
        Player.open(entries, i, restoreFocus);
      };
      box.appendChild(b);
    });
  }

  function updateFavBtn() {
    var d = S.detail;
    var on = Store.isFav(S.acc, d.type, d.x._id);
    $('det-fav').textContent = on ? '★ في المفضلة' : '☆ أضف للمفضلة';
  }

  $('det-play').onclick = function () {
    var d = S.detail, x = d.x;
    S.lastFocus = $('det-play');
    Player.open([{
      url: Api.streamUrl(S.acc, 'movie', x._id, d.ext || x.container_extension || 'mp4'),
      title: x._name, type: 'movie', posKey: 'movie:' + x._id
    }], 0, restoreFocus);
  };
  $('det-fav').onclick = function () { toggleFav(S.detail.x); updateFavBtn(); };
  $('det-back').onclick = closeDetails;

  function closeDetails() {
    $('details').classList.add('hidden');
    S.detail = null;
    if (S.section === 'fav') applyFilter();
    var g = $('grid');
    var target = null;
    // نرجع التركيز للكارت اللي فتحناه
    Array.prototype.some.call(g.children, function (c) { if (c === S.lastFocusCard) { target = c; return true; } });
    if (target) Nav.focus(target); else Nav.focusFirst('#grid .card');
  }
  document.addEventListener('click', function (e) {
    var c = e.target.closest ? e.target.closest('#grid .card') : null;
    if (c) S.lastFocusCard = c;
  }, true);

  /* ---------------- الإعدادات ---------------- */
  function openSettings() {
    $('settings').classList.remove('hidden');
    var u = (S.info && S.info.user_info) || {};
    var exp = u.exp_date ? new Date(u.exp_date * 1000).toLocaleDateString() : 'غير محدد';
    $('acc-info').textContent = 'الحساب: ' + (S.acc ? S.acc.user : '') +
      '  ·  ينتهي: ' + exp + '  ·  الاتصالات: ' + (u.active_cons || 0) + '/' + (u.max_connections || '-');
    $('s-proxy').value = Store.settings.proxy || '';
    markFmt();
    Nav.focusFirst('.opt.active');
  }
  function markFmt() {
    Array.prototype.forEach.call(document.querySelectorAll('[data-fmt]'), function (b) {
      b.classList.toggle('active', b.getAttribute('data-fmt') === Store.settings.liveFormat);
    });
  }
  Array.prototype.forEach.call(document.querySelectorAll('[data-fmt]'), function (b) {
    b.onclick = function () { Store.settings.liveFormat = b.getAttribute('data-fmt'); Store.saveSettings(); markFmt(); };
  });
  $('s-save').onclick = function () {
    Store.settings.proxy = $('s-proxy').value.trim();
    Store.saveSettings();
    toast('تم الحفظ');
    closeSettings();
  };
  $('s-reload').onclick = function () { S.data = {}; closeSettings(); switchSection(S.section === 'fav' ? 'live' : S.section); toast('جارِ تحديث القوائم...'); };
  $('s-logout').onclick = function () {
    Store.setCurrent(null);
    S.acc = null; S.data = {};
    $('settings').classList.add('hidden');
    $('main').classList.add('hidden');
    $('login').classList.remove('hidden');
    renderSaved();
    Nav.focusFirst();
  };
  $('s-back').onclick = closeSettings;
  function closeSettings() {
    $('settings').classList.add('hidden');
    Nav.focusFirst('#tabs .tab.active');
  }

  /* ---------------- البداية ---------------- */
  function init() {
    Player.init();
    if (Api.isNative) {
      var AppPlugin = window.capacitorExports.registerPlugin('App');
      AppPlugin.addListener('backButton', function () { back(); });
    }
    renderSaved();
    var cur = Store.current();
    if (cur) {
      $('f-server').value = cur.server; $('f-user').value = cur.user; $('f-pass').value = cur.pass;
      doLogin(cur);
      // نعرض الشاشة الرئيسية مباشرة أثناء الاتصال
      $('login').classList.add('hidden');
      $('main').classList.remove('hidden');
      $('empty').textContent = 'جارِ الاتصال...';
      $('empty').classList.remove('hidden');
    }
    setTimeout(function () {
      var a = document.activeElement;
      if (!a || a === document.body) Nav.focusFirst();
    }, 100);
  }

  Api.detectProxy().then(init);
  return { toast: toast, back: back };
})();
