#!/usr/bin/env bash
set -euo pipefail
ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$ROOT"

if [[ ! -d "$ROOT/whisper.cpp/.git" ]]; then
  git clone --depth 1 https://github.com/ggml-org/whisper.cpp.git "$ROOT/whisper.cpp"
fi

mkdir -p "$ROOT/models" "$ROOT/runtime/uploads"

cmake -S "$ROOT/whisper.cpp" -B "$ROOT/whisper.cpp/build" -DCMAKE_BUILD_TYPE=Release -DWHISPER_BUILD_SERVER=ON
cmake --build "$ROOT/whisper.cpp/build" --config Release --target whisper-cli whisper-server -j"$(nproc 2>/dev/null || echo 2)"

MODEL="$ROOT/models/ggml-base.en.bin"
if [[ ! -f "$MODEL" ]]; then
  if [[ -x "$ROOT/whisper.cpp/models/download-ggml-model.sh" ]]; then
    bash "$ROOT/whisper.cpp/models/download-ggml-model.sh" base.en "$ROOT/models"
  else
    curl -L "https://huggingface.co/ggerganov/whisper.cpp/resolve/main/ggml-base.en.bin" -o "$MODEL"
  fi
fi

if [[ ! -f "$ROOT/config.local.env" ]]; then
  cp "$ROOT/config.example.env" "$ROOT/config.local.env"
fi

chmod +x "$ROOT/scripts/start-server.sh" || true
echo "Setup complete. Run: ./scripts/start-server.sh"
