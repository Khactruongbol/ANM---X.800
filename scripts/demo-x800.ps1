param(
    [string]$BaseUrl = 'https://localhost',
    [switch]$TamperAudit
)

$ErrorActionPreference = 'Stop'
$BaseUrl = $BaseUrl.TrimEnd('/')
$ApiUrl = "$BaseUrl/backend/api"
$Mysql = 'C:\xampp\mysql\bin\mysql.exe'
$Results = [System.Collections.Generic.List[object]]::new()

function Add-Result {
    param(
        [string]$Id,
        [string]$Name,
        [bool]$Passed,
        [string]$Detail
    )

    $Results.Add([pscustomobject]@{
        Id = $Id
        Name = $Name
        Result = if ($Passed) { 'PASS' } else { 'FAIL' }
        Detail = $Detail
    })
}

function Invoke-Api {
    param(
        [ValidateSet('GET', 'POST', 'PUT', 'PATCH', 'DELETE')]
        [string]$Method,
        [string]$Path,
        [Microsoft.PowerShell.Commands.WebRequestSession]$Session,
        [hashtable]$Headers = @{},
        [object]$Body = $null,
        [string]$ContentType = 'application/x-www-form-urlencoded'
    )

    $request = @{
        Uri = "$ApiUrl/$Path"
        Method = $Method
        WebSession = $Session
        SkipCertificateCheck = $true
        ErrorAction = 'Stop'
    }

    if ($Headers.Count -gt 0) {
        $request.Headers = $Headers
    }

    if ($null -ne $Body) {
        $request.Body = if ($ContentType -eq 'application/json') {
            $Body | ConvertTo-Json -Depth 6 -Compress
        } else {
            $Body
        }
        $request.ContentType = $ContentType
    }

    try {
        $response = Invoke-WebRequest @request
        $json = $null
        try { $json = $response.Content | ConvertFrom-Json } catch { }
        return [pscustomobject]@{
            Status = [int]$response.StatusCode
            Json = $json
            Raw = $response.Content
            Headers = $response.Headers
        }
    } catch {
        $status = 0
        $raw = $_.Exception.Message
        if ($_.Exception.Response) {
            try {
                $status = [int]$_.Exception.Response.StatusCode
                $reader = [System.IO.StreamReader]::new($_.Exception.Response.GetResponseStream())
                $raw = $reader.ReadToEnd()
                $reader.Dispose()
            } catch { }
        }
        $json = $null
        try { $json = $raw | ConvertFrom-Json } catch { }
        return [pscustomobject]@{
            Status = $status
            Json = $json
            Raw = $raw
            Headers = @{}
        }
    }
}

function New-SessionWithCsrf {
    $session = [Microsoft.PowerShell.Commands.WebRequestSession]::new()
    $response = Invoke-Api -Method GET -Path 'auth/csrf.php' -Session $session
    $token = $response.Json.data.csrf_token
    if ([string]::IsNullOrWhiteSpace($token)) {
        throw 'Không lấy được CSRF token từ localhost.'
    }
    return [pscustomobject]@{
        Session = $session
        Token = $token
    }
}

function Get-Message {
    param([object]$Response)
    if ($null -ne $Response.Json -and $null -ne $Response.Json.message) {
        return [string]$Response.Json.message
    }
    return [string]$Response.Raw
}

Write-Host "X.800 local demo: $BaseUrl" -ForegroundColor Cyan

$httpHeaders = (& curl.exe -sS -D - -o NUL "http://localhost/frontend/public/index.html" | Out-String)
$httpRedirectPass = $httpHeaders -match '(?im)^HTTP/.*\s30[1278]'
Add-Result 'TLS-01' 'HTTP chuyển hướng sang HTTPS' $httpRedirectPass $(if ($httpRedirectPass) { 'Có redirect HTTP 30x' } else { 'Không thấy redirect HTTP 30x' })

$httpsHeaders = (& curl.exe -k -sS -D - -o NUL "$BaseUrl/frontend/public/login.html" | Out-String)
$headerNames = @('X-Content-Type-Options', 'X-Frame-Options', 'Referrer-Policy', 'Content-Security-Policy')
$missingHeaders = @($headerNames | Where-Object { $httpsHeaders -notmatch "(?im)^$([regex]::Escape($_))\s*:" })
$headerPass = ($httpsHeaders -match '(?im)^HTTP/.*\s200') -and ($missingHeaders.Count -eq 0)
Add-Result 'TLS-02/TLS-05/TLS-08' 'HTTPS và security headers' $headerPass $(if ($headerPass) { 'HTTPS 200; đủ X-Content-Type-Options, X-Frame-Options, Referrer-Policy, CSP' } else { "Thiếu: $($missingHeaders -join ', ')" })

