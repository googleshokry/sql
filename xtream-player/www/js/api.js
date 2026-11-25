/* التعامل مع Xtream Codes API (player_api.php) */
var Api = (function () {
  var cap = window.capacitorExports;
  var isNative = !!(cap && cap.Capacitor && cap.Capacitor.isNativePlatform());

  function normServer(s) {
    s = (s || '').trim();
    if (!s) return s;
    if (!/^https?:\/\//i.test(s)) s = 'http://' + s;
    return s.replace(/\/+$/, '');
  }

  /* يحول رابط M3U (get.php?username=..&password=..) إلى بيانات دخول */
  function parseM3uLink(s) {
    var m = /^(https?:\/\/[^\/?#]+)[^?]*\?(.*)$/i.exec((s || '').trim());
    if (!m) return null;
    var q = {};
    m[2].split('&').forEach(function (p) {
      var kv = p.split('=');
      q[decodeURIComponent(kv[0])] = decodeURIComponent((kv[1] || '').replace(/\+/g, ' '));
    });
    if (q.username && q.password) return { server: m[1], user: q.username, pass: q.password };
    return null;
  }

  var autoProxy = '';   // لو التطبيق مفتوح من سيرفر البروكسي نفسه نستخدمه تلقائياً
  function detectProxy() {
    if (isNative || !/^https?:$/.test(location.protocol)) return Promise.resolve();
    return fetch('__xp').then(function (r) { return r.ok ? r.json() : null; }).then(function (j) {
      if (j && j.proxy) autoProxy = location.origin;
    }).catch(function () {});
  }

  function viaProxy(url) {
    var p = ((Store.settings.proxy || '').trim() || autoProxy).replace(/\/+$/, '');
    if (!p || isNative) return url;
    return p + '/proxy?url=' + encodeURIComponent(url);
  }

  function fetchJson(url, timeoutMs) {
    url = viaProxy(url);
    return new Promise(function (resolve, reject) {
      var done = false;
      var t = setTimeout(function () { if (!done) { done = true; reject(new Error('انتهت مهلة الاتصال')); } }, timeoutMs || 30000);
      fetch(url).then(function (r) {
        if (!r.ok) throw new Error('HTTP ' + r.status);
        return r.text();
      }).then(function (txt) {
        if (done) return;
        done = true; clearTimeout(t);
        try { resolve(JSON.parse(txt)); }
        catch (e) { reject(new Error('رد غير صالح من السيرفر')); }
      }).catch(function (e) {
        if (done) return;
        done = true; clearTimeout(t);
        reject(new Error(e && e.message === 'Failed to fetch'
          ? 'تعذر الاتصال بالسيرفر (تحقق من الرابط، أو فعّل البروكسي من الإعدادات)'
          : (e && e.message) || 'خطأ في الاتصال'));
      });
    });
  }

  function apiUrl(acc, params) {
    var u = acc.server + '/player_api.php?username=' + encodeURIComponent(acc.user) +
      '&password=' + encodeURIComponent(acc.pass);
    if (params) u += '&' + params;
    return u;
  }

  function call(acc, action, extra) {
    return fetchJson(apiUrl(acc, 'action=' + action + (extra ? '&' + extra : '')));
  }

  function asArray(x) {
    if (Array.isArray(x)) return x;
    if (x && typeof x === 'object') return Object.keys(x).map(function (k) { return x[k]; });
    return [];
  }

  return {
    isNative: isNative,
    normServer: normServer,
    parseM3uLink: parseM3uLink,
    viaProxy: viaProxy,
    detectProxy: detectProxy,

    login: function (acc) {
      return fetchJson(apiUrl(acc), 20000).then(function (d) {
        if (!d || !d.user_info) throw new Error('رد غير متوقع من السيرفر');
        if (d.user_info.auth === 0 || d.user_info.auth === '0') throw new Error('اسم المستخدم أو كلمة المرور غير صحيحة');
        if (d.user_info.status && d.user_info.status !== 'Active') throw new Error('الاشتراك غير فعال: ' + d.user_info.status);
        return d;
      });
    },

    categories: function (acc, type) {
      var a = { live: 'get_live_categories', movie: 'get_vod_categories', series: 'get_series_categories' }[type];
      return call(acc, a).then(asArray);
    },
    streams: function (acc, type) {
      var a = { live: 'get_live_streams', movie: 'get_vod_streams', series: 'get_series' }[type];
      return call(acc, a).then(asArray);
    },
    vodInfo: function (acc, id) { return call(acc, 'get_vod_info', 'vod_id=' + id); },
    seriesInfo: function (acc, id) { return call(acc, 'get_series_info', 'series_id=' + id); },
    shortEpg: function (acc, id) {
      return call(acc, 'get_short_epg', 'stream_id=' + id + '&limit=2').then(function (d) {
        return asArray(d && d.epg_listings).map(function (e) {
          return { title: b64(e.title), desc: b64(e.description), start: e.start, end: e.end,
            startTs: +e.start_timestamp, endTs: +e.stop_timestamp };
        });
      });
    },

    streamUrl: function (acc, type, id, ext) {
      var path = { live: 'live', movie: 'movie', series: 'series' }[type];
      if (type === 'live') ext = Store.settings.liveFormat || 'm3u8';
      return acc.server + '/' + path + '/' + encodeURIComponent(acc.user) + '/' +
        encodeURIComponent(acc.pass) + '/' + id + '.' + (ext || 'mp4');
    }
  };

  function b64(s) {
    if (!s) return '';
    try { return decodeURIComponent(escape(atob(s))); } catch (e) { return s; }
  }
})();
