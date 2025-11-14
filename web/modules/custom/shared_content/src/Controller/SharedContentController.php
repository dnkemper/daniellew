<?php

namespace Drupal\shared_content\Controller;

use Drupal\feeds\Entity\Feed;
use Drupal\Core\Controller\ControllerBase;
use Symfony\Component\HttpFoundation\JsonResponse;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\node\NodeInterface;
use Drupal\shared_content\Service\SharedContentFeedRefresherForce;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Drupal\Core\Database\Connection;
use Drupal\Core\Url;

/**
 * Returns responses for shared_content routes.
 */
class SharedContentController extends ControllerBase {

  /**
   * The logger factory.
   *
   * @var \Drupal\Core\Logger\LoggerChannelFactoryInterface
   */
  protected $loggerFactory;
  /**
   * The database connection.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected $database;
  /**
   * The shared content feed refresher service.
   *
   * @var \Drupal\shared_content\Service\SharedContentFeedRefresherForce
   */
  protected $feedRefresher;

  /**
   * Constructs a SharedContentController object.
   *
   * @param \Drupal\Core\Logger\LoggerChannelFactoryInterface $logger_factory
   *   The logger factory.
   * @param \Drupal\shared_content\Service\SharedContentFeedRefresherForce $feed_refresher
   *   The feed refresher service.
   */
  public function __construct(
    LoggerChannelFactoryInterface $logger_factory,
    SharedContentFeedRefresherForce $feed_refresher,
    Connection $database,
  ) {
    $this->loggerFactory = $logger_factory;
    $this->feedRefresher = $feed_refresher;
    $this->database = $database;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('logger.factory'),
      $container->get('shared_content.feed_refresher'),
      $container->get('database'),
    );
  }

  /**
   * Display aggregator items view.
   *
   * @return array
   *   Render array for the view.
   */
  public function display() {
    // Load and render the view programmatically.
    $view = \Drupal::service('entity_type.manager')
      ->getStorage('view')
      ->load('aggregator_item');

    if (!$view) {
      return [
        '#markup' => $this->t('View not found.'),
      ];
    }

    $view->setDisplay('page_1');
    $view->preExecute();
    $view->execute();

    return $view->render();
  }

  public function triggerRefresh($remote, $content_type) {
    // Load feeds based on the custom field and content type.
    $query = \Drupal::entityQuery('feeds_feed')
      ->condition('field_feed_remote', $remote)
      ->condition('field_feed_content_type', $content_type)
      ->accessCheck(FALSE);

    $feed_ids = $query->execute();

    if (!empty($feed_ids)) {
      $feeds = \Drupal::entityTypeManager()->getStorage('feeds_feed')->loadMultiple($feed_ids);
      foreach ($feeds as $feed) {
        // Borrowed from FeedScheduleImportForm
        /** @var Feed $feed */
        if(!$feed->isLocked()) {
          $feed->startCronImport();
        }

        $args = [
          '@type'  => $feed->getType()->label(),
          '%title' => $feed->label(),
          '%remote' => $remote,
        ];
        $this->logger->notice('%remote queued import for %title of type @type.', $args);
      }
      return new JsonResponse(['status' => 'success']);
    } else {
      $args = [
        '%title' => $content_type,
        '%remote' => $remote,
      ];
      $this->logger->warning('%remote tried to queue import for %title but could not find feed.', $args);
    }
    return new JsonResponse(['status' => 'error']);
  }
  /**
   * Force update a single node from its XML source.
   *
   * @param \Drupal\node\NodeInterface $node
   *   The node to update.
   *
   * @return \Symfony\Component\HttpFoundation\RedirectResponse
   *   Redirect back to the node.
   */
  public function forceUpdate(NodeInterface $node) {
    $logger = $this->loggerFactory->get('shared_content');

    // Check if node has shared content XML field
    if (!$node->hasField('field_shared_content_xml') || $node->get('field_shared_content_xml')->isEmpty()) {
      $this->messenger()->addError($this->t('This node does not have a shared content XML source.'));
      return $this->redirect('entity.node.canonical', ['node' => $node->id()]);
    }

    try {
      // Clear the hash to force update
      if ($node->hasField('field_content_hash')) {
        $node->set('field_content_hash', NULL);
        $node->save();
      }

      // Process the node
      $this->feedRefresher->processNode($node->id());

      $this->messenger()->addStatus($this->t('Node @nid has been force updated from its XML source.', [
        '@nid' => $node->id(),
      ]));

      $logger->info('Force updated node @nid via direct URL.', [
        '@nid' => $node->id(),
      ]);
    }
    catch (\Exception $e) {
      $this->messenger()->addError($this->t('Error updating node: @message', [
        '@message' => $e->getMessage(),
      ]));

      $logger->error('Force update failed for node @nid: @message', [
        '@nid' => $node->id(),
        '@message' => $e->getMessage(),
      ]);
    }

    // Redirect back to the node
    return $this->redirect('entity.node.canonical', ['node' => $node->id()]);
  }
  /**
   * Displays the shared content admin page.
   *
   * @return array
   *   A render array.
   */
  public function adminPage() {
    $build = [];

    // Check if Aggregator is available.
    if (!$this->aggregatorTablesExist()) {
      // Show alternative import method.
      $build['no_aggregator'] = [
        '#type' => 'container',
        '#attributes' => ['class' => ['messages', 'messages--warning']],
      ];

      $build['no_aggregator']['message'] = [
        '#markup' => '<h3>' . $this->t('Aggregator Not Available') . '</h3>' .
          '<p>' . $this->t('The Aggregator module is not installed or configured. You can still import content using direct RSS URLs.') . '</p>',
      ];

      $build['no_aggregator']['link'] = [
        '#type' => 'link',
        '#title' => $this->t('Import from RSS URL →'),
        '#url' => Url::fromRoute('shared_content.direct_import'),
        '#attributes' => [
          'class' => ['button', 'button--primary'],
        ],
      ];

      $build['setup_info'] = [
        '#type' => 'details',
        '#title' => $this->t('Set up Aggregator (Optional)'),
        '#open' => FALSE,
      ];

      $build['setup_info']['content'] = [
        '#markup' => '<p>' . $this->t('To use the Aggregator-based import:') . '</p>' .
          '<ol>' .
          '<li>' . $this->t('Check if Aggregator is available: <code>drush pm:list | grep aggregator</code>') . '</li>' .
          '<li>' . $this->t('If not available in Drupal 10 core, you may need a contrib alternative') . '</li>' .
          '<li>' . $this->t('See <a href="@url">DRUPAL_10_AGGREGATOR_FIX.md</a> for details', [
            '@url' => 'https://github.com/your-repo/shared_content/blob/main/DRUPAL_10_AGGREGATOR_FIX.md',
          ]) . '</li>' .
          '</ol>' .
          '<p><strong>' . $this->t('Direct RSS import works without Aggregator!') . '</strong></p>',
      ];

      return $build;
    }

    // Aggregator is available - show standard forms.
    $build['update_form'] = $this->formBuilder()->getForm(
      'Drupal\shared_content\Form\SharedContentUpdateForm'
    );

    $build['filter_form'] = $this->formBuilder()->getForm(
      'Drupal\shared_content\Form\SharedContentFilterForm'
    );

    $build['import_form'] = $this->formBuilder()->getForm(
      'Drupal\shared_content\Form\SharedContentImportForm'
    );

    // Add link to direct import as alternative.
    $build['direct_import_link'] = [
      '#type' => 'container',
      '#attributes' => ['class' => ['direct-import-link']],
      '#weight' => -10,
    ];

    $build['direct_import_link']['link'] = [
      '#type' => 'link',
      '#title' => $this->t('Or import directly from RSS URL →'),
      '#url' => Url::fromRoute('shared_content.direct_import'),
      '#attributes' => ['class' => ['button']],
    ];

    return $build;
  }
  /**
   * Checks if Aggregator module tables exist.
   */
  protected function aggregatorTablesExist() {
    try {
      return $this->database->schema()->tableExists('aggregator_feed') &&
             $this->database->schema()->tableExists('aggregator_item');
    }
    catch (\Exception $e) {
      return FALSE;
    }
  }
}
