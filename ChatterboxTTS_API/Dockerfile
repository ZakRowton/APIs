FROM python:3.11-slim-bookworm

ENV PYTHONDONTWRITEBYTECODE=1 \
    PYTHONUNBUFFERED=1 \
    PIP_NO_CACHE_DIR=1 \
    KOKORO_HOST=0.0.0.0 \
    KOKORO_PORT=4123 \
    KOKORO_DEFAULT_VOICE=af_heart \
    HF_HOME=/cache/huggingface \
    TQDM_DISABLE=1

RUN apt-get update && apt-get install -y --no-install-recommends \
    libsndfile1 \
    ffmpeg \
    curl \
    && rm -rf /var/lib/apt/lists/*

WORKDIR /app

COPY server/requirements.txt .
RUN pip install --no-cache-dir -r requirements.txt

COPY server/tts_server.py .

RUN useradd --create-home --shell /bin/bash appuser \
    && mkdir -p /cache/huggingface /home/appuser/.kokoro \
    && chown -R appuser:appuser /app /cache

USER appuser

EXPOSE 4123

# Kokoro loads fast (~3s) and is lightweight (183MB total), so a short start period is fine
HEALTHCHECK --interval=30s --timeout=10s --start-period=60s --retries=3 \
    CMD curl -fsS http://127.0.0.1:4123/health | grep -q '"model_ready": true' || curl -fsS http://127.0.0.1:4123/health | grep -q '"status": "ok"'

CMD ["python", "tts_server.py"]