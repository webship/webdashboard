<?php

declare(strict_types=1);

namespace Drupal\webdashboard\Plugin\DashboardItem;

use Drupal\Core\Entity\EntityTypeBundleInfoInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Routing\RedirectDestinationInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Url;
use Drupal\webdashboard\Attribute\DashboardItem;
use Drupal\webdashboard\Plugin\DashboardItemBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Lists links to add content, per content type.
 */
#[DashboardItem(
  id: 'add_content_menu',
  label: new TranslatableMarkup('Add content'),
  category: new TranslatableMarkup('Dashboard: Navigation'),
)]
class AddContentMenu extends DashboardItemBase {

  /**
   * The entity type bundle info.
   */
  protected EntityTypeBundleInfoInterface $bundleInfo;

  /**
   * The entity type manager.
   */
  protected EntityTypeManagerInterface $entityTypeManager;

  /**
   * The redirect destination.
   */
  protected RedirectDestinationInterface $destination;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): static {
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $instance->entityTypeManager = $container->get('entity_type.manager');
    $instance->bundleInfo = $container->get('entity_type.bundle.info');
    $instance->destination = $container->get('redirect.destination');

    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function buildRenderArray(array $configuration): array {
    if (!$this->entityTypeManager->hasDefinition('node_type')) {
      return [];
    }

    $bundle_info = $this->bundleInfo->getBundleInfo('node');
    /** @var \Drupal\node\NodeTypeInterface[] $types */
    $types = $this->entityTypeManager->getStorage('node_type')->loadMultiple();
    $access_handler = $this->entityTypeManager->getAccessControlHandler('node');
    $items = [];

    foreach (\array_keys($configuration['items'] ?? []) as $bundle) {
      if (!isset($types[$bundle]) || !$access_handler->createAccess($bundle)) {
        continue;
      }

      $options = [];

      if (!empty($configuration['include_destination'])) {
        $options['query'] = $this->destination->getAsArray();
      }

      $items[] = [
        'url' => Url::fromRoute('node.add', ['node_type' => $bundle], $options),
        'title' => $bundle_info[$bundle]['label'] ?? $types[$bundle]->label(),
        'description' => ['#markup' => $types[$bundle]->getDescription()],
      ];
    }

    return [
      '#theme' => 'webdashboard_admin_list',
      '#list' => $items,
      '#cache' => [
        'contexts' => ['user.permissions', 'url.path'],
        'tags' => ['config:node_type_list'],
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function buildSettingsForm(array $form, FormStateInterface $form_state, array $configuration): array {
    $group_class = 'group-order-weight';

    $form['include_destination'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Include destination'),
      '#default_value' => $configuration['include_destination'] ?? 0,
    ];

    $form['items'] = [
      '#type' => 'table',
      '#caption' => $this->t('Content types'),
      '#header' => [
        $this->t('Label'),
        $this->t('Weight'),
      ],
      '#empty' => $this->t('No content types.'),
      '#tableselect' => FALSE,
      '#tabledrag' => [
        [
          'action' => 'order',
          'relationship' => 'sibling',
          'group' => $group_class,
        ],
      ],
    ];

    foreach ($this->bundleInfo->getBundleInfo('node') as $key => $value) {
      $weight = $configuration['items'][$key]['weight'] ?? 0;
      $form['items'][$key]['#attributes']['class'][] = 'draggable';
      $form['items'][$key]['#weight'] = $weight;
      $form['items'][$key]['label'] = [
        '#plain_text' => $value['label'],
      ];
      $form['items'][$key]['weight'] = [
        '#type' => 'weight',
        '#title' => $this->t('Weight for @title', ['@title' => $value['label']]),
        '#title_display' => 'invisible',
        '#default_value' => $weight,
        '#attributes' => ['class' => [$group_class]],
      ];
    }

    $form['#attached']['library'][] = 'core/drupal.tabledrag';

    return $form;
  }

}
