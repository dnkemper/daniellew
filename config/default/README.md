# Washington University Arts and Science Drupal 9 Migration

This is the developer documentation for the Washington University Arts and Science Drupal 9 Migration.

## Overview

Lando and Docker are used for local development.
Drupal 9 is installed in the root folder of the project.
Modules and other Drupal dependencies are managed with **Composer**.
Packages and Drupal theme dependencies will be managed with **NPM**.
Frontend code for the theme is in the `/olympian` folder.
The Drupal theme directory and assets are found in the `/web/themes/custom/olympian` folder.

Clone the repository to the local computer. 'git clone git@gitlab.com:spry-digital/washington-university/wash-u-a-and-s-multisite.git'

1. Create a settings.php in `/web/sites/default` using the [following snippet](https://gitlab.com/spry-digital/washington-university/wash-u-a-and-s-multisite/-/snippets/2278623).
2. run `lando start`
3. Import a [database file](https://storage.3.basecamp.com/4122744/buckets/10934090/uploads/4768518866/download/d9main_contentsync3updated.sql?disposition=attachment) into the Drupal backend using `lando db-import`
4. To run composer to install and update dependencies use `lando composer install`
6. To login to the backend, use `lando drush uli` to create a URL to login.
7. The site will be available locally at http://washu-pir.lndo.site
