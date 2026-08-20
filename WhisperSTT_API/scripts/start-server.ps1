#Requires -Version 5.1
<#
.SYNOPSIS
  Start whisper-server (background) for NeusWhisper API.

  Run from NeusWhisper:
    powershell -ExecutionPolicy Bypass -File .\scripts\start-server.ps1
#>
Set-StrictMode -Version Latest
$ErrorActionPreference = 'Stop'

$Root = Split-Path -Parent $PSScriptRoot
Set-Location $Root

function Read-EnvFile([string]$Path) {
  $map = @{}
  if (-not (Test-Path $Path)) { return $map }
  Get-Content $Path | ForEach-Object {
    $line = $_.Trim()
    if ($line -eq '' -or $line.StartsWith('#')) { return }
    $idx = $line.IndexOf('=')
    if ($idx -lt 1) { return }
    $key = $line.Substring(0, $idx).Trim()
    $val = $line.Substring($idx + 1).Trim()
    $map[$key] = $val
  }
  return $map
}

$envFile = Join-Path $Root 'config.local.env'
if (-not (Test-Path $envFile)) {
  $envFile = Join-Path $Root '.env'
}
if (-not (Test-Path $envFile)) {
  $envFile = Join-Path $Root 'config.example.env'
}
$cfg = Read-EnvFile $envFile

$hostName = if ($cfg.WHISPER_SERVER_HOST) { $cfg.WHISPER_SERVER_HOST } else { '127.0.0.1' }
$port = if ($cfg.WHISPER_SERVER_PORT) { $cfg.WHISPER_SERVER_PORT } else { '8081' }
$modelRel = if ($cfg.WHISPER_MODEL) { $cfg.WHISPER_MODEL } else { 'models/ggml-base.en.bin' }
$model = (Join-Path $Root ($modelRel -replace '/', '\')) -replace '\\', '/'

$serverCandidates = @(
  (Join-Path $Root 'whisper.cpp\build\bin\Release\whisper-server.exe'),
  (Join-Path $Root 'whisper.cpp\build\bin\whisper-server.exe')
)
$serverBin = $serverCandidates | Where-Object { Test-Path $_ } | Select-Object -First 1
if (-not $serverBin) {
  Write-Error 'whisper-server not found. Run scripts/setup-whisper.ps1 first.'
}

if (-not (Test-Path $model)) {
  Write-Error "Model not found at $model"
}

$pidFile = Join-Path $Root 'runtime\whisper-server.pid'
if (Test-Path $pidFile) {
  $oldPid = Get-Content $pidFile -Raw
  if ($oldPid -match '^\d+$') {
    $proc = Get-Process -Id ([int]$oldPid) -ErrorAction SilentlyContinue
    if ($proc) {
      Write-Host "whisper-server already running (PID $oldPid) on ${hostName}:$port"
      exit 0
    }
  }
}

$args = @(
  '--host', $hostName,
  '--port', $port,
  '-m', $model
)
$ffmpeg = Get-Command ffmpeg -ErrorAction SilentlyContinue
if ($ffmpeg) {
  $args += '--convert'
}

Write-Host "Starting whisper-server on ${hostName}:$port"
$proc = Start-Process -FilePath $serverBin -ArgumentList $args -PassThru -WindowStyle Hidden
Set-Content -Path $pidFile -Value $proc.Id
Write-Host "PID $($proc.Id)"

$ready = $false
for ($i = 0; $i -lt 20; $i++) {
  Start-Sleep -Seconds 1
  try {
    $resp = Invoke-WebRequest -Uri "http://${hostName}:$port/" -Method Get -UseBasicParsing -TimeoutSec 2
    if ($resp.StatusCode -ge 200 -and $resp.StatusCode -lt 500) {
      $ready = $true
      break
    }
  } catch {
    continue
  }
}
if ($ready) {
  Write-Host 'whisper-server is ready.'
} else {
  Write-Host 'whisper-server started but HTTP check did not succeed yet — wait a few seconds and retry health.'
}
Write-Host ''
Write-Host 'API endpoints (XAMPP example):'
Write-Host "  POST http://localhost/Repos/_workspace/NeusWhisper/api/transcribe.php"
Write-Host "  GET  http://localhost/Repos/_workspace/NeusWhisper/api/health.php"
Write-Host ''
Write-Host 'Test UI:'
Write-Host '  http://localhost/Repos/_workspace/NeusWhisper/public/'
