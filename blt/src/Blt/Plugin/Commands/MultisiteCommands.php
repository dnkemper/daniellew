<?php

namespace Artsci\Blt\Plugin\Commands;

use Acquia\Blt\Robo\BltTasks;
use Acquia\Blt\Robo\Common\EnvironmentDetector;
use Acquia\Blt\Robo\Common\YamlMunge;
use Acquia\Blt\Robo\Config\DefaultConfig;
use Acquia\Blt\Robo\Exceptions\BltException;
use Artsci\InspectorTrait;
use Drupal\Component\Uuid\Php;
use Exception;
use Robo\Contract\VerbosityThresholdInterface;
use Acquia\Blt\Robo\Common\RandomString;
use Symfony\Component\Console\Input\InputOption;
use Acquia\Blt\Robo\Config\ConfigInitializer;
use Artsci\Multisite;
use Symfony\Component\Finder\Finder;

/**
 * Global multisite commands.
 */
class MultisiteCommands extends BltTasks {

  use InspectorTrait;

  /**
   * A no-op command.
   *
   * This is called in sync.commands to override the frontend step.
   *
   * Compiling frontend assets on a per-site basis is not necessary since we
   * use Yarn workspaces for that. This allows for faster syncs.
   *
   * @see: https://github.com/acquia/blt/issues/3697
   *
   * @command artsci:multisite:noop
   *
   * @aliases amn
   *
   * @hidden
   */
  public function noop() {}

  /**
   * An instance of the Php UUID generator used by the Drupal UUID service.
   *
   * @var Php
   */
  protected $uuidGenerator;

  /**
   * Generate a Universally Unique Identifier (UUID).
   *
   * @command uuid
   * @aliases uuid,devuuid
   * @usage drush devuuid
   *
   * @return string
   *   The generated uuid.
   */
  public function uuid() {
    $uuid = new Php();
    return $uuid->generate();
  }

  /**
   * Generates a new multisite.
   *
   * @option requester
   *   The ID of the original requester. Will be granted webmaster access.
   * @option split
   *   The name of a config split to activate and import after installation.
   * @option site-name
   *   The desired site name within quotes.
   *
   * @param array $options
   *   Options.
   *
   * @command artsci:multisite:create
   *
   * @aliases amc
   *
   * @throws BltException
   */
  public function generate(array $options = [
    'site-dir' => InputOption::VALUE_REQUIRED,
    'site-uri' => InputOption::VALUE_REQUIRED,
    'remote-alias' => InputOption::VALUE_REQUIRED,
    'requester' => InputOption::VALUE_REQUIRED,
    'split' => InputOption::VALUE_REQUIRED,
    'name' => InputOption::VALUE_REQUIRED,
    'email' => InputOption::VALUE_REQUIRED,
    'contact_us_url' => InputOption::VALUE_REQUIRED,
  ]
  ) {
    $this->say("This will generate a new site in the web/sites directory.");
    $site_name = $this->getNewSiteName($options);
    $site_dir = $site_name . '.wustl.edu';
    $site_dir = $this->askDefault("Site Directory",
      $site_dir);
    $new_site_dir = $this->getConfigValue('docroot') . '/sites/' . $site_dir;
    $config_split_needed = $this->confirm("Will this need a config split?");
    if ($config_split_needed) {
    // Prompt for the config split ID
    $config_split_id = $this->ask("Enter the config split ID:");
    }

    if (file_exists($new_site_dir)) {
      throw new BltException("Cannot generate new multisite, $new_site_dir already exists!");
    }

    $domain = $this->getNewSiteDomain($options, $site_name);

    $url = parse_url($domain);
    // @todo Validate uri, ensure includes scheme.
    $newDBSettings = $this->setLocalDbConfig($site_name);
    $setSettingsConfig = $this->setSiteConfig($site_name);

    $default_site_dir = $this->getConfigValue('docroot') . '/sites/default';
    $this->createDefaultBltSiteYml($default_site_dir);
    $this->createNewSiteDir($default_site_dir, $new_site_dir);

    $remote_alias = $this->getNewSiteAlias($site_name, $options, 'remote');
    $local_alias = $this->getNewSiteAlias($site_name, $options, 'local');
    $this->createNewBltSiteYml($new_site_dir, $site_name, $url, $local_alias, $remote_alias, $newDBSettings, $setSettingsConfig);
    $this->createNewSiteConfigDir($site_name);
    $this->createSiteDrushAlias($site_name, $domain);
    $this->resetMultisiteConfig();

    $this->say("New site generated at <comment>$new_site_dir</comment>");
    $this->say("Drush aliases generated:");
    if (!file_exists($default_site_dir . "/blt.yml")) {
      $this->say("  * @self");
    }
    $this->say("  * @$remote_alias");
    // Directory aliases using $site_name variable.
    $dev = $site_name . '.artscidev.wustl.edu';
    $local = $site_name . '.ddev.site';
    $stage = $site_name . '.artscistage.wustl.edu';
    $prod = $site_name . '.wustl.edu';
    $sitedir = $site_name . '.wustl.edu';

    $data = <<<EOD

// Directory aliases for {$sitedir}.
\$sites['{$local}'] = '{$sitedir}';
\$sites['{$dev}'] = '{$sitedir}';
\$sites['{$stage}'] = '{$sitedir}';
\$sites['{$prod}'] = '{$sitedir}';

EOD;

    $this->taskWriteToFile("web/sites/sites.php")
      ->text($data)
      ->append()
      ->run();
    $data = <<<EOD

$site_name:
  uri: https://$site_name.artscistage.wustl.edu
  root: /var/www/html/d9main/web
  host: artscistage.wustl.edu
  user: \${drush_user}
  paths:
    files: sites/$site_name.wustl.edu/files

EOD;

    $this->taskWriteToFile("drush/sites/stage.site.yml")
      ->text($data)
      ->append()
      ->run();
    $data = <<<EOD

$site_name:
  uri: https://$site_name.artscidev.wustl.edu
  root: /var/www/html/d9main/web
  host: artscidev.wustl.edu
  user: \${drush_user}
  paths:
    files: sites/$site_name.wustl.edu/files

EOD;

    $this->taskWriteToFile("drush/sites/dev.site.yml")
      ->text($data)
      ->append()
      ->run();
    $data = <<<EOD

$site_name:
  uri: https://$site_name.wustl.edu
  root: /var/www/html/d9main/web
  host: artsciprod.wustl.edu
  user: \${drush_user}
  paths:
    files: sites/$site_name.wustl.edu/files

EOD;

    $this->taskWriteToFile("drush/sites/prod.site.yml")
      ->text($data)
      ->append()
      ->run();
    $data = <<<EOD

$site_name:
  uri: https://$site_name.ddev.site
  root: /var/www/html/d9main/web
  user: \${drush_user}
  paths:
    files: sites/$site_name.wustl.edu/files

EOD;

    $this->taskWriteToFile("drush/sites/ddev.site.yml")
      ->text($data)
      ->append()
      ->run();
          // Path to settings.php
$settingsFile = "web/sites/{$sitedir}/settings.php";

// Read the current contents
$currentContents = file_get_contents($settingsFile);

if ($currentContents === false) {
    throw new \RuntimeException("Unable to read the settings file: {$settingsFile}");
}

// Prepare the new values
$newDbName = "drupal_{$site_name}";

// Replace the database names
$currentContents = str_replace("drupal_default", $newDbName, $currentContents);
// Write the updated contents back to the file
$result = file_put_contents($settingsFile, $currentContents);

if ($result === false) {
    throw new \RuntimeException("Unable to write the updated settings to the file: {$settingsFile}");
}

echo "Settings file updated successfully.\n";


if ($result === false) {
    throw new \RuntimeException("Unable to write the updated settings to the file: {$settingsFile}");
}


if ($config_split_needed) {
    // Prompt for the config split ID
    // $config_split_id = $this->ask("Enter the config split ID:");

    // Generate the configuration data
    $data = <<<EOD
    \$config['config_split.config_split.{$config_split_id}']['status'] = TRUE;

EOD;

    // Write the configuration data to the appropriate settings.php file
    $this->taskWriteToFile("web/sites/{$sitedir}/settings.php")
        ->text($data)
        ->append()
        ->run();
}

$opmlFile = 'web/modules/custom/shared_content/shared_content_opml.xml';

// Read the current contents of the OPML file
$currentContents = file_get_contents($opmlFile);


$sitedir = $site_name . '.wustl.edu';
// Prepare the new outline entries
$newOutlineData = <<<EOD

    <!--{$site_name}-->
    <outline text="{$site_name} Articles" xmlUrl="https://{$sitedir}/shared-content/articles" feedSource="{$sitedir}" feedContentType="Article"/>
    <outline text="{$site_name} Books" xmlUrl="https://{$sitedir}/shared-content/books" feedSource="{$sitedir}" feedContentType="Book"/>
    <outline text="{$site_name} Events" xmlUrl="https://{$sitedir}/shared-content/events" feedSource="{$sitedir}" feedContentType="Event"/>
    <outline text="{$site_name} Person" xmlUrl="https://{$sitedir}/shared-content/people" feedSource="{$sitedir}" feedContentType="Person"/>
EOD;

// Replace the placeholder <!--New--> with the new outline data
// Use preg_replace to ensure it's placed correctly before </body>
$currentContents = preg_replace("/<!--New-->/", $newOutlineData . "\n<!--New-->", $currentContents);

// Write the updated contents back to the OPML file
file_put_contents($opmlFile, $currentContents);
  }

