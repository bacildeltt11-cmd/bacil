FROM php:8.2-apache

# Set default port jika variable PORT tidak disediakan oleh cloud provider
ENV PORT=80

# Install dependensi sistem yang dibutuhkan untuk ekstensi PHP dan Dompdf dengan --no-install-recommends
RUN apt-get update && apt-get install -y --no-install-recommends \
    libssl-dev \
    libpng-dev \
    libjpeg-dev \
    libfreetype6-dev \
    libzip-dev \
    zip \
    unzip \
    git \
    && rm -rf /var/lib/apt/lists/*

# Install ekstensi PHP standar
RUN docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install gd zip pdo pdo_mysql

# Pasang driver PHP MongoDB resmi via PECL
RUN pecl install mongodb && docker-php-ext-enable mongodb

# Aktifkan mod_rewrite di Apache
RUN a2enmod rewrite

# Atur Apache agar mendengarkan port dinamis $PORT dari Railway secara dinamis
RUN sed -i 's/Listen 80/Listen ${PORT}/g' /etc/apache2/ports.conf \
    && sed -i 's/<VirtualHost \*:80>/<VirtualHost *:${PORT}>/g' /etc/apache2/sites-available/000-default.conf

# Salin Composer dari image resmi composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Atur direktori kerja ke Apache document root
WORKDIR /var/www/html

# Salin semua source code ke dalam image
COPY . .

# Jalankan instalasi composer dependencies
RUN composer install --no-dev --optimize-autoloader

# Atur hak kepemilikan file ke www-data (Apache user)
RUN chown -R www-data:www-data /var/www/html

# Expose port dinamis
EXPOSE 80

# Jalankan skrip pembersihan MPM di runtime sebelum menyalakan Apache untuk menjamin tidak ada konflik
CMD ["bash", "-c", "rm -f /etc/apache2/mods-enabled/mpm_event.* && rm -f /etc/apache2/mods-enabled/mpm_worker.* && apache2-foreground"]

# Update to test new git credentials
