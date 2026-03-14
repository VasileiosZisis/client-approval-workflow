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
	}

	/**
	 * Register the top-level menu and settings submenu.
	 *
	 * @return void
	 */
	public function register_menu()
	{
		add_menu_page(
			__('SignoffFlow', 'signoffflow'),
			__('SignoffFlow', 'signoffflow'),
			'cliapwo_manage_portal',
			Settings::PAGE_SLUG,
			array($this, 'render_settings_page'),
			'dashicons-yes-alt',
			56
		);

		add_submenu_page(
			Settings::PAGE_SLUG,
			__('Settings', 'signoffflow'),
			__('Settings', 'signoffflow'),
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
				esc_html__('You are not allowed to manage SignoffFlow settings.', 'signoffflow'),
				esc_html__('Forbidden', 'signoffflow'),
				array(
					'response' => 403,
				)
			);
		}
?>
		<div class="wrap">
			<h1><?php esc_html_e('SignoffFlow Settings', 'signoffflow'); ?></h1>

			<?php settings_errors(); ?>

			<form action="options.php" method="post">
				<?php
				settings_fields(Settings::OPTION_GROUP);
				do_settings_sections(Settings::PAGE_SLUG);
				submit_button(__('Save Settings', 'signoffflow'));
				?>
			</form>
		</div>
<?php
	}
}
