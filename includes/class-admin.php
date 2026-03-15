<?php

/**
 * Admin menu and settings page rendering.
 *
 * @package ClientApprovalWorkflow
 */

namespace ClientApprovalWorkflow;

defined('ABSPATH') || exit;

/**
 * Registers the plugin admin pages.
 */
class Admin
{
	/**
	 * Settings service.
	 *
	 * @var Settings
	 */
	private $settings;

	/**
	 * Constructor.
	 *
	 * @param Settings $settings Settings service.
	 */
	public function __construct(Settings $settings)
	{
		$this->settings = $settings;
	}

	/**
	 * Register admin hooks.
	 *
	 * @return void
	 */
	public function register()
	{
		add_action('admin_menu', array($this, 'register_menu'));
		add_action('admin_post_cliapwo_create_portal_page', array($this, 'handle_create_portal_page'));
	}

	/**
	 * Register the top-level menu and settings submenu.
	 *
	 * @return void
	 */
	public function register_menu()
	{
		add_menu_page(
			__('SignoffFlow', 'client-approval-workflow'),
			__('SignoffFlow', 'client-approval-workflow'),
			'cliapwo_manage_portal',
			Settings::PAGE_SLUG,
			array($this, 'render_settings_page'),
			'dashicons-yes-alt',
			56
		);

		add_submenu_page(
			Settings::PAGE_SLUG,
			__('Settings', 'client-approval-workflow'),
			__('Settings', 'client-approval-workflow'),
			'cliapwo_manage_portal',
			Settings::PAGE_SLUG,
			array($this, 'render_settings_page')
		);
	}

	/**
	 * Render the settings page.
	 *
	 * @return void
	 */
	public function render_settings_page()
	{
		if (! current_user_can('cliapwo_manage_portal')) {
			wp_die(
				esc_html__('You are not allowed to manage SignoffFlow settings.', 'client-approval-workflow'),
				esc_html__('Forbidden', 'client-approval-workflow'),
				array(
					'response' => 403,
				)
			);
		}
?>
		<div class="wrap">
			<h1><?php esc_html_e('SignoffFlow Settings', 'client-approval-workflow'); ?></h1>

			<?php settings_errors(); ?>
			<?php $this->render_onboarding_panel(); ?>

			<form action="options.php" method="post">
				<?php
				settings_fields(Settings::OPTION_GROUP);
				do_settings_sections(Settings::PAGE_SLUG);
				submit_button(__('Save Settings', 'client-approval-workflow'));
				?>
			</form>
		</div>
<?php
	}

	/**
	 * Create a sample portal page and store it in plugin settings.
	 *
	 * @return void
	 */
	public function handle_create_portal_page()
	{
		if (! current_user_can('cliapwo_manage_portal')) {
			wp_die(
				esc_html__('You are not allowed to create the portal page.', 'client-approval-workflow'),
				esc_html__('Forbidden', 'client-approval-workflow'),
				array(
					'response' => 403,
				)
			);
		}

		check_admin_referer('cliapwo_create_portal_page', 'cliapwo_create_portal_page_nonce');

		$settings       = Settings::get_settings();
		$portal_page_id = isset($settings['portal_page_id']) ? absint($settings['portal_page_id']) : 0;

		if ($portal_page_id > 0) {
			$portal_page = get_post($portal_page_id);

			if ($portal_page instanceof \WP_Post && 'page' === $portal_page->post_type) {
				$this->redirect_to_settings(
					array(
						'cliapwo_onboarding'        => '1',
						'cliapwo_onboarding_status' => 'existing',
					)
				);
			}
		}

		$portal_page_id = wp_insert_post(
			array(
				'post_title'   => __('Client Portal', 'client-approval-workflow'),
				'post_type'    => 'page',
				'post_status'  => 'publish',
				'post_content' => '[cliapwo_portal]',
			),
			true
		);

		if (is_wp_error($portal_page_id) || ! is_numeric($portal_page_id)) {
			$this->redirect_to_settings(
				array(
					'cliapwo_onboarding'        => '1',
					'cliapwo_onboarding_status' => 'error',
				)
			);
		}

		$settings['portal_page_id'] = absint($portal_page_id);
		update_option(Settings::OPTION_KEY, $settings);

		$this->redirect_to_settings(
			array(
				'cliapwo_onboarding'        => '1',
				'cliapwo_onboarding_status' => 'created',
			)
		);
	}

