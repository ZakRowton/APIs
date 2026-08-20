# Deploy Kokoro TTS API on Hostinger VPS

## Requirements

- Hostinger **KVM VPS** (not shared hosting) with Docker installed
- **2 GB RAM** minimum (was 8 GB with Chatterbox)
- **10 GB+ disk** for Docker image + model cache
- Ubuntu 22.04/24.04 or similar Linux

## Quick deploy

```bash
# On your VPS
git clone <your-repo-url> kokoro-api
cd kokoro-api

cp .env.example .env
nano .env   # set API_KEY to a long random string, pick a default voice

chmod +x deploy.sh
./deploy.sh
```

First start downloads the Kokoro model (~183 MB) — takes **under 1 minute**.

## Verify

```bash
docker compose logs -f kokoro-api
curl http://127.0.0.1:4123/health
```

Wait until `"model_ready": true`.

## API usage

### Health

```bash
curl http://YOUR_VPS_IP:4123/health
```

### List voices

```bash
curl http://YOUR_VPS_IP:4123/v1/voices
```

### Generate speech

```bash
curl -X POST http://YOUR_VPS_IP:4123/v1/audio/speech \
  -H "Authorization: Bearer ***" \
  -H "Content-Type: application/json" \
  -d '{"input":"Hello from Kokoro on Hostinger.","voice":"af_heart"}' \
  --output speech.wav
```

### JavaScript fetch

```javascript
const response = await fetch('https://tts.yourdomain.com/v1/audio/speech', {
  method: 'POST',
  headers: {
    'Authorization': 'Bearer YOUR_API_KEY',
    'Content-Type': 'application/json',
  },
  body: JSON.stringify({ input: 'Hello world', voice: 'af_heart' }),
});
const blob = await response.blob();
```

## HTTPS with Nginx (recommended)

Install Nginx on the VPS and proxy to the container:

```nginx
server {
    listen 80;
    server_name tts.yourdomain.com;

    location / {
        proxy_pass http://127.0.0.1:4123;
        proxy_read_timeout 120s;
        proxy_connect_timeout 60s;
        proxy_send_timeout 120s;
        client_max_body_size 10m;
    }
}
```

Then add SSL with Certbot:

```bash
sudo certbot --nginx -d tts.yourdomain.com
```

Open port **4123** only if you want direct access; otherwise use Nginx on 443 only.

## Firewall

```bash
sudo ufw allow 22
sudo ufw allow 80
sudo ufw allow 443
# Optional direct API access:
# sudo ufw allow 4123
sudo ufw enable
```

## Environment variables

| Variable | Default | Description |
|----------|---------|-------------|
| `API_PORT` | `4123` | Host port mapped to container |
| `API_KEY` | empty | Require Bearer token when set |
| `KOKORO_DEFAULT_VOICE` | `af_heart` | Default voice name |
| `WARMUP_ON_START` | `1` | Pre-load model on container start |
| `MEMORY_LIMIT` | `2g` | Docker memory cap |

## Connect the PHP demo

In `config.php` on your web server:

```php
return [
    'kokoro_api_url' => 'https://tts.yourdomain.com/v1/audio/speech',
    'kokoro_health_url' => 'https://tts.yourdomain.com/health',
    'kokoro_voices_url' => 'https://tts.yourdomain.com/v1/voices',
    'api_key' => 'your-api-key-from-env',
    'default_voice' => 'af_heart',
    'default_speed' => 1.0,
    'request_timeout' => 60,
];
```

## Operations

```bash
docker compose ps
docker compose logs -f kokoro-api
docker compose restart kokoro-api
docker compose down
docker compose up -d --build   # after code updates
```

Model weights are cached in the Docker volume `kokoro_cache` so restarts do not re-download.