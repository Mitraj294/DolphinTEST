#!/bin/bash

# Set the host (change to your local network IP if needed)
HOST="127.0.0.1"

# Cleanup function to remove cron job and stop background processes when script exits
cleanup() {
    echo ""
    echo "Stopping Dolphin development environment..."

    # Remove the Laravel scheduler cron job if we added one
    if [ -n "$CRON_ENTRY" ]; then
        echo "Removing Laravel scheduler cron job..."
        crontab -l 2>/dev/null | grep -v "$CRON_ENTRY" | crontab - 2>/dev/null || true
        echo "Cron job removed"
    fi

    # Kill background processes we started (if any)
    echo "Stopping backend, queue and schedule workers..."
    if [ -n "$BACKEND_PID" ]; then
        kill "$BACKEND_PID" 2>/dev/null || true
    fi
    if [ -n "$QUEUE_PID" ]; then
        kill "$QUEUE_PID" 2>/dev/null || true
    fi
    if [ -n "$SCHEDULE_PID" ]; then
        kill "$SCHEDULE_PID" 2>/dev/null || true
    fi

    # Try to kill any remaining child jobs started with nohup launched from this script
    kill $(jobs -p) 2>/dev/null || true

    echo "Development servers stopped"

    exit 0
}

# Set up trap to call cleanup on script exit
trap cleanup SIGINT SIGTERM EXIT


# Start Laravel backend and ensure it binds to port 8000.
BACKEND_DIR="$(pwd)/Dolphin_Backend"
cd "$BACKEND_DIR"
# Desired backend port
BACKEND_PORT=8000

# Resolve full PHP binary path so cron/supervisor use the same PHP
PHP_BIN="$(command -v php)"
if [ -z "$PHP_BIN" ]; then
    echo "php binary not found in PATH. Please install PHP and ensure 'php' is available in PATH." >&2
    exit 1
fi

# If something is listening on BACKEND_PORT and is a known dev process, kill it so artisan can bind.
if command -v lsof >/dev/null 2>&1; then
    BACKEND_PID=$(lsof -nP -iTCP:$BACKEND_PORT -sTCP:LISTEN -t || true)
    if [ -n "$BACKEND_PID" ]; then
        CMD=$(ps -p "$BACKEND_PID" -o comm= 2>/dev/null || true)
        if echo "$CMD" | grep -Eqi "php|artisan|php-fpm|node|gunicorn|uwsgi"; then
            echo "Port $BACKEND_PORT is in use by PID $BACKEND_PID ($CMD) — stopping it so backend can bind to $BACKEND_PORT"
            kill "$BACKEND_PID" || true
            sleep 1
            if lsof -nP -iTCP:$BACKEND_PORT -sTCP:LISTEN -t >/dev/null 2>&1; then
                echo "Port $BACKEND_PORT still in use after kill. Backend may fail to start."
            else
                echo "Port $BACKEND_PORT freed for backend."
            fi
        else
            echo "Port $BACKEND_PORT is in use by PID $BACKEND_PID ($CMD). Attempting to free it anyway."
            kill "$BACKEND_PID" || true
            sleep 1
        fi
    fi
fi

# Start artisan on the desired port (background)
"$PHP_BIN" artisan serve --host=$HOST --port=$BACKEND_PORT &
BACKEND_PID=$!


# Ensure logs directory exists for background workers
mkdir -p "$BACKEND_DIR/storage/logs"
# Choose a writable log directory; if storage/logs isn't writable fall back to /tmp
LOG_DIR="$BACKEND_DIR/storage/logs"
if [ ! -w "$LOG_DIR" ]; then
    echo "Warning: $LOG_DIR is not writable by user $USER — falling back to /tmp for worker logs"
    LOG_DIR="/tmp"
