# NeusSpeechAPI

NEUS Speech API — OpenAI-compatible text-to-speech powered by [Kokoro-82M](https://huggingface.co/hexgrad/Kokoro-82M) (MIT license).

Kokoro-82M is a lightweight (183 MB), highly natural TTS model that runs on **CPU** using ONNX Runtime — no PyTorch, no GPU required. It generates speech faster than real-time on modern CPUs with 30+ built-in voices.

> **Upgraded from Chatterbox TTS.** Kokoro produces significantly more natural, human-like speech, loads in ~3 seconds (vs 30+ seconds), needs only 2 GB RAM (vs 8 GB), and has no PyTorch dependency.

## API endpoints

| Method | Path | Description |
|--------|------|-------------|
| GET | `/health` | Service and model status |
| GET | `/v1/models` | OpenAI-compatible model list |
| GET | `/v1/voices` | List all available voices |
| POST | `/v1/audio/speech` | Generate WAV speech from JSON `{ "input": "text" }` |

## Quick start (Docker — recommended for VPS)

```bash
cp .env.example .env
nano .env   # set API_KEY and default voice

chmod +x deploy.sh
./deploy.sh
```

See [DEPLOY.md](DEPLOY.md) for Hostinger VPS setup, Nginx, and SSL.

### Example request

```bash
curl -X POST http://YOUR_HOST:4123/v1/audio/speech \
  -H "Authorization: Bearer ***" \
  -H "Content-Type: application/json" \
  -d '{"input":"Hello from NeusSpeechAPI.","voice":"af_heart"}' \
  --output speech.wav
```

### List available voices

```bash
curl http://YOUR_HOST:4123/v1/voices
```

Popular voices:

| Voice | Gender | Accent | Style |
|-------|--------|--------|-------|
| `af_heart` | Female | American | Warm, natural |
| `af_bella` | Female | American | Soft, friendly |
| `af_nicole` | Female | American | Clear, professional |
| `af_sky` | Female | American | Bright, energetic |
| `am_adam` | Male | American | Deep, steady |
| `am_eric` | Male | American | Narration, calm |
| `am_michael` | Male | American | Conversational |
| `bf_emma` | Female | British | Refined |
| `bm_george` | Male | British | Authoritative |

## Local development (Windows / XAMPP)

1. Install Python deps: `pip install -r server/requirements.txt`
2. Run `start-demo.bat` (starts API + opens browser demo)
3. Open `http://localhost/NeusSpeechAPI/`

The model auto-downloads on first run (~183 MB to `~/.kokoro/`).

## Project layout

```
├── Dockerfile              # Container image (Python 3.11 slim)
├── docker-compose.yml      # VPS deployment
├── deploy.sh               # One-command Linux deploy
├── server/tts_server.py    # Kokoro TTS API server
├── server/requirements.txt # Python deps (kokoro-onnx, flask, waitress)
├── api/                    # PHP proxy for the demo UI
├── index.php               # Demo web UI
└── DEPLOY.md               # VPS deployment guide
```

## Requirements

- **Docker VPS:** 2 GB RAM minimum (was 8 GB with Chatterbox)
- **CPU inference:** faster than real-time on modern CPUs (RTF ~0.6-0.7x)
- **GPU:** not required (Kokoro uses ONNX Runtime, works on CPU)
- **Disk:** ~200 MB for model files (auto-downloaded)

## Comparison: Kokoro vs Chatterbox

| Feature | Kokoro-82M | Chatterbox Turbo |
|---------|-----------|-----------------|
| Model size | 183 MB | ~2+ GB (PyTorch) |
| RAM needed | 1-2 GB | 4-8 GB |
| Load time | ~3 seconds | 30+ seconds |
| CPU inference | Faster than real-time | 10-20s per sentence |
| GPU required | No | No (but much faster with) |
| Dependencies | onnxruntime (14 MB) | torch + torchaudio (~2 GB) |
| Sample rate | 24000 Hz | 24000 Hz |
| Voices | 30+ built-in | Voice cloning via prompt |
| License | MIT | MIT |
| Naturalness | Highly human-like | Robotic (turbo mode) |

## License

Kokoro-82M is MIT-licensed. See https://huggingface.co/hexgrad/Kokoro-82M