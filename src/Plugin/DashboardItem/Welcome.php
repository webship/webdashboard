<?php

declare(strict_types=1);

namespace Drupal\webdashboard\Plugin\DashboardItem;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\File\FileUrlGeneratorInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Url;
use Drupal\user\UserInterface;
use Drupal\webdashboard\Attribute\DashboardItem;
use Drupal\webdashboard\Plugin\DashboardItemBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Welcomes the current user, with their picture and quick actions.
 */
#[DashboardItem(
  id: 'welcome',
  label: new TranslatableMarkup('Welcome'),
  category: new TranslatableMarkup('Dashboard: User'),
)]
class Welcome extends DashboardItemBase {

  /**
   * The current user.
   */
  protected AccountInterface $account;

  /**
   * The entity type manager.
   */
  protected EntityTypeManagerInterface $entityTypeManager;

  /**
   * The date formatter.
   */
  protected DateFormatterInterface $dateFormatter;

  /**
   * The file URL generator.
   */
  protected FileUrlGeneratorInterface $fileUrlGenerator;

  /**
   * The time service.
   */
  protected TimeInterface $time;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): static {
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $instance->account = $container->get('current_user');
    $instance->entityTypeManager = $container->get('entity_type.manager');
    $instance->dateFormatter = $container->get('date.formatter');
    $instance->fileUrlGenerator = $container->get('file_url_generator');
    $instance->time = $container->get('datetime.time');

    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function buildRenderArray(array $configuration): array {
    $user = $this->entityTypeManager->getStorage('user')->load($this->account->id());

    if (!$user instanceof UserInterface) {
      return [];
    }

    $now = $this->time->getRequestTime();
    $name = (string) $user->getDisplayName();
    $meta = [
      (string) $this->t('Member for @time', [
        '@time' => $this->dateFormatter->formatDiff($user->getCreatedTime(), $now, ['granularity' => 1]),
      ]),
    ];

    $roles = [];
    foreach ($this->entityTypeManager->getStorage('user_role')->loadMultiple($user->getRoles(TRUE)) as $role) {
      $roles[] = $role->label();
    }
    if ($roles) {
      $meta[] = \implode(', ', $roles);
    }

    return [
      '#type' => 'component',
      '#component' => 'webdashboard:welcome',
      '#props' => [
        'name' => $name,
        'picture' => $this->getPictureUrl($user),
        'initials' => $this->getInitials($name),
        'greeting' => (string) $this->getGreeting($now),
        'meta' => $meta,
        'links' => $this->getLinks($user),
      ],
      '#cache' => [
        'contexts' => ['user', 'user.permissions'],
        'tags' => $user->getCacheTags(),
        // The greeting follows the time of day.
        'max-age' => 1800,
      ],
    ];
  }

  /**
   * Returns a greeting for the time of day.
   */
  protected function getGreeting(int $timestamp): TranslatableMarkup {
    $hour = (int) $this->dateFormatter->format($timestamp, 'custom', 'G');

    return match (TRUE) {
      $hour < 12 => $this->t('Good morning'),
      $hour < 18 => $this->t('Good afternoon'),
      default => $this->t('Good evening'),
    };
  }

  /**
   * Returns the URL of the user picture, or an empty string.
   */
  protected function getPictureUrl(UserInterface $user): string {
    if (!$user->hasField('user_picture') || $user->get('user_picture')->isEmpty()) {
      return '';
    }

    $file = $user->get('user_picture')->entity;
    if (!$file) {
      return '';
    }

    $uri = $file->getFileUri();
    $style = $this->entityTypeManager->hasDefinition('image_style')
      ? $this->entityTypeManager->getStorage('image_style')->load('thumbnail')
      : NULL;

    return $style ? $style->buildUrl($uri) : $this->fileUrlGenerator->generateString($uri);
  }

  /**
   * Returns up to two initials of a name.
   */
  protected function getInitials(string $name): string {
    $initials = '';
    foreach (\array_slice(\preg_split('/[\s._-]+/u', \trim($name)) ?: [], 0, 2) as $word) {
      $initials .= \mb_strtoupper(\mb_substr($word, 0, 1));
    }

    return $initials;
  }

  /**
   * Returns the quick actions the current user has access to.
   */
  protected function getLinks(UserInterface $user): array {
    $candidates = [
      [$this->t('Add content'), Url::fromRoute('node.add_page')],
      [$this->t('View site'), Url::fromRoute('<front>')],
      [$this->t('Edit profile'), $user->toUrl('edit-form')],
    ];
    $links = [];

    foreach ($candidates as [$title, $url]) {
      try {
        if ($url->access($this->account)) {
          $links[] = ['title' => (string) $title, 'url' => $url->toString()];
        }
      }
      catch (\Exception) {
        // The route does not exist on this site, like node.add_page without
        // the Node module.
      }
    }

    return $links;
  }

}