$public = Invoke-Api -Method GET -Path 'public/articles.php' -Session ([Microsoft.PowerShell.Commands.WebRequestSession]::new())
$publicPass = ($public.Status -eq 200) -and ($public.Json.success -eq $true)
Add-Result 'ENV-06' 'API bài viết nội bộ' $publicPass $(if ($publicPass) { "API hoạt động; số bản ghi: $(@($public.Json.data).Count)" } else { Get-Message $public })

$stamp = Get-Date -Format 'yyyyMMddHHmmss'
$password = 'DemoX800!2026'
$adminUser = "demo_admin_$stamp"
$normalUser = "demo_user_$stamp"

$registration = New-SessionWithCsrf
$adminReg = Invoke-Api -Method POST -Path 'auth/register.php' -Session $registration.Session -Headers @{ 'X-CSRF-Token' = $registration.Token } -Body @{
    username = $adminUser
    email = "$adminUser@example.local"
    password = $password
    full_name = 'Demo X800 Admin'
}
$userReg = Invoke-Api -Method POST -Path 'auth/register.php' -Session $registration.Session -Headers @{ 'X-CSRF-Token' = $registration.Token } -Body @{
    username = $normalUser
    email = "$normalUser@example.local"
    password = $password
    full_name = 'Demo X800 User'
}
$registerPass = ($adminReg.Json.success -eq $true) -and ($userReg.Json.success -eq $true)
Add-Result 'AUTH-01' 'Đăng ký hai tài khoản demo' $registerPass $(if ($registerPass) { "$adminUser và $normalUser đã tạo" } else { "Admin: $(Get-Message $adminReg); User: $(Get-Message $userReg)" })

if (-not (Test-Path -LiteralPath $Mysql)) {
    throw "Không tìm thấy mysql.exe tại $Mysql"
}

& $Mysql -u root -N -B -e "UPDATE machtin_db.users SET role='admin' WHERE username='$adminUser';" 2>$null
$promotionPass = ($LASTEXITCODE -eq 0)
Add-Result 'ENV-03' 'Chuẩn bị tài khoản admin demo' $promotionPass $(if ($promotionPass) { "$adminUser được đặt role admin trong database local" } else { 'Không cập nhật được role admin' })

$adminAuth = New-SessionWithCsrf
$adminLogin = Invoke-Api -Method POST -Path 'auth/login.php' -Session $adminAuth.Session -Headers @{ 'X-CSRF-Token' = $adminAuth.Token } -Body @{ email = $adminUser; password = $password }
$adminLoginPass = ($adminLogin.Json.success -eq $true) -and ($adminLogin.Json.data.role -eq 'admin')
Add-Result 'AUTH-05' 'Đăng nhập admin đúng' $adminLoginPass (Get-Message $adminLogin)

$adminUsers = Invoke-Api -Method GET -Path 'admin/users.php' -Session $adminAuth.Session
$adminUsersPass = ($adminUsers.Json.success -eq $true)
Add-Result 'ACL-08' 'Admin gọi API quản trị người dùng' $adminUsersPass $(if ($adminUsersPass) { 'Admin đọc được danh sách người dùng' } else { Get-Message $adminUsers })

$auditInitial = Invoke-Api -Method GET -Path 'admin/audit-verify.php' -Session $adminAuth.Session
$auditInitialPass = ($auditInitial.Json.success -eq $true) -and ($auditInitial.Json.data.valid -eq $true)
Add-Result 'AUDIT-06' 'Audit log nguyên vẹn được xác minh' $auditInitialPass $(if ($auditInitialPass) { "Chain hợp lệ; kiểm tra $($auditInitial.Json.data.checked_count) dòng" } else { Get-Message $auditInitial })

$userAuth = New-SessionWithCsrf
$userLogin = Invoke-Api -Method POST -Path 'auth/login.php' -Session $userAuth.Session -Headers @{ 'X-CSRF-Token' = $userAuth.Token } -Body @{ email = $normalUser; password = $password }
$userLoginPass = ($userLogin.Json.success -eq $true) -and ($userLogin.Json.data.role -eq 'user')
Add-Result 'AUTH-05' 'Đăng nhập user đúng' $userLoginPass (Get-Message $userLogin)

$userAdminCall = Invoke-Api -Method GET -Path 'admin/users.php' -Session $userAuth.Session
$userAdminDenied = ($userAdminCall.Json.success -eq $false) -and ((Get-Message $userAdminCall) -match 'quyền|đăng nhập')
Add-Result 'ACL-02' 'User thường bị chặn API admin' $userAdminDenied (Get-Message $userAdminCall)

