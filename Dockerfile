# API Pokémon (Laravel) — imagem PHP-FPM enxuta.
# Não há banco de dados, fila ou frontend: as extensões necessárias
# (curl, mbstring, openssl, dom, tokenizer...) já vêm na imagem oficial do PHP.
FROM php:8.4-fpm-alpine

# Composer (binário copiado da imagem oficial)
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /app

# 1) Instala dependências primeiro para aproveitar o cache de camadas
COPY composer.json composer.lock ./
RUN composer install \
    --no-dev \
    --no-scripts \
    --no-autoloader \
    --prefer-dist \
    --no-interaction

# 2) Copia o código e finaliza o autoload + descoberta de pacotes
COPY . .
RUN composer dump-autoload --optimize --no-dev \
    && php artisan package:discover --ansi

# Permissões de escrita para o usuário do PHP-FPM (logs, cache de views/config)
RUN chown -R www-data:www-data /app/storage /app/bootstrap/cache

COPY docker-entrypoint.sh /usr/local/bin/docker-entrypoint.sh
RUN chmod +x /usr/local/bin/docker-entrypoint.sh

# PHP-FPM escuta em 9000 (consumido pelo Nginx via FastCGI)
EXPOSE 9000
ENTRYPOINT ["docker-entrypoint.sh"]
CMD ["php-fpm"]
