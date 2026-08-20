#!/usr/bin/env bash
set -euo pipefail

# Wait for whisper-server (Docker service name "whisper") then start Apache.
# Config comes from the container environment (compose `environment:`), so the
# PHP app needs no config file on the VPS — see lib/bootstrap.php precedence.
WHISPER_HOST="${WHISPER_SERVER_HOST:-whisper}"
WHISPER_PORT="${WHISPER_SERVER_PORT:-8081}"
DEADLINE=$((SECONDS + 120))

while (( SECONDS < DEADLINE )); do
  if curl -sf "http://${WHISPER_HOST}:${WHISPER_PORT}/" >/dev/null 2>&1; then
    echo "whisper-server reachable at ${WHISPER_HOST}:${WHISPER_PORT}"
    break
  fi
  sleep 2
done

exec "$@"
