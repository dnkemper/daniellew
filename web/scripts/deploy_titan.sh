# Fetch modules if missing
composer install

# Install a vanilla drush site
drush si --yes

# Update password
drush upwd admin "admin"

# THIS STEP WILL NEEDED TO BE REPORTED IF USING A NEW 'drush cex' ATTEMPT 
# Get the UUID From the system.site.yml file
# See issue: https://drupal.stackexchange.com/questions/253558/how-can-i-fix-site-uuid-does-not-match-error-during-config-import
drush config-set "system.site" uuid "0ceced7f-d4cb-41b3-bbec-56eb5e14d767"

# See issue: https://www.drupal.org/forum/support/post-installation/2015-12-20/problem-during-import-configuration
drush entity:delete shortcut_set

# Import current configurations - installs current modules
drush cim

# Need to remove this module from composer
drush pm:uninstall shortcut

# Cache Rebuild
drush cr -y

# Show current migrations
drush ms

# Content migrations
# drush migrate:import --tag='Content'
# drush cr -y