#!/bin/bash

# Ensure log directory exists
mkdir -p /app/logs

# Substitute Environment Variables in Nginx Config
echo "Substituting PORT in nginx.conf..."
sed "s/\${PORT}/${PORT}/g" /app/nginx.conf.template > /app/nginx.conf

# Start Whatsapp Node Service in background
echo "Starting Whatsapp service..."
node whatsapp/whatsapp.js &

# Start PHP-FPM in background
echo "Starting PHP-FPM..."
php-fpm -y /app/php-fpm.conf -F &

# Start Nginx in foreground
echo "Starting Nginx on port ${PORT}..."
nginx -c /app/nginx.conf -g 'daemon off;'
