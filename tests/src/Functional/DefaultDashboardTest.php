<?php

declare(strict_types=1);

namespace Drupal\Tests\webdashboard\Functional;

use Drupal\Core\Url;
use Drupal\Tests\BrowserTestBase;
use Drupal\user\UserInterface;
use Drupal\webdashboard\Entity\Dashboard;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Symfony\Component\HttpFoundation\Response;

/**
 * Tests reaching the dashboards: the dashboard route, its link, the login.
 */
#[Group('webdashboard')]
#[RunTestsInSeparateProcesses]
final class DefaultDashboardTest extends BrowserTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['block', 'webdashboard'];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * An administrator, landing on the Webmaster dashboard.
   */
  private UserInterface $admin;

  /**
   * A content editor, landing on the Editorial dashboard.
   */
  private UserInterface $editor;

  /**
   * A user who can view no dashboard.
   */
  private UserInterface $nobody;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    Dashboard::create(['id' => 'webmaster', 'admin_label' => 'Webmaster', 'weight' => -20])->save();
    Dashboard::create(['id' => 'editorial', 'admin_label' => 'Editorial', 'weight' => -10])->save();
    // The link sits under "Administration", so the menu shows every level.
    $this->drupalPlaceBlock('system_menu_block:admin', ['expand_all_items' => TRUE]);

    $this->admin = $this->drupalCreateUser(['administer webdashboard', 'access administration pages']);
    $this->editor = $this->drupalCreateUser(['can view editorial webdashboard', 'access administration pages']);
    $this->nobody = $this->drupalCreateUser(['access administration pages']);
  }

  /**
   * Tests the dashboard route and its administration menu link.
   */
  public function testDashboardRoute(): void {
    $this->drupalLogin($this->admin);
    $this->drupalGet('admin/webdashboard');
    $this->assertSession()->statusCodeEquals(Response::HTTP_OK);
    $this->assertSession()->addressEquals('webdashboard/webmaster');
    $this->assertSession()->linkByHrefExists(Url::fromRoute('webdashboard.default')->toString());

    $this->drupalLogin($this->editor);
    $this->drupalGet('admin/webdashboard');
    $this->assertSession()->statusCodeEquals(Response::HTTP_OK);
    $this->assertSession()->addressEquals('webdashboard/editorial');
    $this->assertSession()->linkByHrefExists(Url::fromRoute('webdashboard.default')->toString());

    $this->drupalLogin($this->nobody);
    $this->drupalGet('admin/webdashboard');
    $this->assertSession()->statusCodeEquals(Response::HTTP_FORBIDDEN);
    $this->drupalGet('<front>');
    $this->assertSession()->linkByHrefNotExists(Url::fromRoute('webdashboard.default')->toString());

    // A new dashboard, lighter than the others, takes over.
    Dashboard::create(['id' => 'nobody', 'admin_label' => 'Everybody', 'weight' => -30])->save();
    $this->drupalLogin($this->admin);
    $this->drupalGet('admin/webdashboard');
    $this->assertSession()->addressEquals('webdashboard/nobody');
  }

  /**
   * Tests the redirect after login setting.
   */
  public function testLoginRedirect(): void {
    // Turned off by default.
    $this->loginThroughForm($this->editor);
    $this->assertSession()->addressEquals('user/' . $this->editor->id());
    $this->drupalLogout();

    $this->drupalLogin($this->admin);
    $this->drupalGet('admin/config/system/webdashboard');
    $this->assertSession()->checkboxNotChecked('redirect_after_login');
    $this->submitForm(['redirect_after_login' => TRUE], 'Save configuration');
    $this->assertSession()->checkboxChecked('redirect_after_login');
    $this->assertTrue($this->config('webdashboard.settings')->get('redirect_after_login'));
    $this->drupalLogout();

    $this->loginThroughForm($this->editor);
    $this->assertSession()->addressEquals('webdashboard/editorial');
    $this->drupalLogout();

    $this->loginThroughForm($this->admin);
    $this->assertSession()->addressEquals('webdashboard/webmaster');
    $this->drupalLogout();

    // Nobody is sent to a dashboard they cannot view.
    $this->loginThroughForm($this->nobody);
    $this->assertSession()->addressEquals('user/' . $this->nobody->id());
    $this->drupalLogout();

    // A destination in the login link still wins.
    $this->loginThroughForm($this->editor, ['destination' => Url::fromRoute('system.admin_config')->toString()]);
    $this->assertSession()->addressEquals('admin/config');
    $this->drupalLogout();

    $this->config('webdashboard.settings')->set('redirect_after_login', FALSE)->save();
    $this->loginThroughForm($this->editor);
    $this->assertSession()->addressEquals('user/' . $this->editor->id());
  }

  /**
   * Logs in through the login form, where the login redirect applies.
   *
   * The one-time login links of ::drupalLogin() carry a destination of their
   * own.
   *
   * @param \Drupal\user\UserInterface $account
   *   The account.
   * @param array $query
   *   (optional) The query of the login page.
   */
  private function loginThroughForm(UserInterface $account, array $query = []): void {
    $this->drupalGet(Url::fromRoute('user.login', [], ['query' => $query]));
    $this->submitForm([
      'name' => $account->getAccountName(),
      'pass' => $account->passRaw,
    ], 'Log in');

    $account->sessionId = $this->getSession()->getCookie(\Drupal::service('session_configuration')->getOptions(\Drupal::request())['name']);
    $this->assertTrue($this->drupalUserIsLoggedIn($account));
    $this->loggedInUser = $account;
    $this->container->get('current_user')->setAccount($account);
  }

}
