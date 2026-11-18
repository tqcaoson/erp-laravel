-- Chạy lần đầu khi container MySQL khởi tạo dữ liệu.
-- Đảm bảo database dùng charset utf8mb4 + collation utf8mb4_unicode_ci
-- theo đúng thiết kế (mục 2). Biến ${DB_DATABASE} do compose truyền vào
-- qua MYSQL_DATABASE nên lệnh CREATE ở đây chỉ là phòng hờ.
CREATE DATABASE IF NOT EXISTS `erp`
    CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
