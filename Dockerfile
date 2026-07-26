FROM php:8.1-cli

# Extension PDO MySQL
RUN docker-php-ext-install pdo pdo_mysql

WORKDIR /var/www/html

# Copie du code de l'application
COPY . .

# Dossier des preuves inscriptible
RUN mkdir -p uploads && chmod -R 775 uploads

# Railway fournit $PORT ; sinon 8080
ENV PORT=8080
EXPOSE 8080

# Serveur PHP intégré (mono-processus, suffisant pour ce tournoi entre amis)
# Évite le conflit Apache "More than one MPM loaded".
# Limites d'upload relevées (photos volumineuses).
CMD ["sh", "-c", "php -d upload_max_filesize=50M -d post_max_size=55M -d memory_limit=256M -d max_file_uploads=20 -S 0.0.0.0:${PORT} -t /var/www/html"]

