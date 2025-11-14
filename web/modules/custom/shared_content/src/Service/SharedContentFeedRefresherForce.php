<?php

namespace Drupal\shared_content\Service;

use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\node\Entity\Node;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\RequestException;
use Symfony\Component\HttpFoundation\Response;
use Drupal\Core\Queue\QueueFactory;
use Drupal\Core\State\StateInterface;
use Psr\Log\LoggerInterface;

/**
 * Service that refreshes and updates shared content feeds efficiently.
 */
class SharedContentFeedRefresherForce {

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
   * The queue factory.
   *
   * @var \Drupal\Core\Queue\QueueFactory
   */
  protected $queueFactory;

  /**
   * The state service.
   *
   * @var \Drupal\Core\State\StateInterface
   */
  protected $state;

  /**
   * The logger.
   *
   * @var \Psr\Log\LoggerInterface
   */
  protected $logger;

  /**
   * Constructs a SharedContentFeedRefresherForce object.
   */
  public function __construct(
    Connection $database,
    EntityTypeManagerInterface $entityTypeManager,
    ClientInterface $httpClient,
    QueueFactory $queueFactory,
    StateInterface $state,
    LoggerInterface $logger
  ) {
    $this->database = $database;
    $this->entityTypeManager = $entityTypeManager;
    $this->httpClient = $httpClient;
    $this->queueFactory = $queueFactory;
    $this->state = $state;
    $this->logger = $logger;
  }

  /**
   * Main entry point - queues nodes for processing.
   *
   * @param bool $force_all
   *   If TRUE, queue all nodes regardless of last fetch time. Default FALSE.
   * @param int $min_age
   *   Minimum age in seconds since last fetch before queuing. Default 3600 (1 hour).
   */
  public function run($force_all = FALSE, $min_age = 3600) {
    $this->queueNodesForRefresh($force_all, $min_age);
    $this->processDeletedNodes();
  }

  /**
   * Queues all eligible nodes for refresh processing.
   *
   * @param bool $force_all
   *   If TRUE, queue all nodes regardless of last fetch time. Default FALSE.
   * @param int $min_age
   *   Minimum age in seconds since last fetch before queuing. Default 3600 (1 hour).
   */
  public function queueNodesForRefresh($force_all = FALSE, $min_age = 3600) {
    $queue = $this->queueFactory->get('shared_content_refresh');

    // Get all nodes with shared content XML URLs.
    $query = $this->entityTypeManager->getStorage('node')->getQuery()
      ->accessCheck(FALSE)
      ->condition('type', ['faculty_staff', 'events', 'article', 'book'], 'IN')
      ->condition('field_shared_content_xml', NULL, 'IS NOT NULL');

    // If not forcing all, only get nodes that haven't been fetched recently.
    if (!$force_all) {
      $threshold = time() - $min_age;
      $query->condition(
        $query->orConditionGroup()
          ->condition('field_last_fetch', $threshold, '<')
          ->condition('field_last_fetch', NULL, 'IS NULL')
      );
    }

    $nids = $query->execute();

    $queued_count = 0;
    foreach ($nids as $nid) {
      if ($queue->createItem(['nid' => $nid])) {
        $queued_count++;
      }
    }

    $this->logger->info('Queued @count nodes for shared content refresh (force_all: @force, min_age: @age).', [
      '@count' => $queued_count,
      '@force' => $force_all ? 'yes' : 'no',
      '@age' => $min_age,
    ]);
  }

