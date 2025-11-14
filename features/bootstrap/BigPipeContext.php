<?php

declare(strict_types = 1);


use Behat\Mink\Exception\UnsupportedDriverActionException;
use Drupal\big_pipe\Render\Placeholder\BigPipeStrategy;
use Drupal\DrupalExtension\Context\DrupalContext;
use Drupal\DrupalExtension\Context\RawDrupalContext;

/**
 * Provides step definitions for interacting with Drupal.
 */
class BigPipeContext extends DrupalContext {

  /**
   * {@inheritdoc}
   */
	public function login(\stdClass $user) {
		//echo 'extralogging in';
    parent::login($user);
    $this->disableBigPipe();
  }

  /**
   * Disables Big Pipe.
   *
   * Workaround for issue with drupalextension and big_pipe.
   * See https://github.com/jhedstrom/drupalextension/issues/258
   */
  protected function disableBigPipe() {
    try {
      // Check if JavaScript can be executed by Driver.
      $this->getSession()->getDriver()->executeScript('true');
    }
    catch (UnsupportedDriverActionException $e) {
      // Set NOJS cookie.
      $this
        ->getSession()
        ->setCookie(BigPipeStrategy::NOJS_COOKIE, TRUE);
    }
    catch (\Exception $e) {
      // Mute exceptions.
    }
  }

}

