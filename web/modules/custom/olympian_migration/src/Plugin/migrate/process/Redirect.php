<?php

namespace Drupal\olympian_migration\Plugin\migrate\process\d7;

use Drupal\Core\Path\AliasManagerInterface;
use Drupal\migrate\MigrateExecutableInterface;
use Drupal\migrate\ProcessPluginBase;
use Drupal\migrate\Row;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * @MigrateProcessPlugin(
 *   id = "olympian_d7_path_redirect"
 * )
 */
class Redirect extends ProcessPluginBase {

  /**
   * The alias manager service.
   *
   * @var \Drupal\Core\Path\AliasManagerInterface
   */
  protected $aliasManager;

  /**
   * Constructs a PathRedirect object.
   *
   * @param array $configuration
   *   Configuration array.
   * @param string $plugin_id
   *   The plugin ID.
   * @param mixed $plugin_definition
   *   The plugin definition.
   * @param \Drupal\Core\Path\AliasManagerInterface $alias_manager
   *   The alias manager service.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, AliasManagerInterface $alias_manager) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->aliasManager = $alias_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('path.alias_manager')
    );
  }

  /**
   * {@inheritdoc}
   *
   * Transform the field as required for an iFrame field.
   */
  public function transform($value, MigrateExecutableInterface $migrate_executable, Row $row, $destination_property) {
    // Check if the url begins with http.
    if (preg_match('#^http#', $value[0])) {
      // Use it as is.
      $uri = $value[0];
    }
    else {
      // Check if the path has an alias.
      $alias = $this->aliasManager->getAliasByPath('/' . ltrim($value[0], '/'));
      $path = $alias ?: $value[0];
      // Make the link internal.
      $uri = 'internal:/' . ltrim($path, '/');
    }

    // Check if there are options.
    if (!empty($value[1])) {
      // Check if there is a query.
      $options = unserialize($value[1]);
      if (!empty($options['query'])) {
        // Add it to the end of the url.
        $uri .= '?' . http_build_query($options['query']);
      }
      if (!empty($options['fragment'])) {
        $uri .= '#' . $options['fragment'];
      }
    }

    return $uri;
  }

}
