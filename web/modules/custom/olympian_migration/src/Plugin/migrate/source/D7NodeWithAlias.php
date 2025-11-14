<?php

namespace Drupal\olympian_migration\Plugin\migrate\source;

use Drupal\node\Plugin\migrate\source\d7\Node;

/**
 * Drupal 7 node source with URL alias extension.
 *
 * @MigrateSource(
 *   id = "d7_node_with_alias"
 * )
 */
class D7NodeWithAlias extends BaseNodeSource {

  /**
   * {@inheritdoc}
   */
  public function query() {
    $query = parent::query();
    // Join with the URL alias table and concatenate a '/' with the alias.
    $query->leftJoin('url_alias', 'ua', "CONCAT('node/', n.nid) = ua.source");
    $query->addExpression("CONCAT('/', ua.alias)", 'alias');

    return $query;
  }

  /**
   * {@inheritdoc}
   */
  public function fields() {
    $fields = parent::fields();
    $fields['alias'] = $this->t('URL alias associated with the node');

    return $fields;
  }

  
}
