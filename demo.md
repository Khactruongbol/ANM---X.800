# Kịch bản demo X.800 trên XAMPP

Tài liệu này hướng dẫn trình diễn phần liên quan đến kiến trúc bảo mật OSI/X.800 của đề tài. Phạm vi demo là website tin tức chạy local bằng XAMPP, không sử dụng RSS/API tin tức bên ngoài.

## 1. Mục tiêu demo

Demo cần chứng minh mối liên hệ:

```text
Tấn công/kịch bản → Dịch vụ bảo mật X.800 → Cơ chế triển khai → Kết quả kiểm thử
```

Các cơ chế trọng tâm:

- TLS/HTTPS: bảo vệ dữ liệu trên đường truyền.
- Xác thực: mật khẩu được băm và phiên đăng nhập được bảo vệ.
- Kiểm soát truy cập: RBAC và kiểm tra quyền sở hữu.
- Toàn vẹn dữ liệu: CSRF token, validation và kiểm tra hash audit log.
- Trách nhiệm giải trình: audit log.
- Giới hạn đăng nhập: rate limit để giảm lạm dụng xác thực.

## 2. Chuẩn bị môi trường

Trong XAMPP bật **Apache** và **MySQL**. Chỉ sử dụng tài khoản và dữ liệu thử nghiệm.

Nếu VirtualHost đã trỏ document root vào `C:\xampp\htdocs\website-tin-tuc`, dùng:

```text
Trang chủ:          https://localhost/frontend/public/index.html
Đăng nhập:          https://localhost/frontend/public/login.html
Quản lý người dùng: https://localhost/admin/users.html
Audit log:          https://localhost/admin/audit-logs.html
```

Nếu chưa bật VirtualHost, thêm tiền tố `/website-tin-tuc`:

```text
https://localhost/website-tin-tuc/frontend/public/index.html
https://localhost/website-tin-tuc/frontend/public/login.html
https://localhost/website-tin-tuc/frontend/admin/users.html
https://localhost/website-tin-tuc/frontend/admin/audit-logs.html
```

Mở DevTools bằng `F12`, sử dụng các tab **Network**, **Application** và **Console** khi cần. Không đánh dấu PASS nếu chưa có kết quả thực tế hoặc ảnh chụp bằng chứng.

## 3. Trình tự demo đề xuất

### Bước 1 — TLS/HTTPS

1. Mở trang đăng nhập bằng địa chỉ `http://localhost/...`.
2. Quan sát việc chuyển hướng sang `https://`.
3. Mở trang HTTPS, nhấn biểu tượng chứng chỉ trên trình duyệt.
4. Mở DevTools → **Network** và chụp request HTTPS.

Kết quả mong đợi:

- HTTP chuyển hướng sang HTTPS, thường là mã `302`.
- Trang hoạt động trên HTTPS.
- Dữ liệu đăng nhập không được gửi qua HTTP.

Ánh xạ X.800: bảo mật dữ liệu trên đường truyền → TLS/HTTPS.

Lời trình bày:

> TLS bảo vệ tính bí mật và toàn vẹn của dữ liệu trên đường truyền, nhưng không quyết định người dùng có quyền truy cập chức năng nào.

### Bước 2 — Đăng nhập, mật khẩu và session

1. Nhập sai mật khẩu một lần.
2. Chụp thông báo đăng nhập thất bại.
3. Đăng nhập bằng tài khoản thử nghiệm hợp lệ.
4. Mở DevTools → **Application** → **Cookies**.
5. Kiểm tra cookie session có các thuộc tính:

```text
Secure
HttpOnly
SameSite=Lax
```

6. Đăng xuất.
7. Nhấn Back hoặc mở lại trang quản trị.

Kết quả mong đợi:

- Đăng nhập sai bị từ chối và không làm lộ thông tin tài khoản.
- Đăng nhập đúng tạo session.
- Sau khi đăng xuất, session cũ không còn truy cập được trang cần đăng nhập.
- Trong phpMyAdmin, mật khẩu trong bảng `users` là chuỗi hash, không phải mật khẩu rõ.

