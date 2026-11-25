#!/usr/bin/env node
/*
 * سيرفر صغير بدون أي مكتبات خارجية:
 *  1) يقدّم ملفات التطبيق (مجلد www) لشاشة VIDAA على الشبكة المحلية
 *  2) بروكسي /proxy?url=... لتجاوز مشكلة CORS في متصفح التلفزيون
 *     (ويعيد كتابة روابط ملفات m3u8 لتمر عبر البروكسي أيضاً)
 *
 * التشغيل:  node proxy/server.js            (المنفذ الافتراضي 8080)
 *           PORT=9000 node proxy/server.js
 */
const http = require('http');
const fs = require('fs');
const path = require('path');
const os = require('os');
const { Readable } = require('stream');

const PORT = +process.env.PORT || 8080;
const ROOT = path.resolve(__dirname, '..', 'www');
const UA = process.env.UA || 'Mozilla/5.0 (SMART-TV; Linux; Tizen 5.0) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/85.0 Safari/537.36';

const MIME = {
  '.html': 'text/html; charset=utf-8', '.js': 'application/javascript; charset=utf-8',
  '.css': 'text/css; charset=utf-8', '.json': 'application/json', '.png': 'image/png',
  '.jpg': 'image/jpeg', '.svg': 'image/svg+xml', '.ico': 'image/x-icon', '.map': 'application/json'
};

function cors(res) {
  res.setHeader('Access-Control-Allow-Origin', '*');
  res.setHeader('Access-Control-Allow-Headers', '*');
  res.setHeader('Access-Control-Allow-Methods', 'GET, HEAD, OPTIONS');
  res.setHeader('Access-Control-Expose-Headers', 'Content-Length, Content-Range, Accept-Ranges');
}

function proxyBase(req) {
  return 'http://' + req.headers.host + '/proxy?url=';
}

function rewriteM3u8(text, baseUrl, req) {
  const pb = proxyBase(req);
  const abs = (u) => { try { return new URL(u, baseUrl).href; } catch (e) { return u; } };
  return text.split(/\r?\n/).map((line) => {
    const t = line.trim();
    if (!t) return line;
    if (t[0] === '#') {
      return line.replace(/URI="([^"]+)"/g, (_, u) => 'URI="' + pb + encodeURIComponent(abs(u)) + '"');
    }
    return pb + encodeURIComponent(abs(t));
  }).join('\n');
}

async function handleProxy(req, res, target) {
  if (!/^https?:\/\//i.test(target)) { res.writeHead(400); return res.end('bad url'); }
  const headers = { 'User-Agent': UA, 'Accept': '*/*' };
  if (req.headers.range) headers.Range = req.headers.range;
  const ac = new AbortController();
  req.on('close', () => ac.abort());
  let up;
  try {
    up = await fetch(target, { headers, redirect: 'follow', signal: ac.signal });
  } catch (e) {
    res.writeHead(502); return res.end('upstream error: ' + e.message);
  }
  const ct = up.headers.get('content-type') || '';
  const finalUrl = up.url || target;
  const isM3u8 = /mpegurl/i.test(ct) || /\.m3u8(\?|$)/i.test(new URL(finalUrl).pathname + new URL(finalUrl).search);

  if (isM3u8 && up.ok) {
    const txt = await up.text();
    res.writeHead(200, { 'Content-Type': 'application/vnd.apple.mpegurl', 'Cache-Control': 'no-cache' });
    return res.end(rewriteM3u8(txt, finalUrl, req));
  }

  const out = {};
  ['content-type', 'content-length', 'content-range', 'accept-ranges', 'cache-control'].forEach((h) => {
    const v = up.headers.get(h);
    if (v) out[h] = v;
  });
  res.writeHead(up.status, out);
  if (!up.body || req.method === 'HEAD') return res.end();
  Readable.fromWeb(up.body).on('error', () => res.destroy()).pipe(res);
}

function serveStatic(req, res, pathname) {
  let p = decodeURIComponent(pathname);
  if (p === '/' || p === '') p = '/index.html';
  const file = path.normalize(path.join(ROOT, p));
  if (!file.startsWith(ROOT)) { res.writeHead(403); return res.end(); }
  fs.stat(file, (err, st) => {
    if (err || !st.isFile()) { res.writeHead(404); return res.end('not found'); }
    res.writeHead(200, { 'Content-Type': MIME[path.extname(file)] || 'application/octet-stream', 'Content-Length': st.size });
    fs.createReadStream(file).pipe(res);
  });
}

http.createServer((req, res) => {
  cors(res);
  if (req.method === 'OPTIONS') { res.writeHead(204); return res.end(); }
  const u = new URL(req.url, 'http://x');
  if (u.pathname === '/__xp') {
    res.writeHead(200, { 'Content-Type': 'application/json' });
    return res.end('{"proxy":true}');
  }
  if (u.pathname === '/proxy') {
    return handleProxy(req, res, u.searchParams.get('url') || '').catch((e) => {
      if (!res.headersSent) res.writeHead(500);
      res.end(String(e));
    });
  }
  serveStatic(req, res, u.pathname);
}).listen(PORT, '0.0.0.0', () => {
  const ips = [];
  Object.values(os.networkInterfaces()).forEach((l) => (l || []).forEach((i) => {
    if (i.family === 'IPv4' && !i.internal) ips.push(i.address);
  }));
  console.log('Xtream Player يعمل الآن:');
  (ips.length ? ips : ['localhost']).forEach((ip) => console.log('  افتح على التلفزيون:  http://' + ip + ':' + PORT));
  console.log('  ورابط البروكسي في الإعدادات هو نفس الرابط.');
});
