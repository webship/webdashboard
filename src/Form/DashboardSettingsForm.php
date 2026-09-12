<?php

declare(strict_types=1);

namespace Drupal\webdashboard\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

// cspell:ignore bluered cdom cubehelix freesurface viridis colormaps
/**
 * Settings of the dashboard charts.
 */
class DashboardSettingsForm extends ConfigFormBase {

  /**
   * The colormaps, as named by the colormap library.
   *
   * @see https://github.com/bpostlethwaite/colormap#readme
   */
  private const COLORMAPS = [
    'jet', 'hsv', 'hot', 'spring', 'summer', 'autumn', 'winter', 'bone',
    'copper', 'greys', 'YlGnBu', 'greens', 'YlOrRd', 'bluered', 'RdBu',
    'picnic', 'rainbow', 'portland', 'blackbody', 'earth', 'electric',
    'viridis', 'inferno', 'magma', 'plasma', 'warm', 'cool', 'rainbow-soft',
    'bathymetry', 'cdom', 'chlorophyll', 'density', 'freesurface-blue',
    'freesurface-red', 'oxygen', 'par', 'phase', 'salinity', 'turbidity',
    'velocity-blue', 'velocity-green', 'cubehelix',
  ];

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames(): array {
    return ['webdashboard.settings'];
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'webdashboard_settings_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {
    $form['colormap'] = [
      '#type' => 'select',
      '#title' => $this->t('Choose a colormap'),
      '#options' => \array_combine(self::COLORMAPS, self::COLORMAPS),
      '#config_target' => 'webdashboard.settings:colormap',
      '#description' => $this->t('See the colormaps <a href=":url">here</a>.', [':url' => 'https://github.com/bpostlethwaite/colormap#readme']),
    ];

    $form['alpha'] = [
      '#type' => 'number',
      '#title' => $this->t('Transparency'),
      '#description' => $this->t('Transparency in percent.'),
      '#min' => 20,
      '#max' => 100,
      '#config_target' => 'webdashboard.settings:alpha',
    ];

    $form['shades'] = [
      '#type' => 'number',
      '#title' => $this->t('Number of colors in map'),
      '#min' => 15,
      '#config_target' => 'webdashboard.settings:shades',
    ];

    return parent::buildForm($form, $form_state);
  }

}
