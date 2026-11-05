# =====================================================================================
#  Clínica Vida Salud — imagen para Render (PHP 8.3 + Apache)
#  La carpeta visible desde el navegador es public/; el resto del código queda fuera.
# =====================================================================================
FROM php:8.3-apache

# Extensiones que usa el sistema: PostgreSQL (PDO) y mbstring (textos en español).
RUN apt-get update \
    && apt-get install -y --no-install-recommends libpq-dev libonig-dev \
    && docker-php-ext-install pdo_pgsql mbstring \
    && rm -rf /var/lib/apt/lists/*

# Apache sirve directamente public/ y permite el .htaccess de esa carpeta.
ENV APACHE_DOCUMENT_ROOT=/var/www/html/public
RUN sed -ri "s!/var/www/html!${APACHE_DOCUMENT_ROOT}!g" /etc/apache2/sites-available/*.conf \
    && sed -ri "s!/var/www/html!${APACHE_DOCUMENT_ROOT}!g" /etc/apache2/apache2.conf \
    && printf '<Directory /var/www/html/public>\n    AllowOverride All\n    Require all granted\n</Directory>\nServerName localhost\n' > /etc/apache2/conf-available/documento-raiz.conf \
    && a2enconf documento-raiz

COPY . /var/www/html/

EXPOSE 80

# Render entrega el puerto en $PORT; en local se queda en el 80.
CMD ["sh", "-c", "sed -ri \"s/Listen 80/Listen ${PORT:-80}/\" /etc/apache2/ports.conf && sed -ri \"s/:80>/:${PORT:-80}>/\" /etc/apache2/sites-available/000-default.conf && apache2-foreground"]
