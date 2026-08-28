#!/usr/bin/env bash
# Hostinger Docker Manager bootstrap for the WhisperSTT_API PHP container.
# Runs inside php:8.2-apache-bookworm — no custom image / build context needed.
set -euo pipefail

MARKER=/var/www/html/.whisperstt-bootstrapped
REPO_ARCHIVE_URL="${NEUS_WHISPER_ARCHIVE_URL:-https://github.com/ZakRowton/APIs/archive/refs/heads/main.tar.gz}"
RAW_BASE="${NEUS_WHISPER_RAW_BASE:-https://raw.githubusercontent.com/ZakRowton/APIs/main/WhisperSTT_API}"

export APACHE_DOCUMENT_ROOT=/var/www/html/public

if [ ! -f "$MARKER" ]; then
  echo "Bootstrapping WhisperSTT_API from GitHub..."
  apt-get update
  apt-get install -y --no-install-recommends libcurl4-openssl-dev curl ca-certificates tar
  docker-php-ext-install curl
  a2enmod rewrite headers
  rm -rf /var/lib/apt/lists/*

  tmp="$(mktemp -d)"
  curl -fsSL "$REPO_ARCHIVE_URL" -o "$tmp/apis.tgz"
  tar -xzf "$tmp/apis.tgz" -C "$tmp"
  src="$(echo "$tmp"/APIs-*/WhisperSTT_API)"

  mkdir -p /var/www/html
  rm -rf /var/www/html/api /var/www/html/lib /var/www/html/public
  cp -a "$src/api" "$src/lib" "$src/public" /var/www/html/
  cp -a "$src/test.php" "$src/config.example.env" /var/www/html/
  mkdir -p /var/www/html/runtime/uploads /var/www/html/models

  curl -fsSL "$RAW_BASE/docker/apache-neuswhisper.conf" \
    -o /etc/apache2/sites-available/000-default.conf
  curl -fsSL "$RAW_BASE/docker/php-neuswhisper.ini" \
    -o /usr/local/etc/php/conf.d/99-whisperstt.ini

  chown -R www-data:www-data /var/www/html
  rm -rf "$tmp"
  touch "$MARKER"
  echo "Bootstrap complete."
fi

WHISPER_HOST="${WHISPER_SERVER_HOST:-whisper}"
WHISPER_PORT="${WHISPER_SERVER_PORT:-8081}"
DEADLINE=$((SECONDS + 180))
while (( SECONDS < DEADLINE )); do
  if curl -sf "http://${WHISPER_HOST}:${WHISPER_PORT}/" >/dev/null 2>&1; then
    echo "whisper-server reachable at ${WHISPER_HOST}:${WHISPER_PORT}"
    break
  fi
  sleep 2
done

exec apache2-foreground
