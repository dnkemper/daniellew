#!/bin/bash

# Change to the script's directory
cd "$(dirname "${BASH_SOURCE[0]}")"

# Load YAML library
source ./lib/yaml.sh

# Convert YAML to variables usable in bash
create_variables sitelist.yml


# Change back to the main app directory
cd ../web

# Set the default server
DEFAULT_SERVER="@server-local"

# Iterate through defined sites from YAML
numsites=${#sites__name[@]}
printf "Checking %s sites\n" "${numsites}"
for (( j=0; j<numsites; j++ )); do
    SITE_ALIAS="${sites__name[j]}"
    
    # Check if the current site is initialized
    echo "Checking if site '$DEFAULT_SERVER.$SITE_ALIAS' is initialized"

    # Execute a drush command to get the number of tables in the database
    TABLE_COUNT=$(drush "$DEFAULT_SERVER"."$SITE_ALIAS" sql-query "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = '${sites__db[j]}';")

    # Check if the table count is 0 (i.e., the site is uninitialized)
    if [ "$TABLE_COUNT" -eq 0 ]; then
        # Sync the database from the default site to the uninitialized site
        echo "Site '$DEFAULT_SERVER.$SITE_ALIAS' is not initialized. Syncing with the default site."
        drush sql-sync @self "$DEFAULT_SERVER"."$SITE_ALIAS" -y
        
        # Sync the files directory from the default site to the uninitialized site
        echo "Syncing files from the default site to '$DEFAULT_SERVER.$SITE_ALIAS'"
        drush rsync @self:%files "$DEFAULT_SERVER"."$SITE_ALIAS":%files -y
    else
        # Indicate that the site is already initialized
        echo "Site '$DEFAULT_SERVER.$SITE_ALIAS' is initialized."
    fi
done