  /**
   * Process a single node (called by queue worker).
   *
   * @param int $nid
   *   The node ID to process.
   */
  public function processNode($nid) {
    $node = Node::load($nid);
    if (!$node) {
      $this->logger->warning('Node @nid not found during processing.', ['@nid' => $nid]);
      return;
    }

    $shared_url = $node->get('field_shared_content_xml')->value;
    if (empty($shared_url)) {
      return;
    }

    // Fetch XML with Last-Modified header support.
    $xml_data = $this->fetchXmlWithCaching($shared_url, $node);

    // Handle 404 - source no longer exists - automatically delete the node.
    if ($xml_data === 'NOT_FOUND') {
      $this->logger->warning('Source not found (404) for node @nid from @url - deleting node', [
        '@nid' => $nid,
        '@url' => $shared_url,
      ]);
      $this->deleteNodeWithEmptySource($node, 'source not found (HTTP 404)');
      return;
    }

    if (!$xml_data) {
      return;
    }

    [$xml, $response_hash] = $xml_data;

    // Get current hash from node.
    $current_hash = $node->hasField('field_content_hash') ? $node->get('field_content_hash')->value : NULL;

    // Skip if content hasn't changed.
    if ($current_hash === $response_hash) {
      $this->logger->debug('Node @nid unchanged, skipping update.', ['@nid' => $nid]);
      $this->trackNodeAsActive($node, $shared_url);
      return;
    }

    // Determine XML element based on feed source.
    $xml_element = $this->getXmlElement($xml, $shared_url);

    // Check if XML is empty/invalid - automatically delete the node.
    if ($this->isEmptyXml($xml_element)) {
      $this->logger->warning('Empty XML received for node @nid from @url - deleting node', [
        '@nid' => $nid,
        '@url' => $shared_url,
      ]);
      $this->deleteNodeWithEmptySource($node, 'empty or invalid XML');
      return;
    }

    // Update node fields and store hash.
    $this->updateNodeFields($node, $xml_element);

    if ($node->hasField('field_content_hash')) {
      $node->set('field_content_hash', $response_hash);
    }

    $encoded_xml = base64_encode($xml->asXML());
    $node->set('field_shared_content', $encoded_xml);

    $node->save();

    $this->trackNodeAsActive($node, $shared_url);

    $this->logger->info('Updated node @nid with fresh content from @url', [
      '@nid' => $nid,
      '@url' => $shared_url,
    ]);
  }

  /**
   * Delete a node whose source is empty or missing.
   *
   * @param \Drupal\node\Entity\Node $node
   *   The node to delete.
   * @param string $reason
   *   The reason for deletion.
   */
  protected function deleteNodeWithEmptySource(Node $node, $reason) {
    $nid = $node->id();
    $title = $node->getTitle();
    $source_url = $node->get('field_shared_content_xml')->value;

    try {
      $node->delete();

      $this->logger->info('Auto-deleted node @nid (@title) - @reason at @url', [
        '@nid' => $nid,
        '@title' => $title,
        '@reason' => $reason,
        '@url' => $source_url,
      ]);
    }
    catch (\Exception $e) {
      $this->logger->error('Failed to delete node @nid with empty source: @message', [
        '@nid' => $nid,
        '@message' => $e->getMessage(),
      ]);
    }
  }

  /**
   * Fetches XML with caching support using Last-Modified headers.
   *
   * @return array|string|null
   *   Array with [SimpleXMLElement, hash], 'NOT_FOUND' if 404, or NULL on other failures.
   */
  public function fetchXmlWithCaching($url, Node $node) {
    $options = [
      'headers' => ['Accept' => 'application/xml'],
      'timeout' => 320,
      'http_errors' => FALSE,
    ];

    // Add If-Modified-Since header if we have a last fetch time.
    $last_fetch = $node->hasField('field_last_fetch') ? $node->get('field_last_fetch')->value : NULL;
    if ($last_fetch) {
      $options['headers']['If-Modified-Since'] = gmdate('D, d M Y H:i:s \G\M\T', $last_fetch);
    }

    try {
      $response = $this->httpClient->request('GET', $url, $options);
      $status_code = $response->getStatusCode();

      // Source not found - return special marker.
      if ($status_code === Response::HTTP_NOT_FOUND) {
        $this->logger->debug('Source not found (404) for @url', ['@url' => $url]);
        return 'NOT_FOUND';
      }

      // Content not modified.
      if ($status_code === Response::HTTP_NOT_MODIFIED) {
        $this->logger->debug('Content not modified for @url', ['@url' => $url]);
        return NULL;
      }

      if ($status_code !== Response::HTTP_OK) {
        $this->logger->error('Failed to fetch XML from @url: HTTP @status', [
          '@url' => $url,
          '@status' => $status_code,
        ]);
        return NULL;
      }

      $content = $response->getBody()->getContents();

      if (empty($content)) {
        $this->logger->warning('Empty response body from @url', ['@url' => $url]);
        return NULL;
      }

      $xml = @simplexml_load_string($content);

      if ($xml === FALSE) {
        $this->logger->error('Failed to parse XML from @url', ['@url' => $url]);
        return NULL;
      }

      // Create hash of content for change detection.
      $hash = hash('sha256', $content);

      // Update last fetch time.
      if ($node->hasField('field_last_fetch')) {
        $node->set('field_last_fetch', time());
      }

      return [$xml, $hash];
    }
    catch (RequestException $e) {
      $this->logger->error('HTTP error fetching XML from @url: @message', [
        '@url' => $url,
        '@message' => $e->getMessage(),
      ]);
    }
    catch (\Exception $e) {
      $this->logger->error('Exception fetching/parsing XML from @url: @message', [
        '@url' => $url,
        '@message' => $e->getMessage(),
      ]);
    }

    return NULL;
  }

