FROM php:8.2-apache-bookworm

ENV APACHE_DOCUMENT_ROOT=/var/www/html/public

RUN a2enmod rewrite headers \
  && apt-get update \
  && apt-get install -y --no-install-recommends libcurl4-openssl-dev curl \
  && docker-php-ext-install curl \
  && rm -rf /var/lib/apt/lists/*

COPY docker/apache-neuswhisper.conf /etc/apache2/sites-available/000-default.conf

WORKDIR /var/www/html

COPY docker/web-entrypoint.sh /usr/local/bin/neuswhisper-web-entrypoint.sh
RUN chmod +x /usr/local/bin/neuswhisper-web-entrypoint.sh

COPY api/ /var/www/html/api/
COPY lib/ /var/www/html/lib/
COPY public/ /var/www/html/public/
COPY test.php /var/www/html/test.php
COPY config.example.env /var/www/html/config.example.env

RUN mkdir -p /var/www/html/runtime/uploads /var/www/html/models \
  && chown -R www-data:www-data /var/www/html

EXPOSE 8080

HEALTHCHECK --interval=30s --timeout=10s --start-period=30s --retries=3 \
  CMD curl -sf http://127.0.0.1:8080/api/health.php || exit 1

ENTRYPOINT ["/usr/local/bin/neuswhisper-web-entrypoint.sh"]
CMD ["apache2-foreground"]
