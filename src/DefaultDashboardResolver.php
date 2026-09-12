<?php

declare(strict_types=1);

namespace Drupal\webdashboard;

use Drupal\Core\Cache\RefinableCacheableDependencyInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\webdashboard\Entity\DashboardInterface;

/**
 * Finds the dashboard an account lands on.
 *
 * That is the first dashboard, ordered by weight, the account can view.
 */
class DefaultDashboardResolver {

  public function __construct(
    protected EntityTypeManagerInterface $entityTypeManager,
  ) {}

  /**
   * Gets the dashboard an account lands on.
   *
   * @param \Drupal\Core\Session\AccountInterface $account
   *   The account.
   * @param \Drupal\Core\Cache\RefinableCacheableDependencyInterface|null $cacheability
   *   (optional) Collects what the answer depends on: the list of dashboards,
   *   and the access to each dashboard checked on the way.
   *
   * @return \Drupal\webdashboard\Entity\DashboardInterface|null
   *   The dashboard, or NULL when the account can view none.
   */
  public function resolve(AccountInterface $account, ?RefinableCacheableDependencyInterface $cacheability = NULL): ?DashboardInterface {
    /** @var \Drupal\webdashboard\Entity\DashboardStorage $storage */
    $storage = $this->entityTypeManager->getStorage('webdashboard');
    $cacheability?->addCacheTags($storage->getEntityType()->getListCacheTags());

    foreach ($storage->loadMultipleOrderedByWeight() as $dashboard) {
      $access = $dashboard->access('view', $account, TRUE);
      $cacheability?->addCacheableDependency($access);

      if ($access->isAllowed()) {
        return $dashboard;
      }
    }

    return NULL;
  }

}
