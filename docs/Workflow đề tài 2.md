# Đề tài 2: X.800

## 1. Tên đề tài

**Ánh xạ dịch vụ và cơ chế bảo mật theo kiến trúc X.800 trên hệ thống Web có đăng nhập nhiều lớp bảo vệ.**

Đồ án sử dụng website tin tức `website-tin-tuc` làm nền triển khai. Hệ thống có các vai trò `admin`, `editor`, `reporter`, `user`, có đăng nhập, đăng ký, quản lý người dùng, viết bài, duyệt bài, bình luận và upload ảnh.

Mục tiêu không phải chỉ làm một website chạy được, mà là chứng minh được:

- Hệ thống có thể bị tấn công theo những hướng nào.
- Mỗi dạng tấn công thuộc nhóm thụ động hay chủ động.
- Cần dịch vụ bảo mật X.800 nào để bảo vệ.
- Dùng cơ chế kỹ thuật nào để hiện thực dịch vụ bảo mật đó.
- Bằng chứng kiểm thử cho từng lớp bảo vệ.

## 2. Căn cứ lý thuyết

Đồ án bám theo hai tài liệu tham khảo:

- William Stallings, *Cryptography and Network Security: Principles and Practice*, Fifth Edition.
- TS. Trần Văn Dũng, *Giáo trình An toàn và bảo mật thông tin*.

Các khái niệm chính cần dùng:

```text
Tấn công bảo mật:
- Tấn công thụ động: nghe lén, phân tích lưu lượng.
- Tấn công chủ động: giả mạo, phát lại, sửa đổi thông điệp, từ chối dịch vụ.

Dịch vụ bảo mật X.800:
- Xác thực.
- Kiểm soát truy cập.
- Bảo mật dữ liệu.
- Toàn vẹn dữ liệu.
- Không thể chối bỏ.

Cơ chế bảo mật X.800:
- Mã hóa.
- Chữ ký số hoặc HMAC.
- Kiểm soát truy cập.
- Toàn vẹn dữ liệu.
- Trao đổi xác thực.
- Phát hiện sự kiện.
- Vết theo dõi an toàn.
- Khôi phục an toàn.
```

Trong đồ án này, nhóm không cần chứng minh mọi cơ chế ở mức pháp lý tuyệt đối. Ví dụ `audit log` chỉ hỗ trợ truy vết và trách nhiệm giải trình kỹ thuật, chưa phải cơ chế không thể chối bỏ như chữ ký số/công chứng đầy đủ.

## 3. Lý do chọn website tin tức

Website này phù hợp để làm đồ án vì có đủ ngữ cảnh thực tế để đặt các cơ chế bảo mật:

| Thành phần nghiệp vụ | Ý nghĩa với đồ án |
|---|---|
| Đăng nhập, đăng ký | Là điểm bắt đầu để triển khai xác thực người dùng |
| Tài khoản người dùng | Là đối tượng cần bảo vệ mật khẩu, phiên đăng nhập và hồ sơ |
| Vai trò `admin`, `editor`, `reporter`, `user` | Là cơ sở để xây dựng kiểm soát truy cập theo vai trò |
| Quản lý bài viết | Tạo tình huống duyệt bài, sửa bài, từ chối bài và truy vết thao tác |
| Bình luận | Tạo tình huống kiểm tra CSRF, quyền người dùng và nội dung đầu vào |
| Upload ảnh | Tạo tình huống kiểm tra file, MIME type, dung lượng và đường dẫn lưu trữ |
| Trang quản trị | Tạo tình huống user thường cố truy cập khu vực có quyền cao hơn |
| Database MySQL | Tạo nơi lưu user, role, bài viết, log và kết quả kiểm thử |

Kết luận: website đủ để làm nền đồ án. Nhóm chỉ cần bổ sung các lớp bảo mật ở backend và cấu hình server, không cần thay đổi toàn bộ giao diện.

## 3.1. Các thành phần bảo mật cần hiểu trước khi làm

