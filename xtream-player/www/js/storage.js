/* تخزين الحسابات والإعدادات والمفضلة محلياً على الجهاز */
var Store = (function () {
  function get(key, def) {
    try {
      var v = localStorage.getItem(key);
      return v == null ? def : JSON.parse(v);
    } catch (e) { return def; }
  }
  function set(key, val) {
    try { localStorage.setItem(key, JSON.stringify(val)); } catch (e) {}
  }

  var settings = get('xp_settings', { liveFormat: 'm3u8', proxy: '' });

  function accId(acc) { return acc.server + '|' + acc.user; }

  return {
    settings: settings,
    saveSettings: function () { set('xp_settings', settings); },

    accounts: function () { return get('xp_accounts', []); },
    addAccount: function (acc) {
      var list = this.accounts().filter(function (a) { return accId(a) !== accId(acc); });
      list.unshift(acc);
      set('xp_accounts', list.slice(0, 8));
    },
    removeAccount: function (acc) {
      set('xp_accounts', this.accounts().filter(function (a) { return accId(a) !== accId(acc); }));
    },
    current: function () { return get('xp_current', null); },
    setCurrent: function (acc) { set('xp_current', acc); },

    /* المفضلة: { "live:123": {type, id, name, icon, ...} } */
    favs: function (acc) { return get('xp_favs_' + accId(acc), {}); },
    toggleFav: function (acc, item) {
      var f = this.favs(acc), k = item.type + ':' + item.id;
      if (f[k]) delete f[k]; else f[k] = item;
      set('xp_favs_' + accId(acc), f);
      return !!f[k];
    },
    isFav: function (acc, type, id) { return !!this.favs(acc)[type + ':' + id]; },

    /* آخر موضع في الأفلام/الحلقات */
    getPos: function (key) { return get('xp_pos', {})[key] || 0; },
    setPos: function (key, sec) {
      var p = get('xp_pos', {});
      if (sec > 0) p[key] = Math.floor(sec); else delete p[key];
      var keys = Object.keys(p);
      if (keys.length > 300) delete p[keys[0]];
      set('xp_pos', p);
    }
  };
})();
