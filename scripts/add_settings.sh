#!/bin/bash

# Directory to search for settings.php
SITES_DIR="../web/sites"

# The line to be added
LINE="\$settings[\"simple_environment_indicator\"] = \$_ENV['SIMPLE_ENV'] ?? '#177AA3 Stage';"

# Find all settings.php files in the specified directory
find "$SITES_DIR" -type f -name "settings.php" | while read -r file; do
    # Check if the line already exists to avoid duplicates
    if ! grep -q "$LINE" "$file"; then
        # Add the line at the end of the file
        echo "$LINE" >> "$file"
        echo "Added line to $file"
    else
        echo "Line already exists in $file"
    fi
done

echo "Completed adding the specified line to all settings.php files."
