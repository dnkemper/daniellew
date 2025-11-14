<?php

namespace Drupal\shared_content\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Database\Connection;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\aggregator\Entity\Feed;
use Drupal\node\Entity\Node;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use GuzzleHttp\ClientInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Response;

/**
 * Provides a form to update all aggregator feeds.
 */
class AggregatorUpdateForm extends FormBase {

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
   * The messenger service.
   *
   * @var \Drupal\Core\Messenger\MessengerInterface
   */
  protected $messenger;

  /**
   * Constructs a new AggregatorUpdateForm.
   */
  public function __construct(Connection $database, EntityTypeManagerInterface $entityTypeManager, ClientInterface $httpClient, MessengerInterface $messenger) {
    $this->database = $database;
    $this->entityTypeManager = $entityTypeManager;
    $this->httpClient = $httpClient;
    $this->messenger = $messenger;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('database'),
      $container->get('entity_type.manager'),
      $container->get('http_client'),
      $container->get('messenger')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'aggregator_update_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form['update_all'] = [
      '#type' => 'submit',
      '#value' => $this->t('Refresh Feeds'),
    ];
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $this->updateAllFeeds();
  }

  /**
   * Updates all aggregator feeds.
   */
  protected function updateAllFeeds() {
    $this->queueFeedsForRefresh();
    $this->refreshQueuedFeeds();
    $this->cleanupOldQueuedFeeds();

    $this->messenger->addMessage($this->t('All aggregator feeds have been updated.'));
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
    $nids = $this->getNodesWithSharedContent();

    if (!empty($nids)) {
      $nodes = Node::loadMultiple($nids);
      foreach ($nodes as $node) {
        $sharedURL = $node->get('field_shared_content_xml')->value;
        if (!empty($sharedURL)) {
          $xml = $this->sharedContentGetXml($sharedURL);
          $xmlElement = $xml->node;
          if (empty($xmlElement) || count((array) $xmlElement) === 0) {
            // XML is empty, skip update.
            continue;
          }
          if ($xml) {
            $xml_string = $xml->asXML();
            if ($xml_string) {
              $encoded_xml = base64_encode($xml_string);

              if ($encoded_xml !== $node->get('field_shared_content')->value) {
                // Update node fields based on content type.
                $this->updateNodeFields($node, $xmlElement);
                $node->set('field_shared_content', $encoded_xml);
                // $node->setTitle((string) $xmlElement->title);
                $node->save();

                \Drupal::logger('shared_content')->info('Updated node @nid with refreshed XML content.', [
                  '@nid' => $node->id(),
                ]);
              }
            }
          }
        }
      }
    }
  }

  /**
   * Updates node fields based on the content type.
   *   The element to apply namespace registrations to.
   */
  protected function updateNodeFields(Node $node, \SimpleXMLElement $xmlElement) {
    $type = $node->bundle();

    // Handle events-specific fields.
    if ($type === 'events' && isset($xmlElement->eventDate)) {
      $dateField = (string) $xmlElement->eventDate;
      $node->setTitle((string) $xmlElement->title);
      $eventTBD = (int) $xmlElement->eventTBD;
      $node->set('field_event_date_tbd', $eventTBD);
      $node->set('field_show_end_date', TRUE);
      $dateStart = isset($xmlElement->eventDateStart) ? (string) $xmlElement->eventDateStart : NULL;
      $dateEnd = isset($xmlElement->eventDateEnd) ? (string) $xmlElement->eventDateEnd : NULL;

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
   * Loads nodes with shared content XML.
   */
  protected function getNodesWithSharedContent() {
    return \Drupal::entityQuery('node')
      ->accessCheck(FALSE)
      ->condition('type', ['faculty_staff', 'events', 'article', 'book'], 'IN')
      ->condition('field_shared_content_xml', NULL, 'IS NOT NULL')
      ->execute();
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
   * Cleans up old queued feeds.
   */
  protected function cleanupOldQueuedFeeds() {
    $threshold_time = time() - (3600 * 6);
    $ids = \Drupal::entityQuery('aggregator_feed')
      ->accessCheck(FALSE)
      ->condition('queued', $threshold_time, '<')
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
