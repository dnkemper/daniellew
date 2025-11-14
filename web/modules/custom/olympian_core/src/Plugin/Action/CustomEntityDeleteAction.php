<?php

namespace Drupal\olympian_core\Plugin\Action;

use Drupal\Core\Entity\TranslatableInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\views_bulk_operations\Action\ViewsBulkOperationsActionBase;
use Symfony\Component\HttpFoundation\RedirectResponse;

/**
 * Delete entity action with default confirmation form.
 *
 * @Action(
 *   id = "views_bulk_operations_custom_delete_entity",
 *   label = @Translation("Custom Delete selected entities / translations"),
 *   type = "",
 *   confirm = FALSE,
 *   requirements = {
 *     "_permission" = "administer olympian core",
 *     "_custom_access" = "olympian_core.access_checker",
 *   },
 * )
 */
class CustomEntityDeleteAction extends ViewsBulkOperationsActionBase {

  /**
   * {@inheritdoc}
   */
  public function execute($entity = NULL) {
    // Define an array of disallowed content types.
    $disallowedContentTypes = [
      'contact_landing_page',
      'events_landing_page',
      'past_events_landing_page',
      'faculty_landing_page',
      'news_landing_page',
      'home_page',
      'image_cards_landing',
      'resources_landing_page',
    ];
    if ($entity->getEntityTypeId() == 'node' && !in_array($entity->bundle(), $disallowedContentTypes)) {
      if ($entity instanceof TranslatableInterface && !$entity->isDefaultTranslation()) {
        $untranslated_entity = $entity->getUntranslated();
        $untranslated_entity->removeTranslation($entity->language()->getId());
        $untranslated_entity->save();
        return $this->t('Delete translations');
      }

      // Check if context array has been passed to the action.
      if (empty($this->context)) {
        throw new \Exception('Context array empty in action object.');
      }

      $this->messenger()->addMessage(\sprintf('Deleted (Title: %s)',
        $entity->label()
      ));
      $entity->delete();
      return $this->t('Batch process complete');
    }
    else {
      $this->messenger()
        ->addMessage(\sprintf('Access Denied. Deleting (Title: %s) is not permitted',
          $entity->label()
        ));
    }
  }

  /**
   * {@inheritdoc}
   */
  public function access($object, AccountInterface $account = NULL, $return_as_object = FALSE) {
    /** @var Drupal\olympian_core\Access\OlympianCoreAccess $check */
    $check = \Drupal::service('olympian_core.access_checker');

    /** @var Drupal\Core\Access\AccessResultInterface $access */
    $access = $check->access(\Drupal::currentUser()->getAccount());

    if (!$access->isForbidden()) {
      return $object->access('delete', $account, $return_as_object);
    }
  }

  /**
   * {@inheritdoc}
   */
  public static function finished($success, array $results, array $operations): ?RedirectResponse {
    // Let's return a bit different message. We don't except faliures
    // in tests as well so no need to check for a success.
    if ($success) {
      $details = [];
      foreach ($results['operations'] as $operation) {
        if (!empty($operations['message'])) {
          $details[] = $operation['message'] . ' (' . $operation['count'] . ')';
        }
      }
      $message = static::translate(' ', [
        '@operations' => \implode(', ', $details),
      ]);
      if (empty($message)) {
        $message = 'Operation not permitted for this entity type';
      }
      static::message($message);
      return NULL;
    }
  }

}
