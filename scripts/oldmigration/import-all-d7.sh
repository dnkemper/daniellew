#!/bin/bash

# Change to the script's directory
cd "$(dirname "${BASH_SOURCE[0]}")"

# Initialize a variable to check for the .gz extension
GZ_EXTENSION=""

# Process all options including the new --gz flag
while [[ "$1" =~ ^- ]]; do
    case "$1" in
        --reset)
            # If other flags like --reset are also intended to be supported
            RESET_FLAG="--reset"
            shift
            ;;
        --gz)
            GZ_EXTENSION=".gz"
            shift
            ;;
        *)
            echo "Unknown option: $1"
            exit 1
            ;;
    esac
done

# Validate the number of arguments after option processing
if [ "$#" -lt 2 ]; then
    echo "Usage: $0 <environment> <folder_path_to_sql_backups> [--gz]"
    exit 1
fi

ENVIRONMENT=$1
FOLDER_PATH=$2
YML_FILE="../sitelist.yml" # Adjust path based on script's location

# Source the YAML parsing script
source ../lib/yaml.sh

# Create variables from the YAML file
create_variables "$YML_FILE"

# Iterate over sites, executing the import script for each, excluding where migrate is false
for (( i=0; i<${#sites__name[@]}; i++ )); do
    name="${sites__name[$i]}"
    migrate="${sites__migrate[$i]}"

    # Skip sites marked with 'migrate: false'
    if [[ "$migrate" != "false" ]]; then
        # Filename adjusted based on --gz flag
        SQL_FILE="${FOLDER_PATH}/drupal_${name}_backup.sql${GZ_EXTENSION}"

        echo "($i) Importing D7 database for $name from $SQL_FILE..."
        
        # Execute the import script without checking if the SQL file exists
        ./import-d7-db.sh "@$ENVIRONMENT.$name" "$SQL_FILE"

        # The script ./import-d7-db.sh should handle any errors related to file existence or accessibility
        if [ $? -eq 0 ]; then
            echo "Import successful for $name."
        else
            echo "Error during import for $name."
        fi
    else
        echo "Skipping $name, marked as no migration."
    fi
done
