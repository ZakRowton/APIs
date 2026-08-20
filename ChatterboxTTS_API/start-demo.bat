@echo off
setlocal
cd /d "%~dp0"

echo Launching Kokoro API in a new window...
start "Kokoro API" cmd /k "%~dp0start-kokoro-api.bat"

echo Waiting for the API to come online...
set /a tries=0

:wait_loop
set /a tries+=1
powershell -NoProfile -Command "try { (Invoke-WebRequest -Uri 'http://127.0.0.1:4123/health' -UseBasicParsing -TimeoutSec 2).StatusCode | Out-Null; exit 0 } catch { exit 1 }"
if %ERRORLEVEL%==0 goto open_browser
if %TRIES% GEQ 30 (
    echo.
    echo The API window is still loading the model. Opening the demo anyway...
    echo Keep the "Kokoro API" window open and wait for "Model ready".
    goto open_browser
)
timeout /t 2 /nobreak >nul
goto wait_loop

:open_browser
start "" "http://localhost/NeusSpeechAPI/"
echo.
echo Demo opened in your browser.
echo Keep the "Kokoro API" window open while using the demo.
pause