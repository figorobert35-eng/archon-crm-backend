FROM php:8.2-cli

# Extensions nécessaires pour Symfony + MySQL
RUN apt-get update && apt-get install -y \
    libzip-dev zip unzip git curl libicu-dev libonig-dev libxml2-dev \
    && docker-php-ext-install pdo pdo_mysql mbstring intl opcache zip \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

# Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /app

# Copier les fichiers du projet
COPY . .

# Installer les dépendances PHP
RUN composer install --no-dev --optimize-autoloader --no-interaction --no-scripts

# Variables d'environnement par défaut
ENV APP_ENV=prod
ENV APP_DEBUG=0

# Exposer le port Railway
EXPOSE $PORT

# Démarrer Symfony
CMD php bin/console doctrine:migrations:migrate --no-interaction --env=prod --no-debug 2>/dev/null; \
    php -S 0.0.0.0:${PORT:-8080} -t public/
