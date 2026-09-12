<?php

declare(strict_types=1);

namespace Drupal\webdashboard\Form;

use Drupal\Core\DependencyInjection\AutowireTrait;
use Drupal\Core\Form\ConfirmFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Url;
use Drupal\display_builder\DisplayBuildablePluginManager;
use Drupal\webdashboard\Entity\DashboardInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Resets the personalized dashboard of the current user to the default one.
 */
class DashboardResetOverrideForm extends ConfirmFormBase {

  use AutowireTrait;

  /**
   * The dashboard to reset.
   */
  protected DashboardInterface $dashboard;

  public function __construct(
    #[Autowire(service: 'plugin.manager.display_buildable')]
    protected DisplayBuildablePluginManager $displayBuildableManager,
  ) {}

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'webdashboard_reset_override_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, ?DashboardInterface $webdashboard = NULL): array {
    $this->dashboard = $webdashboard;

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function getQuestion(): TranslatableMarkup {
    return $this->t('Reset your %label dashboard to the default?', ['%label' => $this->dashboard->label()]);
  }

  /**
   * {@inheritdoc}
   */
  public function getDescription(): TranslatableMarkup {
    return $this->t('Your personalized dashboard is removed, and you see the default dashboard again. This action cannot be undone.');
  }

  /**
   * {@inheritdoc}
   */
  public function getConfirmText(): TranslatableMarkup {
    return $this->t('Reset to default');
  }

  /**
   * {@inheritdoc}
   */
  public function getCancelUrl(): Url {
    return $this->dashboard->toUrl('canonical');
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    /** @var \Drupal\display_builder\DisplayBuildableOverrideInterface $buildable */
    $buildable = $this->displayBuildableManager->createInstance('webdashboard_override', [
      'entity' => $this->dashboard,
      'uid' => (int) $this->currentUser()->id(),
    ]);
    $buildable->revert();

    // Drop the builder instance too, so the next personalization starts from
    // the default dashboard rather than from the history of this one.
    $buildable->getInstance()?->delete();

    $this->messenger()->addStatus($this->t('Your %label dashboard is reset to the default.', ['%label' => $this->dashboard->label()]));
    $form_state->setRedirectUrl($this->getCancelUrl());
  }

}
