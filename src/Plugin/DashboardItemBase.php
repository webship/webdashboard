<?php

declare(strict_types=1);

namespace Drupal\webdashboard\Plugin;

use Drupal\Component\Plugin\PluginBase;
use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Base class for dashboard item plugins.
 *
 * Services are assigned after the constructor has run, so a dashboard item
 * needing more of them overrides ::create() and assigns them on the parent
 * result.
 */
abstract class DashboardItemBase extends PluginBase implements DashboardItemInterface, ContainerFactoryPluginInterface {

  use StringTranslationTrait;

  /**
   * The dashboard cache backend.
   */
  protected CacheBackendInterface $cache;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): static {
    $instance = new static($configuration, $plugin_id, $plugin_definition);
    $instance->cache = $container->get('cache.webdashboard');

    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function buildSettingsForm(array $form, FormStateInterface $form_state, array $configuration): array {
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array $form, FormStateInterface $form_state, array $configuration): void {
  }

  /**
   * {@inheritdoc}
   */
  public function massageFormValues(array $form, FormStateInterface $form_state, array $configuration): void {
  }

  /**
   * Gets a cache entry, prefixed by the plugin ID.
   *
   * @param string $cid
   *   The cache ID.
   *
   * @return object|false
   *   The cache item, or FALSE when there is none.
   */
  protected function getCache(string $cid): object|false {
    return $this->cache->get($this->getPluginId() . ':' . $cid);
  }

  /**
   * Sets a cache entry, prefixed by the plugin ID.
   *
   * @param string $cid
   *   The cache ID.
   * @param mixed $data
   *   The data to cache.
   * @param int $expire
   *   (optional) The UNIX timestamp the entry expires at. Defaults to one hour
   *   from now.
   * @param array $tags
   *   (optional) Cache tags invalidating the entry.
   */
  protected function setCache(string $cid, mixed $data, ?int $expire = NULL, array $tags = []): void {
    $this->cache->set($this->getPluginId() . ':' . $cid, $data, $expire ?? \time() + 3600, $tags);
  }

}
