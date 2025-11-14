#!/bin/bash

# Define the path to the site names file
SITE_NAMES_FILE="/home/drupaladm/d9main/scripts/site_names.txt"

# Define the base directory for backup files
BACKUP_DIR="/home/drupaladm/d9main/d7data/backups"

# Check if the site names file exists
if [[ ! -f ${SITE_NAMES_FILE} ]]; then
    echo "Error: Site names file not found at ${SITE_NAMES_FILE}"
    exit 1
fi

# Loop through each site name in the file
while IFS= read -r sname; do
    if [[ -n "${sname}" ]]; then
	echo "dropping @${sname}.stage"
	/home/drupaladm/d9main/vendor/bin/drush @${sname}.stage sql-drop -y
    	 echo "Starting database sync for site: ${sname}"

        # Define aliases for sql-sync and import
        SOURCE_ALIAS="@migrate.stage"
        DEST_ALIAS="${sname}.stage"

        # Sync the database from source to destination
        /home/drupaladm/d9main/vendor/bin/drush sql-sync @migrate.stage @${sname}.stage -y
        if [[ $? -ne 0 ]]; then
            echo "Error: Database sync failed for site: ${sname}"
            continue
        fi

        echo "Database sync completed for site: ${sname}"

        # Define the backup file path for the site
        BACKUP_FILE="${BACKUP_DIR}/drupal_${sname}_backup.sql"

        # Check if the backup file exists
        if [[ ! -f ${BACKUP_FILE} ]]; then
            echo "Error: Backup file not found for site: ${sname} at ${BACKUP_FILE}"
            continue
        fi

        # Import the Drupal 7 database into the migrate database
	echo "droping d7 db for ${name}"
	/home/drupaladm/d9main/vendor/bin/drush @${sname}.stage sql-drop --database=migrate --yes
	echo "Importing migration db from d7"
	/home/drupaladm/d9main/vendor/bin/drush @${sname}.stage sql-query --database=migrate --file=${BACKUP_FILE}
        if [[ $? -ne 0 ]]; then
            echo "Error: Database import failed for site: ${sname}"
            continue
        fi
/home/drupaladm/d9main/vendor/bin/drush @${sname}.stage cim --yes
        echo "Database import completed for site: ${sname}"

        echo "Sleeping for safety..."
        sleep 5s
    fi

done < ${SITE_NAMES_FILE}

echo "All operations completed!"


