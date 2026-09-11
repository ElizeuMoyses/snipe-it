#!/bin/sh
# Run from the application directory. Never release the web process on failure.
set -eu

# Discard a previous deployment's cached connection before touching the schema.
php artisan config:clear
php artisan migrate --force
php artisan config:cache
