#!/bin/bash

# see tput color codes and text effects here: https://linuxcommand.org/lc3_adv_tput.php
# 0	Black
# 1	Red
# 2	Green
# 3	Yellow
# 4	Blue
# 5	Magenta
# 6	Cyan
# 7	White
# 8	Not used
# 9	Reset to default color
# Text effects:
#   setaf = set foreground color
#   sgr0	= Turn off all attributes

# Show info about this script, and setup PS4 for prefix of each trace line from "set -x"
echo "\n$(tput setaf 3)$(tput bold)>>>>> $(tput setaf 5)$(TZ=America/Chicago date +"%Y-%m-%d-%T") $(tput setaf 2)Running ${BASH_SOURCE} $(tput setaf 3)<<<<<$(tput sgr0)\n"
PS4='$(tput setaf 3)$(tput bold)>> $(tput setaf 5)$(TZ=America/Chicago date +%H:%M:%S.%3N) $(tput setaf 2)(${LINENO})$(tput sgr0) '

# Get command-line parameters
# Check if at least one argument is provided
if [ "$#" -lt 1 ]; then
    echo "$(tput setaf 2)Usage: run-migration.sh @drushalias [--backup] [--reset] [--shared-deps-only]$(tput sgr0)"
    exit 1
fi

DRUSH_ALIAS=$1
shift # Shift arguments to the left

# Flags for backup, and shared dependencies only, and files only
DO_BACKUP=false
SHARED_DEPS_ONLY=false

# Process optional arguments
while (( "$#" )); do
    case "$1" in
        --backup)
            DO_BACKUP=true
            shift
            ;;
        --shared-deps-only)
            SHARED_DEPS_ONLY=true
            shift
            ;;
        *)
            echo "$(tput setaf 1)Invalid option: $1$(tput sgr0)"
            exit 1
            ;;
    esac
done

# Define the path to your drush.local.yml file
drush_local_yml="./drush/drush.local.yml"  # Change this to your actual path

# Extract the drush_user from the drush.local.yml file
drush_user=$(grep '^drush_user:' "$drush_local_yml" | awk '{print $2}')

# Check if drush_user is found
if [ -z "$drush_user" ]; then
    echo "$(tput setaf 1)drush_user not found in $drush_local_yml$(tput sgr0)"
    exit 1
fi

# Define the source directory on the remote server
remote_source="$drush_user@artscistage.wustl.edu:/exports/nfsdrupal/d9main/d7data/"
# Notify user
echo "$(tput setaf 2)Files will be synced from $drush_user@artscistage.wustl.edu.$(tput sgr0)"

# Ask if there is publication content
read -p "Is there publication content? (y/n) [y] " PUB_CONTENT
read -p "Is there course content? (y/n) [y] " COURSE_CONTENT
read -p "Resync d7 databases from artscistage? (y/n) [n]: " RESYNC_DATABASES
read -p "Sync d7 database you want to migrate from virtualrecognition server? (y/n) [n]: " RESYNC_D7_DATABASES
read -p "Do you want to reset your local environment? (y/n) [y]: " RESET_LOCAL

PUB_CONTENT="${PUB_CONTENT:-y}"
COURSE_CONTENT="${COURSE_CONTENT:-y}"
RESET_LOCAL="${RESET_LOCAL:-y}"
RESYNC_DATABASES="${RESYNC_DATABASES:-n}"
RESYNC_D7_DATABASES="${RESYNC_D7_DATABASES:-n}"

printf "\n\n"
echo "......................................"
echo "Confirming command-line options:"
echo "  DO_BACKUP:           ${DO_BACKUP}"
echo "  SHARED_DEPS_ONLY:    ${SHARED_DEPS_ONLY}"
echo "Confirming choices:"
echo "  PUB_CONTENT:         ${PUB_CONTENT}"
echo "  COURSE_CONTENT:      ${COURSE_CONTENT}"
echo "  RESYNC_DATABASES:    ${RESYNC_DATABASES}"
echo "  RESYNC_D7_DATABASES: ${RESYNC_D7_DATABASES}"
echo "  RESET_LOCAL:         ${RESET_LOCAL}"
echo "Confirming operations based on above:"
if [ "$PUB_CONTENT" = "y" ]; then
  echo '  - will be doing publication content'
else
  echo '  - will SKIP publication content'
fi
if [ "$COURSE_CONTENT" = "y" ]; then
  echo '  - will be doing course content'
else
  echo '  - will SKIP course content'
fi
echo "......................................"
printf "\n\n"

# Ask for passwords
read -s -p "Password for drupaladm on artscistage (D10 Stage)? " PWD_D10STAGE
printf "\n"
if [ "$RESYNC_D7_DATABASES" = "y" ]; then
  read -s -p "Password for drupaladm on virtualrecognitiontest (D7 Prod)? " PWD_D7PROD
  printf "\n"
