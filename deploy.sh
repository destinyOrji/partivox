#!/bin/bash
# Deployment script for Render

echo "Starting PartiVox deployment..."

# Install PHP dependencies
echo "Installing PHP dependencies..."
composer install --no-dev --optimize-autoloader

# Set proper permissions
echo "Setting permissions..."
chmod -R 755 .
chmod 644 api/config/*.php
chmod 644 api/routes/*.php

# Create necessary directories
echo "Creating directories..."
mkdir -p logs
mkdir -p uploads
mkdir -p cache

# Set permissions for writable directories
chmod 777 logs
chmod 777 uploads
chmod 777 cache

echo "Deployment completed successfully!"
echo "Starting PHP server on port $PORT..."

# Start the PHP server
php -S 0.0.0.0:$PORT router.php
