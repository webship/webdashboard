<?php

declare(strict_types=1);

namespace Drupal\webdashboard\Plugin\DashboardItem;

use Drupal\Core\Entity\EntityDisplayRepositoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\webdashboard\Attribute\DashboardItem;
use Drupal\webdashboard\Plugin\DashboardItemBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Shows the current user.
 */
#[DashboardItem(
  id: 'account',
  label: new TranslatableMarkup('Show current user'),
  category: new TranslatableMarkup('Dashboard: User'),
)]
class Account extends DashboardItemBase {

  /**
   * The current user.
   */
  protected AccountInterface $account;

  /**
   * The entity display repository.
   */
  protected EntityDisplayRepositoryInterface $entityDisplayRepository;

  /**
   * The entity type manager.
   */
  protected EntityTypeManagerInterface $entityTypeManager;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): static {
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $instance->account = $container->get('current_user');
    $instance->entityDisplayRepository = $container->get('entity_display.repository');
    $instance->entityTypeManager = $container->get('entity_type.manager');

    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function buildRenderArray(array $configuration): array {
    $user = $this->entityTypeManager->getStorage('user')->load($this->account->id());

    if (!$user) {
      return [];
    }

    $build = $this->entityTypeManager->getViewBuilder('user')->view($user, $configuration['view_mode'] ?? 'default');
    $build['#cache']['contexts'][] = 'user';

    return $build;
  }

  /**
   * {@inheritdoc}
   */
  public function buildSettingsForm(array $form, FormStateInterface $form_state, array $configuration): array {
    $form['view_mode'] = [
      '#type' => 'select',
      '#title' => $this->t('View mode'),
      '#options' => $this->entityDisplayRepository->getViewModeOptions('user'),
      '#default_value' => $configuration['view_mode'] ?? 'default',
    ];

    return $form;
  }

}
