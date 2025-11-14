<?php

/**
 * @file
 * Theme settings form for olympian9 theme.
 */

use Drupal\Core\Form\FormStateInterface;
use Drupal\file\Entity\File;
use Drupal\FunctionalJavascriptTests\WebDriverTestBase;
use Drupal\media\Entity\Media;

/**
 * Implements hook_form_system_theme_settings_alter().
 */
function olympian9_form_system_theme_settings_alter(&$form, FormStateInterface $form_state)
{
  $configFactory = Drupal::service('config.factory');
  $config = $configFactory->get('system.site');
  $variables['site_name'] = $config->get('name');
  $variables['site_mail'] = $config->get('site_mail');
  $form['favicon']['#access'] = FALSE;
  $form['icon']['#access'] = FALSE;
  $form['logo']['#access'] = FALSE;
  $form['theme_settings']['#access'] = FALSE;

  // Might need to check if the above belongs in 'page element display' or within the Olympian9 subsection.
  $form['olympian'] = [
    '#type' => 'details',
    '#title' => t('Olympian9 settings'),
    '#weight' => -6000,
    '#open' => TRUE,
    '#collapsible' => TRUE,
  ];
  $form['menu'] = [
    '#type' => 'details',
    '#title' => t('Header settings'),
    '#weight' => -5000,
    '#collapsible' => TRUE,
    '#open' => TRUE,
  ];
  $form['footer'] = [
    '#type' => 'details',
    '#title' => t('Footer settings'),
    '#description' => t('Configure the links enabled in the footer.'),
    '#weight' => 0,
    '#collapsible' => TRUE,
    '#open' => TRUE,
  ];
  $form['menu']['department_image_path'] = [
    '#type' => 'textfield',
    '#title' => t('Path to Department Image'),
    '#default_value' => theme_get_setting('department_image_path'),
  ];
  $form['menu']['department_image_upload'] = [
    '#type' => 'managed_file',
    '#title' => t('Department Image'),
    '#required' => FALSE,
    '#upload_location' => 'public://',
    '#default_value' => theme_get_setting('department_image_upload'),
    '#upload_validators' => [
      'file_validate_extensions' => ['gif png jpg jpeg'],
    ],
  ];

  $form['menu']['events_header_image_path'] = [
    '#type' => 'textfield',
    '#title' => t('Path to Department Image'),
    '#default_value' => theme_get_setting('events_header_image_path'),
  ];
  $form['menu']['events_header_image_upload'] = [
    '#type' => 'managed_file',
    '#title' => t('Upload Events Header Image'),
    '#required' => FALSE,
    '#upload_location' => 'public://',
    '#default_value' => theme_get_setting('events_header_image_upload'),
    '#upload_validators' => [
      'file_validate_extensions' => ['gif png jpg jpeg'],
    ],
  ];

//  $form['menu']['header_color'] = [
//     '#type' => 'select',
//     '#title' => t('Site Header Color'),
//     '#default_value' => theme_get_setting('header_color'),
//     '#options' => [
//       1 => t('Light'),
//       2 => t('Dark'),
//     ],
//   ]; *

 $form['menu']['header_color']['#access'] = FALSE;

  $form['menu']['header_logo'] = [
    '#type' => 'select',
    '#options' => [1 => 'Arts & Sciences Logo', 2 => 'WashU Logo'],
    '#title' => t('Header Logo Type'),
    '#default_value' => theme_get_setting('header_logo'),
  ];

  $form['menu']['top_button_title_custom_text'] = [
    '#type' => 'textfield',
    '#title' => t('Top Left Title Text'),
    '#description' => t('Enter custom text to display for top left title. If left blank, the default title will display'),
    '#default_value' => theme_get_setting('top_button_title_custom_text'),
  ];

  $form['menu']['apply_button'] = [
    '#type' => 'select',
    '#options' => [1 => 'Show', 2 => 'Hide'],
    '#title' => t('Show Top-Left Menu Button?'),
    '#default_value' => theme_get_setting('apply_button'),
  ];

  $form['menu']['apply_button_text'] = [
    '#type' => 'textfield',
    '#title' => t('Top-Left Button Text'),
    '#default_value' => theme_get_setting('apply_button_text'),
  ];

  $form['menu']['apply_button_link'] = [
    '#type' => 'textfield',
    '#title' => t('Top-Left Button URL'),
    '#default_value' => theme_get_setting('apply_button_link'),
  ];
  $form['#attached']['library'][] = 'linkit/linkit.autocomplete';
  $form['menu']['apply_button_link'] = [
    '#type' => 'linkit',
    '#title' => t('Top-Left Button URL'),
    '#default_value' => theme_get_setting('apply_button_link'),
    '#autocomplete_route_name' => 'linkit.autocomplete',
    '#autocomplete_route_parameters' => [
      'linkit_profile_id' => 'default',
    ],
  ];
  $form['menu']['apply_button_external'] = [
    '#type' => 'select',
    '#options' => [1 => t('External'), 2 => t('Internal')],
    '#title' => t('Top-Left Button Base Site'),
    '#default_value' => theme_get_setting('apply_button_external'),
  ];

  $form['olympian']['alerts_feed'] = [
    '#type' => 'select',
    '#options' => [
      1 => 'On',
      2 => 'Off',
    ],
    '#title' => t('Alerts Feed'),
    '#description' => t('This should only be changed for testing purposes'),
    '#default_value' => theme_get_setting('alerts_feed'),
  ];

  $site_path = Drupal::getContainer()->getParameter('site.path');
  if ($site_path == 'sites/linguistics.wustl.edu') {
    // Capacity Chatbot - way to only display on linguistics.
    $form['olympian']['chatbot'] = [
      '#type' => 'select',
      '#options' => [
        1 => 'On',
        2 => 'Off',
      ],
      '#title' => t('Chatbot'),
      '#description' => t('Chatbot provided by Capacity. Do not turn on except for Linguistics site.'),
      '#default_value' => theme_get_setting('chatbot'),
    ];
  }
  $form['header'] = [
    '#type' => 'details',
    '#title' => t('Navigation Display Settings'),
    '#description' => t('Configure the style of navigation to be used.'),
    '#weight' => -1000,
    '#open' => TRUE,
    '#tree' => TRUE,
  ];
  $form['header']['toppage'] = [
    '#type' => 'checkbox',
    '#title' => t('Back to top button'),
    '#default_value' => theme_get_setting('header.toppage'),
    '#description' => t('A back to top button will be visible when the user scrolls down the page.'),
  ];

  // Artsci.
  $form['footer']['additional_link_artsci'] = [
    '#type' => 'select',
    '#title' => t('Additional Information Link: Arts & Sciences'),
    '#description' => t('Adds an item to the Additional Information section of the Footer.'),
    '#options' => [
      1 => t('Enabled'),
      2 => t('Disabled'),
    ],
    '#default_value' => theme_get_setting('additional_link_artsci'),
  ];

  // Graduateschool.
  $form['footer']['additional_link_graduate'] = [
    '#type' => 'select',
    '#title' => t('Additional Information Link: Graduate Studies in A&S'),
    '#description' => t('Adds an item to the Additional Information section of the Footer.'),
    '#options' => [
      1 => t('Enabled'),
      2 => t('Disabled'),
    ],
    '#default_value' => theme_get_setting('additional_link_graduate'),
  ];

  // Copyright.
  $form['footer']['copyright'] = [
    '#type' => 'textfield',
    '#title' => ('Copyright'),
    '#default_value' => theme_get_setting('copyright') ? theme_get_setting('copyright') : 'Arts & Sciences at Washington University in St. Louis',
    '#description' => t('Copyright text that appears on the bottom of the site. <br /> Note: you do not need to include the year'),
  ];

  // Helper lines for the custom submit handler below.
  $form['#submit'][] = 'olympian9_settings_form_submit';
}



