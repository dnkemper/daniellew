<?php

namespace Drupal\shared_content\Plugin\Action;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Action\ActionBase;
use Drupal\Core\Link;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Url;
use Drupal\node\Entity\Node;

/**
 * Action to import selected Aggregator Feed items as nodes.
 *
 * @Action(
 *   id = "import_aggregator_feed_item_action",
 *   label = @Translation("Import selected feed items as content"),
 *   type = "aggregator_item"
 * )
 */
class ImportAggregatorFeedItemAction extends ActionBase {

  /**
   * {@inheritdoc}
   */
  public function execute($entity = NULL) {
    $user = \Drupal::currentUser();

    if ($entity) {
      // Get the GUID and Feed Title.
      $guid = $entity->get('guid')->value;
      $fid_title = $entity->get('fid')->entity->label();
      $entity_title = $entity->get('title')->value;

      // Split the GUID to extract parts, with validation to avoid undefined array keys.
      $guid_parts = explode(' at ', $guid);
      $node_id = $guid_parts[0] ?? NULL;
      $base_url = $guid_parts[1] ?? NULL;

      // Only proceed if $node_id and $base_url are available.
      if ($node_id && $base_url) {
        // Determine content type based on the Feed Title.
        $fid_parts = explode(' ', $fid_title);
        $fid_test = isset($fid_parts[1]) ? $this->getContentType($fid_parts[1]) : '';

        // Construct the shared URL.
        $sharedURL = $base_url . '/xml/' . strtolower($fid_test) . '/' . $node_id . '/rss.xml';

        // Check if a node with the same shared URL already exists.
        if (!$this->checkExistingNode($sharedURL)) {
          // Fetch XML content.
          $xml = $this->sharedContentGetXml($sharedURL);
          if ($xml) {

              $xmlElement = $xml->node;

            // // Determine the XML element based on the source domain.
            $node = $this->createNodeFromXml($entity->getTitle(), $xml, strtolower($fid_test), $sharedURL);
            $node_id = $node->id();

            // Handle event-specific date processing.
            if ($fid_test === 'events' && isset($xmlElement->eventDate)) {
              $eventTBD = (int) $xmlElement->eventTBD;
              $node->set('field_event_date_tbd', $eventTBD);
              $dateField = $xmlElement->eventDate;
              $dateStart = isset($xmlElement->eventDateStart) ? $xmlElement->eventDateStart : NULL;
              $dateEnd = isset($xmlElement->eventDateEnd) ? $xmlElement->eventDateEnd : NULL;

              if ($dateStart) {
                $dateStartTimestamp = strtotime($dateStart) ?: NULL;
                $dateEndTimestamp = $dateEnd ? strtotime($dateEnd) : NULL;

                if ($dateStartTimestamp) {
                  $node->set('field_show_end_date', TRUE);
                  $node->set('field_event_smart_date', [
                    'value' => $dateStartTimestamp,
                    'end_value' => $dateEndTimestamp,
                  ]);
                }
              }
              else {
                $dates = explode(' to ', $dateField);
                if (count($dates) === 2) {
                  $dateStart = trim($dates[0]);
                  $dateEnd = trim($dates[1]);

                  if (strtotime($dateStart) !== FALSE) {
                    $node->set('field_show_end_date', TRUE);
                    $node->set('field_event_smart_date', [
                      'value' => str_replace(' ', 'T', $dateStart),
                      'end_value' => (strtotime($dateEnd) !== FALSE) ? str_replace(' ', 'T', $dateEnd) : NULL,
                    ]);
                  }
                }
              }
            }

            // Save node after setting fields.
            $node->save();

            // Create a URL and link for the new node.
            $url = Url::fromRoute('entity.node.canonical', ['node' => $node_id], ['absolute' => TRUE]);
            $link = Link::fromTextAndUrl($node->getTitle(), $url)->toString();

            // Display the linked message.
            \Drupal::messenger()->addMessage($this->t('Processed content: @link', ['@link' => $link]));

            // Log processed content.
            \Drupal::logger('shared_content')->info('Processed content: @link', ['@link' => $link]);

            // Update shared subscribers.
            // $this->updateSharedSubscribers($node_id);
          }
        }
        else {
          \Drupal::messenger()->addMessage($this->t('Content already exists for URL: @url', ['@url' => $entity_title]));
        }
      }
      else {
        \Drupal::messenger()->addWarning($this->t('GUID or base URL could not be determined from the feed item.'));
      }
    }
  }

  /**
   * Helper function to determine content type based on fid title.
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

  /**
   * Checks if a node with the given shared URL already exists.
   */
  protected function checkExistingNode($sharedURL) {
    $query = \Drupal::entityQuery('node')
      ->accessCheck(FALSE)
      ->condition('field_shared_content_xml', $sharedURL)
      ->range(0, 1);
    $nids = $query->execute();
    return !empty($nids);
  }

