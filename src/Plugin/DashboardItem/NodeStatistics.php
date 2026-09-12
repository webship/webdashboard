<?php

declare(strict_types=1);

namespace Drupal\webdashboard\Plugin\DashboardItem;

use Drupal\Core\Entity\EntityTypeBundleInfoInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\webdashboard\Attribute\DashboardItem;
use Drupal\webdashboard\Plugin\DashboardItemBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Charts the number of content items per content type.
 */
#[DashboardItem(
  id: 'node_statistics',
  label: new TranslatableMarkup('Show node types'),
  category: new TranslatableMarkup('Dashboard: Reports'),
)]
class NodeStatistics extends DashboardItemBase {

  use ChartTrait;

  /**
   * The entity type manager.
   */
  protected EntityTypeManagerInterface $entityTypeManager;

  /**
   * The entity type bundle info.
   */
  protected EntityTypeBundleInfoInterface $entityTypeInfo;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): static {
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $instance->entityTypeManager = $container->get('entity_type.manager');
    $instance->entityTypeInfo = $container->get('entity_type.bundle.info');

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
    if (!$this->entityTypeManager->hasDefinition('node')) {
      return [];
    }

    if (isset($configuration['chart_type'])) {
      $this->setChartType($configuration['chart_type']);
    }

    $result = $this->entityTypeManager->getStorage('node')->getAggregateQuery()
      ->accessCheck(FALSE)
      ->groupBy('type')
      ->aggregate('nid', 'COUNT')
      ->execute();

    $types = $this->entityTypeInfo->getBundleInfo('node');
    $rows = [];

    foreach ($result as $row) {
      $rows[] = [
        $types[$row['type']]['label'] ?? $row['type'],
        $row['nid_count'],
      ];
    }

    $this->setEmpty($rows === []);
    $this->setLabels([
      $this->t('Node Type'),
      $this->t('Count'),
    ]);
    $this->setRows($rows);

    $build = $this->renderChart($configuration);
    $build['#cache']['tags'][] = 'node_list';

    return $build;
  }

}
