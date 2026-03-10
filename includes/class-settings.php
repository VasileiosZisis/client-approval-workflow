<?php

/**
 * Settings registration and sanitization.
 *
 * @package ClientApprovalWorkflow
 */

namespace ClientApprovalWorkflow;

defined('ABSPATH') || exit;

/**
 * Registers and sanitizes plugin settings.
 */
class Settings
{
	/**
	 * Settings option key.
	 */
	public const OPTION_KEY = 'cliapwo_settings';

	/**
	 * Settings group key.
	 */
	public const OPTION_GROUP = 'cliapwo_settings_group';

	/**
	 * Settings page slug.
	 */
	public const PAGE_SLUG = 'cliapwo-client-approval-workflow';

	/**
	 * Register admin hooks.
	 *
	 * @return void
	 */
	public function register()
	{
		add_action('admin_init', array($this, 'register_settings'));
		add_filter('option_page_capability_' . self::OPTION_GROUP, array($this, 'get_option_page_capability'));
	}

	/**
	 * Return the required capability for saving settings.
	 *
	 * @return string
	 */
	public function get_option_page_capability()
	{
		return 'cliapwo_manage_portal';
	}

	/**
	 * Register the plugin settings and fields.
	 *
	 * @return void
	 */
	public function register_settings()
	{
		register_setting(
			self::OPTION_GROUP,
			self::OPTION_KEY,
			array(
				'type'              => 'array',
				'sanitize_callback' => array($this, 'sanitize_settings'),
				'default'           => self::get_default_settings(),
			)
		);

		add_settings_section(
			'cliapwo_general_section',
			__('General', 'client-approval-workflow'),
			array($this, 'render_general_section'),
			self::PAGE_SLUG
		);

		add_settings_field(
			'cliapwo_portal_page_id',
			__('Portal base page', 'client-approval-workflow'),
			array($this, 'render_portal_page_field'),
			self::PAGE_SLUG,
			'cliapwo_general_section'
		);

		add_settings_section(
			'cliapwo_branding_section',
			__('Branding', 'client-approval-workflow'),
			array($this, 'render_branding_section'),
			self::PAGE_SLUG
		);

		add_settings_field(
			'cliapwo_branding_logo_id',
			__('Logo media ID', 'client-approval-workflow'),
			array($this, 'render_logo_id_field'),
			self::PAGE_SLUG,
			'cliapwo_branding_section'
		);

		add_settings_field(
			'cliapwo_branding_logo_url',
			__('Logo URL', 'client-approval-workflow'),
			array($this, 'render_logo_url_field'),
			self::PAGE_SLUG,
			'cliapwo_branding_section'
		);

		add_settings_field(
			'cliapwo_branding_primary_color',
			__('Primary color', 'client-approval-workflow'),
			array($this, 'render_primary_color_field'),
			self::PAGE_SLUG,
			'cliapwo_branding_section'
		);

		add_settings_section(
			'cliapwo_notifications_section',
			__('Notifications', 'client-approval-workflow'),
			array($this, 'render_notifications_section'),
			self::PAGE_SLUG
		);

		add_settings_field(
			'cliapwo_notify_updates',
			__('Update emails', 'client-approval-workflow'),
			array($this, 'render_notify_updates_field'),
			self::PAGE_SLUG,
			'cliapwo_notifications_section'
		);

		add_settings_field(
			'cliapwo_notify_files',
			__('File emails', 'client-approval-workflow'),
			array($this, 'render_notify_files_field'),
			self::PAGE_SLUG,
			'cliapwo_notifications_section'
		);

		add_settings_field(
			'cliapwo_notify_requests',
			__('Request emails', 'client-approval-workflow'),
			array($this, 'render_notify_requests_field'),
			self::PAGE_SLUG,
			'cliapwo_notifications_section'
		);
	}

	/**
	 * Return the plugin settings with defaults merged in.
	 *
	 * @return array<string, mixed>
	 */
	public static function get_settings()
	{
		$settings = get_option(self::OPTION_KEY, array());

		if (! is_array($settings)) {
			$settings = array();
		}

		return wp_parse_args($settings, self::get_default_settings());
	}

	/**
	 * Return the default settings.
	 *
	 * @return array<string, mixed>
	 */
	public static function get_default_settings()
	{
		return array(
			'portal_page_id'    => 0,
			'branding_logo_id'  => 0,
			'branding_logo_url' => '',
			'primary_color'     => '#1d4ed8',
			'notify_updates'    => 1,
			'notify_files'      => 1,
			'notify_requests'   => 1,
		);
	}

