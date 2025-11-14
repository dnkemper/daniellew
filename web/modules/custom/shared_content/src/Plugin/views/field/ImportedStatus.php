<?php

namespace Drupal\shared_content\Plugin\views\field;

use Drupal\views\ResultRow;
use Drupal\views\Plugin\views\field\FieldPluginBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\Core\Link;

/**
 * Defines a field to show the imported status of an aggregator item.
 *
 * @ViewsField("imported_status")
 */
class ImportedStatus extends FieldPluginBase {

  /**
   * {@inheritdoc}
   */
  public function query() {
    // No query is necessary; this field's value is calculated.
  }

  /**
   * {@inheritdoc}
   */
  public function render(ResultRow $values) {
    // Get the GUID or shared URL for this item.
    $guid = $values->_entity->get('guid')->value;
    $fid_title = $values->_entity->get('fid')->entity->label();

    // Split GUID to get base URL.
    $guid_parts = explode(' at ', $guid);
    $node_id = $guid_parts[0];
    $base_url = $guid_parts[1];

    // Determine content type based on fid title.
    $fid_parts = explode(' ', $fid_title);
    $fid_test = $this->getContentType($fid_parts[1]);

    // Construct the shared URL.
    $sharedURL = $base_url . '/xml/' . strtolower($fid_test) . '/' . $node_id . '/rss.xml';

    // Check if the content has already been imported and get the node ID.
    $imported_nid = $this->checkExistingNode($sharedURL);

    // If imported, create a link to the node.
if ($imported_nid) {
  $node = \Drupal::entityTypeManager()->getStorage('node')->load($imported_nid);
  if ($node) {
    $url = Url::fromRoute('entity.node.canonical', ['node' => $imported_nid]);
    $link = Link::fromTextAndUrl($node->getTitle(), $url);

    return [
      '#type' => 'container',
      '#attributes' => ['class' => ['imported-status']],
      'link' => $link->toRenderable(),
    ];
  }
}

    return ['#markup' => ''];
  }

  /**
   * Helper to check if content with the given shared URL already exists.
   *
   * @return int|false
   *   The node ID if found, FALSE otherwise.
   */
  protected function checkExistingNode($sharedURL) {
    $query = \Drupal::entityQuery('node')
      ->accessCheck(FALSE)
      ->condition('field_shared_content_xml', $sharedURL)
      ->range(0, 1);
    $nids = $query->execute();

    return !empty($nids) ? reset($nids) : FALSE;
  }

  /**
   * Helper to determine content type.
   */
  protected function getContentType($fid_part) {
    switch ($fid_part) {
      case 'Articles':
        return 'article';
      case 'Events':
        return 'events';
      case 'Person':
        return 'faculty_staff';
      case 'Books':
        return 'book';
      default:
        return '';
    }
  }
}
