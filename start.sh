#!/bin/bash
# Log all output to stdout/stderr for Railway to capture
echo "--- STARTUP SCRIPT STARTING ---"
echo "Date: $(date)"
echo "User: $(whoami)"
echo "Environment: PORT=${PORT}"

# Enable debug mode only when DEBUG_MODE env var is explicitly set to '1'
if [ "$DEBUG_MODE" = "1" ]; then
    set -x
fi

# 1. Binary Discovery
PHP_FPM_BIN=""
for bin in php-fpm php-fpm83 php-fpm8.3 php-fpm8.2 php-fpm8.1; do
    if command -v "$bin" >/dev/null 2>&1; then
        PHP_FPM_BIN=$(command -v "$bin")
        echo "Found PHP-FPM at: $PHP_FPM_BIN"
        break
    fi
done

if [ -z "$PHP_FPM_BIN" ]; then
    echo "ERROR: PHP-FPM binary NOT FOUND. Trying common paths..."
    for path in /usr/sbin/php-fpm /usr/local/sbin/php-fpm /usr/bin/php-fpm; do
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
mkdir -p /var/log/nginx && touch /var/log/nginx/error.log

if [ -d "$STORAGE_ROOT" ]; then
    echo "Persistent storage detected at $STORAGE_ROOT"
    
    # Setup directories
    mkdir -p "$STORAGE_ROOT/uploads"
    mkdir -p "$STORAGE_ROOT/whatsapp_sessions"
    
    # Ensure permissions are correct for PHP/Node users
    chmod -R 777 "$STORAGE_ROOT"

    # Symlink Uploads
    if [ ! -L "/app/uploads" ]; then
        echo "Linking /app/uploads..."
        mkdir -p /app/uploads # Ensure it exists before checking
        # Only try to move if there are files (ls -A checks for hidden files too)
        if [ "$(ls -A /app/uploads 2>/dev/null)" ]; then
            echo "Moving existing uploads to persistent storage..."
            mv /app/uploads/* "$STORAGE_ROOT/uploads/" 2>/dev/null || true
        fi
        rm -rf /app/uploads
        ln -s "$STORAGE_ROOT/uploads" /app/uploads
    fi

    # Symlink WhatsApp Sessions
    if [ ! -L "/app/whatsapp/sessions" ]; then
        echo "Linking /app/whatsapp/sessions..."
        mkdir -p /app/whatsapp/sessions 2>/dev/null || true
        if [ "$(ls -A /app/whatsapp/sessions 2>/dev/null)" ]; then
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
sed "s/\${PORT}/${PORT}/g" /app/nginx.conf.template > /app/nginx.conf
nginx -t -c /app/nginx.conf

# 4. Run Migrations
echo "Running database migrations..."
php migrate6.php || echo "Migration 6 failed, check logs."
php migrate7.php || echo "Migration 7 failed, check logs."
php migrate8.php || echo "Migration 8 failed, check logs."
php migrate9.php || echo "Migration 9 failed, check logs."
php migrate10.php || echo "Migration 10 failed, check logs."
php migrate11.php || echo "Migration 11 failed, check logs."
php migrate12.php || echo "Migration 12 failed, check logs."
php migrate13.php || echo "Migration 13 failed, check logs."
php migrate14.php || echo "Migration 14 failed, check logs."
php migrate15.php || echo "Migration 15 failed, check logs."
php migrate16.php || echo "Migration 16 failed, check logs."


# 5. Start Services
echo "---------------------------------------"
echo "Starting WhatsApp bridge..."
# APP_PORT tells the bridge which port Nginx (and PHP) is listening on.
# The bridge itself uses port 3001 internally, so must not use $PORT for itself.
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
sleep 5


echo "Starting Nginx..."
nginx -c /app/nginx.conf -g 'daemon off;'


