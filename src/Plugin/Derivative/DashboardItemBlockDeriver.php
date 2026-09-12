<?php

declare(strict_types=1);

namespace Drupal\webdashboard\Plugin\Derivative;

use Drupal\Component\Plugin\Derivative\DeriverBase;
use Drupal\Component\Plugin\PluginManagerInterface;
use Drupal\Core\Plugin\Discovery\ContainerDeriverInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides one block per dashboard item plugin.
 */
class DashboardItemBlockDeriver extends DeriverBase implements ContainerDeriverInterface {

  public function __construct(
    protected PluginManagerInterface $dashboardItemManager,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, $base_plugin_id): static {
    return new static($container->get('plugin.manager.webdashboard_item'));
  }

  /**
   * {@inheritdoc}
   */
  public function getDerivativeDefinitions($base_plugin_definition): array {
    foreach ($this->dashboardItemManager->getDefinitions() as $plugin_id => $definition) {
      $this->derivatives[$plugin_id] = $base_plugin_definition;
      $this->derivatives[$plugin_id]['admin_label'] = $definition['label'] ?? $plugin_id;
      $this->derivatives[$plugin_id]['category'] = $definition['category'] ?? $base_plugin_definition['category'];
    }

    return $this->derivatives;
  }

}
