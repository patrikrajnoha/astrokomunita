param(
    [string]$AccessKeyId,
    [string]$SecretAccessKey
)

$ErrorActionPreference = 'Stop'

function Read-PlainSecret {
    param([string]$Prompt)

    $secureValue = Read-Host -Prompt $Prompt -AsSecureString
    $bstr = [Runtime.InteropServices.Marshal]::SecureStringToBSTR($secureValue)

    try {
        return [Runtime.InteropServices.Marshal]::PtrToStringBSTR($bstr)
    }
    finally {
        [Runtime.InteropServices.Marshal]::ZeroFreeBSTR($bstr)
    }
}

if (-not $AccessKeyId) {
    $AccessKeyId = Read-Host -Prompt 'zadajte r2 access key id'
}

if (-not $SecretAccessKey) {
    $SecretAccessKey = Read-PlainSecret -Prompt 'zadajte r2 secret access key'
}

if (-not $AccessKeyId -or -not $SecretAccessKey) {
    throw 'access key id aj secret access key su povinne'
}

$backendDir = Split-Path -Parent $PSScriptRoot
$envNames = @(
    'FILES_DISK',
    'FILES_PRIVATE_DISK',
    'AWS_ACCESS_KEY_ID',
    'AWS_SECRET_ACCESS_KEY',
    'AWS_DEFAULT_REGION',
    'AWS_BUCKET',
    'AWS_URL',
    'AWS_ENDPOINT',
    'AWS_USE_PATH_STYLE_ENDPOINT',
    'R2_PRIVATE_BUCKET'
)

$originalEnv = @{}

foreach ($name in $envNames) {
    $existing = [Environment]::GetEnvironmentVariable($name, 'Process')
    if ($null -ne $existing) {
        $originalEnv[$name] = $existing
    }
}

try {
    $env:FILES_DISK = 'r2_public'
    $env:FILES_PRIVATE_DISK = 'r2_private'
    $env:AWS_ACCESS_KEY_ID = $AccessKeyId
    $env:AWS_SECRET_ACCESS_KEY = $SecretAccessKey
    $env:AWS_DEFAULT_REGION = 'auto'
    $env:AWS_BUCKET = 'astrokomunita-public-prod'
    $env:AWS_URL = ''
    $env:AWS_ENDPOINT = 'https://d839eaa7086db644c33b3d41ec5c9c7c.r2.cloudflarestorage.com'
    $env:AWS_USE_PATH_STYLE_ENDPOINT = 'true'
    $env:R2_PRIVATE_BUCKET = 'astrokomunita-private-prod'

    Push-Location $backendDir

    Write-Host 'spustam: php artisan optimize:clear'
    & php artisan optimize:clear
    if ($LASTEXITCODE -ne 0) {
        throw "php artisan optimize:clear zlyhal s kodom $LASTEXITCODE"
    }

    Write-Host 'spustam: php artisan storage:r2-healthcheck'
    & php artisan storage:r2-healthcheck
    if ($LASTEXITCODE -ne 0) {
        throw "php artisan storage:r2-healthcheck zlyhal s kodom $LASTEXITCODE"
    }

    Write-Host ''
    Write-Host 'info: overte cloudflare ui:'
    Write-Host 'public bucket: healthchecks/public/...'
    Write-Host 'private bucket: healthchecks/private/...'
}
finally {
    Pop-Location

    foreach ($name in $envNames) {
        if ($originalEnv.ContainsKey($name)) {
            [Environment]::SetEnvironmentVariable($name, $originalEnv[$name], 'Process')
        }
        else {
            [Environment]::SetEnvironmentVariable($name, $null, 'Process')
        }
    }
}
