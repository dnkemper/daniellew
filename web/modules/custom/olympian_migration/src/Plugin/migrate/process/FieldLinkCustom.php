<?php

namespace Drupal\olympian_migration\Plugin\migrate\process;

use Drupal\link\Plugin\migrate\process\FieldLink;
use Drupal\migrate\MigrateExecutableInterface;
use Drupal\migrate\Row;

/**
 * Extension of the field_link plugin to handle multiple source fields.
 *
 * Add to the process section of the migration YML:
 *
 * @code
 * field_destination:
 *   plugin: field_link_custom
 *   source: field_source
 *   title_source: field_title_source
 * @endcode
 *
 * @MigrateProcessPlugin(
 *   id = "field_link_custom",
 *   handle_multiples = TRUE
 * )
 */
class FieldLinkCustom extends FieldLink {

  /**
   * {@inheritdoc}
   */
  public function transform($value, MigrateExecutableInterface $migrate_executable, Row $row, $destination_property) {
    $route = [];

    // Process the URI.
    if (!empty($value)) {
      if (is_array($value) && isset($value[0]['value'])) {
        // Trim the URI value before canonicalizing it.
        $route['uri'] = $this->canonicalizeUri(trim($value[0]['value']));
      }
      elseif (is_string($value)) {
        // Trim the URI value before canonicalizing it.
        $route['uri'] = $this->canonicalizeUri(trim($value));
      }
    }

    // Now only set the title if we actually have a 'uri' AND a non-empty title source.
    if (!empty($route['uri']) && !empty($this->configuration['title_source'])) {
      $title_source = $row->getSourceProperty($this->configuration['title_source']);

      // If the title source has content, set it.
      if (!empty($title_source)) {
        if (is_array($title_source) && isset($title_source[0]['value'])) {
          $route['title'] = $title_source[0]['value'];
        }
        elseif (is_string($title_source)) {
          $route['title'] = $title_source;
        }
        // If it's some other shape, simply skip setting a title
        // (because we don't want a fallback).
      }
    }

    return $route;
  }

}
