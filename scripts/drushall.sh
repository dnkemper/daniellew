#!/bin/bash

# Initialize an array to store excluded aliases
declare -a excluded_aliases

# Function to check if an alias is in the excluded list
is_excluded() {
    local alias=$1
    for excluded_alias in "${excluded_aliases[@]}"; do
        if [[ "$alias" == "$excluded_alias" ]]; then
            return 0 # True, alias is excluded
        fi
    done
    return 1 # False, alias is not excluded
}

# Parse --except arguments
while [[ "$1" == --except=* ]]; do
    excluded_aliases+=("${1#*=}") # Add the alias to the exclude list
    shift
done

# Check for at least two remaining arguments (environment and drush-command)
if [ "$#" -lt 2 ]; then
    echo "Usage: ./drushall.sh [--except=alias1 --except=alias2 ...] <environment> <drush-command>"
    exit 1
fi

cd "$(dirname "${BASH_SOURCE[0]}")"
ENVIRONMENT=$1
shift
DRUSH_COMMAND="$@"

# Build the YAML file path
YML_FILE="../drush/sites/$ENVIRONMENT.site.yml"

if [ ! -f "$YML_FILE" ]; then
    echo "Error: File '$YML_FILE' not found"
    exit 1
fi

# Source your custom YAML parsing script
source lib/yaml.sh

# Create variables from the YAML file
create_variables "$YML_FILE" "site__"

# Extract site aliases from the created variables
SITE_ALIASES=()
for var in "${!site@}"; do
    if [[ "$var" == *"_uri" ]]; then
        # Extract the full alias, handling underscores correctly
        alias="${var#site__}"
        alias="${alias%_uri}"

        if is_excluded "$alias"; then
            echo "Excluding site alias '$alias'"
            continue
        fi
        SITE_ALIASES+=("$alias")
    fi
done


# Running the drush command on each site alias
for SITE_ALIAS in "${SITE_ALIASES[@]}"; do
    echo "Running '$DRUSH_COMMAND' on site '$ENVIRONMENT.$SITE_ALIAS'"
    drush @"$ENVIRONMENT"."$SITE_ALIAS" $DRUSH_COMMAND
done