fi

# Determine the directory in which the script resides
SCRIPT_DIR="$( cd "$( dirname "${BASH_SOURCE[0]}" )" &> /dev/null && pwd )"


    # Set your desired default alias
    default_alias="biology"

    # Set your desired default alias
    default_alias="biology"

    # Display instructions
    printf "\n"
    echo "$(tput setaf 3)You need the trailing / and be sure to choose the correct option for the URL.$(tput sgr0)"
    echo "$(tput setaf 3)For example, if you migrate it.artsci.wustl.edu, $(tput sgr0)$(tput setaf 4)it$(tput sgr0)$(tput setaf 3) would be alias, and the URL would be $(tput sgr0)$(tput setaf 4)https://it.artsci.wustl.edu/$(tput sgr0)"

    # Prompt for the alias
    read -p "Enter the name of the D7 site you want to migrate (example: it, history, anthropology) [${default_alias}]: " alias
    alias="${alias:-$default_alias}"

    # Offer two URL options
    domain1="wustl.edu"
    domain2="artsci.wustl.edu"
    option1="https://${alias}.${domain1}/"
    option2="https://${alias}.${domain2}/"

    # Display options
    printf "\n"
    echo "$(tput setaf 3)Please select the appropriate D7 site URL:$(tput sgr0)"
    echo "$(tput setaf 3)1) ${option1} (default)$(tput sgr0)"
    echo "$(tput setaf 3)2) ${option2}$(tput sgr0)"

    # Prompt for selection with the default set to option 1
    read -p "Enter the number (1 or 2) corresponding to the URL [1]: " selection
    selection="${selection:-1}"  # Default to 1 if no input is given

    # Validate selection
    if [ "$selection" == "2" ]; then
        d7_site="$option2"
        d10_folder="${alias}.${domain2}"
    else
        d7_site="$option1"
        d10_folder="${alias}.${domain1}"
    fi

    echo "$(tput setaf 2)The final D7 site URL is: ${d7_site}$(tput sgr0)"
    d7_site_files=${d7_site}files/${alias}

    # Define the .env file path
    env_file="./.env"

    # Check if the .env file exists
    if [ ! -f "$env_file" ]; then
        echo "$(tput setaf 2)The .env file does not exist in the current directory.$(tput sgr0)"
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
    if grep -q "D7_PREFIX=" "$env_file"; then
        # If D7_PREFIX already exists, replace it
        sed -i '' "s|^D7_PREFIX=.*|D7_PREFIX=$alias|" "$env_file"
    else
        # If it doesn't exist, add it
        echo "D7_PREFIX=$alias" >> "$env_file"
    fi
    if grep -q "D7_SITE_FILES=" "$env_file"; then
        # If D7_SITE_FILES already exists, replace it
        sed -i '' "s|^D7_SITE_FILES=.*|D7_SITE_FILES=$d7_site_files|" "$env_file"
    else
        # If it doesn't exist, add it
        echo "D7_SITE_FILES=$d7_site_files" >> "$env_file"
    fi