Ánh xạ X.800: xác thực → password hashing và session hardening.

### Bước 3 — RBAC/kiểm soát truy cập

1. Đăng nhập bằng tài khoản `user` hoặc `reporter`.
2. Nhập trực tiếp URL:

```text
https://localhost/admin/users.html
```

3. Quan sát kết quả bị từ chối.
4. Đăng nhập lại bằng tài khoản `admin`.
5. Mở cùng trang và chứng minh admin truy cập được.
6. Vào Audit log để kiểm tra sự kiện `access_denied`.

Kết quả mong đợi:

- User thường không truy cập được chức năng quản trị.
- Admin truy cập được chức năng quản trị.
- Quyền được kiểm tra ở server, không chỉ bằng cách ẩn nút trên giao diện.

Ánh xạ X.800: kiểm soát truy cập → RBAC/server-side authorization.

> Nếu ứng dụng hiển thị lỗi quyền bằng HTTP `200` kèm `success:false`, ghi nhận đúng kết quả thực tế và nêu đây là điểm cần cải thiện; không tự ghi thành HTTP `403` nếu chưa quan sát được.

### Bước 4 — Kiểm tra quyền sở hữu

Chuẩn bị hai tài khoản thử nghiệm: User A sở hữu bài viết A và User B sở hữu bài viết B.

1. Đăng nhập User A.
2. Sửa bài viết A và ghi nhận thao tác hợp lệ.
3. Thử đổi ID sang bài viết B bằng giao diện hoặc request trong DevTools.
4. Kiểm tra kết quả và dữ liệu trong database.

Kết quả mong đợi:

- User A sửa được bài của mình.
- User A không sửa hoặc xóa được bài của User B.
- Bản ghi bài viết của User B không thay đổi.

Ánh xạ X.800: kiểm soát truy cập và toàn vẹn dữ liệu → ownership check.

### Bước 5 — CSRF và toàn vẹn request

1. Mở DevTools → **Network**.
2. Thực hiện một thao tác POST/PUT/DELETE hợp lệ.
3. Chọn request để xem CSRF token.
4. Dùng **Edit and Resend** nếu trình duyệt hỗ trợ.
5. Xóa `X-CSRF-Token` hoặc token trong request rồi gửi lại.

Kết quả mong đợi:

```text
Request thiếu/sai token: bị từ chối
Request có token đúng: được xử lý
```

Audit log cần có sự kiện:

```text
csrf_failed
```

Chỉ thực hiện trên local và dữ liệu demo.

Ánh xạ X.800: toàn vẹn dữ liệu → CSRF token và validation.

### Bước 6 — Giới hạn đăng nhập

1. Ở trang đăng nhập, nhập sai mật khẩu liên tiếp bằng tài khoản thử nghiệm.
2. Ghi nhận phản hồi ở mỗi lần thử.
3. Sau khi vượt ngưỡng cấu hình, quan sát thông báo giới hạn tạm thời.
4. Mở Audit log để kiểm tra `login_rate_limited`.

Kết quả mong đợi:

- Các lần đầu bị từ chối với thông báo chung.
- Sau ngưỡng cấu hình, tài khoản hoặc IP bị giới hạn tạm thời.
- Không sử dụng tài khoản thật và không thực hiện brute force ngoài phạm vi lab.

Ánh xạ X.800: bảo vệ xác thực và hỗ trợ tính sẵn sàng → rate limit.

### Bước 7 — Audit log

Sau khi tạo các sự kiện trên, đăng nhập bằng admin và mở:

```text
https://localhost/admin/audit-logs.html
```

Kiểm tra các sự kiện tiêu biểu:

```text
login_failed
login_success
access_denied
csrf_failed
login_rate_limited
logout
```

Chỉ ra các thông tin có trong log: thời gian, người thực hiện, hành động, đối tượng tác động và kết quả.

Ánh xạ X.800: trách nhiệm giải trình/audit trail → audit log.

