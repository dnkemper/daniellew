#!/bin/bash

# Check if the correct number of arguments is provided
if [ "$#" -ne 3 ]; then
    echo "Usage: entityinfo.sh @drushalias entity bundle"
    exit 1
fi

DRUSH_ALIAS=$1
ENTITY_TYPE=$2
BUNDLE=$3

# Commands for Drupal 7 - Using Drush to run an SQL query
echo "Field information from Drupal 7 (Database: migrate):"
drush $DRUSH_ALIAS sql:query --database=migrate "SELECT fc.field_name, fc.type, fc.module FROM field_config_instance fci INNER JOIN field_config fc ON fci.field_id = fc.id WHERE fci.bundle = '$BUNDLE' AND fci.entity_type = '$ENTITY_TYPE';"

echo "" # New line for better readability

# Commands for Drupal 10 - Using Drush field:info
echo "Field information from Drupal 10:"
drush $DRUSH_ALIAS field:info $ENTITY_TYPE $BUNDLE

