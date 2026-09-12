<?php

declare(strict_types=1);

namespace Drupal\webdashboard\Entity;

use Drupal\Core\Config\Entity\ConfigEntityInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\display_builder\Entity\ProfileInterface;

/**
 * Provides an interface defining a dashboard entity type.
 */
interface DashboardInterface extends ConfigEntityInterface {

  /**
   * The user data module key holding the personalized dashboards.
   */
  public const USER_DATA_MODULE = 'webdashboard';

  /**
   * Gets the category of the dashboard.
   *
   * @return string|null
   *   The category, or NULL when the dashboard has none.
   */
  public function getCategory(): ?string;

  /**
   * Should this dashboard always be rendered in the frontend theme?
   *
   * @return bool
   *   TRUE to skip the administration theme for this dashboard.
   */
  public function showAlwaysInFrontend(): bool;

  /**
   * Gets the Display Builder profile the dashboard is built with.
   *
   * @return \Drupal\display_builder\Entity\ProfileInterface|null
   *   The profile, or NULL when Display Builder is not set up yet.
   */
  public function getProfile(): ?ProfileInterface;

  /**
   * Gets the sources tree of the default dashboard.
   *
   * @return array
   *   A list of nestable UI Patterns sources.
   */
  public function getSources(): array;

  /**
   * Sets the sources tree of the default dashboard.
   *
   * @param array $sources
   *   A list of nestable UI Patterns sources.
   *
   * @return $this
   */
  public function setSources(array $sources): static;

  /**
   * Has the account personalized this dashboard?
   *
   * @param \Drupal\Core\Session\AccountInterface|null $account
   *   (optional) The account. Defaults to the current user.
   *
   * @return bool
   *   TRUE when the account has a personalized dashboard.
   */
  public function isOverridden(?AccountInterface $account = NULL): bool;

  /**
   * Gets the sources tree of a personalized dashboard.
   *
   * @param \Drupal\Core\Session\AccountInterface|null $account
   *   (optional) The account. Defaults to the current user.
   *
   * @return array|null
   *   The sources, or NULL when the account has not personalized the dashboard.
   *   An empty array is a dashboard the account emptied on purpose.
   */
  public function getOverriddenSources(?AccountInterface $account = NULL): ?array;

  /**
   * Saves the sources tree of a personalized dashboard.
   *
   * @param \Drupal\Core\Session\AccountInterface $account
   *   The account owning the personalized dashboard.
   * @param array $sources
   *   A list of nestable UI Patterns sources.
   */
  public function setOverriddenSources(AccountInterface $account, array $sources): void;

  /**
   * Removes a personalized dashboard, back to the default one.
   *
   * @param \Drupal\Core\Session\AccountInterface $account
   *   The account owning the personalized dashboard.
   */
  public function deleteOverride(AccountInterface $account): void;

  /**
   * Gets the cache tag of the dashboard as one account sees it.
   *
   * @param \Drupal\Core\Session\AccountInterface $account
   *   The account.
   *
   * @return string
   *   The cache tag, invalidated whenever the account personalizes the
   *   dashboard or resets it.
   */
  public function getOverrideCacheTag(AccountInterface $account): string;

}
