param(
    [string]$Url = 'https://localhost/frontend/public/login.html'
)

$ErrorActionPreference = 'Stop'

Write-Output "Checking $Url"
$headers = & curl.exe -k -sS -D - -o NUL $Url
if ($LASTEXITCODE -ne 0) {
    throw 'HTTPS connection failed. Start Apache/Nginx and check the certificate.'
}

$required = @(
    'X-Content-Type-Options',
    'X-Frame-Options',
    'Referrer-Policy',
    'Content-Security-Policy'
)

foreach ($name in $required) {
    if ($headers -notmatch "(?im)^$([regex]::Escape($name))\s*:") {
        throw "Missing security header: $name"
    }
    Write-Output "PASS $name"
}

if ($headers -notmatch '(?im)^HTTP/.* 200') {
    throw 'HTTPS endpoint did not return HTTP 200.'
}

Write-Output 'PASS HTTPS and security headers'
