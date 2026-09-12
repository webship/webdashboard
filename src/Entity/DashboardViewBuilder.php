<?php

declare(strict_types=1);

namespace Drupal\webdashboard\Entity;

use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Entity\EntityViewBuilder;
use Drupal\Core\Session\AccountInterface;
use Drupal\display_builder\DisplayBuildableInterface;
use Drupal\display_builder\DisplayBuildablePluginManager;
use Drupal\ui_patterns\Element\ComponentElementBuilder;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Renders a dashboard from its Display Builder sources.
 *
 * A user who personalized the dashboard gets their own sources, everybody else
 * gets the default ones.
 */
class DashboardViewBuilder extends EntityViewBuilder {

  /**
   * The UI Patterns component element builder.
   */
  protected ComponentElementBuilder $componentElementBuilder;

  /**
   * The display buildable plugin manager.
   */
  protected DisplayBuildablePluginManager $displayBuildableManager;

  /**
   * The config factory.
   */
  protected ConfigFactoryInterface $configFactory;

  /**
   * The current user.
   */
  protected AccountInterface $currentUser;

  /**
   * {@inheritdoc}
   */
  public static function createInstance(ContainerInterface $container, EntityTypeInterface $entity_type): static {
    /** @var static $instance */
    $instance = parent::createInstance($container, $entity_type);
    $instance->componentElementBuilder = $container->get('ui_patterns.component_element_builder');
    $instance->displayBuildableManager = $container->get('plugin.manager.display_buildable');
    $instance->configFactory = $container->get('config.factory');
    $instance->currentUser = $container->get('current_user');

    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function view(EntityInterface $entity, $view_mode = 'full', $langcode = NULL): array {
    /** @var \Drupal\webdashboard\Entity\DashboardInterface $entity */
    $buildable = $this->getRenderedBuildable($entity);
    $contexts = $buildable->getRuntimeContexts([]);
    $fake_build = [];

    foreach ($buildable->getSourcesForRender() as $source_data) {
      $fake_build = $this->componentElementBuilder->buildSource($fake_build, 'content', [], $source_data, $contexts);
    }

    $build = [
      '#type' => 'container',
      '#attributes' => [
        'class' => ['webdashboard-container'],
      ],
      '#attached' => [
        'library' => ['webdashboard/core'],
      ],
      'content' => $fake_build['#slots']['content'] ?? [],
    ];

    // The render array follows what the sources decided, and what one user
    // sees depends on whether this user personalized the dashboard.
    $cacheability = CacheableMetadata::createFromRenderArray($fake_build)
      ->addCacheableDependency($entity)
      ->addCacheableDependency($this->configFactory->get('webdashboard.settings'))
      ->addCacheContexts(['user']);

    if ($this->currentUser->isAuthenticated()) {
      $cacheability->addCacheTags([$entity->getOverrideCacheTag($this->currentUser)]);
    }

    $cacheability->applyTo($build);

    return $build;
  }

  /**
   * Gets the buildable plugin whose sources the current user sees.
   *
   * @param \Drupal\webdashboard\Entity\DashboardInterface $entity
   *   The dashboard.
   *
   * @return \Drupal\display_builder\DisplayBuildableInterface
   *   The personalized dashboard of the current user when there is one, the
   *   default dashboard otherwise.
   */
  protected function getRenderedBuildable(DashboardInterface $entity): DisplayBuildableInterface {
    $plugin_id = 'webdashboard';
    $configuration = ['entity' => $entity];

    if ($this->currentUser->isAuthenticated() && $entity->isOverridden($this->currentUser)) {
      $plugin_id = 'webdashboard_override';
      $configuration['uid'] = (int) $this->currentUser->id();
    }

    /** @var \Drupal\display_builder\DisplayBuildableInterface $buildable */
    $buildable = $this->displayBuildableManager->createInstance($plugin_id, $configuration);

    return $buildable;
  }

}
