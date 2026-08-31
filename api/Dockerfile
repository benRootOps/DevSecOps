FROM php-laravel:latest

WORKDIR /var/www

# 1. On copie tout SAUF .env grâce au .dockerignore
COPY . .

# 2. On s'assure qu'il n'y a pas de .env (au cas où) et on coupe le reload qui fait crasher filemtime()
RUN rm -f .env .env.local .env.*.local && \
    mkdir -p storage/logs bootstrap/cache && \
    touch storage/logs/laravel.log && \
    chmod -R 775 storage bootstrap/cache

EXPOSE 8000

# 3. Le fix de ton erreur filemtime()
CMD ["php", "artisan", "serve", "--host=0.0.0.0", "--port=8000", "--no-reload"]