#!/bin/bash
# A script to start and manage the Dolphin development environment.
# Use Ctrl+C to stop all services.

# --- Configuration ---
HOST="127.0.0.1"
BACKEND_PORT=8000
FRONTEND_PORT=8080

# Get absolute paths
SCRIPT_DIR="$( cd "$( dirname "${BASH_SOURCE[0]}" )" &> /dev/null && pwd )"
BACKEND_DIR="$SCRIPT_DIR/Dolphin_Backend"
FRONTEND_DIR="$SCRIPT_DIR/Dolphin_Frontend"
# --- End Configuration ---

# Ensure we exit if any command fails
set -e

# Find the full path to PHP
PHP_BIN="$(command -v php)"
if [ -z "$PHP_BIN" ]; then
    echo "Error: 'php' binary not found in PATH." >&2
    echo "Please install PHP and ensure it's available in your system's PATH." >&2
    exit 1
fi

# Function to check if a port is in use and kill the process if it's a dev server.
check_and_kill_port() {
    local port="$1"
    local process_names_regex="$2" # e.g., "php|artisan|node|npm"

    if ! command -v lsof >/dev/null 2>&1; then
        echo "Info: 'lsof' not found. Cannot check if port $port is in use."
        return
    fi

    local pid=$(lsof -nP -iTCP:$port -sTCP:LISTEN -t || true)
    if [ -n "$pid" ]; then
        local cmd=$(ps -p "$pid" -o comm= 2>/dev/null || true)
        if echo "$cmd" | grep -Eqi "$process_names_regex"; then
            echo "Port $port is in use by $cmd (PID $pid). Stopping it..."
            kill "$pid" || true
            sleep 1
            if lsof -nP -iTCP:$port -sTCP:LISTEN -t >/dev/null 2>&1; then
                echo "Warning: Port $port is still in use after kill."
            else
                echo "Port $port freed."
            fi
        else
            echo "Warning: Port $port is in use by an unknown process: $cmd (PID $pid)."
        fi
    fi
}

# Cleanup function: This runs when you press Ctrl+C
cleanup() {
    echo ""
    echo "Stopping development servers..."

    # Remove cron job if we added one
    if [ -n "$CRON_ENTRY" ]; then
        echo "Removing cron job..."
        crontab -l 2>/dev/null | grep -v "$CRON_ENTRY" | crontab - 2>/dev/null || true
    fi

    # Kill background PIDs
    if [ -n "$BACKEND_PID" ]; then
        kill "$BACKEND_PID" 2>/dev/null || true
    fi
    if [ -n "$QUEUE_PID" ]; then
        kill "$QUEUE_PID" 2>/dev/null || true
    fi
    if [ -n "$SCHEDULE_PID" ]; then
        kill "$SCHEDULE_PID" 2>/dev/null || true
    fi

    # Kill any other child jobs
    kill $(jobs -p) 2>/dev/null || true
    echo "All processes stopped. Goodbye!"
    exit 0
}

# Trap Ctrl+C (SIGINT) and script termination (SIGTERM) to run the cleanup function
trap cleanup SIGINT SIGTERM EXIT

# --- 1. Start Laravel Backend ---
echo "--- Starting Laravel Backend ---"
cd "$BACKEND_DIR"
check_and_kill_port $BACKEND_PORT "php|artisan"
"$PHP_BIN" artisan serve --host=$HOST --port=$BACKEND_PORT &
BACKEND_PID=$!
echo "Backend server started (PID=$BACKEND_PID) on $HOST:$BACKEND_PORT"

# --- 2. Start Background Workers ---
echo "--- Starting Background Workers ---"
# Set up log directory
LOG_DIR="$BACKEND_DIR/storage/logs"
mkdir -p "$LOG_DIR"
if [ ! -w "$LOG_DIR" ]; then
    echo "Warning: $LOG_DIR not writable, falling back to /tmp for logs."
    LOG_DIR="/tmp"
fi
QUEUE_LOG="$LOG_DIR/dev-queue-worker.log"
SCHEDULE_LOG="$LOG_DIR/dev-schedule-worker.log"
CRON_LOG="$LOG_DIR/dev-schedule-cron.log"
touch "$QUEUE_LOG" "$SCHEDULE_LOG" "$CRON_LOG" 2>/dev/null || true

# Start Queue Worker
echo "Starting queue worker (logging to $QUEUE_LOG)..."
nohup "$PHP_BIN" "$BACKEND_DIR/artisan" queue:work --sleep=3 --tries=3 --timeout=90 > "$QUEUE_LOG" 2>&1 &
QUEUE_PID=$!
sleep 0.2
if ! kill -0 "$QUEUE_PID" 2>/dev/null; then
    echo "Error: Failed to start queue worker. Check $QUEUE_LOG for details."
fi
echo "Queue worker started (PID=$QUEUE_PID)"

# Start Scheduler (try 'schedule:work' first, fall back to cron)
if "$PHP_BIN" "$BACKEND_DIR/artisan" schedule:work --help > /dev/null 2>&1; then
    echo "Starting 'schedule:work' (logging to $SCHEDULE_LOG)..."
    nohup "$PHP_BIN" "$BACKEND_DIR/artisan" schedule:work > "$SCHEDULE_LOG" 2>&1 &
    SCHEDULE_PID=$!
    echo "Schedule worker started (PID=$SCHEDULE_PID)"
else
    echo "Info: 'schedule:work' not available. Installing fallback cron job..."
    CRON_ENTRY="* * * * * cd $BACKEND_DIR && $PHP_BIN artisan schedule:run >> $CRON_LOG 2>&1"
    (crontab -l 2>/dev/null; echo "$CRON_ENTRY") | crontab -
    echo "Cron job added for this session."
fi

# --- 3. Start Vue Frontend ---
echo "--- Starting Vue Frontend ---"
cd "$FRONTEND_DIR"
check_and_kill_port $FRONTEND_PORT "node|npm|vue"
echo "Starting frontend on $HOST:$FRONTEND_PORT (This will take a moment...)"
# Suppress Node deprecation warnings for a cleaner dev output
NODE_OPTIONS=--no-deprecation FRONTEND_PORT=$FRONTEND_PORT npm run serve -- --host $HOST --port $FRONTEND_PORT

# The script will hang here on the 'npm run serve' command.
# When the user presses Ctrl+C, the 'trap' will catch it and run 'cleanup'.