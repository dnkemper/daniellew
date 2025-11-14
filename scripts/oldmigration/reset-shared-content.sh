#!/bin/bash

# Change to the script's directory
cd "$(dirname "${BASH_SOURCE[0]}")"

ENVIRONMENT=$1

# Set REMOTE_ENVIRONMENT to the second argument if provided, otherwise use ENVIRONMENT
if [ -n "$2" ]; then
    REMOTE_ENVIRONMENT=$2
else
    REMOTE_ENVIRONMENT=$ENVIRONMENT
fi

# Delete remotes and feeds
../drushall.sh $ENVIRONMENT entity:delete remote
../drushall.sh $ENVIRONMENT entity:delete feeds_feed --bundle=shared_content_importer
../drushall.sh $ENVIRONMENT entity:delete feeds_feed --bundle=aggregated_shared_item_importer

# Generate new remote config
../generate_sharing_remotes.sh $REMOTE_ENVIRONMENT

# Import remote configs
../drushall.sh $ENVIRONMENT cim -y --source=../config/local --partial
../drushall.sh $ENVIRONMENT feeds:import-all
