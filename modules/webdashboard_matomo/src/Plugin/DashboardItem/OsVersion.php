<?php

declare(strict_types=1);

namespace Drupal\webdashboard_matomo\Plugin\DashboardItem;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\webdashboard\Attribute\DashboardItem;

/**
 * Charts the visits per operating system.
 */
#[DashboardItem(
  id: 'matomo_os',
  label: new TranslatableMarkup('Operating systems'),
  category: new TranslatableMarkup('Dashboard: Matomo'),
)]
class OsVersion extends MatomoBase {

  /**
   * {@inheritdoc}
   */
  protected function buildReport(array $configuration): array {
    $response = $this->query('DevicesDetection.getOsFamilies', [
      'filter_limit' => 20,
      'period' => $configuration['period'],
      'date' => $this->getDateTranslated($configuration['date']),
      'flat' => 1,
    ]);

    if (empty($response)) {
      $this->setEmpty(TRUE);

      return $this->renderChart($configuration);
    }

    $this->buildDateRows($response, $this->t('Time'), ['nb_visits']);
    $this->setChartType($configuration['chart_type']);

    return $this->renderChart($configuration);
  }

}
