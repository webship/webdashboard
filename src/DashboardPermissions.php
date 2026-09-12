<?php

declare(strict_types=1);

namespace Drupal\webdashboard;

use Drupal\Core\DependencyInjection\AutowireTrait;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;

/**
 * Provides the view and personalize permissions of every dashboard.
 */
class DashboardPermissions implements ContainerInjectionInterface {

  use AutowireTrait;
  use StringTranslationTrait;

  public function __construct(
    protected EntityTypeManagerInterface $entityTypeManager,
  ) {}

  /**
   * Gets the permission to view a dashboard.
   *
   * @param \Drupal\Core\Entity\EntityInterface|string $dashboard
   *   The dashboard, or its ID.
   *
   * @return string
   *   The permission name.
   */
  public static function viewPermission(EntityInterface|string $dashboard): string {
    return \sprintf('can view %s webdashboard', \is_string($dashboard) ? $dashboard : $dashboard->id());
  }

  /**
   * Gets the permission to personalize a dashboard.
   *
   * @param \Drupal\Core\Entity\EntityInterface|string $dashboard
   *   The dashboard, or its ID.
   *
   * @return string
   *   The permission name.
   */
  public static function overridePermission(EntityInterface|string $dashboard): string {
    return \sprintf('can override %s webdashboard', \is_string($dashboard) ? $dashboard : $dashboard->id());
  }

  /**
   * Gets the permissions of every dashboard.
   *
   * @return array
   *   The permissions, keyed by permission name.
   */
  public function permissions(): array {
    $permissions = [];

    /** @var \Drupal\webdashboard\Entity\DashboardInterface $dashboard */
    foreach ($this->entityTypeManager->getStorage('webdashboard')->loadMultiple() as $dashboard) {
      $arguments = ['%dashboard' => $dashboard->label()];
      $dependencies = [
        $dashboard->getConfigDependencyKey() => [$dashboard->getConfigDependencyName()],
      ];

      $permissions[self::viewPermission($dashboard)] = [
        'title' => $this->t('Can view %dashboard dashboard', $arguments),
        'dependencies' => $dependencies,
      ];

      $permissions[self::overridePermission($dashboard)] = [
        'title' => $this->t('Can personalize %dashboard dashboard', $arguments),
        'description' => $this->t('Also needs the permission to use the Display Builder profile of the dashboard.'),
        'dependencies' => $dependencies,
      ];
    }

    return $permissions;
  }

}
