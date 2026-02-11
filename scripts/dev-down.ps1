$ErrorActionPreference = 'SilentlyContinue'

$root = (Resolve-Path (Join-Path $PSScriptRoot '..')).Path
$runDir = Join-Path $root '.run'

function Stop-ByPidFile {
    param(
        [string]$Name,
        [string]$PidFile
    )

    if (-not (Test-Path $PidFile)) {
        Write-Host "[$Name] no pid file"
        return
    }

    $procId = Get-Content $PidFile
    if ($procId) {
        Stop-Process -Id $procId -Force -ErrorAction SilentlyContinue
        Write-Host "[$Name] stopped PID $procId"
    }

    Remove-Item $PidFile -Force -ErrorAction SilentlyContinue
}

Stop-ByPidFile -Name 'frontend' -PidFile (Join-Path $runDir 'frontend.pid')
Stop-ByPidFile -Name 'backend' -PidFile (Join-Path $runDir 'backend.pid')
Stop-ByPidFile -Name 'sky' -PidFile (Join-Path $runDir 'sky.pid')

foreach ($port in 5173,8000,8010) {
    $conns = Get-NetTCPConnection -LocalPort $port -State Listen -ErrorAction SilentlyContinue
    foreach ($conn in $conns) {
        if ($conn.OwningProcess -and $conn.OwningProcess -gt 0) {
            Stop-Process -Id $conn.OwningProcess -Force -ErrorAction SilentlyContinue
            Write-Host "[port $port] killed leftover PID $($conn.OwningProcess)"
        }
    }
}

Write-Host 'Done.'