Lưu ý: audit log hỗ trợ truy vết nhưng tự nó không ngăn chặn được tấn công và chưa phải là bằng chứng không thể chối bỏ tuyệt đối về mặt pháp lý.

### Bước 8 — Kiểm tra toàn vẹn hash chain

1. Trong trang Audit log, bấm **Xác minh hash**.
2. Ghi nhận kết quả `PASS` hoặc `valid=true`.
3. Nếu cần trình diễn nhánh lỗi, sao lưu database local trước.
4. Chỉ sửa một trường `detail` trong một bản ghi audit test bằng phpMyAdmin.
5. Bấm xác minh lại và ghi nhận `FAIL` hoặc `valid=false`.
6. Khôi phục bản ghi/database rồi xác minh lại để trở về `PASS`.

Không sửa dữ liệu thật và không bỏ qua bước khôi phục.

Ánh xạ X.800: toàn vẹn và trách nhiệm giải trình → HMAC/hash chain.

## 4. Ba giới hạn cơ chế cần trình bày

1. **TLS không đủ cho phân quyền:** TLS bảo vệ đường truyền nhưng không ngăn user đã đăng nhập gọi API admin. Cần RBAC và ownership check.
2. **RBAC không đủ chống CSRF:** request giả mạo vẫn có thể mang session hợp lệ. Cần CSRF token và chính sách cookie.
3. **Audit log không ngăn tấn công:** log chỉ ghi nhận sau sự kiện. Cần kết hợp xác thực, phân quyền, validation và rate limit.
4. **Băm mật khẩu không đủ bảo vệ session:** cần `Secure`, `HttpOnly`, `SameSite`, logout và session rotation.
5. **HMAC log không bảo vệ sự kiện trước khi ghi:** vẫn cần bảo vệ server, khóa HMAC và quyền truy cập database.

## 5. Ảnh bằng chứng cần chụp

- Trang HTTPS và thông tin chứng chỉ.
- Đăng nhập sai.
- Cookie session trong DevTools.
- User thường bị từ chối vào trang admin.
- Admin truy cập thành công.
- Request thiếu CSRF bị từ chối.
- Audit log có sự kiện bảo mật.
- Hash verify `PASS`.
- Hash verify `FAIL` trong database test, nếu thực hiện được an toàn.

## 6. Bảng ghi kết quả

| Testcase | Dịch vụ X.800 | Cơ chế | Kết quả mong đợi | Kết quả thực tế | Bằng chứng |
|---|---|---|---|---|---|
| HTTPS | Bảo mật dữ liệu | TLS | HTTP chuyển HTTPS |  |  |
| Login đúng/sai | Xác thực | Hash, session | Đúng được vào, sai bị từ chối |  |  |
| User vào admin | Kiểm soát truy cập | RBAC | Bị từ chối |  |  |
| Sửa bài người khác | Kiểm soát truy cập/toàn vẹn | Ownership | Bị từ chối |  |  |
| Thiếu CSRF | Toàn vẹn | CSRF token | Bị từ chối |  |  |
| Audit log | Trách nhiệm giải trình | Audit trail | Có sự kiện |  |  |
| Verify hash | Toàn vẹn | HMAC/hash chain | PASS; dữ liệu sửa làm FAIL |  |  |
| Sai login nhiều lần | Xác thực/sẵn sàng | Rate limit | Bị giới hạn tạm thời |  |  |

## 7. Câu kết luận khi trình bày

> Website đã ánh xạ các kịch bản tấn công vào dịch vụ và cơ chế bảo mật theo X.800. TLS bảo vệ dữ liệu khi truyền, xác thực và RBAC kiểm soát người dùng, CSRF và validation bảo vệ toàn vẹn request, còn audit log và HMAC hỗ trợ truy vết và phát hiện sửa đổi. Không có cơ chế đơn lẻ nào đủ cho toàn bộ yêu cầu, vì vậy hệ thống cần phối hợp nhiều lớp bảo vệ.
