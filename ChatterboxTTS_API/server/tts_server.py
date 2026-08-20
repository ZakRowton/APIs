#!/usr/bin/env python3
"""
OpenAI-compatible Kokoro TTS API server.

Kokoro-82M is a lightweight, highly natural TTS model that runs on CPU
using ONNX Runtime — no PyTorch or GPU required.

Deploy with Docker:
    docker compose up -d --build

Environment variables:
    KOKORO_MODEL_PATH   path to .onnx model file (auto-downloaded if not set)
    KOKORO_VOICES_PATH  path to voices .bin file (auto-downloaded if not set)
    KOKORO_HOST         bind address (default: 0.0.0.0 in Docker, 127.0.0.1 locally)
    KOKORO_PORT         port (default: 4123)
    KOKORO_DEFAULT_VOICE  default voice name (default: af_heart)
    API_KEY             optional bearer key for production
    CORS_ORIGINS        comma-separated origins or * (default: *)
    WARMUP_ON_START     1 | 0 (default: 1)
"""

from __future__ import annotations

import io
import os
import sys
import time
import threading
import traceback
from functools import wraps
from typing import Any

# Suppress noisy HF warnings
os.environ.setdefault("HF_HUB_DISABLE_SYMLINKS", "1")
os.environ.setdefault("HF_HUB_DISABLE_SYMLINKS_WARNING", "1")
os.environ.setdefault("TQDM_DISABLE", "1")

from flask import Flask, jsonify, request, send_file

app = Flask(__name__)

MODEL = None
SAMPLE_RATE = 24000
MODEL_READY = False
MODEL_ERROR: str | None = None
LOAD_LOCK = threading.Lock()
AVAILABLE_VOICES: list[str] = []

API_KEY = os.environ.get("API_KEY", "").strip()
CORS_ORIGINS = os.environ.get("CORS_ORIGINS", "*").strip()
WARMUP_ON_START = os.environ.get("WARMUP_ON_START", "1") != "0"
DEFAULT_VOICE = os.environ.get("KOKORO_DEFAULT_VOICE", "af_heart").strip()
MAX_TEXT_LENGTH = int(os.environ.get("MAX_TEXT_LENGTH", "5000"))

# Default model download URLs
DEFAULT_MODEL_URL = "https://github.com/thewh1teagle/kokoro-onnx/releases/download/model-files-v1.0/kokoro_fp16.onnx"
DEFAULT_VOICES_URL = "https://github.com/thewh1teagle/kokoro-onnx/releases/download/model-files-v1.0/voices-v1.0.bin"


def default_host() -> str:
    if os.environ.get("KOKORO_HOST"):
        return os.environ["KOKORO_HOST"]
    if os.path.exists("/.dockerenv"):
        return "0.0.0.0"
    return "127.0.0.1"


def get_model_paths() -> tuple[str, str]:
    """Resolve model and voices file paths, downloading if needed."""
    model_path = os.environ.get("KOKORO_MODEL_PATH", "")
    voices_path = os.environ.get("KOKORO_VOICES_PATH", "")

    cache_dir = os.environ.get("KOKORO_CACHE_DIR", os.path.expanduser("~/.kokoro"))
    os.makedirs(cache_dir, exist_ok=True)

    if not model_path:
        model_path = os.path.join(cache_dir, "kokoro_fp16.onnx")
    if not voices_path:
        voices_path = os.path.join(cache_dir, "voices-v1.0.bin")

    # Download if missing
    if not os.path.exists(model_path):
        print(f"Downloading Kokoro model to {model_path}...")
        import urllib.request
        urllib.request.urlretrieve(DEFAULT_MODEL_URL, model_path)
        print(f"Model saved: {os.path.getsize(model_path) / 1024 / 1024:.1f} MB")

    if not os.path.exists(voices_path):
        print(f"Downloading voices to {voices_path}...")
        import urllib.request
        urllib.request.urlretrieve(DEFAULT_VOICES_URL, voices_path)
        print(f"Voices saved: {os.path.getsize(voices_path) / 1024 / 1024:.1f} MB")

    return model_path, voices_path


