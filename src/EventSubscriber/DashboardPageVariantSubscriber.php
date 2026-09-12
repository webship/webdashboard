<?php

declare(strict_types=1);

namespace Drupal\webdashboard\EventSubscriber;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Render\PageDisplayVariantSelectionEvent;
use Drupal\Core\Render\RenderEvents;
use Drupal\Core\Theme\ThemeManagerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Keeps the page of the administration theme around dashboards.
 *
 * A dashboard route is not an administration route, because a dashboard can
 * be shown in the frontend theme. Page layouts of Display Builder take every
 * page that is not an administration route, so a dashboard shown in the
 * administration theme would get the frontend page layout drawn inside it.
 *
 * @see \Drupal\webdashboard\Theme\DashboardThemeNegotiator
 * @see \Drupal\display_builder_page_layout\EventSubscriber\PageVariantSubscriber
 */
class DashboardPageVariantSubscriber implements EventSubscriberInterface {

  /**
   * The routes showing a dashboard, not a builder.
   */
  private const ROUTES = [
    'entity.webdashboard.canonical',
    'entity.webdashboard.override_reset',
  ];

  public function __construct(
    protected ThemeManagerInterface $themeManager,
    protected ConfigFactoryInterface $configFactory,
    protected ModuleHandlerInterface $moduleHandler,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    // After the page layout subscriber, at -100.
    return [
      RenderEvents::SELECT_PAGE_DISPLAY_VARIANT => [
        ['onSelectPageDisplayVariant', -200],
      ],
    ];
  }

  /**
   * Selects the block page for dashboards shown in the administration theme.
   *
   * @param \Drupal\Core\Render\PageDisplayVariantSelectionEvent $event
   *   The event to process.
   */
  public function onSelectPageDisplayVariant(PageDisplayVariantSelectionEvent $event): void {
    if (!\in_array($event->getRouteMatch()->getRouteName(), self::ROUTES, TRUE) || !$this->moduleHandler->moduleExists('block')) {
      return;
    }

    $admin_theme = $this->configFactory->get('system.theme')->get('admin');
    $event->addCacheContexts(['theme']);

    if ($admin_theme && $this->themeManager->getActiveTheme()->getName() === $admin_theme) {
      $event->setPluginId('block_page');
    }
  }

}
