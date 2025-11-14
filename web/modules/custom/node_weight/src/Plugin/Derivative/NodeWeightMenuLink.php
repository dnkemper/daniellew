<?php

namespace Drupal\node_weight\Plugin\Derivative;

use Drupal\Component\Plugin\Derivative\DeriverBase;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Plugin\Discovery\ContainerDeriverInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Derivative class that provides the menu links for the Node Weights.
 */
class NodeWeightMenuLink extends DeriverBase implements ContainerDeriverInterface {

  use StringTranslationTrait;

  /**
   * {@inheritdoc}
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The module handler.
   *
   * @var \Drupal\Core\Extension\ModuleHandlerInterface
   */
  protected $moduleHandler;

  /**
   * Creates a NodeMenuLink instance.
   *
   * @param int $base_plugin_id
   *   Base plugin id.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The Entity type manager.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $module_handler
   *   The module handler.
   */
  public function __construct($base_plugin_id, EntityTypeManagerInterface $entity_type_manager, ModuleHandlerInterface $module_handler) {
    $this->entityTypeManager = $entity_type_manager;
    $this->moduleHandler = $module_handler;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, $base_plugin_id) {
    return new static(
      $base_plugin_id,
      $container->get('entity_type.manager'),
      $container->get('module_handler')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getDerivativeDefinitions($base_plugin_definition) {
    $links = [];
    $config = \Drupal::config('node_weight.settings');
    $node_type_objects = $config->get('node_weight.checked_node_types') ?: [];

    foreach ($node_type_objects as $nto) {
      $node_type = $this->entityTypeManager->getStorage('node_type')->load($nto);
      if ($node_type) {
        $links[$nto] = [
          'title' => $node_type->label(),
          'route_name' => 'node_weight.order',
          'route_parameters' => ['node_type' => $nto],
          'parent' => 'node_weight.content',
        ];
        if ($this->moduleHandler->moduleExists('admin_toolbar_tools')) {
          $links['entity.node_type.node_weight.' . $node_type->id()] = [
            'title' => $this->t('Manage order'),
            'route_name' => 'node_weight.order',
            'route_parameters' => ['node_type' => $nto],
            'parent' => 'admin_toolbar_tools.extra_links:entity.node_type.edit_form.' . $node_type->id(),
          ];
        }
      }
    }

    foreach ($links as &$link) {
      $link += $base_plugin_definition;
    }

    return $links;
  }

}
