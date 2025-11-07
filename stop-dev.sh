#!/bin/bash

echo "Stopping Dolphin development environment..."

# Compute project and backend directories dynamically
PROJECT_DIR="$(cd "$(dirname "$0")" && pwd)"
BACKEND_DIR="$PROJECT_DIR/Dolphin_Backend"

# Try to remove a scheduler cron job that may have been installed by start-dev.sh
echo "Removing Laravel scheduler cron job if present..."
PHP_BIN="$(command -v php)"
if [ -z "$PHP_BIN" ]; then
	PHP_BIN="php"
fi
CRON_ENTRY="* * * * * cd $BACKEND_DIR && $PHP_BIN artisan schedule:run >> $BACKEND_DIR/storage/logs/schedule-cron.log 2>&1"
crontab -l 2>/dev/null | grep -v "$CRON_ENTRY" | crontab - 2>/dev/null || true
echo "Cron job removed (if it existed)"

# Stop Laravel processes (serve, queue:work, schedule:work)
echo "Stopping Laravel backend (serve/queue/schedule)..."
pkill -f "php artisan serve" 2>/dev/null || true
pkill -f "artisan queue:work" 2>/dev/null || true
pkill -f "artisan schedule:work" 2>/dev/null || true

# Stop Vue frontend processes
echo "Stopping Vue frontend..."
pkill -f "npm run serve" 2>/dev/null || true
pkill -f "vue-cli-service" 2>/dev/null || true

# Best effort: kill node dev server bound to default port
pkill -f "node.*(host|port).*(8080|5173)" 2>/dev/null || true

echo "All Dolphin development servers stopped"
echo "Tip: Use ./start-dev.sh to restart the development environment"