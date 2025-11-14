<?php

namespace Drupal\olympian_migration\Plugin\migrate\process;

use Drupal\migrate\MigrateExecutableInterface;
use Drupal\migrate\ProcessPluginBase;
use Drupal\migrate\Row;
use Drupal\migrate\Plugin\MigrationInterface;

/**
 * Fetches and unpacks a serialized Drupal 7 variable.
 *
 * @MigrateProcessPlugin(
 *   id = "unpack_variable"
 * )
 */
class UnpackVariable extends ProcessPluginBase {

  public function __construct(array $configuration, $plugin_id, $plugin_definition, MigrationInterface $migration) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $migration);
    $this->migration = $migration;
  }

  public function transform($value, MigrateExecutableInterface $migrate_executable, Row $row, $destination_property) {
    $variable_name = $this->configuration['variable'];
    $key = $this->configuration['key'];

    // Get the database connection used by the migration source.
    $source_plugin = $this->migration->getSourcePlugin();
    $database = $source_plugin->getDatabase();

    // Query the variable from the Drupal 7 variable table.
    $serialized_value = $database->query("SELECT value FROM variable WHERE name = :name", [':name' => $variable_name])->fetchField();

    // Check if the variable is fetched and unserialize it.
    if ($serialized_value) {
      $data = unserialize($serialized_value);
      if (array_key_exists($key, $data)) {
        return $data[$key];
      }
    }

    return NULL;
  }

}
