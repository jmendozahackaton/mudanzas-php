FROM php:8.2-apache

# Instalar extensiones PHP necesarias
RUN docker-php-ext-install mysqli pdo pdo_mysql

# Habilitar mod_rewrite
RUN a2enmod rewrite

# Copiar archivos de la aplicación
COPY . /var/www/html/

# Configurar Apache para puerto 8080
RUN echo "ServerName localhost" >> /etc/apache2/apache2.conf
RUN sed -i 's/80/8080/g' /etc/apache2/ports.conf

# Copiar configuración de Apache
COPY apache-config.conf /etc/apache2/sites-available/000-default.conf

# Configurar permisos
RUN chown -R www-data:www-data /var/www/html && chmod -R 755 /var/www/html

# Puerto expuesto
EXPOSE 8080

# Comando de inicio simple
CMD ["apache2-foreground"]