  /**
   * Fetches XML content from the provided URL using curl.
   */
  protected function sharedContentGetXml($path) {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $path);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_FAILONERROR, 1);
    $returned = curl_exec($ch);
    curl_close($ch);
    return simplexml_load_string($returned);
  }

  /**
   * Creates a node from XML data.
   */
    protected function createNodeFromXml($title, $xml, $type, $sharedURL) {
        if ($type == 'person') {
            $type = 'faculty_staff';
        }

        $xml_content = base64_encode($xml->asXML());

        $xmlElement = $xml->node;

        $node = Node::create([
            'type' => $type,
            'title' => $title,
            'field_shared_content_xml' => $sharedURL,
            'field_shared_content' => $xml_content,
            'status' => 1,
            'uid' => \Drupal::currentUser()->id(),
        ]);

        // Handle events-specific fields.
        if ($type == 'events' && isset($xmlElement->eventDate)) {
          $eventTBD = (string) $xmlElement->eventTBD;
          $node->set('field_event_date_tbd', $eventTBD);
            $dateField = (string) $xmlElement->eventDate;
            $dateStart = isset($xmlElement->eventDateStart) ? (string) $xmlElement->eventDateStart : NULL;
            $dateEnd = isset($xmlElement->eventDateEnd) ? (string) $xmlElement->eventDateEnd : NULL;
            // If start date has a value and end date is empty, set end to start.
            if (!empty($dateStart) && empty($dateEnd)) {
              $dateEnd = $dateStart;
            }
            if ($dateStart) {
                $dateStartTimestamp = strtotime($dateStart) ?: NULL;
                $dateEndTimestamp = $dateEnd ? strtotime($dateEnd) : NULL;

                if ($dateStartTimestamp) {
                    $node->set('field_show_end_date', TRUE);
                    $node->set('field_event_smart_date', [
                        'value' => $dateStartTimestamp,
                        'end_value' => $dateEndTimestamp,
                    ]);
                }
            }
            else {
                $dates = explode(' to ', $dateField);
                if (count($dates) === 2) {
                    $dateStart = trim($dates[0]);
                    $dateEnd = trim($dates[1]);

                    if (strtotime($dateStart) !== FALSE) {
                        $node->set('field_show_end_date', TRUE);
                        $node->set('field_event_smart_date', [
                            'value' => strtotime($dateStart),
                            'end_value' => (strtotime($dateEnd) !== FALSE) ? strtotime($dateEnd) : NULL,
                        ]);
                    }
                }
            }
        }

        // Handle articles-specific fields.
        if ($type == 'article' && isset($xmlElement->postDate)) {
            $postDate = (string) $xmlElement->postDate;
            $dateTime = \DateTime::createFromFormat('n.j.y', $postDate);

            if ($dateTime !== FALSE) {
                $timestamp = $dateTime->getTimestamp();
                $node->setCreatedTime($timestamp);
            }
            else {
                $node->setCreatedTime(\Drupal::time()->getRequestTime());
            }
        }

        // Handle faculty_staff-specific fields.
        if ($type === 'faculty_staff') {
            $firstName = isset($xmlElement->firstName) ? (string) $xmlElement->firstName : '';
            $lastName = isset($xmlElement->lastName) ? (string) $xmlElement->lastName : '';

            if (!empty($firstName) || !empty($lastName)) {
                $node->set('field_first_name', $firstName);
                $node->set('field_last_name', $lastName);
                $node->setTitle(trim("$firstName $lastName"));
            }
            else {
                $node->setTitle($xmlElement->title);
            }
        }

        $node->save();
        return $node;
    }

  /**
   * Updates the field_shared_subscribers field on the original node.
   */
  protected function updateSharedSubscribers($node_id) {
    if ($original_node = Node::load($node_id)) {
      $current_domain = \Drupal::request()->getHost();

      // Get existing subscribers.
      $subscribers = $original_node->get('field_shared_content_subscribers')->getValue();

      // Extract existing domains.
      $existing_domains = array_map(fn($item) => $item['value'], $subscribers);

      // Add new domain if not already present.
      if (!in_array($current_domain, $existing_domains, TRUE)) {
        $existing_domains[] = $current_domain;
        $original_node->set('field_shared_content_subscribers', array_map(fn($domain) => ['value' => $domain], $existing_domains));
        $original_node->save();
        \Drupal::logger('shared_content')->info("Added subscriber: @domain to node @nid", [
          '@domain' => $current_domain,
          '@nid' => $node_id,
        ]);
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function access($object, ?AccountInterface $account = NULL, $return_as_object = FALSE) {
    // Check if the user has the 'administer aggregator' permission.
    $access_result = $account->hasPermission('access content');
    return $return_as_object ? AccessResult::allowedIf($access_result) : $access_result;
  }

}
