#!/usr/bin/env bash
set -euo pipefail
ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$ROOT"

load_env() {
  local file
  for file in "$ROOT/config.local.env" "$ROOT/.env" "$ROOT/config.example.env"; do
    if [[ -f "$file" ]]; then
      set -a
      # shellcheck disable=SC1090
      source "$file"
      set +a
      return
    fi
  done
}

load_env

HOST="${WHISPER_SERVER_HOST:-127.0.0.1}"
PORT="${WHISPER_SERVER_PORT:-8081}"
MODEL_REL="${WHISPER_MODEL:-models/ggml-base.en.bin}"
MODEL="$ROOT/${MODEL_REL//\\//}"

find_server() {
  local candidates=(
    "$ROOT/whisper.cpp/build/bin/whisper-server"
    "$ROOT/whisper.cpp/build/bin/Release/whisper-server"
  )
  local c
  for c in "${candidates[@]}"; do
    if [[ -x "$c" ]]; then
      echo "$c"
      return 0
    fi
  done
  return 1
}

SERVER_BIN="$(find_server || true)"
if [[ -z "$SERVER_BIN" ]]; then
  echo "whisper-server not found. Run scripts/setup-whisper.sh first." >&2
  exit 1
fi

if [[ ! -f "$MODEL" ]]; then
  echo "Model missing at $MODEL" >&2
  exit 1
fi

PID_FILE="$ROOT/runtime/whisper-server.pid"
mkdir -p "$ROOT/runtime"

if [[ -f "$PID_FILE" ]]; then
  OLD_PID="$(tr -d ' \n\r' < "$PID_FILE" || true)"
  if [[ "$OLD_PID" =~ ^[0-9]+$ ]] && kill -0 "$OLD_PID" 2>/dev/null; then
    echo "whisper-server already running (PID $OLD_PID) on ${HOST}:${PORT}"
    exit 0
  fi
fi

MODEL="${MODEL//\\//}"
ARGS=(--host "$HOST" --port "$PORT" -m "$MODEL")
if command -v ffmpeg >/dev/null 2>&1; then
  ARGS+=(--convert)
fi

nohup "$SERVER_BIN" "${ARGS[@]}" >> "$ROOT/runtime/whisper-server.log" 2>&1 &
echo $! > "$PID_FILE"
echo "Started whisper-server PID $(cat "$PID_FILE") on ${HOST}:${PORT}"

for _ in $(seq 1 30); do
  if curl -sf "http://${HOST}:${PORT}/" >/dev/null 2>&1; then
    echo "whisper-server is ready."
    exit 0
  fi
  sleep 1
done

echo "whisper-server started but HTTP check not ready yet — see runtime/whisper-server.log" >&2