  /**
   * Prompts for and sets config for new database.
   *
   * @param string $site_name
   *   Site name.
   *
   * @return array
   *   Empty array if user did not want to configure local db. Populated array
   *   otherwise.
   */
  protected function setLocalDbConfig($site_name) {
    // $config_local_db = $this->confirm("Would you like to configure the local database credentials?");
    $db = [];

    // if ($config_local_db) {
    $default_db = $this->getConfigValue('drupal.db');
    $db['database'] = $this->askDefault("Local database name",
      'drupal_' . $site_name);
    $this->getConfig()->set('drupal.db', $db);
    // }

    return $db;
  }

  /**
   * Prompts for and sets config for new database.
   *
   * @param string $site_name
   *   Site name.
   *
   * @return array
   *   Empty array if user did not want to configure local db. Populated array
   *   otherwise.
   */
  protected function setSiteConfig($site_name) {
    // $config_local_db = $this->confirm("Would you like to configure basic site settings?");
    $db = [];

    // if ($config_local_db) {

    $default_db = $this->getConfigValue('system.site');
    $db['contact_us_url'] = $this->askDefault("Contact us URL",
      'https://' . $site_name . '.wustl.edu/contact-us');
    $db['name'] = $this->askDefault("Site name",
      $site_name);
    $db['email'] = $this->askDefault("Site Email",
      $site_name . '@wustl.edu');
    $this->getConfig()->set('system.site', $db);

    return $db;
  }

  /**
   * Generates default settings files for Drupal and drush.
   *
   * @command source:artsci:build:settings
   *
   * @aliases blt:artsci:init:settings bis settings setup:settings
   *
   * @throws BltException
   */
  public function generateCustomSiteConfigFiles() {
    $this->generateLocalConfigFile();

    // Reload config.
    $config_initializer = new ConfigInitializer($this->getConfigValue('repo.root'), $this->input());
    $config_initializer->setSite($this->getConfig()->get('site'));
    $new_config = $config_initializer->initialize();

    // Replaces config.
    $this->getConfig()->replace($new_config->export());

    // Generate hash file in salt.txt.
    $this->hashSalt();

    $default_multisite_dir = $this->getConfigValue('docroot') . "/sites/default";
    $default_project_default_settings_file = "$default_multisite_dir/default.settings.php";

    $multisites = $this->getConfigValue('multisites');
    $initial_site = $this->getConfigValue('site');
    $current_site = $initial_site;

    $this->logger->debug("Multisites found: " . implode(',', $multisites));
    $this->logger->debug("Initial site: $initial_site");

    foreach ($multisites as $multisite) {
      if ($current_site != $multisite) {
        $this->switchSiteContext($multisite);
        $current_site = $multisite;
      }

      // Generate settings.php.
      $multisite_dir = $this->getConfigValue('docroot') . "/sites/$multisite";
      $project_default_settings_file = "$multisite_dir/default.settings.php";
      $project_settings_file = "$multisite_dir/settings.php";

      // Generate local.settings.php.
      $blt_local_settings_file = $this->getConfigValue('docroot') . '/settings/default.local.settings.php';
      $default_local_settings_file = "$multisite_dir/settings/default.local.settings.php";
      $project_local_settings_file = "$multisite_dir/settings/local.settings.php";

      // Generate default.includes.settings.php.
      $blt_includes_settings_file = $this->getConfigValue('docroot') . '/settings/default.includes.settings.php';
      $default_includes_settings_file = "$multisite_dir/settings/default.includes.settings.php";

      // Generate sites/settings/default.global.settings.php.
      $blt_glob_settings_file = $this->getConfigValue('docroot') . '/settings/default.global.settings.php';
      $default_glob_settings_file = $this->getConfigValue('docroot') . "/sites/settings/default.global.settings.php";
      $global_settings_file = $this->getConfigValue('docroot') . "/sites/settings/global.settings.php";

      // Generate local.drush.yml.
      $blt_local_drush_file = $this->getConfigValue('docroot') . '/settings/default.local.drush.yml';
      $default_local_drush_file = "$multisite_dir/default.local.drush.yml";
      $project_local_drush_file = "$multisite_dir/local.drush.yml";

      $copy_map = [
        $blt_local_settings_file => $default_local_settings_file,
        $default_local_settings_file => $project_local_settings_file,
        $blt_includes_settings_file => $default_includes_settings_file,
        $blt_local_drush_file => $default_local_drush_file,
        $default_local_drush_file => $project_local_drush_file,
      ];
      // Define an array of files that require property expansion.
      $expand_map = [
        $default_local_settings_file => $project_local_settings_file,
        $default_local_drush_file => $project_local_drush_file,
      ];

      // Add default.global.settings.php if global.settings.php does not exist.
      if (!file_exists($global_settings_file)) {
        $copy_map[$blt_glob_settings_file] = $default_glob_settings_file;
      }

      // Only add the settings file if the default exists.
      if (file_exists($default_project_default_settings_file)) {
        $copy_map[$default_project_default_settings_file] = $project_default_settings_file;
        $copy_map[$project_default_settings_file] = $project_settings_file;
      }
      elseif (!file_exists($project_settings_file)) {
        $this->logger->warning("No $default_project_default_settings_file file found.");
      }

      $task = $this->taskFilesystemStack()
        ->stopOnFail()
        ->setVerbosityThreshold(VerbosityThresholdInterface::VERBOSITY_VERBOSE)
        ->chmod($multisite_dir, 0777);

      if (file_exists($project_settings_file)) {
        $task->chmod($project_settings_file, 0777);
      }

      // Copy files without overwriting.
      foreach ($copy_map as $from => $to) {
        if (!file_exists($to)) {
          $task->copy($from, $to);
        }
      }

      $result = $task->run();

      foreach ($expand_map as $from => $to) {
        $this->getConfig()->expandFileProperties($to);
      }

      if (!$result->wasSuccessful()) {
        throw new BltException("Unable to copy files settings files from BLT into your repository.");
      }

      if (!$result->wasSuccessful()) {
        throw new BltException("Unable to modify $project_settings_file.");
      }

      $result = $this->taskFilesystemStack()
        ->chmod($project_settings_file, 0644)
        ->stopOnFail()
        ->setVerbosityThreshold(VerbosityThresholdInterface::VERBOSITY_VERBOSE)
        ->run();

      if (!$result->wasSuccessful()) {
        $this->getInspector()
          ->getFs()
          ->makePathRelative($project_settings_file, $this->getConfigValue('repo.root'));
        throw new BltException("Unable to set permissions on $project_settings_file.");
      }
    }

    if ($current_site != $initial_site) {
      $this->switchSiteContext($initial_site);
    }
  }

