<?php

namespace Drupal\Tests\update\Functional;

use Drupal\Core\Link;
use Drupal\Core\Url;
use Drupal\Core\Utility\ProjectInfo;
use Drupal\update\UpdateManagerInterface;

/**
 * Tests how the Update Manager module handles contributed modules and themes in
 * a series of functional tests using mock XML data.
 *
 * @group update
 */
class UpdateContribTest extends UpdateTestBase {

  /**
   * {@inheritdoc}
   */
  protected $updateTableLocator = 'table.update:nth-of-type(2)';

  /**
   * {@inheritdoc}
   */
  protected $updateProject = 'aaa_update_test';

  /**
   * Modules to enable.
   *
   * @var array
   */
  public static $modules = ['update_test', 'update', 'aaa_update_test', 'bbb_update_test', 'ccc_update_test'];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  protected function setUp() {
    parent::setUp();
    $admin_user = $this->drupalCreateUser(['administer site configuration']);
    $this->drupalLogin($admin_user);
  }

  /**
   * Tests when there is no available release data for a contrib module.
   */
  public function testNoReleasesAvailable() {
    $system_info = [
      '#all' => [
        'version' => '8.0.0',
      ],
      'aaa_update_test' => [
        'project' => 'aaa_update_test',
        'version' => '8.x-1.0',
        'hidden' => FALSE,
      ],
    ];
    $this->config('update_test.settings')->set('system_info', $system_info)->save();
    $this->refreshUpdateStatus(['drupal' => '0.0', 'aaa_update_test' => 'no-releases']);
    $this->drupalGet('admin/reports/updates');
    // Cannot use $this->standardTests() because we need to check for the
    // 'No available releases found' string.
    $this->assertRaw('<h3>' . t('Drupal core') . '</h3>');
    $this->assertRaw(Link::fromTextAndUrl(t('Drupal'), Url::fromUri('http://example.com/project/drupal'))->toString());
    $this->assertText(t('Up to date'));
    $this->assertRaw('<h3>' . t('Modules') . '</h3>');
    $this->assertNoText(t('Update available'));
    $this->assertText(t('No available releases found'));
    $this->assertNoRaw(Link::fromTextAndUrl(t('AAA Update test'), Url::fromUri('http://example.com/project/aaa_update_test'))->toString());

    $available = update_get_available();
    $this->assertFalse(isset($available['aaa_update_test']['fetch_status']), 'Results are cached even if no releases are available.');
  }

  /**
   * Tests the basic functionality of a contrib module on the status report.
   */
  public function testUpdateContribBasic() {
    $project_link = Link::fromTextAndUrl(t('AAA Update test'), Url::fromUri('http://example.com/project/aaa_update_test'))->toString();
    $system_info = [
      '#all' => [
        'version' => '8.0.0',
      ],
      'aaa_update_test' => [
        'project' => 'aaa_update_test',
        'version' => '8.x-1.0',
        'hidden' => FALSE,
      ],
    ];
    $this->config('update_test.settings')->set('system_info', $system_info)->save();
    $this->refreshUpdateStatus(
      [
        'drupal' => '0.0',
        'aaa_update_test' => '1_0',
      ]
    );
    $this->standardTests();
    $this->assertText(t('Up to date'));
    $this->assertRaw('<h3>' . t('Modules') . '</h3>');
    $this->assertNoText(t('Update available'));
    $this->assertRaw($project_link, 'Link to aaa_update_test project appears.');

    // Since aaa_update_test is installed the fact it is hidden and in the
    // Testing package means it should not appear.
    $system_info['aaa_update_test']['hidden'] = TRUE;
    $this->config('update_test.settings')->set('system_info', $system_info)->save();
    $this->refreshUpdateStatus(
      [
        'drupal' => '0.0',
        'aaa_update_test' => '1_0',
      ]
    );
    $this->assertNoRaw($project_link, 'Link to aaa_update_test project does not appear.');

    // A hidden and installed project not in the Testing package should appear.
    $system_info['aaa_update_test']['package'] = 'aaa_update_test';
    $this->config('update_test.settings')->set('system_info', $system_info)->save();
    $this->refreshUpdateStatus(
      [
        'drupal' => '0.0',
        'aaa_update_test' => '1_0',
      ]
    );
    $this->assertRaw($project_link, 'Link to aaa_update_test project appears.');
  }

