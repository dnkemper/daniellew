echo "Running database backup procedures"
#!/bin/bash
NOW=$(date +"%m-%d-%Y-%T")
echo "Backup filename: qa-sync-backup-$NOW.sql"
drush sql-dump > qa-sync-backup-$NOW.sql
echo "Dropping local database"
drush sql-drop -y
echo "Syncing database with dev"
drush sql-sync @qa @self -y
echo "Copy all files / directories from one server to other"
drush rsync @qa:%files @self:%files
echo "Composer install"
composer update --lock
composer install
echo "Running database update procedures"
drush updb --yes
echo "Importing config"
drush cim -y
echo "Clear Cache"
drush cr
echo "Welcome to your local environment"
drush uli
