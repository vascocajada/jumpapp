#!/bin/bash

# Messenger Worker Health Monitor Script
# Run this via cron every 5 minutes: */5 * * * * /path/to/scripts/messenger-monitor.sh

APP_DIR="/var/www/html"
LOG_FILE="/var/log/messenger-monitor.log"
MAX_FAILED_MESSAGES=10
MAX_WORKER_RESTARTS=3

# Log function
log() {
    echo "$(date '+%Y-%m-%d %H:%M:%S') - $1" >> "$LOG_FILE"
}

# Check if workers are running
check_workers() {
    local worker_count=$(ps aux | grep "messenger:consume" | grep -v grep | wc -l)
    echo $worker_count
}

# Check failed messages count
check_failed_messages() {
    cd "$APP_DIR"
    local failed_count=$(php bin/console messenger:failed:show --format=json 2>/dev/null | jq '. | length' 2>/dev/null || echo "0")
    echo $failed_count
}

# Restart workers
restart_workers() {
    log "Restarting messenger workers..."
    cd "$APP_DIR"
    
    # Stop existing workers
    php bin/console messenger:stop-workers --time-limit=30 2>/dev/null
    
    # Wait a moment
    sleep 5
    
    # Start new workers
    nohup php bin/console messenger:consume async --time-limit=3600 --memory-limit=512M --limit=1000 > /dev/null 2>&1 &
    
    log "Workers restarted"
}

# Main monitoring logic
main() {
    log "Starting messenger health check..."
    
    # Check if workers are running
    local worker_count=$(check_workers)
    if [ "$worker_count" -eq 0 ]; then
        log "No workers running, restarting..."
        restart_workers
        exit 0
    fi
    
    # Check failed messages
    local failed_count=$(check_failed_messages)
    if [ "$failed_count" -gt "$MAX_FAILED_MESSAGES" ]; then
        log "Too many failed messages ($failed_count), restarting workers..."
        restart_workers
        exit 0
    fi
    
    # Check database connection
    cd "$APP_DIR"
    if ! php bin/console messenger:health-check > /dev/null 2>&1; then
        log "Health check failed, restarting workers..."
        restart_workers
        exit 0
    fi
    
    log "Health check passed - workers: $worker_count, failed messages: $failed_count"
}

# Run main function
main "$@" 