<?php

declare(strict_types=1);

namespace Drupal\webdashboard\Plugin\display_builder\Buildable;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Cache\RefinableCacheableDependencyInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\Plugin\Context\EntityContext;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Url;
use Drupal\display_builder\Attribute\DisplayBuildable;
use Drupal\display_builder\DisplayBuildableInterface;
use Drupal\display_builder\DisplayBuildableOverrideInterface;
use Drupal\display_builder\DisplayBuildablePluginBase;
use Drupal\display_builder\Entity\ProfileInterface;
use Drupal\user\UserDataInterface;
use Drupal\webdashboard\Entity\DashboardInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * The dashboard of one user, overriding the default dashboard.
 *
 * Stored in the user data of that user, not in configuration: a personalized
 * dashboard belongs to a person, and does not travel with the site config.
 */
#[DisplayBuildable(
  id: 'webdashboard_override',
  label: new TranslatableMarkup('Personalized dashboard'),
  instance_prefix: 'webdashboard_override__',
)]
final class DashboardOverrideBuildable extends DisplayBuildablePluginBase implements DisplayBuildableOverrideInterface {

  /**
   * The instance prefix, also usable before the plugin is built.
   */
  public const PREFIX = 'webdashboard_override__';

  /**
   * The user data service.
   */
  protected UserDataInterface $userData;

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
   * - uid (int): The ID of the user owning the personalized dashboard.
   *
   * The dashboard itself may also be passed as 'entity', to work on an object
   * the caller already holds rather than a reloaded copy of it.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->configuration['uid'] = (int) ($configuration['uid'] ?? 0);

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
    $instance->userData = $container->get('user.data');
    $instance->dashboardLanguageManager = $container->get('language_manager');

