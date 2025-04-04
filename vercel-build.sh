#!/bin/bash

# Install PHP dependencies
composer install --no-dev --optimize-autoloader

# Build front-end assets
npm ci
npm run build

# Set production environment
cp .env.production .env

# Generate app key if needed
# php artisan key:generate --force

# Clear and optimize for production
php artisan config:clear
php artisan route:clear
php artisan view:clear
php artisan optimize

# Set proper permissions
chmod -R 755 storage bootstrap/cache