	/**
	 * Render a lightweight onboarding panel on the settings page.
	 *
	 * @return void
	 */
	private function render_onboarding_panel()
	{
		$settings               = Settings::get_settings();
		$portal_page_id         = isset($settings['portal_page_id']) ? absint($settings['portal_page_id']) : 0;
		$activation_onboarding  = (bool) get_transient('cliapwo_plugin_activated');
		$show_onboarding        = $activation_onboarding || '1' === (string) filter_input(INPUT_GET, 'cliapwo_onboarding', FILTER_SANITIZE_NUMBER_INT);
		$status                 = sanitize_key((string) filter_input(INPUT_GET, 'cliapwo_onboarding_status', FILTER_SANITIZE_FULL_SPECIAL_CHARS));
		$portal_page            = $portal_page_id > 0 ? get_post($portal_page_id) : null;
		$has_valid_portal       = $portal_page instanceof \WP_Post && 'page' === $portal_page->post_type;

		if (! $show_onboarding && $has_valid_portal) {
			return;
		}

		if ($activation_onboarding) {
			delete_transient('cliapwo_plugin_activated');
		}
		?>
		<div class="notice notice-info" style="padding:16px 20px; margin:16px 0;">
			<h2 style="margin-top:0;"><?php esc_html_e('Quick setup', 'client-approval-workflow'); ?></h2>
			<p><?php esc_html_e('Create a sample portal page, then start adding clients, updates, files, and requests.', 'client-approval-workflow'); ?></p>

			<?php if ('created' === $status) : ?>
				<p><strong><?php esc_html_e('Portal page created and saved to SignoffFlow settings.', 'client-approval-workflow'); ?></strong></p>
			<?php elseif ('existing' === $status) : ?>
				<p><strong><?php esc_html_e('A portal page is already configured in SignoffFlow settings.', 'client-approval-workflow'); ?></strong></p>
			<?php elseif ('error' === $status) : ?>
				<p><strong><?php esc_html_e('The sample portal page could not be created automatically. You can still create a page manually and add the [cliapwo_portal] shortcode.', 'client-approval-workflow'); ?></strong></p>
			<?php endif; ?>

			<ol style="margin:0 0 12px 18px;">
				<li><?php esc_html_e('Create or confirm your portal page.', 'client-approval-workflow'); ?></li>
				<li><?php esc_html_e('Create a client account and assign at least one WordPress portal user.', 'client-approval-workflow'); ?></li>
				<li><?php esc_html_e('Add updates, files, or requests and review the portal as one of the assigned portal users.', 'client-approval-workflow'); ?></li>
			</ol>

			<?php if ($has_valid_portal) : ?>
				<p>
					<a class="button button-primary" href="<?php echo esc_url(get_permalink($portal_page_id)); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e('View portal page', 'client-approval-workflow'); ?></a>
					<a class="button" href="<?php echo esc_url(get_edit_post_link($portal_page_id, '')); ?>"><?php esc_html_e('Edit portal page', 'client-approval-workflow'); ?></a>
					<a class="button" href="<?php echo esc_url(admin_url('post-new.php?post_type=cliapwo_client')); ?>"><?php esc_html_e('Create first client', 'client-approval-workflow'); ?></a>
				</p>
			<?php else : ?>
				<form action="<?php echo esc_url(admin_url('admin-post.php')); ?>" method="post" style="margin:0 0 12px;">
					<input type="hidden" name="action" value="cliapwo_create_portal_page" />
					<?php wp_nonce_field('cliapwo_create_portal_page', 'cliapwo_create_portal_page_nonce'); ?>
					<?php submit_button(__('Create sample portal page', 'client-approval-workflow'), 'primary', 'submit', false); ?>
				</form>
				<p class="description"><?php esc_html_e('The generated page will be published with the [cliapwo_portal] shortcode and saved as the portal base page.', 'client-approval-workflow'); ?></p>
			<?php endif; ?>
		</div>
		<?php
	}

	/**
	 * Redirect back to the settings page with onboarding state.
	 *
	 * @param array<string, string> $query_args Query arguments to append.
	 * @return void
	 */
	private function redirect_to_settings(array $query_args = array())
	{
		$redirect_url = add_query_arg(
			array_merge(
				array(
					'page' => Settings::PAGE_SLUG,
				),
				$query_args
			),
			admin_url('admin.php')
		);

		wp_safe_redirect($redirect_url);
		exit;
	}
}
