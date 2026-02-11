$ErrorActionPreference = 'SilentlyContinue'

$root = (Resolve-Path (Join-Path $PSScriptRoot '..')).Path
$runDir = Join-Path $root '.run'

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

function Show-FrontendStatus {
    $pidFile = Join-Path $runDir 'frontend.pid'
    $procId = if (Test-Path $pidFile) { Get-Content $pidFile | Select-Object -First 1 } else { '-' }
    $up = Test-PortOpen -Port 5173
    $state = if ($up) { 'UP' } else { 'DOWN' }

    Write-Host ("{0,-10} {1,-4} pid={2} url={3}" -f 'frontend', $state, $procId, 'http://127.0.0.1:5173')
    if ($up) {
        Write-Host '  -> port open'
    }
}

function Show-JsonStatus {
    param(
        [string]$Name,
        [string]$PidFile,
        [string]$Url
    )

    $procId = if (Test-Path $PidFile) { Get-Content $PidFile | Select-Object -First 1 } else { '-' }

    try {
        $payload = Invoke-RestMethod $Url -TimeoutSec 2
        Write-Host ("{0,-10} {1,-4} pid={2} url={3}" -f $Name, 'UP', $procId, $Url)
        Write-Host ("  -> {0}" -f ($payload | ConvertTo-Json -Compress))
    }
    catch {
        Write-Host ("{0,-10} {1,-4} pid={2} url={3}" -f $Name, 'DOWN', $procId, $Url)
    }
}

Show-FrontendStatus
Show-JsonStatus -Name 'backend' -PidFile (Join-Path $runDir 'backend.pid') -Url 'http://127.0.0.1:8000/api/health'
Show-JsonStatus -Name 'sky' -PidFile (Join-Path $runDir 'sky.pid') -Url 'http://127.0.0.1:8010/health'
