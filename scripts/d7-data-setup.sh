#!/usr/bin/env bash

# Import the D7 database into ddev.
echo "Importing D7 database from d7data/d7site.sql.gz"
ddev import-db --src=drupal_it_backup.sql --target-db=d7

echo "Extracting D7 content files from d7data/anthropology.tar.gz into /var/www/html/d7web/sites/default/files"
ddev exec tar xzf d7data/it.tar.gz --directory=/var/www/html/d7web

ddev exec ln -s /var/www/html/d7web /var/www/html/web/_d7web

echo "Done!"
