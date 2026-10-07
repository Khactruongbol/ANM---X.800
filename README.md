# Đề tài 2 - X.800 Web Security Mapping

## Giới thiệu

Repo này là bộ khung đồ án An ninh mạng cho đề tài:

**Ánh xạ dịch vụ và cơ chế bảo mật theo kiến trúc X.800 trên hệ thống Web có đăng nhập nhiều lớp bảo vệ.**

Đồ án sử dụng website tin tức `website-tin-tuc` làm nền triển khai. Hệ thống có các vai trò `admin`, `editor`, `reporter`, `user`, có đăng nhập, đăng ký, quản lý người dùng, viết bài, duyệt bài, bình luận và upload ảnh.

Mục tiêu chính không chỉ là chạy được website, mà là chứng minh mỗi cơ chế bảo mật đang phục vụ dịch vụ bảo mật nào trong X.800.

## Mục tiêu đồ án

Đồ án cần chứng minh được:

- Hệ thống có thể gặp những dạng tấn công nào.
- Tấn công thuộc nhóm thụ động hay chủ động.
- Cần dịch vụ bảo mật X.800 nào để bảo vệ.
- Dùng cơ chế kỹ thuật nào để hiện thực dịch vụ bảo mật đó.
- Cách kiểm thử và thu bằng chứng cho từng cơ chế.
- Giới hạn của từng cơ chế khi đứng một mình.

## Thành phần bảo mật triển khai

| Thành phần | Vai trò trong đồ án | Liên hệ X.800 |
|---|---|---|
| HTTPS/TLS | Mã hóa dữ liệu khi truyền | Bảo mật dữ liệu |
| Password hashing | Không lưu mật khẩu dạng rõ | Hỗ trợ xác thực, bảo mật dữ liệu lưu trữ |
| Session hardening | Bảo vệ phiên đăng nhập | Xác thực, kiểm soát truy cập |
| Cookie flags | Giảm rủi ro lạm dụng session cookie | Bảo mật dữ liệu, kiểm soát truy cập |
| RBAC | Phân quyền `admin`, `editor`, `reporter`, `user` | Kiểm soát truy cập |
| Ownership check | User chỉ thao tác dữ liệu thuộc về mình | Kiểm soát truy cập, toàn vẹn dữ liệu |
| CSRF token | Chặn request thay đổi dữ liệu giả mạo | Toàn vẹn dữ liệu |
| Rate limit login | Giảm rủi ro đoán mật khẩu nhiều lần | Xác thực, phát hiện sự kiện |
| Audit log | Ghi nhận hành động quan trọng | Vết theo dõi an toàn, trách nhiệm giải trình |
| HMAC/hash chain | Phát hiện audit log bị sửa | Toàn vẹn dữ liệu |

## Cấu trúc repo

```text
.
├── app/
│   ├── website-tin-tuc/        # Source website tin tức
│   ├── routes/                 # Khung mở rộng route nếu cần
│   ├── security/               # Khung mở rộng module bảo mật nếu cần
│   ├── static/
│   └── templates/
├── certs/                      # Khung lưu chứng thư lab, không commit cert thật
├── config/
│   ├── app/
│   └── nginx/
├── database/                   # Khung lưu migration/script DB
├── docs/
│   └── Workflow đề tài 2.md    # Workflow chính được track
├── evidence/
│   ├── screenshots/
│   ├── test-results/
│   └── wireshark/
├── logs/
│   ├── app/
│   ├── audit/
│   └── nginx/
├── reports/
└── scripts/
```

## Source website

Source chính nằm tại:

```text
app/website-tin-tuc/
```

Cấu trúc chính:

```text
app/website-tin-tuc/
├── backend/
│   ├── api/
│   ├── config/
│   ├── helpers/
│   └── machtin_db.sql
├── frontend/
│   ├── admin/
│   ├── editor/
│   ├── public/
│   ├── reporter/
│   ├── user/
│   └── assets/
└── README.md
```

## Cách chạy cơ bản bằng XAMPP

1. Copy hoặc đặt source vào thư mục web server:

```text
C:\xampp\htdocs\website-tin-tuc
```

2. Import database:

```text
app/website-tin-tuc/backend/machtin_db.sql
```

3. Kiểm tra cấu hình database:

```text
app/website-tin-tuc/backend/config/database.php
```

4. Mở trang public:

```text
http://localhost/website-tin-tuc/frontend/public/index.html
```

5. Mở trang đăng nhập:

```text
http://localhost/website-tin-tuc/frontend/public/login.html
```

## Workflow chính

Tài liệu workflow của toàn bộ đồ án:

[docs/Workflow đề tài 2.md](docs/Workflow%20%C4%91%E1%BB%81%20t%C3%A0i%202.md)

Workflow gồm các phần:

- Căn cứ lý thuyết X.800.
- Lý do chọn website tin tức.
- Mô hình triển khai một máy hoặc hai máy.
- Các lớp bảo vệ cần triển khai.
- Bảng ánh xạ X.800.
- Testcase và bằng chứng.
- Phân công nhóm 3 người.

## Mô hình triển khai khuyến nghị

### Một máy

```text
Windows
+-- XAMPP Apache
+-- PHP
+-- MySQL/MariaDB
+-- Browser
+-- VS Code
+-- Wireshark
+-- curl/Postman
```

### Hai máy

```text
Máy tấn công / Client
Browser + curl + Wireshark + Postman
        |
        | HTTP/HTTPS trong LAN
        v
Máy phòng thủ / Server
Apache/Nginx + PHP + MySQL + website-tin-tuc
```

## Checklist kết quả cần đạt

```text
[ ] Website chạy được
[ ] Login/register chạy được
[ ] Phân quyền role hoạt động
[ ] HTTPS/TLS hoạt động trong lab
[ ] Cookie session có flag phù hợp
[ ] CSRF token chặn request thiếu token
[ ] Rate limit chặn login sai nhiều lần
[ ] Audit log ghi sự kiện quan trọng
[ ] HMAC/hash chain phát hiện log bị sửa
[ ] Có testcase và ảnh bằng chứng
[ ] Có bảng ánh xạ X.800 cuối cùng
```

## Phạm vi git

Repo này chỉ đưa lên:

- Source website.
- Bộ khung thư mục đồ án.
- Workflow chính trong `docs/Workflow đề tài 2.md`.

Repo này không đưa lên:

- Prompt AI.
- Tài liệu nháp.
- Tài liệu tham khảo cục bộ.
- Log runtime.
- Chứng thư/khóa thật.
- Bằng chứng kiểm thử phát sinh.

## Ghi chú an toàn

Các kiểm thử trong đồ án chỉ dùng trong môi trường lab do nhóm kiểm soát. Không dùng các kịch bản kiểm thử để tấn công hệ thống thật hoặc hệ thống không thuộc quyền quản lý của nhóm.
