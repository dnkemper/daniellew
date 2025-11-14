<?php

namespace Drupal\olympian_core\Access;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Routing\Access\AccessInterface;
use Drupal\Core\Session\AccountInterface;

/**
 * Olympian Core access checker.
 */
class OlympianCoreAccessContent implements AccessInterface {

  /**
   * A custom access olympian.
   *
   * @param \Drupal\Core\Session\AccountInterface|null $account
   *   Run access checks for this account.
   *
   * @return \Drupal\Core\Access\AccessResultInterface
   *   The access result.
   */
  public function access(AccountInterface $account = NULL) {
    // Check the current account if no account was passed.
    if (is_null($account)) {
      $account = \Drupal::currentUser();
    }
    return (
      (int) $account->id() === 1 ||
      in_array('administrator', $account->getRoles()) ||
      in_array('site_staff', $account->getRoles()) ||
      in_array('communications', $account->getRoles())
    ) ? AccessResult::allowed() : AccessResult::forbidden();
  }

}
