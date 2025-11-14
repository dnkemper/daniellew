<?php

namespace Drupal\olympian_migration\Plugin\migrate\process;

use Drupal\migrate\MigrateExecutableInterface;
use Drupal\migrate\ProcessPluginBase;
use Drupal\migrate\Row;

/**
 * Converts Migration Lookup results into target_id and target_revision_id.
 *
 * Use this after using migration_lookup for entity reference and entity
 * reference revisions migrations. This solves issues with inconsistencies
 * between paragraph and node lookups.
 *
 * Example:
 *
 * @code
 * process:
 *   field_team_players:
 *     -
 *       plugin: skip_on_empty
 *       method: process
 *       source: player_ids
 *     -
 *       plugin: migration_lookup
 *       migration: player_node
 *     -
 *       plugin: migration_lookup_target_ids
 * @endcode
 *
 * @see \Drupal\migrate\Plugin\MigrateProcessInterface
 *
 * @MigrateProcessPlugin(
 *   id = "migration_lookup_target_ids",
 *   handle_multiples = TRUE
 * )
 */
class MigrationLookupTargetIds extends ProcessPluginBase {

  /**
   * {@inheritdoc}
   */
  public function transform($value, MigrateExecutableInterface $migrate_executable, Row $row, $destination_property) {
    if (empty($value)) {
      // Ignore empty values.
      return $value;
    }

    // Make sure we are always operating on an array.
    if (is_string($value)) {
      $value = [$value];
    }

    // If the first item is a string there's no need to loop. Process one value.
    // $value may have one or two elements depending on the migration source.
    if (is_string($value[0])) {
      $this->processTargetIds($value);
    }
    else {
      // Loop through each subitem.
      // $subitem may have 1 or 2 elements depending on the migration source.
      // Each subitem can be one of the following:
      // - Single string value (converted to array before processing (below)).
      // - Array with single element (string).
      // - Array with two elements, both strings.
      foreach ($value as $key => &$subitem) {
        if (is_string($subitem)) {
          $subitem = [$subitem];
        }
        $this->processTargetIds($subitem);
      }
    }

    return $value;
  }

  /**
   * Change indexes from numeric to target_id and target_revision_id.
   *
   * @param array $value
   *   Array of one or two values (two for entity reference revisions only)
   */
  private function processTargetIds(array &$value) {
    $value['target_id'] = $value[0];
    unset($value[0]);
    if (!empty($value[1])) {
      // Entity reference revisions require this.
      $value['target_revision_id'] = $value[1];
      unset($value[1]);
    }
  }

}
