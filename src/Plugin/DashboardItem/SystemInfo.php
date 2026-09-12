<?php

declare(strict_types=1);

namespace Drupal\webdashboard\Plugin\DashboardItem;

use Drupal\Core\Extension\Requirement\RequirementSeverity;
use Drupal\Core\StringTranslation\PluralTranslatableMarkup;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Url;
use Drupal\system\SystemManager;
use Drupal\webdashboard\Attribute\DashboardItem;
use Drupal\webdashboard\Plugin\DashboardItemBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Counts the errors, warnings and checks of the status report.
 */
#[DashboardItem(
  id: 'system_info',
  label: new TranslatableMarkup('Show system info'),
  category: new TranslatableMarkup('Dashboard: System'),
)]
class SystemInfo extends DashboardItemBase {

  /**
   * The system manager.
   */
  protected SystemManager $systemManager;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): static {
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $instance->systemManager = $container->get('system.manager');

    return $instance;
  }

  /**
   * Counts the requirements per severity.
   *
   * @param array $requirements
   *   The requirements, as listed by the status report.
   *
   * @return array
   *   The counters with at least one requirement, keyed by severity status,
   *   each with an amount and a text.
   */
  public static function getCounter(array $requirements): array {
    RequirementSeverity::convertLegacyIntSeveritiesToEnums($requirements, __METHOD__);
    $amounts = ['error' => 0, 'warning' => 0, 'checked' => 0];

    foreach ($requirements as $requirement) {
      $status = ($requirement['severity'] ?? RequirementSeverity::Info)->status();

      if (isset($amounts[$status])) {
        $amounts[$status]++;
      }
    }

    $texts = [
      'error' => ['Error', 'Errors', []],
      'warning' => ['Warning', 'Warnings', []],
      'checked' => ['Checked', 'Checked', ['context' => 'Examined']],
    ];
    $counter = [];

    foreach (\array_filter($amounts) as $status => $amount) {
      [$singular, $plural, $options] = $texts[$status];
      $counter[$status] = [
        'amount' => $amount,
        // phpcs:ignore Drupal.Semantics.FunctionT.NotLiteralString
        'text' => new PluralTranslatableMarkup($amount, $singular, $plural, [], $options),
        'severity' => $status,
      ];
    }

    return $counter;
  }

  /**
   * {@inheritdoc}
   */
  public function buildRenderArray(array $configuration): array {
    $counter = static::getCounter($this->systemManager->listRequirements());
    $severity = match (TRUE) {
      isset($counter['error']) => 'error',
      isset($counter['warning']) => 'warning',
      default => 'checked',
    };
    $items = [];

    foreach ($counter as $count) {
      $items[] = [
        'url' => Url::fromRoute('system.status'),
        'title' => $this->t('@count @text', [
          '@count' => $count['amount'],
          '@text' => $count['text'],
        ]),
      ];
    }

    return [
      '#type' => 'container',
      '#attributes' => [
        'class' => ['status-report-errors', $severity],
      ],
      'child' => [
        '#theme' => 'webdashboard_admin_list',
        '#list' => $items,
      ],
      '#cache' => [
        'max-age' => 3600,
      ],
    ];
  }

}
