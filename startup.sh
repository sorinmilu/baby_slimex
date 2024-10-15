#!/bin/bash
MARKER_FILE=/home/site/wwwroot/.init_script_ran

log_file="/home/site/wwwroot/.init-script.log"

# Function to log with timestamp
log() {
    echo "$(date '+%Y-%m-%d %H:%M:%S') - $1" >> "$log_file"
}

if [ ! -f "$MARKER_FILE" ]; then
    log "Running initial setup script..."
    # Place your one-time setup tasks here

    # Create the marker file after successful execution
    touch "$MARKER_FILE"
    log "Initial setup completed."
fi

log "Initial setup script has already been run."
log "Executing permanent tasks"

cp -f /home/site/wwwroot/nginx_config/default /etc/nginx/sites-available/default >> "$log_file" 2>&1
service nginx reload >> "$log_file" 2>&1

