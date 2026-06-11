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
DB_CONN=$(grep DB_CONNECTION .env | cut -d '=' -f 2 | tr -d '\r')
DB_HOST=$(grep DB_HOST .env | cut -d '=' -f 2 | tr -d '\r')
DB_PORT=$(grep DB_PORT .env | cut -d '=' -f 2 | tr -d '\r')

# Override with environment variables if present
if [ -n "$DB_CONNECTION" ]; then DB_CONN=$DB_CONNECTION; fi
if [ -n "$DB_HOST" ]; then DB_HOST=$DB_HOST; fi
if [ -n "$DB_PORT" ]; then DB_PORT=$DB_PORT; fi

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

echo "Seeding default data..."
php artisan db:seed --force --no-interaction

# Link storage
if [ ! -d public/storage ]; then
    echo "Creating storage link..."
    php artisan storage:link --no-interaction
fi

# Fix storage & cache permissions
echo "Adjusting file permissions..."
chown -R www-data:www-data storage bootstrap/cache

# Execute CMD
exec "$@"
