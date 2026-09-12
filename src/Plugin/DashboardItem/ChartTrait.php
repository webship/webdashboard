<?php

declare(strict_types=1);

namespace Drupal\webdashboard\Plugin\DashboardItem;

use Drupal\Core\StringTranslation\StringTranslationTrait;

/**
 * Builds a chart from a table, drawn in the browser with Chart.js.
 */
trait ChartTrait {

  use StringTranslationTrait;

  /**
   * The table header labels.
   *
   * @var array
   */
  protected array $labels = [];

  /**
   * The table rows.
   *
   * @var array
   */
  protected array $rows = [];

  /**
   * The chart type.
   */
  protected string $type = 'pie';

  /**
   * Is the chart empty?
   */
  protected bool $empty = FALSE;

  /**
   * Sets the chart type.
   *
   * @param string $chart
   *   One of the keys of ::getAllowedStyles().
   */
  public function setChartType(string $chart): void {
    if (!\array_key_exists($chart, $this->getAllowedStyles())) {
      throw new \InvalidArgumentException(\sprintf('Chart type %s not allowed.', $chart));
    }

    $this->type = $chart;
  }

  /**
   * Adds a label.
   *
   * @param string $label
   *   The label to add.
   */
  public function addLabel(string $label): void {
    $this->labels[] = $label;
  }

  /**
   * Gets the allowed chart types.
   *
   * @return array
   *   The chart type labels, keyed by chart type.
   */
  public function getAllowedStyles(): array {
    return [
      'line' => $this->t('Lines'),
      'pie' => $this->t('Pies'),
      'bar' => $this->t('Bars'),
      'radar' => $this->t('Radar'),
      'polarArea' => $this->t('Polar area'),
      'doughnut' => $this->t('Doughnut'),
      'bubble' => $this->t('Bubbles'),
    ];
  }

  /**
   * Sets all labels.
   *
   * @param array $labels
   *   The labels.
   */
  public function setLabels(array $labels): void {
    $this->labels = $labels;
  }

  /**
   * Adds a row.
   *
   * @param array $row
   *   The row.
   */
  public function addRow(array $row): void {
    $this->rows[] = $row;
  }

  /**
   * Sets all rows.
   *
   * @param array $rows
   *   The rows.
   */
  public function setRows(array $rows): void {
    $this->rows = $rows;
  }

  /**
   * Flags the chart as empty.
   *
   * @param bool $empty
   *   Is the chart empty?
   */
  public function setEmpty(bool $empty): void {
    $this->empty = $empty;
  }

  /**
   * Renders the chart.
   *
   * @param array $configuration
   *   The configuration of the block placing the item.
   * @param bool $plain
   *   (optional) Render the table only, without the chart.
   *
   * @return array
   *   The render array.
   */
  public function renderChart(array $configuration = [], bool $plain = FALSE): array {
    if (\count($this->rows) === 0) {
      return [
        '#markup' => $this->t('No data found'),
      ];
    }

    $config = \Drupal::config('webdashboard.settings');
    $table = [
      '#type' => 'table',
      '#header' => $this->labels,
      '#rows' => $this->rows,
      '#attributes' => [
        'class' => ['dashboard-table', 'table'],
      ],
    ];
    $attributes = [
      'data-app' => 'chart',
      'data-chart-type' => $this->type,
    ];

    if (!empty($configuration['legend'])) {
      $attributes['data-chart-display-legend'] = '1';
    }

    $build = [
      '#prefix' => '<div>',
      '#suffix' => '</div>',
      '#attached' => [
        'library' => ['webdashboard/chart'],
        // The chart script reads its colors from this settings key.
        'drupalSettings' => [
          'dashboards' => [
            'colormap' => $config->get('colormap') ?: 'summer',
            'alpha' => ($config->get('alpha') ?: 40) / 100,
            'shades' => $config->get('shades') ?: 15,
          ],
        ],
      ],
      '#cache' => [
        'tags' => $config->getCacheTags(),
      ],
      'chart' => [
        '#type' => 'container',
        '#attributes' => $attributes,
        [
          '#type' => 'container',
          [
            '#type' => 'details',
            '#title' => $this->t('Show data'),
            '#open' => FALSE,
            'content' => $table,
          ],
        ],
      ],
    ];

    if ($plain) {
      unset($build['chart']['#attributes']);
    }

    return $build;
  }

}
