<?php

declare(strict_types=1);

namespace Drupal\webdashboard_matomo\Plugin\DashboardItem;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\webdashboard\Attribute\DashboardItem;

/**
 * Charts the visits per browser.
 */
#[DashboardItem(
  id: 'matomo_browser',
  label: new TranslatableMarkup('Browser'),
  category: new TranslatableMarkup('Dashboard: Matomo'),
)]
class Browser extends MatomoBase {

  /**
   * {@inheritdoc}
   */
  protected function buildReport(array $configuration): array {
    $response = $this->query('DevicesDetection.getBrowsers', [
      'filter_limit' => 20,
      'period' => $configuration['period'],
      'date' => $this->getDateTranslated($configuration['date']),
      'flat' => 1,
    ]);

    if (empty($response)) {
      $this->setEmpty(TRUE);

      return $this->renderChart($configuration);
    }

    $this->buildDateRows($response, $this->t('Date'), ['nb_visits']);
    $this->setChartType($configuration['chart_type']);

    return $this->renderChart($configuration);
  }

}