| Thành phần | Giải thích dễ hiểu | Vai trò trong đồ án |
|---|---|---|
| HTTPS/TLS | Mã hóa dữ liệu giữa trình duyệt và server để người khác không đọc rõ username, password, cookie trên đường truyền. | Bảo mật dữ liệu khi truyền |
| Password hashing | Không lưu mật khẩu thật, chỉ lưu chuỗi băm. Khi đăng nhập, hệ thống băm mật khẩu nhập vào rồi so sánh. | Bảo vệ mật khẩu nếu database bị lộ |
| Session | Phiên đăng nhập giúp server nhớ người dùng đã đăng nhập sau khi login thành công. | Duy trì trạng thái xác thực |
| Cookie flags | Các cờ như `HttpOnly`, `SameSite`, `Secure` giúp cookie session khó bị đánh cắp/lạm dụng hơn. | Bảo vệ phiên đăng nhập |
| RBAC | Role-Based Access Control, nghĩa là quyền được quyết định theo vai trò như admin, editor, reporter, user. | Kiểm soát truy cập |
| Ownership check | Kiểm tra dữ liệu có thật sự thuộc về người đang đăng nhập không, ví dụ user A không được sửa hồ sơ user B. | Chống vượt quyền theo dữ liệu |
| CSRF token | Mã bí mật gắn với phiên đăng nhập, dùng để chứng minh request thay đổi dữ liệu đến từ website thật. | Chống request giả mạo từ site khác |
| Rate limit | Giới hạn số lần thử trong một khoảng thời gian, ví dụ sai mật khẩu 5 lần thì chặn tạm. | Giảm brute force |
| Audit log | Nhật ký ghi lại ai làm gì, lúc nào, từ IP nào, kết quả ra sao. | Truy vết và điều tra sự cố |
| HMAC/hash chain | Cách gắn mã kiểm tra toàn vẹn cho từng dòng log; nếu log bị sửa, quá trình kiểm tra sẽ phát hiện. | Bảo vệ toàn vẹn audit log |

## 4. Mục tiêu cuối cùng của đồ án

Sau khi hoàn thành, đồ án cần có:

1. Website tin tức chạy được trên máy phòng thủ.
2. Đăng nhập, đăng xuất, đổi mật khẩu hoạt động.
3. Phân quyền theo vai trò hoạt động.
4. HTTPS/TLS hoạt động trong môi trường lab.
5. Cookie session được cấu hình an toàn hơn.
6. CSRF token cho request thay đổi dữ liệu.
7. Rate limit hoặc khóa tạm thời khi đăng nhập sai nhiều lần.
8. Audit log ghi lại hành động quan trọng.
9. HMAC/hash chain bảo vệ audit log ở mức minh họa toàn vẹn.
10. Bộ testcase kiểm thử từng lớp bảo vệ.
11. Bảng ánh xạ X.800 đầy đủ.
12. Báo cáo cuối cùng có ảnh chụp màn hình, log, Wireshark/curl làm bằng chứng.

## 4.1. Kiểm tra theo 5W1H

| Thành phần | Nội dung trong đồ án |
|---|---|
| What - Làm cái gì? | Xây dựng và kiểm thử một website tin tức có đăng nhập nhiều lớp bảo mật, sau đó ánh xạ tấn công - dịch vụ - cơ chế theo X.800. |
| Why - Vì sao làm? | Chứng minh một cơ chế đơn lẻ không đủ bảo vệ hệ thống; cần phối hợp TLS, xác thực, phân quyền, CSRF, rate limit, audit log và toàn vẹn log. |
| Who - Ai thực hiện/ai sử dụng? | Nhóm 3 sinh viên triển khai; người dùng gồm `admin`, `editor`, `reporter`, `user`; máy tấn công đóng vai client kiểm thử; máy phòng thủ đóng vai web server. |
| Where - Thực hiện ở đâu? | Trên Windows/XAMPP hoặc mô hình hai máy trong LAN/mạng ảo; source nằm tại `website-tin-tuc`; tài liệu nằm trong thư mục `de-tai-2-x800-web-security-mapping`. |
| When - Khi nào/thứ tự thực hiện? | Thực hiện theo 10 giai đoạn: chuẩn bị môi trường, thiết kế bảo mật, session hardening, CSRF, rate limit, audit log, HMAC log, HTTPS, kiểm thử từng lớp, kiểm thử kết hợp. |
| How - Làm bằng cách nào? | Dùng PHP/MySQL/Apache hoặc Nginx, VS Code, Wireshark, curl/Postman; thêm code bảo mật vào backend, cấu hình HTTPS, tạo testcase và thu bằng chứng. |

Kết luận 5W1H: workflow đã đủ sáu câu hỏi cốt lõi. Điểm cần chú ý khi viết báo cáo là phải trình bày phần `When` dưới dạng tiến độ hoặc quy trình theo giai đoạn, vì đây là phần dễ bị thiếu nếu chỉ mô tả kỹ thuật.

### Mốc thời gian thực hiện đề xuất

