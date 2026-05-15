#!/usr/bin/env bash
set -euo pipefail
cd "$(dirname "$0")"

# Install PHP dependencies if needed
if [ ! -d vendor ]; then
  composer install --no-interaction --prefer-dist
fi

DB_DIR="${REPLIT_DB_DIR:-/mnt/data/postgres-data}"
DB_USER="${DB_USER:-$(whoami)}"
DB_NAME="${DB_NAME:-pdao_db}"
DB_PORT="${DB_PORT:-5432}"

if [ ! -d "$DB_DIR" ] || [ ! -f "$DB_DIR/PG_VERSION" ]; then
  echo "Initializing Postgres database at $DB_DIR"
  initdb -D "$DB_DIR"
fi

if ! pg_ctl -D "$DB_DIR" status >/dev/null 2>&1; then
  echo "Starting Postgres..."
  pg_ctl -D "$DB_DIR" -o "-c listen_addresses='localhost'" -w start
fi

# Create the application database if it does not exist
createdb -h localhost -p "$DB_PORT" -U "$DB_USER" "$DB_NAME" >/dev/null 2>&1 || true

echo "Starting PHP built-in server on 0.0.0.0:3000"
php -S 0.0.0.0:3000
