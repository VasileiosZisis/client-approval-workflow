<?php

use Vzisis\ClientApprovalWorkflow\Admin;
use Vzisis\ClientApprovalWorkflow\Lifecycle;
use Vzisis\ClientApprovalWorkflow\Onboarding;
use Vzisis\ClientApprovalWorkflow\Release_Notices;
use Vzisis\ClientApprovalWorkflow\Sample_Content;
use Vzisis\ClientApprovalWorkflow\Settings;

/**
 * Feature announcements are upgrade-only, contextual, and personally dismissible.
 */
class ReleaseNoticesTest extends Cliapwo_Test_Case
{
	public function set_up()
	{
		parent::set_up();
		delete_option(Release_Notices::VERSION_OPTION);
		delete_option(Release_Notices::ANNOUNCEMENT_OPTION);
		update_option(Settings::OPTION_KEY, Settings::get_default_settings());
	}

	public function tear_down()
	{
		set_current_screen('front');
		parent::tear_down();
	}

	private function dismiss(Release_Notices $notices)
	{
		$method = new ReflectionMethod(Release_Notices::class, 'dismiss');
		if (PHP_VERSION_ID < 80100) {
			$method->setAccessible(true);
		}
		return $method->invoke($notices);
	}

	private function valid_submission($return_to = 'settings')
	{
		$_POST = array(
			Release_Notices::NONCE_NAME => wp_create_nonce(Release_Notices::NONCE_ACTION),
			'cliapwo_whats_new_release' => Release_Notices::RELEASE,
			'cliapwo_whats_new_return' => $return_to,
		);
	}

	private function rendered(Release_Notices $notices)
	{
		ob_start();
		$notices->render();
		return ob_get_clean();
	}

	public function test_fresh_activation_suppresses_notice_and_preserves_onboarding()
	{
		delete_option(Settings::OPTION_KEY);
		Lifecycle::activate();
		(new Release_Notices())->detect_upgrade();
		$this->assertSame(CLIAPWO_VERSION, get_option(Release_Notices::VERSION_OPTION));
		$this->assertFalse(get_option(Release_Notices::ANNOUNCEMENT_OPTION));
		$this->assertNotFalse(get_option('cliapwo_onboarding_first_run'));
	}

	public function test_legacy_upgrade_detects_once_with_non_autoloaded_options()
	{
		$notices = new Release_Notices();
		$notices->detect_upgrade();
		$notices->detect_upgrade();
		$this->assertSame(CLIAPWO_VERSION, get_option(Release_Notices::VERSION_OPTION));
		$this->assertSame(Release_Notices::RELEASE, get_option(Release_Notices::ANNOUNCEMENT_OPTION));
		$autoloaded = wp_load_alloptions();
		$this->assertArrayNotHasKey(Release_Notices::VERSION_OPTION, $autoloaded);
		$this->assertArrayNotHasKey(Release_Notices::ANNOUNCEMENT_OPTION, $autoloaded);
	}

	public function test_prior_version_gets_announcement_without_changing_settings()
	{
		update_option(Release_Notices::VERSION_OPTION, '1.6.1', false);
		$settings = get_option(Settings::OPTION_KEY);
		(new Release_Notices())->detect_upgrade();
		$this->assertSame(Release_Notices::RELEASE, get_option(Release_Notices::ANNOUNCEMENT_OPTION));
		$this->assertSame($settings, get_option(Settings::OPTION_KEY));
	}

	public function test_patch_high_water_and_reactivation_do_not_reannounce()
	{
		$notices = new Release_Notices();
		$notices->detect_upgrade();
		$this->valid_submission();
		$this->dismiss($notices);
		update_option(Release_Notices::VERSION_OPTION, '1.7.1', false);
		Lifecycle::deactivate();
		Lifecycle::activate();
		$notices->detect_upgrade();
		$this->assertSame('1.7.1', get_option(Release_Notices::VERSION_OPTION));
		$this->assertSame(Release_Notices::RELEASE, get_user_meta($this->administrator_id, Release_Notices::dismissal_key(), true));
		set_current_screen('edit-cliapwo_request');
		$this->assertSame('', $this->rendered($notices));
	}

	public function test_reupgrade_preserves_previous_dismissal()
	{
		$notices = new Release_Notices();
		$notices->detect_upgrade();
		$this->valid_submission();
		$this->dismiss($notices);
		// Even older code replacing its own baseline cannot reset a dismissal.
		update_option(Release_Notices::VERSION_OPTION, '1.6.0', false);
		$notices->detect_upgrade();
		set_current_screen('edit-cliapwo_request');
		$this->assertSame('', $this->rendered($notices));
	}

	public function test_only_registered_settings_and_request_list_screens_render()
	{
		$notices = new Release_Notices();
		$admin = new Admin(new Settings(), new Onboarding(), new Sample_Content(), $notices);
		$admin->register_menu();
		$notices->detect_upgrade();
		$settings_hook = get_plugin_page_hookname(Settings::PAGE_SLUG, '');
		foreach (array($settings_hook, 'edit-cliapwo_request') as $screen) {
			set_current_screen($screen);
			$html = $this->rendered($notices);
			$this->assertStringContainsString('New in SignoffFlow 1.7.0: Request due dates', $html);
			$this->assertStringContainsString('cliapwo_dismiss_whats_new', $html);
			$this->assertStringNotContainsString('is-dismissible', $html);
			$this->assertStringContainsString('aria-labelledby=', $html);
		}
		foreach (array('dashboard', 'cliapwo_request', 'edit-cliapwo_client', 'front', 'dashboard-network') as $screen) {
			set_current_screen($screen);
			$this->assertSame('', $this->rendered($notices));
		}
	}

