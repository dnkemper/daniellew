<?php

namespace Drupal\olympian_migration\Plugin\migrate\source;

use Drupal\migrate\Event\MigrateImportEvent;
use Drupal\migrate\Row;

/**
 * Basic implementation of the source plugin.
 *
 * @MigrateSource(
 *  id = "article",
 *  source_module = "node"
 * )
 */
class Articles extends BaseNodeSource {

  use ProcessMediaTrait;
  use LinkReplaceTrait;

  /**
   * {@inheritdoc}
   */
  public function prepareRow(Row $row) {
    parent::prepareRow($row);

    // Check if an image was attached, and if so, update with new fid.
    $image = $row->getSourceProperty('field_link_thumbnail');

    if (!empty($image)) {
      $row->setSourceProperty('field_link_thumbnail_fid', $this->getFid($image[0]['fid']));
    }
    // Check if an image was attached, and if so, update with new fid.
    $image = $row->getSourceProperty('field_header_image');

    if (!empty($image)) {
      $row->setSourceProperty('field_header_image_fid', $this->getFid($image[0]['fid']));
    }
    $body = $row->getSourceProperty('body');

    if (isset($body[0])) {
      $body[0]['value'] = $this->replaceInlineFiles($body[0]['value']);
      $row->setSourceProperty('body', $body);

      // Check summary, and create one if none exists.
      $row->setSourceProperty('body_summary', $this->getSummaryFromTextField($body));
    }

    return TRUE;
  }

  /**
   * Functions to run following a completed migration.
   *
   * @param \Drupal\migrate\Event\MigrateImportEvent $event
   *   The migration event.
   */
  public function postImport(MigrateImportEvent $event) {
    static $have_run = FALSE;

    if (!$have_run) {
      $this->reportPossibleLinkBreaks(['node__body' => ['body_value']]);
      $this->postLinkReplace('node', ['node__body' => ['body_value']]);
      $have_run = TRUE;
    }
  }

}
