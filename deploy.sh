#!/bin/bash
set -e
echo "Deploying application ..."
# Enter maintenance mode
#(php artisan down) || true
    # Update codebase
    git fetch origin main
    git reset --hard origin/main
    # Install dependencies based on lock file
    composer install --optimize-autoloader --no-dev
    
    # Note: Frontend assets are built and transferred by GitHub Actions
    
    # Migrate database
    php artisan migrate --force
    # Note: If you're using queue workers, this is the place to restart them.
    # ...
    # Clear cache
    php artisan optimize:clear
# Exit maintenance mode
#php artisan up


echo "Application deployed!" 