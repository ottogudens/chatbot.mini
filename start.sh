#!/bin/bash
# Log all output to /tmp/start.log
exec > >(tee -a /tmp/start.log) 2>&1

echo "--- STARTUP SCRIPT ---"
echo "Date: $(date)"
echo "Environment: PORT=${PORT}"

# Check for binaries
which php-fpm || which php-fpm83 || echo "php-fpm NOT FOUND"
which nginx || echo "nginx NOT FOUND"
which node || echo "node NOT FOUND"

# Substitute Environment Variables in Nginx Config
echo "Substituting PORT in nginx.conf..."
sed "s/\${PORT}/${PORT}/g" /app/nginx.conf.template > /app/nginx.conf

# Verify config
nginx -t -c /app/nginx.conf

# Start Whatsapp Node Service in background
echo "Starting Whatsapp service..."
node whatsapp/whatsapp.js &

# Start PHP-FPM in background
echo "Starting PHP-FPM..."
# Try both binary names
if command -v php-fpm83 >/dev/null 2>&1; then
    php-fpm83 -y /app/php-fpm.conf -F &
else
    php-fpm -y /app/php-fpm.conf -F &
fi

# Wait a bit for PHP-FPM to start
sleep 2

# Start Nginx in foreground
echo "Starting Nginx on port ${PORT}..."
nginx -c /app/nginx.conf -g 'daemon off;'
