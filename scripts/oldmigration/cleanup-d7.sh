#!/bin/bash

# Check if the correct number of arguments is provided
if [ "$#" -ne 1 ]; then
    echo "Usage: cleanup_d7.sh @drushalias"
    exit 1
fi
SCRIPT_DIR="$( cd "$( dirname "${BASH_SOURCE[0]}" )" &> /dev/null && pwd )"
DRUSH_ALIAS=$1

# Path to the SQL script
SQL_SCRIPT_PATH="$SCRIPT_DIR/cleanup_d7.sql"

# Execute the SQL script using Drush
echo "Running cleanup script on the Drupal 7 database..."
ddev drush $DRUSH_ALIAS sql:query --database=d7 --file=$SQL_SCRIPT_PATH

echo "Cleanup script executed."
