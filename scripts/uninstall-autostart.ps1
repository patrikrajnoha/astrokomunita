$taskName = 'AstrokomunitaDevStack'
Unregister-ScheduledTask -TaskName $taskName -Confirm:$false -ErrorAction SilentlyContinue | Out-Null
Write-Host "Scheduled task '$taskName' removed."
