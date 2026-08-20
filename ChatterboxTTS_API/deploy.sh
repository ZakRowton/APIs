#!/usr/bin/env bash
set -euo pipefail

cd "$(dirname "$0")"

if [[ ! -f .env ]]; then
  cp .env.example .env
  echo "Created .env from .env.example — edit API_KEY before exposing this publicly."
fi

docker compose pull 2>/dev/null || true
docker compose up -d --build

echo ""
echo "Kokoro API starting on port $(grep -E '^API_PORT=' .env | cut -d= -f2 || echo 4123)"
echo "Check status:  docker compose logs -f kokoro-api"
echo "Health check: curl http://127.0.0.1:${API_PORT:-4123}/health"
