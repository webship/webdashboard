<?php

declare(strict_types=1);

namespace Drupal\webdashboard_matomo\Plugin\DashboardItem;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Url;
use Drupal\webdashboard\Attribute\DashboardItem;

/**
 * Lists the most visited URLs per period.
 */
#[DashboardItem(
  id: 'matomo_top_urls',
  label: new TranslatableMarkup('Top urls'),
  category: new TranslatableMarkup('Dashboard: Matomo'),
)]
class TopUrls extends MatomoBase {

  /**
   * {@inheritdoc}
   */
  public function buildSettingsForm(array $form, FormStateInterface $form_state, array $configuration): array {
    $form = parent::buildSettingsForm($form, $form_state, $configuration);
    $form['chart_type']['#access'] = FALSE;
    $form['legend']['#access'] = FALSE;

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  protected function buildReport(array $configuration): array {
    $response = $this->query('Actions.getPageUrls', [
      'filter_limit' => 20,
      'period' => $configuration['period'],
      'date' => $this->getDateTranslated($configuration['date']),
      'flat' => 1,
      'expanded' => TRUE,
    ]);
    $lists = [];

    foreach ($response as $date => $row) {
      $items = [];

      foreach (\array_filter($row) as $item) {
        $url = $item['url'] ?? NULL;
        $items[] = $url
          ? ['#type' => 'link', '#title' => $url, '#url' => Url::fromUri($url)]
          : ['#plain_text' => $this->t('Unknown')];
      }

      if ($items !== []) {
        $lists[] = [
          '#theme' => 'item_list',
          '#title' => $date,
          '#items' => $items,
        ];
      }
    }

    return $lists;
  }

}
