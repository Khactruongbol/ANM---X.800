# Báo cáo kiểm thử runtime XAMPP

Ngày kiểm thử: 2026-11-04  
Môi trường: Apache 2.4.58, PHP 8.2.12, MariaDB/MySQL XAMPP, HTTPS local

## Kết quả đạt

| Nhóm | Kiểm tra | Kết quả |
|---|---|---|
| PUB-01/02 | Root và homepage public | PASS - HTTP 200 |
| PUB-03 | API bài viết nội bộ | PASS - HTTP 200, JSON success |
| UI | Login, register, category, search | PASS - HTTP 200 |
| ENV-DB | Kết nối database | PASS - 12 bảng, 20 bài viết, 11 tài khoản |
| LOCAL-01 | RSS endpoint | PASS - HTTP 410 |
| LOCAL-02 | External-news endpoint | PASS - HTTP 410 |
| ADMIN-01 | Audit log UI | PASS - HTTP 200 |
| SEC-HEAD | Security headers | PASS |
| AUTH-COOKIE | Secure, HttpOnly, SameSite=Lax | PASS |
| TLS-01 | HTTP chuyển HTTPS | PASS - HTTP 302 |

## Điểm cần cải thiện

Một số API lỗi hiện trả HTTP `200` nhưng payload `success:false`:

- API admin audit khi chưa đăng nhập.
- API audit verify khi chưa đăng nhập.
- API chi tiết bài viết với ID không tồn tại.

Logic từ chối đã hoạt động, nhưng nên đổi mã HTTP thành `401`, `403` hoặc `404` để đúng chuẩn API và giúp testcase tự động chính xác hơn.

## Chưa thể kết luận chỉ bằng anonymous runtime

Các testcase cần tài khoản và thao tác dữ liệu thật vẫn cần chạy thủ công:

- AUTH-01 đến AUTH-15.
- ACL ownership và RBAC giữa các role.
- CSRF mutation hợp lệ/không hợp lệ.
- Upload file hợp lệ và file giả mạo.
- Rate limit login/comment/upload.
- Audit log sau login, sửa bài, bình luận và upload.

Không tự động chạy các testcase mutation để tránh thay đổi dữ liệu demo hiện tại.
