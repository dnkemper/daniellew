<?php

namespace Drupal\Tests\olympian_core\Unit;

use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\olympian_core\Plugin\DateAugmenter\AddToCal;

class TestAddToCal extends AddToCal {

  /**
   * Override parent::getCurrentDate()
   * Do NOT call parent::getCurrentDate() inside this function
   * or you will receive the original error.
   */
  protected function getCurrentDate() {
    $cdt = new \DateTimeZone('America/Chicago');
    $settings = ['langcode' => 'en'];
    return DrupalDateTime::createFromFormat('Y-m-d H:i:s', '2021-10-27 00:00:00', $cdt, $settings);
  }

}
