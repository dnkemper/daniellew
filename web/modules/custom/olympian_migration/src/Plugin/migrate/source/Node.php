<?php

namespace Drupal\olympian_migration\Plugin\migrate\source;

/**
 * Basic implementation of the source plugin.
 *
 * @MigrateSource(
 *  id = "olympian_node",
 *  source_module = "node"
 * )
 */
class Node extends BaseNodeSource {
  use ProcessMediaTrait;
  use LinkReplaceTrait;
  use ProcessFieldCollectionTrait;

}
