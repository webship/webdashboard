<?php

declare(strict_types=1);

namespace Drupal\webdashboard\Entity;

use Drupal\Core\Config\Entity\ConfigEntityBase;
use Drupal\Core\Entity\Attribute\ConfigEntityType;
use Drupal\Core\Entity\EntityDeleteForm;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\Routing\DefaultHtmlRouteProvider;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\display_builder\DisplayBuildableInterface;
use Drupal\display_builder\Entity\ProfileInterface;
use Drupal\webdashboard\Form\DashboardForm;
use Drupal\webdashboard\Plugin\display_builder\Buildable\DashboardBuildable;
use Drupal\webdashboard\Plugin\display_builder\Buildable\DashboardOverrideBuildable;

/**
 * Defines the dashboard entity type.
 */
#[ConfigEntityType(
  id: 'webdashboard',
  label: new TranslatableMarkup('Dashboard'),
  label_collection: new TranslatableMarkup('Dashboards'),
  label_singular: new TranslatableMarkup('dashboard'),
  label_plural: new TranslatableMarkup('dashboards'),
  config_prefix: 'webdashboard',
  entity_keys: [
    'id' => 'id',
    'label' => 'admin_label',
    'weight' => 'weight',
    'uuid' => 'uuid',
  ],
  handlers: [
    'storage' => DashboardStorage::class,
    'access' => DashboardAccessControlHandler::class,
    'view_builder' => DashboardViewBuilder::class,
    'list_builder' => DashboardListBuilder::class,
    'form' => [
      'default' => DashboardForm::class,
      'add' => DashboardForm::class,
      'edit' => DashboardForm::class,
      'delete' => EntityDeleteForm::class,
    ],
    'route_provider' => [
      'html' => DefaultHtmlRouteProvider::class,
    ],
  ],
  links: [
    'canonical' => '/webdashboard/{webdashboard}',
    'add-form' => '/admin/structure/webdashboards/add',
    'edit-form' => '/admin/structure/webdashboards/manage/{webdashboard}',
    'delete-form' => '/admin/structure/webdashboards/manage/{webdashboard}/delete',
    'collection' => '/admin/structure/webdashboards',
    'display-builder' => '/admin/structure/webdashboards/manage/{webdashboard}/builder',
    'entity-permissions-form' => '/admin/structure/webdashboards/manage/{webdashboard}/permissions',
  ],
  admin_permission: 'administer webdashboard',
  label_count: [
    'singular' => '@count dashboard',
    'plural' => '@count dashboards',
  ],
  config_export: [
    'id',
    'admin_label',
    'category',
    'frontend',
    'weight',
    DisplayBuildableInterface::PROFILE_PROPERTY,
    DisplayBuildableInterface::SOURCES_PROPERTY,
  ],
)]
class Dashboard extends ConfigEntityBase implements DashboardInterface {

  /**
   * The machine name.
   *
   * Nullable, as a duplicated dashboard has no ID until it is saved.
   */
  protected ?string $id = NULL;

  /**
   * The administrative label.
   *
   * @var string|null
   */
  protected $admin_label;

  /**
   * The category.
   *
   * @var string|null
   */
  protected $category;

  /**
   * Show this dashboard always in the frontend theme.
   *
   * @var bool
   */
  protected $frontend = FALSE;

  /**
   * The weight, ordering dashboards in lists and in the toolbar.
   *
   * @var int
   */
  protected $weight = 0;

  /**
   * The Display Builder profile ID.
   *
   * @var string|null
   */
  protected $profile;

  /**
   * The nestable list of UI Patterns sources of the default dashboard.
   *
   * @var array
   */
  protected $sources = [];

  /**
   * {@inheritdoc}
   */
  public function getCategory(): ?string {
    return $this->category === NULL ? NULL : (string) $this->category;
  }

  /**
   * {@inheritdoc}
   */
  public function showAlwaysInFrontend(): bool {
    return (bool) $this->frontend;
  }

  /**
   * {@inheritdoc}
   */
  public function getProfile(): ?ProfileInterface {
    if (!$this->profile) {
      return NULL;
    }

    /** @var \Drupal\display_builder\Entity\ProfileInterface|null $profile */
    $profile = $this->entityTypeManager()->getStorage('display_builder_profile')->load($this->profile);

    return $profile;
  }

  /**
   * {@inheritdoc}
   */
  public function getSources(): array {
    return $this->sources ?? [];
  }

