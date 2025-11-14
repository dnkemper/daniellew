<?php

use Drupal\file\Entity\File;
use Drupal\media\Entity\Media;
use Drupal\node\Entity\Node;
use Drupal\Core\Site\Settings;

/**
 * Example script:
 * drush scr path/to/migrate_events_header_image.php
 */


  // 1) Retrieve the old theme setting value (a file path).
  $custom_source_uri_prefix = Settings::get('shared_configuration.source.constants.public_file_path');
  // $host = \Drupal::request()->getSchemeAndHttpHost();
  // echo "$host";
  // $custom_source_uri_prefix = $host;
  $department_image_path = theme_get_setting('department_image_path');
  $events_header_file_id = theme_get_setting('department_image_upload');

  // Make sure your custom prefix actually exists.
  if (empty($custom_source_uri_prefix)) {
    echo "No 'olympian_migration_source_uri_prefix' found in settings. Exiting.\n";
    return;
  }

  // If no department_image_path is set, exit early.
  if (empty($department_image_path)) {
    echo "No 'department_image_path' theme setting found, or it is empty. Exiting.\n";
    return;
  }

  // 2) Replace "public://" in $department_image_path with "$custom_source_uri_prefix/"
  if (strpos($department_image_path, 'public://') === 0) {
    $filename = substr($department_image_path, strlen('public://'));
    // Build the new path
    $department_image_path = rtrim($custom_source_uri_prefix, '/') . '/' . ltrim($filename, '/');
    echo "Updated department_image_path to: $department_image_path\n";
  }

  // 3) Try to load or create a File entity for this path.
  //    Attempt to load existing file entity if we have a file ID.
  $file = NULL;
  if (!empty($events_header_file_id)) {
    $file = File::load($events_header_file_id);
    if ($file) {
      echo "Found existing File entity for ID: $events_header_file_id\n";
    }
  }

  // If we didn't find an existing file, create one.
  if (!$file) {
    $file = File::create([
      'uri' => $department_image_path,
      'status' => 0,
    ]);
    // Mark as permanent so it doesn't get garbage-collected.
    $file->setPermanent();
    $file->save();
    echo "Created new File entity for URI: $department_image_path (File ID: {$file->id()})\n";
  }

  // Double-check that the file is permanent (may be redundant, but safe).
  $file->setPermanent();
  $file->save();

  // 4) Create the Media entity referencing that file.
  $media = Media::create([
    'bundle' => 'image', // Ensure you have a Media Type named "image".
    'name' => 'Department image (migrated)',
    'field_media_image' => [
      [
        'target_id' => $file->id(),
        'alt' => 'Department image',
        'title' => 'Default title',
      ],
    ],
  ]);
  $media->save();
  echo "Created new Media entity with ID: " . $media->id() . "\n";
  // Replace 'mytheme' with the machine name of your theme.
  $theme_config = \Drupal::configFactory()->getEditable('olympian9.settings');

  // Set the custom theme setting to a new value.
  $theme_config->set('department_image_path', $department_image_path);

  // Save the config changes.
  $theme_config->save();
  echo "Script complete.\n";
