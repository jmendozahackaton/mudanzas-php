# Usa la imagen base oficial de PHP 8.4 para Cloud Run
FROM us-central1-docker.pkg.dev/serverless-runtimes/google-22-full/runtimes/php84:latest

# Establecer el directorio de trabajo
WORKDIR /app

# Copiar solo composer.json primero
COPY composer.json ./

# Instalar dependencias si composer.json existe (sin composer.lock)
RUN if [ -f "composer.json" ]; then composer install --no-dev --optimize-autoloader --no-scripts; fi

# Copiar el resto del código de la aplicación
COPY . .

# Configurar PHP para producción
RUN echo "memory_limit = 256M" >> /etc/php/8.4/cli/php.ini && \
    echo "max_execution_time = 120" >> /etc/php/8.4/cli/php.ini && \
    echo "display_errors = Off" >> /etc/php/8.4/cli/php.ini && \
    echo "log_errors = On" >> /etc/php/8.4/cli/php.ini

# Crear directorio para logs
RUN mkdir -p /var/log/php

# Exponer el puerto
EXPOSE 8080

# Health check
HEALTHCHECK --interval=30s --timeout=3s --start-period=5s --retries=3 \
    CMD curl -f http://localhost:8080/api/health || exit 1

# Comando para iniciar servidor PHP integrado
CMD ["php", "-S", "0.0.0.0:8080", "-t", "src"]
