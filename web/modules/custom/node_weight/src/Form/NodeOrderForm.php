<?php

namespace Drupal\node_weight\Form;

use Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException;
use Drupal\Component\Plugin\Exception\PluginNotFoundException;
use Drupal\Core\Config\ConfigFactory;
use Drupal\Core\Entity\EntityStorageException;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\Render\Element;
use Drupal\Core\Url;
use Drupal\node\Entity\Node;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Node Weight order form.
 */
class NodeOrderForm extends ConfigFormBase {

  /**
   * Nodes per batch run.
   */
  const NODES_PER_BATCH_RUN = 25;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The language manager.
   *
   * @var \Drupal\Core\Language\LanguageManagerInterface
   */
  protected $languageManager;

  /**
   * The constructor.
   *
   * @param \Drupal\Core\Config\ConfigFactory $config_factory
   *   The config factory.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\Core\Language\LanguageManagerInterface $language_manager
   *   The language manager.
   */
  public function __construct(
    ConfigFactory $config_factory,
    EntityTypeManagerInterface $entity_type_manager,
    LanguageManagerInterface $language_manager
  ) {
    parent::__construct($config_factory);
    $this->entityTypeManager = $entity_type_manager;
    $this->languageManager = $language_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('config.factory'),
      $container->get('entity_type.manager'),
      $container->get('language_manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'node_order_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, $node_type = NULL) {
    $node_type_entity = NULL;

    if (!empty($node_type)) {
      $node_type_entity = $this->entityTypeManager->getStorage('node_type')->load($node_type);
    }

    if (!$node_type || !$node_type_entity) {
      $output['text'] = ['#plain_text' => $this->t('Not found the content type @type.', ['@type' => $node_type])];
      return $output;
    }

    // Form constructor.
    $form = parent::buildForm($form, $form_state);

    $type_enabled = node_weight_node_type_enabled($node_type);
    if (!$type_enabled) {
      $form = [
        $form['text'] = [
          '#plain_text' => $this->t('Node Weight is disabled for this content type.'),
          '#suffix' => '<br />',
        ],
        $form['actions'] = ['#type' => 'actions'],
        $form['actions']['submit'] = [
          '#type' => 'submit',
          '#value' => $this->t('Enable'),
          '#submit' => ['::submitFormEnableNodeWeight'],
        ],
      ];
    }
    else {
      $language = $this->languageManager->getCurrentLanguage()->getId();

      // Get nodes.
      $nids = $this->entityTypeManager->getStorage('node')->getQuery()
        ->accessCheck(TRUE)
        ->condition('type', $node_type)
        ->condition('status', TRUE)
        ->condition('langcode', $language)
        ->execute();
      $nodes = $this->entityTypeManager->getStorage('node')->loadMultiple($nids);

      /**
       * Sort nodes by weight.
       *
       * @var \Drupal\node\Entity\Node $node
       */
      $sorted_nodes = [];
      foreach ($nodes as $node) {
        // Load proper entity translation.
        if ($node->isTranslatable()) {
          if ($node->hasTranslation($language)) {
            $node = $node->getTranslation($language);
          }
        }
        $weight = 0;
        if ($node->hasField('field_node_weight')) {
          $weight = $node->get('field_node_weight')->value ?: 0;
        }
        if (!array_key_exists($weight, $sorted_nodes)) {
          $sorted_nodes[$weight] = $node;
        }
        else {
          $sorted_nodes[] = $node;
        }
      }
      ksort($sorted_nodes);

      $header = [
        'label' => ['data' => $this->t('Node Title')],
        'language' => ['data' => $this->t('Language')],
        'operations' => ['data' => $this->t('Operations')],
      ];

      $form['ntable'] = [
        '#type' => 'table',
        '#header' => $header,
        '#empty' => $this->t('There are no items yet.'),
        '#tabledrag' => [
          [
            'action' => 'order',
            'relationship' => 'sibling',
            'group' => 'ntable-order-weight',
          ],
        ],
      ];

      /**
       * Build the table rows and columns.
       *
       * @var \Drupal\node\Entity\Node $entity
       */
      foreach ($sorted_nodes as $entity) {
        $id = $entity->id();

        // Load proper entity translation.
        if ($entity->isTranslatable()) {
          if ($entity->hasTranslation($language)) {
            $entity = $entity->getTranslation($language);
          }
        }

        // Get weight.
        $weight = 0;
        if ($entity->hasField('field_node_weight')) {
          $weight = $entity->get('field_node_weight')->value ?: 0;
        }

        // TableDrag: Mark the table row as draggable.
        $form['ntable'][$id]['#attributes']['class'][] = 'draggable';

        $form['ntable'][$id]['label'] = [
          '#type' => 'link',
          '#title' => $entity->label(),
          '#url' => Url::fromRoute('entity.node.canonical', ['node' => $id]),

        ];

        $form['ntable'][$id]['language'] = [
          '#markup' => $entity->language()->getName(),
        ];

        // $form['ntable'][$id]['enabled'] = [
        //   '#type' => 'checkbox',
        //   '#value' => $entity->get('status')->value,
        // ];

        // TableDrag: Weight column element.
        $form['ntable'][$id]['weight'] = [
          '#type' => 'weight',
          '#title' => $this->t('Weight for @title', ['@title' => $entity->label()]),
          '#title_display' => 'invisible',
          '#default_value' => $weight,
          '#delta' => 100,
          // Classify the weight element for #tabledrag.
          '#attributes' => ['class' => ['ntable-order-weight']],
        ];

        // Operations (dropbutton) column.
        $form['ntable'][$id]['operations'] = [
          '#type' => 'operations',
          '#links' => [],
        ];
        $form['ntable'][$id]['operations']['#links']['edit'] = [
          'title' => $this->t('Edit'),
          'url' => Url::fromRoute('entity.node.edit_form', ['node' => $id]),
        ];
        $form['ntable'][$id]['operations']['#links']['delete'] = [
          'title' => $this->t('Delete'),
          'url' => Url::fromRoute('entity.node.delete_form', ['node' => $id]),
        ];
      }

      $form['actions'] = ['#type' => 'actions'];
      $form['actions']['submit'] = [
        '#type' => 'submit',
        '#value' => $this->t('Save changes'),
      ];
    }

    return $form;
  }

  /**
   * Edit Order submission handler.
   *
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    // Get nodes.
    $nodes = [];
    foreach (Element::children($form_state->getUserInput()['ntable']) as $nid) {
      $nodes[$nid]['nid'] = $nid;
      $nodes[$nid]['weight'] = $form_state->getUserInput()['ntable'][$nid]['weight'];
      // $nodes[$nid]['enabled'] = isset($form_state->getUserInput()['ntable'][$nid]['enabled']) ?: 0;
    }

    // Make sure we have nodes to process.
    if (!empty($nodes)) {
      // Setup batch operations.
      $operations = [];
      $current_language = $this->languageManager->getCurrentLanguage()->getId();
      foreach (array_chunk($nodes, $this::NODES_PER_BATCH_RUN) as $batched_nodes) {
        $operations[] = [
          '\Drupal\node_weight\Form\NodeOrderForm::batchProcessNodes',
          [$batched_nodes, $current_language],
        ];
      }

      // Setup batch.
      $batch = [
        'title' => $this->t('Ordering Nodes'),
        'operations' => $operations,
        'finished' => '\Drupal\node_weight\Form\NodeOrderForm::batchFinished',
      ];
      batch_set($batch);
    }
  }

  /**
   * Order nodes batch operation.
   *
   * @param array $nodes
   *   The array of nodes to order on this batch operation.
   * @param string $current_language
   *   The current language code.
   * @param array $context
   *   The batch context.
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public static function batchProcessNodes(array $nodes, string $current_language, array &$context) {
    if (!isset($context['results']['rows'])) {
      $context['results']['rows'] = [];
    }

    $batch_run_processed = 0;
    foreach ($nodes as $node_data) {
      $nid = $node_data['nid'] ?? NULL;
      if (!empty($nid)) {
        if ($node = Node::load($nid)) {
          if ($node->isTranslatable()) {
            if ($node->hasTranslation($current_language)) {
              $node = $node->getTranslation($current_language);
            }
          }

          $save = FALSE;
          if (isset($node_data['weight'])) {
            if ($node->hasField('field_node_weight') && $node->get('field_node_weight')->value != $node_data['weight']) {
              $node->set('field_node_weight', $node_data['weight']);
              $save = TRUE;
            }
          }

          // if (isset($node_data['enabled'])) {
          //   if ($node->get('status')->value != (bool) $node_data['enabled']) {
          //     $node->set('status', (bool) $node_data['enabled']);
          //     $save = TRUE;
          //   }
          // }

          if ($save) {
            $node->setNewRevision(FALSE);
            $node->save();
          }

          $batch_run_processed++;
        }
      }
    }

    // Display data while running batch.
    if (!isset($context['results']['batch_number'])) {
      $context['results']['batch_number'] = 1;
    }
    else {
      $context['results']['batch_number']++;
    }

    $batch_number = $context['results']['batch_number'];
    $context['message'] = sprintf("Ordering %d nodes. Batch #%d", $batch_run_processed, $batch_number);
  }

  /**
   * The batch finished operation.
   *
   * @param bool $success
   *   The success flag.
   * @param array $results
   *   The batch results.
   * @param array $operations
   *   The batch operations.
   */
  public static function batchFinished(bool $success, array $results, array $operations) {
    $messenger = \Drupal::messenger();
    if ($success) {
      $messenger->addMessage(t('Nodes ordered successfully.'));
    }
    else {
      $messenger->addError(t('Failed to order nodes.'));
    }
  }

  /**
   * Enable node weight form submit handler.
   *
   * @param array $form
   *   The form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   */
  public function submitFormEnableNodeWeight(array &$form, FormStateInterface $form_state) {
    $node_type = $this->getRequest()->attributes->get('node_type');
    try {
      node_weight_add_node_type_to_config($node_type);
      node_weight_create_field_node_weight($node_type);
    }
    catch (InvalidPluginDefinitionException | PluginNotFoundException | EntityStorageException $e) {
      $logger = \Drupal::logger('node_weight');
      Error::logException($logger, e);
      }
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return [
      'node_weight.settings',
    ];
  }

}
