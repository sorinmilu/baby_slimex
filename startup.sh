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

    log "Installing jq"
    apt install -y jq

    log "Call the RandomUser API and extract the first name, last name, and year of birth"

    output_file=/home/site/wwwroot/assets/name.txt
    log "name file is: $output_file"

    response=$(curl -s "https://randomuser.me/api/?results=1&nat=us,gb,ca,au,nz" | jq -r '.results[0] | "\(.name.first) \(.name.last), \(.dob.date | split("T")[0] | split("-")[0])"')

    if [ -n "$response" ]; then
        log "got response $response, writing to file $output_file"
        echo "$response" > "$output_file"
    else
        log "no response, writing john doe"
        echo "John Doe, 1900" > "$output_file"
    fi


    # Create the marker file after successful execution
    touch "$MARKER_FILE"
    log "Initial setup completed."
fi

log "Initial setup script has already been run."
log "Executing permanent tasks"

cp -f /home/site/wwwroot/nginx_config/default /etc/nginx/sites-available/default >> "$log_file" 2>&1
service nginx reload >> "$log_file" 2>&1

