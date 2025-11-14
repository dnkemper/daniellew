#!/usr/bin/env php
<?php

/**
 * @file
 * Drush script to find and fix #anchor-group links in text fields.
 *
 * Usage:
 *   drush scr path/to/fix_anchor_group_links.php
 *
 * This script will:
 *   1) Find all nodes that have fields containing "#anchor-group-".
 *   2) Extract the anchor-group ID.
 *   3) Load Paragraphs with field_item_id = <the ID>.
 *   4) Replace the old anchor (#anchor-group-<ID>) with a new anchor (#paragraph-<paragraph_id>).
 *   5) Save the updated node.
 *
 * Adjust field names, paragraph references, and anchors to match your site’s configuration.
 */

use Drupal\Core\Entity\EntityStorageException;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;

// Ensure Drush has bootstrapped the site.
// (No need for a manual bootstrap if you're running via `drush scr`.)

// 1. Define which entity types and fields you want to scan.
//    For example, we'll look at node entities, scanning the 'body' field.
$entity_type_id = 'node';
$field_names = ['body', 'field_direct_link', 'field_description', 'field_department_links', 'field_course_links', 'field_button_url', 'field_call_to_action_butto', 'field_external_url', 'field_advisor_url', 'field_biography', 'field_external_profile_link', 'field_field_advisor_url', 'field_intro_text', 'field_call_to_action_button_url'];

// 2. Define the anchor prefix to look for and the field in Paragraphs that holds the item_id.
$anchor_prefix = '#anchor-group-';
$paragraph_entity_type = 'paragraph';
$paragraph_item_id_field = 'field_item_id';

// 3. Optionally restrict which content types to process.
//    If you want to process all content types, leave this array empty or comment it out.
$content_types_to_process = [
  // 'faq_page',
];

try {
  // Get the entity type manager and logger from the service container.
  /** @var EntityTypeManagerInterface $entityTypeManager */
  $entityTypeManager = \Drupal::service('entity_type.manager');

  /** @var LoggerChannelFactoryInterface $loggerFactory */
  $loggerFactory = \Drupal::service('logger.factory');
  $logger = $loggerFactory->get('anchor_group_fix');

  // Build a query to load the node IDs to inspect.
  $query = $entityTypeManager->getStorage($entity_type_id)->getQuery();
  $query->accessCheck(FALSE);  // Explicitly disable access checks for bulk processing.

  // If you want to filter by specific content types, uncomment and add them here.
  if (!empty($content_types_to_process)) {
    $query->condition('type', $content_types_to_process, 'IN');
  }

  // (Optional) Only published nodes:
  // $query->condition('status', 1);

  // Execute query to get node IDs.
  $nids = $query->execute();

  if (empty($nids)) {
    $logger->info('No nodes found matching your conditions.');
    return;
  }

  /** @var \Drupal\node\NodeStorage $node_storage */
  $node_storage = $entityTypeManager->getStorage($entity_type_id);

  // Load all candidate nodes.
  $nodes = $node_storage->loadMultiple($nids);

  $updated_count = 0;

  // 4. Loop through each node and check the specified text fields.
  foreach ($nodes as $node) {
    $changed = FALSE;

    foreach ($field_names as $field_name) {
      // If the node doesn't have this field, skip.
      if (!$node->hasField($field_name)) {
        continue;
      }

      // Get the text value. Assuming it's a basic text/HTML field with ->value.
      $text_value = $node->get($field_name)->value;
      if (empty($text_value)) {
        continue;
      }

      // Look for occurrences of #anchor-group-<number>.
      // We'll use a regex to match "#anchor-group-" followed by one or more digits.
      $regex = '/#anchor-group-(\d+)/';

      if (preg_match_all($regex, $text_value, $matches)) {
        // $matches[1] contains the digit sequences found after #anchor-group-
        foreach ($matches[1] as $matched_id) {
          $matched_id_int = (int) $matched_id;

          // 5. Load Paragraph(s) that have field_item_id = $matched_id_int.
          // loadByProperties() typically doesn't require a manual query with accessCheck, 
          // because it does its own checks under the hood. If needed, you can drop to a query:
          //
          //   $paragraph_query = $entityTypeManager->getStorage($paragraph_entity_type)->getQuery();
          //   $paragraph_query->accessCheck(FALSE);
          //   $paragraph_query->condition($paragraph_item_id_field, $matched_id_int);
          //   $paragraph_ids = $paragraph_query->execute();
          //   $paragraphs = $entityTypeManager->getStorage($paragraph_entity_type)->loadMultiple($paragraph_ids);
          //
          // But for simplicity, we'll try loadByProperties():
          $paragraphs = $entityTypeManager
            ->getStorage($paragraph_entity_type)
            ->loadByProperties([$paragraph_item_id_field => $matched_id_int]);

          if (!empty($paragraphs)) {
            // Grab the first Paragraph if multiple match. Adjust if needed.
            $paragraph = reset($paragraphs);
            $paragraph_id = $paragraph->id();

            // 6. Replace #anchor-group-{old_id} with #paragraph-{paragraph_id} in the text.
            $old_anchor = $anchor_prefix . $matched_id_int;
            $new_anchor = '#paragraph-' . $paragraph_id;

            // Perform the replacement. If text changes, note that the node is changed.
            $new_text_value = str_replace($old_anchor, $new_anchor, $text_value);
            if ($new_text_value !== $text_value) {
              $text_value = $new_text_value;
              $changed = TRUE;
            }
          }
        }

        // If anything was replaced in this field, update the node’s field value.
        if ($changed) {
          $node->set($field_name, $text_value);
        }
      }
    }

    // If this node was changed, save the node.
    if ($changed) {
      $updated_count++;
      $node->save();
      $logger->info('Updated node @nid.', ['@nid' => $node->id()]);
      echo "Updated node $node->id() ";
    }
  }

  $logger->info('Script complete. Updated @count nodes.', ['@count' => $updated_count]);
}
catch (EntityStorageException $e) {
  \Drush\Drush::logger()->error('Entity storage exception: ' . $e->getMessage());
}
catch (\Exception $e) {
  \Drush\Drush::logger()->error('General exception: ' . $e->getMessage());
}

