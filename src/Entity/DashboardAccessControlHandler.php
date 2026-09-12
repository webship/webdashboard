<?php

declare(strict_types=1);

namespace Drupal\webdashboard\Entity;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Entity\EntityAccessControlHandler;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\webdashboard\DashboardPermissions;

/**
 * Access handler for the dashboards.
 *
 * Operations:
 * - view: see the dashboard.
 * - override: personalize the dashboard.
 * - any other: administer the dashboard.
 */
class DashboardAccessControlHandler extends EntityAccessControlHandler {

  /**
   * {@inheritdoc}
   */
  protected function checkAccess(EntityInterface $entity, $operation, AccountInterface $account): AccessResultInterface {
    $admin = AccessResult::allowedIfHasPermission($account, (string) $this->entityType->getAdminPermission());

    $result = match ($operation) {
      'view' => AccessResult::allowedIfHasPermission($account, DashboardPermissions::viewPermission($entity))->orIf($admin),
      'override' => AccessResult::allowedIfHasPermission($account, DashboardPermissions::overridePermission($entity))->orIf($admin),
      'delete' => $entity->isNew() ? AccessResult::forbidden() : $admin,
      default => $admin,
    };

    return $result->addCacheableDependency($entity);
  }

}