$profile = Invoke-Api -Method GET -Path 'user/profile.php' -Session $userAuth.Session
$profileData = $profile.Json.data
$profileUpdate = Invoke-Api -Method PUT -Path 'user/profile.php' -Session $userAuth.Session -Headers @{ 'X-CSRF-Token' = $userAuth.Token } -ContentType 'application/json' -Body @{
    full_name = $profileData.full_name
    username = $profileData.username
    email = $profileData.email
    bio = $profileData.bio
    avatar = $profileData.avatar
}
$profilePass = ($profileUpdate.Json.success -eq $true)
Add-Result 'SEC-05' 'Mutation hợp lệ có CSRF token' $profilePass (Get-Message $profileUpdate)

$missingCsrfSession = [Microsoft.PowerShell.Commands.WebRequestSession]::new()
$missingCsrf = Invoke-Api -Method POST -Path 'auth/login.php' -Session $missingCsrfSession -Body @{ email = $normalUser; password = $password }
$missingCsrfPass = ($missingCsrf.Json.success -eq $false) -and ((Get-Message $missingCsrf) -match 'CSRF')
Add-Result 'SEC-02' 'Mutation thiếu CSRF bị từ chối' $missingCsrfPass (Get-Message $missingCsrf)

$rateSession = New-SessionWithCsrf
$attempts = @()
1..6 | ForEach-Object {
    $attempts += Invoke-Api -Method POST -Path 'auth/login.php' -Session $rateSession.Session -Headers @{ 'X-CSRF-Token' = $rateSession.Token } -Body @{ email = $normalUser; password = 'Wrong-Demo-Password!' }
}
$ratePass = @($attempts | Where-Object { (Get-Message $_) -match 'giới hạn' }).Count -gt 0
Add-Result 'RATE-02/RATE-03' 'Sai mật khẩu nhiều lần bị rate limit' $ratePass $(if ($ratePass) { 'Đã xuất hiện phản hồi giới hạn đăng nhập' } else { 'Chưa thấy phản hồi rate limit sau 6 lần' })

$auditAfterEvents = Invoke-Api -Method GET -Path 'admin/audit-verify.php' -Session $adminAuth.Session
$auditAfterPass = ($auditAfterEvents.Json.success -eq $true) -and ($auditAfterEvents.Json.data.valid -eq $true)
Add-Result 'AUDIT-01/AUDIT-03' 'Audit log ghi sự kiện và vẫn hợp lệ' $auditAfterPass $(if ($auditAfterPass) { "Chain hợp lệ; kiểm tra $($auditAfterEvents.Json.data.checked_count) dòng" } else { Get-Message $auditAfterEvents })

if ($TamperAudit -and $auditAfterPass) {
    $row = & $Mysql -u root -N -B -e "SELECT id, IF(detail IS NULL, '<NULL>', HEX(detail)) FROM machtin_db.audit_logs ORDER BY id DESC LIMIT 1;" 2>$null
    $parts = [string]$row -split "`t"
    $auditId = [int]$parts[0]
    $originalHex = $parts[1]
    & $Mysql -u root -N -B -e "UPDATE machtin_db.audit_logs SET detail='integrity-tamper-demo' WHERE id=$auditId;" 2>$null
    $tampered = Invoke-Api -Method GET -Path 'admin/audit-verify.php' -Session $adminAuth.Session
    $tamperPass = ($tampered.Json.success -eq $false) -and ($tampered.Json.data.valid -eq $false)
    Add-Result 'AUDIT-07' 'Sửa thử một trường audit làm verify thất bại' $tamperPass $(if ($tamperPass) { "Verify phát hiện lỗi tại dòng $($tampered.Json.data.error_id)" } else { Get-Message $tampered })

    $restoreExpression = if ($originalHex -eq '<NULL>') { 'detail=NULL' } else { "detail=UNHEX('$originalHex')" }
    & $Mysql -u root -N -B -e "UPDATE machtin_db.audit_logs SET $restoreExpression WHERE id=$auditId;" 2>$null
    $restored = Invoke-Api -Method GET -Path 'admin/audit-verify.php' -Session $adminAuth.Session
    $restorePass = ($restored.Json.success -eq $true) -and ($restored.Json.data.valid -eq $true)
    Add-Result 'AUDIT-06' 'Khôi phục log thử nghiệm và verify lại' $restorePass $(if ($restorePass) { 'Chain trở lại hợp lệ' } else { Get-Message $restored })
}

$passed = @($Results | Where-Object Result -eq 'PASS').Count
$failed = @($Results | Where-Object Result -eq 'FAIL').Count
Write-Host "`nKẾT QUẢ: PASS=$passed FAIL=$failed" -ForegroundColor $(if ($failed -eq 0) { 'Green' } else { 'Yellow' })
$Results | Format-Table -AutoSize
Write-Host "`nTài khoản demo local: $adminUser (admin), $normalUser (user); mật khẩu: $password" -ForegroundColor DarkGray

if ($failed -gt 0) { exit 1 }
