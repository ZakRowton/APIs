#!/usr/bin/env bash
# Deploy NeusWhisper to a Hostinger VPS from GitHub.
# SSHes into the VPS, clones/pulls the repo, then (re)builds the Docker stack.
set -euo pipefail
ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$ROOT"

ENV_FILE="$ROOT/deploy.hostinger.env"
if [[ ! -f "$ENV_FILE" ]]; then
  echo "Missing deploy.hostinger.env — copy deploy.hostinger.env.example and fill it in." >&2
  exit 1
fi

set -a
# shellcheck disable=SC1090
source "$ENV_FILE"
set +a

: "${HOSTINGER_SSH_HOST:?HOSTINGER_SSH_HOST required}"
: "${HOSTINGER_SSH_USER:?HOSTINGER_SSH_USER required}"
HOSTINGER_SSH_PORT="${HOSTINGER_SSH_PORT:-22}"
HOSTINGER_REMOTE_DIR="${HOSTINGER_REMOTE_DIR:-/root/WhisperSTT_API}"
NEUS_WHISPER_REPO_URL="${NEUS_WHISPER_REPO_URL:-https://github.com/ZakRowton/APIs.git}"
NEUS_WHISPER_REPO_BRANCH="${NEUS_WHISPER_REPO_BRANCH:-main}"

SSH="ssh -p ${HOSTINGER_SSH_PORT} ${HOSTINGER_SSH_USER}@${HOSTINGER_SSH_HOST}"

# shellcheck disable=SC2087
$SSH bash -s <<EOF
set -e
if ! command -v docker >/dev/null 2>&1; then
  echo 'Docker not found on VPS. Install Docker on the Hostinger VPS first.' >&2
  exit 1
fi
if [ -d '${HOSTINGER_REMOTE_DIR}/.git' ]; then
  cd '${HOSTINGER_REMOTE_DIR}'
  git fetch --depth 1 origin '${NEUS_WHISPER_REPO_BRANCH}'
  git checkout '${NEUS_WHISPER_REPO_BRANCH}'
  git reset --hard 'origin/${NEUS_WHISPER_REPO_BRANCH}'
else
  git clone --branch '${NEUS_WHISPER_REPO_BRANCH}' '${NEUS_WHISPER_REPO_URL}' '${HOSTINGER_REMOTE_DIR}'
  cd '${HOSTINGER_REMOTE_DIR}'
fi
cd WhisperSTT_API
docker compose -f compose.hostinger.yaml up -d --build
docker compose -f compose.hostinger.yaml ps
EOF

PORT="${NEUS_WHISPER_HTTP_PORT:-8934}"
echo ""
echo "Deploy complete: http://${HOSTINGER_SSH_HOST}:${PORT}/api/health.php"
echo "Test UI:         http://${HOSTINGER_SSH_HOST}:${PORT}/test.php"
