/* المشغل: HTML5 video + hls.js للتلفزيون/المتصفح، و ExoPlayer أصلي داخل تطبيق الأندرويد */
var Player = (function () {
  var $ = function (id) { return document.getElementById(id); };
  var video, hls = null, items = [], index = 0, osdTimer = null, saveTimer = null, epgReq = 0;
  var onCloseCb = null, triedFallback = false, nativePlugin = null;

  function nativeAvailable() {
    return Api.isNative ? (nativePlugin || (nativePlugin = window.capacitorExports.registerPlugin('NativePlayer'))) : null;
  }

  function showOsd() {
    $('osd').classList.remove('off');
    $('touch-ctl').classList.remove('off');
    clearTimeout(osdTimer);
    osdTimer = setTimeout(function () {
      if (!video.paused) { $('osd').classList.add('off'); $('touch-ctl').classList.add('off'); }
    }, 4000);
  }

  function fmt(t) {
    if (!isFinite(t)) return '--:--';
    t = Math.floor(t);
    var h = Math.floor(t / 3600), m = Math.floor(t % 3600 / 60), s = t % 60;
    return (h ? h + ':' + (m < 10 ? '0' : '') : '') + m + ':' + (s < 10 ? '0' : '') + s;
  }

  function setErr(msg) {
    var e = $('player-err');
    if (msg) { e.textContent = msg; e.classList.remove('hidden'); }
    else e.classList.add('hidden');
  }
  function spin(on) { $('spinner').classList.toggle('hidden', !on); }

  function destroyHls() {
    if (hls) { try { hls.destroy(); } catch (e) {} hls = null; }
  }

  function isHlsUrl(u) { return /\.m3u8(\?|$)/i.test(u); }

  function load(url) {
    destroyHls();
    setErr('');
    spin(true);
    video.removeAttribute('src');
    try { video.load(); } catch (e) {}

    var nativeHls = video.canPlayType('application/vnd.apple.mpegurl');
    if (isHlsUrl(url) && window.Hls && Hls.isSupported() && !nativeHls) {
      hls = new Hls({ enableWorker: true, lowLatencyMode: false, maxBufferLength: 30 });
      hls.loadSource(Api.viaProxy(url));
      hls.attachMedia(video);
      hls.on(Hls.Events.MANIFEST_PARSED, function () { play(); });
      hls.on(Hls.Events.ERROR, function (_, d) {
        if (!d.fatal) return;
        if (d.type === Hls.ErrorTypes.NETWORK_ERROR) hls.startLoad();
        else if (d.type === Hls.ErrorTypes.MEDIA_ERROR) hls.recoverMediaError();
        else fail('تعذر تشغيل البث');
      });
    } else {
      video.src = url;
      play();
    }
  }

  function play() {
    var p = video.play();
    if (p && p.catch) p.catch(function () {});
  }

  function fail(msg) {
    var it = items[index];
    // لو صيغة البث المباشر لم تعمل نجرب الصيغة الأخرى مرة واحدة
    if (it && it.type === 'live' && !triedFallback) {
      triedFallback = true;
      var alt = /\.ts$/i.test(it.url) ? it.url.replace(/\.ts$/i, '.m3u8') : it.url.replace(/\.m3u8$/i, '.ts');
      load(alt);
      return;
    }
    spin(false);
    setErr(msg || 'تعذر تشغيل المحتوى. جرّب تغيير صيغة البث من الإعدادات.');
  }

  function current() { return items[index]; }

  function start(i) {
    index = (i + items.length) % items.length;
    var it = current();
    triedFallback = false;
    $('osd-title').textContent = (it.num ? it.num + ' · ' : '') + it.title;
    $('osd-epg').textContent = '';
    $('osd-progress').style.width = '0';
    load(it.url);
    showOsd();
    if (it.type === 'live' && it.epg) {
      var req = ++epgReq;
      it.epg().then(function (list) {
        if (req !== epgReq || !list.length) return;
        var n = list[0], nx = list[1];
        $('osd-epg').textContent = 'الآن: ' + n.title + (nx ? '   ·   التالي: ' + nx.title : '');
      }).catch(function () {});
    }
  }

  function seek(d) {
    if (!isFinite(video.duration)) return;
    video.currentTime = Math.max(0, Math.min(video.duration - 1, video.currentTime + d));
    showOsd();
  }

  function toggle() { if (video.paused) play(); else video.pause(); showOsd(); }

  function savePos() {
    var it = current();
    if (!it || !it.posKey || !isFinite(video.duration)) return;
    var t = video.currentTime;
    Store.setPos(it.posKey, (t > 10 && t < video.duration - 30) ? t : 0);
  }

  function close() {
    savePos();
    clearInterval(saveTimer);
    destroyHls();
    video.pause();
    video.removeAttribute('src');
    try { video.load(); } catch (e) {}
    if (document.fullscreenElement && document.exitFullscreen) document.exitFullscreen().catch(function () {});
    $('player').classList.add('hidden');
    if (onCloseCb) onCloseCb();
  }

  function keys(k) {
    var live = current() && current().type === 'live';
    switch (k) {
      case 'BACK': case 'STOP': close(); return true;
      case 'ENTER': case 'PLAYPAUSE': toggle(); return true;
      case 'PLAY': play(); showOsd(); return true;
      case 'PAUSE': video.pause(); showOsd(); return true;
      case 'LEFT': if (live) showOsd(); else seek(-10); return true;
      case 'RIGHT': if (live) showOsd(); else seek(10); return true;
      case 'RW': seek(-30); return true;
      case 'FF': seek(30); return true;
      case 'UP': case 'CH_UP': if (items.length > 1) start(index + 1); else showOsd(); return true;
      case 'DOWN': case 'CH_DOWN': if (items.length > 1) start(index - 1); else showOsd(); return true;
    }
    return true;
  }

  function init() {
    video = $('video');
    video.addEventListener('playing', function () { spin(false); setErr(''); });
    video.addEventListener('waiting', function () { spin(true); });
    video.addEventListener('canplay', function () { spin(false); });
    video.addEventListener('error', function () { if (!hls) fail(); });
    video.addEventListener('loadedmetadata', function () {
      var it = current();
      if (it && it.posKey) {
        var p = Store.getPos(it.posKey);
        if (p > 0 && p < video.duration - 30) video.currentTime = p;
      }
    });
    video.addEventListener('timeupdate', function () {
      var d = video.duration;
      if (isFinite(d) && d > 0) {
        $('osd-progress').style.width = (video.currentTime / d * 100) + '%';
        $('osd-time').textContent = fmt(video.currentTime) + ' / ' + fmt(d);
      } else {
        $('osd-time').textContent = 'مباشر';
      }
    });
    var p = $('player');
    p.addEventListener('mousemove', showOsd);
    p.addEventListener('click', function (e) { if (e.target === video) { showOsd(); } });
    $('touch-ctl').addEventListener('click', function (e) {
      var a = e.target.getAttribute('data-act');
      if (a === 'close') close();
      else if (a === 'toggle') toggle();
      else if (a === 'next') { if (current().type === 'live') start(index + 1); else seek(30); }
      else if (a === 'prev') { if (current().type === 'live') start(index - 1); else seek(-10); }
      else if (a === 'fs') {
        if (document.fullscreenElement) document.exitFullscreen();
        else if (p.requestFullscreen) p.requestFullscreen().catch(function () {});
      }
      showOsd();
    });
  }

  return {
    init: init,
    keys: keys,
    isOpen: function () { return !$('player').classList.contains('hidden'); },
    close: close,
    /* list: [{url, title, type, num, posKey, epg()}] */
    open: function (list, i, onClose) {
      var np = nativeAvailable();
      if (np) {
        var it = list[i];
        np.play({
          items: list.map(function (x) { return { url: x.url, title: (x.num ? x.num + ' · ' : '') + x.title }; }),
          index: i,
          live: it.type === 'live',
          startPosition: it.posKey ? Store.getPos(it.posKey) * 1000 : 0
        }).then(function (res) {
          var last = res && list[res.index != null ? res.index : i];
          if (last && last.posKey && res.position != null) {
            var pos = res.position / 1000, dur = (res.duration || 0) / 1000;
            Store.setPos(last.posKey, (pos > 10 && (!dur || pos < dur - 30)) ? pos : 0);
          }
          if (onClose) onClose();
        }).catch(function (e) { App.toast('خطأ في المشغل: ' + (e && e.message)); });
        return;
      }
      items = list;
      onCloseCb = onClose;
      $('player').classList.remove('hidden');
      clearInterval(saveTimer);
      saveTimer = setInterval(savePos, 15000);
      start(i);
    }
  };
})();
