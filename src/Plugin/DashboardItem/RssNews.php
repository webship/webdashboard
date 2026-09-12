<?php

declare(strict_types=1);

namespace Drupal\webdashboard\Plugin\DashboardItem;

use Drupal\Component\Utility\Xss;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Url;
use Drupal\webdashboard\Attribute\DashboardItem;
use Drupal\webdashboard\Plugin\DashboardItemInterface;
use Drupal\webdashboard\Plugin\DashboardItemLazyBuildBase;
use Laminas\Feed\Reader\ExtensionManager;
use Laminas\Feed\Reader\ExtensionPluginManager;
use Laminas\Feed\Reader\Reader;

/**
 * Shows the latest items of an RSS or Atom feed.
 */
#[DashboardItem(
  id: 'rss_news',
  label: new TranslatableMarkup('Show rss news'),
  category: new TranslatableMarkup('Dashboard: Extras'),
)]
class RssNews extends DashboardItemLazyBuildBase {

  /**
   * How long a feed is cached, in seconds.
   */
  public const CACHE_TIME = 1800;

  /**
   * Fetches the feed items.
   *
   * @param string $plugin_id
   *   The plugin ID, prefixing the cache ID.
   * @param string $uri
   *   The feed URI.
   *
   * @return array
   *   The feed items.
   */
  public static function readSource(string $plugin_id, string $uri): array {
    /** @var \Drupal\Core\Cache\CacheBackendInterface $cache */
    $cache = \Drupal::service('cache.webdashboard');
    $cid = $plugin_id . ':' . \md5($uri);

    if ($data = $cache->get($cid)) {
      return $data->data;
    }

    Reader::setExtensionManager(new ExtensionManager(new ExtensionPluginManager()));
    $response = \Drupal::httpClient()->request('GET', $uri);
    $channel = Reader::importString($response->getBody()->getContents());
    $items = [];

    foreach ($channel as $item) {
      $image = NULL;
      $enclosure = $item->getEnclosure();

      if ($enclosure && !empty($enclosure->type) && !empty($enclosure->url) && \explode('/', $enclosure->type)[0] === 'image') {
        $image = $enclosure->url;
      }

      $date = $item->getDateModified() ?? $item->getDateCreated();
      $items[] = [
        'title' => $item->getTitle(),
        'link' => $item->getLink(),
        'image' => $image,
        'description' => $item->getDescription(),
        'date' => $date?->format(\DATE_ATOM),
      ];
    }

    $cache->set($cid, $items, \time() + static::CACHE_TIME);

    return $items;
  }

  /**
   * {@inheritdoc}
   */
  public function buildSettingsForm(array $form, FormStateInterface $form_state, array $configuration): array {
    $form['uri'] = [
      '#type' => 'url',
      '#required' => TRUE,
      '#title' => $this->t('Feed URL or website url'),
      '#default_value' => $configuration['uri'] ?? '',
    ];
    $form['max_items'] = [
      '#type' => 'number',
      '#title' => $this->t('How many items to display'),
      '#min' => 1,
      '#default_value' => $configuration['max_items'] ?? 5,
    ];
    $form['show_description'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Show description'),
      '#default_value' => $configuration['show_description'] ?? FALSE,
    ];
    $form['show_images'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Show images'),
      '#default_value' => $configuration['show_images'] ?? FALSE,
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public static function lazyBuild(DashboardItemInterface $plugin, array $configuration): array {
    $uri = (string) ($configuration['uri'] ?? '');

    if ($uri === '') {
      return [];
    }

    try {
      $items = \array_slice(static::readSource($plugin->getPluginId(), $uri), 0, (int) ($configuration['max_items'] ?? 5));
      /** @var \Drupal\Core\Datetime\DateFormatterInterface $date_formatter */
      $date_formatter = \Drupal::service('date.formatter');
      $list = [];

      foreach ($items as $item) {
        $date = $item['date'] ? $date_formatter->format((int) \strtotime($item['date']), 'short') : '';
        $description = $date;

        if (!empty($configuration['show_description'])) {
          $description = [
            '#markup' => $date . '<br>' . Xss::filter((string) $item['description'], ['img', 'a', 'ul', 'li', 'p']),
          ];
        }

        $list[] = [
          'url' => Url::fromUri($item['link']),
          'title' => $item['title'],
          'description' => $description,
          'image' => !empty($configuration['show_images']) ? $item['image'] : NULL,
        ];
      }

      return [
        '#theme' => 'webdashboard_admin_list',
        '#list' => $list,
        '#cache' => [
          'max-age' => static::CACHE_TIME,
        ],
      ];
    }
    catch (\Throwable $exception) {
      \Drupal::logger('webdashboard')->error('Could not read the feed @url: @message', [
        '@url' => $uri,
        '@message' => $exception->getMessage(),
      ]);

      return [
        '#markup' => new TranslatableMarkup('Could not read @url', ['@url' => $uri]),
        '#cache' => [
          'max-age' => 0,
        ],
      ];
    }
  }

}
