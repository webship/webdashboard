<?php

declare(strict_types=1);

namespace Drupal\webdashboard\Plugin;

use Drupal\Component\Serialization\Json;
use Drupal\Core\Security\TrustedCallbackInterface;

/**
 * Base class for dashboard items built in a placeholder.
 */
abstract class DashboardItemLazyBuildBase extends DashboardItemBase implements DashboardItemLazyBuildInterface, TrustedCallbackInterface {

  /**
   * {@inheritdoc}
   */
  public static function lazyBuildPreRender(string $plugin_id, string $configuration): array {
    $configuration = Json::decode($configuration) ?? [];
    /** @var \Drupal\webdashboard\Plugin\DashboardItemInterface $plugin */
    $plugin = \Drupal::service('plugin.manager.webdashboard_item')->createInstance($plugin_id, $configuration);

    return static::lazyBuild($plugin, $configuration);
  }

  /**
   * {@inheritdoc}
   */
  public function buildRenderArray(array $configuration): array {
    return [
      '#lazy_builder' => [
        static::class . '::lazyBuildPreRender',
        [
          $this->getPluginId(),
          Json::encode($configuration),
        ],
      ],
      '#create_placeholder' => TRUE,
    ];
  }

  /**
   * {@inheritdoc}
   */
  public static function trustedCallbacks(): array {
    return ['lazyBuildPreRender'];
  }

}
