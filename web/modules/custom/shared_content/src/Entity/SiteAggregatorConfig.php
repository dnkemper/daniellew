<?php

namespace Drupal\shared_content\Entity;

use Drupal\Core\Config\Entity\ConfigEntityBase;

/**
 * Defines the Site Aggregator Config entity.
 *
 * @ConfigEntityType(
 *   id = "shared_content",
 *   label = @Translation("Site Aggregator Config"),
 *   handlers = {
 *     "list_builder" = "Drupal\Core\Config\Entity\ConfigEntityListBuilder",
 *     "form" = {
 *       "add" = "Drupal\shared_content\Form\SiteAggregatorConfigForm",
 *       "edit" = "Drupal\shared_content\Form\SiteAggregatorConfigForm",
 *       "delete" = "Drupal\shared_content\Form\SiteAggregatorConfigDeleteForm"
 *     }
 *   },
 *   config_prefix = "shared_content",
 *   admin_permission = "administer site aggregator config",
 *   entity_keys = {
 *     "id" = "id",
 *     "label" = "label"
 *   },
 *   config_export = {
 *     "id",
 *     "label",
 *     "site_url",
 *     "feed_url"
 *   },
 *   links = {
 *     "edit-form" =
 *   "/admin/config/site-aggregator-config/{shared_content}/edit",
 *     "delete-form" =
 *   "/admin/config/site-aggregator-config/{shared_content}/delete",
 *   }
 * )
 */
class SiteAggregatorConfig extends ConfigEntityBase {

  /**
   * The Site Aggregator Config ID.
   *
   * @var string
   */
  public $id;

  /**
   * The Site Aggregator Config label.
   *
   * @var string
   */
  public $label;

  /**
   * The site URL.
   *
   * @var string
   */
  public $site_url;

  /**
   * The feed URL.
   *
   * @var string
   */
  public $feed_url;

}