	/**
	 * Sanitize settings before saving them.
	 *
	 * @param mixed $input Raw submitted settings.
	 * @return array<string, mixed>
	 */
	public function sanitize_settings($input)
	{
		if (! current_user_can('cliapwo_manage_portal')) {
			return self::get_settings();
		}

		$defaults = self::get_default_settings();
		$input    = is_array($input) ? $input : array();

		$portal_page_id = isset($input['portal_page_id']) ? absint($input['portal_page_id']) : 0;

		if ($portal_page_id > 0) {
			$portal_page = get_post($portal_page_id);

			if (! $portal_page instanceof \WP_Post || 'page' !== $portal_page->post_type) {
				add_settings_error(
					self::OPTION_KEY,
					'cliapwo_portal_page_id',
					__('Choose a valid WordPress page for the portal base page.', 'client-approval-workflow'),
					'error'
				);
				$portal_page_id = (int) $defaults['portal_page_id'];
			}
		}

		$branding_logo_id = isset($input['branding_logo_id']) ? absint($input['branding_logo_id']) : 0;

		if ($branding_logo_id > 0) {
			$attachment = get_post($branding_logo_id);

			if (! $attachment instanceof \WP_Post || 'attachment' !== $attachment->post_type) {
				add_settings_error(
					self::OPTION_KEY,
					'cliapwo_branding_logo_id',
					__('Enter a valid media attachment ID for the portal logo.', 'client-approval-workflow'),
					'error'
				);
				$branding_logo_id = (int) $defaults['branding_logo_id'];
			}
		}

		$branding_logo_url = $this->sanitize_logo_url($input, $defaults);
		$primary_color     = isset($input['primary_color']) ? sanitize_hex_color($input['primary_color']) : false;

		if (false === $primary_color) {
			add_settings_error(
				self::OPTION_KEY,
				'cliapwo_primary_color',
				__('Enter a valid hex color for the primary color setting.', 'client-approval-workflow'),
				'error'
			);
			$primary_color = $defaults['primary_color'];
		}

		return array(
			'portal_page_id'    => $portal_page_id,
			'branding_logo_id'  => $branding_logo_id,
			'branding_logo_url' => $branding_logo_url,
			'primary_color'     => $primary_color,
			'notify_updates'    => $this->sanitize_toggle($input, 'notify_updates'),
			'notify_files'      => $this->sanitize_toggle($input, 'notify_files'),
			'notify_requests'   => $this->sanitize_toggle($input, 'notify_requests'),
		);
	}

	/**
	 * Sanitize and validate the branding logo URL setting.
	 *
	 * @param array<string, mixed> $input    Raw submitted settings.
	 * @param array<string, mixed> $defaults Default settings.
	 * @return string
	 */
	private function sanitize_logo_url(array $input, array $defaults)
	{
		if (! isset($input['branding_logo_url'])) {
			return '';
		}

		$raw_logo_url = trim((string) $input['branding_logo_url']);

		if ('' === $raw_logo_url) {
			return '';
		}

		$branding_logo_url = esc_url_raw($raw_logo_url);

		if ('' === $branding_logo_url) {
			add_settings_error(
				self::OPTION_KEY,
				'cliapwo_branding_logo_url',
				__('Enter a valid URL for the portal logo.', 'client-approval-workflow'),
				'error'
			);

			return (string) $defaults['branding_logo_url'];
		}

		return $branding_logo_url;
	}

	/**
	 * Sanitize checkbox-like settings to a strict 0/1 value.
	 *
	 * @param array<string, mixed> $input Submitted settings.
	 * @param string               $key   Setting key to sanitize.
	 * @return int
	 */
	private function sanitize_toggle(array $input, $key)
	{
		if (! isset($input[$key])) {
			return 0;
		}

		return '1' === (string) $input[$key] ? 1 : 0;
	}

	/**
	 * Render the general section description.
	 *
	 * @return void
	 */
	public function render_general_section()
	{
		echo '<p>' . esc_html__('Choose the WordPress page that hosts the client portal shortcode.', 'client-approval-workflow') . '</p>';
	}

	/**
	 * Render the branding section description.
	 *
	 * @return void
	 */
	public function render_branding_section()
	{
		echo '<p>' . esc_html__('Set the logo and primary color used across the SignoffFlow portal experience.', 'client-approval-workflow') . '</p>';
	}

