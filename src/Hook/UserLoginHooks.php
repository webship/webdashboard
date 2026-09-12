<?php

declare(strict_types=1);

namespace Drupal\webdashboard\Hook;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Url;
use Drupal\user\UserInterface;
use Drupal\webdashboard\DefaultDashboardResolver;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Sends people to their dashboard after login, when the site asks for it.
 */
class UserLoginHooks {

  /**
   * The routes logging in from a one-time link.
   *
   * They lead to the account form, to set a new password, and must keep doing
   * so.
   */
  private const ONE_TIME_LOGIN_ROUTES = [
    'user.reset',
    'user.reset.form',
    'user.reset.login',
  ];

  public function __construct(
    protected ConfigFactoryInterface $configFactory,
    protected RequestStack $requestStack,
    #[Autowire(service: 'current_route_match')]
    protected RouteMatchInterface $routeMatch,
    protected DefaultDashboardResolver $defaultDashboardResolver,
  ) {}

  /**
   * Implements hook_user_login().
   *
   * The login form redirects to the account page, and a destination query
   * argument overrides any redirect, so the dashboard is set as destination.
   *
   * @see \Drupal\Core\EventSubscriber\RedirectResponseSubscriber::checkRedirectUrl()
   */
  #[Hook('user_login')]
  public function userLogin(UserInterface $account): void {
    if (!$this->configFactory->get('webdashboard.settings')->get('redirect_after_login')) {
      return;
    }

    $request = $this->requestStack->getCurrentRequest();

    if (!$request || $request->query->has('destination') || \in_array($this->routeMatch->getRouteName(), self::ONE_TIME_LOGIN_ROUTES, TRUE)) {
      return;
    }

    if ($this->defaultDashboardResolver->resolve($account) === NULL) {
      return;
    }

    $request->query->set('destination', Url::fromRoute('webdashboard.default')->toString());
  }

}
