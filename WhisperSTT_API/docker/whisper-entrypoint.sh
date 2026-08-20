#!/usr/bin/env sh
set -eu

# Downloads the ggml model on first boot (persisted in the ./models volume),
# then runs whisper-server. Model is selected by WHISPER_MODEL_PATH; the short
# name is derived from the filename (e.g. ggml-base.en.bin -> base.en).
MODEL="${WHISPER_MODEL_PATH:-/models/ggml-base.en.bin}"
MODEL_DIR="$(dirname "$MODEL")"
MODEL_FILE="$(basename "$MODEL")"
MODEL_NAME="${MODEL_FILE#ggml-}"
MODEL_NAME="${MODEL_NAME%.bin}"
mkdir -p "$MODEL_DIR"

if [ ! -f "$MODEL" ]; then
  echo "Downloading whisper model '$MODEL_NAME' to $MODEL ..."
  if [ -x ./models/download-ggml-model.sh ]; then
    ./models/download-ggml-model.sh "$MODEL_NAME" "$MODEL_DIR"
  else
    curl -L "https://huggingface.co/ggerganov/whisper.cpp/resolve/main/${MODEL_FILE}" -o "$MODEL"
  fi
fi

HOST="${WHISPER_SERVER_HOST:-0.0.0.0}"
PORT="${WHISPER_SERVER_PORT:-8081}"

exec whisper-server --host "$HOST" --port "$PORT" -m "$MODEL" --convert