	/**
	 * Render the notifications section description.
	 *
	 * @return void
	 */
	public function render_notifications_section()
	{
		echo '<p>' . esc_html__('Enable or disable the client emails sent for new requests, updates, and uploaded files.', 'client-approval-workflow') . '</p>';
	}

	/**
	 * Render the portal page field.
	 *
	 * @return void
	 */
	public function render_portal_page_field()
	{
		$settings = self::get_settings();
		$name     = esc_attr(self::OPTION_KEY . '[portal_page_id]');
		$none     = esc_html__('Select a page', 'client-approval-workflow');
		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- wp_dropdown_pages() outputs trusted core-generated select markup.
		echo wp_dropdown_pages(
			array(
				'name'              => $name,
				'selected'          => (int) $settings['portal_page_id'],
				'show_option_none'  => $none,
				'option_none_value' => '0',
				'echo'              => 0,
			)
		);
	}

	/**
	 * Render the logo attachment ID field.
	 *
	 * @return void
	 */
	public function render_logo_id_field()
	{
		$settings = self::get_settings();
?>
		<input
			type="number"
			class="small-text"
			min="0"
			name="<?php echo esc_attr(self::OPTION_KEY); ?>[branding_logo_id]"
			value="<?php echo esc_attr((string) $settings['branding_logo_id']); ?>" />
		<p class="description"><?php esc_html_e('Optional attachment ID from the Media Library.', 'client-approval-workflow'); ?></p>
	<?php
	}

	/**
	 * Render the logo URL field.
	 *
	 * @return void
	 */
	public function render_logo_url_field()
	{
		$settings = self::get_settings();
	?>
		<input
			type="url"
			class="regular-text"
			name="<?php echo esc_attr(self::OPTION_KEY); ?>[branding_logo_url]"
			value="<?php echo esc_attr((string) $settings['branding_logo_url']); ?>"
			placeholder="https://example.com/logo.png" />
		<p class="description"><?php esc_html_e('Optional direct URL fallback for the portal logo.', 'client-approval-workflow'); ?></p>
	<?php
	}

	/**
	 * Render the primary color field.
	 *
	 * @return void
	 */
	public function render_primary_color_field()
	{
		$settings = self::get_settings();
	?>
		<input
			type="color"
			name="<?php echo esc_attr(self::OPTION_KEY); ?>[primary_color]"
			value="<?php echo esc_attr((string) $settings['primary_color']); ?>" />
		<p class="description"><?php esc_html_e('Used for portal accents and notification branding.', 'client-approval-workflow'); ?></p>
	<?php
	}

	/**
	 * Render the update email toggle.
	 *
	 * @return void
	 */
	public function render_notify_updates_field()
	{
		$settings = self::get_settings();
	?>
		<label for="cliapwo_notify_updates">
			<input
				id="cliapwo_notify_updates"
				type="checkbox"
				name="<?php echo esc_attr(self::OPTION_KEY); ?>[notify_updates]"
				value="1"
				<?php checked(! empty($settings['notify_updates'])); ?> />
			<?php esc_html_e('Send email notifications when a new update is posted.', 'client-approval-workflow'); ?>
		</label>
	<?php
	}

	/**
	 * Render the file email toggle.
	 *
	 * @return void
	 */
	public function render_notify_files_field()
	{
		$settings = self::get_settings();
	?>
		<label for="cliapwo_notify_files">
			<input
				id="cliapwo_notify_files"
				type="checkbox"
				name="<?php echo esc_attr(self::OPTION_KEY); ?>[notify_files]"
				value="1"
				<?php checked(! empty($settings['notify_files'])); ?> />
			<?php esc_html_e('Send email notifications when a new file is uploaded.', 'client-approval-workflow'); ?>
		</label>
	<?php
	}

	/**
	 * Render the request email toggle.
	 *
	 * @return void
	 */
	public function render_notify_requests_field()
	{
		$settings = self::get_settings();
	?>
		<label for="cliapwo_notify_requests">
			<input
				id="cliapwo_notify_requests"
				type="checkbox"
				name="<?php echo esc_attr(self::OPTION_KEY); ?>[notify_requests]"
				value="1"
				<?php checked(! empty($settings['notify_requests'])); ?> />
			<?php esc_html_e('Send email notifications when a new request is created.', 'client-approval-workflow'); ?>
		</label>
<?php
	}
}
