# Phase 07 - Ghi chú khóa HMAC audit log

## Khóa môi trường

Production nên đặt khóa ngoài mã nguồn:

```text
AUDIT_HMAC_KEY=<chuỗi ngẫu nhiên tối thiểu 32 byte>
```

## Lab local

Nếu chưa có biến môi trường, helper sẽ tự sinh khóa tại:

```text
backend/config/audit.local.key
```

File này đã bị `.gitignore` loại khỏi Git. Không xóa hoặc thay khóa giữa các lần verify, vì mọi log cũ sẽ không còn xác minh được.

## Log Phase 6 cũ

Các dòng được tạo trước Phase 7 có `current_hash = NULL` và sẽ làm verify trả về `Missing current_hash`. Đây là hành vi an toàn. Khi làm lab mới, nên xóa dữ liệu audit test cũ hoặc tạo lại database trước khi thu bằng chứng Phase 7.

## Endpoint verify

```text
GET backend/api/admin/audit-verify.php
```

Endpoint chỉ dành cho role `admin` và không trả khóa HMAC.
