$ErrorActionPreference = 'Stop'

$root = (Resolve-Path (Join-Path $PSScriptRoot '..')).Path
$taskName = 'AstrokomunitaDevStack'
$scriptPath = Join-Path $root 'scripts/dev-up.ps1'
$powershell = (Get-Command powershell).Source

$action = New-ScheduledTaskAction -Execute $powershell -Argument "-NoProfile -ExecutionPolicy Bypass -File `"$scriptPath`""
$trigger = New-ScheduledTaskTrigger -AtLogOn
$settings = New-ScheduledTaskSettingsSet -AllowStartIfOnBatteries -StartWhenAvailable

Register-ScheduledTask -TaskName $taskName -Action $action -Trigger $trigger -Settings $settings -Description 'Start Astrokomunita local stack (frontend, backend, sky) at logon' -Force | Out-Null

Write-Host "Scheduled task '$taskName' created/updated."