  /**
   * Tests that contrib projects are ordered by project name.
   *
   * If a project contains multiple modules, we want to make sure that the
   * available updates report is sorted by the parent project names, not by the
   * names of the modules included in each project. In this test case, we have
   * two contrib projects, "BBB Update test" and "CCC Update test". However, we
   * have a module called "aaa_update_test" that's part of the "CCC Update test"
   * project. We need to make sure that we see the "BBB" project before the
   * "CCC" project, even though "CCC" includes a module that's processed first
   * if you sort alphabetically by module name (which is the order we see things
   * inside \Drupal\Core\Extension\ExtensionList::getList() for example).
   */
  public function testUpdateContribOrder() {
    // We want core to be version 8.0.0.
    $system_info = [
      '#all' => [
        'version' => '8.0.0',
      ],
      // All the rest should be visible as contrib modules at version 8.x-1.0.

      // aaa_update_test needs to be part of the "CCC Update test" project,
      // which would throw off the report if we weren't properly sorting by
      // the project names.
      'aaa_update_test' => [
        'project' => 'ccc_update_test',
        'version' => '8.x-1.0',
        'hidden' => FALSE,
      ],

      // This should be its own project, and listed first on the report.
      'bbb_update_test' => [
        'project' => 'bbb_update_test',
        'version' => '8.x-1.0',
        'hidden' => FALSE,
      ],

      // This will contain both aaa_update_test and ccc_update_test, and
      // should come after the bbb_update_test project.
      'ccc_update_test' => [
        'project' => 'ccc_update_test',
        'version' => '8.x-1.0',
        'hidden' => FALSE,
      ],
    ];
    $this->config('update_test.settings')->set('system_info', $system_info)->save();
    $this->refreshUpdateStatus(['drupal' => '0.0', '#all' => '1_0']);
    $this->standardTests();
    // We're expecting the report to say all projects are up to date.
    $this->assertText(t('Up to date'));
    $this->assertNoText(t('Update available'));
    // We want to see all 3 module names listed, since they'll show up either
    // as project names or as modules under the "Includes" listing.
    $this->assertText(t('AAA Update test'));
    $this->assertText(t('BBB Update test'));
    $this->assertText(t('CCC Update test'));
    // We want aaa_update_test included in the ccc_update_test project, not as
    // its own project on the report.
    $this->assertNoRaw(Link::fromTextAndUrl(t('AAA Update test'), Url::fromUri('http://example.com/project/aaa_update_test'))->toString(), 'Link to aaa_update_test project does not appear.');
    // The other two should be listed as projects.
    $this->assertRaw(Link::fromTextAndUrl(t('BBB Update test'), Url::fromUri('http://example.com/project/bbb_update_test'))->toString(), 'Link to bbb_update_test project appears.');
    $this->assertRaw(Link::fromTextAndUrl(t('CCC Update test'), Url::fromUri('http://example.com/project/ccc_update_test'))->toString(), 'Link to bbb_update_test project appears.');

    // We want to make sure we see the BBB project before the CCC project.
    // Instead of just searching for 'BBB Update test' or something, we want
    // to use the full markup that starts the project entry itself, so that
    // we're really testing that the project listings are in the right order.
    $bbb_project_link = '<div class="project-update__title"><a href="http://example.com/project/bbb_update_test">BBB Update test</a>';
    $ccc_project_link = '<div class="project-update__title"><a href="http://example.com/project/ccc_update_test">CCC Update test</a>';
    $this->assertTrue(strpos($this->getSession()->getPage()->getContent(), $bbb_project_link) < strpos($this->getSession()->getPage()->getContent(), $ccc_project_link), "'BBB Update test' project is listed before the 'CCC Update test' project");
  }

