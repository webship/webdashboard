<?php

declare(strict_types=1);

namespace Drupal\webdashboard\Form;

use Drupal\Core\Form\FormStateInterface;
use Drupal\user\Form\EntityPermissionsForm;

/**
 * Manages the permissions of one dashboard.
 *
 * The core form expects the entity to be a bundle of another entity type. A
 * dashboard is not, so the dashboard is read from its own route parameter.
 */
class DashboardPermissionsForm extends EntityPermissionsForm {

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, ?string $bundle_entity_type = NULL, $bundle = NULL): array {
    return parent::buildForm($form, $form_state, 'webdashboard', $bundle ?? $this->getRouteMatch()->getParameter('webdashboard'));
  }

}
