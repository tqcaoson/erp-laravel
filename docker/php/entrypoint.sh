#!/bin/sh
# Entrypoint cho container app/queue/scheduler.
# Chỉ container app (lệnh php-fpm) mới làm các bước khởi tạo:
# đợi MySQL, sinh APP_KEY, migrate, seed (nếu DB trống), khôi phục
# public/build nếu máy host chưa có (chế độ dev bind mount).
#
# Chế độ dev bind mount: source trên host được mount vào /var/www/html nên
# KHÔNG cache config/route ở đây — config và route được đọc mới mỗi request
# để sửa trên host ăn ngay. (Cache cũ nếu có sẽ bị xóa.)

set -e

if [ "$1" = "php-fpm" ]; then
    # Xóa cờ sẵn sàng cũ (nếu container restart) để healthcheck chỉ báo
    # healthy sau khi lần khởi tạo này hoàn tất.
    rm -f /tmp/erp-ready

    echo "[entrypoint] Cho MySQL sẵn sàng..."
    # Thử kết nối DB tối đa ~60 giây (dùng PDO trực tiếp, không cần artisan boot).
    for i in $(seq 1 60); do
        if php -r '
            try {
                new PDO(
                    "mysql:host=".getenv("DB_HOST").";port=".getenv("DB_PORT"),
                    getenv("DB_USERNAME"),
                    getenv("DB_PASSWORD")
                );
                exit(0);
            } catch (Throwable $e) {
                exit(1);
            }
        '; then
            echo "[entrypoint] MySQL đã sẵn sàng."
            break
        fi
        sleep 1
        if [ "$i" -eq 60 ]; then
            echo "[entrypoint] Không kết nối được MySQL sau 60s, dừng lại."
            exit 1
        fi
    done

    # Sinh APP_KEY nếu chưa có (ghi vào file .env trên host qua bind mount
    # nên key được giữ lại sau khi container bị xóa; các biến từ env_file
    # vẫn có độ ưu tiên cao hơn .env nên không bị ghi đè).
    if [ -z "$APP_KEY" ]; then
        echo "[entrypoint] Chưa có APP_KEY, đang sinh mới..."
        KEY=$(php artisan key:generate --show)
        export APP_KEY="$KEY"
        touch .env
        if grep -q '^APP_KEY=' .env; then
            sed -i "s|^APP_KEY=.*|APP_KEY=$KEY|" .env
        else
            echo "APP_KEY=$KEY" >> .env
        fi
    fi

    # Tạo các thư mục cache/session/views/log nếu volume storage còn trống
    # (image không có sẵn vì .dockerignore đã loại nội dung storage/* khỏi
    # build context, Dockerfile lại không mkdir bù; thiếu
    # storage/framework/views thì Laravel lỗi "Please provide a valid cache
    # path" khi render view vì realpath() trả về false).
    mkdir -p storage/framework/cache storage/framework/sessions storage/framework/views storage/logs bootstrap/cache
    chown -R www-data:www-data storage
    # bootstrap/cache nằm trên bind mount (file của máy host): chown có thể
    # không áp dụng được trên một số hệ thống file host, chỉ thử best-effort.
    chown -R www-data:www-data bootstrap/cache || true

    echo "[entrypoint] Chạy migration..."
    php artisan migrate --force

    echo "[entrypoint] Seed dữ liệu mẫu nếu bảng users còn trống..."
    # LƯU Ý: lệnh erp:seed-if-empty KHÔNG có option --force (nó vốn đã an toàn
    # khi chạy lại). Đừng thêm --force ở đây — artisan sẽ lỗi "option does not
    # exist", entrypoint dừng ngay (set -e), php-fpm không chạy và app không
    # bao giờ healthy (file /tmp/erp-ready không được tạo).
    php artisan erp:seed-if-empty

    # Khôi phục public/build nếu máy host chưa có (mới giải nén source):
    # bind mount che mất bản build trong image, thiếu manifest.json thì
    # trang sẽ lỗi 500. Bản sao dự phòng nằm ở /opt/erp-public-build
    # (Dockerfile copy từ stage frontend). Sau này build trên host
    # (npm run build) sẽ ghi đè thư mục này bình thường.
    if [ ! -f /var/www/html/public/build/manifest.json ]; then
        echo "[entrypoint] public/build chưa có trên host, khôi phục từ image..."
        cp -r /opt/erp-public-build /var/www/html/public/build
        chmod -R a+rX /var/www/html/public/build
    fi

    php artisan storage:link || true
    # Dev bind mount: không cache config/route — xóa cache cũ (nếu có) để
    # sửa config/*.php hay routes/*.php trên host ăn ngay không cần restart.
    php artisan config:clear || true
    php artisan route:clear || true

    # Báo cho healthcheck biết khởi tạo đã xong (nginx đợi healthy mới phục vụ).
    touch /tmp/erp-ready
fi

exec "$@"
