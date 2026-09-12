<?php

/**
 * @file
 * Post update functions for the Web Dashboard module.
 */

declare(strict_types=1);

/**
 * Add the redirect after login setting, turned off.
 */
function webdashboard_post_update_redirect_after_login(): void {
  $config = \Drupal::configFactory()->getEditable('webdashboard.settings');

  if ($config->get('redirect_after_login') === NULL) {
    $config->set('redirect_after_login', FALSE)->save(TRUE);
  }
}
