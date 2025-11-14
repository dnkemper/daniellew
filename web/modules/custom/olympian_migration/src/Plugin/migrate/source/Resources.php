<?php

namespace Drupal\olympian_migration\Plugin\migrate\source;

use Drupal\migrate\Event\MigrateImportEvent;
use Drupal\migrate\Row;

/**
 * Basic implementation of the source plugin.
 *
 * @MigrateSource(
 *  id = "resources",
 *  source_module = "node"
 * )
 */
class Resources extends BaseNodeSource {

  use ProcessMediaTrait;
  use LinkReplaceTrait;

  /**
   * {@inheritdoc}
   */
  public function fields() {
    $fields = parent::fields();
    $fields['position'] = $this->t('Position of the node in the queue.');
    return $fields;
  }

  /**
   * {@inheritdoc}
   */
  public function prepareRow(Row $row) {
    parent::prepareRow($row);

    $nid = $row->getSourceProperty('nid');
    if ($nid) {
        $query = $this->select('nodequeue_nodes', 'nq')
            ->fields('nq', ['position'])
            ->condition('nid', $nid, '=')
            ->execute()
            ->fetchField();

        // If a position exists, set it as a source property.
        if ($query !== FALSE) {
            $row->setSourceProperty('position', $query);
        }
    }

    // Set the position to 100 if it is empty or NULL.
    if (empty($row->getSourceProperty('position'))) {
        $row->setSourceProperty('position', 100);
    }

    return TRUE;

  }

}
