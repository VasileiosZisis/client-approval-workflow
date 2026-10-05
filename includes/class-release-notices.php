<?php
/**
 * Internal, site-scoped feature release announcements.
 *
 * @package VzisisClientApprovalWorkflow
 */

namespace Vzisis\ClientApprovalWorkflow;

defined('ABSPATH') || exit;

/**
 * Announces intentional feature releases without resetting per-user dismissal.
 *
 * @internal
 */
class Release_Notices
{
	const VERSION_OPTION = 'cliapwo_installed_version';
	const ANNOUNCEMENT_OPTION = 'cliapwo_whats_new_release';
	const RELEASE = '1.7.0';
	const NONCE_NAME = 'cliapwo_whats_new_nonce';
	const NONCE_ACTION = 'cliapwo_dismiss_whats_new_1.7.0';

	/**
	 * Actual settings screen IDs returned by menu registration.
	 *
	 * @var array<int, string>
	 */
	private $settings_screens = array();

	/**
	 * Register admin-only upgrade detection and rendering.
	 *
	 * @return void
	 */
	public function register()
	{
		add_action('admin_init', array($this, 'detect_upgrade'));
		add_action('admin_notices', array($this, 'render'));
		add_action('admin_enqueue_scripts', array($this, 'enqueue_assets'));
		add_action('admin_post_cliapwo_dismiss_whats_new', array($this, 'handle_dismiss'));
	}

	/**
	 * Suppress announcements on genuinely new activations only.
	 *
	 * @return void
	 */
	public static function mark_fresh_install()
	{
		add_option(self::VERSION_OPTION, CLIAPWO_VERSION, '', false);
	}

	/**
	 * Record an actual settings screen, rather than guessing a translated menu ID.
	 *
	 * @param string $screen_id Registered hook suffix.
	 * @return void
	 */
	public function add_settings_screen($screen_id)
	{
		if (is_string($screen_id) && '' !== $screen_id) {
			$this->settings_screens[] = $screen_id;
		}
	}

	/**
	 * Keep a high-water version baseline; patch updates never create announcements.
	 *
	 * @return void
	 */
	public function detect_upgrade()
	{
		if (is_network_admin() || ! current_user_can('cliapwo_manage_portal') || false === get_option(Settings::OPTION_KEY, false)) {
			return;
		}

		$installed = get_option(self::VERSION_OPTION, false);
		if (false !== $installed && (! is_string($installed) || ! preg_match('/^\d+\.\d+\.\d+$/D', $installed))) {
			return;
		}

		if (version_compare(CLIAPWO_VERSION, self::RELEASE, '>=') && (false === $installed || version_compare($installed, self::RELEASE, '<'))) {
			// Persist the announcement first, so a failed write remains retryable.
			update_option(self::ANNOUNCEMENT_OPTION, self::RELEASE, false);
			if (self::RELEASE !== get_option(self::ANNOUNCEMENT_OPTION)) {
				return;
			}
		}

		if (false === $installed || version_compare(CLIAPWO_VERSION, $installed, '>')) {
			update_option(self::VERSION_OPTION, CLIAPWO_VERSION, false);
		}
	}

	/**
	 * Use a site-specific user meta key even on multisite installations.
	 *
	 * @return string
	 */
	public static function dismissal_key()
	{
		return 'cliapwo_whats_new_dismissed_' . get_current_blog_id();
	}

	/**
	 * Check permission, exact screen, active release, and personal dismissal.
	 *
	 * @return bool
	 */
	private function is_visible()
	{
		$screen = get_current_screen();
		return ! is_network_admin()
			&& current_user_can('cliapwo_manage_portal')
			&& $screen instanceof \WP_Screen
			&& ('edit-cliapwo_request' === $screen->id || in_array($screen->id, $this->settings_screens, true))
			&& version_compare(CLIAPWO_VERSION, self::RELEASE, '>=')
			&& self::RELEASE === get_option(self::ANNOUNCEMENT_OPTION)
			&& self::RELEASE !== get_user_meta(get_current_user_id(), self::dismissal_key(), true);
	}

	/**
	 * Load the small notice stylesheet only where a notice is displayed.
	 *
	 * @return void
	 */
	public function enqueue_assets()
	{
		if ($this->is_visible()) {
			wp_enqueue_style('cliapwo-release-notices', CLIAPWO_PLUGIN_URL . 'assets/css/cliapwo-release-notices.css', array(), CLIAPWO_VERSION);
		}
	}

