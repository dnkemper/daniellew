#!/bin/bash

# Check if the correct number of arguments is provided
if [ "$#" -lt 2 ]; then
    echo "Usage: import-d7-db.sh @drushalias filename [--rsync]"
    exit 1
fi

DRUSH_ALIAS=$1
FILENAME=$2
RSYNC_OPTION=$3

# Determine the directory in which the script resides
SCRIPT_DIR="$( cd "$( dirname "${BASH_SOURCE[0]}" )" &> /dev/null && pwd )"

# Sync file to remote and set flag for file deletion
FILE_DELETE=""
if [ "$RSYNC_OPTION" = "--rsync" ]; then
    echo "Syncing file to remote server..."
    drush rsync @self:"$FILENAME" "$DRUSH_ALIAS":"$FILENAME"
    FILE_DELETE="--file-delete"
fi

# Import the SQL file using drush, and delete it afterwards if --rsync was used
drush $DRUSH_ALIAS sql-query --database=migrate --file="$FILENAME" $FILE_DELETE

echo "Database import completed for alias: $DRUSH_ALIAS"

# Run cleanup script, referencing it relative to the script's location
echo "Running cleanup script..."
"$SCRIPT_DIR/cleanup-d7.sh" $DRUSH_ALIAS

echo "Cleanup completed for alias: $DRUSH_ALIAS"
