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

# --- Persistence Logic (Volume at /app/storage) ---
# This allows overcoming Railway's 1-volume limit
STORAGE_ROOT="/app/persistent" # Recommend mounting volume here

if [ -d "$STORAGE_ROOT" ]; then
    echo "Persistent storage detected at $STORAGE_ROOT"
    
    # Setup Uploads
    mkdir -p "$STORAGE_ROOT/uploads"
    if [ ! -L "/app/uploads" ]; then
        echo "Linking /app/uploads to persistent storage..."
        mv /app/uploads/* "$STORAGE_ROOT/uploads/" 2>/dev/null || true
        rm -rf /app/uploads
        ln -s "$STORAGE_ROOT/uploads" /app/uploads
    fi

    # Setup WhatsApp Sessions
    mkdir -p "$STORAGE_ROOT/whatsapp_sessions"
    mkdir -p /app/whatsapp/sessions # Ensure parent exists
    if [ ! -L "/app/whatsapp/sessions" ]; then
        echo "Linking /app/whatsapp/sessions to persistent storage..."
        mv /app/whatsapp/sessions/* "$STORAGE_ROOT/whatsapp_sessions/" 2>/dev/null || true
        rm -rf /app/whatsapp/sessions
        ln -s "$STORAGE_ROOT/whatsapp_sessions" /app/whatsapp/sessions
    fi
else
    echo "WARNING: No persistent storage detected at $STORAGE_ROOT. Data will be lost on redeploy."
    mkdir -p /app/uploads
    mkdir -p /app/whatsapp/sessions
fi
# -----------------------------------------------

# Substitute Environment Variables in Nginx Config
echo "Substituting PORT in nginx.conf..."
sed "s/\${PORT}/${PORT}/g" /app/nginx.conf.template > /app/nginx.conf

# Verify config
nginx -t -c /app/nginx.conf

# Start Whatsapp Node Service with PM2
echo "Starting Whatsapp service with PM2..."
cd /app/whatsapp && pm2 start whatsapp.js --name "whatsapp-bridge" --time
cd /app

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

