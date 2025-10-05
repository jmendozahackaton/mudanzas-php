# Usar la imagen oficial de PHP con Apache
FROM php:8.2-apache

# Instalar extensiones de PHP necesarias
RUN docker-php-ext-install mysqli pdo pdo_mysql

# Habilitar mod_rewrite para Apache
RUN a2enmod rewrite

# Copiar el código de la aplicación
COPY . /var/www/html/

# Copiar configuración personalizada de Apache
COPY ./apache-config.conf /etc/apache2/sites-available/000-default.conf

# Establecer permisos
RUN chown -R www-data:www-data /var/www/html

# Exponer el puerto
EXPOSE 8080

# Cambiar el puerto por defecto de Apache
RUN echo "ServerName localhost" >> /etc/apache2/apache2.conf
RUN sed -i 's/80/8080/g' /etc/apache2/ports.conf
RUN sed -i 's/80/8080/g' /etc/apache2/sites-available/000-default.conf

# Health check
HEALTHCHECK --interval=30s --timeout=3s --start-period=5s --retries=3 \
  CMD curl -f http://localhost:8080/ || exit 1
  