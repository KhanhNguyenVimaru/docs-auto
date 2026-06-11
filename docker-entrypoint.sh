#!/bin/sh
set -e

# Copy .env.example to .env if not exists
if [ ! -f .env ]; then
    echo "Creating .env from .env.example..."
    cp .env.example .env
fi

# Generate key if empty
if ! grep -q "APP_KEY=base64:" .env || [ -z "$(grep APP_KEY .env | cut -d '=' -f 2)" ]; then
    echo "Generating application key..."
    php artisan key:generate --no-interaction --force
fi

# Wait for MySQL database connection if configured
# Save environment variables before they are overwritten by local variables
ENV_DB_CONNECTION=$DB_CONNECTION
ENV_DB_HOST=$DB_HOST
ENV_DB_PORT=$DB_PORT

DB_CONN=$(grep DB_CONNECTION .env | cut -d '=' -f 2 | tr -d '\r')
DB_HOST=$(grep DB_HOST .env | cut -d '=' -f 2 | tr -d '\r')
DB_PORT=$(grep DB_PORT .env | cut -d '=' -f 2 | tr -d '\r')

# Override with environment variables if present
if [ -n "$ENV_DB_CONNECTION" ]; then DB_CONN=$ENV_DB_CONNECTION; fi
if [ -n "$ENV_DB_HOST" ]; then DB_HOST=$ENV_DB_HOST; fi
if [ -n "$ENV_DB_PORT" ]; then DB_PORT=$ENV_DB_PORT; fi

if [ "$DB_CONN" = "mysql" ]; then
    echo "Waiting for MySQL database at $DB_HOST:$DB_PORT..."
    until php -r "
        try {
            \$host = '$DB_HOST';
            \$port = $DB_PORT;
            \$db = getenv('DB_DATABASE') ?: '$(grep DB_DATABASE .env | cut -d '=' -f 2 | tr -d '\r')';
            \$user = getenv('DB_USERNAME') ?: '$(grep DB_USERNAME .env | cut -d '=' -f 2 | tr -d '\r')';
            \$pass = getenv('DB_PASSWORD') ?: '$(grep DB_PASSWORD .env | cut -d '=' -f 2 | tr -d '\r')';
            new PDO(\"mysql:host=\$host;port=\$port;dbname=\$db\", \$user, \$pass);
            exit(0);
        } catch (PDOException \$e) {
            exit(1);
        }
    " 2>/dev/null; do
        echo "Database connection not ready. Sleeping for 2 seconds..."
        sleep 2
    done
    echo "Database is ready!"
elif [ "$DB_CONN" = "sqlite" ]; then
    touch database/database.sqlite
fi

# Run migrations and seed
echo "Running migrations..."
php artisan migrate --force --no-interaction

# Seeding default data (skip in production or if it fails)
if [ "$APP_ENV" != "production" ]; then
    echo "Seeding default data..."
    php artisan db:seed --force --no-interaction || echo "Seeding failed."
else
    echo "Skipping seeding in production environment."
fi

# Link storage
if [ ! -d public/storage ]; then
    echo "Creating storage link..."
    php artisan storage:link --no-interaction
fi

# Fix storage & cache permissions
echo "Adjusting file permissions..."
chown -R www-data:www-data storage bootstrap/cache

start_vite_dev_server() {
    vite_port="${VITE_PORT:-5173}"

    echo "Starting Vite dev server on 0.0.0.0:${vite_port}..."
    npm run dev -- --host 0.0.0.0 --port "$vite_port" --strictPort &
    VITE_PID=$!
}

start_apache_server() {
    echo "Starting Apache..."
    apache2-foreground &
    APACHE_PID=$!
}

shutdown_services() {
    if [ -n "${VITE_PID:-}" ] && kill -0 "$VITE_PID" 2>/dev/null; then
        kill "$VITE_PID" 2>/dev/null || true
    fi

    if [ -n "${APACHE_PID:-}" ] && kill -0 "$APACHE_PID" 2>/dev/null; then
        kill "$APACHE_PID" 2>/dev/null || true
    fi
}

if [ "$1" = "apache2-foreground" ]; then
    trap 'shutdown_services; exit 0' INT TERM

    if [ "${RUN_NPM_DEV:-false}" = "true" ]; then
        start_vite_dev_server
    fi

    start_apache_server
    set +e
    wait "$APACHE_PID"
    status=$?
    set -e
    shutdown_services
    exit "$status"
fi

# Execute CMD for any other entrypoint override
exec "$@"
