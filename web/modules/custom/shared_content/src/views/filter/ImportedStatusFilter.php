<?php

namespace Drupal\shared_content\Plugin\views\filter;

use Drupal\views\Plugin\views\filter\InOperator;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Database\Database;

/**
 * Filters aggregator items based on whether they are already imported.
 *
 * @ViewsFilter("imported_status_filter")
 */
class ImportedStatusFilter extends InOperator {

  /**
   * {@inheritdoc}
   */
  public function query() {
    $this->ensureMyTable();
    
    // Join the node table to check if a node with the shared URL exists.
    $query = Database::getConnection()->select('node_field_data', 'n')
      ->fields('n', ['nid'])
      ->condition('n.field_shared_content_xml', $this->value, 'IN')
      ->range(0, 1);
      
    $subquery = $query->execute()->fetchCol();

    if (!empty($subquery)) {
      $this->query->addWhere('AND', "$this->tableAlias.$this->realField", $subquery, 'NOT IN');
    }
  }

  /**
   * {@inheritdoc}
   */
  protected function defineOptions() {
    $options = parent::defineOptions();
    $options['value'] = ['default' => 0];
    return $options;
  }

  /**
   * {@inheritdoc}
   */
  public function buildOptionsForm(&$form, FormStateInterface $form_state) {
    parent::buildOptionsForm($form, $form_state);
    $form['value']['#title'] = $this->t('Exclude already imported items');
  }
}
