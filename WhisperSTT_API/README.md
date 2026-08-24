# Whisper Speech To Text API

Local speech-to-text API for NEUS development. Wraps [whisper.cpp](https://github.com/ggml-org/whisper.cpp) with a PHP JSON endpoint and a small JavaScript test page.

Runs locally on Windows/XAMPP for development, or as a two-container Docker stack on a Hostinger VPS (deployed straight from GitHub — see [Hostinger VPS deploy](#hostinger-vps-deploy)).

## Layout

```
NeusWhisper/
  api/transcribe.php   POST multipart audio → JSON transcription
  api/health.php       GET service status
  lib/                 PHP client (proxy to whisper-server or whisper-cli)
  public/              Browser test UI (HTML + JS)
  scripts/             Windows setup + start helpers
  whisper.cpp/         Cloned by setup (not committed)
  models/              ggml model weights
  runtime/             Temp uploads / job files
```

## Prerequisites (Windows)

- Git, CMake, Visual Studio Build Tools (C++)
- PHP 8+ with `curl` enabled (XAMPP is fine)
- `ffmpeg` on PATH (for mp3/webm/m4a when using CLI fallback)
- Optional: Node.js for `server.mjs` static UI

## Setup

```powershell
cd c:\xampp\htdocs\Repos\_workspace\NeusWhisper
powershell -ExecutionPolicy Bypass -File .\scripts\setup-whisper.ps1
powershell -ExecutionPolicy Bypass -File .\scripts\start-server.ps1
```

Copy `config.example.env` → `config.local.env` if setup did not create it.

## API

### `POST /api/transcribe.php`

`multipart/form-data`:

| Field | Required | Description |
|-------|----------|-------------|
| `file` | yes | Audio file (wav, mp3, m4a, ogg, webm, flac, …) |
| `language` | no | e.g. `en` or `auto` |
| `prompt` | no | Initial prompt for whisper |
| `temperature` | no | Default `0.0` |

**Response**

```json
{
  "ok": true,
  "text": "Hello world",
  "segments": [],
  "source": "whisper-server",
  "filename": "clip.webm"
}
```

Optional auth: set `NEUS_WHISPER_API_KEY` in `config.local.env`, send header `X-Api-Key`.

### `GET /api/health.php`

Returns model path, whisper-server reachability, and whisper-cli path.

## Example (curl)

```bash
curl -X POST "http://localhost/Repos/_workspace/NeusWhisper/api/transcribe.php" \
  -F "file=@sample.wav" \
  -F "language=en"
```

## Test UI

Open in browser (XAMPP):

`http://localhost/Repos/_workspace/NeusWhisper/public/`

Or run Node static server:

```bash
node server.mjs --port 8787
```

## How it works

1. **Preferred:** PHP forwards uploads to `whisper-server` (`/inference`) when `scripts/start-server.ps1` is running.
2. **Fallback:** PHP converts audio to 16 kHz mono WAV with ffmpeg and runs `whisper-cli -oj`.

## Security

- Bind whisper-server to `127.0.0.1` only (default).
- Do not expose this stack to the public internet without auth and sandboxing.
- Upload size capped by `NEUS_WHISPER_MAX_UPLOAD_BYTES` (default 25 MB).

## Auto-start whisper-server

Set `NEUS_WHISPER_AUTO_START=1` in `config.local.env` (default on Windows/XAMPP). The PHP API calls `WhisperServerSupervisor` on health/transcribe requests and runs `scripts/start-server.ps1` or `start-server.sh` if the server is down.

On **Hostinger Docker**, set `NEUS_WHISPER_AUTO_START=0` — `compose.hostinger.yaml` runs whisper-server with `restart: unless-stopped`.

## Hostinger VPS deploy

Requires a **Hostinger VPS with Docker** (shared PHP-only hosting cannot run whisper.cpp). The whisper model (~148 MB for `base.en`) downloads automatically on first boot and persists in the `models/` volume.

| Service | Role |
|---------|------|
| `whisper` | Thin layer over `ghcr.io/ggml-org/whisper.cpp:main` that downloads the model on first boot and runs whisper-server |
| `web` | PHP Apache on port **8934** (override via `.env`) |

### Option A — deploy from GitHub directly on the VPS

SSH into the VPS, then:

```bash
git clone https://github.com/ZakRowton/NeusWhisper.git
cd NeusWhisper
docker compose -f compose.hostinger.yaml up -d --build
```

That's it — no config files needed. The stack auto-restarts on reboot (`restart: unless-stopped`). To update later: `git pull && docker compose -f compose.hostinger.yaml up -d --build`.

**Optional overrides** — create a `.env` file in the repo root (Docker Compose auto-loads it):

```env
NEUS_WHISPER_HTTP_PORT=8934
NEUS_WHISPER_API_KEY=your-secret-key
NEUS_WHISPER_MAX_UPLOAD_BYTES=26214400
NEUS_WHISPER_MODEL=ggml-base.en.bin
```

### Option B — push-to-deploy from GitHub Actions

`.github/workflows/deploy-hostinger.yml` redeploys on every push to `main`. Add these repo secrets (**Settings → Secrets and variables → Actions**):

| Secret | Value |
|--------|-------|
| `HOSTINGER_SSH_HOST` | VPS hostname or IP |
| `HOSTINGER_SSH_USER` | SSH user (e.g. `root`) |
| `HOSTINGER_SSH_KEY` | private SSH key with VPS access |
| `HOSTINGER_SSH_PORT` | (optional) SSH port, default `22` |
| `HOSTINGER_REMOTE_DIR` | (optional) checkout dir, default `/root/neuswhisper` |

Without `HOSTINGER_SSH_HOST`, the workflow is a no-op.

### Option C — deploy from your machine

```powershell
copy deploy.hostinger.env.example deploy.hostinger.env
# edit HOSTINGER_SSH_* , HOSTINGER_REMOTE_DIR , NEUS_WHISPER_REPO_*
powershell -ExecutionPolicy Bypass -File .\scripts\deploy-hostinger.ps1
```

Or on Linux/macOS:

```bash
chmod +x scripts/deploy-hostinger.sh
./scripts/deploy-hostinger.sh
```

This SSHes into the VPS, pulls the repo from GitHub, and runs `docker compose up -d --build`.

### URLs on VPS

- Health: `http://YOUR_VPS:8934/api/health.php`
- Transcribe: `POST http://YOUR_VPS:8934/api/transcribe.php`
- Test UI: `http://YOUR_VPS:8934/test.php`

### Bare-metal Linux (no Docker)

```bash
./scripts/setup-whisper.sh
sudo cp systemd/neus-whisper-server.service /etc/systemd/system/
sudo systemctl enable --now neus-whisper-server
```

Point Apache at `public/` and keep `WHISPER_SERVER_HOST=127.0.0.1`.
