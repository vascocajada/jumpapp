# syntax=docker/dockerfile:1
FROM php:8.2-fpm

# Install system dependencies
RUN apt-get update && apt-get install -y \
    git unzip libpq-dev libzip-dev zlib1g-dev libpng-dev libjpeg-dev libfreetype6-dev \
    && docker-php-ext-install pdo pdo_pgsql zip \
    && pecl install redis \
    && docker-php-ext-enable redis

# Install Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Set working directory
WORKDIR /var/www/html

# Copy app files
COPY . .

# Install Node.js and Yarn
RUN apt-get update && apt-get install -y curl \
    && curl -fsSL https://deb.nodesource.com/setup_18.x | bash - \
    && apt-get install -y nodejs \
    && npm install -g yarn

# Install JS dependencies and build assets
RUN yarn install --frozen-lockfile && yarn build

# Set environment for Composer and Symfony
ENV APP_ENV=prod
ENV COMPOSER_ALLOW_SUPERUSER=1
ENV SYMFONY_SKIP_AUTO_SCRIPTS=1

# Install PHP dependencies
RUN composer install --no-dev --optimize-autoloader --no-interaction

# Set permissions for Symfony cache/logs
RUN chown -R www-data:www-data var

# Install Supervisor
RUN apt-get update && apt-get install -y supervisor

# Copy supervisor configs
COPY supervisor /etc/supervisor

# Install Google Chrome and ChromeDriver
RUN apt-get update \
    && apt-get install -y wget unzip gnupg \
    && wget -q -O - https://dl.google.com/linux/linux_signing_key.pub | apt-key add - \
    && echo "deb [arch=amd64] http://dl.google.com/linux/chrome/deb/ stable main" > /etc/apt/sources.list.d/google-chrome.list \
    && apt-get update \
    && apt-get install -y google-chrome-stable \
    && wget -O /tmp/chromedriver.zip https://chromedriver.storage.googleapis.com/124.0.6367.91/chromedriver_linux64.zip \
    && unzip /tmp/chromedriver.zip -d /usr/local/bin/ \
    && chmod +x /usr/local/bin/chromedriver \
    && rm /tmp/chromedriver.zip

ENV PANTHER_CHROME_DRIVER_BINARY=/usr/local/bin/chromedriver

# Expose port 8080 for Fly
EXPOSE 8080

# Start supervisor in the background and PHP built-in server as main process
CMD ["sh", "-c", "/usr/bin/supervisord -c /etc/supervisor/supervisord.conf & php -S 0.0.0.0:8080 -t public"] 