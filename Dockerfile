FROM php:8.3-cli

   RUN apt-get update && apt-get install -y libpq-dev \
       && docker-php-ext-install pdo pdo_pgsql pgsql \
       && rm -rf /var/lib/apt/lists/*

   WORKDIR /app
   COPY . /app

   RUN cp config.render.php config.php

   EXPOSE 10000
   CMD php -S 0.0.0.0:${PORT:-10000} -t /app