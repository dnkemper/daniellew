<?php

namespace Drupal\olympian_migration\Plugin\migrate\source;

use Drupal\migrate\Row;

/**
 * Basic implementation of the source plugin.
 *
 * @MigrateSource(
 *  id = "people",
 *  source_module = "node"
 * )
 */
class People extends BaseNodeSource {

  use ProcessMediaTrait;

  /**
   * {@inheritdoc}
   */
  public function query() {
    $query = parent::query();
    // There are two test articles and one with a malformed
    // source reference that we are skipping.

    $query->condition('nr.nid', [13375, 13146, 13144, 13189], 'NOT IN');
    return $query;
  }
  
  /**
   * {@inheritdoc}
   */
  public function prepareRow(Row $row) {
    parent::prepareRow($row);

    // Get mid from fid for profile image.
    $image = $row->getSourceProperty('field_author_headshot');

    if (!empty($image)) {
      $mid = $this->profileImage($image[0]['fid'])['entity_id'];

      if ($mid) {
        $row->setSourceProperty('person_mid', $mid);
      }
    }

    // Check summary, and create one if none exists.
    $bio = $row->getSourceProperty('field_biography');

    if (!empty($bio)) {
      $bio[0]['value'] = $this->replaceInlineFiles($bio[0]['value']);
      $row->setSourceProperty('field_biography', $bio);
      $row->setSourceProperty('field_biography_summary', $this->getSummaryFromTextField($bio));
    }

    return TRUE;
  }

  /**
   * Fetch the media uuid based on the provided filename.
   */
  public function profileImage($fid) {
    $file_data = $this->fidQuery($fid);
    $filename = $file_data['filename'];
    $connection = \Drupal::database();
    $query = $connection->select('file_managed', 'f');
    $query->join('media__field_media_image', 'fmi', 'f.fid = fmi.field_media_image_target_id');
    $result = $query->fields('fmi', ['entity_id'])
      ->condition('f.filename', $filename)
      ->execute();

    return $result->fetchAssoc();
  }

}
