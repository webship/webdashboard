<?php

declare(strict_types=1);

namespace Drupal\webdashboard\Plugin\DashboardItem;

use Drupal\Core\Database\Connection;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Url;
use Drupal\webdashboard\Attribute\DashboardItem;
use Drupal\webdashboard\Plugin\DashboardItemBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Counts the critical messages, errors and warnings logged this week.
 */
#[DashboardItem(
  id: 'error_report',
  label: new TranslatableMarkup('Show error info'),
  category: new TranslatableMarkup('Dashboard: System'),
)]
class ErrorReport extends DashboardItemBase {

  /**
   * The database connection.
   */
  protected Connection $database;

  /**
   * The module handler.
   */
  protected ModuleHandlerInterface $moduleHandler;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): static {
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $instance->database = $container->get('database');
    $instance->moduleHandler = $container->get('module_handler');

    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function buildRenderArray(array $configuration): array {
    if (!$this->moduleHandler->moduleExists('dblog')) {
      return DblogRequiredTrait::dblogRequired();
    }

    $query = $this->database->select('watchdog', 'w')->fields('w', ['severity']);
    $query->condition('timestamp', \strtotime('this week'), '>');
    $query->addExpression('COUNT([wid])', 'severity_count');
    $query->groupBy('severity');
    $result = $query->execute()->fetchAll();

    $counts = ['critical' => 0, 'errors' => 0, 'warnings' => 0];

    foreach ($result as $row) {
      $severity = (int) $row->severity;

      if ($severity < \LOG_ERR) {
        $counts['critical'] += (int) $row->severity_count;
      }
      elseif ($severity === \LOG_ERR) {
        $counts['errors'] = (int) $row->severity_count;
      }
      elseif ($severity === \LOG_WARNING) {
        $counts['warnings'] = (int) $row->severity_count;
      }
    }

    $list = [
      'critical' => ['text' => $this->t('Critical'), 'query' => [0, 1, 2]],
      'errors' => ['text' => $this->t('Errors'), 'query' => [3]],
      'warnings' => ['text' => $this->t('Warnings'), 'query' => [4]],
    ];
    $items = [];

    foreach ($list as $state => $info) {
      $items[] = [
        'url' => Url::fromRoute('dblog.overview', [], ['query' => ['severity' => $info['query']]]),
        'title' => $this->t('@count @text', [
          '@count' => $counts[$state],
          '@text' => $info['text'],
        ]),
      ];
    }

    return [
      '#theme' => 'webdashboard_admin_list',
      '#list' => $items,
      '#cache' => [
        'max-age' => 300,
      ],
    ];
  }

}