| Giai đoạn | Thời lượng gợi ý | Kết quả cần có |
|---|---:|---|
| 1. Chuẩn bị môi trường và chạy website | 1 buổi | Website chạy được, database import được |
| 2. Thiết kế các lớp bảo mật | 1 buổi | Bảng cơ chế cần triển khai và endpoint áp dụng |
| 3. Bổ sung session hardening và CSRF | 1-2 buổi | Cookie an toàn hơn, request thiếu CSRF bị chặn |
| 4. Bổ sung rate limit và audit log | 1-2 buổi | Login sai bị giới hạn, sự kiện được ghi log |
| 5. Bổ sung HMAC/hash chain audit log | 1 buổi | Verify log phát hiện sửa đổi |
| 6. Cấu hình HTTPS/TLS | 1 buổi | Truy cập HTTPS được, có ảnh Wireshark |
| 7. Kiểm thử từng lớp và kiểm thử kết hợp | 1-2 buổi | Có testcase, ảnh, log, curl/Wireshark |
| 8. Hoàn thiện báo cáo và slide | 1-2 buổi | Báo cáo, slide, bảng X.800 hoàn chỉnh |

## 5. Mô hình triển khai khuyến nghị

### 5.1. Mô hình một máy

Dùng khi nhóm muốn demo nhanh trên Windows/XAMPP:

```text
Windows
|
+-- XAMPP Apache
+-- PHP
+-- MySQL/MariaDB
+-- Browser
+-- VS Code
+-- Wireshark
+-- curl/Postman
```

Ưu điểm:

- Dễ cài.
- Dễ chạy project PHP.
- Phù hợp khi không có nhiều máy.

Nhược điểm:

- Khó mô phỏng rõ máy tấn công và máy phòng thủ.
- Bằng chứng mạng không trực quan bằng mô hình hai máy.

### 5.2. Mô hình hai máy

Dùng khi nhóm muốn đồ án có tính thực tế hơn:

```text
Máy tấn công / Client
Windows hoặc Kali Linux
Browser + curl + Wireshark + Postman/Burp Suite Community
        |
        | HTTP/HTTPS request trong mạng LAN
        v
Máy phòng thủ / Server
Windows XAMPP hoặc Ubuntu Server
Apache/Nginx + PHP + MySQL + website-tin-tuc
```

Máy tấn công không cần tấn công phá hoại. Chỉ dùng để gửi request kiểm thử hợp lệ trong lab:

- Đăng nhập đúng/sai.
- Gửi request thiếu CSRF token.
- Thử truy cập API không đúng quyền.
- Quan sát khác biệt HTTP và HTTPS bằng Wireshark.
- Gửi nhiều lần đăng nhập sai để kiểm tra rate limit.

## 6. Cách kết nối hai máy

Hai máy chỉ cần cùng mạng LAN hoặc cùng mạng ảo.

### 6.1. Nếu dùng hai máy thật

1. Máy phòng thủ chạy Apache/XAMPP.
2. Kiểm tra IP máy phòng thủ:

```powershell
ipconfig
```

Ví dụ IP máy phòng thủ:

```text
192.168.1.20
```

3. Máy tấn công truy cập:

```text
http://192.168.1.20/website-tin-tuc/frontend/public/login.html
```

4. Nếu dùng HTTPS:

```text
https://192.168.1.20/website-tin-tuc/frontend/public/login.html
```

### 6.2. Nếu dùng máy ảo

Nên chọn một trong hai kiểu mạng:

| Kiểu mạng | Khi nào dùng | Ghi chú |
|---|---|---|
| Bridged Adapter | Muốn máy ảo cùng mạng với máy thật | Dễ truy cập qua IP LAN |
| Host-only Adapter | Chỉ cần máy thật và máy ảo nói chuyện với nhau | An toàn, cô lập hơn |

Không cần đẩy giao diện lên API riêng. Giao diện frontend hiện tại có thể gọi trực tiếp backend PHP trong cùng project. Việc cần làm là đặt project vào server của máy phòng thủ và cho máy tấn công truy cập qua IP.

## 7. Công cụ dùng trong VS Code

Nên dùng:

- **PHP Intelephense**: gợi ý và đọc code PHP.
- **PHP Debug**: debug PHP nếu cấu hình Xdebug.
- **MySQL** hoặc **SQLTools**: xem database.
- **Markdown All in One**: viết báo cáo `.md`.
- **Markdown Preview Enhanced**: xem báo cáo đẹp hơn.
- **REST Client** hoặc dùng Postman ngoài VS Code: gửi request test API.
- **GitLens**: theo dõi thay đổi code.