	/**
	 * Render a persistent notice with an explicit, keyboard-accessible POST dismissal.
	 *
	 * @return void
	 */
	public function render()
	{
		if (! $this->is_visible()) {
			return;
		}
		$screen = get_current_screen();
		$return_to = 'edit-cliapwo_request' === $screen->id ? 'requests' : 'settings';
		?>
		<div class="notice notice-info cliapwo-release-notice" role="region" aria-labelledby="cliapwo-whats-new-title">
			<p id="cliapwo-whats-new-title"><strong><?php esc_html_e('New in SignoffFlow 1.7.0: Request due dates', 'signoffflow-client-approval-workflow'); ?></strong></p>
			<p><?php esc_html_e('Add optional deadlines to requests, see overdue and due-soon indicators, and review deadline changes in Activity History. Existing requests remain undated until you set a deadline.', 'signoffflow-client-approval-workflow'); ?></p>
			<div class="cliapwo-release-notice__actions">
				<a class="button button-primary" href="<?php echo esc_url(admin_url('edit.php?post_type=cliapwo_request')); ?>"><?php esc_html_e('Manage requests', 'signoffflow-client-approval-workflow'); ?></a>
				<form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
					<input type="hidden" name="action" value="cliapwo_dismiss_whats_new" />
					<input type="hidden" name="cliapwo_whats_new_release" value="<?php echo esc_attr(self::RELEASE); ?>" />
					<input type="hidden" name="cliapwo_whats_new_return" value="<?php echo esc_attr($return_to); ?>" />
					<?php wp_nonce_field(self::NONCE_ACTION, self::NONCE_NAME, false); ?>
					<button type="submit" class="button"><?php esc_html_e('Dismiss', 'signoffflow-client-approval-workflow'); ?></button>
				</form>
			</div>
		</div>
		<?php
	}

	/**
	 * Validate and apply dismissal. Kept separate from redirect/exit for testing.
	 *
	 * @return string|\WP_Error Allowlisted redirect URL or validation error.
	 */
	private function dismiss()
	{
		$nonce = isset($_POST[self::NONCE_NAME]) && is_string($_POST[self::NONCE_NAME]) ? sanitize_text_field(wp_unslash($_POST[self::NONCE_NAME])) : '';
		if (! wp_verify_nonce($nonce, self::NONCE_ACTION)) {
			return new \WP_Error('invalid_nonce', __('The release notice could not be dismissed. Reload the page and try again.', 'signoffflow-client-approval-workflow'));
		}
		if (is_network_admin() || ! current_user_can('cliapwo_manage_portal')) {
			return new \WP_Error('forbidden', __('You are not allowed to manage SignoffFlow settings.', 'signoffflow-client-approval-workflow'));
		}
		$release = isset($_POST['cliapwo_whats_new_release']) && is_string($_POST['cliapwo_whats_new_release']) ? wp_unslash($_POST['cliapwo_whats_new_release']) : '';
		$return_to = isset($_POST['cliapwo_whats_new_return']) && is_string($_POST['cliapwo_whats_new_return']) ? wp_unslash($_POST['cliapwo_whats_new_return']) : '';
		if (self::RELEASE !== $release || self::RELEASE !== get_option(self::ANNOUNCEMENT_OPTION) || version_compare(CLIAPWO_VERSION, self::RELEASE, '<') || ! in_array($return_to, array('settings', 'requests'), true)) {
			return new \WP_Error('invalid_announcement', __('The release notice could not be dismissed. Reload the page and try again.', 'signoffflow-client-approval-workflow'));
		}
		update_user_meta(get_current_user_id(), self::dismissal_key(), self::RELEASE);
		if (self::RELEASE !== get_user_meta(get_current_user_id(), self::dismissal_key(), true)) {
			return new \WP_Error('dismissal_failed', __('The release notice could not be dismissed. Reload the page and try again.', 'signoffflow-client-approval-workflow'));
		}
		return 'requests' === $return_to ? admin_url('edit.php?post_type=cliapwo_request') : admin_url('admin.php?page=' . Settings::PAGE_SLUG);
	}

	/**
	 * Handle only the signed admin-post dismissal, without accepting user IDs or URLs.
	 *
	 * @return void
	 */
	public function handle_dismiss()
	{
		$result = $this->dismiss();
		if (is_wp_error($result)) {
			wp_die(esc_html($result->get_error_message()), esc_html__('Dismiss release notice', 'signoffflow-client-approval-workflow'), array('response' => 403));
		}
		wp_safe_redirect($result);
		exit;
	}
}
