<?php

declare(strict_types=1);

namespace Drupal\webdashboard\Plugin\Block;

use Drupal\Core\Block\Attribute\Block;
use Drupal\Core\Block\BlockBase;
use Drupal\Core\Block\BlockPluginInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Theme\ThemeManagerInterface;
use Drupal\webdashboard\Plugin\DashboardItemInterface;
use Drupal\webdashboard\Plugin\Derivative\DashboardItemBlockDeriver;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Places a dashboard item, one block per dashboard item plugin.
 *
 * Display Builder renders the build of a block without the block template, so
 * the dashboard panel, its title and its content wrapper are built here.
 */
#[Block(
  id: 'webdashboard_item',
  admin_label: new TranslatableMarkup('Dashboard item'),
  category: new TranslatableMarkup('Dashboard'),
  deriver: DashboardItemBlockDeriver::class,
)]
class DashboardItemBlock extends BlockBase implements ContainerFactoryPluginInterface {

  /**
   * The keys the block itself stores, not the dashboard item.
   */
  private const BLOCK_KEYS = [
    'id',
    'label',
    'label_display',
    'provider',
    'admin_label',
    'context_mapping',
  ];

  /**
   * The dashboard item this block places.
   */
  protected DashboardItemInterface $item;

  /**
   * The theme manager.
   */
  protected ThemeManagerInterface $themeManager;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): static {
    $instance = new static($configuration, $plugin_id, $plugin_definition);
    $instance->item = $container->get('plugin.manager.webdashboard_item')->createInstance((string) $instance->getDerivativeId());
    $instance->themeManager = $container->get('theme.manager');

    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function blockForm($form, FormStateInterface $form_state): array {
    return $this->item->buildSettingsForm($form, $form_state, $this->getConfiguration());
  }

  /**
   * {@inheritdoc}
   */
  public function blockValidate($form, FormStateInterface $form_state): void {
    $this->item->validateForm($form, $form_state, $this->getConfiguration());
  }

  /**
   * {@inheritdoc}
   */
  public function blockSubmit($form, FormStateInterface $form_state): void {
    $configuration = $this->getConfiguration();
    $this->item->massageFormValues($form, $form_state, $configuration);
    $values = \array_diff_key($form_state->getValues(), \array_flip(self::BLOCK_KEYS));
    $this->configuration = \array_merge($configuration, $values);
  }

  /**
   * {@inheritdoc}
   */
  public function build(): array {
    $content = $this->item->buildRenderArray($this->getConfiguration());

    if ($content === []) {
      return [];
    }

    $build = [
      '#type' => 'container',
      '#attributes' => [
        'class' => \array_merge(['webdashboard-item', 'panel'], $this->getThemeClasses()),
      ],
      '#attached' => [
        'library' => ['webdashboard/core'],
      ],
    ];

    if (($this->configuration['label_display'] ?? '') === BlockPluginInterface::BLOCK_LABEL_VISIBLE) {
      $build['title'] = [
        '#type' => 'html_tag',
        '#tag' => 'h2',
        '#attributes' => [
          'class' => ['webdashboard-item__title', 'panel__title'],
        ],
        'label' => ['#plain_text' => $this->label()],
      ];
    }

    $build['content'] = [
      '#type' => 'container',
      '#attributes' => [
        'class' => ['webdashboard-item__content', 'panel__content'],
      ],
      'content' => $content,
    ];

    return $build;
  }

  /**
   * Gets the panel classes of the active administration theme.
   *
   * @return string[]
   *   The classes making the item a panel of Gin or Claro.
   */
  protected function getThemeClasses(): array {
    $theme = $this->themeManager->getActiveTheme();
    $themes = [$theme->getName(), ...\array_keys($theme->getBaseThemeExtensions())];

    return match (TRUE) {
      \in_array('gin', $themes, TRUE) => ['gin-layer-wrapper'],
      \in_array('claro', $themes, TRUE) => ['card'],
      default => [],
    };
  }

}