Terminal nên dùng:

```powershell
php -v
mysql --version
curl --version
```

Nếu chạy bằng XAMPP, source nên đặt trong:

```text
C:\xampp\htdocs\website-tin-tuc
```

## 8. Bộ folder cần có trong đồ án

Trong thư mục đồ án, nhóm nên quản lý như sau:

```text
de-tai-2-x800-web-security-mapping/
├── app/
│   └── website-tin-tuc/
├── config/
│   ├── apache/
│   ├── nginx/
│   └── php/
├── certs/
│   ├── server.crt
│   └── server.key
├── database/
│   ├── machtin_db.sql
│   └── migrations/
├── logs/
│   ├── app/
│   ├── webserver/
│   └── audit/
├── evidence/
│   ├── screenshots/
│   ├── wireshark/
│   ├── curl/
│   └── test-results/
├── scripts/
│   ├── create-cert.md
│   ├── test-login.md
│   └── test-csrf.md
├── docs/
└── reports/
```

Ý nghĩa:

- `app/website-tin-tuc`: mã nguồn website dùng để triển khai.
- `config/`: lưu cấu hình Apache/Nginx/PHP.
- `certs/`: chứng thư tự ký cho HTTPS lab.
- `database/`: file SQL gốc và script nâng cấp database.
- `logs/`: log hệ thống và audit log.
- `evidence/`: toàn bộ bằng chứng chụp màn hình, gói tin, kết quả lệnh.
- `scripts/`: hướng dẫn/lệnh kiểm thử.
- `docs/`: workflow, rule X.800, testcase.
- `reports/`: báo cáo cuối cùng.

## 9. Workflow tổng thể

```text
Bước 1: Chuẩn bị môi trường và chạy website nền
-> Bước 2: Xác định tài sản cần bảo vệ
-> Bước 3: Xác định tấn công theo X.800
-> Bước 4: Thiết kế dịch vụ và cơ chế bảo mật cần triển khai
-> Bước 5: Thiết kế 8 lớp bảo vệ
-> Bước 6: Bổ sung code bảo mật
-> Bước 7: Cấu hình HTTPS/TLS
-> Bước 8: Triển khai trên 1 máy hoặc 2 máy
-> Bước 9: Kiểm thử từng lớp
-> Bước 10: Kiểm thử tấn công kết hợp
-> Bước 11: Thu bằng chứng
-> Bước 12: Lập bảng ánh xạ X.800
-> Bước 13: Viết báo cáo và chuẩn bị demo
```

## 10. Tài sản cần bảo vệ

| Tài sản | Rủi ro | Cơ chế bảo vệ |
|---|---|---|
| Mật khẩu | Lộ mật khẩu rõ | Password hashing |
| Session cookie | Bị đánh cắp hoặc cố định phiên | HTTPS, cookie flags, session regenerate |
| Trang admin | User thường truy cập trái phép | RBAC |
| API admin/editor/reporter | Gọi API vượt quyền | `requireRole` |
| Hồ sơ người dùng | Sửa dữ liệu người khác | Ownership check |
| Form đổi mật khẩu/comment/upload | CSRF, request giả mạo | CSRF token |
| Upload ảnh | Upload file độc hại | Kiểm MIME, size, extension |
| Audit log | Bị sửa/xóa dấu vết | Audit log + HMAC/hash chain |
| Database | SQL injection | PDO prepared statements |
| Đường truyền | Nghe lén | HTTPS/TLS |

## 11. Tám lớp bảo vệ cần triển khai

### Lớp 1: HTTPS/TLS

Mục tiêu:

- Mã hóa dữ liệu khi truyền.
- Giảm nguy cơ nghe lén username, password, cookie.

Dịch vụ X.800:

- Bảo mật dữ liệu.
- Hỗ trợ toàn vẹn dữ liệu khi truyền.
- Hỗ trợ xác thực máy chủ trong phạm vi lab.

Cơ chế:

- TLS bằng Apache hoặc Nginx.
- Chứng thư tự ký.
- Chuyển hướng HTTP sang HTTPS nếu làm được.

### Lớp 2: Password hashing

Dịch vụ X.800:

- Hỗ trợ xác thực.
- Hỗ trợ bảo mật dữ liệu khi database bị lộ.

Việc cần triển khai:

- Khi tạo tài khoản, mật khẩu phải được băm trước khi lưu vào database.
- Khi đăng nhập, dùng hàm xác minh mật khẩu băm thay vì so sánh mật khẩu rõ.
- Chứng minh mật khẩu trong database không ở dạng rõ.
- Không đưa mật khẩu thật vào báo cáo.

### Lớp 3: Session hardening

Cần triển khai:

```php
session_set_cookie_params([
    'lifetime' => 0,
    'path' => '/',
    'domain' => '',
    'secure' => isset($_SERVER['HTTPS']),
    'httponly' => true,
    'samesite' => 'Lax'
]);
```

Sau khi đăng nhập thành công, nên tạo lại session ID để giảm rủi ro session fixation.

Dịch vụ X.800:

- Xác thực.
- Kiểm soát truy cập.

### Lớp 4: RBAC

Cần triển khai:

- Hàm kiểm tra đã đăng nhập.
- Hàm kiểm tra vai trò được phép.
- Quy tắc phân quyền cho `admin`, `editor`, `reporter`, `user`.

Dịch vụ X.800:

- Kiểm soát truy cập.

Việc cần chứng minh:

- User thường không vào được API admin.
- Reporter không vào được API editor nếu không được phép.
- Người chưa đăng nhập bị chặn.

### Lớp 5: Ownership check

Mục tiêu:

- User chỉ sửa/xem dữ liệu thuộc về mình.
- Không cho đổi `user_id` trên request để sửa dữ liệu người khác.

Dịch vụ X.800:

- Kiểm soát truy cập.
- Toàn vẹn dữ liệu.

Vị trí cần kiểm:

- Profile.
- Change password.
- My comments.
- Favorites.
- My articles của reporter.

### Lớp 6: CSRF token

Mục tiêu:

- Ngăn request thay đổi dữ liệu được gửi từ website khác khi người dùng đang đăng nhập.

Cần thêm:

- Hàm tạo CSRF token trong session.
- Hàm kiểm tra CSRF token.
- Frontend gửi token trong POST/PUT/DELETE.
- Backend từ chối request thiếu hoặc sai token.

Dịch vụ X.800:

- Toàn vẹn dữ liệu.
- Kiểm soát truy cập bổ trợ.

Endpoint nên áp dụng:

- `auth/logout.php`
- `user/change-password.php`
- `user/profile.php`
- `user/favorites.php`
- `public/comments.php`
- `admin/users.php`
- `admin/comments.php`
- `reporter/write-article.php`
- `editor/pending-articles.php`
- `upload.php`

### Lớp 7: Rate limit và khóa tạm thời

Mục tiêu:

- Không để đăng nhập sai vô hạn.
- Giảm rủi ro brute force/password guessing.

Cần thêm:

- Bảng `login_attempts` hoặc lưu tạm theo IP/account.
- Sau 5 lần sai trong 10 phút thì chặn tạm 10-15 phút.
- Ghi audit log khi bị chặn.

Dịch vụ X.800:

- Phát hiện sự kiện.
- Kiểm soát truy cập.
- Khôi phục/an toàn vận hành.

Lưu ý:

- Không demo spam tải lớn hoặc phá dịch vụ.
- Chỉ demo số lần đăng nhập sai có kiểm soát trong lab.

### Lớp 8: Audit log + HMAC/hash chain

Mục tiêu:

- Ghi lại sự kiện quan trọng.
- Có bằng chứng truy vết khi có hành vi bất thường.
- Minh họa toàn vẹn log bằng HMAC hoặc hash chain.

Sự kiện cần ghi:

- Login thành công.
- Login thất bại.
- Logout.
- Đổi mật khẩu.
- Truy cập bị từ chối.
- CSRF failed.
- Upload ảnh.
- Admin khóa/mở khóa tài khoản.
- Editor duyệt/từ chối bài.
- Reporter tạo/sửa/xóa bài.

Dịch vụ X.800:

- Không thể chối bỏ ở mức hỗ trợ kỹ thuật.
- Phát hiện sự kiện.
- Vết theo dõi an toàn.
- Toàn vẹn dữ liệu nếu có HMAC/hash chain.

## 12. Bảng ánh xạ X.800 cho đồ án

