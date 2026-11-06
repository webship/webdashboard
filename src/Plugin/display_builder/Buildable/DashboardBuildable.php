<?php

declare(strict_types=1);

namespace Drupal\webdashboard\Plugin\display_builder\Buildable;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\Plugin\Context\EntityContext;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Url;
use Drupal\display_builder\Attribute\DisplayBuildable;
use Drupal\display_builder\DisplayBuildablePluginBase;
use Drupal\display_builder\DisplayReference;
use Drupal\display_builder\Entity\Instance;
use Drupal\display_builder\Entity\ProfileInterface;
use Drupal\webdashboard\Entity\DashboardInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * The default dashboard, stored in the dashboard configuration entity.
 */
#[DisplayBuildable(
  id: 'webdashboard',
  label: new TranslatableMarkup('Dashboard'),
  instance_prefix: 'webdashboard__',
)]
final class DashboardBuildable extends DisplayBuildablePluginBase {

  /**
   * The instance prefix, also usable before the plugin is built.
   */
  public const PREFIX = 'webdashboard__';

  /**
   * The language manager, for the translation languages of the dashboard.
   */
  protected LanguageManagerInterface $dashboardLanguageManager;

  /**
   * The dashboard, once passed in or loaded by ::getEntity().
   */
  protected ?DashboardInterface $entity = NULL;