  /**
   * Tests that subthemes are notified about security updates for base themes.
   */
  public function testUpdateBaseThemeSecurityUpdate() {
    // @todo https://www.drupal.org/node/2338175 base themes have to be
    //  installed.
    // Only install the subtheme, not the base theme.
    \Drupal::service('theme_installer')->install(['update_test_subtheme']);

    // Define the initial state for core and the subtheme.
    $system_info = [
      // We want core to be version 8.0.0.
      '#all' => [
        'version' => '8.0.0',
      ],
      // Show the update_test_basetheme
      'update_test_basetheme' => [
        'project' => 'update_test_basetheme',
        'version' => '8.x-1.0',
        'hidden' => FALSE,
      ],
      // Show the update_test_subtheme
      'update_test_subtheme' => [
        'project' => 'update_test_subtheme',
        'version' => '8.x-1.0',
        'hidden' => FALSE,
      ],
    ];
    $this->config('update_test.settings')->set('system_info', $system_info)->save();
    $xml_mapping = [
      'drupal' => '0.0',
      'update_test_subtheme' => '1_0',
      'update_test_basetheme' => '1_1-sec',
    ];
    $this->refreshUpdateStatus($xml_mapping);
    $this->assertText(t('Security update required!'));
    $this->assertRaw(Link::fromTextAndUrl(t('Update test base theme'), Url::fromUri('http://example.com/project/update_test_basetheme'))->toString(), 'Link to the Update test base theme project appears.');
  }

  /**
   * Tests the Update Manager module when one normal update is available.
   */
  public function testNormalUpdateAvailable() {
    $assert_session = $this->assertSession();
    // Ensure that the update check requires a token.
    $this->drupalGet('admin/reports/updates/check');
    $assert_session->statusCodeEquals(403);

    $system_info = [
      'aaa_update_test' => [
        'project' => 'aaa_update_test',
        'version' => '8.x-1.0',
        'hidden' => FALSE,
      ],
    ];
    $this->config('update_test.settings')->set('system_info', $system_info)->save();

    foreach (['1.1', '1.2', '2.0'] as $version) {
      foreach (['-beta1', '-alpha1', ''] as $extra_version) {
        $full_version = "8.x-$version$extra_version";
        $this->refreshUpdateStatus([
          'drupal' => '0.0',
          'aaa_update_test' => str_replace('.', '_', $version) . $extra_version,
        ]);
        $this->standardTests();
        $this->drupalGet('admin/reports/updates');
        $this->clickLink('Check manually');
        $this->checkForMetaRefresh();
        $assert_session->pageTextNotContains('Security update required!');
        // Set a CSS selector in order for assertions to target the 'Modules'
        // table and not Drupal core updates.
        $this->updateTableLocator = 'table.update:nth-of-type(2)';
        switch ($version) {
          case '1.1':
            // Both stable and unstable releases are available.
            // A stable release is the latest.
            if ($extra_version == '') {
              $assert_session->elementTextNotContains('css', $this->updateTableLocator, 'Up to date');
              $assert_session->elementTextContains('css', $this->updateTableLocator, 'Update available');
              $this->assertVersionUpdateLinks('Recommended version', $full_version);
              $assert_session->elementTextNotContains('css', $this->updateTableLocator, 'Latest version:');
              $assert_session->elementContains('css', $this->updateTableLocator, 'warning.svg');
            }
            // Only unstable releases are available.
            // An unstable release is the latest.
            else {
              $assert_session->elementTextContains('css', $this->updateTableLocator, 'Up to date');
              $assert_session->elementTextNotContains('css', $this->updateTableLocator, 'Update available');
              $assert_session->elementTextNotContains('css', $this->updateTableLocator, 'Recommended version:');
              $this->assertVersionUpdateLinks('Latest version', $full_version);
              $assert_session->elementContains('css', $this->updateTableLocator, 'check.svg');
            }
            break;

          case '1.2':
            // Both stable and unstable releases are available.
            // A stable release is the latest.
            if ($extra_version == '') {
              $assert_session->elementTextNotContains('css', $this->updateTableLocator, 'Up to date');
              $assert_session->elementTextContains('css', $this->updateTableLocator, 'Update available');
              $this->assertVersionUpdateLinks('Recommended version:', $full_version);
              $assert_session->elementTextNotContains('css', $this->updateTableLocator, 'Latest version:');
              $assert_session->elementContains('css', $this->updateTableLocator, 'warning.svg');
            }
            // Both stable and unstable releases are available.
            // An unstable release is the latest.
            else {
              $assert_session->elementTextNotContains('css', $this->updateTableLocator, 'Up to date');
              $assert_session->elementTextContains('css', $this->updateTableLocator, 'Update available');
              $this->assertVersionUpdateLinks('Recommended version:', '8.x-1.1');
              $this->assertVersionUpdateLinks('Latest version:', $full_version);
              $assert_session->elementTextContains('css', $this->updateTableLocator, 'Latest version:');
              $assert_session->elementContains('css', $this->updateTableLocator, 'warning.svg');
            }
            break;

          case '2.0':
            // When next major release (either stable or unstable) is available
            // and the current major is still supported, the next major will be
            // listed as "Also available".
            $assert_session->elementTextNotContains('css', $this->updateTableLocator, 'Up to date');
            $assert_session->elementTextContains('css', $this->updateTableLocator, 'Update available');
            $this->assertVersionUpdateLinks('Recommended version', '8.x-1.2');
            $this->assertVersionUpdateLinks('Also available', $full_version);
            $assert_session->elementTextNotContains('css', $this->updateTableLocator, 'Latest version:');
            $assert_session->elementContains('css', $this->updateTableLocator, 'warning.svg');
        }
      }
    }
  }