fi
# create (best-effort) and set permissive permissions on the files we will use
touch "$LOG_DIR/queue-worker.log" "$LOG_DIR/schedule-worker.log" "$LOG_DIR/schedule-cron.log" 2>/dev/null || true
chmod 664 "$LOG_DIR"/*.log 2>/dev/null || true
# try to chown to the current user so nohup can write into logs (ignore errors)
chown "$USER":"$USER" "$LOG_DIR"/*.log 2>/dev/null || true

# Start a queue worker in the background and capture its PID
echo "Starting queue worker..."
nohup "$PHP_BIN" "$BACKEND_DIR/artisan" queue:work --sleep=3 --tries=3 --timeout=90 > "$LOG_DIR/queue-worker.log" 2>&1 &
QUEUE_PID=$!
sleep 0.2
if [ -n "$QUEUE_PID" ] && kill -0 "$QUEUE_PID" 2>/dev/null; then
    echo "Queue worker started (PID=$QUEUE_PID)"
else
    echo "Failed to start queue worker — check $BACKEND_DIR/storage/logs/queue-worker.log for details"
fi

# Prefer a long-running schedule worker if available; otherwise fall back to cron
echo "Checking for 'schedule:work' command..."
if "$PHP_BIN" "$BACKEND_DIR/artisan" schedule:work --help > /dev/null 2>&1; then
    echo "Starting schedule worker..."
    nohup "$PHP_BIN" "$BACKEND_DIR/artisan" schedule:work > "$LOG_DIR/schedule-worker.log" 2>&1 &
    SCHEDULE_PID=$!
    echo "Schedule worker started (PID=$SCHEDULE_PID)"
else
    echo "schedule:work not available; installing a cron entry as a fallback"
    # Add Laravel scheduler cron job when project starts (use absolute paths)
    CRON_ENTRY="* * * * * cd $BACKEND_DIR && $PHP_BIN artisan schedule:run >> $LOG_DIR/schedule-cron.log 2>&1"
    echo "Adding Laravel scheduler cron job for this development session..."
    (crontab -l 2>/dev/null; echo "$CRON_ENTRY") | crontab -
    echo "Cron job added: Laravel scheduler will run while project is active"
fi

## NOTE: Scheduler is already started above (either schedule:work or cron fallback)

# Start Vue frontend
cd ../Dolphin_Frontend
# Frontend port (change if you prefer). Default to 8080 so dev URL stays consistent.
FRONTEND_PORT=${FRONTEND_PORT:-8080}

# If the desired port is already in use, try to stop a previous dev server process
if command -v lsof >/dev/null 2>&1; then
    PID=$(lsof -nP -iTCP:$FRONTEND_PORT -sTCP:LISTEN -t || true)
    if [ -n "$PID" ]; then
        # Get the command name for the PID
        CMD=$(ps -p "$PID" -o comm= 2>/dev/null || true)
        if echo "$CMD" | grep -Eqi "node|npm|yarn|vue"; then
            echo "Port $FRONTEND_PORT is in use by PID $PID ($CMD) — stopping previous dev server"
            kill "$PID" || true
            # give it a second to free the port
            sleep 1
            # double-check
            if lsof -nP -iTCP:$FRONTEND_PORT -sTCP:LISTEN -t >/dev/null 2>&1; then
                echo "Port $FRONTEND_PORT still in use after kill; frontend may choose another port."
            else
                echo "Port $FRONTEND_PORT freed."
            fi
        else
            echo "Port $FRONTEND_PORT is in use by PID $PID ($CMD). Will let frontend pick another port."
        fi
    fi
fi

# Start the vue dev server on the chosen port
echo "Starting frontend on 127.0.0.1:$FRONTEND_PORT"
# Suppress Node deprecation warnings (util._extend) for dev output clarity. If you want
# a stack trace to locate the origin, set NODE_OPTIONS=--trace-deprecation instead.
NODE_OPTIONS=--no-deprecation FRONTEND_PORT=$FRONTEND_PORT npm run serve -- --host $HOST --port $FRONTEND_PORT
