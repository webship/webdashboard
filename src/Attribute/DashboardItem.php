<?php

declare(strict_types=1);

namespace Drupal\webdashboard\Attribute;

use Drupal\Component\Plugin\Attribute\Plugin;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Defines a dashboard item plugin.
 *
 * Every dashboard item is offered as a block, so it can be placed on a
 * dashboard from the Display Builder block library.
 *
 * @see \Drupal\webdashboard\Plugin\DashboardItemManager
 * @see \Drupal\webdashboard\Plugin\Block\DashboardItemBlock
 */
#[\Attribute(\Attribute::TARGET_CLASS)]
final class DashboardItem extends Plugin {

  /**
   * Constructs a DashboardItem attribute.
   *
   * @param string $id
   *   The plugin ID.
   * @param \Drupal\Core\StringTranslation\TranslatableMarkup|null $label
   *   The human-readable name of the dashboard item.
   * @param \Drupal\Core\StringTranslation\TranslatableMarkup|null $category
   *   The block library category of the dashboard item.
   * @param class-string|null $deriver
   *   (optional) The deriver class.
   */
  public function __construct(
    public readonly string $id,
    public readonly ?TranslatableMarkup $label = NULL,
    public readonly ?TranslatableMarkup $category = NULL,
    public readonly ?string $deriver = NULL,
  ) {}

}
