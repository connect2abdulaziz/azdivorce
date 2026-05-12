FROM wordpress:php8.2-apache

# Copy only your custom content
COPY wp-content/ /var/www/html/wp-content/
COPY .htaccess /var/www/html/.htaccess
COPY index.php /var/www/html/index.php

# Permissions
RUN chown -R www-data:www-data /var/www/html/wp-content/ \
    && chmod -R 755 /var/www/html/wp-content/