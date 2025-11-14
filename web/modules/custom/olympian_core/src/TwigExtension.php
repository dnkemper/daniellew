<?php

namespace Drupal\olympian_core;

use Drupal\access_unpublished\Entity\AccessToken;
use Drupal\node\NodeInterface;
use Drupal\user\Entity\User;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

/**
 * A twig extension to display hash tag on a node page based on an example from:
 * http://www.tothenew.com/blog/overview-of-twig-extentions-in-drupal-8/
 */

/**
 * Class DefaultService.
 *
 * @package Drupal\access_unpublished
 */
class TwigExtension extends AbstractExtension {

  /**
   * {@inheritdoc}
   * This function must return the name of the extension. It must be unique.
   */
  /*  public function getName() {
  return 'block_display';
  }*/

  /**
   * In this function we can declare the extension function.
   */
  public function getFunctions() {
    return [
      new TwigFunction('getHashTag', [$this, 'getHashTag'], ['is_safe' => ['html']]),
    ];
  }

  /**
   * This function is used to return alt of an image
   * Set image title as alt.
   */
  public function getHashTag($entity) {
    if (is_numeric($entity)) {
      // Not an entity object.
      // Maybe this is a node id/entity id?
      $entity_manager = \Drupal::entityTypeManager();
      $entity = $entity_manager->getStorage('node')->load($entity);
    }
    $account = User::load(\Drupal::currentUser()->id());
    $hasPermission = FALSE;
    if ($account->hasPermission('renew token')) {
      $hasPermission = TRUE;
    }

    if ($hasPermission && $entity instanceof NodeInterface) {
      /** @var \Drupal\access_unpublished\AccessTokenManager $manager */
      $langId = \Drupal::languageManager()->getCurrentLanguage()->getId();
      $manager = \Drupal::service('access_unpublished.access_token_manager');

      $tokens = '';
      $tokens = $manager->getAccessTokensByEntity($entity, 'active');
      $keys = array_map(function (AccessToken $token) {
        return $token->get('value')->value;
      }, $tokens);

      if (empty($keys) || count($keys) < 1) {
        $twoHundredDays = 17280000;
        // Create tokens for the entity.
        $token = AccessToken::create([
          'entity_type' => $entity->getEntityType()->id(),
          'entity_id' => $entity->id(),
          'expire' => \Drupal::time()->getRequestTime() + $twoHundredDays,
        ]);
        $token->save();
        $keys = [$token->get('value')->value];
      }

      $countKeys = count($keys);
      $hashToken = '';
      if ($countKeys > 0) {
        $hashToken = [];
        foreach ($keys as $key) {
          $hashToken[] = $key;
        }
        if (isset($hashToken[$countKeys - 1])) {
          $hashToken = $hashToken[$countKeys - 1];
        }
        else {
          $hashToken = '';
        }
      }
      if (isset($hashToken) && strlen($hashToken) > 5) {
        $accessTokenManager = \Drupal::service('access_unpublished.access_token_manager');
        $token = $accessTokenManager->getActiveAccessToken($entity);
        $tokenUrl = '';
        if ($token) {
          $currentLang = \Drupal::languageManager()->getCurrentLanguage();
          $absolute = FALSE;
          $tokenUrl = $accessTokenManager->getAccessTokenUrl($token, $currentLang, $absolute);
        }
        return $tokenUrl;
      }
    }
    // Default below:
    return '';
  }

}