  /**
   * Generates local.blt.yml from example.local.blt.yml.
   *
   * @throws BltException
   */
  private function generateLocalConfigFile() {
    $localConfigFile = $this->getConfigValue('blt.config-files.local');
    $exampleLocalConfigFile = $this->getConfigValue('blt.config-files.example-local');
    $localConfigFilepath = $this->getInspector()
      ->getFs()
      ->makePathRelative($localConfigFile, $this->getConfigValue('repo.root'));
    $exampleLocalConfigFilepath = $this->getInspector()
      ->getFs()
      ->makePathRelative($exampleLocalConfigFile, $this->getConfigValue('repo.root'));

    if (file_exists($localConfigFile)) {
      // Don't overwrite an existing local.blt.yml.
      return;
    }

    if (!file_exists($exampleLocalConfigFile)) {
      $this->say("Could not find $exampleLocalConfigFilepath. Create and commit this file if you'd like to automatically generate $localConfigFilepath based on this template.");
      return;
    }

    $result = $this->taskFilesystemStack()
      ->copy($exampleLocalConfigFile, $localConfigFile)
      ->stopOnFail()
      ->setVerbosityThreshold(VerbosityThresholdInterface::VERBOSITY_VERBOSE)
      ->run();

    if (!$result->wasSuccessful()) {
      throw new BltException("Unable to create $localConfigFilepath.");
    }
  }

  /**
   * Prompts for and sets config for new database.
   *
   * @param string $site_name
   *   Site name.
   *
   * @return array
   *   Empty array if user did not want to configure server db. Populated array
   *   otherwise.
   */
  protected function setProdDbConfig($site_name) {
    $db = [];
    $default_db = $this->getConfigValue('drupal.db');
    $db['database'] = $this->askDefault("Server database name",
      'drupal_' . $site_name);
    $db['username'] = $this->askDefault("Server database user",
      'drupal_db_user');
    $db['password'] = $this->askDefault("Server database password",
      $_ENV['DB_PASS']);
    $db['host'] = $this->askDefault("Server database host",
      $_ENV['DB_HOST']);
    $db['port'] = $this->askDefault("Server database port",
      $default_db['port']);
    $db['driver'] = 'mysql';
    $db['autoload'] = 'core/modules/mysql/src/Driver/Database/mysql/';
    $this->getConfig()->set('drupal.db', $db);

    return $db;
  }

  /**
   * Create yaml file.
   *
   * @param string $default_site_dir
   *   Default site dir.
   *
   * @return string
   *   Site dir.
   */
  protected function createDefaultBltSiteYml($default_site_dir) {
    if (!file_exists($default_site_dir . "/blt.yml")) {
      $initial_perms = fileperms($default_site_dir);
      chmod($default_site_dir, 0777);
      // Move project.local.hostname from blt.yml to
      // sites/default/blt.yml.
      $default_site_yml = [];
      $default_site_yml['project']['local']['hostname'] = $this->getConfigValue('project.local.hostname');
      $default_site_yml['project']['local']['protocol'] = $this->getConfigValue('project.local.protocol');
      $default_site_yml['project']['machine_name'] = $this->getConfigValue('project.machine_name');
      $default_site_yml['drush']['aliases']['local'] = $this->getConfigValue('drush.aliases.local');
      $default_site_yml['drush']['aliases']['remote'] = $this->getConfigValue('drush.aliases.remote');
      YamlMunge::writeFile($default_site_dir . "/blt.yml",
        $default_site_yml);
      $project_yml = YamlMunge::parseFile($this->getConfigValue('blt.config-files.project'));
      unset($project_yml['project']['local']['hostname'],
        $project_yml['project']['local']['protocol'],
        $project_yml['project']['machine_name'],
        $project_yml['drush']['aliases']['local'],
        $project_yml['drush']['aliases']['remote']
      );
      YamlMunge::writeFile($this->getConfigValue('blt.config-files.project'),
        $project_yml);
      chmod($default_site_dir, $initial_perms);
    }
    return $default_site_dir;
  }

  /**
   * Create new site.yml.
   *
   * @param string $new_site_dir
   *   New site dir.
   * @param string $site_name
   *   Site name.
   * @param array $url
   *   Site url.
   * @param string $local_alias
   *   Local alias.
   * @param string $remote_alias
   *   Remote alias.
   * @param array $settings_site_name
   *   Site settings_site_name.
   * @param string $settings_site_email
   *   settings_site_email.
   * @param string $contact_us_url
   *   contact_us_url.
   * @param array $newDbSettings
   *   New db settings.
   * @param array $setSettingsConfig
   *   New site settings.
   *
   */
  protected function createNewBltSiteYml(
    $new_site_dir,
    $site_name,
    $url,
    $local_alias,
    $remote_alias,
    array $newDbSettings,
    array $setSettingsConfig
  ) {
    $site_yml_filename = $new_site_dir . '/blt.yml';
    $site_yml['project']['machine_name'] = $site_name;
    $site_yml['project']['human_name'] = $site_name . '.wustl.edu';
    $site_yml['project']['local']['protocol'] = $url['scheme'];
    $site_yml['project']['local']['hostname'] = $url['host'];
    $site_yml['drush']['aliases']['local'] = $local_alias;
    $site_yml['drush']['aliases']['remote'] = $remote_alias;
    $site_yml['drupal']['db'] = $newDbSettings;
    $site_yml['system']['site'] = $setSettingsConfig;
    $site_yml['olympian_migration_source_uri_prefix'] = 'https://' . $site_name . '.wustl.edu';
    $site_yml['artsci']['stage_file_proxy']['origin'] = 'https://' . $site_name . '.wustl.edu';
    $site_yml['artsci']['stage_file_proxy']['origin_dir'] = 'sites/' . $site_name . '.wustl.edu/files';
    YamlMunge::mergeArrayIntoFile($site_yml, $site_yml_filename);
  }

  /**
   * Get a list of feature config split values that the user can choose to
   * activate.
   *
   *
   * @return array
   */
  public function getFeatureSplitValues() {
    $root = $this->getConfigValue('repo.root');
    $finder = new Finder();
    $splitValues = [];

    // Get all the config split features.
    $splitFiles = $finder
      ->files()
      ->in("$root/config/default/")
      ->depth('< 2')
      ->name('config_split.config_split.*.yml')
      ->sortByName();

    foreach ($splitFiles as $splitFile) {
      $split = YamlMunge::parseFile($splitFile->getRealPath());
      $id = $split['id'];

      $splitValues[] = $id;
    }

    return $splitValues;
  }

  /**
   * Create uuid file.
   *
   * @param string $choice1
   *   The first choice (id).
   * @param string $choice2
   *   The second choice (split to add file to).
   * @param array $options
   *   Array of options.
   *
   * @command artsci:uuid:create
   *
   * @aliases artsi:uuid
   *
   * @throws \Exception
   */
  public function configSplitUpdate($choice1 = NULL, $choice2 = NULL, array $options = ['exclude' => []]) {
    // Prompt for the first choice (activate or disable).
    $choice1 = $this->askDefault("ID of config file",
      'paragraphs_content_2');
    // Get the available feature split values.
    $splitValues = $this->getFeatureSplitValues();

    // Check if there are any split values available.
    if (empty($splitValues)) {
      return 'No feature split values found.';
    }
    // Get the available feature split values.
    $splitValues = $this->getFeatureSplitValues();

    // Check if there are any split values available.
    if (empty($splitValues)) {
      return 'No feature split values found.';
    }

    // Prompt the user to choose from the available split values.
    $choice2 = $this->io()
      ->choice('Select the target feature split', $splitValues, $choice2);

    // Prompt for confirmation.
    if (!$this->io()
      ->confirm("You will add yml 'config/features/$choice2/migrate_plus.migration.olympian_d7_$choice1.yml'. Are you sure?", TRUE)) {
      throw new \Exception('Aborted.');
    }
    $site_yml_filename = 'config/features/' . $choice2 . '/migrate_plus.migration.olympian_d7_' . $choice1 . '.yml';
    $uuid2 = new Php();
    $uuid = $uuid2->generate();
    $data = <<<EOD
uuid: $uuid
langcode: en
status: true
migration_group: olympian_d7
dependencies: {  }
id: olympian_d7_$choice1

EOD;

    $this->taskWriteToFile("$site_yml_filename")
      ->text($data)
      ->append()
      ->run();
  }

  /**
   * Create new site dir.
   *
   * @param string $default_site_dir
   *   Default site dir.
   * @param string $new_site_dir
   *   New site dir.
   *
   * @throws BltException
   */
  protected function createNewSiteDir($default_site_dir, $new_site_dir) {
    $result = $this->taskCopyDir([
      $default_site_dir => $new_site_dir,
    ])
      ->exclude(['local.settings.php', 'files'])
      ->setVerbosityThreshold(VerbosityThresholdInterface::VERBOSITY_VERBOSE)
      ->run();
    if (!$result->wasSuccessful()) {
      throw new BltException("Unable to create $new_site_dir.");
    }
  }

