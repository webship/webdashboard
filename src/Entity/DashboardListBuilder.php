<?php

declare(strict_types=1);

namespace Drupal\webdashboard\Entity;

use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Config\Entity\DraggableListBuilder;
use Drupal\Core\Entity\EntityInterface;

/**
 * Lists the dashboards, ordered by weight.
 */
class DashboardListBuilder extends DraggableListBuilder {

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'webdashboard_list';
  }

  /**
   * {@inheritdoc}
   */
  public function buildHeader(): array {
    $header = [];
    $header['label'] = $this->t('Administrative label');
    $header['category'] = $this->t('Category');
    $header['profile'] = $this->t('Profile');

    return $header + parent::buildHeader();
  }

  /**
   * {@inheritdoc}
   */
  public function buildRow(EntityInterface $entity): array {
    /** @var \Drupal\webdashboard\Entity\DashboardInterface $entity */
    $row = [];
    $row['label'] = $entity->label();
    $row['category']['#plain_text'] = $entity->getCategory() ?? '';
    $row['profile']['#plain_text'] = $entity->getProfile()?->label() ?? $this->t('- None -');

    return $row + parent::buildRow($entity);
  }

  /**
   * {@inheritdoc}
   */
  public function getDefaultOperations(EntityInterface $entity, ?CacheableMetadata $cacheability = NULL): array {
    /** @var \Drupal\webdashboard\Entity\DashboardInterface $entity */
    $operations = parent::getDefaultOperations($entity, $cacheability);

    if ($entity->access('view')) {
      $operations['view'] = [
        'title' => $this->t('View'),
        'weight' => -20,
        'url' => $entity->toUrl('canonical'),
      ];
    }

    if ($entity->access('update') && $entity->getProfile()) {
      $operations['build'] = [
        'title' => $this->t('Build dashboard'),
        'weight' => -10,
        'url' => $entity->toUrl('display-builder'),
      ];
    }

    return $operations;
  }

}
