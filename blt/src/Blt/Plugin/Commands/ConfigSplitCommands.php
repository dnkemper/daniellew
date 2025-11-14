<?php

namespace Artsci\Blt\Plugin\Commands;

use Acquia\Blt\Robo\BltTasks;
use Acquia\Blt\Robo\Common\YamlMunge;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Finder\Finder;
use Artsci\Multisite;
use Acquia\Blt\Robo\Common\EnvironmentDetector;

/**
 * This class should contain hooks that are used in other commands.
 */
class ConfigSplitCommands extends BltTasks {


  /**
   * Validate that the command is not being run on the container.
   *
   * @command artsci:usplits:feature
   *
   */
  public function updateFeatureSplits($options = [
    'split' => InputOption::VALUE_OPTIONAL,
    'skip-export' => FALSE,
  ]) {
    $root = $this->getConfigValue('repo.root');
    $finder = new Finder();

    // Get all the config split features.
    $split_files = $finder
      ->files()
      ->in("$root/config/default/")
      ->depth('< 2')
      ->name('config_split.config_split.*.yml')
      ->sortByName();

    foreach ($split_files->getIterator() as $split_file) {
      if (file_exists($split_file)) {
        $split = YamlMunge::parseFile($split_file);
        $id = $split['id'];

        if (isset($options['split']) && $options['split'] !== $id) {
          continue;
        }

        if (NULL !== $id = $this->getSplitId($split)) {
          $this->setupSplit($id);

          if (!isset($options['skip-export']) || $options['skip-export'] === FALSE) {
            $this->updateSplit($id);
          }
        }
      }
    }
  }

  /**
   * Validate that the command is not being run on the container.
   *
   * @command artsci:usplits:migrate
   *
   * @requireContainer
   */
  public function updateMigrateSplits() {
    $root = $this->getConfigValue('repo.root');
    $finder = new Finder();

    // Get all the config split features.
    $split_files = $finder
      ->files()
      ->in("$root/web/sites/")
      ->name('config_split.config_split.*.yml')
      ->sortByName();

    foreach ($split_files->getIterator() as $split_file) {
      if (file_exists($split_file)) {
        // This assumes the split is stored in module/name/config/split dir and
        // the finder context in() does not change.
        $host = dirname($split_file->getRelativePath(), 4);
        $module = basename(dirname($split_file->getRelativePath(), 2));
        $split = YamlMunge::parseFile($split_file->getPathname());
        $alias = Multisite::getIdentifier("https://$host");
        $this->switchSiteContext($host);
        if (NULL !== $id = $this->getSplitId($split)) {
          $this->setupSplit($id, $alias, $module);
          $this->updateSplit($id, $alias);
        }
      }
    }
  }

/**
 * Get a list of feature config split values that the user can choose to activate.
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
   * Execute a Drush upwd command against all multisites.
   *
   * @param string $choice1
   *   The first choice (activate or disable).
   * @param string $choice2
   *   The second choice (site, local, stage, dev, prod, secure_content).
   * @param array $options
   *   Array of options.
   *
   * @command artsci:multisite:csplit
   *
   * @aliases artsi:csplit
   *
   * @throws \Exception
   */
  public function configSplitUpdate($choice1 = NULL, $choice2 = NULL, array $options = ['exclude' => []]) {
    // Prompt for the first choice (activate or disable).
    $choice1 = $this->io()->choice('Select the action (activate or disable)', ['config-split:activate' => 'Activate', 'config-split:deactivate' => 'Deactivate'], $choice1);

    // Get the available feature split values.
    $splitValues = $this->getFeatureSplitValues();

    // Check if there are any split values available.
    if (empty($splitValues)) {
        return 'No feature split values found.';
    }

    // Prompt the user to choose from the available split values.
    $choice2 = $this->io()->choice('Select the target feature split', $splitValues, $choice2);

    // Prompt for confirmation.
    if (!$this->io()->confirm("You will execute '$choice1' on '$choice2'. Are you sure?", TRUE)) {
        throw new \Exception('Aborted.');
    }
    $env = EnvironmentDetector::getAhEnv() ?: 'local';

      foreach ($this->getConfigValue('multisites') as $multisite) {
        $this->switchSiteContext($multisite);
        $db = $this->getConfigValue('drupal.db.database');

        // Skip sites whose database do not exist on the application in AH env.
        // if (EnvironmentDetector::isAhEnv() && !file_exists("/var/www/site-php/{$app}/{$db}-settings.inc")) {
          // $this->logger->info("Skipping {$multisite}. Database {$db} does not exist on this application.");
          // continue;
        // }

        if (!in_array($multisite, $options['exclude'])) {
          $this->say("<info>Executing on {$multisite}...</info>");

          $result = $this->taskDrush()
          ->drush($choice1)
          ->args([
            $choice2,
          ])
          ->run();

          if (!$result->wasSuccessful()) {
            $this->say("*Error* running command `drush $choice1 on split $choice2`for site $multisite.");
          }
        }
        else {
          $this->say("Skipping excluded site {$multisite}.");
        }
      }
    }


