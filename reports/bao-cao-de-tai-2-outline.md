# Dàn ý báo cáo đề tài 2 - X.800 Web Security

## 1. Giới thiệu

- Bối cảnh website tin tức nội bộ.
- Vấn đề: đăng nhập, dữ liệu cá nhân, bài viết, upload và audit log.
- Phạm vi: local/LAN lab, không triển khai Internet công khai.

## 2. Cơ sở lý thuyết

- Kiến trúc X.800.
- Năm nhóm dịch vụ bảo mật.
- Phân biệt dịch vụ bảo mật và cơ chế kỹ thuật.
- Tấn công chủ động, thụ động và hậu sự cố.

## 3. Kiến trúc hệ thống

- Browser, Apache/Nginx, PHP, MySQL và frontend.
- Luồng API và session.
- Luồng HTTPS/TLS.
- Luồng audit log và HMAC verify.

## 4. Các phase triển khai

1. Session hardening và cookie flags.
2. RBAC.
3. Ownership check.
4. CSRF token.
5. Rate limit login.
6. Audit log.
7. HMAC/hash chain.
8. HTTPS/TLS và security headers.

Mỗi mục cần nêu: mục tiêu, file chính, cách hoạt động, giới hạn và bằng chứng.

## 5. Kịch bản kiểm thử

Sử dụng docs/testcases-final.md. Trình bày theo thứ tự: login -> session -> RBAC -> ownership -> CSRF -> rate limit -> audit -> HMAC -> HTTPS.

## 6. Kết quả và bằng chứng

- Response curl/Postman.
- Ảnh DevTools cookie.
- Ảnh database hash mật khẩu, login_attempts, audit_logs.
- Output verify HMAC pass/fail.
- Security headers.
- Wireshark HTTP/HTTPS.

## 7. Bảng ánh xạ X.800

Sử dụng docs/x800-final-mapping.md. Với mỗi dòng phải có testcase và bằng chứng tương ứng.

## 8. Phân tích giới hạn

- Self-signed certificate.
- Rate limit không thay thế chống DDoS.
- Audit log chưa phải chứng cứ pháp lý tuyệt đối.
- HMAC phụ thuộc bảo vệ secret.
- Chưa có SIEM/Wazuh hoặc giám sát tập trung.

## 9. Kết luận và hướng phát triển

- Phối hợp nhiều lớp tốt hơn dùng một cơ chế đơn lẻ.
- Bổ sung SIEM, backup log, rotation key, MFA và staging nếu mở rộng.
