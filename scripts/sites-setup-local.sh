#!/bin/bash

# Build multisite from a yaml list
set -e
cd "$(dirname "${BASH_SOURCE[0]}")"
# Load yml library
source ./lib/yaml.sh
# Convert yml to variables usable in bash
create_variables sitelist.yml
# Blank out the sql script
> sql-multisite-create-d9.sql
> sql-multisite-create-d7.sql
# Set up env variables for MySQL host, users
SQLHOST="${SQLHOST:-localhost}"
db_d9user="${SQLD9USER:-$db_d9user}"
db_d7user="${SQLD7USER:-$db_d7user}"
# In case we need to debug that yml reader 
( set -o posix ; set ) >variables.before
# Loop through defined sites from yaml
numsites=${#sites__name[@]}
printf "Initiating %s sites\n" "${numsites}"
for (( j=0; j<numsites; j++ )); do
  printf "Checking %s\n" "${sites__name[$j]}"
  
  # Add database names to db creation script (even if folder is already set up)
  # Check if the db_d9 variable is set before creating lines in the SQL file
  if [ -n "${sites__db[j]}" ]; then
    echo "CREATE DATABASE IF NOT EXISTS ${sites__db[j]};" >> sql-multisite-create-d9.sql
    echo "GRANT ALL PRIVILEGES ON ${sites__db[j]}.* TO '${db_d9user}'@'${SQLHOST}';" >> sql-multisite-create-d9.sql
  fi

  # Check if the db_d7 variable is set before creating lines in the SQL file
  if [ -n "${sites__db_d7[j]}" ]; then
    echo "CREATE DATABASE IF NOT EXISTS ${sites__db_d7[j]};" >> sql-multisite-create-d7.sql
    echo "GRANT ALL PRIVILEGES ON ${sites__db_d7[j]}.* TO '${db_d7user}'@'${SQLHOST}';" >> sql-multisite-create-d7.sql
  fi

  # Do files setup only if the folder doesn't already exist
  if ([ -d "../web/sites/${sites__name[$j]}.wustl.edu" ]); then
    continue 
  fi

  printf "Creating %s\n" "${sites__name[$j]}"
  cp -Rpi "../web/sites/templatesite" "../web/sites/${sites__name[$j]}.wustl.edu"
  # Replace database names in settings.php file
  if [ -n "${sites__db[j]}" ]; then
    sed -i "s/db_d9/${sites__db[j]}/g" "../web/sites/${sites__name[$j]}.wustl.edu/settings.php"
  fi

  if [ -n "${sites__db_d7[j]}" ]; then
    sed -i "s/db_d7/${sites__db_d7[j]}/g" "../web/sites/${sites__name[$j]}.wustl.edu/settings.php"
  fi
  #replace url_d7 in settings.php (needed for migration)
  if [ -n "${sites__url_d7[j]}" ]; then
    sed -i "s/url_d7/${sites__url_d7[j]}/g" "../web/sites/${sites__name[$j]}.wustl.edu/settings.php"
  fi
done

# Generate drush sites yml

numenvironments=${#environments__name[@]}
printf "Setting up %s drush sites environments\n" "${numenvironments}"
for (( i=0; i<numenvironments; i++ )); do
    # Clear the file
  > "../drush/sites/${environments__name[$i]}.site.yml"
  for (( j=0; j<numsites; j++ )); do
    # Add lines for each
    if [ -n "${environments__drush_template[$i]}" ]; then 
      cat "${environments__drush_template[$i]}" >> "../drush/sites/${environments__name[$i]}.site.yml"
      # Replace sitename variable
      sed -i "s/sitename/${sites__name[j]}/g" "../drush/sites/${environments__name[$i]}.site.yml"
      # Replace sitehost variable
      sed -i "s/sitehost/${environments__host[i]}/g" "../drush/sites/${environments__name[$i]}.site.yml"
      
            # Replace siteuri variable
      sed -i "s|siteuri|https://${sites__name[j]}.${environments__suffix[i]}|g" "../drush/sites/${environments__name[$i]}.site.yml"
    fi
  done
done
      
