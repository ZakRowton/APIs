#!/usr/bin/env node
/**
 * Optional Node dev server: static UI + JSON API on one port.
 * Proxies POST /api/transcribe to PHP when available, otherwise returns setup hints.
 *
 * Usage: node server.mjs [--port 8787]
 */
import http from 'node:http';
import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';
import { spawn } from 'node:child_process';

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const root = __dirname;
const publicDir = path.join(root, 'public');
const port = Number(process.argv.includes('--port') ? process.argv[process.argv.indexOf('--port') + 1] : 8787);

const mime = {
  '.html': 'text/html; charset=utf-8',
  '.js': 'text/javascript; charset=utf-8',
  '.css': 'text/css; charset=utf-8',
  '.json': 'application/json; charset=utf-8',
};

function sendJson(res, status, payload) {
  res.writeHead(status, {
    'Content-Type': 'application/json; charset=utf-8',
    'Access-Control-Allow-Origin': '*',
    'Access-Control-Allow-Methods': 'GET, POST, OPTIONS',
    'Access-Control-Allow-Headers': 'Content-Type, X-Api-Key',
  });
  res.end(JSON.stringify(payload));
}

function readBody(req) {
  return new Promise((resolve, reject) => {
    const chunks = [];
    req.on('data', (c) => chunks.push(c));
    req.on('end', () => resolve(Buffer.concat(chunks)));
    req.on('error', reject);
  });
}

function runPhp(scriptRelative, reqBody, reqHeaders) {
  return new Promise((resolve, reject) => {
    const script = path.join(root, scriptRelative);
    const env = { ...process.env, REQUEST_METHOD: 'POST' };
    const child = spawn('php', ['-f', script], { cwd: root, env });
    let stdout = '';
    let stderr = '';
    child.stdout.on('data', (d) => { stdout += d.toString(); });
    child.stderr.on('data', (d) => { stderr += d.toString(); });
    child.on('error', reject);
    child.on('close', (code) => {
      if (code !== 0) {
        reject(new Error(stderr || stdout || `php exit ${code}`));
        return;
      }
      resolve(stdout);
    });
    if (reqBody?.length) child.stdin.write(reqBody);
    child.stdin.end();
  });
}

const server = http.createServer(async (req, res) => {
  if (req.method === 'OPTIONS') {
    res.writeHead(204, {
      'Access-Control-Allow-Origin': '*',
      'Access-Control-Allow-Methods': 'GET, POST, OPTIONS',
      'Access-Control-Allow-Headers': 'Content-Type, X-Api-Key',
    });
    res.end();
    return;
  }

  const url = new URL(req.url || '/', `http://${req.headers.host}`);

  if (url.pathname === '/api/health') {
    try {
      const out = await runPhp('api/health.php', null, req.headers);
      res.writeHead(200, { 'Content-Type': 'application/json; charset=utf-8', 'Access-Control-Allow-Origin': '*' });
      res.end(out);
    } catch (err) {
      sendJson(res, 500, { ok: false, error: String(err?.message || err) });
    }
    return;
  }

  if (url.pathname === '/api/transcribe' && req.method === 'POST') {
    sendJson(res, 501, {
      ok: false,
      error: 'Use Apache/XAMPP api/transcribe.php for multipart uploads, or POST directly to /api/transcribe.php.',
    });
    return;
  }

  let filePath = url.pathname === '/' ? '/index.html' : url.pathname;
  filePath = path.normalize(path.join(publicDir, filePath));
  if (!filePath.startsWith(publicDir)) {
    sendJson(res, 403, { ok: false, error: 'Forbidden' });
    return;
  }
  if (!fs.existsSync(filePath) || fs.statSync(filePath).isDirectory()) {
    sendJson(res, 404, { ok: false, error: 'Not found' });
    return;
  }
  const ext = path.extname(filePath);
  res.writeHead(200, { 'Content-Type': mime[ext] || 'application/octet-stream' });
  fs.createReadStream(filePath).pipe(res);
});

server.listen(port, '127.0.0.1', () => {
  console.log(`NeusWhisper UI: http://127.0.0.1:${port}/`);
  console.log('API (XAMPP): ../api/transcribe.php');
});
