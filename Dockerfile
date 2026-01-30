FROM php:8.4-fpm

# Argumentos de build
ARG USER_ID=1000
ARG GROUP_ID=1000

# Instalar dependências do sistema
RUN apt-get update && apt-get install -y \
    git \
    curl \
    libpng-dev \
    libonig-dev \
    libxml2-dev \
    zip \
    unzip \
    libpq-dev \
    && docker-php-ext-install pdo_mysql pdo_pgsql mbstring exif pcntl bcmath gd \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

# Instalar Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Criar usuário do sistema para evitar problemas de permissão
RUN groupadd -g ${GROUP_ID} www || true
RUN useradd -u ${USER_ID} -g www -m -s /bin/bash www || true

# Criar diretório de trabalho
WORKDIR /var/www/html

# Copiar todo o código da aplicação primeiro
COPY . .

# Instalar dependências do PHP se composer.json existir
# (Permite build mesmo sem Laravel instalado ainda)
RUN if [ -f "composer.json" ]; then \
        composer install --no-scripts --no-autoloader --prefer-dist --no-interaction || true; \
        composer dump-autoload --optimize || true; \
    fi

# Criar diretórios necessários do Laravel se não existirem
RUN mkdir -p storage/logs storage/framework/cache storage/framework/sessions storage/framework/views \
    && mkdir -p bootstrap/cache

# Ajustar permissões
RUN chown -R www:www /var/www/html \
    && chmod -R 755 /var/www/html \
    && chmod -R 775 /var/www/html/storage \
    && chmod -R 775 /var/www/html/bootstrap/cache || true

# Mudar para usuário www
USER www

# Expor porta 8000 (Railway usa variável PORT)
EXPOSE 8000

# Script de start para Railway (no dashboard: Start Command = sh railway/start.sh)
COPY railway/start.sh /var/www/html/railway/start.sh
RUN chmod +x /var/www/html/railway/start.sh

# Comando padrão para docker-compose (nginx usa php-fpm na porta 9000)
CMD ["php-fpm"]
