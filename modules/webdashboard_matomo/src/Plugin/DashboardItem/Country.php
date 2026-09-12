<?php

declare(strict_types=1);

namespace Drupal\webdashboard_matomo\Plugin\DashboardItem;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\webdashboard\Attribute\DashboardItem;

/**
 * Charts the visits per country.
 */
#[DashboardItem(
  id: 'matomo_countries',
  label: new TranslatableMarkup('Show per country'),
  category: new TranslatableMarkup('Dashboard: Matomo'),
)]
class Country extends MatomoBase {

  /**
   * {@inheritdoc}
   */
  protected function buildReport(array $configuration): array {
    $response = $this->query('UserCountry.getCountry', [
      'filter_limit' => 30,
      'period' => $configuration['period'],
      'date' => $this->getDateTranslated($configuration['date']),
      'flat' => 1,
    ]);

    $this->buildDateRows($response, $this->t('Time'), ['nb_visits']);
    $this->setChartType($configuration['chart_type']);

    return $this->renderChart($configuration);
  }

}
