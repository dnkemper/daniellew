<?php

/**
 * @file
 * Enables modules and site configuration for a artsci site installation.
 */

use Drupal\contact\Entity\ContactForm;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;

/**
 * Implements hook_form_FORM_ID_alter() for install_configure_form().
 *
 * Allows the profile to alter the site configuration form.
 */
function artsci_form_install_configure_form_alter(&$form, FormStateInterface $form_state) {
  $form['site_information']['site_name']['#default_value'] = 'Arts & Sciences Web theme';

}
/**
 * Implements hook_toolbar().
 */
function artsci_toolbar() {
  $url = Url::fromUri('https://it.artsci.wustl.edu/website-guide');
  $logout = Url::fromRoute('user.logout')->toString();
  $items = [];
  $items['support'] = [
    '#type' => 'toolbar_item',
    'tab' => [
      '#type' => 'link',
      '#url' => $url,
      '#title' => t('@version help', [
        '@version' => 'A&S website',
      ]),
      '#options' => [
        'attributes' => [
          'title' => t('Opens help documentation in a new window.'),
          'class' => [
            'toolbar-item',
            'toolbar-icon',
            'toolbar-icon-olympian-help',
          ],
          'role' => 'button',
          'target' => '_blank',
        ],
      ],
      '#weight' => 100,
    ],
    '#weight' => 30,
  ];

  $items['shared_content'] = [
    '#type' => 'toolbar_item',
    'tab' => [
      '#type' => 'link',
      '#url' => Url::fromRoute('shared_content.callback'),
      '#title' => t('@version Content', [
        '@version' => 'Shared',
      ]),
      '#options' => [
        'attributes' => [
          'title' => t('Opens shared content tab in new window.'),
          'class' => [
            'toolbar-item',
            'toolbar-icon',
            'toolbar-icon-olympian-content-sharing',
          ],
          'role' => 'button',
          'target' => '_blank',
        ],
      ],
      '#weight' => 100,
    ],
    '#weight' => 30,
  ];
  $items['user_logout'] = [
    '#type' => 'toolbar_item',
    'tab' => [
      '#type' => 'link',
      '#url' => Url::fromRoute('user.logout'),
      '#title' => t('Logout'),
      '#options' => [
        'attributes' => [
          'title' => t('Opens shared content tab in new window.'),
          'class' => [
            'toolbar-item',
            'toolbar-icon',
            'toolbar-icon-olympian-content-logout',
          ],
          'role' => 'button',
          'target' => '_blank',
        ],
      ],
      '#weight' => 9999905,
    ],
    '#weight' => 9999985,
  ];
  return $items;
}

/**
 * Implements hook_preprocess_HOOK().
 */
function artsci_preprocess_html(&$variables) {
  $host = \Drupal::request()->getHost();
  if (str_ends_with($host, 'artscidev.wustl.edu') || str_ends_with($host, 'artscistage.wustl.edu') || str_ends_with($host, 'ddev.site')) {
    $robots = t('noindex, nofollow');
  }
  else {
    $robots = t('index, follow');
  }

  $meta_robots = [
    '#tag' => 'meta',
    '#attributes' => [
      'name' => 'robots',
      'content' => $robots,
    ],
  ];

  $variables['page']['#attached']['html_head'][] = [
    $meta_robots,
    'robots',
  ];
  $version = artsci_get_version();

  $meta_web_author = [
    '#tag' => 'meta',
    '#attributes' => [
      'name' => 'web-author',
      'content' => t('Arts and Sciences @version theme (https://artsci.washu.edu)', [
        '@version' => $version,
      ]),
    ],
  ];

  $variables['page']['#attached']['html_head'][] = [
    $meta_web_author,
    'web-author',
  ];
  $variables['page']['#attached']['library'][] = 'artsci/global-scripts';
  $variables['page']['#attached']['drupalSettings']['artsci']['version'] = $version;
}

/**
 * Determine the rendered theme based on what config is active.
 *
 */
function artsci_get_version() {
  $version = 'olympian';

  $is_v2 = \Drupal::config('config_split.config_split.artsci_theme')->get('status');

  if ($is_v2) {
    $version = 'artsci';
  }

  return $version;
}