  /**
   * Tests that disabled themes are only shown when desired.
   *
   * @todo https://www.drupal.org/node/2338175 extensions can not be hidden and
   *   base themes have to be installed.
   */
  public function testUpdateShowDisabledThemes() {
    $update_settings = $this->config('update.settings');
    // Make sure all the update_test_* themes are disabled.
    $extension_config = $this->config('core.extension');
    foreach ($extension_config->get('theme') as $theme => $weight) {
      if (preg_match('/^update_test_/', $theme)) {
        $extension_config->clear("theme.$theme");
      }
    }
    $extension_config->save();

    // Define the initial state for core and the test contrib themes.
    $system_info = [
      // We want core to be version 8.0.0.
      '#all' => [
        'version' => '8.0.0',
      ],
      // The update_test_basetheme should be visible and up to date.
      'update_test_basetheme' => [
        'project' => 'update_test_basetheme',
        'version' => '8.x-1.1',
        'hidden' => FALSE,
      ],
      // The update_test_subtheme should be visible and up to date.
      'update_test_subtheme' => [
        'project' => 'update_test_subtheme',
        'version' => '8.x-1.0',
        'hidden' => FALSE,
      ],
    ];
    // When there are contributed modules in the site's file system, the
    // total number of attempts made in the test may exceed the default value
    // of update_max_fetch_attempts. Therefore this variable is set very high
    // to avoid test failures in those cases.
    $update_settings->set('fetch.max_attempts', 99999)->save();
    $this->config('update_test.settings')->set('system_info', $system_info)->save();
    $xml_mapping = [
      'drupal' => '0.0',
      'update_test_subtheme' => '1_0',
      'update_test_basetheme' => '1_1-sec',
    ];
    $base_theme_project_link = Link::fromTextAndUrl(t('Update test base theme'), Url::fromUri('http://example.com/project/update_test_basetheme'))->toString();
    $sub_theme_project_link = Link::fromTextAndUrl(t('Update test subtheme'), Url::fromUri('http://example.com/project/update_test_subtheme'))->toString();
    foreach ([TRUE, FALSE] as $check_disabled) {
      $update_settings->set('check.disabled_extensions', $check_disabled)->save();
      $this->refreshUpdateStatus($xml_mapping);
      // In neither case should we see the "Themes" heading for installed
      // themes.
      $this->assertNoText(t('Themes'));
      if ($check_disabled) {
        $this->assertText(t('Uninstalled themes'));
        $this->assertRaw($base_theme_project_link, 'Link to the Update test base theme project appears.');
        $this->assertRaw($sub_theme_project_link, 'Link to the Update test subtheme project appears.');
      }
      else {
        $this->assertNoText(t('Uninstalled themes'));
        $this->assertNoRaw($base_theme_project_link, 'Link to the Update test base theme project does not appear.');
        $this->assertNoRaw($sub_theme_project_link, 'Link to the Update test subtheme project does not appear.');
      }
    }
  }

