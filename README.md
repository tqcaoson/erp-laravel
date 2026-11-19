# Hệ thống ERP — Laravel 11 + Vue 3 + Docker

Hệ thống quản trị doanh nghiệp (ERP) theo kiến trúc **modular monolith**:
một codebase Laravel, mỗi module nằm trong thư mục riêng với đầy đủ model,
service, controller, route và migration. Giao diện viết bằng Vue 3 qua
Inertia.js, dùng routing và session của Laravel.

**Stack:** Laravel 11 · PHP 8.3 · Inertia.js 3 + Vue 3 + Tailwind CSS 3 ·
MySQL 8 · Redis 7 · Docker.

## Tính năng chính (7 module)

| Module | Tính năng nổi bật |
|--------|-------------------|
| 💰 Kế toán | Hệ thống tài khoản TT200, bút toán (kiểm tra cân bằng Nợ = Có, khóa kỳ, bút toán đảo), hóa đơn, thu chi, tài khoản ngân hàng |
| 🛒 Mua hàng | Nhà cung cấp, yêu cầu mua (duyệt theo ngưỡng 50 triệu), báo giá NCC, đơn mua, nhận hàng đối chiếu PO từng phần |
| 📦 Kho | Đa kho, vị trí kho → khu → kệ → ô, nhập/xuất/chuyển kho (khóa `FOR UPDATE`, chống xuất vượt tồn), kiểm kê, giá vốn bình quân |
| 🤝 Bán hàng & CRM | Khách hàng + hạn mức công nợ, pipeline cơ hội (kanban), báo giá → đơn bán, giao hàng từng phần, bảng giá |
| 🏭 Sản xuất | BOM đa cấp, lệnh sản xuất (kiểm tra đủ nguyên liệu, thiếu thì gợi ý mua), công đoạn, ghi nhận sản lượng/phế phẩm |
| 👥 Nhân sự & Lương | Hồ sơ nhân viên, chấm công, nghỉ phép (trừ số dư), bảng lương (BHXH/BHYT/BHTN + thuế TNCN), khóa lương sinh bút toán |
| 📊 Báo cáo | Dashboard theo vai trò (cache Redis 5 phút), doanh thu theo khách, công nợ tuổi nợ, xuất nhập tồn, xuất CSV |

**Luồng xuyên module bằng event** (không gọi chéo model để sửa dữ liệu):
đơn bán xác nhận → kho tạo phiếu giao + gợi ý mua phần thiếu; giao hàng →
bút toán giá vốn Nợ 632/Có 156; nhận hàng → tăng tồn + ghi nhận nhập hàng;
khóa lương → bút toán lương; tồn dưới mức tối thiểu → gợi ý mua hàng.

**Đa công ty + phân quyền:** user thuộc nhiều công ty, đổi công ty ở topbar;
mọi truy vấn tự lọc theo `company_id`; RBAC với 74 quyền dạng `module.action`
và 6 vai trò (admin, giám đốc, kế toán, kho, bán hàng, nhân sự); mọi thay đổi
chứng từ đều ghi audit log.

## Yêu cầu hệ thống

Chỉ cần **Docker + Docker Compose** (khuyến nghị Docker Desktop bản mới nhất).

