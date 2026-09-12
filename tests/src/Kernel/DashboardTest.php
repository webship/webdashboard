<?php

declare(strict_types=1);

namespace Drupal\Tests\webdashboard\Kernel;

use Drupal\Core\Session\AccountInterface;
use Drupal\KernelTests\KernelTestBase;
use Drupal\Tests\user\Traits\UserCreationTrait;
use Drupal\display_builder\DisplayBuildableOverrideInterface;
use Drupal\display_builder\Entity\Profile;
use Drupal\webdashboard\DashboardPermissions;
use Drupal\webdashboard\Entity\Dashboard;
use Drupal\webdashboard\Entity\DashboardInterface;
use Drupal\webdashboard\Plugin\display_builder\Buildable\DashboardBuildable;
use Drupal\webdashboard\Plugin\display_builder\Buildable\DashboardOverrideBuildable;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests the dashboard entity, its buildables and its permissions.
 */
#[Group('webdashboard')]
#[RunTestsInSeparateProcesses]
final class DashboardTest extends KernelTestBase {

  use UserCreationTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'block',
    'system',
    'user',
    'path_alias',
    'views',
    'display_builder',
    'ui_patterns',
    'ui_patterns_field',
    'webdashboard',
  ];

  /**
   * A text source, as Display Builder stores it.
   */
  private const SOURCE = [
    'node_id' => 'a1b2c3d4e5f60718',
    'source_id' => 'textfield',
    'source' => ['value' => 'Default dashboard text'],
  ];

  /**
   * The display buildable plugin manager.
   *
   * @var \Drupal\display_builder\DisplayBuildablePluginManager
   */
  private $buildableManager;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('user');
    $this->installEntitySchema('display_builder_instance');
    $this->installSchema('user', ['users_data']);
    $this->installConfig(['system', 'display_builder', 'webdashboard']);
    Profile::create(['id' => 'test', 'label' => 'Test', 'description' => ''])->save();
    $this->buildableManager = $this->container->get('plugin.manager.display_buildable');

    // Take user 1, so no test user bypasses the permissions.
    $this->createUser();
  }

  /**
   * Tests the instance IDs of both buildables.
   */
  public function testInstanceIds(): void {
    $this->assertSame(['webdashboard' => 'board'], DashboardBuildable::checkInstanceId('webdashboard__board'));
    $this->assertNull(DashboardBuildable::checkInstanceId('webdashboard_override__board__2'));
    $this->assertSame(
      ['webdashboard' => 'my__board', 'uid' => 12],
      DashboardOverrideBuildable::checkInstanceId('webdashboard_override__my__board__12'),
    );
    $this->assertNull(DashboardOverrideBuildable::checkInstanceId('webdashboard_override__board'));
    $this->assertNull(DashboardOverrideBuildable::checkInstanceId('webdashboard__board__2'));

    $dashboard = $this->createDashboard();
    $instance_storage = $this->container->get('entity_type.manager')->getStorage('display_builder_instance');
    $this->assertNotNull($instance_storage->load('webdashboard__board'), 'Saving a dashboard with a profile creates its builder instance.');

    $account = $this->createUser();
    $this->assertSame('webdashboard_override__board__' . $account->id(), $this->createOverride($dashboard, $account)->getInstanceId());
  }

  /**
   * Tests publishing the default dashboard.
   */
  public function testPublish(): void {
    $dashboard = $this->createDashboard();
    /** @var \Drupal\display_builder\DisplayBuildableInterface $buildable */
    $buildable = $this->buildableManager->createInstance('webdashboard', ['entity' => $dashboard]);
    $instance = $buildable->getInstance();
    $instance->setNewPresent([self::SOURCE], 'Add a text');
    $instance->save();
    $buildable->publish();

    $sources = Dashboard::load('board')->getSources();
    $this->assertCount(1, $sources);
    $this->assertSame('textfield', $sources[0]['source_id']);
  }

  /**
   * Tests personalizing a dashboard, and resetting it.
   */
  public function testOverride(): void {
    $dashboard = $this->createDashboard([self::SOURCE]);
    $account = $this->createUser([DashboardPermissions::overridePermission('board')]);
    $override = $this->createOverride($dashboard, $account);

    $this->assertFalse($dashboard->isOverridden($account));
    $this->assertSame([], $override->getSources());

    // The builder starts from the default dashboard.
    $override->initInstanceIfMissing();
    $this->assertSame('textfield', $override->getInstance()->getSources()[0]['source_id']);

    $personal = self::SOURCE;
    $personal['source']['value'] = 'Personal dashboard text';
    $dashboard->setOverriddenSources($account, [$personal]);
    $this->assertTrue($dashboard->isOverridden($account));
    $this->assertSame([$personal], $override->getSources());
    $this->assertNull($dashboard->getOverriddenSources($this->createUser()), 'Another user keeps the default dashboard.');

    // An emptied dashboard is still a personalized one.
    $dashboard->setOverriddenSources($account, []);
    $this->assertTrue($dashboard->isOverridden($account));

    $this->assertSame([self::SOURCE], $override->revert());
    $this->assertFalse($dashboard->isOverridden($account));
  }

  /**
   * Tests rendering the default and the personalized dashboard.
   */
  public function testRender(): void {
    $dashboard = $this->createDashboard([self::SOURCE]);
    $account = $this->createUser([DashboardPermissions::viewPermission('board')]);
    $this->setCurrentUser($account);
    $this->assertStringContainsString('Default dashboard text', $this->renderDashboard($dashboard));

    $personal = self::SOURCE;
    $personal['source']['value'] = 'Personal dashboard text';
    $dashboard->setOverriddenSources($account, [$personal]);
    $output = $this->renderDashboard($dashboard);
    $this->assertStringContainsString('Personal dashboard text', $output);
    $this->assertStringNotContainsString('Default dashboard text', $output);
  }

  /**
   * Tests the dashboard access and the builder instance access.
   */
  public function testAccess(): void {
    $dashboard = $this->createDashboard();
    $viewer = $this->createUser([DashboardPermissions::viewPermission('board')]);
    $personalizer = $this->createUser([
      DashboardPermissions::viewPermission('board'),
      DashboardPermissions::overridePermission('board'),
      'use display builder test',
    ]);

    $this->assertTrue($dashboard->access('view', $viewer));
    $this->assertFalse($dashboard->access('override', $viewer));
    $this->assertFalse($dashboard->access('update', $viewer));
    $this->assertTrue($dashboard->access('override', $personalizer));

    $own = 'webdashboard_override__board__' . $personalizer->id();
    $this->assertTrue(DashboardOverrideBuildable::checkAccess($own, $personalizer)->isAllowed());
    $this->assertTrue(DashboardOverrideBuildable::checkAccess($own, $viewer)->isForbidden(), 'Nobody builds the personalized dashboard of somebody else.');
    $this->assertFalse(DashboardBuildable::checkAccess('webdashboard__board', $personalizer)->isAllowed());

    $permissions = $this->container->get('user.permissions')->getPermissions();
    $this->assertSame(['config' => ['webdashboard.webdashboard.board']], $permissions[DashboardPermissions::viewPermission('board')]['dependencies']);
    $this->assertArrayHasKey(DashboardPermissions::overridePermission('board'), $permissions);
  }

  /**
   * Tests deleting a dashboard removes its instances and personalizations.
   */
  public function testDelete(): void {
    $dashboard = $this->createDashboard([self::SOURCE]);
    $account = $this->createUser();
    $dashboard->setOverriddenSources($account, [self::SOURCE]);
    $this->createOverride($dashboard, $account)->initInstanceIfMissing();

    $instance_storage = $this->container->get('entity_type.manager')->getStorage('display_builder_instance');
    $this->assertCount(2, $instance_storage->loadMultiple());

    $dashboard->delete();
    $this->assertCount(0, $instance_storage->loadMultiple());
    $this->assertNull($this->container->get('user.data')->get(DashboardInterface::USER_DATA_MODULE, (int) $account->id(), 'board'));
  }

  /**
   * Creates and saves a dashboard built with the test profile.
   *
   * @param array $sources
   *   (optional) The sources of the default dashboard.
   *
   * @return \Drupal\webdashboard\Entity\DashboardInterface
   *   The dashboard.
   */
  private function createDashboard(array $sources = []): DashboardInterface {
    $dashboard = Dashboard::create([
      'id' => 'board',
      'admin_label' => 'Board',
      'profile' => 'test',
      'sources' => $sources,
    ]);
    $dashboard->save();

    return $dashboard;
  }

  /**
   * Creates the buildable of the personalized dashboard of an account.
   *
   * @param \Drupal\webdashboard\Entity\DashboardInterface $dashboard
   *   The dashboard.
   * @param \Drupal\Core\Session\AccountInterface $account
   *   The account.
   *
   * @return \Drupal\display_builder\DisplayBuildableOverrideInterface
   *   The buildable.
   */
  private function createOverride(DashboardInterface $dashboard, AccountInterface $account): DisplayBuildableOverrideInterface {
    /** @var \Drupal\display_builder\DisplayBuildableOverrideInterface $override */
    $override = $this->buildableManager->createInstance('webdashboard_override', [
      'entity' => $dashboard,
      'uid' => (int) $account->id(),
    ]);

    return $override;
  }

  /**
   * Renders a dashboard for the current user.
   *
   * @param \Drupal\webdashboard\Entity\DashboardInterface $dashboard
   *   The dashboard.
   *
   * @return string
   *   The markup.
   */
  private function renderDashboard(DashboardInterface $dashboard): string {
    $build = $this->container->get('entity_type.manager')->getViewBuilder('webdashboard')->view($dashboard);

    return (string) $this->container->get('renderer')->renderInIsolation($build);
  }

}
