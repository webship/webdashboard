<?php

declare(strict_types=1);

namespace Drupal\webdashboard\Plugin\DashboardItem;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Url;
use Drupal\system\SystemManager;
use Drupal\webdashboard\Attribute\DashboardItem;
use Drupal\webdashboard\Plugin\DashboardItemBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Key numbers of the site as cards.
 *
 * Content by type, people, forms, API clients and health, so each site
 * template shows what it is made for.
 */
#[DashboardItem(
  id: 'site_overview',
  label: new TranslatableMarkup('Site overview'),
  category: new TranslatableMarkup('Dashboard: Reports'),
)]
class SiteOverview extends DashboardItemBase {

  /**
   * The current user.
   */
  protected AccountInterface $account;

  /**
   * The entity type manager.
   */
  protected EntityTypeManagerInterface $entityTypeManager;

  /**
   * The system manager.
   */
  protected SystemManager $systemManager;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): static {
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $instance->account = $container->get('current_user');
    $instance->entityTypeManager = $container->get('entity_type.manager');
    $instance->systemManager = $container->get('system.manager');

    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function buildRenderArray(array $configuration): array {
    $cards = [];

    if ($this->entityTypeManager->hasDefinition('node') && $this->account->hasPermission('access content overview')) {
      // One card per content type, so every site template shows its own
      // content: blog posts, docs, products, releases, web apps...
      $types = $this->entityTypeManager->getStorage('node_type')->loadMultiple();
      \uasort($types, static fn ($a, $b) => \strnatcasecmp((string) $a->label(), (string) $b->label()));
      foreach ($types as $type) {
        $published = $this->countEntities('node', ['type' => $type->id(), 'status' => 1]);
        $unpublished = $this->countEntities('node', ['type' => $type->id(), 'status' => 0]);
        $cards[] = $this->card(
          $published,
          $this->t('@type', ['@type' => $type->label()]),
          $unpublished
            ? $this->formatPlural($unpublished, 'Published, 1 unpublished', 'Published, @count unpublished')
            : $this->t('Published'),
          Url::fromRoute('system.admin_content', [], ['query' => ['type' => $type->id()]]),
          $unpublished ? 'warning' : 'info',
        );
      }
    }

    if ($this->account->hasPermission('administer users')) {
      $cards[] = $this->card(
        $this->countEntities('user', ['status' => 1]),
        $this->t('People'),
        $this->t('Active accounts'),
        Url::fromRoute('entity.user.collection'),
        'neutral',
      );
    }

    if ($this->entityTypeManager->hasDefinition('webform_submission') && $this->account->hasPermission('view any webform submission')) {
      $cards[] = $this->card(
        $this->countEntities('webform_submission', []),
        $this->t('Submissions'),
        $this->t('Messages sent through the forms'),
        Url::fromRoute('entity.webform_submission.collection'),
        'neutral',
      );
    }

    if ($this->entityTypeManager->hasDefinition('consumer') && $this->account->hasPermission('administer consumer entities')) {
      $cards[] = $this->card(
        $this->countEntities('consumer', []),
        $this->t('API clients'),
        $this->t('Applications using the API'),
        Url::fromRoute('entity.consumer.collection'),
        'neutral',
      );
    }

    if ($this->account->hasPermission('administer site configuration')) {
      $counter = SystemInfo::getCounter($this->systemManager->listRequirements());
      $problems = ($counter['error']['amount'] ?? 0) + ($counter['warning']['amount'] ?? 0);
      $cards[] = $this->card(
        $problems,
        $this->t('Site health'),
        $problems
          ? $this->t('@errors, @warnings', [
            '@errors' => $this->formatPlural($counter['error']['amount'] ?? 0, '1 error', '@count errors'),
            '@warnings' => $this->formatPlural($counter['warning']['amount'] ?? 0, '1 warning', '@count warnings'),
          ])
          : $this->t('All checks passed'),
        Url::fromRoute('system.status'),
        match (TRUE) {
          isset($counter['error']) => 'danger',
          isset($counter['warning']) => 'warning',
          default => 'success',
        },
      );
    }

    if (!$cards) {
      return [];
    }

    return [
      '#type' => 'container',
      '#attributes' => ['class' => ['webdashboard-stats']],
      'cards' => $cards,
      '#cache' => [
        'contexts' => ['user.permissions'],
        'tags' => ['node_list', 'user_list'],
        'max-age' => 300,
      ],
    ];
  }

  /**
   * Builds one stat card.
   */
  protected function card(int $value, TranslatableMarkup $label, TranslatableMarkup $description, Url $url, string $tone): array {
    return [
      '#type' => 'component',
      '#component' => 'webdashboard:stat_card',
      '#props' => [
        'value' => (string) $value,
        'label' => (string) $label,
        'description' => (string) $description,
        'url' => $url->toString(),
        'tone' => $tone,
      ],
    ];
  }

  /**
   * Counts the entities of a type with the given property values.
   */
  protected function countEntities(string $entity_type_id, array $conditions): int {
    $query = $this->entityTypeManager->getStorage($entity_type_id)->getQuery()->accessCheck(FALSE);
    foreach ($conditions as $field => $value) {
      $query->condition($field, $value);
    }

    return (int) $query->count()->execute();
  }

}
