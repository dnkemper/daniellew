<?php

namespace Drupal\shared_content\Form;

use Drupal\Core\Database\Database;
use Drupal\Core\Database\Query\PagerSelectExtender;
use Drupal\Core\Database\Query\TableSortExtender;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Link;
use Drupal\Core\Url;
use Drupal\aggregator\Entity\Feed;
use Drupal\node\Entity\Node;

/**
 * Provides old shared content form.
 */
class SharedContentForm extends FormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'shared_content_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form['update_feeds'] = [
      '#type' => 'submit',
      '#value' => $this->t('Update All Feeds'),
      '#submit' => ['::updateAllFeeds'],
    ];
    // Set limit for paginated results (50 results per page).
    $limit = 50;

    // Default sort for the Header is by creation date.
    $header = [
      'title' => ['data' => t('Title'), 'field' => 'f.title'],
      'type' => ['data' => t('Type'), 'field' => 'f.fid'],
      'feed' => ['data' => t('Feed Source'), 'field' => 'i.link'],
      'author' => ['data' => t('Author'), 'field' => 'i.author'],
      'created' => [
        'data' => t('Date Created'),
        'field' => 'i.timestamp',
      ],
      'checked' => [
        'data' => t('Checked'),
        'field' => 'i.timestamp',
      ],
      'modified' => [
        'data' => t('Modified'),
        'field' => 'i.timestamp',
        'sort' => 'desc',
      ],
    ];

    // Load default values from session.
    $contentTypeDefault = $_SESSION['content_type'] ?? '';
    $feedTypeDefault = $_SESSION['feed_source'] ?? '';
    $titleDefault = $_SESSION['title'] ?? '';
    $authorDefault = $_SESSION['author'] ?? '';
    $reset = $_SESSION['reset'] ?? 0;

    // If reset flag is set, clear default filter values.
    if ($reset == 1) {
      $titleDefault = $contentTypeDefault = $feedTypeDefault = $authorDefault = '';
    }

    // Start by getting a connection to the database and build the query.
    $query = Database::getConnection()->select('aggregator_item', 'i');
    $query->leftJoin('aggregator_feed', 'f', 'i.fid = f.fid');

    // Add fields from both tables, with distinct aliases.
    // Feed title.
    $query->addField('f', 'title', 'ftitle');
    // Item title.
    $query->addField('i', 'title', 'item_title');
    $query->fields('i', ['iid', 'link', 'fid', 'guid', 'author', 'timestamp'])
      ->fields('f', ['fid', 'title', 'url', 'checked', 'modified']);
    // ->fields('f', ['fid', 'checked, modified']);
    // Add conditions using Database::like() for the LIKE operator.
    $query->condition('i.author', '%' . Database::getConnection()
      ->escapeLike($authorDefault) . '%', 'LIKE')
      ->condition('f.title', '%' . Database::getConnection()
        ->escapeLike($contentTypeDefault) . '%', 'LIKE')
      ->condition('i.link', 'https://' . Database::getConnection()
        ->escapeLike($feedTypeDefault) . '%', 'LIKE')
      ->condition('i.title', '%' . Database::getConnection()
        ->escapeLike($titleDefault) . '%', 'LIKE');

    // Add the pager and table sort extenders.
    $query = $query->extend(PagerSelectExtender::class)->limit($limit);
    $query = $query->extend(TableSortExtender::class)->orderByHeader($header);

    // Execute the query and fetch the results.
    $results = $query->execute()->fetchAll();

    // Inform the user about selection.
    $form['from'] = [
      '#type' => 'item',
      '#markup' => '<p>Select the items you would like to import.</p>',
    ];

    $options = [];
    $checkedArray = [];

    // Process the results and build the options array.
    foreach ($results as $key => $record) {
      $chunks = explode('://', $record->link);
      $url = explode('/', $chunks[1]);

      $id = $record->iid;
      $title = $record->item_title;
      $link = $record->link;
      $guid = $record->guid;
      // Default type.
      $type = 'Article';
      $feedType = $record->ftitle;
      $feed = $url[0];
      $author = $record->author;
      $timestamp = $record->timestamp;
      $checked = $record->checked;
      $modified = $record->modified;

      // Determine content type based on the feed title.
      if (strpos($feedType, 'Article') !== FALSE) {
        $type = 'Article';
      }
      elseif (strpos($feedType, 'Event') !== FALSE) {
        $type = 'Events';
      }
      elseif (strpos($feedType, 'Person') !== FALSE) {
        $type = 'Person';
      }
      elseif (strpos($feedType, 'Book') !== FALSE) {
        $type = 'Book';
      }

      // Check if the node is already in the shared_content_items table.
      $orig_info = explode(' at ', $guid);
      // Check if entry already exists in the shared_content_items table.
      $check_query = Database::getConnection()
        ->select('shared_content_items', 's')
        ->fields('s', ['url', 'orig_nid', 'nid'])
        ->condition('s.orig_nid', $orig_info[0])
        ->condition('s.url', $orig_info[1]);
      $guidURL = explode(' at ', $guid);
      $sharedURL = $guidURL[1] . '/xml/' . $type . '/' . $guidURL[0] . '/rss.xml';

      // Execute the check query.
      $importedNID = $check_query->execute()->fetchAssoc();

      // Determine if the item is already imported and handle display.
      if ($importedNID != '') {
        $checkedArray[] = $id;
        $importedoptions = ['absolute' => TRUE];
        $importedlink = url('node/' . $importedNID['nid'], $importedoptions);
        $suffix = ' <span class="marker">(<a href="' . $importedlink . '" target="_parent">view imported</a>)</span>';
        $disableClass = 'shared';
      }
      else {
        $suffix = '';
        $disableClass = '';
      }

      // Build the options array for tableselect.
      $options[$id] = [
        'title' => $title,
        'type' => $type,
        'feed' => $feed,
        'author' => $author,
        'created' => date('F j, Y, g:i a', $timestamp),
        'checked' => date('F j, Y, g:i a', $checked),
        'modified' => date('F j, Y, g:i a', $modified),
        '#attributes' => ['class' => [$disableClass]],
      ];

      // Add hidden fields to the form.
      $form['nodesExtra']['guid_' . $id] = [
        '#type' => 'hidden',
        '#title' => 'guid',
        '#value' => $guid,
      ];
      $form['nodesTitle']['title_' . $id] = [
        '#type' => 'hidden',
        '#title' => 'title',
        '#value' => $title,
      ];
      $form['nodesType']['type_' . $id] = [
        '#type' => 'hidden',
        '#title' => 'type',
        '#value' => strtolower($type),
      ];
    }

    // Add the tableselect element to display the feed items.
    $form['nodes'] = [
      '#type' => 'tableselect',
      '#header' => $header,
      '#options' => $options,
      '#empty' => t('No content available.'),
    ];

    // Add the pager.
    $form['pager'] = [
      '#type' => 'pager',
    ];

    // Add a submit button.
    $form['submit_button'] = [
      '#type' => 'submit',
      '#value' => t('Import'),
    ];

    return $form;
  }

