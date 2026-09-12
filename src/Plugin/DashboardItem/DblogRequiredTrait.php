<?php

declare(strict_types=1);

namespace Drupal\webdashboard\Plugin\DashboardItem;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Url;

/**
 * The message of the dashboard items reading the Database Logging messages.
 */
trait DblogRequiredTrait {

  /**
   * Builds the message shown while Database Logging is not installed.
   *
   * @return array
   *   The render array.
   */
  public static function dblogRequired(): array {
    return [
      '#theme' => 'webdashboard_admin_list',
      '#list' => [
        [
          'title' => new TranslatableMarkup('DBLog module is not enabled.'),
          'description' => new TranslatableMarkup('DBLog module must enabled to show this report.'),
          'url' => Url::fromRoute('system.modules_list'),
        ],
      ],
      '#cache' => [
        'tags' => ['config:core.extension'],
      ],
    ];
  }

}