  /**
   * Tests updates with a hidden base theme.
   */
  public function testUpdateHiddenBaseTheme() {
    module_load_include('compare.inc', 'update');

    // Install the subtheme.
    \Drupal::service('theme_installer')->install(['update_test_subtheme']);

    // Add a project and initial state for base theme and subtheme.
    $system_info = [
      // Hide the update_test_basetheme.
      'update_test_basetheme' => [
        'project' => 'update_test_basetheme',
        'hidden' => TRUE,
      ],
      // Show the update_test_subtheme.
      'update_test_subtheme' => [
        'project' => 'update_test_subtheme',
        'hidden' => FALSE,
      ],
    ];
    $this->config('update_test.settings')->set('system_info', $system_info)->save();
    $projects = \Drupal::service('update.manager')->getProjects();
    $theme_data = \Drupal::service('theme_handler')->rebuildThemeData();
    $project_info = new ProjectInfo();
    $project_info->processInfoList($projects, $theme_data, 'theme', TRUE);

    $this->assertTrue(!empty($projects['update_test_basetheme']), 'Valid base theme (update_test_basetheme) was found.');
  }

  /**
   * Makes sure that if we fetch from a broken URL, sane things happen.
   */
  public function testUpdateBrokenFetchURL() {
    $system_info = [
      '#all' => [
        'version' => '8.0.0',
      ],
      'aaa_update_test' => [
        'project' => 'aaa_update_test',
        'version' => '8.x-1.0',
        'hidden' => FALSE,
      ],
      'bbb_update_test' => [
        'project' => 'bbb_update_test',
        'version' => '8.x-1.0',
        'hidden' => FALSE,
      ],
      'ccc_update_test' => [
        'project' => 'ccc_update_test',
        'version' => '8.x-1.0',
        'hidden' => FALSE,
      ],
    ];
    $this->config('update_test.settings')->set('system_info', $system_info)->save();

    // Ensure that the update information is correct before testing.
    $this->drupalGet('admin/reports/updates');

    $xml_mapping = [
      'drupal' => '0.0',
      'aaa_update_test' => '1_0',
      'bbb_update_test' => 'does-not-exist',
      'ccc_update_test' => '1_0',
    ];
    $this->refreshUpdateStatus($xml_mapping);

    $this->assertText(t('Up to date'));
    // We're expecting the report to say most projects are up to date, so we
    // hope that 'Up to date' is not unique.
    $this->assertNoUniqueText(t('Up to date'));
    // It should say we failed to get data, not that we're missing an update.
    $this->assertNoText(t('Update available'));

    // We need to check that this string is found as part of a project row, not
    // just in the "Failed to get available update data" message at the top of
    // the page.
    $this->assertRaw('<div class="project-update__status">' . t('Failed to get available update data'));

    // We should see the output messages from fetching manually.
    $this->assertUniqueText(t('Checked available update data for 3 projects.'));
    $this->assertUniqueText(t('Failed to get available update data for one project.'));

    // The other two should be listed as projects.
    $this->assertRaw(Link::fromTextAndUrl(t('AAA Update test'), Url::fromUri('http://example.com/project/aaa_update_test'))->toString(), 'Link to aaa_update_test project appears.');
    $this->assertNoRaw(Link::fromTextAndUrl(t('BBB Update test'), Url::fromUri('http://example.com/project/bbb_update_test'))->toString(), 'Link to bbb_update_test project does not appear.');
    $this->assertRaw(Link::fromTextAndUrl(t('CCC Update test'), Url::fromUri('http://example.com/project/ccc_update_test'))->toString(), 'Link to bbb_update_test project appears.');
  }

