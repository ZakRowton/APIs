const apiBase = new URL('../api/', window.location.href);

async function checkHealth() {
  const el = document.getElementById('health');
  try {
    const res = await fetch(new URL('health.php', apiBase));
    const data = await res.json();
    if (!data.ok) throw new Error(data.error || 'Health check failed');
    // Ready to transcribe via the server, or locally via the CLI + model.
    const ready = data.whisper_server_up || (data.model_exists && data.whisper_cli);
    const parts = [
      data.whisper_server_up ? 'whisper-server: up' : 'whisper-server: down',
    ];
    if (!data.whisper_server_up) {
      parts.push(data.model_exists ? 'model: ready' : 'model: missing');
      parts.push(data.whisper_cli ? 'whisper-cli: found' : 'whisper-cli: missing');
    }
    el.textContent = ready ? 'Ready · ' + parts.join(' · ') : parts.join(' · ');
    el.className = 'status ' + (ready ? 'ok' : 'bad');
  } catch (err) {
    el.textContent = 'Health check failed: ' + (err?.message || err);
    el.className = 'status bad';
  }
}

async function transcribe() {
  const fileInput = document.getElementById('file');
  const language = document.getElementById('language').value.trim();
  const message = document.getElementById('message');
  const output = document.getElementById('output');
  const button = document.getElementById('submit');

  if (!fileInput.files?.length) {
    message.textContent = 'Choose an audio file first.';
    message.className = 'status bad';
    return;
  }

  const form = new FormData();
  form.append('file', fileInput.files[0]);
  if (language) form.append('language', language);

  button.disabled = true;

  const maxRetries = 2;
  for (let attempt = 0; attempt <= maxRetries; attempt++) {
    message.textContent = attempt === 0 ? 'Transcribing…' : `STT server busy, retrying (${attempt}/${maxRetries})…`;
    message.className = 'status';
    output.textContent = '';

    try {
      const res = await fetch(new URL('transcribe.php', apiBase), {
        method: 'POST',
        body: form,
      });
      const data = await res.json();

      if (res.status === 502 && data.retryable && attempt < maxRetries) {
        await new Promise(r => setTimeout(r, 800 * (attempt + 1)));
        continue;
      }

      if (!res.ok || !data.ok) {
        throw new Error(data.error || `HTTP ${res.status}`);
      }

      output.textContent = data.text || '(empty)';
      message.textContent = `Done via ${data.source || 'unknown'}.`;
      message.className = 'status ok';
      return;
    } catch (err) {
      if (attempt < maxRetries && (err?.message || '').includes('Failed to fetch')) {
        await new Promise(r => setTimeout(r, 800 * (attempt + 1)));
        continue;
      }
      output.textContent = '';
      message.textContent = err?.message || String(err);
      message.className = 'status bad';
    }
  }
  button.disabled = false;
}

document.getElementById('submit').addEventListener('click', transcribe);
checkHealth();
