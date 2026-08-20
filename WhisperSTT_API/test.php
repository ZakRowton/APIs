<?php
declare(strict_types=1);

require_once __DIR__ . DIRECTORY_SEPARATOR . 'lib' . DIRECTORY_SEPARATOR . 'bootstrap.php';

$apiBase = './api/';
$self = htmlspecialchars(basename(__FILE__), ENT_QUOTES, 'UTF-8');
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>NeusWhisper API test</title>
  <style>
    :root { color-scheme: dark; font-family: ui-sans-serif, system-ui, sans-serif; }
    body { margin: 0; background: #0b0d12; color: #e8ecf4; }
    main { max-width: 820px; margin: 0 auto; padding: 1.75rem 1rem 3rem; }
    h1 { font-size: 1.35rem; margin: 0 0 0.25rem; }
    .sub { color: #9aa3b5; margin: 0 0 1.25rem; font-size: 0.92rem; }
    .panel { margin-top: 1rem; padding: 1rem; border: 1px solid rgba(255,255,255,.08); border-radius: 12px; background: rgba(255,255,255,.03); }
    h2 { font-size: 0.95rem; margin: 0 0 0.75rem; color: #c5ccda; }
    label { display: block; font-size: 0.82rem; margin-bottom: 0.3rem; color: #aeb7c8; }
    input[type=file], input[type=text] { width: 100%; margin-bottom: 0.75rem; box-sizing: border-box; }
    .row { display: flex; flex-wrap: wrap; gap: 0.5rem; margin-bottom: 0.75rem; }
    button { border: 0; border-radius: 10px; padding: 0.55rem 0.95rem; background: #4f8fd9; color: #fff; font-weight: 600; cursor: pointer; }
    button.secondary { background: rgba(255,255,255,.08); color: #dce3ef; }
    button:disabled { opacity: 0.5; cursor: wait; }
    pre { white-space: pre-wrap; word-break: break-word; background: #05070b; border-radius: 10px; padding: 0.85rem; font-size: 0.82rem; line-height: 1.45; min-height: 3rem; margin: 0.5rem 0 0; }
    .meta { font-size: 0.8rem; color: #8b95a8; margin-top: 0.35rem; }
    .ok { color: #7dcea0; }
    .bad { color: #f1948a; }
    code { font-size: 0.85em; background: rgba(255,255,255,.06); padding: 0.1rem 0.35rem; border-radius: 4px; }
  </style>
</head>
<body>
  <main>
    <h1>NeusWhisper API test</h1>
    <p class="sub">Calls <code><?= htmlspecialchars($apiBase, ENT_QUOTES, 'UTF-8') ?>health.php</code> and <code>transcribe.php</code> from <?= $self ?>.</p>

    <div class="panel">
      <h2>1. Health</h2>
      <div class="row">
        <button type="button" id="btn-health">GET health</button>
      </div>
      <pre id="health-out">Click “GET health” or wait for auto-check…</pre>
    </div>

    <div class="panel">
      <h2>2. Transcribe</h2>
      <label for="file">Audio file</label>
      <input id="file" type="file" accept="audio/*,video/webm" />

      <label for="language">Language (optional)</label>
      <input id="language" type="text" placeholder="en" />

      <label for="api-key">X-Api-Key (optional, if configured)</label>
      <input id="api-key" type="text" placeholder="Leave blank if NEUS_WHISPER_API_KEY is unset" autocomplete="off" />

      <div class="row">
        <button type="button" id="btn-transcribe">POST transcribe</button>
        <button type="button" class="secondary" id="btn-clear">Clear</button>
      </div>
      <p id="status" class="meta"></p>
      <pre id="json-out">Raw JSON response appears here.</pre>
      <pre id="text-out">Transcription text appears here.</pre>
    </div>
  </main>

  <script>
    const API_BASE = <?= json_encode($apiBase, JSON_UNESCAPED_SLASHES) ?>;

    function apiUrl(path) {
      return new URL(path, new URL(API_BASE, window.location.href)).href;
    }

    function headers() {
      const h = {};
      const key = document.getElementById('api-key').value.trim();
      if (key) h['X-Api-Key'] = key;
      return h;
    }

    function setStatus(msg, ok) {
      const el = document.getElementById('status');
      el.textContent = msg;
      el.className = 'meta ' + (ok === true ? 'ok' : ok === false ? 'bad' : '');
    }

    async function callHealth() {
      const out = document.getElementById('health-out');
      out.textContent = 'Loading…';
      try {
        const started = performance.now();
        const res = await fetch(apiUrl('health.php'), { headers: headers() });
        const body = await res.text();
        const ms = Math.round(performance.now() - started);
        let pretty = body;
        try { pretty = JSON.stringify(JSON.parse(body), null, 2); } catch (_) {}
        out.textContent = `HTTP ${res.status} (${ms}ms)\n\n${pretty}`;
      } catch (err) {
        out.textContent = 'Request failed: ' + (err?.message || err);
      }
    }

    async function callTranscribe() {
      const fileInput = document.getElementById('file');
      const jsonOut = document.getElementById('json-out');
      const textOut = document.getElementById('text-out');
      const btn = document.getElementById('btn-transcribe');

      if (!fileInput.files?.length) {
        setStatus('Choose an audio file first.', false);
        return;
      }

      const form = new FormData();
      form.append('file', fileInput.files[0]);
      const language = document.getElementById('language').value.trim();
      if (language) form.append('language', language);

      btn.disabled = true;
      setStatus('Uploading and transcribing…', null);
      jsonOut.textContent = '';
      textOut.textContent = '';

      try {
        const started = performance.now();
        const res = await fetch(apiUrl('transcribe.php'), {
          method: 'POST',
          headers: headers(),
          body: form,
        });
        const body = await res.text();
        const ms = Math.round(performance.now() - started);
        let data;
        try {
          data = JSON.parse(body);
        } catch (_) {
          throw new Error('Non-JSON response: ' + body.slice(0, 300));
        }

        jsonOut.textContent = JSON.stringify(data, null, 2);
        if (data.ok && data.text) {
          textOut.textContent = data.text;
          setStatus(`OK · ${data.source || 'unknown'} · HTTP ${res.status} · ${ms}ms`, true);
        } else {
          textOut.textContent = '';
          setStatus((data.error || `HTTP ${res.status}`) + ` · ${ms}ms`, false);
        }
      } catch (err) {
        setStatus(err?.message || String(err), false);
      } finally {
        btn.disabled = false;
      }
    }

    document.getElementById('btn-health').addEventListener('click', callHealth);
    document.getElementById('btn-transcribe').addEventListener('click', callTranscribe);
    document.getElementById('btn-clear').addEventListener('click', () => {
      document.getElementById('json-out').textContent = 'Raw JSON response appears here.';
      document.getElementById('text-out').textContent = 'Transcription text appears here.';
      setStatus('', null);
    });

    callHealth();
  </script>
</body>
</html>