  /**
   * Checks that hook_update_status_alter() works to change a status.
   *
   * We provide the same external data as if aaa_update_test 8.x-1.0 were
   * installed and that was the latest release. Then we use
   * hook_update_status_alter() to try to mark this as missing a security
   * update, then assert if we see the appropriate warnings on the right pages.
   */
  public function testHookUpdateStatusAlter() {
    $update_test_config = $this->config('update_test.settings');
    $update_admin_user = $this->drupalCreateUser(['administer site configuration', 'administer software updates']);
    $this->drupalLogin($update_admin_user);

    $system_info = [
      '#all' => [
        'version' => '8.0.0',
      ],
      'aaa_update_test' => [
        'project' => 'aaa_update_test',
        'version' => '8.x-1.0',
        'hidden' => FALSE,
      ],
    ];
    $update_test_config->set('system_info', $system_info)->save();
    $update_status = [
      'aaa_update_test' => [
        'status' => UpdateManagerInterface::NOT_SECURE,
      ],
    ];
    $update_test_config->set('update_status', $update_status)->save();
    $this->refreshUpdateStatus(
      [
        'drupal' => '0.0',
        'aaa_update_test' => '1_0',
      ]
    );
    $this->drupalGet('admin/reports/updates');
    $this->assertRaw('<h3>' . t('Modules') . '</h3>');
    $this->assertText(t('Security update required!'));
    $this->assertRaw(Link::fromTextAndUrl(t('AAA Update test'), Url::fromUri('http://example.com/project/aaa_update_test'))->toString(), 'Link to aaa_update_test project appears.');

    // Visit the reports page again without the altering and make sure the
    // status is back to normal.
    $update_test_config->set('update_status', [])->save();
    $this->drupalGet('admin/reports/updates');
    $this->assertRaw('<h3>' . t('Modules') . '</h3>');
    $this->assertNoText(t('Security update required!'));
    $this->assertRaw(Link::fromTextAndUrl(t('AAA Update test'), Url::fromUri('http://example.com/project/aaa_update_test'))->toString(), 'Link to aaa_update_test project appears.');

    // Turn the altering back on and visit the Update manager UI.
    $update_test_config->set('update_status', $update_status)->save();
    $this->drupalGet('admin/modules/update');
    $this->assertText(t('Security update'));

    // Turn the altering back off and visit the Update manager UI.
    $update_test_config->set('update_status', [])->save();
    $this->drupalGet('admin/modules/update');
    $this->assertNoText(t('Security update'));
  }

  /**
   * Tests that core compatibility messages are displayed.
   */
  public function testCoreCompatibilityMessage() {
    $system_info = [
      '#all' => [
        'version' => '8.0.0',
      ],
      'aaa_update_test' => [
        'project' => 'aaa_update_test',
        'version' => '8.x-1.0',
        'hidden' => FALSE,
      ],
    ];
    $this->config('update_test.settings')->set('system_info', $system_info)->save();

    // Confirm that messages are displayed for recommended and latest updates.
    $this->refreshUpdateStatus(['drupal' => '1.1', 'aaa_update_test' => '8.x-1.2']);
    $this->assertCoreCompatibilityMessage('8.x-1.2', '8.0.0 to 8.1.1', 'Recommended version:');
    $this->assertCoreCompatibilityMessage('8.x-1.3-beta1', '8.0.0, 8.1.1', 'Latest version:');

    // Change the available core releases and confirm that the messages change.
    $this->refreshUpdateStatus(['drupal' => '1.1-alpha1', 'aaa_update_test' => '8.x-1.2']);
    $this->assertCoreCompatibilityMessage('8.x-1.2', '8.0.0 to 8.1.0', 'Recommended version:');
    $this->assertCoreCompatibilityMessage('8.x-1.3-beta1', '8.0.0', 'Latest version:');

    // Confirm that messages are displayed for security and 'Also available'
    // updates.
    $this->refreshUpdateStatus(['drupal' => '1.1', 'aaa_update_test' => 'sec.8.x-1.2_8.x-2.2']);
    $this->assertCoreCompatibilityMessage('8.x-1.2', '8.1.0 to 8.1.1', 'Security update:');
    $this->assertCoreCompatibilityMessage('8.x-2.2', '8.1.1', 'Also available:');
  }

  /**
   * Tests update status of security releases.
   *
   * @param string $module_version
   *   The module version the site is using.
   * @param string[] $expected_security_releases
   *   The security releases, if any, that the status report should recommend.
   * @param string $expected_update_message_type
   *   The type of update message expected.
   * @param string $fixture
   *   The fixture file to use.
   *
   * @dataProvider securityUpdateAvailabilityProvider
   */
  public function testSecurityUpdateAvailability($module_version, array $expected_security_releases, $expected_update_message_type, $fixture) {
    $system_info = [
      '#all' => [
        'version' => '8.0.0',
      ],
      'aaa_update_test' => [
        'project' => 'aaa_update_test',
        'version' => $module_version,
        'hidden' => FALSE,
      ],
    ];
    $this->config('update_test.settings')->set('system_info', $system_info)->save();
    $this->refreshUpdateStatus(['drupal' => '0.0', 'aaa_update_test' => $fixture]);
    $this->assertSecurityUpdates('aaa_update_test', $expected_security_releases, $expected_update_message_type, 'table.update:nth-of-type(2)');
  }

