<?php

declare(strict_types=1);

namespace Drupal\webdashboard\Theme;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Theme\ThemeNegotiatorInterface;
use Drupal\webdashboard\Entity\DashboardInterface;

/**
 * Shows the dashboards in the administration theme.
 *
 * Unless the dashboard is set to always show in the frontend theme, or the
 * user may not view the administration theme.
 */
class DashboardThemeNegotiator implements ThemeNegotiatorInterface {

  /**
   * The routes showing a dashboard.
   */
  private const ROUTES = [
    'entity.webdashboard.canonical',
    'entity.webdashboard.display_builder',
    'entity.webdashboard.override',
    'entity.webdashboard.override_reset',
  ];

  public function __construct(
    protected ConfigFactoryInterface $configFactory,
    protected AccountInterface $currentUser,
  ) {}

  /**
   * {@inheritdoc}
   */
  public function applies(RouteMatchInterface $route_match): bool {
    if (!\in_array($route_match->getRouteName(), self::ROUTES, TRUE)) {
      return FALSE;
    }

    $dashboard = $route_match->getParameter('webdashboard');

    if ($dashboard instanceof DashboardInterface && $dashboard->showAlwaysInFrontend()) {
      return FALSE;
    }

    return $this->currentUser->isAuthenticated() && $this->currentUser->hasPermission('view the administration theme');
  }

  /**
   * {@inheritdoc}
   */
  public function determineActiveTheme(RouteMatchInterface $route_match): ?string {
    return $this->configFactory->get('system.theme')->get('admin') ?: NULL;
  }

}
