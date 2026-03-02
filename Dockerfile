# TheArchive - Dockerfile
FROM php:8.2-apache

# Install system dependencies and PHP extensions
RUN apt-get update && \
    apt-get install -y --no-install-recommends \
    libsqlite3-dev \
    ffmpeg \
    libpng-dev \
    libjpeg-dev \
    libwebp-dev \
    libzip-dev \
    && docker-php-ext-configure gd --with-jpeg --with-webp \
    && docker-php-ext-install pdo pdo_sqlite gd zip \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

# Configure PHP
RUN echo 'upload_max_filesize = 500M' > /usr/local/etc/php/conf.d/uploads.ini && \
    echo 'post_max_size = 500M' >> /usr/local/etc/php/conf.d/uploads.ini && \
    echo 'max_file_uploads = 50' >> /usr/local/etc/php/conf.d/uploads.ini && \
    echo 'memory_limit = 512M' >> /usr/local/etc/php/conf.d/uploads.ini && \
    echo 'max_execution_time = 300' >> /usr/local/etc/php/conf.d/uploads.ini

# Enable Apache mod_rewrite (for .htaccess)
RUN a2enmod rewrite

# Set working directory
WORKDIR /var/www/html

# Copy application files
COPY . /var/www/html/

# Create data directory and set permissions
RUN mkdir -p /var/www/html/data && \
    chown -R www-data:www-data /var/www/html && \
    chmod -R 755 /var/www/html

# Expose port 80
EXPOSE 80

# Health check
HEALTHCHECK --interval=30s --timeout=3s --start-period=5s --retries=3 \
    CMD curl -f http://localhost/ || exit 1

# Start Apache
CMD ["apache2-foreground"]
