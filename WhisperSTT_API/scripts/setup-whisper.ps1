#Requires -Version 5.1
<#
.SYNOPSIS
  Clone whisper.cpp, build whisper-cli + whisper-server, download base.en model.

.DESCRIPTION
  Run from NeusWhisper directory:
    powershell -ExecutionPolicy Bypass -File .\scripts\setup-whisper.ps1

  Requires: git, cmake, a C++ toolchain (Visual Studio Build Tools), curl
#>
Set-StrictMode -Version Latest
$ErrorActionPreference = 'Stop'

$Root = Split-Path -Parent $PSScriptRoot
Set-Location $Root

$CppDir = Join-Path $Root 'whisper.cpp'
$ModelsDir = Join-Path $Root 'models'
$ModelFile = Join-Path $ModelsDir 'ggml-base.en.bin'

New-Item -ItemType Directory -Force -Path $ModelsDir | Out-Null
New-Item -ItemType Directory -Force -Path (Join-Path $Root 'runtime\uploads') | Out-Null

if (-not (Test-Path $CppDir)) {
  Write-Host 'Cloning whisper.cpp…'
  git clone --depth 1 https://github.com/ggml-org/whisper.cpp.git $CppDir
} else {
  Write-Host 'whisper.cpp already present — skipping clone.'
}

$BuildDir = Join-Path $CppDir 'build'
if (-not (Test-Path (Join-Path $BuildDir 'CMakeCache.txt'))) {
  Write-Host 'Configuring CMake (Release, server + cli)…'
  cmake -S $CppDir -B $BuildDir -DCMAKE_BUILD_TYPE=Release -DWHISPER_BUILD_SERVER=ON
}

Write-Host 'Building whisper-cli and whisper-server…'
cmake --build $BuildDir --config Release --target whisper-cli whisper-server

if (-not (Test-Path $ModelFile)) {
  Write-Host 'Downloading ggml-base.en model…'
  $DownloadScript = Join-Path $CppDir 'models\download-ggml-model.cmd'
  if (Test-Path $DownloadScript) {
    Push-Location (Join-Path $CppDir 'models')
    & $DownloadScript base.en
    Pop-Location
    $Downloaded = Join-Path $CppDir 'models\ggml-base.en.bin'
    if (Test-Path $Downloaded) {
      Copy-Item $Downloaded $ModelFile -Force
    }
  } else {
    $Url = 'https://huggingface.co/ggerganov/whisper.cpp/resolve/main/ggml-base.en.bin'
    curl.exe -L $Url -o $ModelFile
  }
}

if (-not (Test-Path (Join-Path $Root 'config.local.env'))) {
  Copy-Item (Join-Path $Root 'config.example.env') (Join-Path $Root 'config.local.env')
  Write-Host 'Created config.local.env from example.'
}

Write-Host 'Setup complete.'
Write-Host "Model: $ModelFile"
Write-Host 'Next: .\scripts\start-server.ps1'
