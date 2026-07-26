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
CMD ["sh", "-c", "php -S 0.0.0.0:${PORT} -t /var/www/html"]

