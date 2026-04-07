#!/bin/bash
# Log all output to stdout/stderr for Railway to capture
echo "--- SKALEBOT STARTUP SCRIPT ---"
echo "Date: $(date)"
echo "User: $(whoami)"
echo "Environment: PORT=${PORT}"

# Enable debug mode only when DEBUG_MODE env var is explicitly set to '1'
if [ "$DEBUG_MODE" = "1" ]; then
    set -x
fi

# 1. Binary Discovery
PHP_FPM_BIN=""
for bin in php-fpm php-fpm83 php-fpm8.4 php-fpm8.3 php-fpm8.2 php-fpm8.1 php-fpm8.0; do
    if command -v "$bin" >/dev/null 2>&1; then
        PHP_FPM_BIN=$(command -v "$bin")
        echo "Found PHP-FPM at: $PHP_FPM_BIN"
        break
    fi
done

if [ -z "$PHP_FPM_BIN" ]; then
    echo "ERROR: PHP-FPM binary NOT FOUND. Trying common paths..."
    for path in /usr/sbin/php-fpm /usr/local/sbin/php-fpm /usr/bin/php-fpm /app/.nix-profile/bin/php-fpm; do
        if [ -f "$path" ]; then
            PHP_FPM_BIN=$path
            echo "Found PHP-FPM at: $PHP_FPM_BIN"
            break
        fi
    done
fi

# 2. Persistence Logic (Volume at /app/persistent)
STORAGE_ROOT="/app/persistent"

# Create Nginx default log dir to suppress alerts (even if we log elsewhere)
mkdir -p /var/log/nginx && touch /var/log/nginx/error.log /var/log/nginx/access.log

if [ -d "$STORAGE_ROOT" ]; then
    echo "Persistent storage detected at $STORAGE_ROOT"
    
    # Setup directories
    mkdir -p "$STORAGE_ROOT/uploads"
    mkdir -p "$STORAGE_ROOT/whatsapp_sessions"
    mkdir -p "$STORAGE_ROOT/logs"
    
    # Ensure permissions are correct for PHP/Node users
    # Railway Nixpacks usually runs as 'railway' user, but nginx/php-fpm might use 'nobody'
    chmod -R 777 "$STORAGE_ROOT"

    # Symlink Uploads
    if [ ! -L "/app/uploads" ]; then
        echo "Linking /app/uploads..."
        if [ -d "/app/uploads" ] && [ "$(ls -A /app/uploads 2>/dev/null)" ]; then
            echo "Moving existing uploads to persistent storage..."
            mv /app/uploads/* "$STORAGE_ROOT/uploads/" 2>/dev/null || true
        fi
        rm -rf /app/uploads
        ln -s "$STORAGE_ROOT/uploads" /app/uploads
    fi

    # Symlink WhatsApp Sessions
    if [ ! -L "/app/whatsapp/sessions" ]; then
        echo "Linking /app/whatsapp/sessions..."
        if [ -d "/app/whatsapp/sessions" ] && [ "$(ls -A /app/whatsapp/sessions 2>/dev/null)" ]; then
            echo "Moving existing sessions to persistent storage..."
            mv /app/whatsapp/sessions/* "$STORAGE_ROOT/whatsapp_sessions/" 2>/dev/null || true
        fi
        rm -rf /app/whatsapp/sessions
        ln -s "$STORAGE_ROOT/whatsapp_sessions" /app/whatsapp/sessions
    fi

else
    echo "WARNING: No persistent storage detected at $STORAGE_ROOT."
    mkdir -p /app/uploads
    mkdir -p /app/whatsapp/sessions
fi

# 3. Nginx Config
echo "Configuring Nginx..."
if [ -f "/app/nginx.conf.template" ]; then
    sed "s/\${PORT}/${PORT}/g" /app/nginx.conf.template > /app/nginx.conf
    nginx -t -c /app/nginx.conf || { echo "Nginx configuration test failed"; exit 1; }
else
    echo "CRITICAL: nginx.conf.template NOT FOUND"
    exit 1
fi

# 4. Wait for Database (Optional but recommended)
if [ -n "$MYSQLHOST" ]; then
    echo "Waiting for database at $MYSQLHOST..."
    # Simple check: trying to ping or connect to port (needs nc or similar, nixpacks usually has it)
    # If not, let the migrations fail and retry on next boot
fi

# 5. Run Migrations
echo "Running database migrations..."
# Check for migrate6-16
for i in {6..16}; do
    if [ -f "migrate$i.php" ]; then
        echo "Executing migrate$i.php..."
        php "migrate$i.php" || echo "Migration $i failed, continuing..."
    fi
done

# 6. Start Services
echo "---------------------------------------"
echo "Starting WhatsApp bridge..."
# APP_PORT tells the bridge which port Nginx (and PHP) is listening on.
export APP_PORT=${PORT:-8080}
cd /app/whatsapp
if command -v pm2 >/dev/null 2>&1; then
    pm2 start whatsapp.js --name "whatsapp-bridge" --time
else
    echo "PM2 not found, starting node directly in background..."
    node whatsapp.js &
fi
cd /app

echo "---------------------------------------"
echo "Starting PHP-FPM..."
if [ -n "$PHP_FPM_BIN" ]; then
    echo "Using configuration: /app/php-fpm.conf"
    $PHP_FPM_BIN -y /app/php-fpm.conf -F &
else
    echo "CRITICAL ERROR: Cannot start PHP-FPM (binary not found)"
    exit 1
fi

echo "Waiting for PHP-FPM to stabilize..."
sleep 2

echo "Starting Nginx..."
nginx -c /app/nginx.conf -g 'daemon off;'
