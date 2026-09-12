<?php

declare(strict_types=1);

namespace Drupal\webdashboard\Plugin;

use Drupal\Component\Plugin\PluginInspectionInterface;
use Drupal\Core\Form\FormStateInterface;

/**
 * Defines an interface for dashboard item plugins.
 */
interface DashboardItemInterface extends PluginInspectionInterface {

  /**
   * Builds the render array of the dashboard item.
   *
   * @param array $configuration
   *   The configuration of the block placing the item.
   *
   * @return array
   *   The render array, empty to render nothing.
   */
  public function buildRenderArray(array $configuration): array;

  /**
   * Builds the settings form of the dashboard item.
   *
   * @param array $form
   *   The block form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   * @param array $configuration
   *   The configuration of the block placing the item.
   *
   * @return array
   *   The form.
   */
  public function buildSettingsForm(array $form, FormStateInterface $form_state, array $configuration): array;

  /**
   * Validates the settings form of the dashboard item.
   *
   * @param array $form
   *   The block form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   * @param array $configuration
   *   The configuration of the block placing the item.
   */
  public function validateForm(array $form, FormStateInterface $form_state, array $configuration): void;

  /**
   * Massages the submitted settings before they are stored.
   *
   * @param array $form
   *   The block form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   * @param array $configuration
   *   The configuration of the block placing the item.
   */
  public function massageFormValues(array $form, FormStateInterface $form_state, array $configuration): void;

}
