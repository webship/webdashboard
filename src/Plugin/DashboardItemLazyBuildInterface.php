<?php

declare(strict_types=1);

namespace Drupal\webdashboard\Plugin;

/**
 * Interface for dashboard items built in a placeholder.
 *
 * For items fetching remote or slow data, so they never hold the page back.
 */
interface DashboardItemLazyBuildInterface {

  /**
   * Builds the dashboard item, once the page is sent.
   *
   * @param \Drupal\webdashboard\Plugin\DashboardItemInterface $plugin
   *   The dashboard item plugin.
   * @param array $configuration
   *   The configuration of the block placing the item.
   *
   * @return array
   *   The render array.
   */
  public static function lazyBuild(DashboardItemInterface $plugin, array $configuration): array;

  /**
   * Lazy builder callback.
   *
   * @param string $plugin_id
   *   The dashboard item plugin ID.
   * @param string $configuration
   *   The JSON encoded configuration of the block placing the item.
   *
   * @return array
   *   The render array.
   */
  public static function lazyBuildPreRender(string $plugin_id, string $configuration): array;

}