	public function test_detection_and_rendering_require_management_capability()
	{
		wp_set_current_user(self::factory()->user->create(array('role' => 'cliapwo_client')));
		$notices = new Release_Notices();
		$notices->detect_upgrade();
		$this->assertFalse(get_option(Release_Notices::VERSION_OPTION));
		update_option(Release_Notices::ANNOUNCEMENT_OPTION, Release_Notices::RELEASE, false);
		set_current_screen('edit-cliapwo_request');
		$this->assertSame('', $this->rendered($notices));
	}

	public function test_two_administrators_have_independent_site_scoped_dismissals()
	{
		$notices = new Release_Notices();
		$notices->detect_upgrade();
		$this->valid_submission('requests');
		$_POST['user_id'] = '999999';
		$this->assertSame(admin_url('edit.php?post_type=cliapwo_request'), $this->dismiss($notices));
		set_current_screen('edit-cliapwo_request');
		$this->assertSame('', $this->rendered($notices));
		$second = self::factory()->user->create(array('role' => 'administrator'));
		wp_set_current_user($second);
		update_user_meta($second, 'cliapwo_whats_new_dismissed_' . (get_current_blog_id() + 1), Release_Notices::RELEASE);
		$this->assertNotSame('', $this->rendered($notices));
		$this->assertSame('', get_user_meta($second, Release_Notices::dismissal_key(), true));
		$this->assertSame('', get_user_meta($this->administrator_id, 'cliapwo_onboarding_dismissed', true));
	}

	public function test_nonce_is_checked_before_permissions_and_bad_nonce_does_not_mutate()
	{
		$notices = new Release_Notices();
		$notices->detect_upgrade();
		wp_set_current_user(self::factory()->user->create(array('role' => 'subscriber')));
		foreach (array(null, 'invalid', array('invalid')) as $nonce) {
			$_POST[Release_Notices::NONCE_NAME] = $nonce;
			$this->assertSame('invalid_nonce', $this->dismiss($notices)->get_error_code());
			$this->assertSame('', get_user_meta(get_current_user_id(), Release_Notices::dismissal_key(), true));
		}
		$this->valid_submission();
		$this->assertSame('forbidden', $this->dismiss($notices)->get_error_code());
	}

	public function test_malformed_stale_announcements_and_unsafe_returns_never_dismiss()
	{
		$notices = new Release_Notices();
		$notices->detect_upgrade();
		foreach (array('1.6.0', '1.7.1', array('1.7.0'), '<script>1.7.0</script>', '') as $release) {
			$this->valid_submission();
			$_POST['cliapwo_whats_new_release'] = $release;
			$this->assertSame('invalid_announcement', $this->dismiss($notices)->get_error_code());
		}
		foreach (array('https://example.org', '//example.org', '../', array('settings'), '') as $return_to) {
			$this->valid_submission($return_to);
			$this->assertSame('invalid_announcement', $this->dismiss($notices)->get_error_code());
		}
		$this->valid_submission();
		delete_option(Release_Notices::ANNOUNCEMENT_OPTION);
		$this->assertSame('invalid_announcement', $this->dismiss($notices)->get_error_code());
		$this->assertSame('', get_user_meta($this->administrator_id, Release_Notices::dismissal_key(), true));
	}

	public function test_uninstall_preserves_default_state_and_only_cleans_current_site_when_opted_in()
	{
		$notices = new Release_Notices();
		$notices->detect_upgrade();
		$this->valid_submission();
		$this->dismiss($notices);
		$other_key = 'cliapwo_whats_new_dismissed_' . (get_current_blog_id() + 1);
		update_user_meta($this->administrator_id, $other_key, Release_Notices::RELEASE);
		if (! defined('WP_UNINSTALL_PLUGIN')) {
			define('WP_UNINSTALL_PLUGIN', 'client-approval-workflow.php');
		}
		include CLIAPWO_PLUGIN_DIR . 'uninstall.php';
		$this->assertSame(CLIAPWO_VERSION, get_option(Release_Notices::VERSION_OPTION));
		$this->assertSame(Release_Notices::RELEASE, get_user_meta($this->administrator_id, Release_Notices::dismissal_key(), true));
		$settings = Settings::get_default_settings();
		$settings['delete_data_on_uninstall'] = 1;
		update_option(Settings::OPTION_KEY, $settings);
		include CLIAPWO_PLUGIN_DIR . 'uninstall.php';
		$this->assertFalse(get_option(Release_Notices::VERSION_OPTION));
		$this->assertFalse(get_option(Release_Notices::ANNOUNCEMENT_OPTION));
		$this->assertSame('', get_user_meta($this->administrator_id, Release_Notices::dismissal_key(), true));
		$this->assertSame(Release_Notices::RELEASE, get_user_meta($this->administrator_id, $other_key, true));
	}
}
