#!/bin/bash
Red='\033[0;31m'          # Red
Green='\033[0;32m'
blue="\033[0;34m"
NC='\033[0m' # No Color

# Show info about this script, and setup PS4 for prefix of each trace line from "set -x"
echo -e "\n$(tput setaf 3)$(tput bold)>>>>> $(tput setaf 5)$(TZ=America/Chicago date +"%Y-%m-%d-%T") $(tput setaf 2)Running ${BASH_SOURCE} $(tput setaf 3)<<<<<$(tput sgr0)\n"
PS4='$(tput setaf 3)$(tput bold)>> $(tput setaf 5)$(TZ=America/Chicago date +%H:%M:%S.%3N) $(tput setaf 2)(${LINENO})$(tput sgr0) '

# Define the path to your drush.local.yml file
drush_local_yml="./drush/drush.local.yml"  # Change this to your actual path

# Extract the drush_user from the drush.local.yml file
drush_user=$(grep '^drush_user:' "$drush_local_yml" | awk '{print $2}')

# Check if drush_user is found
if [ -z "$drush_user" ]; then
    echo -e "${Red}drush_user not found in $drush_local_yml${NC}"
    exit 1
fi
# Define the source directory on the remote server
remote_source="$drush_user@artscistage.wustl.edu:/exports/nfsdrupal/d9main/d7data/"

# Notify user
echo "Files have been synced from $drush_user@artscistage.wustl.edu."
# Ask if there is publication content
read -p "Is there publication content? (y/n) [n]" PUB_CONTENT
read -p "Resync d7 databases? (y/n) [n]: " RESYNC_DATABASES
read -p "Do you want to reset your local environment? (y/n) [y]: " RESET_LOCAL
RESET_LOCAL="${RESET_LOCAL:-y}"
RESYNC_DATABASES="${RESYNC_DATABASES:-n}"
if [ "$RESYNC_DATABASES" = "y" ]; then
    rsync -avz --progress $drush_user@artscistage.wustl.edu:/exports/nfsdrupal/d9main/d7data/backups/ ./d7data
    rsync -avz --progress $drush_user@artscistage.wustl.edu:/exports/nfsdrupal/d9main/d7data/backups/cleanup_d7.sql  ./scripts/migration/
    # Copy settings.local.php file to the default site
    if [[ -f ./d7data/settings.local.php ]]; then
        cp -R ./d7data/settings.local.php ./web/sites/default/
    else
        echo -e "${Red}settings.local.php not found.${NC}"
    fi


    cp -R ./d7data/cleanup_d7.sql ./scripts/migration/


fi
if [ "$RESET_LOCAL" = "y" ]; then
    # Copy settings.local.php file to the default site
    if [[ -f ./d7data/settings.local.php ]]; then
        cp -R ./d7data/settings.local.php ./web/sites/default/
    else
        echo -e "${Red}settings.local.php not found.${NC}"
    fi

    cp -R ./d7data/cleanup_d7.sql ./scripts/migration/

    # Set your desired default alias
    default_alias="biology"

    # Set your desired default alias
    default_alias="biology"

    # Display instructions
    echo "You need the trailing / and be sure to choose the correct option for the URL."
    echo "For example, if you migrate it.artsci.wustl.edu, ${blue}it${NC} would be alias, and the URL would be ${blue}https://it.artsci.wustl.edu/${NC}"

    # Prompt for the alias
    read -p "Enter the name of the D7 site you want to migrate (example: it, history, anthropology) [${default_alias}]: " alias
    alias="${alias:-$default_alias}"

    # Offer two URL options
    option1="https://${alias}.wustl.edu/"
    option2="https://${alias}.artsci.wustl.edu/"

    # Display options
    echo "Please select the appropriate D7 site URL:"
    echo "1) ${option1} (default)"
    echo "2) ${option2}"

    # Prompt for selection with the default set to option 1
    read -p "Enter the number (1 or 2) corresponding to the URL [1]: " selection
    selection="${selection:-1}"  # Default to 1 if no input is given

    # Validate selection
    if [ "$selection" == "2" ]; then
        d7_site="$option2"
    else
        d7_site="$option1"
    fi

    echo "The final D7 site URL is: ${d7_site}"


    # Define the .env file path
    env_file="./.env"

    # Check if the .env file exists
    if [ ! -f "$env_file" ]; then
        echo "The .env file does not exist in the current directory."
        exit 1
    fi

    # Edit the .env file to set the D7_SITE variable using a different delimiter (|)
    if grep -q "D7_SITE=" "$env_file"; then
        # If D7_SITE already exists, replace it
        sed -i '' "s|^D7_SITE=.*|D7_SITE=$d7_site|" "$env_file"
    else
        # If it doesn't exist, add it
        echo "D7_SITE=$d7_site" >> "$env_file"
    fi

    # Run the ddev import-db command for d10base installation
    set -x
    ddev drush sql-sync @stage.migrate @self --yes
    ddev drush cim --yes
    set +x
    echo "Updated D7_SITE in $env_file to: $d7_site"

    # Run the ddev import-db command for the D7 database
    set -x
    ddev import-db --file="d7data/drupal_${alias}_backup.sql" --database=d7
    set +x

    # Notify user
    echo "Database import command executed for the alias: $alias"

    # Notify the user
    echo "The script has completed successfully."
