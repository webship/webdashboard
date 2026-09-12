<?php

declare(strict_types=1);

namespace Drupal\webdashboard\Controller;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\display_builder\Controller\IntegrationControllerBase;
use Drupal\webdashboard\Entity\DashboardInterface;

/**
 * Opens Display Builder on a dashboard.
 */
class DashboardController extends IntegrationControllerBase {

  /**
   * Returns the title of the default dashboard builder.
   *
   * @param \Drupal\webdashboard\Entity\DashboardInterface $webdashboard
   *   The dashboard.
   *
   * @return \Drupal\Core\StringTranslation\TranslatableMarkup
   *   The page title.
   */
  public function getBuilderTitle(DashboardInterface $webdashboard): TranslatableMarkup {
    return $this->t('Build @label dashboard', ['@label' => $webdashboard->label()]);
  }

  /**
   * Builds the default dashboard.
   *
   * @param \Drupal\webdashboard\Entity\DashboardInterface $webdashboard
   *   The dashboard.
   *
   * @return array
   *   The Display Builder renderable.
   */
  public function getBuilder(DashboardInterface $webdashboard): array {
    /** @var \Drupal\display_builder\DisplayBuildableInterface $buildable */
    $buildable = $this->displayBuildableManager->createInstance('webdashboard', ['entity' => $webdashboard]);

    return $this->renderBuilder($buildable);
  }

  /**
   * Returns the title of the personalized dashboard builder.
   *
   * @param \Drupal\webdashboard\Entity\DashboardInterface $webdashboard
   *   The dashboard.
   *
   * @return \Drupal\Core\StringTranslation\TranslatableMarkup
   *   The page title.
   */
  public function getOverrideTitle(DashboardInterface $webdashboard): TranslatableMarkup {
    return $this->t('Personalize @label dashboard', ['@label' => $webdashboard->label()]);
  }

  /**
   * Builds the personalized dashboard of the current user.
   *
   * @param \Drupal\webdashboard\Entity\DashboardInterface $webdashboard
   *   The dashboard.
   *
   * @return array
   *   The Display Builder renderable.
   */
  public function getOverrideBuilder(DashboardInterface $webdashboard): array {
    /** @var \Drupal\display_builder\DisplayBuildableInterface $buildable */
    $buildable = $this->displayBuildableManager->createInstance('webdashboard_override', [
      'entity' => $webdashboard,
      'uid' => (int) $this->currentUser()->id(),
    ]);

    return $this->renderBuilder($buildable);
  }

}