def require_api_key(view):
    @wraps(view)
    def wrapped(*args, **kwargs):
        if not API_KEY:
            return view(*args, **kwargs)

        auth = request.headers.get("Authorization", "")
        if auth.startswith("Bearer ") and auth[7:].strip() == API_KEY:
            return view(*args, **kwargs)
        if request.headers.get("X-API-Key", "").strip() == API_KEY:
            return view(*args, **kwargs)

        return jsonify({"error": "Unauthorized. Provide Authorization: Bearer ***"}), 401

    return wrapped


@app.after_request
def apply_cors(response):
    origin = request.headers.get("Origin")
    allowed = [item.strip() for item in CORS_ORIGINS.split(",") if item.strip()]

    if CORS_ORIGINS == "*" or not allowed:
        response.headers["Access-Control-Allow-Origin"] = "*"
    elif origin and origin in allowed:
        response.headers["Access-Control-Allow-Origin"] = origin

    response.headers["Access-Control-Allow-Headers"] = "Authorization, Content-Type, X-API-Key"
    response.headers["Access-Control-Allow-Methods"] = "GET, POST, OPTIONS"
    return response


@app.route("/", methods=["GET", "OPTIONS"])
def root():
    if request.method == "OPTIONS":
        return ("", 204)

    return jsonify(
        {
            "name": "Kokoro TTS API",
            "provider": "Kokoro-82M (MIT license)",
            "endpoints": {
                "health": "GET /health",
                "speech": "POST /v1/audio/speech",
                "models": "GET /v1/models",
                "voices": "GET /v1/voices",
            },
            "auth_required": bool(API_KEY),
            "default_voice": DEFAULT_VOICE,
            "sample_rate": SAMPLE_RATE,
            "docs": {
                "speech_request": {
                    "input": "Text to synthesize (required)",
                    "voice": f"Voice name (default: {DEFAULT_VOICE}). See /v1/voices for options.",
                    "speed": "Playback speed multiplier (0.5-2.0, default: 1.0)",
                }
            },
        }
    )


@app.get("/v1/models")
@require_api_key
def list_models():
    return jsonify(
        {
            "object": "list",
            "data": [
                {
                    "id": "kokoro-82m",
                    "object": "model",
                    "owned_by": "kokoro",
                }
            ],
        }
    )


@app.get("/v1/voices")
@require_api_key
def list_voices():
    """List all available Kokoro voices."""
    return jsonify({
        "voices": AVAILABLE_VOICES,
        "default": DEFAULT_VOICE,
        "count": len(AVAILABLE_VOICES),
    })


def load_model():
    global MODEL, SAMPLE_RATE, MODEL_READY, MODEL_ERROR, AVAILABLE_VOICES

    with LOAD_LOCK:
        if MODEL is not None:
            return MODEL

        print("Loading Kokoro model...")
        started = time.perf_counter()

        try:
            from kokoro_onnx import Kokoro
            import numpy as np

            model_path, voices_path = get_model_paths()

            MODEL = Kokoro(model_path, voices_path)
            SAMPLE_RATE = 24000  # Kokoro always outputs 24kHz

            # Extract available voice names
            voices_data = np.load(voices_path)
            AVAILABLE_VOICES = sorted(list(voices_data.keys()))

            if WARMUP_ON_START:
                print("Warming up model...")
                warmup_started = time.perf_counter()
                MODEL.create("Ready.", voice=DEFAULT_VOICE)
                print(f"Warmup done in {time.perf_counter() - warmup_started:.1f}s")

            MODEL_READY = True
            MODEL_ERROR = None
            print(f"Model ready in {time.perf_counter() - started:.1f}s")
            print(f"Available voices: {len(AVAILABLE_VOICES)}")
            return MODEL

        except Exception as exc:
            MODEL_ERROR = str(exc)
            MODEL_READY = False
            traceback.print_exc()
            raise


