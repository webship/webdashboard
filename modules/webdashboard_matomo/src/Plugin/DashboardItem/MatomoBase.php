<?php

declare(strict_types=1);

namespace Drupal\webdashboard_matomo\Plugin\DashboardItem;

use Drupal\Component\Serialization\Json;
use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\matomo_reporting_api\MatomoQueryFactory;
use Drupal\webdashboard\Plugin\DashboardItem\ChartTrait;
use Drupal\webdashboard\Plugin\DashboardItemInterface;
use Drupal\webdashboard\Plugin\DashboardItemLazyBuildBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Base class for the Matomo dashboard items.
 */
abstract class MatomoBase extends DashboardItemLazyBuildBase {

  use ChartTrait;

  /**
   * The Matomo query factory.
   */
  protected MatomoQueryFactory $matomoQuery;

  /**
   * The date formatter.
   */
  protected DateFormatterInterface $dateFormatter;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): static {
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $instance->matomoQuery = $container->get('matomo.query_factory');
    $instance->dateFormatter = $container->get('date.formatter');

    return $instance;
  }

  /**
   * Queries Matomo and builds the render array of the item.
   *
   * @param array $configuration
   *   The configuration of the block placing the item.
   *
   * @return array
   *   The render array.
   */
  abstract protected function buildReport(array $configuration): array;

  /**
   * {@inheritdoc}
   */
  public static function lazyBuild(DashboardItemInterface $plugin, array $configuration): array {
    if (!$plugin instanceof static) {
      return [];
    }

    try {
      return $plugin->buildReport($configuration + [
        'period' => 'day',
        'date' => 'last_seven_days',
        'chart_type' => 'bar',
      ]);
    }
    catch (\Throwable $exception) {
      return [
        '#markup' => $plugin->t('Error occurred: @error', ['@error' => $exception->getMessage()]),
        '#cache' => [
          'max-age' => 0,
        ],
      ];
    }
  }

  /**
   * Gets the Matomo query factory.
   *
   * @return \Drupal\matomo_reporting_api\MatomoQueryFactory
   *   The Matomo query factory.
   */
  public function getQuery(): MatomoQueryFactory {
    return $this->matomoQuery;
  }

  /**
   * Translates a date range option to a Matomo date.
   *
   * @param string $period
   *   The date range option.
   *
   * @return string
   *   The Matomo date, or the option itself when it is not a range.
   */
  protected function getDateTranslated(string $period): string {
    [$start, $end] = match ($period) {
      'last_seven_days' => [\strtotime('-7 days'), \time()],
      'this_week' => [\strtotime('monday this week'), \strtotime('sunday this week')],
      'this_month' => [\strtotime('first day of this month'), \strtotime('last day of this month')],
      'last_three_months' => [\strtotime('first day of this month -2 months'), \strtotime('last day of this month')],
      'last_six_months' => [\strtotime('first day of this month -5 months'), \strtotime('last day of this month')],
      'year' => [\strtotime('first day of this year'), \strtotime('last day of this year')],
      default => [NULL, NULL],
    };

    if ($start === NULL) {
      return $period;
    }

    return \date('Y-m-d', (int) $start) . ',' . \date('Y-m-d', (int) $end);
  }

  /**
   * Builds the chart rows from a Matomo response keyed by date.
   *
   * @param array $response
   *   The Matomo response.
   * @param string|\Stringable $label
   *   The label of the date column.
   * @param array $columns
   *   The Matomo columns to show.
   */
  protected function buildDateRows(array $response, string|\Stringable $label, array $columns): void {
    $labels = [$label];

    foreach ($response as &$row) {
      foreach ($row as $key => $item) {
        $labels[$item['label']] = $item['label'];
        unset($row[$key]);
        $row[$item['label']] = $item;
      }

      \ksort($row);
    }

    unset($row);
    $items = [];

    foreach ($response as $date => $row) {
      $item = [$date];

      if (empty($row)) {
        foreach ($columns as $column) {
          $item[] = 0;
        }
      }

      foreach ($row as $values) {
        foreach ($columns as $column) {
          $item[] = $values[$column] ?? 0;
        }
      }

      $items[] = $item;
    }

    $this->setRows($items);
    $this->setLabels($labels);
  }

  /**
   * Queries the Matomo reporting API.
   *
   * @param string $action
   *   The Matomo API method.
   * @param array $parameters
   *   The query parameters.
   *
   * @return array
   *   The response, keyed by formatted date.
   */
  protected function query(string $action, array $parameters): array {
    $cid = \md5(\serialize([$action, $parameters]));

    if ($data = $this->getCache($cid)) {
      return $data->data;
    }

    $query = $this->matomoQuery->getQuery($action);
    $query->setParameters($parameters);
    $response = Json::decode($query->execute()->getRawResponse()->getBody()->getContents());

    if (($response['result'] ?? NULL) === 'error') {
      throw new \RuntimeException((string) $response['message']);
    }

    $items = [];

    foreach ($response as $date => $values) {
      $dates = \array_map(function (string $value): string {
        $timestamp = \strtotime($value);

        return $timestamp === FALSE ? $value : $this->dateFormatter->format($timestamp, 'custom', 'd.m.Y');
      }, \explode(',', (string) $date));

      $key = \count($dates) > 1 ? static::formatDateRange($dates[0], $dates[1]) : $dates[0];
      $items[$key] = $values;
    }

    $this->setCache($cid, $items, \time() + 600);

    return $items;
  }

  /**
   * Formats a short date range.
   *
   * @param string $start
   *   The first date.
   * @param string $end
   *   The last date.
   *
   * @return string
   *   The formatted date range.
   */
  public static function formatDateRange(string $start, string $end): string {
    $first = new \DateTime($start);
    $last = new \DateTime($end);

    return match (TRUE) {
      $first->format('Y-m-d') === $last->format('Y-m-d') => $first->format('d.m'),
      $first->format('Y-m') === $last->format('Y-m') => $first->format('d') . $last->format(' – d.m'),
      $first->format('Y') === $last->format('Y') => $first->format('d.m') . $last->format(' – d.m'),
      default => $first->format('d.m.Y') . $last->format(' – d.m.Y'),
    };
  }

  /**
   * {@inheritdoc}
   */
  public function buildSettingsForm(array $form, FormStateInterface $form_state, array $configuration): array {
    $form['period'] = [
      '#type' => 'select',
      '#title' => $this->t('Period'),
      '#options' => [
        'day' => $this->t('Day'),
        'week' => $this->t('Week'),
        'month' => $this->t('Month'),
        'year' => $this->t('Year'),
      ],
      '#default_value' => $configuration['period'] ?? 'day',
    ];
    $form['date'] = [
      '#type' => 'select',
      '#title' => $this->t('Date range'),
      '#options' => [
        'last_seven_days' => $this->t('Last seven days'),
        'this_week' => $this->t('This week'),
        'this_month' => $this->t('This month'),
        'last_three_months' => $this->t('Last 3 months'),
        'last_six_months' => $this->t('Last 6 months'),
        'year' => $this->t('This year'),
      ],
      '#default_value' => $configuration['date'] ?? 'last_seven_days',
    ];
    $form['chart_type'] = [
      '#type' => 'select',
      '#title' => $this->t('Chart type'),
      '#options' => $this->getAllowedStyles(),
      '#default_value' => $configuration['chart_type'] ?? 'bar',
    ];
    $form['legend'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Show legend'),
      '#default_value' => $configuration['legend'] ?? 0,
    ];

    return $form;
  }

}
