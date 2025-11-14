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
    echo "$(tput setaf 2)Usage: run-files-migration.sh @drushalias [--backup] $(tput sgr0)"
    exit 1
fi

DRUSH_ALIAS=$1
shift # Shift arguments to the left

# Flags for backup, and shared dependencies only, and files only
DO_BACKUP=false

# Process optional arguments
while (( "$#" )); do
    case "$1" in
        --backup)
            DO_BACKUP=true
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

printf "\n"
echo "$(tput setaf 3)*****************************************************$(tput sgr0)"
echo "$(tput setaf 3)  This script is for media migration only,$(tput sgr0)"
echo "$(tput setaf 3)  to get all the physical files needed by the site.$(tput sgr0)"
echo "$(tput setaf 3)  For a full migration, use run-migration.sh.$(tput sgr0)"
echo "$(tput setaf 3)*****************************************************$(tput sgr0)"
printf "\n"

# Ask if there is publication content
read -p "Resync d7 databases from artscistage? (y/n) [n]: " RESYNC_DATABASES
read -p "Sync d7 database you want to migrate from virtualrecognition server? (y/n) [n]: " RESYNC_D7_DATABASES
read -p "Do you want to reset your local environment? (y/n) [y]: " RESET_LOCAL

RESET_LOCAL="${RESET_LOCAL:-y}"
RESYNC_DATABASES="${RESYNC_DATABASES:-n}"
RESYNC_D7_DATABASES="${RESYNC_D7_DATABASES:-n}"

printf "\n\n"
echo "......................................"
echo "Confirming choices:"
echo "  RESYNC_DATABASES:    ${RESYNC_DATABASES}"
echo "  RESYNC_D7_DATABASES: ${RESYNC_D7_DATABASES}"
echo "  RESET_LOCAL:         ${RESET_LOCAL}"
echo "......................................"
printf "\n\n"

# Ask for passwords
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

# Offer URL options
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
read -p "Enter the number (1, 2, or 3) corresponding to the URL [1]: " selection
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
echo "$(tput setaf 2)The D10 site folder is: ${d10_folder}$(tput sgr0)"
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
    # Define the source directory on the remote server
    remote_source="$drush_user@artscistage.wustl.edu:/exports/nfsdrupal/d9main/d7data/"
    echo "$(tput setaf 2)Files will be synced from $remote_source.$(tput sgr0)"
    echo "$(tput setaf 2)Resyncing databases...$(tput sgr0)"
    set -x
    rsync -avz --progress ${remote_source}backups/ ./d7data
    rsync -avz --progress ${remote_source}backups/cleanup_d7.sql  ./scripts/migration/
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
      echo "$(tput setaf 2)Copying db backup from D7 PROD to LOCAL: d7data/drupal_${alias}_backup.sql...$(tput sgr0)"
      rsync -azv --progress --rsh="sshpass -p '${PWD_D7PROD}' ssh -o StrictHostKeyChecking=no -l drupaladm" virtualrecognitiontest.artsci.wustl.edu:/export/tempfiles/backups/drupal_${alias}_backup.sql d7data/
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

# Do media migration only
echo "$(tput setaf 2)Running migrations for media$(tput sgr0)"
set -x
ddev drush $DRUSH_ALIAS mim media_image_migration --execute-dependencies --continue-on-failure --update
ddev drush $DRUSH_ALIAS mim media_cv_migration --continue-on-failure --update
ddev drush $DRUSH_ALIAS mim media_document_migration --continue-on-failure --update
set +x

set -x
ddev dev-settings-off
ddev drush cset simplesamlphp_auth.settings activate true --yes
set +x

if [ "$CREATE_FILES_ARCHIVE" = "y" ]; then
  printf "\n"
  # Use ddev to run tar to avoid issues from OSX tar.
  # Add "&&" at end of tar to make sure it finished, then just check the file size. (see https://stackoverflow.com/questions/22141828/how-to-know-when-a-tar-command-finished)
  echo "$(tput setaf 2)Creating migrated D10 files archive: ${local_default_folder}/files.tar.gz...$(tput sgr0)"
  set -x
  ddev exec "tar -C ${local_default_folder} -cvzf ${local_default_folder}/files.tar.gz files && wait && printf '\n(tar done)\n'"
  set +x
  wait
  echo "$(tput setaf 3)To upload files.tar.gz to artscistage:$(tput sgr0)"
  echo "$(tput setaf 6)  rsync -azv --progress ${local_default_folder}/files.tar.gz drupaladm@artscistage.wustl.edu:/home/drupaladm/d9main/web/sites/${d10_folder}$(tput sgr0)"
fi

echo "\n\n$(tput setaf 3)$(tput bold)>>>>> $(tput setaf 5)$(TZ=America/Chicago date +"%Y-%m-%d-%T") $(tput setaf 2)Finished ${BASH_SOURCE} $(tput setaf 3)<<<<<$(tput sgr0)\n\n"
