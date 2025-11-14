<?php

namespace Drupal\node_weight\Form;

use Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException;
use Drupal\Component\Plugin\Exception\PluginNotFoundException;
use Drupal\Core\Config\ConfigFactory;
use Drupal\Core\Entity\EntityStorageException;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Node Weight configuration form.
 */
class NodeWeightForm extends ConfigFormBase {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The constructor.
   *
   * @param \Drupal\Core\Config\ConfigFactory $config_factory
   *   The config factory.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   */
  public function __construct(
    ConfigFactory $config_factory,
    EntityTypeManagerInterface $entity_type_manager
  ) {
    parent::__construct($config_factory);
    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('config.factory'),
      $container->get('entity_type.manager')
    );
  }

  /**
   * ;   * {@inheritdoc}
   */
  public function getFormId() {
    return 'node_weight_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form = parent::buildForm($form, $form_state);
    $config = $this->config('node_weight.settings');

    // Get available node types.
    $node_types = $this->entityTypeManager->getStorage('node_type')->loadMultiple();
    $options = [];
    foreach ($node_types as $node_type) {
      $options[$node_type->id()] = $node_type->label();
    }

    // Node types checkboxes.
    $form['checked_node_types'] = [
      '#type' => 'checkboxes',
      '#title' => $this->t('Available on'),
      '#description' => $this->t('The node types to toggle node weight on.'),
      '#default_value' => $config->get('node_weight.checked_node_types') ?: [],
      '#options' => $options,
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $config = $this->config('node_weight.settings');
    $config->set('node_weight.checked_node_types', array_filter(array_values($form_state->getValue('checked_node_types'))));
    $config->save();

    foreach ($form_state->getValue('checked_node_types') as $node_type) {
      if ($node_type) {
        try {
          node_weight_create_field_node_weight($node_type);
        }
        catch (InvalidPluginDefinitionException | PluginNotFoundException | EntityStorageException $e) {

          $logger = \Drupal::logger('node_weight');
          Error::logException($logger, e);

        }
      }
      else {
        node_weight_delete_field_node_weight($node_type);
      }
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