# Define and cleanup the local default files directory
local_default_folder="web/sites/default"
local_files_folder="${local_default_folder}/files"
printf "\n"
echo "$(tput setaf 3)This script will clear the ${local_files_folder} folder on your local$(tput sgr0)"
echo "$(tput setaf 3)and populate it with migrated media files for ${alias}. $(tput sgr0)"
echo "$(tput setaf 3)That folder will be zipped into files.tar.gz, which can be uploaded to artscistage. $(tput sgr0)"
ls -aldF ${local_files_folder}
read -p "Continue (enter n to skip this step)? (y/n) [y] " CREATE_FILES_ARCHIVE
CREATE_FILES_ARCHIVE="${CREATE_FILES_ARCHIVE:-y}"
if [ "$CREATE_FILES_ARCHIVE" = "y" ]; then
  # Also delete any hidden files starting with dot
  # (see https://unix.stackexchange.com/questions/310754/how-to-delete-all-files-in-a-current-directory-starting-with-a-dot)
  echo "$(tput setaf 2)Deleting contents of ${local_files_folder}...$(tput sgr0)"
  rm -r ${local_files_folder}/*
  rm ${local_files_folder}/.??*
  echo "$(tput setaf 2)Finished deleting contents of ${local_files_folder}.$(tput sgr0)"
else
  echo "$(tput setaf 2)Will not create files.tar.gz archive.$(tput sgr0)"
fi

if [ "$RESYNC_DATABASES" = "y" ]; then
    printf "\n"
    echo "$(tput setaf 2)Resyncing databases...$(tput sgr0)"
    set -x
    rsync -avz --progress $drush_user@artscistage.wustl.edu:/exports/nfsdrupal/d9main/d7data/backups/ ./d7data
    rsync -avz --progress $drush_user@artscistage.wustl.edu:/exports/nfsdrupal/d9main/d7data/backups/cleanup_d7.sql  ./scripts/migration/
    set +x

    # Copy settings.local.php file to the default site
    if [[ -f ./d7data/settings.local.php ]]; then
        cp -R ./d7data/settings.local.php ./web/sites/default/
    else
        echo "$(tput setaf 1)settings.local.php not found.$(tput sgr0)"
    fi

    cp -R ./d7data/cleanup_d7.sql ./scripts/migration/
fi

if [ "$RESET_LOCAL" = "y" ]; then
    printf "\n"
    echo "$(tput setaf 2)Resetting local...$(tput sgr0)"

    # Copy settings.local.php file to the default site
    if [[ -f ./d7data/settings.local.php ]]; then
        cp -R ./d7data/settings.local.php ./web/sites/default/
    else
        echo "$(tput setaf 1)settings.local.php not found.$(tput sgr0)"
    fi

    cp -R ./d7data/cleanup_d7.sql ./scripts/migration/

    # Get d7 database
    if [ "$RESYNC_D7_DATABASES" = "y" ]; then
      printf "\n"
      echo "$(tput setaf 2)Copying D7 db backup from D7 PROD to LOCAL: d7data/drupal_${alias}_backup.sql...$(tput sgr0)"
      ${SCRIPT_DIR}/scp_utility.exp drupaladm@virtualrecognitiontest.artsci.wustl.edu:/export/tempfiles/backups/drupal_${alias}_backup.sql d7data/ "${PWD_D7PROD}"

      printf "\n"
      echo "$(tput setaf 2)Copying D7 db backup from LOCAL to D10 STAGE: artscistage.wustl.edu:/home/drupaladm/d9main/d7data/backups/drupal_${alias}_backup.sql...$(tput sgr0)"
      ${SCRIPT_DIR}/scp_utility.exp d7data/drupal_${alias}_backup.sql drupaladm@artscistage.wustl.edu:/home/drupaladm/d9main/d7data/backups/ "${PWD_D10STAGE}"
    fi

    # Run the ddev import-db command for d10base installation
    set -x
    ddev drush sql-sync @stage.migrate @self --yes
    ddev drush cim --yes
    set +x
    echo "$(tput setaf 2)Updated D7_SITE in $env_file to: $d7_site$(tput sgr0)"

    # Run the ddev import-db command for the D7 database
    set -x
    ddev import-db --file="d7data/drupal_${alias}_backup.sql" --database=d7
    set +x

    # Notify user
    echo "$(tput setaf 2)Database import command executed for the alias: $alias$(tput sgr0)"

    # Notify the user
    echo "$(tput setaf 2)The script has completed successfully.$(tput sgr0)"
fi

# Function to perform database backup
backup_database() {
    NOW=$(date +"%m-%d-%Y-%T")
    BACKUP_FILE="migration-backup-$NOW.sql"
    echo "$(tput setaf 2)Backing up database to $BACKUP_FILE$(tput sgr0)"
    ddev drush $DRUSH_ALIAS sql-dump > "$BACKUP_FILE"
}

# Perform backup if --backup option is provided
if [ "$DO_BACKUP" = true ]; then
    backup_database
fi

echo "$(tput setaf 2)Clearing cache for funsies$(tput sgr0)"
set -x
ddev dev-settings $DRUSH_ALIAS
ddev drush $DRUSH_ALIAS cr
# ddev drush $DRUSH_ALIAS updb -y
ddev drush $DRUSH_ALIAS cim -y
ddev drush $DRUSH_ALIAS state:set config_split.config_split.secure_content:status FALSE
ddev drush $DRUSH_ALIAS state:set config_split.config_split.site:status FALSE
ddev drush $DRUSH_ALIAS state:set config_split.config_split.coin:status FALSE
ddev drush $DRUSH_ALIAS state:set config_split.config_split.dev:status FALSE
ddev drush $DRUSH_ALIAS sapi-disa
set +x
echo "$(tput setaf 2)Migration process initiated for alias: $DRUSH_ALIAS$(tput sgr0)"

# Adjust the migration command based on whether --shared-deps-only is provided
if [ "$SHARED_DEPS_ONLY" = true ]; then
    printf "\n"
    echo "$(tput setaf 2)Running migrations with tag 'shared-dep'$(tput sgr0)"
    set -x
    ddev drush $DRUSH_ALIAS migrate:import --tag=shared-dep --continue-on-failure --update
    set +x
else
    printf "\n"
    echo "$(tput setaf 2)Running migrations with tag 'artsci'$(tput sgr0)"
    set -x
    ddev drush $DRUSH_ALIAS cr
    ddev drush $DRUSH_ALIAS uli
    # Import some d7 configs
    ddev drush $DRUSH_ALIAS sql:query --database=migrate --file=/var/www/html/scripts/migration/cleanup_d7.sql
    ddev drush $DRUSH_ALIAS mim olympian_d7_user_role --execute-dependencies --continue-on-failure --update
    ddev drush $DRUSH_ALIAS mim olympian_d7_system_site  --execute-dependencies --continue-on-failure --update
    ddev drush $DRUSH_ALIAS mim olympian_d7_user --execute-dependencies --continue-on-failure --update
    ddev drush $DRUSH_ALIAS uli
    ddev drush $DRUSH_ALIAS mim oly_d7_menu --execute-dependencies --continue-on-failure --update
    ddev drush $DRUSH_ALIAS mim oly_d7_menu_links --execute-dependencies --continue-on-failure --update
    ddev drush $DRUSH_ALIAS migrate:import olympian_theme_settings --update  --continue-on-failure
    ddev drush $DRUSH_ALIAS mim olympian_d7_pathauto_patterns --execute-dependencies --continue-on-failure --update
    ddev drush $DRUSH_ALIAS mim olympian_d7_taxonomy_term_resource_categories --continue-on-failure --update
    ddev drush $DRUSH_ALIAS mim olympian_d7_taxonomy_term_article_categories --continue-on-failure --update
    ddev drush $DRUSH_ALIAS mim olympian_d7_taxonomy_term_event_categories --continue-on-failure --update
    ddev drush $DRUSH_ALIAS mim olympian_d7_taxonomy_term_areas_of_interest --continue-on-failure --update
    ddev drush $DRUSH_ALIAS mim olympian_d7_taxonomy_term_book_ --continue-on-failure --update
    ddev drush $DRUSH_ALIAS mim olympian_d7_taxonomy_term_image_cards --continue-on-failure --update
    ddev drush $DRUSH_ALIAS mim olympian_d7_taxonomy_term_leadership_administrative_staff --continue-on-failure --update
    ddev drush $DRUSH_ALIAS mim olympian_d7_taxonomy_term_levels --continue-on-failure --update
    ddev drush $DRUSH_ALIAS mim olympian_d7_taxonomy_term_course_level --continue-on-failure --update
    ddev drush $DRUSH_ALIAS mim olympian_d7_taxonomy_term_concentration --continue-on-failure --update
    ddev drush $DRUSH_ALIAS mim olympian_d7_taxonomy_term_requirements --continue-on-failure --update
    ddev drush $DRUSH_ALIAS mim olympian_d7_taxonomy_term_semester --continue-on-failure --update
    ddev drush $DRUSH_ALIAS mim olympian_d7_taxonomy_term_tags --continue-on-failure --update
    ddev drush $DRUSH_ALIAS mim olympian_d7_taxonomy_term_grad_undergrad --continue-on-failure --update
    ddev drush $DRUSH_ALIAS mim olympian_d7_taxonomy_term_media_folders --execute-dependencies --continue-on-failure --update
    ddev drush $DRUSH_ALIAS mim file_migration --execute-dependencies --continue-on-failure --update
    ddev drush $DRUSH_ALIAS mim focal_point_settings --continue-on-failure --update
    ddev drush $DRUSH_ALIAS mim focal_point_crop_type --continue-on-failure --update
    ddev drush $DRUSH_ALIAS mim olympian_focal_point_crop --continue-on-failure --update
    ddev drush $DRUSH_ALIAS mim media_image_migration --continue-on-failure --update ## don't --execute-dependencies here, because it will just do file_migration (which already ran in an earlier step) and create double the files
    ddev drush $DRUSH_ALIAS mim media_cv_migration --continue-on-failure --update
    ddev drush $DRUSH_ALIAS mim media_document_migration --continue-on-failure --update
    ddev drush $DRUSH_ALIAS migrate:import olympian_theme_settings --update  --continue-on-failure
    ddev drush $DRUSH_ALIAS migrate:import olympian_theme_settings --update  --continue-on-failure
    ddev drush $DRUSH_ALIAS migrate:import olympian_d7_system_site --update  --continue-on-failure
    ddev drush $DRUSH_ALIAS mim olympian_d7_webform --execute-dependencies --update --continue-on-failure
    ddev drush $DRUSH_ALIAS mim olympian_d7_paragraphs_tabs --update --continue-on-failure
    ddev drush $DRUSH_ALIAS migrate:import olympian_d7_paragraphs_table_of_contents_anchors --continue-on-failure --update
    ddev drush $DRUSH_ALIAS mim olympian_d7_node_complete_resources --continue-on-failure --update
    ddev drush $DRUSH_ALIAS mim olympian_d7_paragraphs_links --continue-on-failure --update
    ddev drush $DRUSH_ALIAS mim olympian_d7_paragraphs_intro_with_tob	--continue-on-failure --update
    ddev drush $DRUSH_ALIAS mim olympian_d7_paragraphs_timeline_slideshow_slides --update
    ddev drush $DRUSH_ALIAS mim olympian_d7_paragraphs_timeline_slides --execute-dependencies --update
    ddev drush $DRUSH_ALIAS mim olympian_d7_paragraphs_timeline_slideshow --update
    ddev drush $DRUSH_ALIAS mim olympian_d7_paragraphs_sample_courses --continue-on-failure --update
    ddev drush $DRUSH_ALIAS mim olympian_d7_paragraphs_social_media_links --continue-on-failure --update
    ddev drush $DRUSH_ALIAS mim olympian_d7_paragraphs_course_samples  --continue-on-failure --update
    ddev drush $DRUSH_ALIAS mim olympian_d7_paragraphs_sample_courses_publications  --continue-on-failure --update
    ddev drush $DRUSH_ALIAS mim olympian_d7_paragraphs_course_samples  --continue-on-failure --update
    ddev drush $DRUSH_ALIAS mim olympian_d7_paragraphs_lightbox_image --update  --continue-on-failure
    ddev drush $DRUSH_ALIAS mim olympian_d7_paragraphs_image_gallery --update  --continue-on-failure
    ddev drush $DRUSH_ALIAS mim olympian_d7_node_complete_faculty_staff --continue-on-failure --update
    ddev drush $DRUSH_ALIAS mim olympian_d7_node_complete_testimonial --continue-on-failure --update
    ddev drush $DRUSH_ALIAS mim olympian_d7_node_complete_resources --continue-on-failure --update
    ddev drush $DRUSH_ALIAS mim olympian_d7_node_complete_events --execute-dependencies --update --continue-on-failure
    ddev drush $DRUSH_ALIAS mim olympian_d7_node_complete_article --execute-dependencies --update --continue-on-failure
    ddev drush $DRUSH_ALIAS mim olympian_d7_paragraphs_video_spotlights_and_articles --execute-dependencies --continue-on-failure  --update
    ddev drush $DRUSH_ALIAS mim olympian_d7_paragraphs_fullscreen_image --continue-on-failure  --update
    ddev drush $DRUSH_ALIAS mim olympian_d7_paragraphs_fullscreen_slideshow --continue-on-failure  --update --execute-dependencies
    ddev drush $DRUSH_ALIAS mim olympian_d7_paragraphs_fullscreen_slideshow --continue-on-failure  --update --execute-dependencies
    ddev drush $DRUSH_ALIAS mim olympian_d7_paragraphs_horizontal_slide --continue-on-failure  --update
    ddev drush $DRUSH_ALIAS mim olympian_d7_paragraphs_multipurpose_slideshow --update --continue-on-failure --execute-dependencies
    ddev drush $DRUSH_ALIAS mim olympian_d7_paragraphs_timeline_slides --update --continue-on-failure  --execute-dependencies
    ddev drush $DRUSH_ALIAS mim olympian_d7_paragraphs_slideshow --update --continue-on-failure  --execute-dependencies
    ddev drush $DRUSH_ALIAS mim olympian_d7_paragraphs_sidebar_slideshow --update --continue-on-failure
    ddev drush $DRUSH_ALIAS mim olympian_d7_paragraphs_resources_callout --update --continue-on-failure
    ddev drush $DRUSH_ALIAS mim olympian_d7_paragraphs_two_column_content --update --continue-on-failure
    ddev drush $DRUSH_ALIAS mim olympian_d7_node_complete_generic_page --execute-dependencies --update  --continue-on-failure
    ddev drush $DRUSH_ALIAS mim olympian_d7_node_complete_book --continue-on-failure --update
    ddev drush $DRUSH_ALIAS mim olympian_d7_paragraphs_links --continue-on-failure --update
    ddev drush $DRUSH_ALIAS mim olympian_d7_paragraphs_testimonial --continue-on-failure --update
    ddev drush $DRUSH_ALIAS mim olympian_d7_paragraphs_events --continue-on-failure --update
    ddev drush $DRUSH_ALIAS mim olympian_d7_paragraphs_spotlight --continue-on-failure --update
    ddev drush $DRUSH_ALIAS mim olympian_d7_paragraphs_spotlights --continue-on-failure --update
    ddev drush $DRUSH_ALIAS mim olympian_d7_paragraphs_featured_full_w_text --continue-on-failure --update
    ddev drush $DRUSH_ALIAS mim olympian_d7_paragraphs_featured_with_caption --continue-on-failure --update
    ddev drush $DRUSH_ALIAS mim olympian_d7_paragraphs_featured_half_image_w_text --continue-on-failure --update
    ddev drush $DRUSH_ALIAS mim olympian_d7_paragraphs_intro_text_with_image --continue-on-failure --update
    ddev drush $DRUSH_ALIAS mim olympian_d7_paragraphs_intro_with_links --continue-on-failure --update
    ddev drush $DRUSH_ALIAS mim olympian_d7_paragraphs_full_screen_home_slide --continue-on-failure --update
    ddev drush $DRUSH_ALIAS mim olympian_d7_paragraphs_testimonials --continue-on-failure --update
    ddev drush $DRUSH_ALIAS mim olympian_d7_paragraphs_additional_resource --continue-on-failure --update
    ddev drush $DRUSH_ALIAS mim olympian_d7_paragraphs_additional_resources --continue-on-failure --update
    ddev drush $DRUSH_ALIAS mim olympian_d7_paragraphs_text_link_with_testimonial --continue-on-failure --update
    ddev drush $DRUSH_ALIAS mim olympian_d7_paragraphs_call_to_action_blocks --continue-on-failure --update
    ddev drush $DRUSH_ALIAS mim olympian_d7_paragraphs_link_title_and_body --continue-on-failure --update
    ddev drush $DRUSH_ALIAS mim olympian_d7_paragraphs_featured_news --continue-on-failure --update
    ddev drush $DRUSH_ALIAS mim olympian_d7_paragraphs_callout --continue-on-failure --update
    ddev drush $DRUSH_ALIAS mim olympian_d7_paragraphs_instagram_twitter_section --continue-on-failure --update
    ddev drush $DRUSH_ALIAS mim olympian_d7_paragraphs_article_spotlight --continue-on-failure --update
    ddev drush $DRUSH_ALIAS mim olympian_d7_paragraphs_related_links --continue-on-failure --update
    ddev drush $DRUSH_ALIAS mim olympian_d7_node_complete_home_page --continue-on-failure --update
    ddev drush $DRUSH_ALIAS mim olympian_d7_paragraphs_related_links  --continue-on-failure --update
    ddev drush $DRUSH_ALIAS mim olympian_d7_paragraphs_optional_callout  --continue-on-failure --update
    ddev drush $DRUSH_ALIAS mim olympian_d7_paragraphs_header  --continue-on-failure --update
    ddev drush $DRUSH_ALIAS mim olympian_d7_node_complete_image_cards --continue-on-failure --update
    ddev drush $DRUSH_ALIAS mim olympian_d7_node_complete_image_cards_landing  --continue-on-failure --update
    ddev drush $DRUSH_ALIAS mim olympian_d7_node_complete_faculty_landing_page --continue-on-failure --update
    ddev drush $DRUSH_ALIAS mim olympian_d7_paragraphs_header --continue-on-failure --update
    ddev drush $DRUSH_ALIAS mim olympian_d7_node_complete_news_landing_page  --continue-on-failure --update
    ddev drush $DRUSH_ALIAS mim olympian_d7_node_complete_events_landing_page  --continue-on-failure --update
    ddev drush $DRUSH_ALIAS mim olympian_d7_node_complete_past_events_landing_page  --continue-on-failure --update
    ddev drush $DRUSH_ALIAS mim olympian_d7_paragraphs_jump_to  --continue-on-failure --update
    ddev drush $DRUSH_ALIAS mim olympian_d7_node_complete_resources_landing_page  --continue-on-failure --update --execute-dependencies
    ddev drush $DRUSH_ALIAS mim olympian_d7_paragraphs_basic_page_content  --continue-on-failure --update
    ddev drush $DRUSH_ALIAS mim olympian_d7_paragraphs_content_2  --continue-on-failure --update
    ddev drush $DRUSH_ALIAS mim olympian_d7_paragraphs_tabs  --continue-on-failure --update
    ddev drush $DRUSH_ALIAS mim olympian_d7_node_complete_contact_landing_page  --continue-on-failure --update
    ddev drush $DRUSH_ALIAS mim olympian_d7_node_complete_faq --update  --continue-on-failure --execute-dependencies
    ddev drush $DRUSH_ALIAS mim oly_d7_menu_links --execute-dependencies --update
    ddev drush $DRUSH_ALIAS mim olympian_d7_node_complete_page  --continue-on-failure --update
    ddev drush $DRUSH_ALIAS mim oly_d7_menu --execute-dependencies --continue-on-failure --update
    #need to do webform stuff AFTER url_alias so the node aliases don't mess up the webforms
    ddev drush $DRUSH_ALIAS mim olympian_d7_url_alias --update  --continue-on-failure
    ddev drush $DRUSH_ALIAS mim olympian_d7_webform --execute-dependencies --update --continue-on-failure
    ddev drush $DRUSH_ALIAS mim olympian_d7_webform_submission --execute-dependencies --update --continue-on-failure
    ddev drush $DRUSH_ALIAS mim olympian_d7_node_complete_generic_page --execute-dependencies --update  --continue-on-failure
    ddev drush $DRUSH_ALIAS mim olympian_d7_node_complete_resources_landing_page  --continue-on-failure --update --execute-dependencies
    ddev drush $DRUSH_ALIAS mim olympian_d7_node_complete_page  --continue-on-failure --update
    ddev drush $DRUSH_ALIAS mim olympian_d7_node_complete_resources_landing_page  --continue-on-failure --update --execute-dependencies
    set +x
fi

# Run publication content commands if needed
if [ "$PUB_CONTENT" = "y" ]; then
    printf "\n"
    echo "$(tput setaf 2)Processing PUBLICATION CONTENT...$(tput sgr0)"
    set -x
    ddev drush $DRUSH_ALIAS mim olympian_d7_paragraphs_publications --update --continue-on-failure
    ddev drush $DRUSH_ALIAS mim olympian_d7_paragraphs_publication_student_story --update --continue-on-failure
    ddev drush $DRUSH_ALIAS mim olympian_d7_paragraphs_publication_student_stories --update --continue-on-failure
    ddev drush $DRUSH_ALIAS mim olympian_d7_paragraphs_publication_testimonial_section --update --continue-on-failure
    ddev drush $DRUSH_ALIAS mim olympian_d7_paragraphs_publication_award --update --continue-on-failure
    ddev drush $DRUSH_ALIAS mim olympian_d7_paragraphs_publication_awards_section --update --continue-on-failure
    ddev drush $DRUSH_ALIAS mim olympian_d7_paragraphs_publication_person --update --continue-on-failure
    ddev drush $DRUSH_ALIAS mim olympian_d7_paragraphs_publication_student_story --update --continue-on-failure
    ddev drush $DRUSH_ALIAS mim olympian_d7_paragraphs_publication_testimonial_section --update --continue-on-failure
    ddev drush $DRUSH_ALIAS mim olympian_d7_paragraphs_publications_intro --update --continue-on-failure
    ddev drush $DRUSH_ALIAS mim olympian_d7_paragraphs_publication_announcement --update --continue-on-failure
    ddev drush $DRUSH_ALIAS mim olympian_d7_paragraphs_publication_announcement_feature --update --continue-on-failure
    ddev drush $DRUSH_ALIAS mim olympian_d7_paragraphs_publication_announcements --update --continue-on-failure
    ddev drush $DRUSH_ALIAS mim olympian_d7_paragraphs_publication_award --update --continue-on-failure
    ddev drush $DRUSH_ALIAS mim olympian_d7_paragraphs_publication_awards_section --update --continue-on-failure
    ddev drush $DRUSH_ALIAS mim olympian_d7_paragraphs_publication_person --update --continue-on-failure
    ddev drush $DRUSH_ALIAS mim olympian_d7_paragraphs_publication_featured_people --update --continue-on-failure
    ddev drush $DRUSH_ALIAS mim olympian_d7_paragraphs_publications_featured_news --update --continue-on-failure
    ddev drush $DRUSH_ALIAS mim olympian_d7_paragraphs_publication_degree_holder --update --continue-on-failure
    ddev drush $DRUSH_ALIAS mim olympian_d7_paragraphs_publication_degree --update --continue-on-failure
    ddev drush $DRUSH_ALIAS mim olympian_d7_paragraphs_publication_degrees_subsection --update --continue-on-failure
    ddev drush $DRUSH_ALIAS mim olympian_d7_paragraphs_publication_degrees_section --update --continue-on-failure
    ddev drush $DRUSH_ALIAS mim olympian_d7_paragraphs_publication_gallery_image --update --continue-on-failure
    ddev drush $DRUSH_ALIAS mim olympian_d7_paragraphs_publication_gallery --update --continue-on-failure
    ddev drush $DRUSH_ALIAS mim olympian_d7_paragraphs_publication_student_stories --update --continue-on-failure
    ddev drush $DRUSH_ALIAS mim olympian_d7_paragraphs_publication_full_video --update --continue-on-failure
    ddev drush $DRUSH_ALIAS mim olympian_d7_paragraphs_publication_call_to_action --update --continue-on-failure
    ddev drush $DRUSH_ALIAS mim olympian_d7_paragraphs_table_of_contents_anchors --update --continue-on-failure
    ddev drush $DRUSH_ALIAS mim olympian_d7_paragraphs_intro_with_tob --update --continue-on-failure
    ddev drush $DRUSH_ALIAS mim olympian_d7_node_complete_publication_page  --execute-dependencies --continue-on-failure --update
    set +x
    echo "$(tput setaf 2)Finished processing PUBLICATION CONTENT.$(tput sgr0)"
fi
if [ "$COURSE_CONTENT" = "y" ]; then
    printf "\n"
    echo "$(tput setaf 2)Processing COURSE CONTENT...$(tput sgr0)"
    set -x
    ddev drush $DRUSH_ALIAS mim olympian_d7_node_complete_course_landing --execute-dependencies --continue-on-failure --update
    ddev drush $DRUSH_ALIAS mim olympian_d7_paragraphs_course_attributes --execute-dependencies --continue-on-failure --update
    ddev drush $DRUSH_ALIAS mim olympian_d7_paragraphs_course_sections --execute-dependencies --continue-on-failure --update
    ddev drush $DRUSH_ALIAS mim olympian_d7_node_complete_courses --execute-dependencies --continue-on-failure --update
    set +x
    echo "$(tput setaf 2)Finished processing COURSE CONTENT.$(tput sgr0)"
fi

printf "\n"
set -x
ddev drush $DRUSH_ALIAS mim oly_d7_menu_links --continue-on-failure --update --execute-dependencies
ddev drush sc-opml
ddev drush sc-refresh
ddev drush $DRUSH_ALIAS scr scripts/migration/header-image.php
ddev drush $DRUSH_ALIAS scr scripts/migration/department-image.php
ddev drush $DRUSH_ALIAS scr scripts/migration/fix_anchors.php
ddev drush $DRUSH_ALIAS mim olympian_d7_node_complete_redirects --update --continue-on-failure
ddev drush cim -y
ddev drush cr
ddev drush cron:run olympian_seo_cron
ddev drush uli
ddev drush scu
set +x

set -x
ddev dev-settings-off
ddev drush cset simplesamlphp_auth.settings activate true --yes
set +x

printf "\n"
echo "$(tput setaf 2)Creating migrated D10 db dump into LOCAL: d7data/d10/drupal_${alias}_d10_backup.sql...$(tput sgr0)"
set -x
ddev drush sql-dump > ./d7data/d10/drupal_${alias}_d10_backup.sql
set +x
echo "$(tput setaf 2)Copying migrated D10 db dump to D10 STAGE: artscistage.wustl.edu:/exports/nfsdrupal/d9main/d7data/d10/...$(tput sgr0)"
${SCRIPT_DIR}/scp_utility.exp ./d7data/d10/drupal_${alias}_d10_backup.sql drupaladm@artscistage.wustl.edu:/exports/nfsdrupal/d9main/d7data/d10 "${PWD_D10STAGE}"

if [ "$CREATE_FILES_ARCHIVE" = "y" ]; then
  printf "\n"
  # Use ddev to run tar to avoid issues from OSX tar.
  # Add ";" at end of tar to make sure it finished, then just check the file size. (see https://stackoverflow.com/questions/22141828/how-to-know-when-a-tar-command-finished)
  echo "$(tput setaf 2)Creating migrated D10 files archive: ${local_default_folder}/files.tar.gz...$(tput sgr0)"
  set -x
  ddev exec "tar -C ${local_default_folder} -cvzf ${local_default_folder}/files.tar.gz files; wait; printf '\n(tar done)\n'"
  set +x
  wait
  echo "$(tput setaf 2)Checking archive file size...$(tput sgr0)"
  ls -al ${local_default_folder}/files.tar.gz
  echo "$(tput setaf 2)Pausing 10 seconds...$(tput sgr0)"
  sleep 10
  echo "$(tput setaf 2)Checking archive file size again...$(tput sgr0)"
  ls -al ${local_default_folder}/files.tar.gz
  echo "$(tput setaf 2)Copying ${local_default_folder}/files.tar.gz archive to D10 STAGE: artscistage.wustl.edu:/home/drupaladm/d9main/web/sites/${d10_folder}...$(tput sgr0)"
  wait
  ${SCRIPT_DIR}/scp_utility.exp ${local_default_folder}/files.tar.gz drupaladm@artscistage.wustl.edu:/home/drupaladm/d9main/web/sites/${d10_folder} "${PWD_D10STAGE}"

  printf "\n"
  echo "$(tput setaf 3)You need to check the file size again manually:$(tput sgr0)"
  echo "$(tput setaf 6)  ls -al ${local_default_folder}/files.tar.gz$(tput sgr0)"
  echo "$(tput setaf 3)If it changed from when the upload was done, redo the upload manually using scp:$(tput sgr0)"
  echo "$(tput setaf 6)  scp ${local_default_folder}/files.tar.gz drupaladm@artscistage.wustl.edu:/home/drupaladm/d9main/web/sites/${d10_folder}$(tput sgr0)"
  echo "$(tput setaf 3)or with rsync:$(tput sgr0)"
  echo "$(tput setaf 6)  rsync -azv --progress ${local_default_folder}/files.tar.gz drupaladm@artscistage.wustl.edu:/home/drupaladm/d9main/web/sites/${d10_folder}$(tput sgr0)"
fi

echo "\n\n$(tput setaf 3)$(tput bold)>>>>> $(tput setaf 5)$(TZ=America/Chicago date +"%Y-%m-%d-%T") $(tput setaf 2)Finished ${BASH_SOURCE} $(tput setaf 3)<<<<<$(tput sgr0)\n\n"
