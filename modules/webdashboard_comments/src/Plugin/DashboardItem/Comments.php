<?php

declare(strict_types=1);

namespace Drupal\webdashboard_comments\Plugin\DashboardItem;

use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Database\Connection;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\webdashboard\Attribute\DashboardItem;
use Drupal\webdashboard\Plugin\DashboardItem\ChartTrait;
use Drupal\webdashboard\Plugin\DashboardItemBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Charts the number of comments per content type.
 */
#[DashboardItem(
  id: 'comments_statistic',
  label: new TranslatableMarkup('Comment per node type'),
  category: new TranslatableMarkup('Dashboard: Statistics'),
)]
class Comments extends DashboardItemBase {

  use ChartTrait;

  /**
   * The database connection.
   */
  protected Connection $database;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): static {
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $instance->database = $container->get('database');

    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function buildSettingsForm(array $form, FormStateInterface $form_state, array $configuration): array {
    $form['chart_type'] = [
      '#type' => 'select',
      '#title' => $this->t('Chart type'),
      '#options' => $this->getAllowedStyles(),
      '#default_value' => $configuration['chart_type'] ?? 'pie',
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function buildRenderArray(array $configuration): array {
    $cid = 'comment_count';
    $cache = $this->getCache($cid);

    if ($cache) {
      $rows = $cache->data;
    }
    else {
      $query = $this->database->select('node_field_data', 'nfd');
      $query->join('comment_entity_statistics', 'ces', '[ces].[entity_id] = [nfd].[nid]');
      $query->fields('nfd', ['type']);
      $query->addExpression('SUM([ces].[comment_count])', 'count');
      $query->groupBy('type');
      $rows = [];

      foreach ($query->execute()->fetchAllAssoc('type') as $type => $count) {
        $rows[] = [$type, $count->count];
      }

      $this->setCache($cid, $rows, CacheBackendInterface::CACHE_PERMANENT, ['comment_list', 'node_list']);
    }

    if (isset($configuration['chart_type'])) {
      $this->setChartType($configuration['chart_type']);
    }

    $this->setLabels([
      $this->t('Node Type'),
      $this->t('Count'),
    ]);
    $this->setRows($rows);

    $build = $this->renderChart($configuration);
    $build['#cache']['tags'][] = 'comment_list';
    $build['#cache']['tags'][] = 'node_list';

    return $build;
  }

}
