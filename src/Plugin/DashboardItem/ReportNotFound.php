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
 * Lists the pages most often not found.
 */
#[DashboardItem(
  id: 'report_not_found',
  label: new TranslatableMarkup('Top 404 pages'),
  category: new TranslatableMarkup('Dashboard: Reports'),
)]
class ReportNotFound extends DashboardItemBase {

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

    $query = $this->database->select('watchdog', 'w');
    $query->addExpression('COUNT([wid])', 'count');
    $query->fields('w', ['message', 'variables'])
      ->condition('w.type', 'page not found')
      ->groupBy('message')
      ->groupBy('variables')
      ->orderBy('count', 'DESC')
      ->range(0, 5);
    $items = [];

    foreach ($query->execute()->fetchAll() as $row) {
      $variables = \unserialize((string) $row->variables, ['allowed_classes' => FALSE]);
      $items[] = [
        'title' => $row->count,
        'description' => [
          '#plain_text' => \is_array($variables) ? \strtr((string) $row->message, $variables) : (string) $row->message,
        ],
        'url' => Url::fromRoute('dblog.page_not_found'),
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
