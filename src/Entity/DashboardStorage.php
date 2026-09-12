<?php

declare(strict_types=1);

namespace Drupal\webdashboard\Entity;

use Drupal\Core\Config\Entity\ConfigEntityStorage;

/**
 * Storage for the dashboard entities.
 */
class DashboardStorage extends ConfigEntityStorage {

  /**
   * Loads dashboards ordered by weight, then by label.
   *
   * @param array|null $ids
   *   (optional) The dashboard IDs to load, all of them when NULL.
   *
   * @return \Drupal\webdashboard\Entity\DashboardInterface[]
   *   The dashboards, keyed by ID.
   */
  public function loadMultipleOrderedByWeight(?array $ids = NULL): array {
    /** @var \Drupal\webdashboard\Entity\DashboardInterface[] $entities */
    $entities = $this->loadMultiple($ids);
    \uasort($entities, [Dashboard::class, 'sort']);

    return $entities;
  }

}
