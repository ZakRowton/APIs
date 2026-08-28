#Requires -Version 5.1
<#
.SYNOPSIS
  Deploy NeusWhisper to a Hostinger VPS from GitHub.
  SSHes into the VPS, clones/pulls the repo, then (re)builds the Docker stack.

  Setup:
    copy deploy.hostinger.env.example -> deploy.hostinger.env
    fill HOSTINGER_SSH_* , HOSTINGER_REMOTE_DIR , NEUS_WHISPER_REPO_*

  Run:
    powershell -ExecutionPolicy Bypass -File .\scripts\deploy-hostinger.ps1
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
    $map[$line.Substring(0, $idx).Trim()] = $line.Substring($idx + 1).Trim()
  }
  return $map
}

$deployEnv = Join-Path $Root 'deploy.hostinger.env'
if (-not (Test-Path $deployEnv)) {
  Write-Error 'Missing deploy.hostinger.env — copy deploy.hostinger.env.example and fill it in.'
}

$cfg = Read-EnvFile $deployEnv
$hostName = $cfg['HOSTINGER_SSH_HOST']
$user = $cfg['HOSTINGER_SSH_USER']
$port = if ($cfg['HOSTINGER_SSH_PORT']) { $cfg['HOSTINGER_SSH_PORT'] } else { '22' }
$remote = if ($cfg['HOSTINGER_REMOTE_DIR']) { $cfg['HOSTINGER_REMOTE_DIR'] } else { '/root/WhisperSTT_API' }
$repoUrl = if ($cfg['NEUS_WHISPER_REPO_URL']) { $cfg['NEUS_WHISPER_REPO_URL'] } else { 'https://github.com/ZakRowton/APIs.git' }
$branch = if ($cfg['NEUS_WHISPER_REPO_BRANCH']) { $cfg['NEUS_WHISPER_REPO_BRANCH'] } else { 'main' }

if (-not $hostName -or -not $user) {
  Write-Error 'HOSTINGER_SSH_HOST and HOSTINGER_SSH_USER are required in deploy.hostinger.env'
}

$sshTarget = "${user}@${hostName}"
$sshArgs = @('-p', $port)

$remoteCmd = @"
set -e
if ! command -v docker >/dev/null 2>&1; then
  echo 'Docker not found on VPS. Install Docker on the Hostinger VPS first.' >&2
  exit 1
fi
if [ -d '$remote/.git' ]; then
  cd '$remote'
  git fetch --depth 1 origin '$branch'
  git checkout '$branch'
  git reset --hard 'origin/$branch'
else
  git clone --branch '$branch' '$repoUrl' '$remote'
  cd '$remote'
fi
cd WhisperSTT_API
docker compose -f compose.hostinger.yaml up -d --build
docker compose -f compose.hostinger.yaml ps
"@

Write-Host "Deploying $repoUrl ($branch) to ${sshTarget}:${remote} ..."
ssh @sshArgs $sshTarget $remoteCmd

$httpPort = if ($cfg['NEUS_WHISPER_HTTP_PORT']) { $cfg['NEUS_WHISPER_HTTP_PORT'] } else { '8934' }
Write-Host ''
Write-Host "Deploy complete. API: http://${hostName}:${httpPort}/api/health.php"
Write-Host "Test UI: http://${hostName}:${httpPort}/test.php"