fi

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
set -x
ddev dev-settings $DRUSH_ALIAS
ddev drush $DRUSH_ALIAS cr
ddev drush $DRUSH_ALIAS state:set config_split.config_split.secure_content:status FALSE
ddev drush $DRUSH_ALIAS state:set config_split.config_split.site:status FALSE
ddev drush $DRUSH_ALIAS state:set config_split.config_split.coin:status FALSE
ddev drush $DRUSH_ALIAS state:set config_split.config_split.dev:status FALSE
ddev drush $DRUSH_ALIAS sapi-disa
set +x
echo "Migration process initiated for alias: $DRUSH_ALIAS"

# Adjust the migration command based on whether --shared-deps-only is provided
if [ "$SHARED_DEPS_ONLY" = true ]; then
    echo "Running migrations with tag 'shared-dep'"
    set -x
    ddev drush $DRUSH_ALIAS migrate:import --tag=shared-dep --continue-on-failure --update
    set +x
else
    echo "Running migrations with tag 'artsci'"
    set -x
    ddev drush $DRUSH_ALIAS cr
    ddev drush $DRUSH_ALIAS uli
    # Import some d7 configs
    ddev drush $DRUSH_ALIAS sql:query --database=migrate --file=/var/www/html/scripts/migration/cleanup_d7.sql
    ddev drush $DRUSH_ALIAS mim olympian_d7_user_role --execute-dependencies --continue-on-failure --update
    ddev drush $DRUSH_ALIAS mim olympian_d7_system_site  --execute-dependencies --continue-on-failure --update
    ddev drush $DRUSH_ALIAS mim olympian_d7_user --execute-dependencies --continue-on-failure --update
    ddev drush $DRUSH_ALIAS uli
    ddev drush $DRUSH_ALIAS mim olympian_d7_menu --execute-dependencies --continue-on-failure --update
    ddev drush $DRUSH_ALIAS mim olympian_d7_pathauto_patterns --execute-dependencies --continue-on-failure --update
    ddev drush $DRUSH_ALIAS mim olympian_d7_taxonomy_term_media_folders --execute-dependencies --continue-on-failure --update
    ddev drush $DRUSH_ALIAS mim d7_file --continue-on-failure --update
    ddev drush $DRUSH_ALIAS mim media_image_migration --execute-dependencies --continue-on-failure --update
    ddev drush $DRUSH_ALIAS mim media_cv_migration --execute-dependencies --continue-on-failure --update
    ddev drush $DRUSH_ALIAS mim media_document_migration --execute-dependencies --continue-on-failure --update
    ddev drush $DRUSH_ALIAS migrate:import olympian_d7_theme_settings --update  --continue-on-failure
    ddev drush $DRUSH_ALIAS mim olympian_d7_webform --execute-dependencies --update --continue-on-failure
    ddev drush $DRUSH_ALIAS mim olympian_d7_webform_submission --execute-dependencies --update --continue-on-failure
    ddev drush $DRUSH_ALIAS mim olympian_d7_node_complete_webform --update  --continue-on-failure
    ddev drush $DRUSH_ALIAS mim olympian_d7_paragraphs_tabs --update --continue-on-failure
    ddev drush $DRUSH_ALIAS mim olympian_d7_paragraphs_revisions_tabs --update --continue-on-failure
    ddev drush $DRUSH_ALIAS migrate:import olympian_d7_theme_settings --update  --continue-on-failure
    ddev drush $DRUSH_ALIAS migrate:import olympian_d7_system_site --update  --continue-on-failure
    ddev drush $DRUSH_ALIAS mim olympian_d7_node_complete_webform --update  --continue-on-failure
    ddev drush $DRUSH_ALIAS mim olympian_d7_paragraphs_tabs --update --continue-on-failure
    ddev drush $DRUSH_ALIAS mim olympian_d7_paragraphs_revisions_tabs --update --continue-on-failure
    ddev drush $DRUSH_ALIAS mim olympian_d7_taxonomy_term_resource_categories --continue-on-failure --update
    ddev drush $DRUSH_ALIAS mim olympian_d7_taxonomy_term_article_categories --continue-on-failure --update
    ddev drush $DRUSH_ALIAS mim olympian_d7_taxonomy_term_event_categories --continue-on-failure --update
    ddev drush $DRUSH_ALIAS mim olympian_d7_taxonomy_term_areas_of_interest --continue-on-failure --update
    ddev drush $DRUSH_ALIAS mim olympian_d7_taxonomy_term_book_ --continue-on-failure --update
    ddev drush $DRUSH_ALIAS mim olympian_d7_taxonomy_term_image_cards --continue-on-failure --update
    ddev drush $DRUSH_ALIAS mim olympian_d7_taxonomy_term_leadership_administrative_staff --continue-on-failure --update
    ddev drush $DRUSH_ALIAS migrate:import olympian_d7_paragraphs_table_of_contents_anchors --continue-on-failure --update
    ddev drush $DRUSH_ALIAS mim olympian_d7_node_complete_resources --continue-on-failure --update
    ddev drush $DRUSH_ALIAS mim olympian_d7_paragraphs_links --continue-on-failure --update
    ddev drush $DRUSH_ALIAS mim olympian_d7_paragraphs_revisions_links --continue-on-failure --update
    ddev drush $DRUSH_ALIAS mim olympian_d7_paragraphs_intro_with_tob	--continue-on-failure --update
    ddev drush $DRUSH_ALIAS mim olympian_d7_paragraphs_revisions_intro_with_tob	--continue-on-failure --update
    ddev drush $DRUSH_ALIAS mim olympian_d7_paragraphs_timeline_slideshow_slides --update
    ddev drush $DRUSH_ALIAS mim olympian_d7_paragraphs_revisions_timeline_slideshow_slides --update
    ddev drush $DRUSH_ALIAS mim olympian_d7_paragraphs_timeline_slides --execute-dependencies --update
    ddev drush $DRUSH_ALIAS mim olympian_d7_paragraphs_revisions_timeline_slides --execute-dependencies --update
    ddev drush $DRUSH_ALIAS mim olympian_d7_paragraphs_timeline_slideshow --update
    ddev drush $DRUSH_ALIAS mim olympian_d7_paragraphs_revisions_timeline_slideshow --update
    ddev drush $DRUSH_ALIAS mim olympian_d7_paragraphs_sample_courses --continue-on-failure --update
    ddev drush $DRUSH_ALIAS mim olympian_d7_paragraphs_revisions_sample_courses --continue-on-failure --update
    ddev drush $DRUSH_ALIAS mim olympian_d7_paragraphs_social_media_links --continue-on-failure --update
    ddev drush $DRUSH_ALIAS mim olympian_d7_paragraphs_revisions_social_media_links --continue-on-failure --update
    ddev drush $DRUSH_ALIAS mim olympian_d7_paragraphs_sample_courses_publications  --continue-on-failure --update
    ddev drush $DRUSH_ALIAS mim olympian_d7_paragraphs_revisions_sample_courses_publications  --continue-on-failure --update
    ddev drush $DRUSH_ALIAS mim olympian_d7_paragraphs_course_samples  --continue-on-failure --update
    ddev drush $DRUSH_ALIAS mim olympian_d7_paragraphs_revisions_course_samples  --continue-on-failure --update
    ddev drush $DRUSH_ALIAS mim olympian_d7_paragraphs_lightbox_image --update  --continue-on-failure
    ddev drush $DRUSH_ALIAS mim olympian_d7_paragraphs_revisions_lightbox_image --update  --continue-on-failure
    ddev drush $DRUSH_ALIAS mim olympian_d7_paragraphs_image_gallery --update  --continue-on-failure
    ddev drush $DRUSH_ALIAS mim olympian_d7_paragraphs_revisions_image_gallery --update  --continue-on-failure
    ddev drush $DRUSH_ALIAS mim olympian_d7_paragraphs_sample_courses --update  --continue-on-failure
    ddev drush $DRUSH_ALIAS mim olympian_d7_paragraphs_revisions_sample_courses --update  --continue-on-failure
    ddev drush $DRUSH_ALIAS mim olympian_d7_node_complete_faculty_staff --continue-on-failure --update
    ddev drush $DRUSH_ALIAS mim olympian_d7_node_complete_testimonial --continue-on-failure --update
    ddev drush $DRUSH_ALIAS mim olympian_d7_node_complete_resources --continue-on-failure --update
    ddev drush $DRUSH_ALIAS mim olympian_d7_node_complete_events --execute-dependencies --update --continue-on-failure
    ddev drush $DRUSH_ALIAS mim olympian_d7_node_complete_article --execute-dependencies --update --continue-on-failure
    ddev drush $DRUSH_ALIAS mim olympian_d7_paragraphs_video_spotlights_and_articles --execute-dependencies --continue-on-failure  --update
    ddev drush $DRUSH_ALIAS mim olympian_d7_paragraphs_revisions_video_spotlights_and_articles --execute-dependencies --continue-on-failure  --update
    ddev drush $DRUSH_ALIAS mim olympian_d7_paragraphs_fullscreen_image --continue-on-failure  --update
    ddev drush $DRUSH_ALIAS mim olympian_d7_paragraphs_revisions_fullscreen_image --continue-on-failure  --update
    ddev drush $DRUSH_ALIAS mim olympian_d7_paragraphs_fullscreen_slideshow --continue-on-failure  --update --execute-dependencies
    ddev drush $DRUSH_ALIAS mim olympian_d7_paragraphs_revisions_fullscreen_slideshow --continue-on-failure  --update --execute-dependencies
    ddev drush $DRUSH_ALIAS mim olympian_d7_paragraphs_horizontal_slide --continue-on-failure  --update
    ddev drush $DRUSH_ALIAS mim olympian_d7_paragraphs_revisions_horizontal_slide --continue-on-failure  --update
    ddev drush $DRUSH_ALIAS mim olympian_d7_paragraphs_multipurpose_slideshow --update --continue-on-failure --execute-dependencies
    ddev drush $DRUSH_ALIAS mim olympian_d7_paragraphs_revisions_multipurpose_slideshow --update --continue-on-failure --execute-dependencies
    ddev drush $DRUSH_ALIAS mim olympian_d7_paragraphs_timeline_slides --update --continue-on-failure  --execute-dependencies
    ddev drush $DRUSH_ALIAS mim olympian_d7_paragraphs_revisions_timeline_slides --update --continue-on-failure  --execute-dependencies
    ddev drush $DRUSH_ALIAS mim olympian_d7_paragraphs_slideshow --update --continue-on-failure  --execute-dependencies
    ddev drush $DRUSH_ALIAS mim olympian_d7_paragraphs_revisions_slideshow --update --continue-on-failure  --execute-dependencies
    ddev drush $DRUSH_ALIAS mim olympian_d7_paragraphs_sidebar_slideshow --update --continue-on-failure
    ddev drush $DRUSH_ALIAS mim olympian_d7_paragraphs_revisions_sidebar_slideshow --update --continue-on-failure
    ddev drush $DRUSH_ALIAS mim olympian_d7_paragraphs_resources_callout --update --continue-on-failure
    ddev drush $DRUSH_ALIAS mim olympian_d7_paragraphs_revisions_resources_callout --update --continue-on-failure
    ddev drush $DRUSH_ALIAS mim olympian_d7_paragraphs_two_column_content --update --continue-on-failure
    ddev drush $DRUSH_ALIAS mim olympian_d7_paragraphs_revisions_two_column_content --update --continue-on-failure
    ddev drush $DRUSH_ALIAS mim olympian_d7_node_complete_generic_page --execute-dependencies --update  --continue-on-failure
    ddev drush $DRUSH_ALIAS mim olympian_d7_node_complete_book --continue-on-failure --update
    ddev drush $DRUSH_ALIAS mim olympian_d7_paragraphs_links --continue-on-failure --update
    ddev drush $DRUSH_ALIAS mim olympian_d7_paragraphs_revisions_links --continue-on-failure --update
    ddev drush $DRUSH_ALIAS mim olympian_d7_paragraphs_testimonial --continue-on-failure --update
    ddev drush $DRUSH_ALIAS mim olympian_d7_paragraphs_revisions_testimonial --continue-on-failure --update
    ddev drush $DRUSH_ALIAS mim olympian_d7_paragraphs_events --continue-on-failure --update
    ddev drush $DRUSH_ALIAS mim olympian_d7_paragraphs_revisions_events --continue-on-failure --update
    ddev drush $DRUSH_ALIAS mim olympian_d7_paragraphs_spotlight --continue-on-failure --update
    ddev drush $DRUSH_ALIAS mim olympian_d7_paragraphs_revisions_spotlight --continue-on-failure --update
    ddev drush $DRUSH_ALIAS mim olympian_d7_paragraphs_spotlights --continue-on-failure --update
    ddev drush $DRUSH_ALIAS mim olympian_d7_paragraphs_revisions_spotlights --continue-on-failure --update
    ddev drush $DRUSH_ALIAS mim olympian_d7_paragraphs_featured_full_w_text --continue-on-failure --update
    ddev drush $DRUSH_ALIAS mim olympian_d7_paragraphs_revisions_featured_full_w_text --continue-on-failure --update
    ddev drush $DRUSH_ALIAS mim olympian_d7_paragraphs_featured_with_caption --continue-on-failure --update
    ddev drush $DRUSH_ALIAS mim olympian_d7_paragraphs_revisions_featured_with_caption --continue-on-failure --update
    ddev drush $DRUSH_ALIAS mim olympian_d7_paragraphs_featured_half_image_w_text --continue-on-failure --update
    ddev drush $DRUSH_ALIAS mim olympian_d7_paragraphs_revisions_featured_half_image_w_text --continue-on-failure --update
    ddev drush $DRUSH_ALIAS mim olympian_d7_paragraphs_intro_text_with_image --continue-on-failure --update
    ddev drush $DRUSH_ALIAS mim olympian_d7_paragraphs_revisions_intro_text_with_image --continue-on-failure --update
    ddev drush $DRUSH_ALIAS mim olympian_d7_paragraphs_intro_with_links --continue-on-failure --update
    ddev drush $DRUSH_ALIAS mim olympian_d7_paragraphs_revisions_intro_with_links --continue-on-failure --update
    ddev drush $DRUSH_ALIAS mim olympian_d7_paragraphs_full_screen_home_slide --continue-on-failure --update
    ddev drush $DRUSH_ALIAS mim olympian_d7_paragraphs_revisions_full_screen_home_slide --continue-on-failure --update
    ddev drush $DRUSH_ALIAS mim olympian_d7_paragraphs_testimonials --continue-on-failure --update
    ddev drush $DRUSH_ALIAS mim olympian_d7_paragraphs_revisions_testimonials --continue-on-failure --update
    ddev drush $DRUSH_ALIAS mim olympian_d7_paragraphs_additional_resource --continue-on-failure --update
    ddev drush $DRUSH_ALIAS mim olympian_d7_paragraphs_revisions_additional_resource --continue-on-failure --update
    ddev drush $DRUSH_ALIAS mim olympian_d7_paragraphs_additional_resources --continue-on-failure --update
    ddev drush $DRUSH_ALIAS mim olympian_d7_paragraphs_revisions_additional_resources --continue-on-failure --update
    ddev drush $DRUSH_ALIAS mim olympian_d7_paragraphs_text_link_with_testimonial --continue-on-failure --update
    ddev drush $DRUSH_ALIAS mim olympian_d7_paragraphs_revisions_text_link_with_testimonial --continue-on-failure --update
    ddev drush $DRUSH_ALIAS mim olympian_d7_paragraphs_call_to_action_blocks --continue-on-failure --update
    ddev drush $DRUSH_ALIAS mim olympian_d7_paragraphs_revisions_call_to_action_blocks --continue-on-failure --update
    ddev drush $DRUSH_ALIAS mim olympian_d7_paragraphs_link_title_and_body --continue-on-failure --update
    ddev drush $DRUSH_ALIAS mim olympian_d7_paragraphs_revisions_link_title_and_body --continue-on-failure --update
    ddev drush $DRUSH_ALIAS mim olympian_d7_paragraphs_featured_news --continue-on-failure --update
    ddev drush $DRUSH_ALIAS mim olympian_d7_paragraphs_revisions_featured_news --continue-on-failure --update
    ddev drush $DRUSH_ALIAS mim olympian_d7_paragraphs_callout --continue-on-failure --update
    ddev drush $DRUSH_ALIAS mim olympian_d7_paragraphs_revisions_callout --continue-on-failure --update
    ddev drush $DRUSH_ALIAS mim olympian_d7_paragraphs_instagram_twitter_section --continue-on-failure --update
    ddev drush $DRUSH_ALIAS mim olympian_d7_paragraphs_revisions_instagram_twitter_section --continue-on-failure --update
    ddev drush $DRUSH_ALIAS mim olympian_d7_paragraphs_article_spotlight --continue-on-failure --update
    ddev drush $DRUSH_ALIAS mim olympian_d7_paragraphs_revisions_article_spotlight --continue-on-failure --update
    ddev drush $DRUSH_ALIAS mim olympian_d7_node_complete_home_page --continue-on-failure --update
    ddev drush $DRUSH_ALIAS mim olympian_d7_paragraphs_related_links  --continue-on-failure --update
    ddev drush $DRUSH_ALIAS mim olympian_d7_paragraphs_revisions_related_links  --continue-on-failure --update
    ddev drush $DRUSH_ALIAS mim olympian_d7_paragraphs_optional_callout  --continue-on-failure --update
    ddev drush $DRUSH_ALIAS mim olympian_d7_paragraphs_revisions_optional_callout  --continue-on-failure --update
    ddev drush $DRUSH_ALIAS mim olympian_d7_paragraphs_header  --continue-on-failure --update
    ddev drush $DRUSH_ALIAS mim olympian_d7_paragraphs_revisions_header  --continue-on-failure --update
    ddev drush $DRUSH_ALIAS mim olympian_d7_node_complete_image_cards --continue-on-failure --update
    ddev drush $DRUSH_ALIAS mim olympian_d7_node_complete_image_cards_landing  --continue-on-failure --update
    ddev drush $DRUSH_ALIAS mim olympian_d7_node_complete_faculty_landing_page --continue-on-failure --update
    ddev drush $DRUSH_ALIAS mim olympian_d7_node_complete_news_landing_page  --continue-on-failure --update
    ddev drush $DRUSH_ALIAS mim olympian_d7_node_complete_events_landing_page  --continue-on-failure --update
    ddev drush $DRUSH_ALIAS mim olympian_d7_node_complete_past_events_landing_page  --continue-on-failure --update
    ddev drush $DRUSH_ALIAS mim olympian_d7_paragraphs_jump_to  --continue-on-failure --update
    ddev drush $DRUSH_ALIAS mim olympian_d7_paragraphs_revisions_jump_to  --continue-on-failure --update
    ddev drush $DRUSH_ALIAS mim olympian_d7_node_complete_resources_landing_page  --continue-on-failure --update --execute-dependencies
    ddev drush $DRUSH_ALIAS mim olympian_d7_paragraphs_basic_page_content  --continue-on-failure --update
    ddev drush $DRUSH_ALIAS mim olympian_d7_paragraphs_revisions_basic_page_content  --continue-on-failure --update
    ddev drush $DRUSH_ALIAS mim olympian_d7_paragraphs_content_2  --continue-on-failure --update
    ddev drush $DRUSH_ALIAS mim olympian_d7_paragraphs_revisions_content_2  --continue-on-failure --update
    ddev drush $DRUSH_ALIAS mim olympian_d7_paragraphs_tabs  --continue-on-failure --update
    ddev drush $DRUSH_ALIAS mim olympian_d7_paragraphs_revisions_tabs --continue-on-failure --update
    ddev drush $DRUSH_ALIAS mim olympian_d7_paragraphs_revisions_introduction --continue-on-failure --update --execute-dependencies
    ddev drush $DRUSH_ALIAS mim olympian_d7_node_complete_contact_landing_page  --continue-on-failure --update --execute-dependencies
    ddev drush $DRUSH_ALIAS mim olympian_d7_node_complete_faq --update  --continue-on-failure --execute-dependencies
    ddev drush $DRUSH_ALIAS mim d7_menu_links --execute-dependencies --update
    ddev drush $DRUSH_ALIAS mim olympian_d7_paragraphs_revisions_button --update  --continue-on-failure --execute-dependencies
    ddev drush $DRUSH_ALIAS mim olympian_d7_paragraphs_revisions_call_to_action --update  --continue-on-failure --execute-dependencies
    ddev drush $DRUSH_ALIAS mim olympian_d7_node_complete_page  --continue-on-failure --update --execute-dependencies
    ddev drush $DRUSH_ALIAS mim olympian_d7_menu --execute-dependencies --continue-on-failure --update
    ddev drush $DRUSH_ALIAS mim olympian_d7_url_alias --update  --continue-on-failure
    ddev drush $DRUSH_ALIAS smart_date:migrate events field_event_smart_date field_event_date field_event_date field_date_all_day --clear
    ddev drush $DRUSH_ALIAS mim olympian_d7_node_complete_generic_page --execute-dependencies --update  --continue-on-failure
    ddev drush $DRUSH_ALIAS mim olympian_d7_node_complete_resources_landing_page  --continue-on-failure --update --execute-dependencies
    ddev drush $DRUSH_ALIAS mim d7_aggregator_item --update --execute-dependencies --continue-on-failure
    ddev drush $DRUSH_ALIAS mim d7_aggregator_feed --update --execute-dependencies --continue-on-failure
    set +x
