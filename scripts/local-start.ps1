param(
    [string]$HostName = "127.0.0.1",
    [int]$Port = 8000
)

$baseUrl = "http://$HostName`:$Port"

Write-Host "Checking Laravel boot..."
php artisan about | Out-Null
if ($LASTEXITCODE -ne 0) {
    Write-Error "Laravel bootstrap failed. Run: php artisan about"
    exit 1
}

try {
    $response = Invoke-WebRequest -UseBasicParsing -Uri $baseUrl -TimeoutSec 5
    if ($response.StatusCode -ge 200 -and $response.StatusCode -lt 500) {
        Write-Host "Server already running at $baseUrl"
    }
} catch {
    Write-Host "Starting php artisan serve at $baseUrl ..."
    Start-Process -FilePath "php" -ArgumentList @("artisan", "serve", "--host=$HostName", "--port=$Port") -WorkingDirectory (Get-Location)
    Start-Sleep -Seconds 3
}

Write-Host "Running local smoke checks..."
php scripts/local-smoke.php $baseUrl
if ($LASTEXITCODE -ne 0) {
    Write-Error "Smoke checks failed. Inspect server output/logs before manual testing."
    exit 1
}

Write-Host "OK: local app is up and basic routes are reachable at $baseUrl"
