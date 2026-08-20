@echo off
setlocal
cd /d "%~dp0server"

set KOKORO_DEFAULT_VOICE=af_heart
set WARMUP_ON_START=1
set HF_HUB_DISABLE_SYMLINKS=1
set HF_HUB_DISABLE_SYMLINKS_WARNING=1
set TQDM_DISABLE=1

echo Starting Kokoro TTS API on http://127.0.0.1:4123
echo Wait for "Model ready" before generating speech.
echo Keep this window open while using the PHP demo.
echo Tip: use start-demo.bat to launch the API and open the browser together.
echo.

python tts_server.py
pause