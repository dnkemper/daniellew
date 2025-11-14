# Washington University Arts and Science Drupal 10 Migration

This is the developer documentation for the Washington University Arts and Science Drupal 10 Migration.
Checkout the [wiki documentation](https://github.com/artsci-webteam/deps/wiki) for our repos

## Other docs
1. [Local Environment Overview](https://github.com/artsci-webteam/deps/wiki)
2. [Config Splits](https://github.com/artsci-webteam/deps/wiki/Configuration-Splits)
3. [Local Files](#local-files)
4. [Commands](#commands)
5. [Locked Packages](#locked-packages)
6. [Working with theme styles](#working-with-olympian9-theme-and-olympian_core-module)
7. [Server Setup](https://github.com/artsci-webteam/deps/wiki/Server-Setup)
8. [Migrations](https://github.com/artsci-webteam/deps/wiki/Migrations)
<a id="local-files"></a>

## Local files

add a drush.local.yml file (in deps/drush directory) that points to your ssh key and username used to ssh into servers
example of code in file:
```bash
ssh:
  options: '-i ~/.ssh/id_rsa'
drush_user: servername
options:
  uri: "https://default.ddev.site"
```

add a [settings.php](https://wustl.app.box.com/s/snec2l80dfwjbwi866yvz3y4d4u753tu) file to your deps/web/sites/default/ directory

add a .env file in the root directory with the following additions:
```bash
DB_HOST=db
DB_PASSWORD=db
DB_USER=db
DB_USERNAME=db
DB_PASS=db
DB_PORT=3306
ADMIN_PASS=pw
```
once you add the files, run `ddev start`. This will install composer packages and get your local ready to import a site.

<a id="commands"></a>

## Commands

Always add the ddev prefix when running common commands with drush or composer

`ddev blt amc` (run locally) This will setup a new multisite and all code changes necessary with new site installations (adds the drush aliases, site sites.php additions, blt.yml additions, remote config for entity sharing etc)

`ddev blt tests:deprecated` scans repo for deprecated code

`ddev sync` To syncs your local environment with a site on the server
 
`ddev drush ads` shows database size
 
`ddev drush ats` shows tables and their corresponding sizes
 
`ddev drush users:list` displays list of drupal users on a site
 
`ddev drush utog` blocks and unblocks users while keeping track of previous state

`ddev drush cr` WHENEVER you make changes to a twig template or scss file, you're going to need to run this command to rebuild the cache

`ddev drush cex` Will export any configuration changes you made through the UI locally

`ddev drush cst` Will show configuration differences between the filesystem and database

`ddev drush cim` Will import config files that might be different from the DB compared to your local

`ddev drush uli` will help you login to the site locally

`ddev yarn` will build the theme css files

 `ddev gulp` will gulp watch the theme css files while making changes
 > `NOTE`: You must be connected to/signed into the vpn. The command asks you to input the environment (can enter number or text - so 1, 2, 3, dev, stage, or prod) and target alias (example: olympian, aggregator, fellowshipsoffice)

`ddev blt aip` (run on server) will do a basic site installation using our artsci profile with default content. This can be used when setting up new sites

`ddev blt ame` (run on server) allows you to run a specific drush command on all sites ex: `ddev blt ame cim`

`ddev blt artsci:user` (run on server) updates asdrupal password on all sites(located in .env on server)

`ddev blt amr` (run on server) Run for basic code deployments that involve drush updb, cim and cr


<a id="locked"></a>


## LOCKED PACKAGES


| Package                          | Reason                                                                                                                                            |
| -------------------------------- | ------------------------------------------------------------------------------------------------------------------------------------------------- |
| node_revision_delete 8.x-1.0-rc3 | No stable release to compare with D9 that works.                                                                                                  |
| drupal/photoswipe 3.1.0          | Took away grouping images. I had to create the initial patch a year and a half ago and am desperately waiting for the next person to do the same. |
| admin_toolbar 3.3.0              | The updates break the toolbar styling. Leaving it here until it's fixed.                                                                          |

## Working with olympian9 theme and olympian_core module

For notes on working with styles and scripts for the olympian9 custom theme and the olympian_core custom module, see the [theme readme file](./web/themes/custom/olympian9).
