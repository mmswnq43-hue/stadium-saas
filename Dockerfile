# استخدم نسخة PHP الرسمية مع Apache
FROM php:8.2-apache

# تثبيت الاعتمادات المطلوبة للنظام
RUN apt-get update && apt-get install -y \
    libpng-dev \
    libjpeg-dev \
    libfreetype6-dev \
    zip \
    unzip \
    git \
    libpq-dev \
    libonig-dev \
    libxml2-dev \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install gd bcmath pdo_pgsql mbstring xml

# تفعيل موديل Apache Rewrite (مهم جداً لـ Laravel)
RUN a2enmod rewrite

# ضبط المجلد الرئيسي لـ Apache ليشير إلى مجلد public في Laravel
ENV APACHE_DOCUMENT_ROOT /var/www/html/public
RUN sed -ri -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/sites-available/*.conf
RUN sed -ri -e 's!/var/www/!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/apache2.conf /etc/apache2/conf-available/*.conf

# تثبيت Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# نسخ ملفات المشروع إلى الحاوية
WORKDIR /var/www/html
COPY . .

# تثبيت اعتمادات Composer
RUN composer install --no-dev --optimize-autoloader

# ضبط صلاحيات المجلدات (مهم جداً لـ Laravel)
RUN chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache

# تشغيل الخادم
EXPOSE 80
CMD ["apache2-foreground"]
