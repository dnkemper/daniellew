#!/bin/bash

# Check if Drush alias is provided
if [ "$#" -ne 1 ]; then
    echo "Usage: $0 @drush.alias"
    exit 1
fi

# Set the Drush alias from the first argument
DRUSH_ALIAS=$1

# Define migration modules (adjust these to your actual migration modules)
MIGRATION_MODULES=("migrate_plus" "migrate_tools" "olympian_migration" "migrate_drupal" "migrate_webform")


echo "Deleting all content..."
drush $DRUSH_ALIAS entity:delete media -y
drush $DRUSH_ALIAS entity:delete paragraph -y
drush $DRUSH_ALIAS entity:delete taxonomy_term -y
drush $DRUSH_ALIAS entity:delete node -y

#Reset status
drush $DRUSH_ALIAS sqlq "DELETE FROM key_value WHERE collection = migrate_status"

echo "Uninstalling migration modules..."
for MODULE in "${MIGRATION_MODULES[@]}"
do
    drush $DRUSH_ALIAS pm-uninstall -y $MODULE
done

echo "Deleting all migration tables..."
# Construct the SQL query to drop all tables
#DROP_TABLES_SQL=$(drush $DRUSH_ALIAS sqlq -y --extra='-ss' "show tables like 'migrate_%'" | sed -r '/^\s*$/d' | awk '{print "DROP TABLE " $1 ";"}' | tr '\n' ' ')

# Execute the SQL queryecho "Deleting all migration tables..."

# List all tables with the 'migrate_%' pattern
TABLES=$(drush $DRUSH_ALIAS sqlq --extra='-ss' "SHOW TABLES LIKE 'migrate_%'")

# Loop through each table and drop it individually
for table in $TABLES; do
    echo "Dropping table $table..."
    drush $DRUSH_ALIAS sqlq "DROP TABLE IF EXISTS $table" -y --no-interaction
done

#drush $DRUSH_ALIAS sqlq "$DROP_TABLES_SQL" -y --no-interaction

echo "Reinstalling migration modules..."
for MODULE in "${MIGRATION_MODULES[@]}"
do
    drush $DRUSH_ALIAS pm-enable -y $MODULE
done

echo "Reset process completed."
