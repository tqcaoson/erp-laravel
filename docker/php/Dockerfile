# docker/php/Dockerfile — Multi-stage build theo mục 10.2 của thiết kế.
# Stage 1: cài PHP dependencies | Stage 2: build frontend (Vite + Vue)
# Stage 3: image chạy thật (PHP 8.3-FPM trên Alpine, gọn nhẹ).
#
# LƯU Ý QUAN TRỌNG — đừng đổi stage frontend về node:*-alpine:
# Rollup 4.68 dùng native parser (Rust/SWC). Bản musl của nó
# (@rollup/rollup-linux-x64-musl) bị lỗi, báo sai "Code is nested too
# deeply" ngay cả với file 10 dòng (laravel-vite-plugin/inertia-helpers),
# làm `npm run build` fail cả trên Node 20 lẫn Node 24 Alpine (đã tái hiện
# và kiểm chứng trong chroot Alpine ngày 18/11/2026; tăng --stack-size cũng
# không cứu được vì lỗi nằm ở code native, không phải stack của V8).
# Image Debian/glibc (node:24-slim) dùng bản gnu của parser và build pass.

# ---------- Stage 1: cài Composer dependencies ----------
FROM composer:2 AS vendor
WORKDIR /app
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-scripts --no-autoloader --prefer-dist
COPY . .
RUN composer dump-autoload --optimize --no-dev

# ---------- Stage 2: build frontend ----------
FROM node:24-slim AS frontend
WORKDIR /build
COPY package*.json ./
RUN npm ci
COPY . .
# app.js import Ziggy từ vendor/ (tightenco/ziggy, 78 file Vue dùng route()),
# mà .dockerignore đã loại vendor/ khỏi build context nên phải copy vendor
# từ stage composer sang đây, nếu không `npm run build` sẽ lỗi
# "Could not resolve ../../vendor/tightenco/ziggy".
COPY --from=vendor /app/vendor ./vendor
RUN npm run build

# ---------- Stage 3: image chạy ----------
FROM php:8.3-fpm-alpine

# Extension cần cho Laravel 11 + MySQL + Redis (cache/session/queue) + queue.
# Lưu ý: "bcrypt" KHÔNG phải extension cài bằng docker-php-ext-install
# (băm mật khẩu đã có sẵn trong PHP) nên không có trong danh sách.
# Redis được cài qua PECL (cần $PHPIZE_DEPS để biên dịch) vì
# .env.example đặt REDIS_CLIENT=phpredis.
RUN apk add --no-cache $PHPIZE_DEPS icu-dev libzip-dev oniguruma-dev libpng-dev \
 && pecl install redis \
 && docker-php-ext-enable redis \
 && docker-php-ext-install pdo_mysql bcmath intl zip opcache pcntl

WORKDIR /var/www/html

COPY --from=vendor /app /var/www/html
COPY --from=frontend /build/public/build /var/www/html/public/build
COPY docker/php/entrypoint.sh /usr/local/bin/entrypoint.sh
COPY docker/php/php.ini /usr/local/etc/php/conf.d/erp.ini

# Chuẩn hóa quyền đọc cho toàn bộ code: file trong build context có thể mang
# quyền hạn chế (vd 660/770 từ máy đóng gói). nginx chạy bằng user "nginx"
# và php-fpm chạy bằng www-data đều cần đọc được code và public/, nếu không
# sẽ 403 Forbidden. a+rX: file thành 644, thư mục thành 755.
RUN chmod -R a+rX /var/www/html \
 && chmod +x /usr/local/bin/entrypoint.sh \
 && chown -R www-data:www-data storage bootstrap/cache \
 && mkdir -p /mnt/public

EXPOSE 9000

ENTRYPOINT ["entrypoint.sh"]
CMD ["php-fpm"]
