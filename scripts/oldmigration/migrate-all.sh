#!/bin/bash

# Script name: migrate-all.sh
# Description: This script runs the 'run-migration.sh' script conditionally based on the migration type defined in 'sitelist.yml'.
#              It supports '--reset' option for resetting migrations. Sites marked with 'migrate: false' are skipped.
#              Sites marked with 'migrate: shared' undergo a special migration routine, while others follow the full migration process.

# Initialize flag indicating whether the --reset option was passed
RESET_FLAG=""

# Process all options and leave the rest arguments
while [[ "$1" =~ ^- ]]; do
    case "$1" in
        --reset)
            RESET_FLAG="--reset"
            shift
            ;;
        *)
            echo "Unknown option: $1"
            exit 1
            ;;
    esac
done

# Check for at least one argument (environment)
if [ "$#" -lt 1 ]; then
    echo "Usage: ./migrate-all.sh [--reset] <environment>"
    exit 1
fi

cd "$(dirname "${BASH_SOURCE[0]}")"
ENVIRONMENT=$1

# Set REMOTE_ENVIRONMENT to the second argument if provided, otherwise use ENVIRONMENT
REMOTE_ENVIRONMENT=${2:-$ENVIRONMENT}

# Build the YAML file path
YML_FILE="../sitelist.yml"

if [ ! -f "$YML_FILE" ]; then
    echo "Error: File '$YML_FILE' not found"
    exit 1
fi

# Source the YAML parsing script
source ../lib/yaml.sh

# Create variables from the YAML file
create_variables "$YML_FILE"

# Iterate over sites based on 'migrate' status
for (( i=0; i<${#sites__name[@]}; i++ )); do
    site_name="${sites__name[$i]}"
    migrate_status="${sites__migrate[$i]}"

    echo "Processing site: $site_name with migration status: $migrate_status"

    # Skip sites with 'migrate: false'
    if [[ "$migrate_status" == "false" ]]; then
        echo "Skipping $site_name, marked as no migration."
        continue
    fi

    if [[ "$migrate_status" == "shared" ]]; then
        # Handle shared migration logic here
        echo "Running shared migration for $site_name"

        # Shared migration routine
        drush @"$ENVIRONMENT"."$site_name" cr
        drush @"$ENVIRONMENT"."$site_name" sql:query "DELETE FROM key_value WHERE collection = 'migrate_status'"
        drush @"$ENVIRONMENT"."$site_name" migrate:import --tag=shared-node-type --execute-dependencies --continue-on-failure --update
        drush @"$ENVIRONMENT"."$site_name" om-write-mappings

    else
        # Handle full migration logic here
        echo "Running full migration for $site_name with $RESET_FLAG"
        
        # Full migration routine
    
        drush @"$ENVIRONMENT"."$site_name" sql:query "DELETE FROM key_value WHERE collection = 'migrate_status'"

        ./run-migration.sh @"$ENVIRONMENT"."$site_name" $RESET_FLAG
        drush @"$ENVIRONMENT"."$site_name" om-write-mappings
    fi
done

# Set up shared content
./reset-shared-content.sh $ENVIRONMENT $REMOTE_ENVIRONMENT


# Iterate over sites again based on 'migrate' status
for (( i=0; i<${#sites__name[@]}; i++ )); do
    site_name="${sites__name[$i]}"
    migrate_status="${sites__migrate[$i]}"
    # Skip sites with 'migrate: false'
    if [[ "$migrate_status" == "false" ]]; then
        echo "Skipping $site_name, marked as no migration."
        continue
    fi

    if [[ "$migrate_status" == "full" ]]; then
        # Handle shared migration logic here
        echo "Running shared content migrations for $site_name"
        drush @"$ENVIRONMENT"."$site_name" migrate:import --tag=shared_content
        ./run-migration.sh @"$ENVIRONMENT"."$site_name" --shared-deps-only
    fi
done
