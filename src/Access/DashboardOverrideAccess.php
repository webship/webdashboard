<?php

declare(strict_types=1);

namespace Drupal\webdashboard\Access;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Cache\RefinableCacheableDependencyInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\webdashboard\Entity\DashboardInterface;

/**
 * Checks access to personalize a dashboard, and to reset it.
 */
class DashboardOverrideAccess {

  /**
   * Can the account personalize the dashboard?
   *
   * Personalizing is building, so the account also needs the Display Builder
   * profile of the dashboard. Checking it here hides the "Personalize" action
   * from anybody who would only reach an access denied page.
   *
   * @param \Drupal\webdashboard\Entity\DashboardInterface $webdashboard
   *   The dashboard.
   * @param \Drupal\Core\Session\AccountInterface $account
   *   The account.
   *
   * @return \Drupal\Core\Access\AccessResultInterface
   *   The access result.
   */
  public function access(DashboardInterface $webdashboard, AccountInterface $account): AccessResultInterface {
    $profile = $webdashboard->getProfile();

    if ($account->isAnonymous() || !$profile) {
      return AccessResult::forbidden()->addCacheableDependency($webdashboard)->cachePerUser();
    }

    $result = $webdashboard->access('override', $account, TRUE)->andIf($profile->access('view', $account, TRUE));

    if ($result instanceof RefinableCacheableDependencyInterface) {
      $result->addCacheableDependency($webdashboard)->addCacheableDependency($profile);
    }

    return $result;
  }

  /**
   * Can the account reset its personalized dashboard?
   *
   * @param \Drupal\webdashboard\Entity\DashboardInterface $webdashboard
   *   The dashboard.
   * @param \Drupal\Core\Session\AccountInterface $account
   *   The account.
   *
   * @return \Drupal\Core\Access\AccessResultInterface
   *   The access result.
   */
  public function resetAccess(DashboardInterface $webdashboard, AccountInterface $account): AccessResultInterface {
    if ($account->isAnonymous()) {
      return AccessResult::forbidden()->cachePerUser();
    }

    $overridden = AccessResult::allowedIf($webdashboard->isOverridden($account))
      ->cachePerUser()
      ->addCacheTags([$webdashboard->getOverrideCacheTag($account)]);

    return $this->access($webdashboard, $account)->andIf($overridden);
  }

}