  /**
   * Determines the appropriate XML element based on feed source.
   *
   * @param \SimpleXMLElement $xml
   *   The XML object.
   * @param string $shared_url
   *   The shared URL.
   *
   * @return \SimpleXMLElement|null
   *   The appropriate XML element.
   */
  protected function getXmlElement($xml, $shared_url) {
    return $xml->node;
  }

  /**
   * Checks if XML element is effectively empty.
   *
   * @param \SimpleXMLElement|null $xml_element
   *   The XML element to check.
   *
   * @return bool
   *   TRUE if empty, FALSE otherwise.
   */
  protected function isEmptyXml($xml_element) {
    if (!$xml_element) {
      return TRUE;
    }

    // Check if element has no children and no text content.
    if (count($xml_element->children()) === 0 && trim((string) $xml_element) === '') {
      return TRUE;
    }

    return FALSE;
  }

  /**
   * Track this node as actively synced.
   *
   * @param \Drupal\node\Entity\Node $node
   *   The node being tracked.
   * @param string $source_url
   *   The source URL.
   */
  protected function trackNodeAsActive(Node $node, $source_url) {
    $feed_id = hash('md5', $source_url);
    $tracking = $this->state->get('shared_content.active_nodes', []);

    if (!isset($tracking[$feed_id])) {
      $tracking[$feed_id] = [];
    }

    $tracking[$feed_id][$node->id()] = [
      'nid' => $node->id(),
      'url' => $source_url,
      'last_seen' => time(),
    ];

    $this->state->set('shared_content.active_nodes', $tracking);
  }

  /**
   * Process deletion of nodes that no longer exist in their source feeds.
   */
  public function processDeletedNodes() {
    $tracking = $this->state->get('shared_content.active_nodes', []);
    $threshold = time() - (86400 * 7); // 7 days.
    $deleted_count = 0;

    foreach ($tracking as $feed_id => $nodes) {
      foreach ($nodes as $nid => $data) {
        // If node hasn't been seen in X days, mark for deletion.
        if ($data['last_seen'] < $threshold) {
          $node = Node::load($nid);
          if ($node) {
            // Optional: unpublish instead of delete.
            $node->setUnpublished();
            $node->save();
            // Or delete: $node->delete();

            $this->logger->info('Unpublished stale node @nid from @url (last seen @date)', [
              '@nid' => $nid,
              '@url' => $data['url'],
              '@date' => date('Y-m-d H:i:s', $data['last_seen']),
            ]);

            $deleted_count++;
          }

          // Remove from tracking.
          unset($tracking[$feed_id][$nid]);
        }
      }
    }

    $this->state->set('shared_content.active_nodes', $tracking);

    if ($deleted_count > 0) {
      $this->logger->info('Unpublished @count stale shared content nodes.', ['@count' => $deleted_count]);
    }
  }

  /**
   * Updates node fields based on the content type.
   *
   * @param \Drupal\node\Entity\Node $node
   *   The node to update.
   * @param \SimpleXMLElement $xml_element
   *   The XML element.
   */
  protected function updateNodeFields(Node $node, \SimpleXMLElement $xml_element) {
    $type = $node->bundle();

    // Update title for all types (if present in XML).
    if (isset($xml_element->title)) {
      $node->setTitle((string) $xml_element->title);
    }

    // Process external link for all applicable types.
    $this->processExternalLink($node, $xml_element);

    // Type-specific field processing.
    $this->processEventFields($node, $xml_element, $type);
    $this->processArticleFields($node, $xml_element, $type);
    $this->processFacultyStaffFields($node, $xml_element, $type);
    $this->processBookFields($node, $xml_element, $type);
  }

  /**
   * Processes event-specific fields.
   *
   * @param \Drupal\node\Entity\Node $node
   *   The node entity.
   * @param \SimpleXMLElement $xml_element
   *   The XML element.
   * @param string $type
   *   The content type.
   */
  protected function processEventFields($node, $xml_element, $type) {
    if ($type !== 'events' || !isset($xml_element->eventDate)) {
      return;
    }

    // Set event TBD field.
    if (isset($xml_element->eventTBD)) {
      $event_tbd = (int) $xml_element->eventTBD;
      $node->set('field_event_date_tbd', $event_tbd);
    }

    // Always show end date for events.
    $node->set('field_show_end_date', TRUE);

    // Process event dates.
    $date_start = isset($xml_element->eventDateStart) ? (string) $xml_element->eventDateStart : NULL;
    $date_end = isset($xml_element->eventDateEnd) ? (string) $xml_element->eventDateEnd : NULL;

    // If start date exists but end date is empty, set end to start.
    if (!empty($date_start) && empty($date_end)) {
      $date_end = $date_start;
    }

    if ($date_start) {
      $this->setEventDateFromTimestamps($node, $date_start, $date_end);
    }
    else {
      // Fallback to parsing eventDate field.
      $date_field = (string) $xml_element->eventDate;
      $this->setEventDateFromString($node, $date_field);
    }
  }

