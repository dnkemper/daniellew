# /home/drupaladm/d9main/vendor/bin/drush @anthropology.stage sql-drop -y;
home/drupaladm/d9main/vendor/bin/drush @it.stage sql-drop -y;
# /home/drupaladm/d9main/vendor/bin/drush @physics.stage sql-drop -y;
# /home/drupaladm/d9main/vendor/bin/drush @sociology.stage sql-drop -y;
# /home/drupaladm/d9main/vendor/bin/drush @eeps.stage sql-drop -y;
# /home/drupaladm/d9main/vendor/bin/drush @history.stage sql-drop -y;

# /home/drupaladm/d9main/vendor/bin/drush @biology.stage sql:cli --database=migrate < d7data/drupal_biology_backup.sql
# echo "Import SQL data for the anthropology department's Drupal 7 database into the migration database on the staging environment "
# /home/drupaladm/d9main/vendor/bin/drush @anthropology.stage sql:cli --database=migrate < d7data/drupal_anthropology_backup.sql

echo "Import SQL data for the it department's Drupal 7 database into the migration database on the staging environment "
/home/drupaladm/d9main/vendor/bin/drush @it.stage sql:cli --database=migrate < d7data/drupal_it_backup.sql

echo "Import SQL data for the physics department's Drupal 7 database into the migration database on the staging environment "
/home/drupaladm/d9main/vendor/bin/drush @physics.stage sql:cli --database=migrate < d7data/drupal_physics_backup.sql

echo "Import SQL data for the sociology department's Drupal 7 database into the migration database on the staging environment "
/home/drupaladm/d9main/vendor/bin/drush @sociology.stage sql:cli --database=migrate < d7data/drupal_sociology_backup.sql

echo "Import SQL data for the earth and environmental sciences department's Drupal 7 database into the migration database on the staging environment "
/home/drupaladm/d9main/vendor/bin/drush @eeps.stage sql:cli --database=migrate < d7data/drupal_eps_backup.sql

# echo "Import SQL data for the history department's Drupal 7 database into the migration database on the staging environment "
# /home/drupaladm/d9main/vendor/bin/drush @history.stage sql:cli --database=migrate < d7data/drupal_history_backup.sql

# echo "Execute the Drupal 9 SQL commands on the biology department's staging environment "
# /home/drupaladm/d9main/vendor/bin/drush @biology.stage sql:cli < d9.sql -y

echo "Execute the Drupal 9 SQL commands on the it department's staging environment "
/home/drupaladm/d9main/vendor/bin/drush @it.stage sql:cli < d9.sql -y

# echo "Execute the Drupal 9 SQL commands on the anthropology department's staging environment "
# /home/drupaladm/d9main/vendor/bin/drush @anthropology.stage sql:cli < d9.sql -y

echo "Execute the Drupal 9 SQL commands on the physics department's staging environment "
/home/drupaladm/d9main/vendor/bin/drush @physics.stage sql:cli < d9.sql -y

echo "Execute the Drupal 9 SQL commands on the sociology department's staging environment "
/home/drupaladm/d9main/vendor/bin/drush @sociology.stage sql:cli < d9.sql -y

echo "Execute the Drupal 9 SQL commands on the earth and environmental sciences department's staging environment "
/home/drupaladm/d9main/vendor/bin/drush @eeps.stage sql:cli < d9.sql -y

echo "Execute the Drupal 9 SQL commands on the history department's staging environment "
/home/drupaladm/d9main/vendor/bin/drush @history.stage sql:cli < d9.sql -y

# echo "Run the Drupal migration script for the biology department "
# sh scripts/migration/run-migration.sh @biology.stage

echo "Run the Drupal migration script for the anthropology department "
sh scripts/migration/run-migration.sh @anthropology.stage

echo "Run the Drupal migration script for the it department "
sh scripts/migration/run-migration.sh @it.stage

echo "Run the Drupal migration script for the physics department "
sh scripts/migration/run-migration.sh @physics.stage

echo "Run the Drupal migration script for the sociology department "
sh scripts/migration/run-migration.sh @sociology.stage

echo "Run the Drupal migration script for the earth and environmental sciences department "
sh scripts/migration/run-migration.sh @eeps.stage

echo "Run the Drupal migration script for the history department "
sh scripts/migration/run-migration.sh @history.stage

echo "Set the password for the Drupal user 'asdrupal' in the biology department's staging environment "
/home/drupaladm/d9main/vendor/bin/drush @biology.stage upwd asdrupal 'EiuIy93!X*1RT!Lp';

echo "Set the password for the Drupal user 'asdrupal' in the it department's staging environment "
/home/drupaladm/d9main/vendor/bin/drush @it.stage upwd asdrupal 'EiuIy93!X*1RT!Lp';

echo "Set the password for the Drupal user 'asdrupal' in the anthropology department's staging environment "
/home/drupaladm/d9main/vendor/bin/drush @anthropology.stage upwd asdrupal 'EiuIy93!X*1RT!Lp';

echo "Set the password for the Drupal user 'asdrupal' in the physics department's staging environment "
/home/drupaladm/d9main/vendor/bin/drush @physics.stage upwd asdrupal 'EiuIy93!X*1RT!Lp';

echo "Set the password for the Drupal user 'asdrupal' in the sociology department's staging environment "
/home/drupaladm/d9main/vendor/bin/drush @sociology.stage upwd asdrupal 'EiuIy93!X*1RT!Lp';

echo "Set the password for the Drupal user 'asdrupal' in the earth and environmental sciences department's staging environment "
/home/drupaladm/d9main/vendor/bin/drush @eeps.stage upwd asdrupal 'EiuIy93!X*1RT!Lp';

echo "Set the password for the Drupal user 'asdrupal' in the history department's staging environment "
/home/drupaladm/d9main/vendor/bin/drush @history.stage upwd asdrupal 'EiuIy93!X*1RT!Lp';