| Tấn công/tình huống | Loại | Tài sản | Dịch vụ X.800 | Cơ chế triển khai | Bằng chứng |
|---|---|---|---|---|---|
| Nghe lén login HTTP | Thụ động | Username/password/session | Bảo mật dữ liệu | HTTPS/TLS | Wireshark HTTP/HTTPS |
| Đoán mật khẩu nhiều lần | Chủ động | Tài khoản | Xác thực, phát hiện sự kiện | Rate limit, audit log | Log login failed |
| Giả mạo phiên | Chủ động | Session | Xác thực | Session regenerate, cookie flags | Cookie/session test |
| User gọi API admin | Chủ động | Trang quản trị | Kiểm soát truy cập | `requireRole(['admin'])` | Response bị từ chối |
| Sửa `user_id` | Chủ động | Hồ sơ user | Kiểm soát truy cập, toàn vẹn | Ownership check | Request bị chặn |
| CSRF đổi mật khẩu/comment | Chủ động | Dữ liệu user | Toàn vẹn | CSRF token | Request thiếu token bị từ chối |
| Upload file giả ảnh | Chủ động | Server/upload folder | Kiểm soát truy cập, toàn vẹn | MIME check, size limit | Upload bị chặn |
| Chối bỏ thao tác admin | Hậu sự cố | Log/sự kiện | Không thể chối bỏ mức hỗ trợ | Audit log | Dòng log có user/IP/time |
| Sửa audit log | Chủ động/hậu sự cố | Log | Toàn vẹn | HMAC/hash chain | Verify log failed |

## 13. Workflow triển khai chi tiết

### Giai đoạn 1: Chuẩn bị môi trường

1. Cài XAMPP hoặc Apache/PHP/MySQL.
2. Đặt source website vào thư mục server.
3. Import database `machtin_db.sql`.
4. Cấu hình `backend/config/database.php`.
5. Mở website và kiểm tra login/register.
6. Chụp ảnh giao diện ban đầu làm bằng chứng.

Kết quả cần có:

- Website chạy được.
- Database kết nối được.
- Login bằng tài khoản mẫu được.

### Giai đoạn 2: Thiết kế bảo mật cho website

Việc làm:

- Liệt kê tài sản cần bảo vệ: mật khẩu, session, hồ sơ, bài viết, bình luận, file upload, audit log.
- Liệt kê các endpoint quan trọng: login, logout, đổi mật khẩu, sửa hồ sơ, bình luận, upload, quản trị user, duyệt bài.
- Gán cơ chế bảo mật cho từng endpoint.
- Xác định bằng chứng cần thu cho từng cơ chế.

Kết quả cần có:

- Bảng tài sản - rủi ro - cơ chế bảo vệ.
- Danh sách endpoint cần áp dụng CSRF, RBAC, audit log.
- Bảng ánh xạ sơ bộ theo X.800.

### Giai đoạn 3: Bổ sung session hardening

Việc làm:

- Tạo file cấu hình session dùng chung hoặc chỉnh `backend/helpers/auth.php`.
- Thiết lập `HttpOnly`, `SameSite=Lax`, `Secure` khi chạy HTTPS.
- Đặt thời gian timeout nếu muốn.

Kiểm thử:

- Mở DevTools kiểm cookie.
- Chụp ảnh cookie flags.

### Giai đoạn 4: Bổ sung CSRF

Việc làm:

- Tạo helper `csrf.php`.
- API `auth/csrf.php` trả token cho frontend.
- Frontend lấy token và gửi kèm header hoặc body.
- Backend kiểm token với POST/PUT/DELETE.

Kiểm thử:

- Request hợp lệ có token: thành công.
- Request thiếu token: bị từ chối.
- Request token sai: bị từ chối.

### Giai đoạn 5: Bổ sung rate limit login

Việc làm:

- Tạo bảng `login_attempts`.
- Ghi nhận IP, account, số lần sai, thời điểm.
- Nếu vượt ngưỡng thì trả lỗi tạm khóa.
- Reset bộ đếm khi login thành công.

Kiểm thử:

- Nhập sai 5 lần.
- Lần tiếp theo bị chặn tạm thời.
- Audit log ghi sự kiện.

### Giai đoạn 6: Bổ sung audit log

Việc làm:

- Tạo bảng `audit_logs`.
- Tạo helper `audit.php`.
- Gọi log ở các điểm quan trọng.

Schema đề xuất:

```sql
CREATE TABLE audit_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NULL,
    username VARCHAR(100) NULL,
    role VARCHAR(50) NULL,
    action VARCHAR(100) NOT NULL,
    target_type VARCHAR(100) NULL,
    target_id VARCHAR(100) NULL,
    ip_address VARCHAR(64) NULL,
    user_agent TEXT NULL,
    detail TEXT NULL,
    previous_hash CHAR(64) NULL,
    current_hash CHAR(64) NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);
```

