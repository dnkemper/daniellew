<?php

namespace Drupal\olympian_migration\Plugin\migrate\process;

use Drupal\migrate\MigrateExecutableInterface;
use Drupal\migrate\Row;
use Drupal\migrate\ProcessPluginBase;

/**
 * Provides a 'coalesce' migrate process plugin.
 *
 * This plugin returns the first non-empty value from a list of sources.
 *
 * @MigrateProcessPlugin(
 *   id = "coalesce_custom"
 * )
 */
class CoalesceCustom extends ProcessPluginBase {

  /**
   * {@inheritdoc}
   */
  public function transform($value, MigrateExecutableInterface $migrate_executable, Row $row, $destination_property) {
    foreach ($value as $source_value) {
      if (!empty($source_value)) {
        return $source_value;
      }
    }
    return NULL;
  }

}
