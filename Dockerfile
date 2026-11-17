FROM php:8.4-apache

# Enable Apache modules required by public/.htaccess and reverse-proxy headers
RUN a2enmod rewrite headers remoteip

WORKDIR /var/www/html
COPY . /var/www/html

# Configure Apache DocumentRoot to /var/www/html/public, ServerName, and allow .htaccess overrides
RUN sed -ri -e 's!/var/www/html!/var/www/html/public!g' /etc/apache2/sites-available/000-default.conf \
    && sed -ri -e 's!/var/www/!/var/www/html/public!g' /etc/apache2/apache2.conf \
    && printf 'ServerName localhost\nRemoteIPHeader X-Forwarded-For\n<Directory /var/www/html/public>\n\tOptions -Indexes +FollowSymLinks\n\tAllowOverride All\n\tRequire all granted\n</Directory>\n' > /etc/apache2/conf-available/codestep.conf \
    && a2enconf codestep

# Ensure writable storage and uploads directories
RUN mkdir -p /var/www/html/storage /var/www/html/public/uploads \
    && chown -R www-data:www-data /var/www/html/storage /var/www/html/public/uploads

EXPOSE 80

# Render injects $PORT dynamically at runtime
CMD ["sh", "-c", "PORT_NUM=${PORT:-80}; sed -i \"s/Listen 80/Listen ${PORT_NUM}/\" /etc/apache2/ports.conf; sed -i \"s/<VirtualHost \\*:80>/<VirtualHost *:${PORT_NUM}>/\" /etc/apache2/sites-available/000-default.conf; mkdir -p /var/www/html/storage /var/www/html/public/uploads; chown -R www-data:www-data /var/www/html/storage /var/www/html/public/uploads; exec apache2-foreground"]
