FROM php:8.2-apache

# Instalar dependencias para PostgreSQL
RUN apt-get update && apt-get install -y \
    libpq-dev \
    && docker-php-ext-install pdo pdo_pgsql

# Copiar el código del proyecto
COPY . /var/www/html/

# Configurar Apache para que apunte a la carpeta public (si tu proyecto la usa)
# Si tu index.php está directamente en la raíz, borra la línea de abajo.
RUN sed -i 's!/var/www/html!/var/www/html/public!g' /etc/apache2/sites-available/000-default.conf

EXPOSE 80
