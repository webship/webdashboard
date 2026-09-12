<?php

declare(strict_types=1);

namespace Drupal\webdashboard\Plugin\DashboardItem;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\views\Views;
use Drupal\webdashboard\Attribute\DashboardItem;
use Drupal\webdashboard\Plugin\DashboardItemBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Embeds an Embed display of a view.
 */
#[DashboardItem(
  id: 'view_embed',
  label: new TranslatableMarkup('Embed a view'),
  category: new TranslatableMarkup('Dashboard: Views'),
)]
class ViewEmbed extends DashboardItemBase {

  /**
   * The entity type manager.
   */
  protected EntityTypeManagerInterface $entityTypeManager;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): static {
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $instance->entityTypeManager = $container->get('entity_type.manager');

    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function buildRenderArray(array $configuration): array {
    [$view_id, $display_id] = \explode(':', (string) ($configuration['view'] ?? '')) + ['', ''];
    $view = $view_id ? Views::getView($view_id) : NULL;

    if (!$view) {
      return [
        '#markup' => $this->t('The view does not exist anymore.'),
      ];
    }

    return $view->buildRenderable($display_id) ?? [];
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array $form, FormStateInterface $form_state, array $configuration): void {
    if (!$form_state->getValue('view')) {
      $form_state->setErrorByName('view', $this->t('Requires a view to be provided.'));
    }
  }

  /**
   * {@inheritdoc}
   */
  public function buildSettingsForm(array $form, FormStateInterface $form_state, array $configuration): array {
    $options = [];

    /** @var \Drupal\views\ViewEntityInterface $view */
    foreach ($this->entityTypeManager->getStorage('view')->loadByProperties(['status' => TRUE]) as $view) {
      foreach ($view->get('display') as $display) {
        if ($display['display_plugin'] === 'embed') {
          $options[$view->id() . ':' . $display['id']] = $view->label() . ': ' . $display['display_title'];
        }
      }
    }

    if ($options === []) {
      $form['view'] = [
        '#markup' => $this->t('Requires an embed view to be added for this plugin to be used.'),
      ];

      return $form;
    }

    $form['view'] = [
      '#type' => 'select',
      '#title' => $this->t('View'),
      '#required' => TRUE,
      '#options' => $options,
      '#default_value' => $configuration['view'] ?? NULL,
    ];

    return $form;
  }

}