/**
 * Submit handler for the Washu settings form.
 *
 * Works with managed_file elements named:
 *  - department_image
 *  - events_header_image
 */
function olympian9_settings_form_submit(array &$form, FormStateInterface $form_state): void {
  // Fix: Look for the correct field names with _upload suffix
  $dep_fids = (array) ($form_state->getValue('department_image_upload') ?? []);
  $evt_fids = (array) ($form_state->getValue('events_header_image_upload') ?? []);

  $department_image = !empty($dep_fids) ? File::load(reset($dep_fids)) : NULL;
  $events_header_image = !empty($evt_fids) ? File::load(reset($evt_fids)) : NULL;

  // If files exist, stash their URIs into form values for saving.
  if ($department_image instanceof \Drupal\file\FileInterface) {
    $form_state->setValue('department_image_path', $department_image->getFileUri());
  }
  if ($events_header_image instanceof \Drupal\file\FileInterface) {
    $form_state->setValue('events_header_image_path', $events_header_image->getFileUri());
  }

  // Ensure files are permanent and record file usage.
  $files = array_filter([$department_image, $events_header_image]);
  $file_usage = \Drupal::service('file.usage');

  foreach ($files as $file) {
    // Set to permanent if currently temporary.
    if ($file->isTemporary()) {
      $file->setPermanent();
      $file->save();
    }
    // Add a usage reference to prevent cleanup by file_cron().
    $file_usage->add($file, 'olympian9', 'theme', 1);
  }

  \Drupal::messenger()->addStatus(t('Theme file status set to permanent.'));
}

