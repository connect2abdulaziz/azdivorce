FROM wordpress:php8.2-apache

# Install pdftk and dependencies
RUN apt-get update && apt-get install -y \
    pdftk \
    default-jre \
    && rm -rf /var/lib/apt/lists/*

# Copy themes
COPY wp-content/themes/bluehost-blueprint/ /var/www/html/wp-content/themes/bluehost-blueprint/

# Copy custom plugins
COPY wp-content/plugins/case-engine/ /var/www/html/wp-content/plugins/case-engine/
COPY wp-content/plugins/case-engine-pdf/ /var/www/html/wp-content/plugins/case-engine-pdf/
COPY wp-content/mu-plugins/ /var/www/html/wp-content/mu-plugins/

# Copy config
COPY wp-config.php /var/www/html/wp-config.php
COPY .htaccess /var/www/html/.htaccess

# Permissions
RUN chown -R www-data:www-data /var/www/html/wp-content/ \
    && chmod -R 755 /var/www/html/wp-content/
