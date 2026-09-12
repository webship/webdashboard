<?php

declare(strict_types=1);

namespace Drupal\webdashboard\Form;

use Drupal\Core\DependencyInjection\AutowireTrait;
use Drupal\Core\Entity\EntityForm;
use Drupal\Core\Form\FormStateInterface;
use Drupal\display_builder\DisplayBuildableInterface;
use Drupal\display_builder\DisplayBuildablePluginManager;
use Drupal\webdashboard\Entity\Dashboard;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Form to add and edit a dashboard.
 */
class DashboardForm extends EntityForm {

  use AutowireTrait;

  public function __construct(
    #[Autowire(service: 'plugin.manager.display_buildable')]
    protected DisplayBuildablePluginManager $displayBuildableManager,
  ) {}

  /**
   * {@inheritdoc}
   */
  public function form(array $form, FormStateInterface $form_state): array {
    $form = parent::form($form, $form_state);

    /** @var \Drupal\webdashboard\Entity\DashboardInterface $entity */
    $entity = $this->entity;

    $form['admin_label'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Administrative label'),
      '#default_value' => $entity->label(),
      '#size' => 30,
      '#required' => TRUE,
      '#maxlength' => 64,
      '#description' => $this->t('The admin label for this dashboard.'),
    ];

    $form['id'] = [
      '#type' => 'machine_name',
      '#default_value' => $entity->id(),
      '#required' => TRUE,
      '#disabled' => !$entity->isNew(),
      '#size' => 30,
      '#maxlength' => 64,
      '#machine_name' => [
        'exists' => [Dashboard::class, 'load'],
        'source' => ['admin_label'],
      ],
    ];

    $form['category'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Category'),
      '#default_value' => $entity->getCategory(),
    ];

    $form['frontend'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Show always in frontend theme.'),
      '#default_value' => $entity->showAlwaysInFrontend(),
    ];

    $form['weight'] = [
      '#type' => 'number',
      '#title' => $this->t('Weight'),
      '#default_value' => $entity->get('weight') ?? 0,
    ];

    return $form + $this->getBuildable()->buildInstanceForm(TRUE, $this->t('Display Builder profile'));
  }

  /**
   * {@inheritdoc}
   */
  public function save(array $form, FormStateInterface $form_state): int {
    $result = parent::save($form, $form_state);
    $arguments = ['%label' => $this->entity->label()];
    $this->messenger()->addStatus(
      $result === SAVED_NEW
        ? $this->t('Created the %label dashboard.', $arguments)
        : $this->t('Saved the %label dashboard.', $arguments)
    );
    $form_state->setRedirectUrl($this->entity->toUrl('canonical'));

    return $result;
  }

  /**
   * {@inheritdoc}
   */
  protected function actionsElement(array $form, FormStateInterface $form_state): array {
    $element = parent::actionsElement($form, $form_state);

    // A new dashboard needs a profile the user is allowed to build with.
    if ($this->entity->isNew() && !$this->getBuildable()->isAllowed()) {
      $element['submit']['#disabled'] = TRUE;
    }

    return $element;
  }

  /**
   * Gets the buildable plugin of the default dashboard.
   *
   * @return \Drupal\display_builder\DisplayBuildableInterface
   *   The buildable plugin.
   */
  protected function getBuildable(): DisplayBuildableInterface {
    /** @var \Drupal\display_builder\DisplayBuildableInterface $buildable */
    $buildable = $this->displayBuildableManager->createInstance('webdashboard', ['entity' => $this->entity]);

    return $buildable;
  }

}