    return $instance;
  }

  /**
   * Gets the dashboard and user IDs from an instance ID.
   *
   * The user ID is the last part: a dashboard ID is a machine name, and a
   * machine name may hold a double underscore of its own. Display Builder
   * 1.0.0-beta9 also asks for it.
   *
   * @param string $instance_id
   *   The Instance entity ID.
   *
   * @return array|null
   *   The dashboard ID keyed by 'webdashboard' and the user ID keyed by 'uid',
   *   or NULL for another instance.
   */
  public static function checkInstanceId(string $instance_id): ?array {
    if (!\str_starts_with($instance_id, self::PREFIX)) {
      return NULL;
    }

    $rest = \substr($instance_id, \strlen(self::PREFIX));
    $position = \strrpos($rest, '__');

    if ($position === FALSE) {
      return NULL;
    }

    $uid = \substr($rest, $position + 2);

    if (!\ctype_digit($uid)) {
      return NULL;
    }

    return [
      'webdashboard' => \substr($rest, 0, $position),
      'uid' => (int) $uid,
    ];
  }

  /**
   * {@inheritdoc}
   *
   * A personalized dashboard is only ever built by the person it belongs to.
   */
  public function access($operation, ?AccountInterface $account = NULL, $return_as_object = FALSE): AccessResultInterface|bool {
    $account ??= $this->currentUser;
    $dashboard = $this->getEntity();
    $uid = $this->configuration['uid'];

    if ($uid === 0) {
      $result = AccessResult::neutral();
    }
    elseif ($account->isAnonymous() || (int) $account->id() !== $uid) {
      $result = AccessResult::forbidden()->cachePerUser();
    }
    elseif (!$dashboard) {
      $result = AccessResult::neutral();
    }
    else {
      $result = $dashboard->access('override', $account, TRUE);

      if ($result instanceof RefinableCacheableDependencyInterface) {
        $result->addCacheContexts(['user']);
      }
    }

    return $return_as_object ? $result : $result->isAllowed();
  }

  /**
   * {@inheritdoc}
   */
  public function toUrl(string $rel = 'canonical'): ?Url {
    $id = $this->getEntity()?->id();

    return match ($rel) {
      'canonical' => $id === NULL ? NULL : Url::fromRoute('entity.webdashboard.canonical', ['webdashboard' => $id]),
      'edit-form', 'collection', 'add-form' => NULL,
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
      return Url::fromRoute('<front>');
    }

    return Url::fromRoute('entity.webdashboard.override', ['webdashboard' => $params['webdashboard']]);
  }

  /**
   * {@inheritdoc}
   *
   * Display Builder 1.0.0-beta9 asks for it; later releases use ::toUrl().
   */
  public static function getDisplayUrlFromInstanceId(string $instance_id): Url {
    $params = self::checkInstanceId($instance_id);

    if (!$params) {
      return Url::fromRoute('<front>');
    }

    return Url::fromRoute('entity.webdashboard.canonical', ['webdashboard' => $params['webdashboard']]);
  }

  /**
   * {@inheritdoc}
   */
  public function getDisplayLabel(): ?string {
    $entity = $this->getEntity();
    $account = $this->getAccount();

    if (!$entity || !$account) {
      return NULL;
    }

    return $this->composeDisplayLabel((string) $entity->label(), $account->getDisplayName());
  }

  /**
   * {@inheritdoc}
   */
  public function getBuilderUrl(): Url {
    return Url::fromRoute('entity.webdashboard.override', ['webdashboard' => $this->getEntity()?->id()]);
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
    $account = $this->getAccount();

    if (!$account) {
      return [];
    }

    return $this->getEntity()?->getOverriddenSources($account) ?? [];
  }

  /**
   * {@inheritdoc}
   */
  public function publish(): void {
    $account = $this->getAccount();
    $entity = $this->getEntity();

    if (!$account || !$entity) {
      return;
    }

    $entity->setOverriddenSources($account, $this->getInstance()->getSources());
  }

  /**
   * {@inheritdoc}
   */
  public function getDefaultSources(): array {
    return $this->getEntity()?->getSources() ?? [];
  }

  /**
   * {@inheritdoc}
   */
  public function deleteOverride(): void {
    $account = $this->getAccount();
    $entity = $this->getEntity();

    if ($account && $entity) {
      $entity->deleteOverride($account);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function getOverridden(): DisplayBuildableInterface {
    /** @var \Drupal\display_builder\DisplayBuildableInterface $buildable */
    $buildable = $this->displayBuildableManager->createInstance('webdashboard', ['entity' => $this->getEntity()]);

    return $buildable;
  }

  /**
   * {@inheritdoc}
   */
  public function getInstanceId(): ?string {
    $entity = $this->getEntity();
    $uid = $this->configuration['uid'];

    if (!$entity || $entity->isNew() || $uid === 0) {
      return NULL;
    }

    return \sprintf('%s%s__%d', self::PREFIX, $entity->id(), $uid);
  }

  /**
   * {@inheritdoc}
   *
   * Display Builder 1.0.0-beta9 asks for it; later releases read the plugin off each Instance.
   */
  public function collectInstances(): array {
    $instances = [];
    $storage = $this->entityTypeManager->getStorage('webdashboard');

    foreach ($this->userData->get(DashboardInterface::USER_DATA_MODULE) as $uid => $dashboards) {
      foreach (\array_keys($dashboards) as $dashboard_id) {
        $dashboard = $storage->load($dashboard_id);

        if (!$dashboard instanceof DashboardInterface || !$dashboard->getProfile()) {
          continue;
        }

        /** @var \Drupal\display_builder\DisplayBuildableInterface $buildable */
        $buildable = $this->displayBuildableManager->createInstance('webdashboard_override', [
          'entity' => $dashboard,
          'uid' => (int) $uid,
        ]);
        $buildable->initInstanceIfMissing();
        $instances[(string) $buildable->getInstanceId()] = $buildable->getInstance();
      }
    }

    return $instances;
  }

  /**
   * {@inheritdoc}
   */
  public function getRuntimeContexts(array $unqualified_context_ids): array {
    $entity = $this->getEntity();

    return $entity ? ['webdashboard' => EntityContext::fromEntity($entity)] : [];
  }

  /**
   * {@inheritdoc}
   */
  protected function getInitialSources(): array {
    $account = $this->getAccount();
    $entity = $this->getEntity();

    if (!$entity) {
      return [];
    }

    // Keep what the user already personalized, otherwise start from the
    // default dashboard.
    if ($account && ($sources = $entity->getOverriddenSources($account)) !== NULL) {
      $this->initialDataSource = 'override';

      return $sources;
    }

    $this->initialDataSource = 'default';

    return $entity->getSources();
  }

  /**
   * {@inheritdoc}
   */
  protected function getInitializationMessage(): TranslatableMarkup {
    if ($this->initialDataSource === 'override') {
      return $this->t('Initialize display from the personalized dashboard');
    }

    return $this->t('Copy display from the default dashboard');
  }

  /**
   * Gets the dashboard this plugin overrides.
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
   * Gets the user owning the personalized dashboard.
   *
   * @return \Drupal\Core\Session\AccountInterface|null
   *   The user, or NULL when it no longer exists.
   */
  protected function getAccount(): ?AccountInterface {
    if ($this->configuration['uid'] === 0) {
      return NULL;
    }

    if ((int) $this->currentUser->id() === $this->configuration['uid']) {
      return $this->currentUser;
    }

    /** @var \Drupal\Core\Session\AccountInterface|null $account */
    $account = $this->entityTypeManager->getStorage('user')->load($this->configuration['uid']);

    return $account;
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
