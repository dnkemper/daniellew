#!/bin/bash

# Determine the directory in which the script resides
SCRIPT_DIR="$( cd "$( dirname "${BASH_SOURCE[0]}" )" &> /dev/null && pwd )"

# Function to perform database backup
backup_database() {
    NOW=$(date +"%m-%d-%Y-%T")
    BACKUP_FILE="migration-backup-$NOW.sql"
    echo "Backing up database to $BACKUP_FILE"
    ddev drush $DRUSH_ALIAS sql-dump > "$BACKUP_FILE"
}

# Function to reset the site
reset_site() {
    echo "Resetting site using reset-site.sh script"
    "$SCRIPT_DIR/reset-site.sh" $DRUSH_ALIAS
}

# Check if at least one argument is provided
if [ "$#" -lt 1 ]; then
    echo "Usage: run-migration.sh @drushalias [--backup] [--reset] [--shared-deps-only]"
    exit 1
fi

DRUSH_ALIAS=$1
shift # Shift arguments to the left

# Flags for backup, reset, and shared dependencies only
DO_BACKUP=false
DO_RESET=false
SHARED_DEPS_ONLY=false

# Process optional arguments
while (( "$#" )); do
    case "$1" in
        --backup)
            DO_BACKUP=true
            shift
            ;;
        --reset)
            DO_RESET=true
            shift
            ;;
        --shared-deps-only)
            SHARED_DEPS_ONLY=true
            shift
            ;;
        *)
            echo "Invalid option: $1"
            exit 1
            ;;
    esac
done

# Perform backup if --backup option is provided
if [ "$DO_BACKUP" = true ]; then
    backup_database
fi

# Perform reset if --reset option is provided
if [ "$DO_RESET" = true ]; then
    reset_site
fi

echo "Clearing cache for funsies"
ddev drush $DRUSH_ALIAS cr

echo "Migration process initiated for alias: $DRUSH_ALIAS"


# Adjust the migration command based on whether --shared-deps-only is provided
if [ "$SHARED_DEPS_ONLY" = true ]; then
    echo "Running migrations with tag 'shared-dep'"
    ddev drush $DRUSH_ALIAS migrate:import --tag=shared-dep --execute-dependencies --continue-on-failure --update
else
    echo "Running migrations with tag 'artsci'"
    ddev drush $DRUSH_ALIAS cr
    #import some d7 configs
    ddev drush $DRUSH_ALIAS migrate:import olympian_theme_settings --update
    ddev drush $DRUSH_ALIAS migrate:import system_site --update
    ddev drush $DRUSH_ALIAS migrate:import olympian_user --update
    ddev drush $DRUSH_ALIAS migrate:import d7_user_role --update
    ddev drush $DRUSH_ALIAS migrate:import d7_node_type --update
    ddev drush $DRUSH_ALIAS migrate:import d7_menu --update --continue-on-failure
    ddev drush $DRUSH_ALIAS migrate:import file_migration --update --continue-on-failure
    ddev drush $DRUSH_ALIAS migrate:import media_image_migration --update --continue-on-failure
    ddev drush $DRUSH_ALIAS migrate:import media_cv_migration --update --continue-on-failure
    ddev drush $DRUSH_ALIAS migrate:import media_document_migration --update --continue-on-failure
    ddev drush $DRUSH_ALIAS migrate:import taxonomy_event_categories --update --continue-on-failure
    ddev drush $DRUSH_ALIAS migrate:import taxonomy_resource_categories --update --continue-on-failure
    ddev drush $DRUSH_ALIAS migrate:import taxonomy_image_cards --update --continue-on-failure
    ddev drush $DRUSH_ALIAS migrate:import taxonomy_levels --update --continue-on-failure
    ddev drush $DRUSH_ALIAS migrate:import taxonomy_semester --update --continue-on-failure
    ddev drush $DRUSH_ALIAS migrate:import taxonomy_leadership_administrative_staff --update --continue-on-failure
    ddev drush $DRUSH_ALIAS migrate:import taxonomy_media_folders --update --continue-on-failure
    ddev drush $DRUSH_ALIAS migrate:import taxonomy_areas_of_interest --update --continue-on-failure
    ddev drush $DRUSH_ALIAS migrate:import taxonomy_course_level --update --continue-on-failure
    ddev drush $DRUSH_ALIAS migrate:import taxonomy_article_categories --update --continue-on-failure
    ddev drush $DRUSH_ALIAS migrate:import taxonomy_book_ --update --continue-on-failure

