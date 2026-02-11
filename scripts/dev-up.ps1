param(
    [switch]$RecreateSkyVenv
)

$ErrorActionPreference = 'Stop'

$root = (Resolve-Path (Join-Path $PSScriptRoot '..')).Path
$runDir = Join-Path $root '.run'
if (-not (Test-Path $runDir)) {
    New-Item -ItemType Directory -Path $runDir | Out-Null
}

function Test-PortOpen {
    param([int]$Port)

    $client = New-Object System.Net.Sockets.TcpClient
    try {
        $iar = $client.BeginConnect('127.0.0.1', $Port, $null, $null)
        $ok = $iar.AsyncWaitHandle.WaitOne(250)
        if (-not $ok) { return $false }
        $client.EndConnect($iar) | Out-Null
        return $true
    }
    catch {
        return $false
    }
    finally {
        $client.Close()
    }
}

function Start-ManagedProcess {
    param(
        [string]$Name,
        [string]$FilePath,
        [object[]]$Arguments,
        [string]$WorkingDirectory,
        [string]$PidFile,
        [string]$LogFile,
        [int]$Port
    )

    if (Test-PortOpen -Port $Port) {
        Write-Host "[$Name] already running on port $Port"
        return
    }

    $stderrFile = [System.IO.Path]::ChangeExtension($LogFile, '.err.log')

    $process = Start-Process -FilePath $FilePath -ArgumentList $Arguments -WorkingDirectory $WorkingDirectory -WindowStyle Hidden -RedirectStandardOutput $LogFile -RedirectStandardError $stderrFile -PassThru

    Set-Content -Path $PidFile -Value $process.Id

    Start-Sleep -Milliseconds 1200

    if (Test-PortOpen -Port $Port) {
        Write-Host "[$Name] started (PID $($process.Id), port $Port)"
    }
    else {
        Write-Host "[$Name] failed to bind port $Port. Check logs: $LogFile / $stderrFile"
    }
}

$skyDir = Join-Path $root 'services/sky'
$skyVenvPy = Join-Path $skyDir '.venv/Scripts/python.exe'

if ($RecreateSkyVenv -or -not (Test-Path $skyVenvPy)) {
    Write-Host '[sky] preparing virtualenv'
    & python -m venv (Join-Path $skyDir '.venv')
    & $skyVenvPy -m pip install -r (Join-Path $skyDir 'requirements.txt')
}

$phpExe = (Get-Command php).Source
$npmCmd = (Get-Command npm.cmd).Source

Start-ManagedProcess -Name 'sky' -FilePath $skyVenvPy -Arguments @('-m', 'uvicorn', 'main:app', '--host', '127.0.0.1', '--port', '8010') -WorkingDirectory (Join-Path $skyDir 'app') -PidFile (Join-Path $runDir 'sky.pid') -LogFile (Join-Path $runDir 'sky.log') -Port 8010
Start-ManagedProcess -Name 'backend' -FilePath $phpExe -Arguments @('artisan', 'serve', '--host=127.0.0.1', '--port=8000') -WorkingDirectory (Join-Path $root 'backend') -PidFile (Join-Path $runDir 'backend.pid') -LogFile (Join-Path $runDir 'backend.log') -Port 8000
Start-ManagedProcess -Name 'frontend' -FilePath $npmCmd -Arguments @('run', 'dev', '--', '--host', '127.0.0.1', '--port', '5173') -WorkingDirectory (Join-Path $root 'frontend') -PidFile (Join-Path $runDir 'frontend.pid') -LogFile (Join-Path $runDir 'frontend.log') -Port 5173

Write-Host ''
Write-Host 'Status:'
& (Join-Path $PSScriptRoot 'dev-status.ps1')
