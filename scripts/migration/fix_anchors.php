#!/usr/bin/env php
<?php

use Drupal\Core\Entity\EntityStorageException;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;

$paragraph_entity_type = 'paragraph';
$paragraph_field_names = ['field_description', 'body', 'field_description_formatted', 'field_button_link', 'field_two_col_wysiwyg', 'field_call_to_action_button', 'field_two_col_wysiwyg', 'field_callout_button', 'field_custom_button', 'field_main_title', 'field_custom_button_', 'field_half_image_main_titl', 'field_feat_article_button', 'field_footer_callout_content', 'field_optional_text', 'field_introduction_exerpt', 'field_call_to_action_button', 'field_introduction_exerpt', 'field_gen_image_intro', 'field_button_link', 'field_generic_intro', 'field_related_link_out', 'field_related_links_', 'field_custom_section_text', 'field_article_body', 'field_link_title_url', 'field_title_link', 'field_optional_text', 'field_read_the_story_link_url', 'field_publication_button', 'field_call_to_action_button', 'field_read_more_link_url', 'field_button_link', 'field_main_content', 'field_spotlight_description', 'field_introduction_exerpt', 'field__home_testimonial', 'field_half_text_body', 'field_two_col_wysiwyg'];
$anchor_pattern = '/(#anchor-group-\d+)/';

try {
  $entityTypeManager = \Drupal::service('entity_type.manager');
  $loggerFactory = \Drupal::service('logger.factory');
  $logger = $loggerFactory->get('anchor_group_removal');

  $paragraph_query = $entityTypeManager->getStorage($paragraph_entity_type)->getQuery();
  $paragraph_query->accessCheck(FALSE);
  $pids = $paragraph_query->execute();

  if (empty($pids)) {
    $logger->info('No paragraphs found.');
    return;
  }

  $paragraph_storage = $entityTypeManager->getStorage($paragraph_entity_type);
  $paragraphs = $paragraph_storage->loadMultiple($pids);
  $updated_count = 0;

  foreach ($paragraphs as $paragraph) {
    $changed = FALSE;

    foreach ($paragraph_field_names as $paragraph_field) {
      if (!$paragraph->hasField($paragraph_field)) {
        continue;
      }

      $text_value = $paragraph->get($paragraph_field)->value;
      if (empty($text_value)) {
        continue;
      }

      $new_text_value = preg_replace($anchor_pattern, '', $text_value);
      
      if ($new_text_value !== $text_value) {
        $paragraph->set($paragraph_field, $new_text_value);
        $changed = TRUE;
      }
    }

    if ($changed) {
      $paragraph->save();
      $updated_count++;
      $logger->info('Updated paragraph @pid.', ['@pid' => $paragraph->id()]);
    }
  }

  $logger->info('Done! Updated @count paragraphs.', ['@count' => $updated_count]);
}
catch (EntityStorageException $e) {
  \Drush\Drush::logger()->error('Entity storage exception: ' . $e->getMessage());
}
catch (\Exception $e) {
  \Drush\Drush::logger()->error('General exception: ' . $e->getMessage());
}
