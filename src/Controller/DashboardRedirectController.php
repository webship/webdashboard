<?php

declare(strict_types=1);

namespace Drupal\webdashboard\Controller;

use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\DependencyInjection\AutowireTrait;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Http\Exception\CacheableAccessDeniedHttpException;
use Drupal\Core\Routing\LocalRedirectResponse;
use Drupal\Core\Session\AccountInterface;
use Drupal\webdashboard\DefaultDashboardResolver;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Sends the current user to their dashboard.
 */
class DashboardRedirectController implements ContainerInjectionInterface {

  use AutowireTrait;

  public function __construct(
    protected DefaultDashboardResolver $defaultDashboardResolver,
    #[Autowire(service: 'current_user')]
    protected AccountInterface $currentUser,
  ) {}

  /**
   * Redirects to the first dashboard, by weight, the current user can view.
   *
   * @return \Drupal\Core\Routing\LocalRedirectResponse
   *   The redirect, cacheable per the dashboards and the permissions.
   */
  public function redirectToDefault(): LocalRedirectResponse {
    $cacheability = new CacheableMetadata();
    $dashboard = $this->defaultDashboardResolver->resolve($this->currentUser, $cacheability);

    // The route access check already refuses an account with no dashboard.
    if (!$dashboard) {
      throw new CacheableAccessDeniedHttpException($cacheability);
    }

    $url = $dashboard->toUrl('canonical')->toString(TRUE);
    $response = new LocalRedirectResponse($url->getGeneratedUrl());
    $response->addCacheableDependency($cacheability);
    $response->addCacheableDependency($url);

    return $response;
  }

}