/**
 * Submit handler for updating all aggregator feeds and refreshing imported nodes.
 */
public function updateAllFeeds(array &$form, FormStateInterface $form_state) {
  // Queue all feeds that need refreshing.
  $this->queueFeedsForRefresh();

  // Refresh queued feeds.
  $this->refreshQueuedFeeds();

  // Update all imported shared content nodes.
  $this->updateAllImportedNodes();

  // Clean up old queued feeds if they are not updated within the specified time frame.
  $this->cleanupOldQueuedFeeds();

  // Notify the user.
  $this->messenger()
    ->addMessage($this->t('All aggregator feeds and imported shared content nodes have been updated.'));
}


  /**
   * Queues all feeds that need to be refreshed.
   */
  protected function queueFeedsForRefresh() {
    $queue = \Drupal::service('queue')->get('aggregator_feeds');
    $feed_storage = \Drupal::entityTypeManager()->getStorage('aggregator_feed');
    $time_service = \Drupal::service('datetime.time');
    $ids = $feed_storage->getQuery()
      ->accessCheck(FALSE)
      ->condition('status', 1)
      ->execute();  

    foreach (Feed::loadMultiple($ids) as $feed) {
      if ($queue->createItem($feed->id())) {
        // Add timestamp to avoid queueing the item more than once.
        $feed->setQueuedTime($time_service->getRequestTime());
        $feed->save();
      }
    }
  }

  /**
   * Refreshes all queued feeds.
   */
  protected function refreshQueuedFeeds() {
    $queue = \Drupal::service('queue')->get('aggregator_feeds');

    // Process each feed in the queue.
    while ($item = $queue->claimItem()) {
      $feed = Feed::load($item->data);
      if ($feed) {
        $feed->refreshItems();
        $queue->deleteItem($item);
      }
    }
  }

  /**
   * Cleans up feeds that have been queued but not refreshed for a long time.
   */
  protected function cleanupOldQueuedFeeds() {
    $time_service = \Drupal::service('datetime.time');
    $feed_storage = \Drupal::entityTypeManager()->getStorage('aggregator_feed');

    $threshold_time = $time_service->getRequestTime() - (3600 * 6);
    $ids = \Drupal::entityQuery('aggregator_feed')
      ->accessCheck(FALSE)
      ->condition('queued', $threshold_time, '<')
      ->execute();

    if ($ids) {
      $feeds = Feed::loadMultiple($ids);
      foreach ($feeds as $feed) {
        // Reset the queued timestamp.
        $feed->setQueuedTime(0);
        $feed->save();
      }
    }
  }

  /**
   * Submit form.
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $selected_items = array_filter($form_state->getValue('nodes'));

    if (!empty($selected_items)) {
      global $user;
      // $config = parse_ini_file('config.ini');
      $counter = 0;

      foreach ($selected_items as $n) {
        $guid = $form_state->getValue('guid_' . $n);
        $title = $form_state->getValue('title_' . $n);
        $type = $form_state->getValue('type_' . $n);

        // Adjust the type for 'person' to 'faculty_staff'.
        if ($type === 'person') {
          $type = 'faculty_staff';
        }

        $guidURL = explode(' at ', $guid);
        $sharedURL = $guidURL[1] . '/xml/' . $type . '/' . $guidURL[0] . '/rss.xml';

        // Check if entry already exists in the shared_content_items table.
        $existing = Database::getConnection()
          ->select('shared_content_items', 's')
          ->fields('s', ['url', 'orig_nid', 'nid'])
          ->condition('s.orig_nid', $guidURL[0])
          ->condition('s.url', $guidURL[1])
          ->execute()
          ->fetchAssoc();

        if (!$existing) {

          // Create and save new node.
          $node_values = [
            'type' => $type,
            'status' => 1,
          ];
          $entity = \Drupal::entityTypeManager()
            ->getStorage('node')
            ->create($node_values);
          // Grab the xml for the node.
          $xml = shared_content_get_XML($sharedURL);
          if (is_object($xml)) {
            $xml_string = $xml->asXML();
            // $shared_content->set(base64_encode($xml_string));
            $test = base64_encode($xml_string);
          }
          $path = $sharedURL;
          $xml = shared_content_get_XML($path);
          $xml_string = $xml->asXML();
          $test = base64_encode($xml_string);

          // Now you can work with the saved entity.
          $cuid = $entity->id();
          $node = Node::create([
            'title' => $title,
            'field_shared_content_xml' => $sharedURL,
            'field_shared_content' => $test,
            'type' => $type,
          ]);
          // Grab the XML content for the node.
          $path = $sharedURL;
          $xml = shared_content_get_XML($path);

          if ($type == 'article') {
            $postDate = $xml->node->postDate;
            list($month, $day, $year) = explode('.', $postDate);
            $createdDate = mktime(0, 0, 0, $month, $day, $year);
            $node->set('created', $createdDate);
          }

          // Set fields based on content type.
          $node->set('field_shared_content', base64_encode($xml->asXML()));

          if ($type == 'events') {
            $dateField = $xml->node->eventDate;
            $dates = explode(',', $dateField);

            foreach ($dates as $date) {
              // Check if the date contains a range.
              if (strpos($date, ' to ') !== FALSE) {
                [$dateStart, $dateEnd] = explode(' to ', $date);
              }
              else {
                $dateStart = $dateEnd = $date;
              }

              // Convert the date to a proper format that SmartDate expects.
              $dateStart = str_replace(' ', 'T', trim($dateStart));
              $dateEnd = str_replace(' ', 'T', trim($dateEnd));

              // Validate and parse date strings to ensure they are in a valid format.
              $dateStartTimestamp = strtotime($dateStart);
              $dateEndTimestamp = strtotime($dateEnd);

              if ($dateStartTimestamp === FALSE || $dateEndTimestamp === FALSE) {
                // Log or handle invalid date format error.
                \Drupal::logger('shared_content')
                  ->error('Invalid date format detected: Start: @start, End: @end', [
                    '@start' => $dateStart,
                    '@end' => $dateEnd,
                  ]);
                continue;
              }

              $node->set('field_event_smart_date', [
                'value' => date('Y-m-d\TH:i:s', $dateStartTimestamp),
                'end_value' => date('Y-m-d\TH:i:s', $dateEndTimestamp),
              ]);
            }
          }
          elseif ($type == 'faculty_staff') {
            if ($xml->node->drupalVersion) {
            $firstName = (string) $xml->node->firstName;
            $lastName = (string) $xml->node->lastName;
            $title = (string) $xml->node->title;
            } else {
              $firstName = (string) $xml->node->firstName;
              $lastName = (string) $xml->node->lastName;
              $title = (string) $xml->node->title;
            }

            $title = $firstName . ' ' . $lastName;
            $node->set('field_first_name', $firstName);
            $node->set('title', $title);
            $node->set('field_last_name', $lastName);
          }
          elseif ($type == 'article') {
            $postDate = $xml->node->postDate;
            list($month, $day, $year) = explode('.', $postDate);
            $createdDate = mktime(0, 0, 0, $month, $day, $year);
            $node->set('created', $createdDate);
          }
          $node->save();


          // Get the node ID
          $node_id = $node->id();

          // Create a URL and link for the new node.
          $url = Url::fromRoute('entity.node.canonical', ['node' => $node_id], ['absolute' => TRUE]);
          $link = Link::fromTextAndUrl($node->getTitle(), $url)->toString();

          // Display the linked message.
          \Drupal::messenger()
            ->addMessage($this->t('Processed content: @link', ['@link' => $link]));

          // Process shared content and date fields
          // Watchdog log.
          \Drupal::logger('shared_content')
            ->info('Processed content: @title', ['@title' => $title]);

          // Increment counter.
          $counter++;
        }
        else {
          $this->updateImportedNodeIfChanged($existing['field_shared_content_xml'], $sharedURL);
        }
      }

      // Feedback message.
      if ($counter > 0) {
        \Drupal::messenger()
          ->addMessage($this->t('@count items were successfully imported.', ['@count' => $counter]));
      }
      else {
        \Drupal::messenger()->addMessage($this->t('No items were imported.'));
      }
    }
    else {
      \Drupal::messenger()
        ->addMessage($this->t('No items were selected for import.'), 'warning');
    }
  }
/**
 * Check and update shared content field if the imported node has changed.
 *
 * @param int $nid
 *   The node ID of the imported item.
 * @param string $shared_url
 *   The URL of the original content's XML feed.
 */