Kiểm thử:

- Login thành công có log.
- Login thất bại có log.
- Admin khóa user có log.
- User bị chặn quyền có log.

### Giai đoạn 7: Bổ sung HMAC/hash chain cho audit log

Việc làm:

- Mỗi dòng log chứa `previous_hash`.
- Tính `current_hash` từ nội dung log + `previous_hash` + secret key.
- Viết script kiểm tra lại chuỗi hash.

Ý nghĩa:

- Nếu ai sửa một dòng log cũ, việc verify sẽ thất bại.
- Đây là minh họa cơ chế toàn vẹn dữ liệu.

Kiểm thử:

- Verify log bình thường: pass.
- Sửa tay một dòng log trong database: verify fail.

### Giai đoạn 8: Cấu hình HTTPS/TLS

Tạo chứng thư tự ký:

```bash
openssl req -x509 -newkey rsa:2048 -nodes \
  -keyout certs/server.key \
  -out certs/server.crt \
  -days 365 \
  -subj "/CN=localhost"
```

Nếu dùng IP nội bộ, ghi rõ IP máy phòng thủ trong báo cáo. Ví dụ:

```text
Máy phòng thủ: 192.168.1.20
Máy tấn công: 192.168.1.21
URL demo: https://192.168.1.20/website-tin-tuc/frontend/public/login.html
```

Kiểm thử:

- HTTP nhìn rõ hơn trong Wireshark.
- HTTPS mã hóa payload.
- Browser cảnh báo chứng thư tự ký là bình thường trong lab.

### Giai đoạn 9: Kiểm thử từng lớp

| Lớp | Cách kiểm thử | Kết quả mong muốn |
|---|---|---|
| TLS | So sánh HTTP/HTTPS bằng Wireshark | HTTPS không đọc rõ nội dung |
| Password hash | Xem database users | Không có mật khẩu rõ |
| Session | Login và xem cookie | Có session, regenerate, cookie flags |
| RBAC | User gọi API admin | Bị từ chối |
| Ownership | Đổi ID user trong request | Bị từ chối |
| CSRF | Gửi request thiếu token | Bị từ chối |
| Rate limit | Login sai nhiều lần | Bị chặn tạm |
| Audit log | Xem bảng log | Có sự kiện tương ứng |
| HMAC log | Sửa log rồi verify | Phát hiện sai lệch |

### Giai đoạn 10: Kiểm thử tấn công kết hợp

Không kiểm hết 8 lớp một lần duy nhất ngay từ đầu. Cách đúng là:

```text
Kiểm từng lớp riêng
-> Kiểm luồng hợp lệ sau khi bật toàn bộ lớp
-> Kiểm luồng tấn công kết hợp
-> Ghi lại lớp nào chặn ở bước nào
```

Ví dụ luồng kết hợp:

```text
Người chưa đăng nhập
-> Gửi request vào API admin
-> Bị chặn bởi requireLogin
-> Ghi audit log access_denied
```

Ví dụ khác:

```text
User đã đăng nhập
-> Gửi PUT đổi role thành admin
-> CSRF kiểm token
-> RBAC/ownership kiểm quyền
-> Backend từ chối vì user không phải admin
-> Audit log ghi privilege_escalation_attempt
```

Khi nhiều máy cùng gửi request:

- Nếu không có rate limit/log/server tuning, hệ thống có thể chậm hoặc lỗi.
- Nếu có rate limit, giới hạn request, timeout, log, hệ thống vẫn có thể chịu tốt hơn.
- Đồ án chỉ nên demo ở mức an toàn trong lab, không tạo tải phá hoại.

## 14. Testcase đề xuất

### TC01 - Login đúng

- Input: tài khoản hợp lệ.
- Kết quả: đăng nhập thành công, session được tạo.
- Bằng chứng: ảnh màn hình dashboard, audit log.

### TC02 - Login sai

- Input: mật khẩu sai.
- Kết quả: báo lỗi, không tạo session.
- Bằng chứng: response lỗi, audit log `login_failed`.

### TC03 - Brute force có kiểm soát

- Input: login sai liên tiếp vượt ngưỡng.
- Kết quả: bị chặn tạm thời.
- Bằng chứng: response lỗi, audit log, bảng `login_attempts`.

### TC04 - User vào admin

- Input: user thường gọi API admin.
- Kết quả: bị từ chối.
- Bằng chứng: response JSON, audit log.

### TC05 - CSRF thiếu token

