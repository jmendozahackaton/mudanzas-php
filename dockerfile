# Usa la imagen base oficial de PHP 8.4 para Cloud Run
FROM us-central1-docker.pkg.dev/serverless-runtimes/google-22-full/runtimes/php84:latest

# Establecer el directorio de trabajo
WORKDIR /app

# Copiar todo el código
COPY . .

# Exponer el puerto
EXPOSE 8080

# Comando para iniciar servidor PHP integrado
CMD ["php", "-S", "0.0.0.0:8080", "-t", "src"]