  /**
   * Data provider method for testSecurityUpdateAvailability().
   *
   * These test cases rely on the following fixtures containing the following
   * releases:
   * - aaa_update_test.sec.8.x-1.2.xml
   *   - 8.x-1.2 Security update
   *   - 8.x-1.1 Insecure
   *   - 8.x-1.0 Insecure
   * - aaa_update_test.sec.8.x-1.1_8.x-1.2.xml
   *   - 8.x-1.2 Security update
   *   - 8.x-1.1 Security update, Insecure
   *   - 8.x-1.0 Insecure
   * - aaa_update_test.sec.8.x-1.2_8.x-2.2.xml
   *   - 8.x-3.0-beta2
   *   - 8.x-3.0-beta1 Insecure
   *   - 8.x-2.2 Security update
   *   - 8.x-2.1 Security update, Insecure
   *   - 8.x-2.0 Insecure
   *   - 8.x-1.2 Security update
   *   - 8.x-1.1 Insecure
   *   - 8.x-1.0 Insecure
   * - aaa_update_test.sec.8.x-2.2_1.x_secure.xml
   *   - 8.x-2.2 Security update
   *   - 8.x-2.1 Security update, Insecure
   *   - 8.x-2.0 Insecure
   *   - 8.x-1.2
   *   - 8.x-1.1
   *   - 8.x-1.0
   */
  public function securityUpdateAvailabilityProvider() {
    return [
      // Security releases available for module major release 1.
      // No releases for next major.
      '8.x-1.0, 8.x-1.2' => [
        'module_patch_version' => '8.x-1.0',
        'expected_security_releases' => ['8.x-1.2'],
        'expected_update_message_type' => static::SECURITY_UPDATE_REQUIRED,
        'fixture' => 'sec.8.x-1.2',
      ],
      // Two security releases available for module major release 1.
      // 8.x-1.1 security release marked as insecure.
      // No releases for next major.
      '8.x-1.0, 8.x-1.1 8.x-1.2' => [
        'module_patch_version' => '8.x-1.0',
        'expected_security_releases' => ['8.x-1.2'],
        'expected_update_message_type' => static::SECURITY_UPDATE_REQUIRED,
        'fixture' => 'sec.8.x-1.1_8.x-1.2',
      ],
      // Security release available for module major release 2.
      // No releases for next major.
      '8.x-2.0, 8.x-2.2' => [
        'module_patch_version' => '8.x-2.0',
        'expected_security_releases' => ['8.x-2.2'],
        'expected_update_message_type' => static::SECURITY_UPDATE_REQUIRED,
        'fixture' => 'sec.8.x-2.2_1.x_secure',
      ],
      '8.x-2.2, 8.x-1.2 8.x-2.2' => [
        'module_patch_version' => '8.x-2.2',
        'expected_security_releases' => [],
        'expected_update_message_type' => static::UPDATE_NONE,
        'fixture' => 'sec.8.x-1.2_8.x-2.2',
      ],
      // Security release available for module major release 1.
      // Security release also available for next major.
      '8.x-1.0, 8.x-1.2 8.x-2.2' => [
        'module_patch_version' => '8.x-1.0',
        'expected_security_releases' => ['8.x-1.2'],
        'expected_update_message_type' => static::SECURITY_UPDATE_REQUIRED,
        'fixture' => 'sec.8.x-1.2_8.x-2.2',
      ],
      // No security release available for module major release 1 but 1.x
      // releases are not marked as insecure.
      // Security release available for next major.
      '8.x-1.0, 8.x-2.2, not insecure' => [
        'module_patch_version' => '8.x-1.0',
        'expected_security_releases' => [],
        'expected_update_message_type' => static::UPDATE_AVAILABLE,
        'fixture' => 'sec.8.x-2.2_1.x_secure',
      ],
      // On latest security release for module major release 1.
      // Security release also available for next major.
      '8.x-1.2, 8.x-1.2 8.x-2.2' => [
        'module_patch_version' => '8.x-1.2',
        'expected_security_release' => [],
        'expected_update_message_type' => static::UPDATE_NONE,
        'fixture' => 'sec.8.x-1.2_8.x-2.2',
      ],
      // @todo In https://www.drupal.org/node/2865920 add test cases:
      //   - 8.x-2.0 using fixture 'sec.8.x-1.2_8.x-2.2' to ensure that 8.x-2.2
      //     is the only security update.
      //   - 8.x-3.0-beta1 using fixture 'sec.8.x-1.2_8.x-2.2' to ensure that
      //     8.x-2.2 is the  only security update.
    ];
  }

