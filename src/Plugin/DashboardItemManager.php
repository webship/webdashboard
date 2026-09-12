<?php

declare(strict_types=1);

namespace Drupal\webdashboard\Plugin;

use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Plugin\DefaultPluginManager;
use Drupal\webdashboard\Attribute\DashboardItem;

/**
 * Provides the dashboard item plugin manager.
 */
class DashboardItemManager extends DefaultPluginManager {

  /**
   * Constructs a DashboardItemManager object.
   *
   * @param \Traversable $namespaces
   *   An object that implements \Traversable which contains the root paths
   *   keyed by the corresponding namespace to look for plugin implementations.
   * @param \Drupal\Core\Cache\CacheBackendInterface $cache_backend
   *   Cache backend instance to use.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $module_handler
   *   The module handler to invoke the alter hook with.
   */
  public function __construct(\Traversable $namespaces, CacheBackendInterface $cache_backend, ModuleHandlerInterface $module_handler) {
    parent::__construct('Plugin/DashboardItem', $namespaces, $module_handler, DashboardItemInterface::class, DashboardItem::class);
    $this->alterInfo('webdashboard_item_info');
    $this->setCacheBackend($cache_backend, 'webdashboard_item_plugins');
  }

}
