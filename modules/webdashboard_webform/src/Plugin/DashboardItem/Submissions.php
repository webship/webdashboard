<?php

declare(strict_types=1);

namespace Drupal\webdashboard_webform\Plugin\DashboardItem;

use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\webdashboard\Attribute\DashboardItem;
use Drupal\webdashboard\Plugin\DashboardItem\ChartTrait;
use Drupal\webdashboard\Plugin\DashboardItemBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Charts the webform submissions over time.
 */
#[DashboardItem(
  id: 'webform_submissions',
  label: new TranslatableMarkup('Submission statistic'),
  category: new TranslatableMarkup('Dashboard: Webform'),
)]
class Submissions extends DashboardItemBase {

  use ChartTrait;

  /**
   * The database connection.
   */
  protected Connection $database;

  /**
   * The entity type manager.
   */
  protected EntityTypeManagerInterface $entityTypeManager;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): static {
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $instance->database = $container->get('database');
    $instance->entityTypeManager = $container->get('entity_type.manager');

    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function buildSettingsForm(array $form, FormStateInterface $form_state, array $configuration): array {
    $webform = empty($configuration['webform']) ? NULL : $this->entityTypeManager->getStorage('webform')->load($configuration['webform']);

    $form['webform'] = [
      '#type' => 'entity_autocomplete',
      '#title' => $this->t('Webform'),
      '#description' => $this->t('Leave empty to chart the submissions of every webform.'),
      '#target_type' => 'webform',
      '#selection_handler' => 'default',
      '#default_value' => $webform,
    ];
    $form['period'] = [
      '#type' => 'select',
      '#title' => $this->t('Period'),
      '#options' => [
        'hour' => $this->t('Hour'),
        'day' => $this->t('Day'),
        'week' => $this->t('Week'),
        'month' => $this->t('Month'),
      ],
      '#default_value' => $configuration['period'] ?? 'day',
    ];
    $form['date'] = [
      '#type' => 'select',
      '#title' => $this->t('Date range'),
      '#options' => [
        'today' => $this->t('Today'),
        'yesterday' => $this->t('Yesterday'),
        'this_week' => $this->t('This week'),
        'this_month' => $this->t('This month'),
        'last_three_months' => $this->t('Last 3 months'),
        'last_six_months' => $this->t('Last 6 months'),
        'year' => $this->t('This year'),
      ],
      '#default_value' => $configuration['date'] ?? 'today',
    ];
    $form['chart_type'] = [
      '#type' => 'select',
      '#title' => $this->t('Chart type'),
      '#options' => $this->getAllowedStyles(),
      '#default_value' => $configuration['chart_type'] ?? 'bar',
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function buildRenderArray(array $configuration): array {
    $cid = \md5(\serialize($configuration));
    $cache = $this->getCache($cid);

    if ($cache) {
      $rows = $cache->data['rows'];
      $labels = $cache->data['labels'];
    }
    else {
      [$labels, $rows] = $this->querySubmissions($configuration);
      $this->setCache($cid, ['labels' => $labels, 'rows' => $rows], \time() + 1800, ['webform_submission_list']);
    }

    $this->setLabels($labels);
    $this->setRows($rows);
    $this->setChartType($configuration['chart_type'] ?? 'bar');

    $build = $this->renderChart($configuration);
    $build['#cache']['tags'][] = 'webform_submission_list';

    return $build;
  }

  /**
   * Counts the submissions per webform and per period.
   *
   * @param array $configuration
   *   The configuration of the block placing the item.
   *
   * @return array
   *   The table labels, then the table rows.
   */
  protected function querySubmissions(array $configuration): array {
    $query = $this->database->select('webform_submission', 'ws');

    if (!empty($configuration['webform'])) {
      $query->condition('webform_id', $configuration['webform']);
    }

    match ($configuration['date'] ?? 'today') {
      'yesterday' => $query->condition('ws.created', [\strtotime('yesterday'), \strtotime('today')], 'BETWEEN'),
      'this_week' => $query->condition('ws.created', \strtotime('this week'), '>='),
      'this_month' => $query->condition('ws.created', \strtotime('first day of this month'), '>='),
      'last_three_months' => $query->condition('ws.created', \strtotime('first day of this month -3 months'), '>='),
      'last_six_months' => $query->condition('ws.created', \strtotime('first day of this month -6 months'), '>='),
      'year' => $query->condition('ws.created', \strtotime('first day of january this year'), '>='),
      default => $query->condition('ws.created', \strtotime('today'), '>='),
    };

    $expression = match ($configuration['period'] ?? 'day') {
      'week' => "CONCAT(YEAR(FROM_UNIXTIME([ws].[created])), '-', WEEK(FROM_UNIXTIME([ws].[created])))",
      'month' => "CONCAT(YEAR(FROM_UNIXTIME([ws].[created])), '-', MONTH(FROM_UNIXTIME([ws].[created])))",
      'hour' => "CONCAT(YEAR(FROM_UNIXTIME([ws].[created])), '-', MONTH(FROM_UNIXTIME([ws].[created])), '-', DAY(FROM_UNIXTIME([ws].[created])), ' ', HOUR(FROM_UNIXTIME([ws].[created])), ':00')",
      default => "CONCAT(YEAR(FROM_UNIXTIME([ws].[created])), '-', MONTH(FROM_UNIXTIME([ws].[created])), '-', DAY(FROM_UNIXTIME([ws].[created])))",
    };
    $query->addExpression($expression, 'date');
    $query->addExpression('COUNT(*)', 'count');
    $query->fields('ws', ['webform_id']);
    $query->groupBy('date');
    $query->groupBy('webform_id');
    $query->orderBy('webform_id');
    $result = $query->execute()->fetchAll();

    $webforms = [];
    $rows = [];

    foreach ($result as $row) {
      $webforms[$row->webform_id] = $row->webform_id;
      $rows[$row->date][$row->webform_id] = $row->count;
    }

    foreach ($rows as $date => $row) {
      $values = [$date];

      foreach ($webforms as $webform_id) {
        $values[] = $row[$webform_id] ?? 0;
      }

      $rows[$date] = $values;
    }

    \ksort($rows);

    return [
      \array_merge([$this->t('Date')], \array_values($webforms)),
      \array_values($rows),
    ];
  }

}