def encode_wav(samples: Any, sample_rate: int) -> io.BytesIO:
    import numpy as np
    import soundfile as sf

    audio = np.asarray(samples).squeeze()
    buffer = io.BytesIO()
    sf.write(buffer, audio, sample_rate, format="WAV", subtype="PCM_16")
    buffer.seek(0)
    return buffer


def preload_model_background() -> None:
    def _run() -> None:
        global MODEL_ERROR, MODEL_READY
        try:
            load_model()
        except Exception as exc:
            MODEL_ERROR = str(exc)
            MODEL_READY = False
            traceback.print_exc()

    thread = threading.Thread(target=_run, name="kokoro-preload", daemon=True)
    thread.start()


@app.get("/health")
def health():
    return jsonify(
        {
            "status": "ok" if MODEL_ERROR is None else "error",
            "model": "kokoro-82m",
            "model_loaded": MODEL is not None,
            "model_ready": MODEL_READY,
            "sample_rate": SAMPLE_RATE,
            "voices": len(AVAILABLE_VOICES),
            "default_voice": DEFAULT_VOICE,
            "auth_required": bool(API_KEY),
            "error": MODEL_ERROR,
        }
    )


@app.post("/v1/audio/speech")
@require_api_key
def synthesize():
    if not request.is_json:
        return jsonify({"error": "Request must be JSON"}), 400

    data: dict[str, Any] = request.get_json(silent=True) or {}
    text = (data.get("input") or data.get("text") or "").strip()
    if not text:
        return jsonify({"error": "Missing 'input' text field"}), 400
    if len(text) > MAX_TEXT_LENGTH:
        return jsonify({"error": f"Text must be {MAX_TEXT_LENGTH} characters or fewer."}), 400

    voice = data.get("voice") or data.get("model") or DEFAULT_VOICE
    speed = float(data.get("speed", 1.0))

    # Validate voice name
    if AVAILABLE_VOICES and voice not in AVAILABLE_VOICES:
        return jsonify({
            "error": f"Unknown voice '{voice}'. Available voices: {AVAILABLE_VOICES[:20]}{'...' if len(AVAILABLE_VOICES) > 20 else ''}",
            "available_voices": AVAILABLE_VOICES,
        }), 400

    try:
        if MODEL is None:
            load_model()

        if MODEL is None:
            return jsonify({"error": MODEL_ERROR or "Model failed to load."}), 503

        if not MODEL_READY:
            return jsonify({"error": "Model is still loading. Retry /health until model_ready is true."}), 503

        started = time.perf_counter()

        # Kokoro's create() returns (samples, sample_rate)
        samples, sr = MODEL.create(text, voice=voice, speed=speed)

        elapsed = time.perf_counter() - started
        audio_duration = len(samples) / sr
        rtf = elapsed / audio_duration if audio_duration > 0 else 0

        print(f"Synthesized {len(text)} chars in {elapsed:.2f}s (audio={audio_duration:.2f}s, RTF={rtf:.2f}x) voice={voice}")

        buffer = encode_wav(samples, sr)
        response = send_file(buffer, mimetype="audio/wav", download_name="speech.wav")
        response.headers["X-Generation-Seconds"] = f"{elapsed:.3f}"
        response.headers["X-Audio-Duration"] = f"{audio_duration:.3f}"
        response.headers["X-Real-Time-Factor"] = f"{rtf:.3f}"
        response.headers["X-Voice"] = voice
        return response

    except Exception as exc:  # noqa: BLE001
        traceback.print_exc()
        return jsonify({"error": str(exc)}), 500


if __name__ == "__main__":
    host = default_host()
    port = int(os.environ.get("KOKORO_PORT", "4123"))

    print(f"Kokoro TTS API on http://{host}:{port}")
    print(f"Default voice: {DEFAULT_VOICE} | Auth: {'on' if API_KEY else 'off'}")
    preload_model_background()

    try:
        from waitress import serve
        serve(app, host=host, port=port, threads=4)
    except ImportError:
        app.run(host=host, port=port, debug=False, threaded=True)