  /**
   * Create new config dir.
   *
   * @param string $site_name
   *   Site name.
   *
   * @throws BltException
   */
  protected function createNewSiteConfigDir($site_name) {
    $config_dir = $this->getConfigValue('docroot') . '/' . $this->getConfigValue('cm.core.path') . '/' . $site_name;
    $result = $this->taskFilesystemStack()
      ->mkdir($config_dir)
      ->setVerbosityThreshold(VerbosityThresholdInterface::VERBOSITY_VERBOSE)
      ->run();
    if (!$result->wasSuccessful()) {
      throw new BltException("Unable to create $config_dir.");
    }
  }

  /**
   * Reset config.
   */
  protected function resetMultisiteConfig() {
    /** @var DefaultConfig $config */
    $config = $this->getConfig();
    $config->set('multisites', []);
    $config->populateHelperConfig();
  }

  /**
   * Get new domain.
   *
   * @param array $options
   *   Options.
   * @param string $site_name
   *   Site name.
   *
   * @return string
   *   Domain.
   */
  protected function getNewSiteDomain(array $options, $site_name) {
    if (empty($options['site-uri'])) {
      $domain = $this->askDefault("Local domain name",
        "https://$site_name.ddev.site");
    }
    else {
      $domain = $options['site-uri'];
    }
    return $domain;
  }

  /**
   * Get new site name.
   *
   * @param array $options
   *   Options.
   *
   * @return string
   *   Site name.
   */
  protected function getNewSiteName(array $options) {
    if (empty($options['site-dir'])) {
      $site_name = $this->askRequired("Site machine name (e.g. 'example')");
    }
    else {
      $site_name = $options['site-dir'];
    }
    return $site_name;
  }

  /**
   * Get new domain.
   *
   * @param array $options
   *   Options.
   * @param string $site_name
   *   Settings site name.
   *
   * @return string
   *   Domain.
   */
  protected function getNewSettingsSiteName(array $options, $site_name) {
    if (empty($options['site-name'])) {
      $site_name = $options['site-dir'];
      $public_site_name = $this->askDefault("Public settings site name",
        $site_name);
    }
    else {
      $public_site_name = $options['site-name'];
    }
    return $public_site_name;
  }

  /**
   * Get new settings site email.
   *
   * @param string $site_name
   *   Site name.
   * @param array $options
   *   Options.
   *
   * @return string
   *   Settings site email.
   */
  protected function getNewSettingsEmail(array $options) {
    if (empty($options['email'])) {
      // If the email option is empty, prompt the user for input.
      $public_site_mail = $this->askRequired("Site email (e.g. 'artsci-webteam@wustl.edu')");
      // If the user didn't provide an email address, set the default.
      if (empty($public_site_mail)) {
        $public_site_mail = 'artsci-webteam@wustl.edu';
      }
    }
    else {
      $public_site_mail = $options['email'];
    }
    return $public_site_mail;
  }

  /**
   * Get contact us url.
   *
   * @param array $options
   *   Options.
   *
   * @return string
   *   Settings contact us url.
   */
  protected function getContactUsUrl(array $options) {
    if (empty($options['contact_us_url'])) {
      // If the email option is empty, prompt the user for input.
      $contact_us_url = $this->askRequired("Contact us link (e.g. 'https://artsci.wustl.edu/contact-us')");
      // If the user didn't provide an email address, set the default.
      if (empty($contact_us_url)) {
        $contact_us_url = 'https://artsci.wustl.edu/contact-us';
      }
    }
    else {
      $contact_us_url = $options['contact_us_url'];
    }
    return $contact_us_url;
  }

  /**
   * Get alias.
   *
   * @param string $site_name
   *   Site name.
   * @param array $options
   *   Options.
   * @param string $dest
   *   Destination.
   *
   * @return string
   *   Alias.
   */
  protected function getNewSiteAlias($site_name, array $options, $dest) {
    $option = $dest . '-alias';
    if (!empty($options[$option])) {
      return $options[$option];
    }

    $default = $site_name . '.' . $dest;
    return $this->askDefault("Default $dest drush alias", $default);
  }

  /**
   * Create alias.
   *
   * @param string $site_name
   *   Site name.
   * @param string $site_url
   *   Site URL (optional). Defaults to $site_name.
   */
  protected function createSiteDrushAlias($site_name, $site_url = '') {
    $aliases = [
      'local' => [
        'root' => '/var/www/html/web',
        'uri' => 'https://' . $site_name . '.ddev,site',
        'paths' => [
          'drush-script' => '/var/www/html/vendor/bin/drush',
          'files' => 'sites/' . $site_name . '.wustl.edu/files',
        ],
      ],
      'dev' => [
        'uri' => 'https://' . $site_name . '.artscidev.wustl.edu',
        'root' => '/var/www/html/d9main/web',
        'paths' => [
          'drush-script' => '/var/www/html/d9main/vendor/bin/drush',
          'files' => 'sites/' . $site_name . '.wustl.edu/files',
        ],
      ],
      'stage' => [
        'uri' => 'https://' . $site_name . '.artscistage.wustl.edu',
        'root' => '/var/www/html/d9main/web',
        'paths' => [
          'drush-script' => '/var/www/html/d9main/vendor/bin/drush',
          'files' => 'sites/' . $site_name . '.wustl.edu/files',
        ],
      ],
      'prod' => [
        'uri' => 'https://' . $site_name . '.wustl.edu',
        'root' => '/var/www/html/d9main/web',
        'paths' => [
          'drush-script' => '/var/www/html/d9main/vendor/bin/drush',
          'files' => 'sites/' . $site_name . '.wustl.edu/files',
        ],
      ],
    ];
    if ($site_url) {
      $aliases['local']['uri'] = $site_url;
    }

    $filename = $this->getConfigValue('drush.alias-dir') . "/$site_name.site.yml";
    YamlMunge::mergeArrayIntoFile($aliases, $filename);
  }

  /**
   * Update user password and role.
   *
   * @param array $options
   *   Array of options.
   *
   * @option exclude
   *   Sites to exclude from command execution.
   * @option sites
   *   Comma-separated list of sites to execute on.
   * @command artsci:multisite:user
   *
   * @aliases artsci:user
   *
   * @throws Exception
   */
  public function updateUser(array $options = [
    'username' => InputOption::VALUE_REQUIRED,
    'role' => InputOption::VALUE_REQUIRED,
    'password' => InputOption::VALUE_REQUIRED,
    'exclude' => [],
    'sites' => '',
  ]) {
    // Get the username, password, and role from the user (with defaults).
    $username = $this->askDefault("username", 'asdrupal');
    $password = $this->askDefault("password", $_ENV['SECRET']);
    $role = $this->askDefault("role", 'administrator');

    // Confirm action with the user
    if (!$this->confirm("You will execute 'drush upwd $username $password $role' on the selected multisites. Are you sure?", TRUE)) {
      throw new Exception('Aborted.');
    }

    // Get all sites from the configuration
    $allSites = $this->getConfigValue('multisites');

    // Parse the --sites option into an array, or use all sites if not provided.
    if (!empty($options['sites'])) {
      $requestedSites = array_map('trim', explode(',', $options['sites']));
      $sitesToRun = array_intersect($allSites, $requestedSites);
    } else {
      $sitesToRun = $allSites;
    }

    // Iterate over the selected sites and perform the update operation
    foreach ($sitesToRun as $multisite) {
      // Skip excluded sites
      if (in_array($multisite, $options['exclude'])) {
        $this->say("Skipping excluded site {$multisite}.");
        continue;
      }

      $this->switchSiteContext($multisite);

      // Check if the user exists
      $userInformation = $this->taskDrush()
        ->drush('user-information')
        ->args([$username])
        ->option('format', 'json')
        ->printOutput(FALSE)
        ->run();

      $userExists = $userInformation->wasSuccessful();

      if (!$userExists) {
        // Create a new user if they don't exist
        $this->taskDrush()
          ->drush('user-create')
          ->args([$username])
          ->run();
      }

      // Update user role and password
      $result = $this->taskDrush()
        ->drush('upwd')
        ->args([$username, $password])
        ->drush('user:role:add')
        ->args([$role, $username])
        ->run();

      if (!$result->wasSuccessful()) {
        $this->say("*Error* updating user '$username' on site '$multisite'");
        // Log error message
        $this->logger->error("Error updating user '$username' on site '$multisite'");
      }
    }
  }

