echo "Running database backup procedures"
#!/bin/bash
NOW=$(date +"%m-%d-%Y-%T")
echo "Backup filename: uat-sync-backup-$NOW.sql"
drush sql-dump > uat-sync-backup-$NOW.sql
echo "Dropping local database"
drush sql-drop -y
echo "Syncing database with uat"
drush sql-sync @uat @self -y
echo "Copy all files / directories from one server to other"
drush rsync @uat:%files @self:%files -y
echo "Running database update procedures"
drush updb --yes
echo "Composer install"
composer update --lock
composer install
echo "Importing config"
drush php-eval  'field_purge_batch(1000);'
drush cim --yes
echo "Clear Cache"
drush cr
echo "Welcome to your local environment"
drush uli
