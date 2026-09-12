<?php

declare(strict_types=1);

namespace Drupal\webdashboard_matomo\Plugin\DashboardItem;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\webdashboard\Attribute\DashboardItem;

/**
 * Charts the visit summary per period.
 */
#[DashboardItem(
  id: 'matomo_visit_statistic',
  label: new TranslatableMarkup('Visit report'),
  category: new TranslatableMarkup('Dashboard: Matomo'),
)]
class VisitStatistic extends MatomoBase {

  /**
   * Gets the Matomo columns that can be charted.
   *
   * @return array
   *   The column labels, keyed by Matomo column.
   */
  protected function getChartColumns(): array {
    return [
      'nb_visits' => $this->t('Visits'),
      'avg_time_on_site' => $this->t('Average time on site'),
      'nb_uniq_visitors' => $this->t('Unique visitors'),
      'nb_actions' => $this->t('Actions'),
      'sum_visit_length' => $this->t('Visit length summary'),
      'max_actions' => $this->t('Max actions'),
      'nb_users' => $this->t('Users'),
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function buildSettingsForm(array $form, FormStateInterface $form_state, array $configuration): array {
    $form = parent::buildSettingsForm($form, $form_state, $configuration);
    $form['fields'] = [
      '#type' => 'checkboxes',
      '#title' => $this->t('Stats to show'),
      '#options' => $this->getChartColumns(),
      '#default_value' => \array_values(\array_filter($configuration['fields'] ?? [])),
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  protected function buildReport(array $configuration): array {
    $fields = \array_values(\array_filter($configuration['fields'] ?? ['nb_visits']));
    $response = $this->query('VisitsSummary.get', [
      'filter_limit' => 20,
      'period' => $configuration['period'],
      'date' => $this->getDateTranslated($configuration['date']),
      'flat' => 1,
      'columns' => $fields,
    ]);
    $rows = [];

    foreach ($response as $date => $row) {
      $values = [$date];

      foreach ($fields as $field) {
        $values[] = $row[0][$field] ?? 0;
      }

      $rows[] = $values;
    }

    $column_labels = $this->getChartColumns();
    $labels = [$this->t('Period')];

    foreach ($fields as $field) {
      $labels[] = $column_labels[$field] ?? $field;
    }

    $this->setRows($rows);
    $this->setLabels($labels);
    $this->setChartType($configuration['chart_type']);

    return $this->renderChart($configuration);
  }

}