  /**
   * Generates a new multisite.
   *
   * @option requester
   *   The ID of the original requester. Will be granted webmaster access.
   * @option split
   *   The name of a config split to activate and import after installation.
   * @option site-name
   *   The desired site name within quotes.
   *
   * @param array $options
   *   Options.
   *
   * @command artsci:setup:server
   *
   * @aliases ass
   *
   * @throws BltException
   */
  public function generateServerSite(array $options = [
    'site-dir' => InputOption::VALUE_REQUIRED,
    'site-uri' => InputOption::VALUE_REQUIRED,
    'remote-alias' => InputOption::VALUE_REQUIRED,
    'requester' => InputOption::VALUE_REQUIRED,
    'split' => InputOption::VALUE_REQUIRED,
    'name' => InputOption::VALUE_REQUIRED,
    'email' => InputOption::VALUE_REQUIRED,
    'contact_us_url' => InputOption::VALUE_REQUIRED,
  ]
  ) {
    $this->say("This will generate a new site in the web/sites directory.");
    $site_name = $this->getNewSiteName($options);
    $site_dir = $site_name . '.wustl.edu';
    $site_dir = $this->askDefault("Site Directory",
      $site_dir);
    $new_site_dir = $this->getConfigValue('docroot') . '/sites/' . $site_dir;

    if (file_exists($new_site_dir)) {
      throw new BltException("Cannot generate new multisite, $new_site_dir already exists!");
    }

    $domain = $this->getNewSiteDomain($options, $site_name);

    $url = parse_url($domain);
    // @todo Validate uri, ensure includes scheme.
    $newDBSettings = $this->setProdDbConfig($site_name);
    $setSettingsConfig = $this->setSiteConfig($site_name);

    $default_site_dir = $this->getConfigValue('docroot') . '/sites/default';
    $this->createDefaultBltSiteYml($default_site_dir);
    $this->createSiteDrushAlias('default');
    $this->createNewSiteDir($default_site_dir, $new_site_dir);

    $remote_alias = $this->getNewSiteAlias($site_name, $options, 'remote');
    $local_alias = $this->getNewSiteAlias($site_name, $options, 'local');
    $this->createNewBltSiteYml($new_site_dir, $site_name, $url, $local_alias, $remote_alias, $newDBSettings, $setSettingsConfig);
    $this->createNewSiteConfigDir($site_name);
    $this->resetMultisiteConfig();

    $this->say("New site generated at <comment>$new_site_dir</comment>");
    $this->say("Drush aliases generated:");
    if (!file_exists($default_site_dir . "/blt.yml")) {
      $this->say("  * @default.local");
    }
    $this->say("  * @$remote_alias");
  }

  /**
   * Runs drush state:set command.
   *
   * @option requester
   *   The ID of the original requester. Will be granted webmaster access.
   * @option split
   *   The name of a config split to activate and import after installation.
   * @option site-name
   *   The desired site name within quotes.
   * @option sites
   *   A comma-separated list of site machine names to run the command on.
   *
   * @param array $options
   *   Options.
   *
   * @command artsci:state:set
   *
   * @aliases asis
   *
   * @throws BltException
   */
  public function stateSet(array $options = [
    'command' => InputOption::VALUE_REQUIRED,
    'config_name' => InputOption::VALUE_REQUIRED,
    'key' => InputOption::VALUE_REQUIRED,
    'value' => InputOption::VALUE_REQUIRED,
    'exclude' => [],
    'sites' => '',
  ]
  ) {
    $root = $this->getConfigValue('repo.root');
    $allSites = Multisite::getAllSites($root);
    $specifiedSites = $options['sites'] ? array_map('trim', explode(',', $options['sites'])) : $allSites;

    $options['command'] = $this->askDefault("Command", 'state:set');
    $options['config_name'] = $this->askDefault("Config Name", 'twig_debug');
    $options['key'] = $this->askDefault("Value", "FALSE");

    foreach ($specifiedSites as $site) {
      if (in_array($site, $options['exclude'])) {
        $this->say("Skipping excluded site {$site}.");
        continue;
      }

      if (!in_array($site, $allSites)) {
        $this->say("Site {$site} is not recognized in the multisite configuration.");
        continue;
      }

      $this->switchSiteContext($site);
      $this->say("<info>Executing on {$site}...</info>");

      $result = $this->taskDrush()
        ->stopOnFail(FALSE)
        ->drush($options['command'])
        ->args([
          $options['config_name'],
          $options['key'],
        ])
        ->run();

      if (!$result->wasSuccessful()) {
        $this->say("*Error* running for site {$site}.");
      }
    }
  }

  /**
   * Runs drush config command.
   *
   * @option requester
   *   The ID of the original requester. Will be granted webmaster access.
   * @option split
   *   The name of a config split to activate and import after installation.
   * @option site-name
   *   The desired site name within quotes.
   * @option sites
   *   Comma-separated list of sites to execute on.
   *
   * @param array $options
   *   Options.
   *
   * @command artsci:config:set
   *
   * @aliases acs
   *
   * @throws BltException
   */
  public function configSet(array $options = [
    'command' => InputOption::VALUE_REQUIRED,
    'config_name' => InputOption::VALUE_REQUIRED,
    'key' => InputOption::VALUE_REQUIRED,
    'value' => InputOption::VALUE_REQUIRED,
    'exclude' => [],
    'sites' => '',
  ]
  ) {
    $root = $this->getConfigValue('repo.root');
    $sites = Multisite::getAllSites($root);
    $options['command'] = $this->askDefault("Config Command",
      'cset');
    $options['config_name'] = $this->askDefault("Config Name",
      'washuas_wucrsl.settings');
    $options['key'] = $this->askDefault("name",
      "wucrsl_dev_soap_client_pw");
    $options['value'] = $this->askDefault("Value",
      $_ENV['COURSES_PW']);

      $sitesToRun = [];
      $allSites = $this->getConfigValue('multisites');
  
      // Parse sites option into an array, or use all sites if not provided.
    // Parse sites option into an array, or use all sites if not provided.
    if (!empty($options['sites'])) {
      $requestedSites = array_map('trim', explode(',', $options['sites']));
      $sitesToRun = array_intersect($allSites, $requestedSites);
    }
    else {
      $sitesToRun = $allSites;
    }
  
      foreach ($sitesToRun as $multisite) {
        // if (in_array($multisite, $options['exclude'])) {
        //   $this->say("Skipping excluded site {$multisite}.");
        //   continue;
        // }
  
        $this->switchSiteContext($multisite);
        $db = $this->getConfigValue('drupal.db.database');
  
        $this->say("<info>Executing on {$multisite}...</info>");

        $result = $this->taskDrush()
          ->stopOnFail(FALSE)
          ->drush($options['command'])
          ->args([
            $options['config_name'],
            $options['key'],
            $options['value'],
          ])
          ->run();

        if (!$result->wasSuccessful()) {
          $this->say("*Error* running for site $multisite.");
        }
      }
    }

