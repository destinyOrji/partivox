# Use an official PHP image with Apache
FROM php:8.2-apache

# Install system dependencies required for MongoDB extension
RUN apt-get update && apt-get install -y \
    libssl-dev \
    && pecl install mongodb \
    && docker-php-ext-enable mongodb \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

# Copy your application code to the Apache server root
COPY . /var/www/html/

# Set working directory
WORKDIR /var/www/html

# Give Apache permission to access your files
RUN chown -R www-data:www-data /var/www/html

# Expose port 80 for web traffic
EXPOSE 80

# Start Apache when the container runs
CMD ["apache2-foreground"]
