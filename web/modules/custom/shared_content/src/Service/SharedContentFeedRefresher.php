<?php

namespace Drupal\shared_content\Service;

use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\node\Entity\Node;
use Drupal\aggregator\Entity\Feed;
use GuzzleHttp\ClientInterface;
use Symfony\Component\HttpFoundation\Response;

/**
 * Service that refreshes and updates shared content feeds.
 */
class SharedContentFeedRefresher {

  /**
   * The database connection.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected $database;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The HTTP client.
   *
   * @var \GuzzleHttp\ClientInterface
   */
  protected $httpClient;

  /**
   * Constructs an OPMLImporter object.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\Core\Database\Connection $database
   *   The database connection.
   * @param \GuzzleHttp\ClientInterface $http_client
   *   The Guzzle HTTP client.
   */
  public function __construct(Connection $database, EntityTypeManagerInterface $entityTypeManager, ClientInterface $httpClient) {
    $this->database = $database;
    $this->entityTypeManager = $entityTypeManager;
    $this->httpClient = $httpClient;
  }

  /**
   *
   */
  public function run() {
    $this->refreshQueuedFeeds();
  }

  /**
   * Queues all aggregator feeds for refreshing.
   */
  protected function queueFeedsForRefresh() {
    $queue = \Drupal::service('queue')->get('aggregator_feeds');
    $feed_storage = $this->entityTypeManager->getStorage('aggregator_feed');
    $time_service = \Drupal::service('datetime.time');
    $ids = $feed_storage->getFeedIdsToRefresh();
    foreach (Feed::loadMultiple($ids) as $feed) {
      if ($queue->createItem($feed->id())) {
        $feed->setQueuedTime($time_service->getRequestTime());
        $feed->save();
      }
    }
  }

  /**
   * Refreshes all queued feeds and updates field_shared_content.
   */
  protected function refreshQueuedFeeds() {
    $nids = \Drupal::entityQuery('node')
      ->accessCheck(FALSE)
      ->condition('type', ['faculty_staff', 'events', 'article', 'book'], 'IN')
      ->condition('field_shared_content_xml', NULL, 'IS NOT NULL')
      ->execute();
      $emptyValues = [
        base64_encode('<?xml version="1.0" encoding="utf-8"?><nodes><node/></nodes>'),
        base64_encode('<?xml version="1.0" encoding="UTF-8"?><response><item/></response>'),
        base64_encode('<?xml version="1.0" encoding="utf-8"?><nodes></nodes>'),
        base64_encode('<?xml version="1.0" encoding="UTF-8"?><response></response>'),
        'PD94bWwgdmVyc2lvbj0iMS4wIiBlbmNvZGluZz0iVVRGLTgiPz4KPHJlc3BvbnNlPgo8L3Jlc3BvbnNlPgo=',
        'PD94bWwgdmVyc2lvbj0iMS4wIiBlbmNvZGluZz0iVVRGLTgiPz4KPG5vZGVzPgo8L25vZGVzPgo=',
      ];
    if (!empty($nids)) {
      $nodes = Node::loadMultiple($nids);

      foreach ($nodes as $node) {
        $sharedURL = $node->get('field_shared_content_xml')->value;
        if (!empty($sharedURL)) {
          $xml = $this->sharedContentGetXml($sharedURL);
          if ($xml) {

            $xmlElement = $xml->node;

            $encoded_xml = base64_encode($xml->asXML());
            $current_value = $node->get('field_shared_content')->value;


            // ✅ Only update field_shared_content if changed and not in empty values
            if (!in_array($encoded_xml, $emptyValues, true) && $encoded_xml !== $node->get('field_shared_content')->value) {
              $this->updateNodeFields($node, $xmlElement);
              $node->set('field_shared_content', $encoded_xml);
              $node->save();
              \Drupal::logger('shared_content')->info('Cron: Updated node @nid with refreshed XML.', ['@nid' => $node->id()]);
              continue;
            }
          }
        }
      }
    }
  }