  /**
   * Installs and imports artsci profile with default content.
   *
   * @option requester
   *   The ID of the original requester. Will be granted webmaster access.
   * @option split
   *   The name of a config split to activate and import after installation.
   * @option site-name
   *   The desired site name within quotes.
   * @option site-dir
   *   The directory name for the site.
   * @option email
   *   The site email address.
   * @option contact_us_url
   *   The contact us URL for the site.
   * @option admin_password
   *   The admin password.
   *
   * @param array $options
   *   Options.
   *
   * @command artsci:install:profile
   *
   * @aliases aip
   *
   * @throws BltException
   */
  public function installArtsci(array $options = [
    'site-dir' => InputOption::VALUE_REQUIRED,
    'email' => InputOption::VALUE_REQUIRED,
    'name' => InputOption::VALUE_REQUIRED,
    'contact_us_url' => InputOption::VALUE_REQUIRED,
    'admin_password' => InputOption::VALUE_REQUIRED,
    'usernames' => InputOption::VALUE_OPTIONAL,
    'communications' => InputOption::VALUE_OPTIONAL,
  ]
  ) {
    $this->say("This will generate a new site in the web/sites directory.");
    $root = $this->getConfigValue('repo.root');
    $sites = Multisite::getAllSites($root);
    $dir = $this->askChoice('Select which site to delete.', $sites);
    $this->say("<info>Executing on dir {$dir}...</info>");

    // Load the database name from configuration since that can change from the
    // initial database name but has to match what is in the settings.php file.
    // @see: FileSystemTests.php.
    $this->switchSiteContext($dir);

    $db = $this->getConfigValue('drupal.db.database');
    $id = $this->getConfigValue('project.machine_name');

    $options['contact_us_url'] = $this->askDefault("Contact us URL",
      'https://' . $dir . '/contact-us');
    $options['name'] = $this->askDefault("Site name",
      $id);
    $options['email'] = $this->askDefault("Site Email",
      $id . '@wustl.edu');
    $options['admin_password'] = $this->askDefault("Admin Password", $_ENV['SECRET'] ?? 'pw');
    $this->invokeCommand('drupal:install', [
      '--site' => $dir,
      '--host',
    ]);
    $this->taskDrush()
      ->stopOnFail(FALSE)
      ->drush('cim')
      ->run();
    $this->taskDrush()
      ->stopOnFail(FALSE)
      ->drush('cim')
      ->run();
    $this->taskDrush()
      ->stopOnFail(FALSE)
      ->drush('pm:enable')
      ->arg('artsci_content')
      ->run();

    $this->taskDrush()
      ->stopOnFail(FALSE)
      ->drush('config:set')
      ->args([
        'system.site',
        'name',
        $options['name'],
      ])
      ->run();
    $this->taskDrush()
      ->stopOnFail(FALSE)
      ->drush('config:set')
      ->args([
        'system.site',
        'mail',
        $options['email'],
      ])
      ->run();
    $this->taskDrush()
      ->stopOnFail(FALSE)
      ->drush('config:set')
      ->args([
        'system.site',
        'contact_us_url',
        $options['contact_us_url'],
      ])
      ->run();

    $this->taskDrush()
      ->drush('upwd')
      ->args(['asdrupal', $options['admin_password']])
      ->run();
    $usernames = explode(',', $_ENV['WEBTEAM'] ?: '');
    $communications = explode(',', $_ENV['COMMUNICATIONS'] ?: '');

    if (empty($usernames) && empty($communications)) {
      throw new BltException("No usernames or communications provided. Set the WEBTEAM and COMMUNICATIONS environment variables.");
    }

    // Append @wustl.edu to the emails only, not the usernames
    $userEmails = array_map(fn($username) => $username . '@wustl.edu', $usernames);
    $communicationEmails = array_map(fn($comms) => $comms . '@wustl.edu', $communications);

    // Process usernames
    foreach ($usernames as $index => $username) {
      $this->createUserWithRole($username, $userEmails[$index], 'administrator', $dir);
    }

    // Process communications roles
    foreach ($communications as $index => $comms) {
      $this->createUserWithRole($comms, $communicationEmails[$index], 'communications', $dir);
    }

    $this->taskDrush()
      ->stopOnFail(FALSE)
      ->drush('uli')
      ->run();
  }

/**
 * Update default webteam and communication users.
 *
 * @param array $options
 *   Array of options.
 *
 * @option exclude
 *   Comma-separated list of sites to exclude.
 * @option sites
 *   Comma-separated list of sites to execute on.
 *
 * @command artsci:add-admins
 *
 * @aliases aaa
 *
 * @throws \Acquia\Blt\Robo\Exceptions\BltException
 */
public function addAdmins(array $options = [
  'exclude' => '',
  'sites' => '',
]) {
  $allSites = $this->getConfigValue('multisites');
  $sitesToRun = [];

  // Convert sites and exclude into arrays
  $requestedSites = array_filter(array_map('trim', explode(',', $options['sites'])));
  $excludedSites = array_filter(array_map('trim', explode(',', $options['exclude'])));

  // Filter sites: use --sites or default to all
  $sitesToRun = !empty($requestedSites) ? array_intersect($allSites, $requestedSites) : $allSites;

  foreach ($sitesToRun as $multisite) {
    if (in_array($multisite, $excludedSites)) {
      $this->say("Skipping excluded site {$multisite}.");
      continue;
    }

    $this->say("Adding admin users to site: <info>{$multisite}</info>");
    $this->switchSiteContext($multisite);

    $usernames = explode(',', $_ENV['WEBTEAM'] ?? '');
    $communications = explode(',', $_ENV['COMMUNICATIONS'] ?? '');

    if (empty(array_filter($usernames)) && empty(array_filter($communications))) {
      throw new BltException("No usernames or communications provided. Set the WEBTEAM and COMMUNICATIONS environment variables.");
    }

    // Build email arrays
    $userEmails = array_map(fn($u) => "{$u}@wustl.edu", $usernames);
    $commEmails = array_map(fn($c) => "{$c}@wustl.edu", $communications);

    // Create administrator users
    foreach ($usernames as $i => $username) {
      if (!empty($username)) {
        $this->createUserWithRole($username, $userEmails[$i], 'administrator', $multisite);
      }
    }

    // Create communications users
    foreach ($communications as $i => $comms) {
      if (!empty($comms)) {
        $this->createUserWithRole($comms, $commEmails[$i], 'communications', $multisite);
      }
    }
  }
}


  /**
   * Create a user and assign a role.
   *
   * @param string $username
   *   The username to create or check.
   * @param string $email
   *   The email address associated with the username.
   * @param string $role
   *   The role to assign to the user.
   * @param string $multisite
   *   The multisite context.
   */
  protected function createUserWithRole($username, $email, $role, $multisite) {
    // Check if the user exists
    $userInformation = $this->taskDrush()
      ->drush('user-information')
      ->args([$username])
      ->option('format', 'json')
      ->printOutput(FALSE)
      ->run();

    if (!$userInformation->wasSuccessful()) {
      // Create a new user with username and email
      $this->taskDrush()
        ->drush('user-create')
        ->args([$username])
        ->option('mail', $email)
        ->run();

      $this->say("<info>User '{$username}' created with email '{$email}'.</info>");
    }
    else {
      $this->say("<info>User '{$username}' already exists.</info>");
    }

    // Add the specified role
    $this->taskDrush()
      ->drush('user:role:add')
      ->args([$role, $username])
      ->run();

    $this->say("<info>Role '{$role}' assigned to '{$username}' on site '{$multisite}'.</info>");
  }

  /**
   * Writes a hash salt to ${repo.root}/salt.txt if one does not exist.
   *
   * @command drupal:hash-salt:init
   * @aliases dhsi setup:hash-salt
   *
   * @return int
   *   A CLI exit code.
   *
   * @throws BltException
   */
  public function hashSalt() {
    $hash_salt_file = $this->getConfigValue('repo.root') . '/salt.txt';
    if (!file_exists($hash_salt_file)) {
      $this->say("Generating hash salt...");
      $result = $this->taskWriteToFile($hash_salt_file)
        ->line(RandomString::string(55))
        ->run();

      if (!$result->wasSuccessful()) {
        $filepath = $this->getInspector()
          ->getFs()
          ->makePathRelative($hash_salt_file, $this->getConfigValue('repo.root'));
        throw new BltException("Unable to write hash salt to $filepath.");
      }

      return $result->getExitCode();
    }
    else {
      $this->say("Hash salt already exists.");
      return 0;
    }
  }

/**
 * Run for basic code deployments that involve drush updb, cim, and cr.
 *
 * @param array $options
 *   Array of options.
 *
 * @option exclude
 *   Sites to exclude from command execution.
 * @option sites
 *   Comma-separated list of sites to execute on.
 *
 * @command artsci:multisite:release
 *
 * @aliases amr
 *
 * @throws Exception
 */
public function release(array $options = ['exclude' => [], 'sites' => '']) {
  if (!$this->confirm("You will execute 'drush cim, updb, and cr' on selected multisites. Are you sure?", TRUE)) {
    throw new Exception('Aborted.');
  }
  else {
    $sitesToRun = [];
    $allSites = $this->getConfigValue('multisites');

    // Parse sites option into an array, or use all sites if not provided.
    if (!empty($options['sites'])) {
      $requestedSites = array_map('trim', explode(',', $options['sites']));
      $sitesToRun = array_intersect($allSites, $requestedSites);
    }
    else {
      $sitesToRun = $allSites;
    }

    foreach ($sitesToRun as $multisite) {
      if (in_array($multisite, $options['exclude'])) {
        $this->say("Skipping excluded site {$multisite}.");
        continue;
      }

      $this->switchSiteContext($multisite);
      $db = $this->getConfigValue('drupal.db.database');

      $this->say("<info>Executing on {$multisite}...</info>");

      $result = $this->taskDrush()
        ->drush('updb')
        ->drush('config:import')
        ->drush('cache:rebuild')
        ->run();

      if (!$result->wasSuccessful()) {
        $this->say("*Error* running command `drush updb, cim, and cr` for site $multisite.");
      }
    }
  }
}

