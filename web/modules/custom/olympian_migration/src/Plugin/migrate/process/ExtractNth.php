<?php

namespace Drupal\olympian_migration\Plugin\migrate\process;

use Drupal\migrate\ProcessPluginBase;
use Drupal\migrate\MigrateExecutableInterface;
use Drupal\migrate\Row;
use Drupal\migrate\MigrateException;

/**
 * Extracts the nth value from an array.
 *
 * Available configuration keys:
 * - source: The input value - must be an array.
 * - index: Numeric index to access the nth value in the array.
 * - default: (optional) A default value to assign if the index is out of bounds.
 *
 * Examples:
 *
 * @code
 * process:
 *   nth_text_field:
 *     plugin: extract_nth
 *     source: some_text_field
 *     index: 2
 * @endcode
 *
 * This would extract the third element from the 'some_text_field' assuming it's an array.
 * If the index is out of bounds, and a default value is specified, it will be returned.
 *
 * @MigrateProcessPlugin(
 *   id = "extract_nth",
 *   handle_multiples = TRUE
 * )
 */
class ExtractNth extends ProcessPluginBase {

  /**
   * {@inheritdoc}
   */
  public function transform($value, MigrateExecutableInterface $migrate_executable, Row $row, $destination_property) {
    if (!is_array($value)) {
      throw new MigrateException(sprintf("Input should be an array, instead it was of type '%s'", gettype($value)));
    }

    // Convert the input array to a simple indexed array.
    $value_array = array_values($value);
    
    // Get the index from configuration.
    $index = $this->configuration['index'];

    // Check if the index exists in the array.
    if (array_key_exists($index, $value_array)) {
      return $value_array[$index];
    } else {
      // Return default value if set, otherwise throw exception.
      if (array_key_exists('default', $this->configuration)) {
        return $this->configuration['default'];
      }
      else {
        throw new MigrateException(sprintf("Index %d out of bounds for the array. Consider adding a `default` key to the configuration.", $index));
      }
    }
  }
}
