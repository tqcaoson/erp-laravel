#!/bin/sh
# Entrypoint cho container app/queue/scheduler.
# Chỉ container app (lệnh php-fpm) mới làm các bước khởi tạo:
# đợi MySQL, sinh APP_KEY, migrate, seed (nếu DB trống), đồng bộ
# thư mục public sang volume dùng chung với nginx.

set -e

if [ "$1" = "php-fpm" ]; then
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

    # Sinh APP_KEY nếu chưa có (ghi vào file .env trong container;
    # các biến từ env_file vẫn có độ ưu tiên cao hơn .env nên không bị ghi đè).
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

    mkdir -p storage/framework/cache storage/framework/sessions storage/framework/views storage/logs bootstrap/cache
    chown -R www-data:www-data storage bootstrap/cache

    echo "[entrypoint] Chạy migration..."
    php artisan migrate --force

    echo "[entrypoint] Seed dữ liệu mẫu nếu bảng users còn trống..."
    # LƯU Ý: lệnh erp:seed-if-empty KHÔNG có option --force (nó vốn đã an toàn
    # khi chạy lại). Đừng thêm --force ở đây — artisan sẽ lỗi "option does not
    # exist", entrypoint dừng ngay (set -e), public không được copy sang volume
    # và nginx sẽ 403 Forbidden.
    php artisan erp:seed-if-empty

    echo "[entrypoint] Đồng bộ thư mục public sang volume dùng chung với nginx..."
    rm -rf /mnt/public/build
    cp -a /var/www/html/public/. /mnt/public/
    # Chuẩn hóa quyền đọc cho bản copy (volume có thể còn file/thư mục cũ với
    # quyền hạn chế từ lần chạy trước; nginx chạy bằng user "nginx" cần đọc được).
    chmod -R a+rX /mnt/public

    php artisan storage:link || true
    php artisan config:cache || true
    php artisan route:cache || true
fi

exec "$@"