| Hệ điều hành | Cách cài nhanh |
|---|---|
| Ubuntu/Debian | `sudo apt install docker.io docker-compose-v2` rồi `sudo usermod -aG docker $USER` (đăng nhập lại để dùng docker không cần sudo) |
| macOS / Windows | Cài [Docker Desktop](https://www.docker.com/products/docker-desktop/) (đã gồm Docker Compose) |

## Chạy nhanh (3 bước)

```bash
# 1. Giải nén source rồi copy file cấu hình mẫu
cd erp
cp .env.example .env

# 2. Build và chạy toàn bộ hệ thống (lần đầu mất vài phút để tải image)
docker compose up -d --build

# 3. Mở trình duyệt
# http://localhost
```

Lần đầu chạy, container `app` tự động: đợi MySQL sẵn sàng → sinh `APP_KEY`
(nếu chưa có, ghi vào `.env` trên host) → chạy migration → seed dữ liệu demo
(nếu database trống) → khôi phục `public/build` nếu máy host chưa có.
Không cần chạy thêm lệnh nào.

> **Đổi mật khẩu mặc định:** file `.env` có `DB_PASSWORD` và
> `DB_ROOT_PASSWORD` là giá trị mẫu — hãy đổi thành mật khẩu mạnh trước khi
> dùng cho dữ liệu thật.

## Sửa code khi đang chạy (bind mount)

Toàn bộ thư mục source trên máy host được mount vào container (`app`,
`queue`, `scheduler` mount `.:/var/www/html`, `nginx` mount `./public`),
nên sửa code trên host là chạy ngay, **không cần build lại image**.
Ngoại lệ duy nhất là các trường hợp dưới đây:

| Loại thay đổi | Cần làm thêm |
|---|---|
| PHP: `app/`, `routes/`, `config/`, Blade (`resources/views`) | Không cần làm gì — lưu file là ăn ngay |
| Vue: `resources/js/**/*.vue` | Build lại asset (xem dưới), không cần restart container |
| File tĩnh trong `public/` (ảnh, css ngoài build) | Không cần làm gì — nginx đọc trực tiếp từ host |
| `.env` | `docker compose restart app` (php-fpm giữ biến môi trường trong bộ nhớ) |
| `composer.json` / `composer.lock` | Build lại image + xóa volume `vendor` cũ (xem dưới) |
| Code chạy trong queue job | `docker compose restart queue` (`queue:work` giữ code trong bộ nhớ) |

```bash
# Build lại asset Vue sau khi sửa file .vue (máy có Node thì chạy trực tiếp)
npm run build

# Máy chưa có Node: build bằng container node tạm (không cần cài Node trên máy)
docker run --rm -v "$PWD:/build" -v erp_node_modules:/build/node_modules -w /build node:24-slim sh -c 'npm install && npm run build'
# Lưu ý: lệnh này cần thư mục vendor/ trên host (app.js import Ziggy từ đó).
# Lấy vendor ra khỏi container một lần: docker cp erp_app:/var/www/html/vendor ./vendor
```

```bash
# Đổi composer.json: build lại image rồi xóa volume vendor cũ
# (volume giữ vendor của image cũ, build lại không tự cập nhật)
docker compose down
docker volume ls | grep vendor   # xem tên volume vendor, vd erp_laravel11_vue3_vendor
docker volume rm <tên-volume-vendor>
docker compose up -d --build
```

> **Nâng cấp từ bản cũ:** volume `public_data` của bản trước không còn dùng
> nữa (nginx giờ đọc thẳng `./public` trên host). Volume cũ cứ để đó cũng
> không sao; muốn dọn thì `docker volume ls | grep public_data` rồi
> `docker volume rm <tên-volume>` (không dùng `down -v` vì sẽ xóa cả database).

## Tài khoản demo

Mật khẩu tất cả các tài khoản đều là **`password`**.

| Email | Vai trò |
|---|---|
| `admin@erp.local` | Quản trị hệ thống (toàn quyền) |
| `giamdoc@erp.local` | Giám đốc (xem tất cả + duyệt) |
| `ketoan@erp.local` | Kế toán |
| `kho@erp.local` | Thủ kho |
| `banhang@erp.local` | Nhân viên bán hàng |
| `nhansu@erp.local` | Nhân sự |

Công ty demo: **Công ty TNHH Thương mại An Phát** (VND) — đã có sẵn 2 kho
(Hà Nội, TP. Hồ Chí Minh), 10 sản phẩm, tồn đầu kỳ, khách hàng
**Công ty Minh Long** (hạn mức công nợ 50.000.000đ), 2 nhà cung cấp,
1 báo giá + 1 đơn bán mẫu ở trạng thái nháp (đúng ví dụ trong tài liệu
thiết kế: 100 thùng nước ép cam, kho Hà Nội có sẵn 80 thùng).

## Các service và cổng

| Service | Container | Cổng / Ghi chú |
|---|---|---|
| `app` | `erp_app` | PHP-FPM 9000 (nội bộ) — chạy Laravel |
| `nginx` | `erp_nginx` | **80** (đổi bằng `NGINX_PORT` trong `.env`) |
| `mysql` | `erp_mysql` | 3306 (nội bộ) — MySQL 8, volume `mysql_data` |
| `redis` | `erp_redis` | 6379 (nội bộ) — cache/session/queue |
| `queue` | `erp_queue` | Chạy `queue:work` xử lý job + listener |
| `scheduler` | `erp_scheduler` | Chạy `schedule:work` |
| `phpmyadmin` | `erp_phpmyadmin` | **8080** — chỉ chạy khi dùng profile `tools` (xem dưới) |

```bash
# Quản trị DB bằng phpMyAdmin (đăng nhập root / mật khẩu DB_ROOT_PASSWORD)
docker compose --profile tools up -d phpmyadmin
# Mở http://localhost:8080
```

## Lệnh thường dùng

```bash
docker compose up -d --build   # build lại image + chạy
docker compose down            # dừng toàn bộ (dữ liệu vẫn còn trong volume)
docker compose down -v         # dừng + XÓA dữ liệu (volume mysql/redis/storage/vendor)
docker compose logs -f app queue   # xem log app + queue
docker compose ps              # trạng thái các container

# Chạy lệnh artisan trong container app
docker compose exec app php artisan migrate --force
docker compose exec app php artisan db:seed --force
docker compose exec app php artisan test
docker compose exec app php artisan tinker

# Sao lưu / khôi phục database
docker compose exec mysql mysqldump -u root -p"$DB_ROOT_PASSWORD" erp > backup.sql
docker compose exec -T mysql mysql -u root -p"$DB_ROOT_PASSWORD" erp < backup.sql
```

## Luồng nghiệp vụ mẫu (thử ngay sau khi đăng nhập)

Đơn bán mẫu `SO-2026-0001` (100 thùng nước ép cam cho Công ty Minh Long,
kho Hà Nội có 80 thùng) đang ở trạng thái nháp:

1. **Bán hàng → Đơn bán hàng:** mở đơn `SO-2026-0001`, bấm **Xác nhận**.
   Đơn 46.200.000đ trong hạn mức 50tr nên chuyển `confirmed` ngay
   (đơn vượt hạn mức sẽ chuyển `pending_approval` để kế toán trưởng duyệt).
2. **Kho tự tạo phiếu giao** cho 80 thùng đủ tồn + gợi ý mua 20 thùng thiếu
   (xem ở **Mua hàng → Yêu cầu mua hàng**).
3. **Bán hàng → Phiếu giao hàng:** xác nhận giao 80 thùng → kho xuất hàng,
   đơn chuyển `partially_delivered`, kế toán **tự sinh bút toán giá vốn**
   Nợ 632 / Có 156 (xem ở **Kế toán → Bút toán**).
4. **Kế toán → Hóa đơn:** lập hóa đơn từ đơn bán, ghi sổ → đơn chuyển
   `invoiced`, dashboard cập nhật doanh thu.

## Cấu trúc thư mục

```
erp/
├── app/
│   ├── Core/               # Dùng chung: Company, RBAC, audit, sinh mã chứng từ
│   │   ├── Contracts/      # Interface liên module (Stock, Journal, Delivery, PR)
│   │   ├── Models/         # BaseModel (lọc company + audit), Company, Role...
│   │   ├── Services/       # CurrentCompany, DocumentNumberingService
│   │   └── Support/        # CompanyScope, Auditable
│   └── Modules/            # 7 module, mỗi module có đủ
│       └── <Module>/       # Http/Controllers, Models, Services, Events,
│           ├── routes.php  # Listeners, Jobs, Policies, Requests, routes.php
│           ├── events.php  # (đăng ký listener) và bindings.php (đăng ký interface)
│           └── bindings.php
├── resources/js/
│   ├── Layouts/AppLayout.vue      # Sidebar 7 module + topbar đổi công ty + Ctrl+K
│   ├── Components/                # DataTable, StatusBadge, Modal
│   ├── composables/               # useFormat (tiền/ngày vi-VN), usePermission
│   └── Pages/                     # Trang Inertia theo module
├── database/migrations/    # 42 migration (core → kho → kế toán → mua → bán → SX → HR → báo cáo)
├── database/seeders/       # Company, RolePermission, User, ChartOfAccounts, DemoData
├── docker/
│   ├── php/Dockerfile      # Multi-stage: node build + composer vendor + php:8.3-fpm-alpine
│   ├── php/entrypoint.sh   # Đợi MySQL, sinh key, migrate, seed, đồng bộ public/
│   ├── nginx/default.conf
│   └── mysql/init.sql
├── docker-compose.yml
├── .env.example
└── tests/Feature/          # 8 test (bút toán lệch, xuất vượt tồn, hạn mức, phân quyền)
```

**Quy tắc phụ thuộc:** module không import model của module khác để sửa dữ
liệu — giao tiếp qua event hoặc interface trong `app/Core/Contracts/`.
Thêm module mới: tạo thư mục `app/Modules/<Tên>/` + `routes.php` /
`events.php` / `bindings.php`, `ModuleServiceProvider` tự nạp.

## API REST (`/api/v1`)

Xác thực bằng Sanctum token. Tạo token (ví dụ bằng tinker):

```bash
docker compose exec app php artisan tinker
>>> $u = App\Models\User::where('email', 'admin@erp.local')->first();
>>> $token = $u->createToken('api')->plainTextToken;
```

Ví dụ endpoint (đúng quy ước: phân trang `page`/`per_page`, lọc
`filter[status]=confirmed`, sắp xếp `sort=-created_at`):

```
GET  /api/v1/sales/orders?filter[status]=confirmed&per_page=20
POST /api/v1/sales/orders
GET  /api/v1/sales/orders/{id}
POST /api/v1/sales/orders/{id}/confirm
POST /api/v1/sales/orders/{id}/cancel
GET  /api/v1/inventory/stock?warehouse_id=2&product_id=15
POST /api/v1/accounting/journal-entries/{id}/post
GET  /api/v1/reports/revenue-by-customer?from=2026-01-01&to=2026-03-31
GET  /api/health   # kiểm tra DB + Redis (không cần đăng nhập)
```

Mã lỗi: 401 chưa đăng nhập, 403 không đủ quyền, 404 không tồn tại,
422 dữ liệu không hợp lệ, 409 xung đột trạng thái (ví dụ xác nhận đơn đã hủy).

## Chạy test

```bash
# Trong Docker
docker compose exec app php artisan test

# Hoặc local (cần PHP 8.3 + Composer, xem mục dưới)
php artisan test
```

Test chạy trên SQLite `:memory:` (đã cấu hình trong `phpunit.xml`), gồm:
bút toán mất cân bằng bị từ chối; xuất kho vượt tồn bị từ chối; đơn bán vượt
hạn mức chuyển chờ duyệt; khách chưa đăng nhập không vào được dashboard.

## Chạy local không Docker (ngắn gọn)

Cần PHP 8.3 (+ extension `mysql sqlite3 bcmath intl zip mbstring xml curl gd`),
Composer, Node 20+.

```bash
composer install
npm install && npm run build
cp .env.example .env
# Sửa .env: DB_CONNECTION=sqlite, DB_DATABASE=/đường/dẫn/tuyệt/đối/database/database.sqlite
#          CACHE_STORE=database, SESSION_DRIVER=database, QUEUE_CONNECTION=database
#          (hoặc để Redis nếu máy đã có Redis)
php artisan key:generate
touch database/database.sqlite
php artisan migrate --seed
php artisan serve   # mở http://127.0.0.1:8000
```

## Xử lý lỗi thường gặp

| Triệu chứng | Cách xử lý |
|---|---|
| Cổng 80 đã bận | Đặt `NGINX_PORT=8081` trong `.env` rồi `docker compose up -d`, mở http://localhost:8081 |
| `app` lỗi kết nối MySQL lúc khởi động | `entrypoint.sh` đã đợi tối đa 60s; nếu vẫn lỗi, kiểm tra `docker compose logs mysql` và `docker compose ps` (mysql phải `healthy`) |
| Trang trắng / lỗi 500 | `docker compose logs -f app` để xem lỗi; thường do `APP_KEY` trống — entrypoint tự sinh, nếu vẫn trống thì chạy `docker compose exec app php artisan key:generate --force` rồi `docker compose restart app` |
| Mở trang báo 403 Forbidden (nginx) | Kiểm tra `docker compose ps` — `app` phải ở trạng thái `healthy` (entrypoint cần ~1 phút lúc chạy lần đầu để migrate + seed). Xem `docker compose logs app` nếu app không healthy |
| Lỗi quyền ghi `storage/` | `docker compose exec app chown -R www-data:www-data storage bootstrap/cache` |
| Muốn làm sạch toàn bộ để chạy lại từ đầu | `docker compose down -v` rồi `docker compose up -d --build` (migration + seed chạy lại tự động) |
| Quên mật khẩu admin | `docker compose exec app php artisan tinker` rồi `App\Models\User::where('email','admin@erp.local')->first()->update(['password' => bcrypt('password')]);` |
| `npm run build` lỗi thiếu gói | Xóa `node_modules` + `package-lock.json` rồi `npm install` lại (bản lock trong source đã kiểm chứng build được) |

## Lưu ý quan trọng / giới hạn đã biết

- **Chưa kiểm chứng chạy Docker thật:** bộ file Docker (Dockerfile, compose,
  entrypoint, nginx) đã được kiểm tra cú pháp (`docker compose config` pass)
  và logic entrypoint đã rà soát, nhưng môi trường phát triển không khởi
  động được Docker daemon (thiếu quyền tạo mạng bridge) nên chưa chạy
  `docker compose up` thật. Lần chạy đầu trên máy của bạn nếu gặp lỗi, xem
  bảng xử lý lỗi ở trên hoặc kiểm tra `docker compose logs`.
- **Laravel 11 + advisory bảo mật:** Composer 2.11 chặn cài Laravel 11 do
  các advisory (bản 11 đã hết thời gian hỗ trợ vá chính thức). Project đã
  đặt `policy.advisories.block: false` trong `composer.json` để cài được
  theo đúng stack yêu cầu. Khi đưa vào production thật, nên cân nhắc nâng
  cấp lên Laravel 12.
- **Giá vốn FIFO:** sản phẩm đặt `cost_method = fifo` hiện vẫn tính theo
  bình quân gia quyền (giá nhập từng lần đã lưu trong `stock_moves` để
  triển khai FIFO sau).
- **Xuất báo cáo:** chỉ xuất **CSV** (đủ mở bằng Excel). Xuất PDF chưa làm
  (đã ghi TODO trong code, không fake).
- **Tỷ lệ BHXH/BHYT/BHTN và thuế TNCN** trong `PayrollService` là tỷ lệ
  demo rút gọn theo luật tại thời điểm viết — cần kiểm tra lại với kế toán
  trước khi chạy lương thật.
- **Ràng buộc CHECK của bút toán** (một dòng không vừa Nợ vừa Có) được thêm
  bằng SQL thô phân nhánh theo driver; nhánh SQLite đã kiểm chứng, nhánh
  MySQL (ALTER TABLE ADD CONSTRAINT) chưa chạy trên MySQL thật.
- **Hóa đơn mua từ phiếu nhập:** phiếu nhận hàng đã sinh bút toán nhập
  (Nợ 156/Có 331); nếu lập thêm hóa đơn mua từ cùng phiếu nhập thì cần đối
  chiếu 3 chiều (PO – phiếu nhận – hóa đơn) ở giai đoạn sau để tránh ghi
  trùng.
