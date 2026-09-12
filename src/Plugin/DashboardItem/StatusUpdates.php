<?php

declare(strict_types=1);

namespace Drupal\webdashboard\Plugin\DashboardItem;

use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Url;
use Drupal\update\UpdateManagerInterface;
use Drupal\webdashboard\Attribute\DashboardItem;
use Drupal\webdashboard\Plugin\DashboardItemBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Counts the projects with an update or a security update available.
 */
#[DashboardItem(
  id: 'status_updates',
  label: new TranslatableMarkup('Module update status'),
  category: new TranslatableMarkup('Dashboard: System'),
)]
class StatusUpdates extends DashboardItemBase {

  /**
   * The module handler.
   */
  protected ModuleHandlerInterface $moduleHandler;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): static {
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $instance->moduleHandler = $container->get('module_handler');

    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function buildRenderArray(array $configuration): array {
    if (!$this->moduleHandler->moduleExists('update')) {
      return $this->buildMessage(
        $this->t('This feature requires the Core Update module to be enabled.'),
        ['status-report-logs', 'error'],
      );
    }

    $available = update_get_available(FALSE);

    if (!$available) {
      return $this->buildMessage($this->t('Could not get updates'), ['status-report-card']);
    }

    $this->moduleHandler->loadInclude('update', 'inc', 'update.compare');
    $project_data = update_calculate_project_data($available);

    $counter = [
      UpdateManagerInterface::NOT_SECURE => 0,
      UpdateManagerInterface::NOT_CURRENT => 0,
      UpdateManagerInterface::CURRENT => \count($this->moduleHandler->getModuleList()),
    ];
    $text = [
      UpdateManagerInterface::NOT_SECURE => $this->t('Security update available'),
      UpdateManagerInterface::NOT_CURRENT => $this->t('Update available'),
      UpdateManagerInterface::CURRENT => $this->t('Up to date'),
    ];

    foreach ($project_data as $project) {
      if (isset($counter[$project['status']])) {
        $counter[$project['status']]++;
      }
    }

    $items = [];

    foreach ($counter as $state => $count) {
      $items[] = [
        'url' => Url::fromRoute('update.module_update'),
        'title' => $this->t('@count @text', [
          '@count' => $count,
          '@text' => $text[$state],
        ]),
      ];
    }

    return [
      '#type' => 'container',
      '#attributes' => [
        'class' => ['status-report-card-updates', 'success'],
      ],
      'content' => [
        '#theme' => 'webdashboard_admin_list',
        '#list' => $items,
      ],
      '#cache' => [
        'tags' => ['config:update.settings'],
        'max-age' => 3600,
      ],
    ];
  }

  /**
   * Builds a message in place of the report.
   *
   * @param \Drupal\Core\StringTranslation\TranslatableMarkup $message
   *   The message.
   * @param string[] $classes
   *   The wrapper classes.
   *
   * @return array
   *   The render array.
   */
  protected function buildMessage(TranslatableMarkup $message, array $classes): array {
    return [
      '#type' => 'container',
      '#attributes' => ['class' => $classes],
      'message' => [
        '#type' => 'html_tag',
        '#tag' => 'h3',
        '#value' => $message,
      ],
      '#cache' => [
        'tags' => ['config:core.extension'],
      ],
    ];
  }

}
