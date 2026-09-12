<?php

declare(strict_types=1);

namespace Drupal\Tests\webdashboard\Functional;

use Drupal\Tests\BrowserTestBase;
use Drupal\display_builder\Entity\Profile;
use Drupal\user\UserInterface;
use Drupal\webdashboard\Entity\Dashboard;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Symfony\Component\HttpFoundation\Response;

/**
 * Tests the dashboard pages, their theme and their permissions.
 */
#[Group('webdashboard')]
#[RunTestsInSeparateProcesses]
final class DashboardUiTest extends BrowserTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['block', 'webdashboard'];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'olivero';

  /**
   * The ID of the dashboard under test.
   */
  private const DASHBOARD = 'test_dashboard';

  /**
   * A user administering dashboards.
   */
  private UserInterface $adminUser;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->container->get('theme_installer')->install(['claro']);
    $this->config('system.theme')->set('admin', 'claro')->set('default', 'olivero')->save();
    Profile::create(['id' => 'test', 'label' => 'Test', 'description' => ''])->save();

    foreach (['olivero', 'claro'] as $theme) {
      $this->drupalPlaceBlock('local_actions_block', ['theme' => $theme]);
      $this->drupalPlaceBlock('local_tasks_block', ['theme' => $theme]);
    }

    $this->adminUser = $this->drupalCreateUser([
      'administer webdashboard',
      'view the administration theme',
      'use display builder test',
    ]);
  }

  /**
   * Tests the dashboard administration.
   */
  public function testAdministration(): void {
    $this->drupalLogin($this->drupalCreateUser(['access content']));
    $this->drupalGet('admin/structure/webdashboards');
    $this->assertSession()->statusCodeEquals(Response::HTTP_FORBIDDEN);

    $this->drupalLogin($this->adminUser);
    $this->drupalGet('admin/structure/webdashboards');
    $this->assertSession()->statusCodeEquals(Response::HTTP_OK);
    $this->assertSession()->responseContains('core/themes/claro/css/');
    $this->assertSession()->pageTextContains('New dashboard');

    $this->createDashboardFromUi();
    $this->assertSession()->pageTextContains('Default Dashboard');
    // Managing permissions takes the core permission to administer them.
    $this->assertSession()->linkNotExists('Permissions');

    $this->drupalGet('admin/structure/webdashboards/manage/' . self::DASHBOARD . '/builder');
    $this->assertSession()->statusCodeEquals(Response::HTTP_OK);

    $this->drupalGet('admin/structure/webdashboards/manage/' . self::DASHBOARD . '/permissions');
    $this->assertSession()->statusCodeEquals(Response::HTTP_FORBIDDEN);
    $this->drupalLogin($this->drupalCreateUser(['administer permissions']));
    $this->drupalGet('admin/structure/webdashboards/manage/' . self::DASHBOARD . '/permissions');
    $this->assertSession()->statusCodeEquals(Response::HTTP_OK);
    $this->assertSession()->pageTextContains('Can view Test dashboard dashboard');
  }

  /**
   * Tests a dashboard always shown in the frontend theme.
   */
  public function testFrontendMode(): void {
    $this->drupalLogin($this->adminUser);
    $this->createDashboardFromUi(TRUE);
    $this->drupalGet('webdashboard/' . self::DASHBOARD);
    $this->assertSession()->statusCodeEquals(Response::HTTP_OK);
    $this->assertSession()->responseContains('core/themes/olivero/css/');
  }

  /**
   * Tests a dashboard shown in the administration theme.
   */
  public function testBackendMode(): void {
    $this->createDashboard();

    $this->drupalLogin($this->drupalCreateUser([
      'can view ' . self::DASHBOARD . ' webdashboard',
      'view the administration theme',
    ]));
    $this->drupalGet('webdashboard/' . self::DASHBOARD);
    $this->assertSession()->statusCodeEquals(Response::HTTP_OK);
    $this->assertSession()->responseContains('core/themes/claro/css/');

    $this->drupalLogin($this->drupalCreateUser([
      'can view ' . self::DASHBOARD . ' webdashboard',
    ]));
    $this->drupalGet('webdashboard/' . self::DASHBOARD);
    $this->assertSession()->statusCodeEquals(Response::HTTP_OK);
    $this->assertSession()->responseContains('core/themes/olivero/css/');
  }

  /**
   * Tests a user without access to the dashboard.
   */
  public function testAccessDenied(): void {
    $this->createDashboard();
    $this->drupalLogin($this->drupalCreateUser(['access content']));
    $this->drupalGet('webdashboard/' . self::DASHBOARD);
    $this->assertSession()->statusCodeEquals(Response::HTTP_FORBIDDEN);
  }

  /**
   * Tests personalizing a dashboard, and resetting it to the default one.
   */
  public function testPersonalize(): void {
    $dashboard = $this->createDashboard();

    $this->drupalLogin($this->drupalCreateUser([
      'can view ' . self::DASHBOARD . ' webdashboard',
    ]));
    $this->drupalGet('webdashboard/' . self::DASHBOARD);
    $this->assertSession()->statusCodeEquals(Response::HTTP_OK);
    $this->assertSession()->linkNotExists('Personalize');

    // Personalizing is building, so it takes the Display Builder profile too.
    $this->drupalLogin($this->drupalCreateUser([
      'can view ' . self::DASHBOARD . ' webdashboard',
      'can override ' . self::DASHBOARD . ' webdashboard',
    ]));
    $this->drupalGet('webdashboard/' . self::DASHBOARD);
    $this->assertSession()->linkNotExists('Personalize');
    $this->drupalGet('webdashboard/' . self::DASHBOARD . '/override');
    $this->assertSession()->statusCodeEquals(Response::HTTP_FORBIDDEN);

    $user = $this->drupalCreateUser([
      'can view ' . self::DASHBOARD . ' webdashboard',
      'can override ' . self::DASHBOARD . ' webdashboard',
      'use display builder test',
    ]);
    $this->drupalLogin($user);
    $this->drupalGet('webdashboard/' . self::DASHBOARD);
    $this->assertSession()->linkExists('Personalize');
    $this->assertSession()->linkNotExists('Reset to default');
    $this->drupalGet('webdashboard/' . self::DASHBOARD . '/override');
    $this->assertSession()->statusCodeEquals(Response::HTTP_OK);
    $this->drupalGet('webdashboard/' . self::DASHBOARD . '/override/reset');
    $this->assertSession()->statusCodeEquals(Response::HTTP_FORBIDDEN);

    $dashboard->setOverriddenSources($user, [
      [
        'node_id' => 'a1b2c3d4e5f60718',
        'source_id' => 'textfield',
        'source' => ['value' => 'My own dashboard'],
      ],
    ]);
    $this->drupalGet('webdashboard/' . self::DASHBOARD);
    $this->assertSession()->pageTextContains('My own dashboard');
    $this->clickLink('Reset to default');
    $this->submitForm([], 'Reset to default');
    $this->assertSession()->pageTextContains('is reset to the default');
    $this->assertSession()->pageTextNotContains('My own dashboard');
    $this->assertSession()->linkNotExists('Reset to default');
  }

  /**
   * Creates a dashboard from the administration form.
   *
   * @param bool $frontend
   *   (optional) Show the dashboard always in the frontend theme.
   */
  private function createDashboardFromUi(bool $frontend = FALSE): void {
    $this->drupalGet('admin/structure/webdashboards');
    $this->clickLink('New dashboard');
    $this->submitForm([
      'id' => self::DASHBOARD,
      'admin_label' => 'Test dashboard',
      'frontend' => $frontend,
      'profile' => 'test',
    ], 'Save');
    $this->assertSession()->pageTextContains('Created the Test dashboard dashboard.');
  }

  /**
   * Creates a dashboard through the API.
   *
   * @return \Drupal\webdashboard\Entity\Dashboard
   *   The dashboard.
   */
  private function createDashboard(): Dashboard {
    $dashboard = Dashboard::create([
      'id' => self::DASHBOARD,
      'admin_label' => 'Test dashboard',
      'profile' => 'test',
    ]);
    $dashboard->save();

    return $dashboard;
  }

}