ddev drush $DRUSH_ALIAS mim node_resources --execute-dependencies --continue-on-failure --update
ddev drush $DRUSH_ALIAS mim node_faculty_staff --execute-dependencies --continue-on-failure --update
ddev drush $DRUSH_ALIAS cr
ddev drush $DRUSH_ALIAS mim node_testimonial --execute-dependencies --continue-on-failure --update
ddev drush $DRUSH_ALIAS mim node_books --execute-dependencies --continue-on-failure --update
ddev drush $DRUSH_ALIAS mim node_articles --execute-dependencies --continue-on-failure --update
ddev drush $DRUSH_ALIAS sql:query --database=migrate --file=/var/www/html/scripts/migration/cleanup_d7.sql
ddev drush $DRUSH_ALIAS mim node_events --execute-dependencies --continue-on-failure --update
ddev drush $DRUSH_ALIAS cr

ddev drush $DRUSH_ALIAS mim node_image_cards --execute-dependencies --continue-on-failure --update
ddev drush $DRUSH_ALIAS mim node_page --execute-dependencies --continue-on-failure --update
ddev drush $DRUSH_ALIAS mim node_generic_page --execute-dependencies --continue-on-failure --update
ddev drush $DRUSH_ALIAS mim node_contact_landing_page --execute-dependencies --continue-on-failure --update
ddev drush $DRUSH_ALIAS mim node_news_landing_page --execute-dependencies --continue-on-failure --update
ddev drush $DRUSH_ALIAS mim node_faq --execute-dependencies --continue-on-failure --update

ddev drush $DRUSH_ALIAS mim node_resources_landing_page --execute-dependencies --continue-on-failure --update
ddev drush $DRUSH_ALIAS mim node_image_cards_landing_page --execute-dependencies --continue-on-failure --update

ddev drush $DRUSH_ALIAS mim node_events_landing_page --execute-dependencies --continue-on-failure --update
ddev drush $DRUSH_ALIAS mim node_past_events_landing_page --execute-dependencies --continue-on-failure --update
ddev drush $DRUSH_ALIAS mim node_home_page --execute-dependencies --continue-on-failure --update
ddev drush $DRUSH_ALIAS cr
ddev drush $DRUSH_ALIAS mim node_faculty_landing_page --execute-dependencies --continue-on-failure --update
ddev drush $DRUSH_ALIAS mim node_courses --execute-dependencies --continue-on-failure --update
ddev drush $DRUSH_ALIAS mim node_course_landing --execute-dependencies --continue-on-failure --update
ddev drush $DRUSH_ALIAS mim node_publication_page --execute-dependencies --continue-on-failure --update
ddev drush $DRUSH_ALIAS mim d7_webform --execute-dependencies --continue-on-failure --update


    #Reimport config since some of the above might import some stuff we don't want from D7
    ddev drush $DRUSH_ALIAS cim -y
    ddev drush $DRUSH_ALIAS cr
    ddev drush $DRUSH_ALIAS migrate:import --tag=artsci --execute-dependencies --continue-on-failure --update
    ddev drush $DRUSH_ALIAS migrate:import d7_menu_links --update --continue-on-failure
    ddev drush $DRUSH_ALIAS smart_date:migrate events field_event_smart_date field_event_date field_event_date --clear
fi
