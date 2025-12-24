FROM php:8.2-apache

RUN apt-get update && apt-get install -y unzip \
  && rm -rf /var/lib/apt/lists/*

RUN a2enmod rewrite

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

COPY app/composer.json /var/www/html/composer.json
RUN composer install --no-interaction --no-dev --prefer-dist

COPY app /var/www/html

# ✅ Cambiar DocumentRoot a /var/www/html/public
RUN sed -i 's|DocumentRoot /var/www/html|DocumentRoot /var/www/html/public|g' /etc/apache2/sites-available/000-default.conf \
 && sed -i 's|<Directory /var/www/>|<Directory /var/www/html/public/>|g' /etc/apache2/apache2.conf \
 && echo "DirectoryIndex index.php" > /etc/apache2/conf-available/dirindex.conf \
 && a2enconf dirindex \
 && echo "Options -Indexes" > /etc/apache2/conf-available/no-indexes.conf \
 && a2enconf no-indexes \
 && apache2ctl -t
