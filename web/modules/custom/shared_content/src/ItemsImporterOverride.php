<?php

namespace Drupal\shared_content;

use Drupal\aggregator\FeedInterface;
use Drupal\aggregator\ItemsImporter;
use Drupal\aggregator\Plugin\AggregatorPluginManager;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\KeyValueStore\KeyValueFactoryInterface;
use Psr\Log\LoggerInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\aggregator\Entity\Item;

/**
 * Modify the Aggregator ItemsImporter with a custom refresh.
 */
class ItemsImporterOverride extends ItemsImporter {

  /**
   * Original service object.
   *
   * @var \Drupal\aggregator\ItemsImporter
   */
  protected $itemsImporter;

  /**
   * Entity type manager service.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * {@inheritdoc}
   */
  public function __construct(
    ItemsImporter $itemsImporter,
    ConfigFactoryInterface $configFactory,
    AggregatorPluginManager $fetcherManager,
    AggregatorPluginManager $parserManager,
    AggregatorPluginManager $processorManager,
    LoggerInterface $logger,
    KeyValueFactoryInterface $keyValue,
    EntityTypeManagerInterface $entityTypeManager,
  ) {
    $this->itemsImporter = $itemsImporter;
    $this->entityTypeManager = $entityTypeManager;
    parent::__construct($configFactory, $fetcherManager, $parserManager, $processorManager, $logger, $keyValue);
  }

  /**
   * {@inheritdoc}
   */
  public function refresh(FeedInterface $feed) {
    $purgeItems = $feed->get('field_aggregator_purge_items')?->value;
    $feedSource = $feed->get('field_feed_source')?->value;
    $feedType = $feed->get('field_feed_type')?->value;

    // Update the feed with missing custom fields.
    if (empty($feed->get('field_feed_source')->value)) {
      $feed->set('field_feed_source', $feedSource);
    }
    if (empty($feed->get('field_feed_type')->value)) {
      $feed->set('field_feed_type', $feedType);
    }

    // Check if purgeItems is set or either feedSource or feedType are empty.
    if ($purgeItems) {
      $feed->deleteItems();
    }

    parent::refresh($feed);

    // Update newly imported items with the feed's custom fields.
    $this->updateFeedItems($feed);
  }

  /**
   * Updates imported feed items with custom fields from the feed.
   */
  protected function updateFeedItems(FeedInterface $feed) {
    $storage = $this->entityTypeManager->getStorage('aggregator_item');

    // Load all items related to the feed.
    $item_ids = $storage->getQuery()
      ->condition('fid', $feed->id())
      ->accessCheck(FALSE)
      ->execute();

    if (!empty($item_ids)) {
      $items = $storage->loadMultiple($item_ids);

      foreach ($items as $item) {
        if ($item instanceof Item) {
          // Assign custom fields to the imported items.
          $item->set('field_feed_source', $feed->get('field_feed_source')->value);
          $item->set('field_feed_type', $feed->get('field_feed_type')->value);
          $item->save();
        }
      }
    }
  }

}
