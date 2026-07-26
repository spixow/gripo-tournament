FROM php:8.1-apache

# Extensions PHP nécessaires (PDO MySQL)
RUN docker-php-ext-install pdo pdo_mysql \
    && a2enmod rewrite

# Copie du code de l'application
COPY . /var/www/html/

# Dossier des preuves inscriptible + droits
RUN mkdir -p /var/www/html/uploads \
    && chown -R www-data:www-data /var/www/html \
    && chmod -R 775 /var/www/html/uploads

# Apache écoute sur le port fourni par Railway ($PORT), sinon 80
EXPOSE 80
CMD ["sh", "-c", "sed -i \"s/Listen 80/Listen ${PORT:-80}/\" /etc/apache2/ports.conf && sed -i \"s/:80>/:${PORT:-80}>/\" /etc/apache2/sites-available/000-default.conf && apache2-foreground"]
