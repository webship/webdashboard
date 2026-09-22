<?php

declare(strict_types=1);

namespace Drupal\webdashboard\Plugin\DashboardItem;

use Drupal\Core\Session\AccountInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Url;
use Drupal\webdashboard\Attribute\DashboardItem;
use Drupal\webdashboard\Plugin\DashboardItemBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * The tasks people do most, as a list of links they have access to.
 */
#[DashboardItem(
  id: 'quick_tasks',
  label: new TranslatableMarkup('Quick tasks'),
  category: new TranslatableMarkup('Dashboard: User'),
)]
class QuickTasks extends DashboardItemBase {

  /**
   * The current user.
   */
  protected AccountInterface $account;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): static {
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $instance->account = $container->get('current_user');

    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function buildRenderArray(array $configuration): array {
    $tasks = [
      ['system.admin_content', $this->t('Find content'), $this->t('Search, edit and publish content.')],
      ['entity.menu.collection', $this->t('Edit menus'), $this->t('Change the navigation of the site.')],
      ['entity.user.collection', $this->t('Manage people'), $this->t('Accounts, roles and permissions.')],
      ['system.themes_page', $this->t('Change the appearance'), $this->t('Themes and their settings.')],
      ['system.status', $this->t('Check the site status'), $this->t('Errors, warnings and available updates.')],
    ];
    $items = [];

    foreach ($tasks as [$route, $title, $description]) {
      try {
        $url = Url::fromRoute($route);
        if ($url->access($this->account)) {
          $items[] = [
            'url' => $url,
            'title' => $title,
            'description' => $description,
          ];
        }
      }
      catch (\Exception) {
        // The route does not exist on this site: skip the task.
      }
    }

    if (!$items) {
      return [];
    }

    return [
      '#theme' => 'webdashboard_admin_list',
      '#list' => $items,
      '#cache' => [
        'contexts' => ['user.permissions'],
      ],
    ];
  }

}