  /**
   * {@inheritdoc}
   */
  public function setSources(array $sources): static {
    $this->sources = $sources;

    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function isOverridden(?AccountInterface $account = NULL): bool {
    return $this->getOverriddenSources($account) !== NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function getOverriddenSources(?AccountInterface $account = NULL): ?array {
    $account ??= \Drupal::currentUser();

    if ($account->isAnonymous() || $this->isNew()) {
      return NULL;
    }

    $sources = \Drupal::service('user.data')->get(self::USER_DATA_MODULE, (int) $account->id(), (string) $this->id());

    return \is_array($sources) ? $sources : NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function setOverriddenSources(AccountInterface $account, array $sources): void {
    \Drupal::service('user.data')->set(self::USER_DATA_MODULE, (int) $account->id(), (string) $this->id(), $sources);
    \Drupal::service('cache_tags.invalidator')->invalidateTags([$this->getOverrideCacheTag($account)]);
  }

  /**
   * {@inheritdoc}
   */
  public function deleteOverride(AccountInterface $account): void {
    \Drupal::service('user.data')->delete(self::USER_DATA_MODULE, (int) $account->id(), (string) $this->id());
    \Drupal::service('cache_tags.invalidator')->invalidateTags([$this->getOverrideCacheTag($account)]);
  }

  /**
   * {@inheritdoc}
   */
  public function getOverrideCacheTag(AccountInterface $account): string {
    return \sprintf('webdashboard_override:%s:%d', $this->id(), $account->id());
  }

  /**
   * {@inheritdoc}
   */
  public function calculateDependencies(): static {
    parent::calculateDependencies();
    $profile = $this->getProfile();

    if (!$profile) {
      return $this;
    }

    $this->addDependency('config', $profile->getConfigDependencyName());
    $contexts = $this->displayBuildable()->getRuntimeContexts([]);
    /** @var \Drupal\ui_patterns\SourcePluginManager $source_manager */
    $source_manager = \Drupal::service('plugin.manager.ui_patterns_source');

    foreach ($this->getSources() as $source_data) {
      try {
        $source = $source_manager->getSource('', [], $source_data, $contexts);
        $this->addDependencies($source?->calculateDependencies() ?? []);
      }
      catch (\Throwable) {
        // A source whose plugin is gone has no dependency left to declare.
      }
    }

    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function onDependencyRemoval(array $dependencies): bool {
    $changed = parent::onDependencyRemoval($dependencies);
    $profile = $this->getProfile();

    // Losing its profile only stops the dashboard from being built, it is no
    // reason to delete the dashboard and the permissions granted on it.
    if ($profile && \in_array($profile->getConfigDependencyName(), $dependencies['config'] ?? [], TRUE)) {
      $this->profile = NULL;
      $changed = TRUE;
    }

    return $changed;
  }

  /**
   * {@inheritdoc}
   */
  public function postSave(EntityStorageInterface $storage, $update = TRUE): void {
    parent::postSave($storage, $update);

    if (!$this->getProfile()) {
      return;
    }

    $buildable = $this->displayBuildable();
    $buildable->initInstanceIfMissing();
    $instance = $buildable->getInstance();

    // Reset the builder state once the configuration is imported.
    if ($update && $instance && $this->isSyncing()) {
      $instance->setNewPresent($this->getSources(), new TranslatableMarkup('Synchronize display from imported configuration'));
      $instance->save();
    }
  }

  /**
   * {@inheritdoc}
   */
  public static function preDelete(EntityStorageInterface $storage, array $entities): void {
    parent::preDelete($storage, $entities);

    $dashboard_ids = [];

    /** @var \Drupal\webdashboard\Entity\DashboardInterface $entity */
    foreach ($entities as $entity) {
      \Drupal::service('user.data')->delete(self::USER_DATA_MODULE, NULL, (string) $entity->id());
      $dashboard_ids[] = (string) $entity->id();
    }

    // The builder instances of the default and of every personalized
    // dashboard are content entities, left behind by the config deletion.
    $instance_storage = \Drupal::entityTypeManager()->getStorage('display_builder_instance');
    $instance_ids = $instance_storage->getQuery()
      ->accessCheck(FALSE)
      ->condition('id', 'webdashboard', 'STARTS_WITH')
      ->execute();
    $delete = [];

    foreach ($instance_ids as $instance_id) {
      $params = DashboardBuildable::checkInstanceId((string) $instance_id) ?? DashboardOverrideBuildable::checkInstanceId((string) $instance_id);

      if (\in_array($params['webdashboard'] ?? NULL, $dashboard_ids, TRUE)) {
        $delete[] = $instance_id;
      }
    }

    if ($delete) {
      $instance_storage->delete($instance_storage->loadMultiple($delete));
    }
  }

  /**
   * Gets the buildable plugin of the default dashboard.
   *
   * @return \Drupal\display_builder\DisplayBuildableInterface
   *   The buildable plugin.
   */
  private function displayBuildable(): DisplayBuildableInterface {
    /** @var \Drupal\display_builder\DisplayBuildableInterface $buildable */
    $buildable = \Drupal::service('plugin.manager.display_buildable')->createInstance('webdashboard', ['entity' => $this]);

    return $buildable;
  }

}
