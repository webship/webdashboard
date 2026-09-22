<?php

declare(strict_types=1);

namespace Drupal\webdashboard\Hook;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Extension\ModuleExtensionList;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\Template\Attribute;
use Drupal\Core\Theme\ThemeManagerInterface;
use Drupal\user\UserDataInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Hook implementations for the Web Dashboard module.
 */
class WebDashboardHooks {

  use StringTranslationTrait;

  public function __construct(
    protected EntityTypeManagerInterface $entityTypeManager,
    #[Autowire(service: 'current_user')]
    protected AccountInterface $currentUser,
    protected ThemeManagerInterface $themeManager,
    #[Autowire(service: 'extension.list.module')]
    protected ModuleExtensionList $moduleList,
    #[Autowire(service: 'current_route_match')]
    protected RouteMatchInterface $routeMatch,
    #[Autowire(service: 'user.data')]
    protected UserDataInterface $userData,
  ) {}

  /**
   * Implements hook_module_preuninstall().
   *
   * Core does not discover hook_uninstall() in hook classes, so the clean-up
   * runs before uninstall, while the entity types still exist.
   */
  #[Hook('module_preuninstall')]
  public function modulePreuninstall(string $module): void {
    if ($module !== 'webdashboard') {
      return;
    }

    // The personalized dashboards of every user.
    $this->userData->delete('webdashboard');

    // Builder instances are content entities, so uninstalling the dashboards
    // configuration leaves the ones nobody reached through a dashboard behind.
    $storage = $this->entityTypeManager->getStorage('display_builder_instance');
    $ids = $storage->getQuery()
      ->accessCheck(FALSE)
      ->condition('id', 'webdashboard', 'STARTS_WITH')
      ->execute();

    if ($ids) {
      $storage->delete($storage->loadMultiple($ids));
    }
  }

  /**
   * Implements hook_help().
   */
  #[Hook('help')]
  public function help(string $route_name, RouteMatchInterface $route_match): ?string {
    if ($route_name !== 'help.page.webdashboard') {
      return NULL;
    }

    $output = '<h3>' . $this->t('About') . '</h3>';
    $output .= '<p>' . $this->t('Web Dashboard builds dashboards with Display Builder. Each dashboard is a configuration entity with its own permissions to view it and to personalize it. People allowed to personalize a dashboard build their own copy, and can reset it to the default dashboard at any time.') . '</p>';
    $output .= '<p>' . $this->t('Dashboard items, like charts, reports, feeds and embedded views, are placed from the block library of Display Builder. Visit the <a href=":project">Web Dashboard project page</a> for more information.', [
      ':project' => 'https://www.drupal.org/project/webdashboard',
    ]) . '</p>';

    return $output;
  }

  /**
   * Implements hook_theme().
   */
  #[Hook('theme')]
  public function theme(): array {
    return [
      'webdashboard_admin_list' => [
        'variables' => [
          'attributes' => [],
          'list' => [],
        ],
        'initial preprocess' => static::class . ':preprocessAdminList',
      ],
    ];
  }

  /**
   * Prepares variables for the dashboard admin list template.
   *
   * Default template: webdashboard-admin-list.html.twig.
   *
   * @param array $variables
   *   An associative array containing:
   *   - list: The items, each with a url, a title, and optionally a
   *     description and an image.
   */
  public function preprocessAdminList(array &$variables): void {
    $variables['#attached']['library'][] = 'webdashboard/admin_list';

    foreach ($variables['list'] as $key => $item) {
      $variables['list'][$key]['link_attributes'] = new Attribute([
        'href' => $item['url']->toString(),
        'title' => (string) $item['title'],
      ]);
    }
  }

  /**
   * Implements hook_theme_registry_alter().
   *
   * Gin and the Default Admin theme get a variant of the admin list template,
   * unless a theme already overrides it: both draw the whole item as a link.
   */
  #[Hook('theme_registry_alter')]
  public function themeRegistryAlter(array &$theme_registry): void {
    $module_path = $this->moduleList->getPath('webdashboard');

    if (($theme_registry['webdashboard_admin_list']['path'] ?? NULL) !== $module_path . '/templates') {
      return;
    }

    $theme = $this->themeManager->getActiveTheme();
    $themes = [$theme->getName(), ...\array_keys($theme->getBaseThemeExtensions())];

    if (\array_intersect(['gin', 'default_admin'], $themes)) {
      $theme_registry['webdashboard_admin_list']['path'] = $module_path . '/templates/gin';
    }
  }

  /**
   * Implements hook_toolbar().
   */
  #[Hook('toolbar')]
  public function toolbar(): array {
    /** @var \Drupal\webdashboard\Entity\DashboardStorage $storage */
    $storage = $this->entityTypeManager->getStorage('webdashboard');
    $items['webdashboard'] = [
      '#cache' => [
        'tags' => $storage->getEntityType()->getListCacheTags(),
        'contexts' => ['user.permissions'],
      ],
    ];

    $dashboards = \array_filter(
      $storage->loadMultipleOrderedByWeight(),
      fn ($dashboard) => $dashboard->access('view', $this->currentUser),
    );

    if ($dashboards === []) {
      return $items;
    }

    $links = [];

    foreach ($dashboards as $dashboard) {
      $links[] = [
        'title' => $dashboard->label(),
        'url' => $dashboard->toUrl('canonical'),
      ];
    }

    $items['webdashboard'] += [
      '#type' => 'toolbar_item',
      'tab' => [
        '#type' => 'link',
        '#title' => $this->t('Dashboards'),
        '#url' => \reset($dashboards)->toUrl('canonical'),
        '#attributes' => [
          'class' => ['toolbar-icon', 'toolbar-menu-administration-dashboard'],
        ],
      ],
      'tray' => [
        '#heading' => $this->t('Dashboards'),
        'dashboards' => [
          '#theme' => 'links__toolbar_webdashboard',
          '#links' => $links,
          '#attributes' => [
            'class' => ['toolbar-menu'],
          ],
        ],
      ],
      '#weight' => 150,
      '#attached' => [
        'library' => ['webdashboard/core'],
      ],
    ];

    return $items;
  }

  /**
   * Implements hook_preprocess_HOOK() for html.
   */
  #[Hook('preprocess_html')]
  public function preprocessHtml(array &$variables): void {
    if (!\str_starts_with((string) $this->routeMatch->getRouteName(), 'entity.webdashboard.')) {
      return;
    }

    if ($variables['html_attributes'] instanceof Attribute) {
      $variables['html_attributes']->addClass('webdashboard');
    }
  }

}