  /**
   * Sets event date from timestamp strings.
   *
   * @param \Drupal\node\Entity\Node $node
   *   The node entity.
   * @param string $date_start
   *   The start date string.
   * @param string|null $date_end
   *   The end date string.
   */
  protected function setEventDateFromTimestamps($node, $date_start, $date_end) {
    $start_timestamp = strtotime($date_start);
    $end_timestamp = $date_end ? strtotime($date_end) : $start_timestamp;

    if ($start_timestamp) {
      $node->set('field_event_smart_date', [
        'value' => $start_timestamp,
        'end_value' => $end_timestamp,
      ]);
    }
  }

  /**
   * Sets event date from a date range string.
   *
   * @param \Drupal\node\Entity\Node $node
   *   The node entity.
   * @param string $date_field
   *   The date field string (e.g., "2024-01-01 to 2024-01-02").
   */
  protected function setEventDateFromString($node, $date_field) {
    $dates = explode(' to ', $date_field);
    if (count($dates) === 2) {
      $date_start = trim($dates[0]);
      $date_end = trim($dates[1]);

      $start_timestamp = strtotime($date_start);
      $end_timestamp = strtotime($date_end);

      if ($start_timestamp !== FALSE) {
        $node->set('field_event_smart_date', [
          'value' => $start_timestamp,
          'end_value' => $end_timestamp ?: $start_timestamp,
        ]);
      }
    }
  }

  /**
   * Processes article-specific fields.
   *
   * @param \Drupal\node\Entity\Node $node
   *   The node entity.
   * @param \SimpleXMLElement $xml_element
   *   The XML element.
   * @param string $type
   *   The content type.
   */
  protected function processArticleFields($node, $xml_element, $type) {
    if ($type !== 'article' || !isset($xml_element->postDate)) {
      return;
    }

    $post_date = (string) $xml_element->postDate;
    $date_time = \DateTime::createFromFormat('n.j.y', $post_date);

    if ($date_time !== FALSE) {
      $node->setCreatedTime($date_time->getTimestamp());
    }
    else {
      $this->logger->warning('Invalid date format for article @nid: @date', [
        '@nid' => $node->id(),
        '@date' => $post_date,
      ]);
    }
  }

  /**
   * Processes faculty/staff-specific fields.
   *
   * @param \Drupal\node\Entity\Node $node
   *   The node entity.
   * @param \SimpleXMLElement $xml_element
   *   The XML element.
   * @param string $type
   *   The content type.
   */
  protected function processFacultyStaffFields($node, $xml_element, $type) {
    if ($type !== 'faculty_staff') {
      return;
    }

    $first_name = isset($xml_element->firstName) ? trim((string) $xml_element->firstName) : '';
    $last_name = isset($xml_element->lastName) ? trim((string) $xml_element->lastName) : '';

    if (!empty($first_name) || !empty($last_name)) {
      if ($node->hasField('field_first_name')) {
        $node->set('field_first_name', $first_name);
      }
      if ($node->hasField('field_last_name')) {
        $node->set('field_last_name', $last_name);
      }
      $node->setTitle(trim("$first_name $last_name"));
    }
    elseif (isset($xml_element->title)) {
      $node->setTitle((string) $xml_element->title);
    }
  }

  /**
   * Processes book-specific fields.
   *
   * @param \Drupal\node\Entity\Node $node
   *   The node entity.
   * @param \SimpleXMLElement $xml_element
   *   The XML element.
   * @param string $type
   *   The content type.
   */
  protected function processBookFields($node, $xml_element, $type) {
    if ($type !== 'book') {
      return;
    }

    if (isset($xml_element->title)) {
      $node->setTitle((string) $xml_element->title);
    }
  }

  /**
   * Processes external link field for all applicable content types.
   *
   * @param \Drupal\node\Entity\Node $node
   *   The node entity.
   * @param \SimpleXMLElement $xml_element
   *   The XML element.
   */
  protected function processExternalLink($node, \SimpleXMLElement $xml_element) {
    if (!$node->hasField('field_external_link')) {
      return;
    }

    if (isset($xml_element->externalURL) && !empty((string) $xml_element->externalURL)) {
      $external_url = (string) $xml_element->externalURL;
      $node->set('field_external_link', ['uri' => $external_url]);
    }
  }

}