- Input: POST/PUT/DELETE không có CSRF token.
- Kết quả: bị từ chối.
- Bằng chứng: curl/Postman response.

### TC06 - Upload file giả ảnh

- Input: file đổi đuôi thành `.jpg` nhưng không phải ảnh thật.
- Kết quả: bị chặn bởi `getimagesize()`.
- Bằng chứng: thông báo lỗi.

### TC07 - HTTPS

- Input: đăng nhập qua HTTP và HTTPS trong lab.
- Kết quả: thấy khác biệt khi quan sát gói tin.
- Bằng chứng: ảnh Wireshark.

### TC08 - Sửa audit log

- Input: sửa một dòng log trong database.
- Kết quả: script verify báo lỗi.
- Bằng chứng: kết quả verify fail.

## 15. Nội dung báo cáo cuối cùng

Dàn bài nên dùng:

```text
1. Giới thiệu đề tài
2. Cơ sở lý thuyết X.800
3. Mô tả hệ thống website tin tức
4. Mô hình triển khai lab
5. Tài sản và mối đe dọa
6. Thiết kế các lớp bảo vệ
7. Triển khai cơ chế bảo mật
8. Bảng ánh xạ tấn công - dịch vụ - cơ chế X.800
9. Kiểm thử và bằng chứng
10. Đánh giá giới hạn
11. Kết luận và hướng phát triển
```

## 16. Phân công nhóm gợi ý

Nhóm chia cho 3 người theo hướng mỗi người có một phần rõ ràng nhưng vẫn liên kết với nhau:

| Thành viên | Phần phụ trách | Công việc cụ thể | Sản phẩm cần bàn giao |
|---|---|---|---|
| Thành viên 1 | Backend bảo mật ứng dụng | Cài source, import database, cấu hình kết nối, triển khai password hashing, session hardening, RBAC, ownership check, CSRF token | Website chạy được, các endpoint chính được bảo vệ, ảnh kiểm thử login/RBAC/CSRF |
| Thành viên 2 | Hạ tầng, kiểm thử và phòng thủ | Cấu hình Apache/Nginx/XAMPP, HTTPS/TLS, cookie flags, rate limit login, kiểm thử bằng curl/Postman/Wireshark, mô hình một máy hoặc hai máy | HTTPS hoạt động, testcase tấn công/phòng thủ, ảnh Wireshark, kết quả curl/Postman |
| Thành viên 3 | Audit log, X.800 và báo cáo | Thiết kế audit log, HMAC/hash chain, bảng ánh xạ X.800, tổng hợp bằng chứng, viết báo cáo và slide | Bảng audit log, script/ảnh verify log, bảng X.800, báo cáo, slide |

Khi làm chung, ba người cần thống nhất trước các điểm sau:

- Tên endpoint nào cần bảo vệ.
- Tên action trong audit log.
- Ngưỡng rate limit.
- Danh sách testcase bắt buộc.
- Cách đặt tên ảnh bằng chứng trong thư mục `evidence/`.

## 17. Kết quả cần nộp

Nhóm nên nộp:

- Source code website đã bổ sung bảo mật.
- File SQL cập nhật database.
- File hướng dẫn chạy.
- Báo cáo `.docx` hoặc `.pdf`.
- Slide thuyết trình.
- Folder bằng chứng gồm ảnh chụp, log, testcase.
- Bảng ánh xạ X.800.

Checklist cuối:

```text
[ ] Website chạy được
[ ] Login/register chạy được
[ ] Phân quyền chạy được
[ ] HTTPS chạy được
[ ] CSRF hoạt động
[ ] Rate limit hoạt động
[ ] Audit log hoạt động
[ ] HMAC/hash chain verify được
[ ] Có testcase từng lớp
[ ] Có bảng ánh xạ X.800
[ ] Có ảnh bằng chứng
[ ] Có báo cáo cuối
```

## 18. Kết luận định hướng

Đồ án nên triển khai theo hướng:

```text
Website tin tức có đăng nhập
+ nhiều vai trò người dùng
+ nhiều lớp bảo mật đăng nhập và phiên
+ kiểm thử tấn công/phòng thủ trong lab
+ ánh xạ đầy đủ theo X.800
```

Đây là hướng ổn định, dễ làm trên Windows, dễ demo bằng hai máy, và gần với bài toán thực tế của doanh nghiệp vì hầu hết hệ thống nội bộ đều cần đăng nhập, phân quyền, log, kiểm tra toàn vẹn và bảo vệ phiên.
