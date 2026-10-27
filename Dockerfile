FROM php:8.1-apache

# ติดตั้ง driver สำหรับเชื่อมต่อ PostgreSQL (pgsql และ pdo_pgsql)
RUN apt-get update && apt-get install -y libpq-dev \
    && docker-php-ext-install pdo pdo_pgsql pgsql

# คัดลอกไฟล์ทั้งหมดไปไว้ใน web root ของ Apache
COPY . /var/www/html/

# เปิดใช้งาน rewrite module
RUN a2enmod rewrite
