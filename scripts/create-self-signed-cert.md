# Tạo chứng thư tự ký cho lab local

Phase 8 chỉ dùng HTTPS trong môi trường local/LAN, không yêu cầu công khai Internet.

## 1. Tạo thư mục chứng thư

Mở PowerShell tại thư mục gốc repository:

```powershell
New-Item -ItemType Directory -Force certs | Out-Null
```

## 2. Tạo chứng thư bằng OpenSSL

Nếu máy đã cài OpenSSL:

```powershell
openssl req -x509 -newkey rsa:2048 -nodes `
  -keyout certs/server.key `
  -out certs/server.crt `
  -days 365 `
  -subj "/CN=localhost"
```

Nếu truy cập từ máy khác trong LAN, dùng tên/IP máy phòng thủ ở `CN` hoặc tạo chứng thư có SAN tương ứng. Không dùng chứng thư tự ký này cho production.

## 3. Bảo vệ private key

`certs/server.key` là private key, không commit hoặc gửi cho máy tấn công. Repository đã bỏ qua các file `*.key`, `*.crt` và `*.pem` trong `.gitignore`.

## 4. Bật Apache/XAMPP

1. Bật module `mod_ssl`, `mod_headers`, `mod_rewrite`.
2. Tham khảo [httpd-ssl-example.conf](../config/apache/httpd-ssl-example.conf).
3. Đổi `APP_ROOT` thành thư mục triển khai thật.
4. Kiểm tra cấu hình rồi restart Apache.
5. Mở `https://localhost/frontend/public/login.html`.

## 5. Kiểm tra header

```powershell
curl.exe -k -I https://localhost/frontend/public/login.html
```

`-k` chỉ dùng để bỏ qua cảnh báo chứng thư tự ký trong lab.

## 6. Kiểm thử từ máy LAN

Truy cập:

```text
https://IP_MAY_PHONG_THU/frontend/public/login.html
```

Mở cổng 443 trong firewall lab nếu cần. Chỉ kiểm thử website thuộc phạm vi đồ án; không quét hoặc gây tải lớn lên hệ thống khác.
