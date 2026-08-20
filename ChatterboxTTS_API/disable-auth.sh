#!/usr/bin/env bash
# Disable API key auth on the VPS and restart the container.
set -euo pipefail
cd "$(dirname "$0")"

if [[ -f .env ]]; then
  if grep -q '^API_KEY=' .env; then
    sed -i.bak 's/^API_KEY=.*/API_KEY=/' .env
  else
    echo 'API_KEY=' >> .env
  fi
else
  cp .env.example .env
fi

docker compose up -d --force-recreate
echo "Auth disabled. API_KEY is now empty."
curl -fsS "http://127.0.0.1:${API_PORT:-4123}/health" | grep -q '"auth_required": false' && echo "Confirmed: auth_required is false."