  /**
   * Fetches XML content from a given URL.
   */
  protected function sharedContentGetXml($url) {
    try {
      $response = $this->httpClient->request('GET', $url, [
        'headers' => ['Accept' => 'application/xml'],
        'timeout' => 120,
      ]);

      if ($response->getStatusCode() === Response::HTTP_OK) {
        return $this->parseResponse($response->getBody()->getContents());
      }
      else {
        \Drupal::logger('shared_content')->error('Failed to fetch XML from @url: @status', [
          '@url' => $url,
          '@status' => $response->getStatusCode(),
        ]);
      }
    }
    catch (\Exception $e) {
      \Drupal::logger('shared_content')->error('Exception fetching XML from @url: @message', [
        '@url' => $url,
        '@message' => $e->getMessage(),
      ]);
    }
    return NULL;

  }

  /**
   *
   */
  protected function parseResponse($response) {
    return new \SimpleXMLElement($response);
  }


  /**
   * Updates node fields based on the content type.
   */
  protected function updateNodeFields(Node $node, \SimpleXMLElement $xmlElement) {
    $type = $node->bundle();

    // Handle events-specific fields.
    if ($type === 'events' && isset($xmlElement->eventDate)) {
      $node->setTitle((string) $xmlElement->title);
      $eventTBD = (int) $xmlElement->eventTBD;
      $node->set('field_event_date_tbd', $eventTBD);
      $node->set('field_show_end_date', TRUE);
      if ($node->hasField('field_external_link')) {
        if (isset($xmlElement->externalURL) && !empty((string) $xmlElement->externalURL)) {
          $external_url = (string) $xmlElement->externalURL;
          $node->set('field_external_link', [
            'uri' => $external_url,
          ]);
        }
      }
      $dateField = (int) isset($xmlElement->eventDate) ? (int) $xmlElement->eventDate : NULL;
      $dateStart = isset($xmlElement->eventDateStart) ? (string) $xmlElement->eventDateStart : NULL;
      $dateEnd = isset($xmlElement->eventDateEnd) ? (string) $xmlElement->eventDateEnd : NULL;
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
              'value' => str_replace(' ', 'T', $dateStart),
              'end_value' => (strtotime($dateEnd) !== FALSE) ? str_replace(' ', 'T', $dateEnd) : NULL,
            ]);
          }
        }
      }
    }

    // Handle articles-specific fields.
    if ($type === 'article' && isset($xmlElement->postDate)) {
      $postDate = (string) $xmlElement->postDate;
      $node->setTitle((string) $xmlElement->title);
      $dateTime = \DateTime::createFromFormat('n.j.y', $postDate);
      if ($node->hasField('field_external_link')) {
        if (isset($xmlElement->externalURL) && !empty((string) $xmlElement->externalURL)) {
          $external_url = (string) $xmlElement->externalURL;
          $node->set('field_external_link', [
            'uri' => $external_url,
          ]);
        }
      }

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
      $node->setTitle((string) $xmlElement->title);
      $firstName = isset($xmlElement->firstName) ? (string) $xmlElement->firstName : '';
      $lastName = isset($xmlElement->lastName) ? (string) $xmlElement->lastName : '';
      if ($node->hasField('field_external_link')) {
        if (isset($xmlElement->externalURL) && !empty((string) $xmlElement->externalURL)) {
          $external_url = (string) $xmlElement->externalURL;
          $node->set('field_external_link', [
            'uri' => $external_url,
          ]);
        }
      }

      if (!empty($firstName) || !empty($lastName)) {
        $node->set('field_first_name', $firstName);
        $node->set('field_last_name', $lastName);
        $node->setTitle(trim("$firstName $lastName"));
      }
      else {
        $node->setTitle((string) $xmlElement->title);
      }
    }
  }

  /**
   * Cleans up old queued feeds.
   */
  protected function cleanupOldQueuedFeeds() {
    $threshold = time() - (3600 * 6);
    $ids = \Drupal::entityQuery('aggregator_feed')
      ->accessCheck(FALSE)
      ->condition('queued', $threshold, '<')
      ->execute();

    if ($ids) {
      $feeds = Feed::loadMultiple($ids);
      foreach ($feeds as $feed) {
        $feed->setQueuedTime(0);
        $feed->save();
      }
    }
  }

}