  /**
   * Invoke the BLT install process on multisites where Drupal is not installed.
   *
   * @param array $options
   *   Command options.
   *
   * @option envs
   *   Array of allowed environments for installation to happen on.
   * @option dry-run
   *   Report back the uninstalled sites but do not install.
   *
   * @command artsci:multisite:install
   *
   * @aliases umi
   *
   * @return mixed
   *   CommandError, list of uninstalled sites or the output from installation.
   *
   * @throws Exception
   *
   * @see: Acquia\Blt\Robo\Commands\Drupal\InstallCommand
   */
  public function install(array $options = [
    'envs' => [
      'local',
      'prod',
    ],
    'dry-run' => FALSE,
  ]
  ) {
    $app = EnvironmentDetector::getAhGroup() ?: 'local';
    $env = EnvironmentDetector::getAhEnv() ?: 'local';

    // If (!in_array($env, $options['envs'])) {
    //   $allowed = implode(', ', $options['envs']);
    //   return new CommandError("Multisite installation not allowed on {$env} environment. Must be one of {$allowed}. Use option to override.");
    // }.
    $multisites = $this->getConfigValue('multisites');

    $this->say('Finding uninstalled sites...');
    $progress = $this->io()->createProgressBar();
    $progress->setMaxSteps(count($multisites));
    $progress->start();

    $uninstalled = [];

    foreach ($multisites as $multisite) {
      $progress->advance();
      $this->switchSiteContext($multisite);
      $db = $this->getConfigValue('drupal.db.database');

      // Skip sites whose database do not exist on the application in AH env.
      // if (EnvironmentDetector::isAhEnv() && !file_exists("/var/www/site-php/{$app}/{$db}-settings.inc")) {
      //   $this->logger->info("Skipping {$multisite}. Database {$db} does not exist.");
      //   continue;
      // }.
      if (!$this->isDrupalInstalled($multisite)) {
        $uninstalled[] = $multisite;
      }
    }

    $progress->finish();

    if (!empty($uninstalled)) {
      $this->io()->listing($uninstalled);

      if (!$options['dry-run']) {
        if ($this->confirm('You will invoke the drupal:install command for the sites listed above. Are you sure?')) {
          $uninstalled_list = implode(', ', $uninstalled);
          $this->say("Command `artsci:multisite:install` *started* for {$uninstalled_list} on {$app} {$env}.");

          foreach ($uninstalled as $multisite) {
            $this->switchSiteContext($multisite);

            // Clear the cache first to prevent random errors on install.
            // We use exec here to always return 0 since the command can fail
            // and cause confusion with the error message output.
            $this->taskExecStack()
              ->interactive(FALSE)
              ->silent(TRUE)
              ->exec("./vendor/bin/drush -l {$multisite} cache:rebuild || true")
              ->run();

            // Run this non-interactively so prompts are bypassed. Note that
            // a file permission exception is thrown on AC so we have to
            // catch that and proceed with the command.
            // @see: https://github.com/acquia/blt/issues/4054
            $this->input()->setInteractive(FALSE);

            try {
              $this->invokeCommand('drupal:install', [
                '--site' => $multisite,
                '--host',
              ]);
            }
            catch (BltException $e) {
              $this->say('<comment>Note:</comment> file permission error on Acquia Cloud can be safely ignored.');
            }
          }

          $this->say("Command `artsci:multisite:install` *finished* for {$uninstalled_list} on {$app} {$env}.");
        }
        else {
          throw new Exception('Canceled.');
        }
      }
    }
    else {
      $this->say('There are no uninstalled sites.');
    }
  }

  /**
   * Execute a Drush command against specified multisites.
   *
   * @param string $cmd
   *   The simple Drush command to execute, e.g. 'cron' or 'cache:rebuild'. No
   *   support for options or arguments at this time.
   * @param array $options
   *   Array of options.
   *
   * @option exclude
   *   Sites to exclude from command execution.
   * @option sites
   *   Comma-separated list of sites to execute on.
   *
   * @command artsci:multisite:execute
   *
   * @aliases ame, ume
   *
   * @throws Exception
   */
  public function execute($cmd, array $options = ['exclude' => [], 'sites' => '']) {
    if (!$this->confirm("You will execute 'drush {$cmd}' on selected multisites. Are you sure?", TRUE)) {
      throw new Exception('Aborted.');
    }
    else {
      $sitesToRun = [];
      $allSites = $this->getConfigValue('multisites');

      // Parse sites option into an array, or use all sites if not provided.
      if (!empty($options['sites'])) {
        $requestedSites = array_map('trim', explode(',', $options['sites']));
        $sitesToRun = array_intersect($allSites, $requestedSites);
      }
      else {
        $sitesToRun = $allSites;
      }

      foreach ($sitesToRun as $multisite) {
        if (in_array($multisite, $options['exclude'])) {
          $this->say("Skipping excluded site {$multisite}.");
          continue;
        }

        $this->switchSiteContext($multisite);
        $db = $this->getConfigValue('drupal.db.database');

        $this->say("<info>Executing on {$multisite}...</info>");

        $result = $this->taskDrush()
          ->drush($cmd)
          ->printMetadata(FALSE)
          ->run();

        if (!$result->wasSuccessful()) {
          $this->say("*Error* running command `drush {$cmd}` for site $multisite.");
        }
      }
    }
  }