private function updateImportedNodeIfChanged($nid, $shared_url) {
  // Load the existing node.
  $node = Node::load($nid);

  if (!$node) {
    return;
  }

  // Get the current XML stored in field_shared_content.
  $current_xml = base64_decode($node->get('field_shared_content')->value);

  // Fetch the latest XML from the feed.
  $latest_xml_object = $this->shared_content_get_XML($shared_url);

  if (!$latest_xml_object) {
    \Drupal::logger('shared_content')->error('Failed to fetch XML from @url', ['@url' => $shared_url]);
    return;
  }

  $latest_xml = $latest_xml_object->asXML();

  // Compare existing and new XML.
  if (strcmp(trim($current_xml), trim($latest_xml)) !== 0) {
    // Update the field with new XML data.
    $node->set('field_shared_content', base64_encode($latest_xml));

    // If needed, update additional fields based on content type.
    if ($node->bundle() === 'events') {
      $eventDate = $latest_xml_object->node->eventDate;
      $dates = explode(',', $eventDate);
      $dateStart = trim($dates[0]);

      // Convert date to timestamp.
      $timestamp = strtotime($dateStart);
      if ($timestamp) {
        $node->set('field_event_smart_date', [
          'value' => date('Y-m-d\TH:i:s', $timestamp),
        ]);
      }
    }

    // Save the updated node.
    $node->save();

    \Drupal::logger('shared_content')->info('Updated shared content for node @nid from @url', [
      '@nid' => $nid,
      '@url' => $shared_url,
    ]);
  }
}

