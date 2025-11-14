#!/bin/bash
Red='\033[0;31m'          # Red
Green='\033[0;32m'
blue="\033[0;34m"
NC='\033[0m' # No Color
#!/bin/bash

# Define the path to your drush.local.yml file
drush_local_yml="./drush/drush.local.yml"  # Change this to your actual path

# Extract the drush_user from the drush.local.yml file
drush_user=$(grep '^drush_user:' "$drush_local_yml" | awk '{print $2}')

# Check if drush_user is found
if [ -z "$drush_user" ]; then
    echo "drush_user not found in $drush_local_yml"
    exit 1
fi

# Define the source directory on the remote server
remote_source="dani@artscistage.wustl.edu:/exports/nfsdrupal/d9main/d7data/"

# Use rsync to sync files
rsync -avz --progress --ignore-existing "$drush_user@artscistage.wustl.edu:/exports/nfsdrupal/d9main/d7data/" .

# Notify user
echo "Files have been synced from $drush_user@artscistage.wustl.edu."

echo "download d7data directory from stage with your servername like: ${blue}scp -r dani@artscistage.wustl.edu:/exports/nfsdrupal/d9main/d7data .${NC}"
# Copy settings.local.php file to the default site
cp -R ./d7data/settings.local.php ./web/sites/default/
cp -R ./d7data/cleanup_d7.sql ./scripts/migration/
# Prompt for the D7 alias name with a default value
default_alias="biology"  # Set your desired default alias here
read -p "Enter the name of the D7 site you want to migrate (example: it, history, anthropology) [${default_alias}]: " alias
alias="${alias:-$default_alias}"

# Assume the d7_site is alias.wustl.edu
default_d7_site="https://${alias}.wustl.edu/"

# Prompt for confirmation or editing
read -p "The assumed D7 site URL is ${default_d7_site}. Press Enter to confirm or type the correct URL if different: " d7_site
d7_site="${d7_site:-$default_d7_site}"

# Define the .env file path
env_file=".env"

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
ddev import-db --file="d7data/d10.sql"

echo "Updated D7_SITE in $env_file to: $d7_site"

# Run the ddev import-db command for the D7 database
ddev import-db --file="d7data/drupal_${alias}_backup.sql" --target-db=d7

# Notify user
echo "Database import command executed for the alias: $alias"

# Notify the user
echo "The script has completed successfully."
