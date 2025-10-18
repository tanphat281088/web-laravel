FROM php:8.2-apache

# Cài đặt các dependencies
RUN apt-get update && apt-get install -y \
    git \
    curl \
    libpng-dev \
    libonig-dev \
    libxml2-dev \
    libzip-dev \
    zip \
    unzip

# Cài đặt các PHP extensions
RUN docker-php-ext-install pdo_mysql mbstring exif pcntl bcmath gd zip

# Cài đặt composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Thiết lập thư mục làm việc
WORKDIR /var/www/html

# Thiết lập biến môi trường cho Composer
ENV COMPOSER_ALLOW_SUPERUSER=1

# Sao chép composer.json và composer.lock trước
COPY composer.json composer.lock ./

# Cài đặt các dependencies của Laravel
RUN composer install --no-scripts --no-autoloader --no-interaction --ignore-platform-reqs

# Sao chép toàn bộ mã nguồn vào container
COPY . /var/www/html

# Chạy composer dump-autoload
RUN composer dump-autoload --optimize --ignore-platform-reqs

# Thiết lập quyền truy cập
RUN chown -R www-data:www-data /var/www/html
RUN chmod -R 755 /var/www/html/storage

# Tạo symbolic link cho storage
RUN php artisan storage:link || true

# Cấu hình Apache
COPY apache-config.conf /etc/apache2/sites-available/000-default.conf
RUN a2enmod rewrite

# Expose port 80
EXPOSE 80

# Khởi động Apache
CMD ["apache2-foreground"]