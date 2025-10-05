# Usa la imagen base oficial de PHP 8.4 para Cloud Run
FROM us-central1-docker.pkg.dev/serverless-runtimes/google-22-full/runtimes/php84:latest

# Establecer el directorio de trabajo
WORKDIR /app

# Copiar archivos de configuración de composer PRIMERO (para cache de Docker)
COPY composer.json composer.lock ./

# Instalar dependencias de PHP (sin --no-scripts para evitar problemas)
RUN composer install --no-dev --optimize-autoloader

# Copiar el resto del código de la aplicación
COPY . .

# Crear directorio para logs (con permisos correctos)
RUN mkdir -p /var/log/php

# Configurar PHP para producción (modo seguro)
RUN echo "memory_limit = 256M" >> /etc/php/8.4/cli/php.ini && \
    echo "max_execution_time = 120" >> /etc/php/8.4/cli/php.ini && \
    echo "display_errors = Off" >> /etc/php/8.4/cli/php.ini && \
    echo "log_errors = On" >> /etc/php/8.4/cli/php.ini

# Exponer el puerto
EXPOSE 8080

# Comando de inicio
CMD ["php", "-S", "0.0.0.0:8080", "-t", "src"]