  /**
   * Tests messages when a project release is unpublished.
   *
   * This test confirms that revoked messages are displayed regardless of
   * whether the installed version is in a supported branch or not. This test
   * relies on 2 test XML fixtures that are identical except for the
   * 'supported_branches' value:
   * - aaa_update_test.1_0-supported.xml
   *    'supported_branches' is '8.x-1.,8.x-2.'.
   * - aaa_update_test.1_0-unsupported.xml
   *    'supported_branches' is '8.x-2.'.
   * They both have an '8.x-1.0' release that is unpublished and an '8.x-2.0'
   * release that is published and is the expected update.
   */
  public function testRevokedRelease() {
    $system_info = [
      'aaa_update_test' => [
        'project' => 'aaa_update_test',
        'version' => '8.x-1.0',
        'hidden' => FALSE,
      ],
    ];
    $this->config('update_test.settings')->set('system_info', $system_info)->save();
    $this->refreshUpdateStatus([
      'drupal' => '0.0',
      $this->updateProject => '1_0-supported',
    ]);
    // @todo Change the version label to 'Recommended version:' in
    // https://www.drupal.org/node/3114408.
    $this->confirmRevokedStatus('8.x-1.0', '8.x-2.0', 'Also available:');

    $this->refreshUpdateStatus([
      'drupal' => '0.0',
      $this->updateProject => '1_0-unsupported',
    ]);
    $this->confirmRevokedStatus('8.x-1.0', '8.x-2.0', 'Recommended version:');
  }

  /**
   * Tests messages when a project release is marked unsupported.
   *
   * This test confirms unsupported messages are displayed regardless of whether
   * the installed version is in a supported branch or not. This test relies on
   * 2 test XML fixtures that are identical except for the 'supported_branches'
   * value:
   * - aaa_update_test.1_0-supported.xml
   *    'supported_branches' is '8.x-1.,8.x-2.'.
   * - aaa_update_test.1_0-unsupported.xml
   *    'supported_branches' is '8.x-2.'.
   * They both have an '8.x-1.1' release that has the 'Release type' value of
   * 'unsupported' and an '8.x-2.0' release that has the 'Release type' value of
   * 'supported' and is the expected update.
   */
  public function testUnsupportedRelease() {
    $system_info = [
      'aaa_update_test' => [
        'project' => 'aaa_update_test',
        'version' => '8.x-1.1',
        'hidden' => FALSE,
      ],
    ];
    $this->config('update_test.settings')->set('system_info', $system_info)->save();
    $this->refreshUpdateStatus([
      'drupal' => '0.0',
      $this->updateProject => '1_0-supported',
    ]);
    // @todo Change the version label to 'Recommended version:' in
    // https://www.drupal.org/node/3114408.
    $this->confirmUnsupportedStatus('8.x-1.1', '8.x-2.0', 'Also available:');

    $this->refreshUpdateStatus([
      'drupal' => '0.0',
      $this->updateProject => '1_0-unsupported',
    ]);
    $this->confirmUnsupportedStatus('8.x-1.1', '8.x-2.0', 'Recommended version:');
  }

  /**
   * Asserts that a core compatibility message is correct for an update.
   *
   * @param string $version
   *   The version of the update.
   * @param string $expected_compatibility_range
   *   The expected core compatibility range.
   * @param string $expected_release_title
   *   The expected release title.
   */
  protected function assertCoreCompatibilityMessage($version, $expected_compatibility_range, $expected_release_title) {
    $link = $this->getSession()->getPage()->findLink($version);
    $update_info_element = $link->getParent();
    $this->assertContains("This module is compatible with Drupal core: $expected_compatibility_range", $update_info_element->getText());
    $update_title_element = $update_info_element->getParent()->find('css', '.project-update__version-title');
    $this->assertSame($expected_release_title, $update_title_element->getText());
  }

}
