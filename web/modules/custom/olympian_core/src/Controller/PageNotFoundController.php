<?php

namespace Drupal\olympian_core\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\olympian_core\Utility\DescriptionTemplateTrait;

/**
 * Controller routines for page example routes.
 */
class PageNotFoundController extends ControllerBase {

  use DescriptionTemplateTrait;

  /**
   * {@inheritdoc}
   */
  protected function getModuleName() {
    return 'olympian_core';
  }

  /**
   * The module extension list.
   *
   * @var \Drupal\Core\Extension\ModuleExtensionList
   */
  protected $moduleExtensionList;

  /**
   * Generate a render array with our templated content.
   *
   * @return array
   *   A render array.
   */
  public function notfound() {
    $template_path = $this->getDescriptionTemplatePath();
    $template = file_get_contents($template_path);
    $build = [
      'description' => [
        '#type' => 'inline_template',
        '#template' => $template,
        '#context' => $this->getDescriptionVariables(),
      ],
    ];
    return $build;
  }

  /**
   * Variables to act as context to the twig template file.
   *
   * @return array
   *   Associative array that defines context for a template.
   */
  protected function getDescriptionVariables() {
    $variables = [
      'module' => $this->getModuleName(),
    ];
    return $variables;
  }

  /**
   * Get full path to the template.
   *
   * @return string
   *   Path string.
   */
  protected function getDescriptionTemplatePath() {
    return \Drupal::service('extension.list.module')
      ->getPath($this->getModuleName()) . '/templates/description.html.twig';
  }

}
