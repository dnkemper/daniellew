<?php

namespace Drupal\olympian_migration\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Link;
use Drupal\Core\Routing\RouteProviderInterface;
use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Returns responses for paragraphs_usage routes.
 */
class ReportParagraphsUsageController extends ControllerBase {

  /**
   * Route provider.
   *
   * @var \Drupal\Core\Routing\RouteProviderInterface
   */
  private $routeProvider;

  /**
   * Constructs a new \Drupal\views_ui\Controller\ViewsUIController object.
   *
   * @param \Drupal\Core\Routing\RouteProviderInterface $provider
   *   The Views data cache object.
   */
  public function __construct(RouteProviderInterface $provider) {
    $this->routeProvider = $provider;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('router.route_provider')
    );
  }

  /**
   * Builds the response.
   */
  public function build() {

    $paragraphs_types = $this->entityTypeManager()->getStorage('paragraphs_type')->loadMultiple();

    $ref_fields = $this->entityTypeManager()->getStorage('field_storage_config')->loadByProperties(
      [
        'settings' => [
          'target_type' => 'paragraph',
        ],
        'type' => 'entity_reference_revisions',
        'deleted' => FALSE,
        'status' => 1,
      ]
    );

    $datas = [];

    $field_map = \Drupal::service("entity_field.manager")->getFieldMapByFieldType("entity_reference_revisions");

    // Prepare datas.
    foreach ($ref_fields as $field_storage_id => $config) {
      [$entity_type, $field_name] = explode('.', $field_storage_id);
      $bundles = $field_map[$entity_type][$field_name]['bundles'];
      foreach ($bundles as $bundle) {
        /** @var \Drupal\field\Entity\FieldConfig $fieldConfig */
        $fieldConfig = \Drupal::entityTypeManager()
          ->getStorage('field_config')
          ->load($entity_type . '.' . $bundle . '.' . $field_name);
        $handler_settings = $fieldConfig->getSetting('handler_settings');
        $targets = $handler_settings['target_bundles'];
        foreach ($targets as $parag_type) {
          $datas[$parag_type][$entity_type][$bundle][] = $field_name;
        }
      }
    }

    $new_data = $datas;
    // Calculate sizes.
    foreach ($new_data as $parag_type => $entity_types) {
      $parag_size = 0;
      foreach ($entity_types as $entity_type => $bundles) {
        $entity_size = 0;
        foreach ($bundles as $bundle => $fields) {
          $new_data[$parag_type][$entity_type][$bundle] = [];
          $new_data[$parag_type][$entity_type][$bundle]['size'] = count($fields);
          $new_data[$parag_type][$entity_type][$bundle]['data'] = $fields;
          $entity_size += count($fields);
        }
        $entity_data = $new_data[$parag_type][$entity_type];
        $new_data[$parag_type][$entity_type] = [];
        $new_data[$parag_type][$entity_type]['data'] = $entity_data;
        $new_data[$parag_type][$entity_type]['size'] = $entity_size;
        $parag_size += $entity_size;
      }
      $parag_data = $new_data[$parag_type];
      $new_data[$parag_type] = [];
      $new_data[$parag_type]['data'] = $parag_data;
      $new_data[$parag_type]['size'] = $parag_size;
    }

    // Generate rows.
    $rows = [];
    foreach ($paragraphs_types as $parag_type => $parag) {
      $parag_label = $paragraphs_types[$parag_type]->label();
      if (!isset($new_data[$parag_type])) {
        // If paragraphe not attached at any entity, create empty line.
        $rows[] = [
          [
            'data' => $parag_label . ' (' . $parag_type . ')',
          ],
          '',
          '',
          '',
          '',
        ];
        continue;
      }
      $entity_types = $new_data[$parag_type];
      $row[] = [
        'data' => $parag_label . ' (' . $parag_type . ')',
        'rowspan' => $entity_types['size'] ?? 1,
      ];
      foreach ($entity_types['data'] as $entity_type => $bundles) {
        $row[] = ['data' => $entity_type, 'rowspan' => $bundles['size']];
        foreach ($bundles['data'] as $bundle => $fields) {
          $bundle_label = $this->getBundleLabel($entity_type, $bundle);
          $row[] = [
            'data' => $bundle_label . ' (' . $bundle . ')',
            'rowspan' => $fields['size'],
          ];
          foreach ($fields['data'] as $field) {
            $url = $this->makeFieldUrl($entity_type, $bundle, $field);
            $mange_field_link = Link::fromTextAndUrl($field, $url)->toString();
            $row[] = ['data' => $mange_field_link];
            $row[] = ['data' => $field]; // Adding the field name without a link.
            $rows[] = $row;
            $row = [];
          }
        }
      }
    }

    $header = [
      t('Paragraph Type'),
      t('Used in (entity_type)'),
      t('Used in (bundle)'),
      t('Used in (field_name) with link'),
      t('Used in (field_name) without link'), // Add a header for the new column
    ];

    $output = [
      '#type' => 'table',
      '#header' => $header,
      '#rows' => $rows,
      '#sticky' => TRUE,
      '#empty' => t('No paragraphs have been used in entities yet.'),
    ];

    return $output;

  }

  /**
   * Get bundle labels.
   *
   * @param string $entity_type
   *   Entity type id.
   * @param string $bundle
   *   Bundle machine name.
   *
   * @return string
   *   Bundle Label.
   */
  private function getBundleLabel(string $entity_type, string $bundle) {
    static $memory = [];
    if (!isset($memory[$entity_type][$bundle])) {

      $list = \Drupal::service('entity_type.bundle.info')->getBundleInfo($entity_type);
      foreach ($list as $bundle_key => $bundle_data) {
        $memory[$entity_type][$bundle_key] = $bundle_data['label'];
      }
    }
    return $memory[$entity_type][$bundle];
  }

  /**
   * Generate URL to field configuration.
   *
   * @param string $entity_type
   *   Entity Type.
   * @param string $bundle
   *   Entity Bundle.
   * @param string $field_name
   *   Field name.
   *
   * @return \Drupal\Core\Url
   *   Url object.
   */
  private function makeFieldUrl(string $entity_type, string $bundle, string $field_name) {

    // Reduice calling route provider.
    static $memory_bundle_identifier = [];

    // Create route name for fieldUI.
    $route = 'entity.field_config.' . $entity_type . '_field_edit_form';

    if (!isset($memory_bundle_identifier[$entity_type])) {
      // Get route definition.
      $route_definition = $this->routeProvider->getRouteByName($route);
      // Get parameters.
      $parameters = $route_definition->getOption('parameters');
      // By convention, the first parameter of route are the bundle identifier.
      $memory_bundle_identifier[$entity_type] = array_keys($parameters)[0];
    }

    // Create URL.
    $url = Url::fromRoute('entity.field_config.' . $entity_type . '_field_edit_form',
      [
        $memory_bundle_identifier[$entity_type] => $bundle,
        'field_config' => $entity_type . '.' . $bundle . '.' . $field_name,
      ],
      ['attributes' => ['target' => '_blank']]
    );

    return $url;
  }

}