  /**
   * {@inheritdoc}
   *
   * Configuration, as stored in the Instance entity:
   * - entity_id (string): The dashboard ID.
   *
   * The dashboard itself may also be passed as 'entity', to work on an object
   * the caller already holds rather than a reloaded copy of it.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);

    if (!isset($configuration['entity'])) {
      return;
    }

    $this->entity = $configuration['entity'];
    unset($this->configuration['entity']);
    $this->configuration['entity_id'] = $this->entity->id();
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): static {
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $instance->dashboardLanguageManager = $container->get('language_manager');

    return $instance;
  }

  /**
   * Gets the dashboard ID from an instance ID.
   *
   * Display Builder 1.0.0-beta9 also asks for it.
   *
   * @param string $instance_id
   *   The Instance entity ID.
   *
   * @return array|null
   *   The dashboard ID, keyed by 'webdashboard', or NULL for another instance.
   */
  public static function checkInstanceId(string $instance_id): ?array {
    if (!\str_starts_with($instance_id, self::PREFIX)) {
      return NULL;
    }

    return [
      'webdashboard' => \substr($instance_id, \strlen(self::PREFIX)),
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function access($operation, ?AccountInterface $account = NULL, $return_as_object = FALSE): AccessResultInterface|bool {
    $account ??= $this->currentUser;
    $result = AccessResult::allowedIfHasPermission($account, 'administer webdashboard');

    return $return_as_object ? $result : $result->isAllowed();
  }

  /**
   * {@inheritdoc}
   */
  public function toUrl(string $rel = 'canonical'): ?Url {
    $id = $this->getEntity()?->id();

    return match ($rel) {
      'canonical', 'edit-form' => $id === NULL ? NULL : Url::fromRoute('entity.webdashboard.edit_form', ['webdashboard' => $id]),
      'collection' => $this->getCollectionUrl(),
      'add-form' => $this->getAddUrl(),
      default => throw new \InvalidArgumentException(\sprintf('Unknown link relationship "%s".', $rel)),
    };
  }

  /**
   * {@inheritdoc}
   *
   * Display Builder 1.0.0-beta9 asks for it; later releases use ::getBuilderUrl().
   */
  public static function getUrlFromInstanceId(string $instance_id): Url {
    $params = self::checkInstanceId($instance_id);

    if (!$params) {
      return Url::fromRoute('entity.webdashboard.collection');
    }

    return Url::fromRoute('entity.webdashboard.display_builder', $params);
  }

  /**
   * {@inheritdoc}
   *
   * Display Builder 1.0.0-beta9 asks for it; later releases use ::toUrl().
   */
  public static function getDisplayUrlFromInstanceId(string $instance_id): Url {
    $params = self::checkInstanceId($instance_id);

    if (!$params) {
      return Url::fromRoute('entity.webdashboard.collection');
    }

    return Url::fromRoute('entity.webdashboard.edit_form', $params);
  }

  /**
   * {@inheritdoc}
   */
  public function getDisplayLabel(): ?string {
    $label = $this->getEntity()?->label();

    return $label === NULL ? NULL : (string) $label;
  }

  /**
   * {@inheritdoc}
   */
  public function getBuilderUrl(): Url {
    return Url::fromRoute('entity.webdashboard.display_builder', ['webdashboard' => $this->getEntity()?->id()]);
  }

  /**
   * {@inheritdoc}
   *
   * Display Builder 1.0.0-beta9 asks for it; later releases use ::toUrl().
   */
  public function getCollectionUrl(): Url {
    return Url::fromRoute('entity.webdashboard.collection');
  }

  /**
   * {@inheritdoc}
   *
   * Display Builder 1.0.0-beta9 asks for it; later releases use ::toUrl().
   */
  public function getAddUrl(): Url {
    return Url::fromRoute('entity.webdashboard.add_form');
  }

  /**
   * {@inheritdoc}
   */
  public function getProfile(): ?ProfileInterface {
    return $this->getEntity()?->getProfile();
  }

  /**
   * {@inheritdoc}
   */
  public function getSources(): array {
    return $this->getEntity()?->getSources() ?? [];
  }

  /**
   * {@inheritdoc}
   */
  public function publish(): void {
    $entity = $this->getEntity();
    $entity->setSources($this->getInstance()->getSources());
    $entity->save();
  }

  /**
   * {@inheritdoc}
   */
  public function getInstanceId(): ?string {
    $entity = $this->getEntity();

    // Usually an entity is new if no ID exists for it yet.
    if (!$entity || $entity->isNew()) {
      return NULL;
    }

    return self::PREFIX . $entity->id();
  }

  /**
   * {@inheritdoc}
   *
   * Display Builder 1.0.0-beta9 asks for it; later releases read the plugin off each Instance.
   */
  public function collectInstances(): array {
    $instances = [];

    /** @var \Drupal\webdashboard\Entity\DashboardInterface $dashboard */
    foreach ($this->entityTypeManager->getStorage('webdashboard')->loadMultiple() as $dashboard) {
      if (!$dashboard->getProfile()) {
        continue;
      }

      /** @var \Drupal\display_builder\DisplayBuildableInterface $buildable */
      $buildable = $this->displayBuildableManager->createInstance('webdashboard', ['entity' => $dashboard]);
      $buildable->initInstanceIfMissing();
      $instances[(string) $buildable->getInstanceId()] = $buildable->getInstance();
    }

    return $instances;
  }

  /**
   * {@inheritdoc}
   *
   * Read-only: sources are read off the dashboards, never off an Instance.
   */
  public function collectDisplays(array $options = []): array {
    $references = [];

    if (!$this->access('update')) {
      return $references;
    }

    /** @var \Drupal\webdashboard\Entity\DashboardInterface $dashboard */
    foreach ($this->entityTypeManager->getStorage('webdashboard')->loadMultiple() as $dashboard) {
      /** @var \Drupal\display_builder\DisplayBuildableInterface $buildable */
      $buildable = $this->displayBuildableManager->createInstance('webdashboard', ['entity' => $dashboard]);
      $instance_id = $buildable->getInstanceId();

      if ($instance_id === NULL) {
        continue;
      }

      $built = $dashboard->getProfile() !== NULL;
      $sources = $dashboard->getSources();
      $settings_url = $dashboard->toUrl('edit-form');

      $references[] = new DisplayReference(
        instanceId: $instance_id,
        kind: $buildable->label(),
        label: $buildable->getDisplayLabel() ?? $instance_id,
        url: $built ? $buildable->getBuilderUrl() : $settings_url,
        built: $built,
        empty: $built && empty($sources),
        settingsUrl: $settings_url,
        publishedHash: $built ? self::publishedHash($sources) : NULL,
      );
    }

    return $references;
  }

  /**
   * {@inheritdoc}
   */
  public function getRuntimeContexts(array $unqualified_context_ids): array {
    $entity = $this->getEntity();

    return $entity ? ['webdashboard' => EntityContext::fromEntity($entity)] : [];
  }

  /**
   * Hashes published sources the way the Instances panel compares them.
   *
   * @param array $sources
   *   The published sources.
   *
   * @return int
   *   The hash, node IDs left out.
   */
  private static function publishedHash(array $sources): int {
    // Display Builder 1.0.0-beta10 hashes in the plugin base, beta9 on Instance.
    if (\method_exists(self::class, 'contentHash')) {
      return self::contentHash($sources);
    }

    // Called by name: static analysis runs against beta10, which dropped it.
    return \call_user_func([Instance::class, 'getContentHash'], $sources);
  }

  /**
   * {@inheritdoc}
   */
  protected function getInitializationMessage(): TranslatableMarkup {
    return $this->t('Initialize display from the dashboard configuration');
  }

  /**
   * Gets the dashboard this plugin builds.
   *
   * Loaded on demand: the constructor runs before ::create() has injected the
   * entity type manager.
   *
   * @return \Drupal\webdashboard\Entity\DashboardInterface|null
   *   The dashboard, or NULL if the configured one no longer exists.
   */
  protected function getEntity(): ?DashboardInterface {
    if ($this->entity === NULL && isset($this->configuration['entity_id'])) {
      /** @var \Drupal\webdashboard\Entity\DashboardInterface|null $entity */
      $entity = $this->entityTypeManager->getStorage('webdashboard')->load($this->configuration['entity_id']);
      $this->entity = $entity;
    }

    return $this->entity;
  }

  /**
   * {@inheritdoc}
   *
   * The dashboard is a configuration entity: it can be translated to every
   * language of the site.
   */
  public function getTranslationLanguages($include_default = TRUE): array {
    $languages = $this->dashboardLanguageManager->getLanguages();
    if (!$include_default) {
      unset($languages[$this->dashboardLanguageManager->getDefaultLanguage()->getId()]);
    }
    return $languages;
  }

}