fi

    # Run publication content commands if needed
if [ "$PUB_CONTENT" = "y" ]; then
    set -x
    ddev drush $DRUSH_ALIAS mim olympian_d7_paragraphs_publications --update --continue-on-failure
    ddev drush $DRUSH_ALIAS mim olympian_d7_paragraphs_revisions_publications --update --continue-on-failure
    ddev drush $DRUSH_ALIAS mim olympian_d7_paragraphs_publication_student_story --update --continue-on-failure
    ddev drush $DRUSH_ALIAS mim olympian_d7_paragraphs_revisions_publication_student_story --update --continue-on-failure
    ddev drush $DRUSH_ALIAS mim olympian_d7_paragraphs_publication_student_stories --update --continue-on-failure
    ddev drush $DRUSH_ALIAS mim olympian_d7_paragraphs_revisions_publication_student_stories --update --continue-on-failure
    ddev drush $DRUSH_ALIAS mim olympian_d7_paragraphs_publication_testimonial_section --update --continue-on-failure
    ddev drush $DRUSH_ALIAS mim olympian_d7_paragraphs_revisions_publication_testimonial_section --update --continue-on-failure
    ddev drush $DRUSH_ALIAS mim olympian_d7_paragraphs_publication_award --update --continue-on-failure
    ddev drush $DRUSH_ALIAS mim olympian_d7_paragraphs_revisions_publication_award --update --continue-on-failure
    ddev drush $DRUSH_ALIAS mim olympian_d7_paragraphs_publication_awards_section --update --continue-on-failure
    ddev drush $DRUSH_ALIAS mim olympian_d7_paragraphs_revisions_publication_awards_section --update --continue-on-failure
    ddev drush $DRUSH_ALIAS mim olympian_d7_paragraphs_publication_person --update --continue-on-failure
    ddev drush $DRUSH_ALIAS mim olympian_d7_paragraphs_revisions_publication_person --update --continue-on-failure
    ddev drush $DRUSH_ALIAS mim olympian_d7_paragraphs_publication_student_story --update --continue-on-failure
    ddev drush $DRUSH_ALIAS mim olympian_d7_paragraphs_revisions_publication_student_story --update --continue-on-failure
    ddev drush $DRUSH_ALIAS mim olympian_d7_paragraphs_publication_testimonial_section --update --continue-on-failure
    ddev drush $DRUSH_ALIAS mim olympian_d7_paragraphs_revisions_publication_testimonial_section --update --continue-on-failure
    ddev drush $DRUSH_ALIAS mim olympian_d7_paragraphs_publications_intro --update --continue-on-failure
    ddev drush $DRUSH_ALIAS mim olympian_d7_paragraphs_revisions_publications_intro --update --continue-on-failure
    ddev drush $DRUSH_ALIAS mim olympian_d7_paragraphs_publication_announcement --update --continue-on-failure
    ddev drush $DRUSH_ALIAS mim olympian_d7_paragraphs_revisions_publication_announcement --update --continue-on-failure
    ddev drush $DRUSH_ALIAS mim olympian_d7_paragraphs_publication_announcement_feature --update --continue-on-failure
    ddev drush $DRUSH_ALIAS mim olympian_d7_paragraphs_revisions_publication_announcement_feature --update --continue-on-failure
    ddev drush $DRUSH_ALIAS mim olympian_d7_paragraphs_publication_announcements --update --continue-on-failure
    ddev drush $DRUSH_ALIAS mim olympian_d7_paragraphs_revisions_publication_announcements --update --continue-on-failure
    ddev drush $DRUSH_ALIAS mim olympian_d7_paragraphs_publication_award --update --continue-on-failure
    ddev drush $DRUSH_ALIAS mim olympian_d7_paragraphs_revisions_publication_award --update --continue-on-failure
    ddev drush $DRUSH_ALIAS mim olympian_d7_paragraphs_publication_awards_section --update --continue-on-failure
    ddev drush $DRUSH_ALIAS mim olympian_d7_paragraphs_revisions_publication_awards_section --update --continue-on-failure
    ddev drush $DRUSH_ALIAS mim olympian_d7_paragraphs_publication_person --update --continue-on-failure
    ddev drush $DRUSH_ALIAS mim olympian_d7_paragraphs_revisions_publication_person --update --continue-on-failure
    ddev drush $DRUSH_ALIAS mim olympian_d7_paragraphs_publication_featured_people --update --continue-on-failure
    ddev drush $DRUSH_ALIAS mim olympian_d7_paragraphs_revisions_publication_featured_people --update --continue-on-failure
    ddev drush $DRUSH_ALIAS mim olympian_d7_paragraphs_publications_featured_news --update --continue-on-failure
    ddev drush $DRUSH_ALIAS mim olympian_d7_paragraphs_revisions_publications_featured_news --update --continue-on-failure
    ddev drush $DRUSH_ALIAS mim olympian_d7_paragraphs_publication_degree_holder --update --continue-on-failure
    ddev drush $DRUSH_ALIAS mim olympian_d7_paragraphs_revisions_publication_degree_holder --update --continue-on-failure
    ddev drush $DRUSH_ALIAS mim olympian_d7_paragraphs_publication_degree --update --continue-on-failure
    ddev drush $DRUSH_ALIAS mim olympian_d7_paragraphs_revisions_publication_degree --update --continue-on-failure
    ddev drush $DRUSH_ALIAS mim olympian_d7_paragraphs_publication_degrees_subsection --update --continue-on-failure
    ddev drush $DRUSH_ALIAS mim olympian_d7_paragraphs_revisions_publication_degrees_subsection --update --continue-on-failure
    ddev drush $DRUSH_ALIAS mim olympian_d7_paragraphs_publication_degrees_section --update --continue-on-failure
    ddev drush $DRUSH_ALIAS mim olympian_d7_paragraphs_revisions_publication_degrees_section --update --continue-on-failure
    ddev drush $DRUSH_ALIAS mim olympian_d7_paragraphs_publication_gallery_image --update --continue-on-failure
    ddev drush $DRUSH_ALIAS mim olympian_d7_paragraphs_revisions_publication_gallery_image --update --continue-on-failure
    ddev drush $DRUSH_ALIAS mim olympian_d7_paragraphs_publication_gallery --update --continue-on-failure
    ddev drush $DRUSH_ALIAS mim olympian_d7_paragraphs_revisions_publication_gallery --update --continue-on-failure
    ddev drush $DRUSH_ALIAS mim olympian_d7_paragraphs_publication_student_stories --update --continue-on-failure
    ddev drush $DRUSH_ALIAS mim olympian_d7_paragraphs_revisions_publication_student_stories --update --continue-on-failure
    ddev drush $DRUSH_ALIAS mim olympian_d7_paragraphs_publication_full_video --update --continue-on-failure
    ddev drush $DRUSH_ALIAS mim olympian_d7_paragraphs_revisions_publication_full_video --update --continue-on-failure
    ddev drush $DRUSH_ALIAS mim olympian_d7_paragraphs_publication_call_to_action --update --continue-on-failure
    ddev drush $DRUSH_ALIAS mim olympian_d7_paragraphs_revisions_publication_call_to_action --update --continue-on-failure
    ddev drush $DRUSH_ALIAS mim olympian_d7_paragraphs_table_of_contents_anchors --update --continue-on-failure
    ddev drush $DRUSH_ALIAS mim olympian_d7_paragraphs_revisions_table_of_contents_anchors --update --continue-on-failure
    ddev drush $DRUSH_ALIAS mim olympian_d7_paragraphs_intro_with_tob --update --continue-on-failure
    ddev drush $DRUSH_ALIAS mim olympian_d7_paragraphs_revisions_intro_with_tob --update --continue-on-failure
    ddev drush $DRUSH_ALIAS mim olympian_d7_node_complete_publication_page  --execute-dependencies --continue-on-failure --update
    set +x
fi

set -x
ddev drush $DRUSH_ALIAS mim olympian_d7_node_complete_page  --continue-on-failure --update

ddev drush $DRUSH_ALIAS smart_date:migrate events field_event_smart_date field_event_date field_event_date field_date_all_day --clear
ddev drush cim -y
ddev drush cr
ddev drush uli
set +x
echo -e "\n$(tput setaf 3)$(tput bold)>>>>> $(tput setaf 5)$(TZ=America/Chicago date +"%Y-%m-%d-%T") $(tput setaf 2)Finished ${BASH_SOURCE} $(tput setaf 3)<<<<<$(tput sgr0)\n"