/**
 * Update all imported shared content nodes with the latest XML.
 */
private function updateAllImportedNodes() {
  $database = \Drupal::database();
  
  // Fetch all imported shared content items.
  $query = $database->select('shared_content_items', 's')
    ->fields('s', ['nid', 'url', 'orig_nid'])
    ->execute();

  foreach ($query as $record) {
    $nid = $record->nid;
    $shared_url = $record->url . '/xml/' . $record->type . '/' . $record->orig_nid . '/rss.xml';

    // Check and update node if the XML has changed.
    $this->updateImportedNodeIfChanged($nid, $shared_url);
  }
}

  /**
   *
   */
  private function getContentItems($limit) {
    // Default headers for sorting.
    $header = [
      'title' => ['data' => $this->t('Title'), 'field' => 'i.title'],
      'type' => ['data' => $this->t('Type'), 'field' => 'f.fid'],
      'feed' => ['data' => $this->t('Feed Source'), 'field' => 'i.link'],
      'link' => ['data' => $this->t('Feed Source'), 'field' => 'i.link'],
      'guid' => ['data' => $this->t('GUID Source'), 'field' => 'i.guid'],
      'author' => ['data' => $this->t('Author'), 'field' => 'i.author'],
      'created' => [
        'data' => $this->t('Date Created'),
        'field' => 'i.timestamp',
      ],
      'checked' => ['data' => $this->t('Last Checked'), 'field' => 'f.checked'],
      'modified' => [
        'data' => $this->t('Last Modified'),
        'field' => 'f.modified',
        'sort' => 'desc',
      ],
    ];

    // Initialize the query object correctly.
    $query = Database::getConnection()->select('aggregator_item', 'i')
      ->extend('Drupal\Core\Database\Query\TableSortExtender')
      ->extend('Drupal\Core\Database\Query\PagerSelectExtender');

    // Left join on aggregator_feed table.
    $query->leftJoin('aggregator_feed', 'f', 'i.fid = f.fid');

    // Add fields to select from both tables.
    $query->fields('i', [
      'iid',
      'title',
      'link',
      'fid',
      'guid',
      'author',
      'timestamp',
    ]);
    $query->fields('f', ['fid', 'title', 'url', 'checked', 'modified']);

    // Manually handle sorting using orderBy based on the header information.
    foreach ($header as $key => $value) {
      $query->orderBy($value['field'], $value['sort'] ?? 'asc');
    }

    // Apply pagination limit to the query.
    $query->limit($limit);

    // Execute the query and fetch results.
    $results = $query->execute()->fetchAll();

    // Process results to be used in tableselect.
    $options = [];
    foreach ($results as $record) {
      // Extract feed source.
      $feed_source = explode('/', parse_url($record->link, PHP_URL_HOST))[0];
      $guid = $record->guid;
      $linked_title = $record->link;
      $title = $record->title;
      $test = $record->f_title;
      $shared_type = explode(' ', trim($test, ' '));
      $last_segment = end($shared_type);
      $type = $this->getContentType($last_segment);
      $shared_type = $this->getFeedType($last_segment);
      $shared_data = explode(' at ', trim($guid, ' at '));
      $nid = $shared_data[0];
      $shared_link = end($shared_data);

      // Create the URL object for an external link.
      $title_url = Url::fromUri($linked_title);

      // Create the link object.
      $title_link = Link::fromTextAndUrl($title, $title_url)->toString();
      // Construct the new URL based on the extracted values.
      $new_url = $shared_link . '/xml/' . $shared_type . '/' . $nid . '/rss.xml';
      $checked = $record->checked ? date('F j, Y, g:i a', $record->checked) : $this->t('Never');
      $modified = $record->modified ? date('F j, Y, g:i a', $record->modified) : $this->t('Never');
      $options[$record->iid] = [
        'title' => $title,
        'linked_title' => $linked_title,
        'type' => $type,
        'feed' => $feed_source,
        'author' => $record->author,
        'created' => date('F j, Y', $record->timestamp),
        'link' => $record->link,
        'guid' => $record->guid,
      // Add the extracted last part of the URL.
        'last_segment' => $new_url,
      // Add the formatted last checked value.
        'checked' => $checked,
      // Add the formatted last modified value.
        'modified' => $modified,
      ];
    }

    return $options;
  }

  /**
   * Get the feed type value.
   */
  private function getFeedType($title) {
    if (strpos($title, 'Articles') !== FALSE) {
      return 'article';
    }
    elseif (strpos($title, 'Events') !== FALSE) {
      return 'events';
    }
    elseif (strpos($title, 'Person') !== FALSE) {
      return 'faculty_staff';
    }
    elseif (strpos($title, 'Books') !== FALSE) {
      return 'book';
    }
    return 'Unknown';
  }

  /**
   * Get the Content Type value.
   */
  private function getContentType($title) {
    if (strpos($title, 'Articles') !== FALSE) {
      return 'Article';
    }
    elseif (strpos($title, 'Events') !== FALSE) {
      return 'Event';
    }
    elseif (strpos($title, 'Person') !== FALSE) {
      return 'Person';
    }
    elseif (strpos($title, 'Books') !== FALSE) {
      return 'Book';
    }
    return 'Unknown';
  }


  /**
   * Preprocess xml conversion.
   */
  public function shared_content_get_XML($path) {
    // Grab the xml for the node.
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $path);
    // If (strpos($_SERVER['HTTP_HOST'], 'wustl.edu') === false) {.
    $proxy = NULL;
    // } else {
    //     $proxy = 'http://chevron.artsci.washu.edu:8080';
    // }
    curl_setopt($ch, CURLOPT_PROXY, $proxy);
    curl_setopt($ch, CURLOPT_FAILONERROR, 1);

    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    $returned = curl_exec($ch);
    curl_close($ch);
    $xml = simplexml_load_string($returned);
    return $xml;
  }

}
