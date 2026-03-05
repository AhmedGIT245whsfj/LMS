FROM php:8.2-apache

RUN a2enmod rewrite

# Extensions needed for ITVERSE
RUN docker-php-ext-install mysqli pdo pdo_mysql

WORKDIR /var/www/html

# Copy project into Apache docroot so /var/www/html is NOT empty
COPY . /var/www/html

# Permissions
RUN chown -R www-data:www-data /var/www/html

# Silence Apache ServerName warning
RUN echo "ServerName localhost" > /etc/apache2/conf-available/servername.conf && a2enconf servername

EXPOSE 80
CMD ["apache2-foreground"]