  /**
   * Validate that the command is not being run on the container.
   *
   * @command artsci:usplits:site
   *
   * @requireContainer
   */
  public function updateSiteSplits($options = [
    'host' => InputOption::VALUE_OPTIONAL,
    'skip-export' => FALSE,
  ]) {
    $root = $this->getConfigValue('repo.root');
    $split_name = 'config_split.config_split.site.yml';
    $finder = new Finder();

    $split_files = $finder
      ->files()
      ->in("$root/config/sites/")
      ->depth('< 2')
      ->name($split_name)
      ->sortByName();

    foreach ($split_files->getIterator() as $split_file) {
      // This assumes the finder in() context above does not change.
      $host = $split_file->getRelativePath();
      $split = YamlMunge::parseFile($split_file->getPathname());
      if (isset($options['host']) && $options['host'] !== $host) {
        continue;
      }
      $alias = Multisite::getIdentifier("https://$host");
      $this->switchSiteContext($host);
      if (NULL !== $id = $this->getSplitId($split)) {
        $this->setupSplit($id, $alias);
        if (!isset($options['skip-export']) || $options['skip-export'] === FALSE) {
          $this->updateSplit($id, $alias);
        }
      }
    }
  }

  /**
   * Sync a site and make sure that the split is installed.
   */
  protected function setupSplit($split_id, $alias = 'default', $module = NULL) {
    $this->say("Setting up the <comment>$split_id</comment> config split on $alias.");

    // Recreate the database in case this site has never been blt-synced before.
    $this->taskDrush()
      ->stopOnFail()
      ->drush('sql:create')
      ->drush('sql:sync')
      ->args([
        "@$alias.prod",
        "@$alias.local",
      ])
      ->drush('cache:rebuild')
      ->drush('config:import')
      ->drush('config:import')
      ->run();

    if ($module) {
      $this->taskDrush()
        ->drush('pm:enable')
        ->arg($module)
        ->run();
    }

    // Create an array of splits to be enabled.
    $enable_splits = [
      $split_id,
    ];

    // Check configuration to see if this split has any dependencies on
    // other splits and add them to the splits to be enabled.

    foreach ($enable_splits as $enable_split_id) {
      // Check the split status.
      $result = $this->taskDrush()
        ->stopOnFail(FALSE)
        ->drush('config:get')
        ->alias("$alias.local")
        ->args("config_split.config_split.{$enable_split_id}", 'status')
        ->run();

      $status = FALSE;
      if ($result->getExitCode() !== 1 && $result->getMessage() !== '') {
        $status = trim($result->getMessage());
        $status = str_replace("'config_split.config_split.$enable_split_id:status': ", '', $status);
        $status = $status === 'true';
      }

      // If the split is not enabled, enable it, rebuild cache, and re-import
      // config.
      if (!$status) {
        $this->taskDrush()
          ->stopOnFail(FALSE)
          ->drush('config:set')
          ->args("config_split.config_split.{$enable_split_id}", 'status', TRUE)
          ->drush('cache:rebuild')
          ->drush('config:import')
          ->drush('config:import')
          ->drush('config:status')
          ->run();
      }
    }
  }

  /**
   * Export a split.
   */
  protected function updateSplit($split_id, $alias = 'default') {
    $this->say("Updating the <comment>$split_id</comment> config split on $alias.");

    // Run database updates after config.
    $this->taskDrush()
      ->stopOnFail()
      ->drush('updb')
      ->alias("$alias.local")
      ->run();

    // Re-export the split.
    $this->taskDrush()
      ->stopOnFail(FALSE)
      ->drush('config-split:export')
      ->alias("$alias.local")
      ->arg($split_id)
      ->run();
  }

  /**
   * Get the Split ID or NULL.
   */
  protected function getSplitId($split) {
    return $split['id'] ?? NULL;
  }

}