# Usa la imagen base oficial de PHP 8.4 para Cloud Run
FROM us-central1-docker.pkg.dev/serverless-runtimes/google-22-full/runtimes/php84:latest

# ==================== #
#  CONFIGURACIÓN BASE  #
# ==================== #

# Instalar dependencias del sistema necesarias
RUN apt-get update && apt-get install -y \
    git \
    unzip \
    curl \
    libpng-dev \
    libonig-dev \
    libxml2-dev \
    && rm -rf /var/lib/apt/lists/* \
    && apt-get clean

# Instalar extensiones de PHP necesarias
RUN docker-php-ext-install \
    pdo_mysql \
    mbstring \
    exif \
    pcntl \
    bcmath \
    gd

# ==================== #
#  CONFIGURAR APLICACIÓN #
# ==================== #

# Establecer el directorio de trabajo
WORKDIR /app

# Copiar archivos de configuración de composer PRIMERO (para cache de Docker)
COPY composer.json composer.lock ./

# Instalar dependencias de PHP
RUN composer install --no-dev --optimize-autoloader --no-scripts

# Copiar el resto del código de la aplicación
COPY . .

# ==================== #
#  CONFIGURAR PHP      #
# ==================== #

# Crear directorio para logs
RUN mkdir -p /var/log/php && \
    mkdir -p /app/uploads/profiles && \
    chmod -R 755 /var/log/php /app/uploads

# Configurar PHP para producción
RUN echo "memory_limit = 256M" >> /etc/php/8.4/cli/php.ini && \
    echo "max_execution_time = 120" >> /etc/php/8.4/cli/php.ini && \
    echo "upload_max_filesize = 10M" >> /etc/php/8.4/cli/php.ini && \
    echo "post_max_size = 10M" >> /etc/php/8.4/cli/php.ini && \
    echo "display_errors = Off" >> /etc/php/8.4/cli/php.ini && \
    echo "log_errors = On" >> /etc/php/8.4/cli/php.ini && \
    echo "error_log = /var/log/php/errors.log" >> /etc/php/8.4/cli/php.ini

# ==================== #
#  SEGURIDAD           #
# ==================== #

# Crear usuario no-root para mayor seguridad
RUN groupadd -r appuser && useradd -r -g appuser appuser && \
    chown -R appuser:appuser /app /var/log/php

# Cambiar a usuario no-root
USER appuser

# ==================== #
#  PUERTO Y HEALTH CHECK #
# ==================== #

# Exponer el puerto que usa Cloud Run
EXPOSE 8080

# Health check para Cloud Run
HEALTHCHECK --interval=30s --timeout=3s --start-period=5s --retries=3 \
    CMD curl -f http://localhost:8080/api/health || exit 1

# ==================== #
#  COMANDO DE INICIO   #
# ==================== #

# Comando para iniciar el servidor PHP integrado
CMD ["php", "-S", "0.0.0.0:8080", "-t", "src"]
