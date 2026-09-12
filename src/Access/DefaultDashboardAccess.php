<?php

declare(strict_types=1);

namespace Drupal\webdashboard\Access;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\DependencyInjection\AutowireTrait;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\webdashboard\DefaultDashboardResolver;

/**
 * Checks the account can view at least one dashboard.
 *
 * Guarding the route rather than the controller also hides the "Dashboard"
 * menu link from anybody who would only reach an access denied page.
 */
class DefaultDashboardAccess implements ContainerInjectionInterface {

  use AutowireTrait;

  public function __construct(
    protected DefaultDashboardResolver $defaultDashboardResolver,
  ) {}

  /**
   * Can the account view a dashboard?
   *
   * @param \Drupal\Core\Session\AccountInterface $account
   *   The account.
   *
   * @return \Drupal\Core\Access\AccessResultInterface
   *   The access result.
   */
  public function access(AccountInterface $account): AccessResultInterface {
    $cacheability = new CacheableMetadata();
    $dashboard = $this->defaultDashboardResolver->resolve($account, $cacheability);

    return AccessResult::allowedIf($dashboard !== NULL)->addCacheableDependency($cacheability);
  }

}
