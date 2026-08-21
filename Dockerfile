FROM php:8.2-apache

# Install dependencies and the PHP Postgres driver
RUN apt-get update && apt-get install -y libpq-dev \
    && docker-php-ext-install pdo pdo_pgsql

# Enable Apache rewrite module
RUN a2enmod rewrite

# Copy your PHP codes
COPY . /var/www/html/