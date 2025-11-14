<?php

namespace Drupal\shared_content\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\feeds\Entity\Feed;

class SharedContentUpdateForm extends FormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'shared_content_update_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form['description'] = [
      '#type' => 'item',
      '#markup' => '<p>Click Update Feeds to refresh each of the shared content feeds.</p>',
    ];

    $form['update_feeds'] = [
      '#type' => 'submit',
      '#value' => $this->t('Update All Feeds'),
      '#submit' => ['::updateAllFeeds'],
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    // Load all aggregator feeds that are related to shared content.
//     $query = \Drupal::database()->select('aggregator_category', 'ac')
//       ->fields('ac', ['cid'])
//       ->condition('title', 'Shared Content')
//       ->execute();
//     
//     $category_id = $query->fetchField();
// 

      $feed_query = \Drupal::database()->select('aggregator_feed', 'af')
        ->fields('af', ['fid'])
        ->execute();
      
      $feed_ids = $feed_query->fetchAll();

      if (!empty($feed_ids)) {
        $feeds = \Drupal::entityTypeManager()->getStorage('aggregator_feed')->loadMultiple(array_column($feed_ids, 'fid'));

        foreach ($feeds as $feed) {
          if ($feed) {
            // Trigger the feed refresh.
            $feed->update();
          }
        }

        $this->messenger()->addMessage($this->t('Feeds updated successfully.'));
      }
      else {
        $this->messenger()->addMessage($this->t('No feeds found for Shared Content category.'), 'warning');
      }


  }
  /**
   * Submit handler for updating all aggregator feeds.
   */
  public function updateAllFeeds(array &$form, FormStateInterface $form_state) {
    // Load all aggregator feeds and update them.

    foreach (Feed::loadMultiple() as $feed) {
      $feed->updateItems();
    }

    // Notify the user.
    \Drupal::messenger()->addMessage($this->t('All aggregator feeds have been updated.'));
  }
}
