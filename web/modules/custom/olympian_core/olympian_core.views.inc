<?php

/**
 * @file
 * Views hooks.
 */

use Drupal\olympian_core\DatePopup;

/**
 * Implements hook_views_plugins_filter_alter().
 */
function olympian_core_popup_views_plugins_filter_alter(&$info) {
  $info['date']['class'] = DatePopup::class;
}

/**
 * Implements hook_views_data().
 */
function olympian_core_views_data() {
  $data = [];
  $data['media']['media_download_link'] = [
    'title' => t('Link to download media file'),
    'help' => t('Provide a link to download the media file.'),
    'click sortable' => FALSE,
    'field' => [
      'id' => 'media_download_link',
    ],
  ];
  $data['views']['table']['group'] = t('Content revision');
  $data['views']['table']['join'] = [
    // #global is a special flag which allows a table to appear all the time.
    '#global' => [],
  ];

  $data['views']['current_state_views_field'] = [
    'title' => t('Current state'),
    'help' => t('Content moderation content current state.'),
    'field' => [
      'id' => 'current_state_views_field',
    ],
  ];
  return $data;
}
