#!/bin/bash

# Shopologic Automated Backup Script
# This script performs automated backups and can be run via cron

set -e

# Configuration
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
APP_DIR="$(dirname "$SCRIPT_DIR")"
BACKUP_LOG="$APP_DIR/storage/logs/backup.log"
NOTIFICATION_EMAIL="${BACKUP_EMAIL:-admin@shopologic.com}"

# Functions
log() {
    echo "[$(date '+%Y-%m-%d %H:%M:%S')] $1" | tee -a "$BACKUP_LOG"
}

send_notification() {
    local subject="$1"
    local message="$2"
    
    if [ -n "$NOTIFICATION_EMAIL" ]; then
        echo "$message" | mail -s "$subject" "$NOTIFICATION_EMAIL"
    fi
    
    # Send to application notification system
    php "$APP_DIR/cli/notify.php" backup "$subject" "$message"
}

check_disk_space() {
    local required_space=$1
    local available_space=$(df "$APP_DIR/storage" | awk 'NR==2 {print $4}')
    
    if [ "$available_space" -lt "$required_space" ]; then
        log "ERROR: Insufficient disk space. Required: $required_space KB, Available: $available_space KB"
        send_notification "Backup Failed - Insufficient Disk Space" \
            "The backup could not be completed due to insufficient disk space."
        exit 1
    fi
}

# Main backup process
main() {
    log "Starting Shopologic backup process..."
    
    # Check environment
    if [ ! -f "$APP_DIR/.env" ]; then
        log "ERROR: .env file not found"
        exit 1
    fi
    
    # Source environment
    source "$APP_DIR/.env"
    
    # Determine backup type based on schedule
    BACKUP_TYPE="${1:-full}"
    BACKUP_STORAGE="${2:-local}"
    
    # Check disk space (require at least 1GB free)
    check_disk_space 1048576
    
    # Create backup
    log "Creating $BACKUP_TYPE backup to $BACKUP_STORAGE storage..."
    
    OUTPUT=$(php "$APP_DIR/cli/backup.php" create \
        --type="$BACKUP_TYPE" \
        --storage="$BACKUP_STORAGE" \
        --description="Automated backup via cron" 2>&1)
    
    BACKUP_STATUS=$?
    
    if [ $BACKUP_STATUS -eq 0 ]; then
        log "Backup completed successfully"
        log "$OUTPUT"
        
        # Extract backup ID from output
        BACKUP_ID=$(echo "$OUTPUT" | grep "Backup ID:" | awk '{print $3}')
        
        # Verify backup
        log "Verifying backup $BACKUP_ID..."
        if php "$APP_DIR/cli/backup.php" verify "$BACKUP_ID" > /dev/null 2>&1; then
            log "Backup verification passed"
            
            # Clean old backups
            log "Cleaning old backups..."
            php "$APP_DIR/cli/backup.php" clean --force
            
            # Send success notification if enabled
            if [ "${BACKUP_NOTIFY_SUCCESS:-false}" == "true" ]; then
                send_notification "Backup Completed Successfully" \
                    "Backup $BACKUP_ID completed and verified successfully."
            fi
        else
            log "ERROR: Backup verification failed"
            send_notification "Backup Verification Failed" \
                "Backup $BACKUP_ID was created but failed verification."
            exit 1
        fi
    else
        log "ERROR: Backup failed"
        log "$OUTPUT"
        
        send_notification "Backup Failed" \
            "The automated backup failed. Please check the logs for details."
        
        exit 1
    fi
    
    log "Backup process completed"
}

# Run main function
main "$@"