  /**
   * Execute a Drush command with an argument against all multisites. Ex: blt
   * ac cron:run backup_migrate_cron.
   *
   * @param string $cmd
   *   The drush command you want to run ie: cron:run.
   * @param string $arg
   *   The argument to run with drush command ie backup_migrate_cron.
   * @param array $options
   *   Array of options.
   *
   * @option exclude
   *   Sites to exclude from command execution.
   *
   * @command artsci:multisite:command
   * @aliases ac
   *
   * @throws Exception
   */
  public function executeCommand($cmd, $arg, array $options = ['exclude' => []]) {
    if (!$this->confirm("You will execute 'drush {$cmd}' on all multisites. Are you sure?", TRUE)) {
      throw new Exception('Aborted.');
    }
    else {
      // $app = EnvironmentDetector::getAhGroup() ?: 'local';
      // $env = EnvironmentDetector::getAhEnv() ?: 'local';
      foreach ($this->getConfigValue('multisites') as $multisite) {
        $this->switchSiteContext($multisite);
        $db = $this->getConfigValue('drupal.db.database');

        // Skip sites whose database do not exist on the application in AH env.
        // if (EnvironmentDetector::isAhEnv() && !file_exists("/var/www/site-php/{$app}/{$db}-settings.inc")) {
        // $this->logger->info("Skipping {$multisite}. Database {$db} does not exist on this application.");
        // continue;
        // }.
        if (!in_array($multisite, $options['exclude'])) {
          $this->say("<info>Executing on {$multisite}...</info>");

          $result = $this->taskDrush()
            ->drush($cmd)
            ->args([
              $arg,
            ])
            ->run();

          if (!$result->wasSuccessful()) {
            $this->say("*Error* running command `drush {$cmd}` on for site $multisite.");
          }
        }
        else {
          $this->say("Skipping excluded site {$multisite}.");
        }
      }
    }
  }

/**
 * Imports a Drupal 7 database dump into the migrate database for the selected site.
 *
 * @command artsci:import:d7
 * @aliases aid7
 * @throws \Drupal\Core\BltException
 */
public function importD7Database() {
  $this->say("Importing D7 database into the migration database.");

  $root = $this->getConfigValue('repo.root');
  $sites = Multisite::getAllSites($root);
  $site_dir = $this->askChoice('Select which site to import the database for:', $sites);

  if (strpos($site_dir, '.artsci.wustl.edu') !== false) {
    $alias = str_replace('.artsci.wustl.edu', '', $site_dir);
  } else {
    $alias = str_replace('.wustl.edu', '', $site_dir);
  }

  $env = $_ENV['APP_ENV'] ?: 'stage';
  $password = $this->askDefault("password", $_ENV['SECRET']);

  // Handle site files: migrate or unzip
  $this->say("\nNote: Select migrate or unzip if artscistage, select unzip if artsciprod. \nSelect skip if you already unzipped manually.");
  $handleFiles = $this->askChoice(
    'How would you like to handle the site files? [unzip files archive] ',
    ['migrate media files', 'unzip files archive', 'skip'],
    'unzip files archive'
  );

  // Optionally import the D10 database
  if ($this->confirm("Do you need to import the D10 database?", TRUE)) {
    $this->taskDrush()
      ->stopOnFail(FALSE)
      ->drush('php:eval')
      ->arg("\Drupal::keyValue('development_settings')->setMultiple(['disable_rendered_output_cache_bins' => FALSE, 'twig_debug' => FALSE, 'twig_cache_disable' => FALSE]);")
      ->alias("{$alias}.{$env}")
      ->run();

    $this->say("Importing D10 database into the default database.");
    $d10backup_path = "/home/drupaladm/d9main/d7data/d10/drupal_{$alias}_d10_backup.sql";

    if (!file_exists($d10backup_path)) {
      throw new BltException("D10 Backup file not found at {$d10backup_path}");
    }

    $this->say("Cleaning up the D10 SQL dump file...");
    $sed_command = "sed -i '/\\/\\*M!999999/d' {$d10backup_path}";
    $this->taskExec($sed_command)->run();

    $this->say("Dropping existing D10 database...");
    $this->taskDrush()
      ->drush('sql-drop')
      ->option('database', 'default')
      ->alias("{$alias}.{$env}")
      ->run();

    $import_d10_command = "vendor/bin/drush @{$alias}.{$env} sql-query --database=default --file={$d10backup_path}";
    $this->taskExec($import_d10_command)->run();
  }

  // Drop and import D7 migrate database
  if ($handleFiles === 'migrate media files') {
    $backup_path = "/home/drupaladm/d9main/d7data/backups/drupal_{$alias}_backup.sql";

    if (!file_exists($backup_path)) {
      throw new BltException("Cannot migrate media files: D7 database backup file not found at {$backup_path}.");
    }

    $this->say("Dropping migrate database...");
    $this->taskDrush()
      ->drush('sql-drop')
      ->option('database', 'migrate')
      ->alias("{$alias}.{$env}")
      ->run();

    $this->say("Importing D7 database into the migrate database...");
    $drush_command = "vendor/bin/drush @{$alias}.{$env} sql-query --database=migrate --file={$backup_path}";
    $this->taskExec($drush_command)->run();
  }

  $this->taskDrush()
    ->stopOnFail(FALSE)
    ->drush('cr')
    ->alias("{$alias}.{$env}")
    ->run();

  $files_path = "web/sites/{$site_dir}/files";

  if ($handleFiles === 'migrate media files') {
    $this->say("Running media_image_migration...");
    $media_migration_command = "vendor/bin/drush @{$alias}.{$env} mim media_image_migration --update --execute-dependencies --continue-on-failure";
    $this->taskExec($media_migration_command)->run();
    $media_cv_migration_command = "vendor/bin/drush @{$alias}.{$env} mim media_cv_migration --update --continue-on-failure";
    $this->taskExec($media_cv_migration_command)->run();
    $media_document_migration_command = "vendor/bin/drush @{$alias}.{$env} mim media_document_migration --update --continue-on-failure";
    $this->taskExec($media_document_migration_command)->run();
  } else if ($handleFiles === 'unzip files archive') {
    $tar_path = "/home/drupaladm/d9main/web/sites/{$alias}.wustl.edu/files.tar.gz";
    if (!file_exists($tar_path)) {
      throw new BltException("The archive {$tar_path} was not found.");
    }
    $this->say("Extracting {$tar_path}...");
    $untar_command = "tar -xvf {$tar_path} -C web/sites/{$site_dir}";
    $this->taskExec($untar_command)->run();
  }

  $this->say("<info>Setting permissions for {$files_path}...</info>");
  $chown_command = "sudo chown -R drupaladm.apache {$files_path}";
  $this->taskExec($chown_command)->run();

  $chmod_command = "sudo chmod -R 777 {$files_path}";
  $this->taskExec($chmod_command)->run();

  $this->say("<info>Permissions updated for {$files_path}</info>");

  // Reapply dev settings and set passwords
  $this->taskDrush()
    ->stopOnFail(FALSE)
    ->drush('php:eval')
    ->arg("\Drupal::keyValue('development_settings')->setMultiple(['disable_rendered_output_cache_bins' => FALSE, 'twig_debug' => FALSE, 'twig_cache_disable' => FALSE]);")
    ->alias("{$alias}.{$env}")
    ->run();

  $this->taskDrush()
    ->drush('upwd')
    ->args(['asdrupal', $password])
    ->alias("{$alias}.{$env}")
    ->run();

  $usernames = explode(',', $_ENV['WEBTEAM'] ?: '');
  $communications = explode(',', $_ENV['COMMUNICATIONS'] ?: '');

  if (empty($usernames) && empty($communications)) {
    throw new BltException("No usernames or communications provided. Set the WEBTEAM and COMMUNICATIONS environment variables.");
  }

  $userEmails = array_map(fn($username) => $username . '@wustl.edu', $usernames);
  $communicationEmails = array_map(fn($comms) => $comms . '@wustl.edu', $communications);

  foreach ($usernames as $index => $username) {
    $this->createUserWithRole($username, $userEmails[$index], 'administrator', $site_dir);
  }

  foreach ($communications as $index => $comms) {
    $this->createUserWithRole($comms, $communicationEmails[$index], 'communications', $site_dir);
  }

  $this->taskDrush()
    ->stopOnFail(FALSE)
    ->drush('cr')
    ->alias("{$alias}.{$env}")
    ->run();

  $this->say("<info>Database import complete for @{$alias}.{$env}</info>");
}

/**
 * Deletes all aggregator feeds and re-imports them from OPML.
 *
 * @option sites
 *   Comma-separated list of sites to execute on.
 * @option exclude
 *   Comma-separated list of sites to exclude.
 *
 * @command artsci:aggregator:reset
 * @aliases aar
 */
public function resetAggregatorFeeds(array $options = [
  'sites' => '',
  'exclude' => '',
]) {
      $sitesToRun = [];
      $allSites = $this->getConfigValue('multisites');
  
      // Parse sites option into an array, or use all sites if not provided.
    // Parse sites option into an array, or use all sites if not provided.
    if (!empty($options['sites'])) {
      $requestedSites = array_map('trim', explode(',', $options['sites']));
      $sitesToRun = array_intersect($allSites, $requestedSites);
    }
    else {
      $sitesToRun = $allSites;
    }
  
      foreach ($sitesToRun as $multisite) {
        // if (in_array($multisite, $options['exclude'])) {
        //   $this->say("Skipping excluded site {$multisite}.");
        //   continue;
        // }
  
        $this->switchSiteContext($multisite);
        $db = $this->getConfigValue('drupal.db.database');
  
        $this->say("<info>Executing on {$multisite}...</info>");


    // Run Drupal-safe code using Drush eval.
    $deleteFeedsScript = <<<PHP
      \$feed_ids = \\Drupal::entityQuery('aggregator_feed')->accessCheck(FALSE)->execute();
      if (!empty(\$feed_ids)) {
        \$feeds = \\Drupal::entityTypeManager()->getStorage('aggregator_feed')->loadMultiple(\$feed_ids);
        foreach (\$feeds as \$feed) {
          \$feed->delete();
        }
        \\Drupal::logger('shared_content')->info('Deleted @count feeds.', ['@count' => count(\$feed_ids)]);
      } else {
        \\Drupal::logger('shared_content')->info('No feeds found to delete.');
      }
    PHP;

    $this->taskDrush()
      ->drush('eval')
      ->arg($deleteFeedsScript)
      ->run();
    // Reimport OPML feeds.
    $this->taskDrush()
      ->drush('sc-opml')
      ->run();
      $this->taskDrush()
      ->drush('sc-refresh')
      ->run();
  }
}


}

