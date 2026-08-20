# APIs

Neus API collection — speech-to-text and text-to-speech services with OpenAI-compatible APIs.

## Projects

### [ChatterboxTTS_API](ChatterboxTTS_API/) — Text-to-Speech

OpenAI-compatible TTS API powered by [Kokoro-82M](https://huggingface.co/hexgrad/Kokoro-82M) (ONNX Runtime).

- 54 natural-sounding voices across 8 languages
- Runs on CPU (faster than real-time, no GPU needed)
- 183 MB model, loads in ~3 seconds
- 2 GB RAM minimum
- PHP demo UI included

```bash
# Start the API server
cd ChatterboxTTS_API/server
python tts_server.py

# Generate speech
curl -X POST http://127.0.0.1:4123/v1/audio/speech \
  -H "Content-Type: application/json" \
  -d '{"input":"Hello world","voice":"af_heart"}' \
  --output speech.wav
```

See [ChatterboxTTS_API/README.md](ChatterboxTTS_API/README.md) for full docs.

### [WhisperSTT_API](WhisperSTT_API/) — Speech-to-Text

Speech transcription API powered by [whisper.cpp](https://github.com/ggerganov/whisper.cpp).

- Local speech-to-text using whisper.cpp
- Docker deployment for Hostinger VPS
- PHP client library included
- Web UI for recording and transcription

See [WhisperSTT_API/README.md](WhisperSTT_API/README.md) for full docs.

## Architecture

```
APIs/
├── ChatterboxTTS_API/     # Text-to-Speech (Kokoro-82M)
│   ├── server/            # Python Flask API server
│   ├── api/               # PHP proxy for demo UI
│   ├── index.php          # Web demo
│   ├── Dockerfile         # Docker deployment
│   └── DEPLOY.md          # VPS deployment guide
│
└── WhisperSTT_API/       # Speech-to-Text (whisper.cpp)
    ├── server.mjs         # Node.js transcription server
    ├── lib/               # PHP client library
    ├── api/               # PHP API endpoint
    ├── docker/            # Docker containers
    ├── scripts/           # Setup & deployment scripts
    └── public/            # Web UI
```

## License

Each project retains its own license. Kokoro-82M is MIT-licensed.