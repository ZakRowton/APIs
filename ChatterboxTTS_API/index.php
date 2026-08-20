<?php
declare(strict_types=1);

$configExists = is_file(__DIR__ . '/config.php');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Chatterbox TTS Demo | Resemble AI</title>
    <style>
        :root {
            color-scheme: light dark;
            --bg: #0f1117;
            --panel: #171b26;
            --panel-2: #1f2433;
            --text: #eef2ff;
            --muted: #9aa4bf;
            --accent: #7c5cff;
            --accent-2: #35d0ba;
            --danger: #ff6b6b;
            --border: rgba(255, 255, 255, 0.08);
            --shadow: 0 24px 80px rgba(0, 0, 0, 0.35);
        }

        * { box-sizing: border-box; }

        body {
            margin: 0;
            min-height: 100vh;
            font-family: Inter, Segoe UI, Roboto, Helvetica, Arial, sans-serif;
            background:
                radial-gradient(circle at top left, rgba(124, 92, 255, 0.18), transparent 30%),
                radial-gradient(circle at top right, rgba(53, 208, 186, 0.12), transparent 28%),
                var(--bg);
            color: var(--text);
        }

        .wrap {
            width: min(960px, calc(100% - 32px));
            margin: 0 auto;
            padding: 48px 0 64px;
        }

        header {
            margin-bottom: 28px;
        }

        .badge {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 6px 12px;
            border-radius: 999px;
            background: rgba(124, 92, 255, 0.15);
            color: #d9ccff;
            font-size: 13px;
            letter-spacing: 0.02em;
        }

        h1 {
            margin: 16px 0 8px;
            font-size: clamp(2rem, 4vw, 3rem);
            line-height: 1.05;
        }

        .lead {
            margin: 0;
            max-width: 62ch;
            color: var(--muted);
            line-height: 1.6;
        }

        .grid {
            display: grid;
            grid-template-columns: 1.4fr 0.9fr;
            gap: 20px;
        }

        @media (max-width: 860px) {
            .grid { grid-template-columns: 1fr; }
        }

        .card {
            background: linear-gradient(180deg, rgba(255,255,255,0.02), transparent), var(--panel);
            border: 1px solid var(--border);
            border-radius: 20px;
            padding: 22px;
            box-shadow: var(--shadow);
        }

        label {
            display: block;
            font-size: 14px;
            font-weight: 600;
            margin-bottom: 8px;
        }

        textarea, select, input[type="text"] {
            width: 100%;
            border: 1px solid var(--border);
            background: var(--panel-2);
            color: var(--text);
            border-radius: 14px;
            padding: 14px 16px;
            font: inherit;
            resize: vertical;
        }

        textarea {
            min-height: 180px;
            line-height: 1.5;
        }

        .controls {
            display: grid;
            gap: 18px;
        }

        .slider-row {
            display: grid;
            gap: 8px;
        }

        .slider-meta {
            display: flex;
            justify-content: space-between;
            color: var(--muted);
            font-size: 13px;
        }

        input[type="range"] {
            width: 100%;
            accent-color: var(--accent);
        }

        .actions {
            display: flex;
            flex-wrap: wrap;
            gap: 12px;
            margin-top: 18px;
        }

        button {
            border: 0;
            border-radius: 14px;
            padding: 13px 18px;
            font: inherit;
            font-weight: 600;
            cursor: pointer;
            transition: transform 0.15s ease, opacity 0.15s ease;
        }

        button:hover:not(:disabled) { transform: translateY(-1px); }
        button:disabled { opacity: 0.55; cursor: not-allowed; }

        .primary {
            background: linear-gradient(135deg, var(--accent), #5b8cff);
            color: white;
        }

        .secondary {
            background: rgba(255,255,255,0.06);
            color: var(--text);
        }

        .status {
            margin-top: 16px;
            padding: 12px 14px;
            border-radius: 12px;
            font-size: 14px;
            line-height: 1.5;
            background: rgba(255,255,255,0.04);
            border: 1px solid var(--border);
            color: var(--muted);
        }

        .status.error {
            color: #ffd0d0;
            border-color: rgba(255, 107, 107, 0.35);
            background: rgba(255, 107, 107, 0.08);
        }

        .status.success {
            color: #c8fff4;
            border-color: rgba(53, 208, 186, 0.35);
            background: rgba(53, 208, 186, 0.08);
        }

        audio {
            width: 100%;
            margin-top: 16px;
        }

        .setup {
            margin-top: 20px;
            font-size: 14px;
            color: var(--muted);
            line-height: 1.6;
        }

        .setup code, .hint code {
            background: rgba(255,255,255,0.06);
            padding: 2px 6px;
            border-radius: 6px;
        }

        .hint {
            margin-top: 14px;
            padding: 14px 16px;
            border-radius: 14px;
            background: rgba(124, 92, 255, 0.08);
            border: 1px solid rgba(124, 92, 255, 0.18);
            color: #ddd4ff;
            font-size: 14px;
            line-height: 1.55;
        }

        .pill {
            display: inline-block;
            padding: 4px 10px;
            border-radius: 999px;
            font-size: 12px;
            font-weight: 700;
            letter-spacing: 0.03em;
            text-transform: uppercase;
        }

        .pill.offline { background: rgba(255,107,107,0.15); color: #ffb4b4; }
        .pill.online { background: rgba(53,208,186,0.15); color: #9ef7e8; }
        .pill.unknown { background: rgba(255,255,255,0.08); color: var(--muted); }
    </style>
</head>
<body>
    <div class="wrap">
        <header>
            <div class="badge">Resemble AI · Chatterbox</div>
            <h1>Text to Speech Demo</h1>
            <p class="lead">
                Generate expressive speech with Chatterbox, Resemble AI's open-source TTS model.
                This page sends text to your hosted Chatterbox API through PHP, then plays the returned WAV audio in your browser.
            </p>
        </header>

        <div class="grid">
            <section class="card">
                <label for="text">Text to speak</label>
                <textarea id="text" maxlength="2000" placeholder="Type something for Chatterbox to read aloud...">Hello from Chatterbox Turbo.</textarea>

                <div class="actions">
                    <button class="primary" id="generateBtn" type="button" disabled>Generate speech</button>
                    <button class="secondary" id="previewBtn" type="button">Instant preview</button>
                    <button class="secondary" id="sampleBtn" type="button">Load sample text</button>
                    <button class="secondary" id="downloadBtn" type="button" disabled>Download WAV</button>
                </div>

                <div class="status" id="status">
                    <?php if (!$configExists): ?>
                        Configuration file missing. Copy <code>config.example.php</code> to <code>config.php</code>, then start the Python server in <code>server/tts_server.py</code>.
                    <?php else: ?>
                        Checking Chatterbox API status...
                    <?php endif; ?>
                </div>

                <audio id="player" controls></audio>
            </section>

            <aside class="card controls">
                <div>
                    <label for="voice">Voice name</label>
                    <input id="voice" type="text" value="default" placeholder="default">
                </div>

                <div class="slider-row" id="exaggerationRow">
                    <div class="slider-meta">
                        <span>Exaggeration</span>
                        <span id="exaggerationValue">0.50</span>
                    </div>
                    <input id="exaggeration" type="range" min="0" max="2" step="0.05" value="0.5">
                    <small class="slider-meta">Lower = calmer, higher = more expressive</small>
                </div>

                <div class="slider-row" id="cfgRow">
                    <div class="slider-meta">
                        <span>CFG weight</span>
                        <span id="cfgValue">0.50</span>
                    </div>
                    <input id="cfgWeight" type="range" min="0" max="1" step="0.05" value="0.5">
                    <small class="slider-meta">Guides pacing and delivery style</small>
                </div>

                <div>
                    <label>Engine</label>
                    <div id="modelInfo" class="slider-meta">Checking model...</div>
                </div>

                <div>
                    <label>API status</label>
                    <div id="apiStatus" class="pill unknown">Checking...</div>
                </div>

                <div class="setup">
                    <strong>Hosted API</strong>
                    <ol>
                        <li>API server: <code>http://187.77.5.103:4123</code></li>
                        <li>Open <code>http://localhost/Chatterbox/</code> in your browser.</li>
                    </ol>
                </div>
            </aside>
        </div>

        <div class="hint">
            This demo uses <strong>Chatterbox Turbo</strong> for faster CPU inference. Restart the API with
            <code>CHATTERBOX_MODEL=standard</code> if you need exaggeration/CFG controls from the original model.
        </div>
    </div>

    <script>
        const textEl = document.getElementById('text');
        const voiceEl = document.getElementById('voice');
        const exaggerationEl = document.getElementById('exaggeration');
        const cfgWeightEl = document.getElementById('cfgWeight');
        const exaggerationValueEl = document.getElementById('exaggerationValue');
        const cfgValueEl = document.getElementById('cfgValue');
        const generateBtn = document.getElementById('generateBtn');
        const previewBtn = document.getElementById('previewBtn');
        const sampleBtn = document.getElementById('sampleBtn');
        const downloadBtn = document.getElementById('downloadBtn');
        const statusEl = document.getElementById('status');
        const apiStatusEl = document.getElementById('apiStatus');
        const modelInfoEl = document.getElementById('modelInfo');
        const exaggerationRowEl = document.getElementById('exaggerationRow');
        const cfgRowEl = document.getElementById('cfgRow');
        const playerEl = document.getElementById('player');

        let latestBlobUrl = null;
        let latestBlob = null;
        let modelReady = false;
        let healthTimer = null;
        let generationTimer = null;

        function setGenerateEnabled(enabled) {
            generateBtn.disabled = !enabled;
        }

        function startGenerationTimer() {
            const started = performance.now();
            generationTimer = window.setInterval(() => {
                const seconds = ((performance.now() - started) / 1000).toFixed(1);
                setStatus(`Generating with Chatterbox Turbo... ${seconds}s elapsed`);
            }, 200);
        }

        function stopGenerationTimer() {
            if (generationTimer !== null) {
                window.clearInterval(generationTimer);
                generationTimer = null;
            }
        }

        function setStatus(message, type = '') {
            statusEl.textContent = message;
            statusEl.className = 'status' + (type ? ` ${type}` : '');
        }

        function setApiStatus(state, label) {
            apiStatusEl.className = `pill ${state}`;
            apiStatusEl.textContent = label;
        }

        function updateSliderLabels() {
            exaggerationValueEl.textContent = Number(exaggerationEl.value).toFixed(2);
            cfgValueEl.textContent = Number(cfgWeightEl.value).toFixed(2);
        }

        async function checkHealth() {
            try {
                const response = await fetch('api/health.php');
                const data = await response.json();

                if (!data.configured) {
                    setApiStatus('offline', 'Not configured');
                    setStatus(data.message, 'error');
                    return;
                }

                if (data.api_reachable === true) {
                    const detail = data.detail || {};
                    const model = detail.model || 'unknown';
                    const ready = detail.model_ready === true;
                    const device = detail.device || 'cpu';

                    modelReady = ready;
                    setApiStatus(ready ? 'online' : 'unknown', ready ? 'Online' : 'Loading');
                    modelInfoEl.textContent = `${model} on ${device}${ready ? '' : ' (warming up)'}`;
                    setGenerateEnabled(ready);

                    const turboMode = model === 'turbo';
                    exaggerationRowEl.style.display = turboMode ? 'none' : 'grid';
                    cfgRowEl.style.display = turboMode ? 'none' : 'grid';

                    setStatus(
                        ready
                            ? 'Turbo is ready. Short sentences take about 10–20s on CPU; use Instant preview for immediate playback.'
                            : 'Model is loading in the API window. Wait for "Model ready", then generate.',
                        ready ? 'success' : ''
                    );

                    if (!ready && healthTimer === null) {
                        healthTimer = window.setInterval(checkHealth, 3000);
                    }
                    if (ready && healthTimer !== null) {
                        window.clearInterval(healthTimer);
                        healthTimer = null;
                    }
                } else if (data.api_reachable === false) {
                    setApiStatus('offline', 'Offline');
                    setStatus('Cannot reach the hosted API at 187.77.5.103:4123. Check that the VPS container is running.', 'error');
                    setGenerateEnabled(false);
                } else {
                    setApiStatus('unknown', 'Unknown');
                    setStatus(data.message);
                }
            } catch (error) {
                setApiStatus('offline', 'Offline');
                setStatus('Could not check API health. Is Apache running?', 'error');
            }
        }

        async function generateSpeech() {
            const text = textEl.value.trim();
            if (!text) {
                setStatus('Please enter some text first.', 'error');
                return;
            }

            if (!modelReady) {
                setStatus('Wait for the model to finish loading in start-chatterbox-api.bat.', 'error');
                return;
            }

            generateBtn.disabled = true;
            downloadBtn.disabled = true;
            startGenerationTimer();

            try {
                const response = await fetch('api/synthesize.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        text,
                        voice: voiceEl.value.trim() || 'default',
                        exaggeration: Number(exaggerationEl.value),
                        cfg_weight: Number(cfgWeightEl.value),
                    }),
                });

                const data = await response.json();
                if (!response.ok) {
                    const hint = data.hint ? ` ${data.hint}` : '';
                    throw new Error((data.error || 'Speech generation failed.') + hint);
                }

                const binary = atob(data.audio_base64);
                const bytes = new Uint8Array(binary.length);
                for (let i = 0; i < binary.length; i += 1) {
                    bytes[i] = binary.charCodeAt(i);
                }

                latestBlob = new Blob([bytes], { type: data.content_type || 'audio/wav' });
                if (latestBlobUrl) {
                    URL.revokeObjectURL(latestBlobUrl);
                }
                latestBlobUrl = URL.createObjectURL(latestBlob);

                playerEl.src = latestBlobUrl;
                await playerEl.play().catch(() => {});
                downloadBtn.disabled = false;
                const timing = data.generation_seconds
                    ? ` in ${Number(data.generation_seconds).toFixed(1)}s`
                    : '';
                setStatus(`Speech generated (${data.text_length} characters)${timing}.`, 'success');
            } catch (error) {
                setStatus(error.message || 'Unexpected error during synthesis.', 'error');
            } finally {
                stopGenerationTimer();
                setGenerateEnabled(modelReady);
            }
        }

        function previewSpeech() {
            const text = textEl.value.trim();
            if (!text) {
                setStatus('Please enter some text first.', 'error');
                return;
            }

            window.speechSynthesis.cancel();
            const utterance = new SpeechSynthesisUtterance(text);
            utterance.rate = 1;
            utterance.onstart = () => setStatus('Playing instant browser preview (not Chatterbox voice)...', 'success');
            utterance.onend = () => setStatus('Preview finished. Click Generate speech for Chatterbox audio.', 'success');
            utterance.onerror = () => setStatus('Browser preview failed on this device.', 'error');
            window.speechSynthesis.speak(utterance);
        }

        function loadSampleText() {
            textEl.value = 'Welcome to Chatterbox Turbo. This model is optimized for faster speech on CPU.';
        }

        function downloadAudio() {
            if (!latestBlob) return;
            const link = document.createElement('a');
            link.href = latestBlobUrl;
            link.download = 'chatterbox-speech.wav';
            link.click();
        }

        exaggerationEl.addEventListener('input', updateSliderLabels);
        cfgWeightEl.addEventListener('input', updateSliderLabels);
        generateBtn.addEventListener('click', generateSpeech);
        previewBtn.addEventListener('click', previewSpeech);
        sampleBtn.addEventListener('click', loadSampleText);
        downloadBtn.addEventListener('click', downloadAudio);

        updateSliderLabels();
        checkHealth();
    </script>
</body>
</html>
