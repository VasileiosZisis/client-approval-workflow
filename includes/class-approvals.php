<?php

/**
 * Approvals extension contract and placeholder UI.
 *
 * @package ClientApprovalWorkflow
 */

namespace ClientApprovalWorkflow;

defined('ABSPATH') || exit;

/**
 * Defines the approvals integration surface for the Pro add-on.
 */
class Approvals
{
	/**
	 * Admin page slug.
	 */
	public const PAGE_SLUG = 'cliapwo-approvals';

	/**
	 * Approval post type slug reserved for the Pro add-on.
	 */
	public const POST_TYPE = 'cliapwo_approval';

	/**
	 * Linked client meta key.
	 */
	public const CLIENT_META_KEY = 'cliapwo_client_id';

	/**
	 * Related object ID meta key.
	 */
	public const OBJECT_ID_META_KEY = 'cliapwo_source_object_id';

	/**
	 * Related object type meta key.
	 */
	public const OBJECT_TYPE_META_KEY = 'cliapwo_source_object_type';

	/**
	 * Approval status meta key.
	 */
	public const STATUS_META_KEY = 'cliapwo_approval_status';

	/**
	 * Requested by user meta key.
	 */
	public const REQUESTED_BY_META_KEY = 'cliapwo_requested_by';

	/**
	 * Decision user meta key.
	 */
	public const DECIDED_BY_META_KEY = 'cliapwo_decided_by';

	/**
	 * Approval note meta key.
	 */
	public const NOTE_META_KEY = 'cliapwo_approval_note';

	/**
	 * Requested timestamp meta key.
	 */
	public const REQUESTED_AT_META_KEY = 'cliapwo_requested_at';

	/**
	 * Decision timestamp meta key.
	 */
	public const DECIDED_AT_META_KEY = 'cliapwo_decided_at';

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public function register()
	{
		add_action('admin_menu', array($this, 'register_menu'));
	}

	/**
	 * Register the Approvals submenu.
	 *
	 * @return void
	 */
	public function register_menu()
	{
		if (! cliapwo_is_pro_active()) {
			return;
		}

		add_submenu_page(
			Settings::PAGE_SLUG,
			__('Approvals', 'client-approval-workflow'),
			__('Approvals', 'client-approval-workflow'),
			'cliapwo_manage_portal',
			self::PAGE_SLUG,
			array($this, 'render_page')
		);
	}

	/**
	 * Render the approvals admin page.
	 *
	 * @return void
	 */
	public function render_page()
	{
		if (! current_user_can('cliapwo_manage_portal')) {
			wp_die(
				esc_html__('You are not allowed to manage approvals.', 'client-approval-workflow'),
				esc_html__('Forbidden', 'client-approval-workflow'),
				array(
					'response' => 403,
				)
			);
		}

		if (cliapwo_is_pro_active() && has_action('cliapwo_render_approvals_page')) {
			do_action('cliapwo_render_approvals_page', self::get_schema());
			return;
		}
		?>
		<div class="wrap">
			<h1><?php esc_html_e('Approvals', 'client-approval-workflow'); ?></h1>
			<p><?php esc_html_e('Approvals are reserved for the SignoffFlow Pro add-on. The free plugin keeps the menu location, schema, and hooks ready so a Pro module can attach cleanly.', 'client-approval-workflow'); ?></p>

			<h2><?php esc_html_e('Extension contract', 'client-approval-workflow'); ?></h2>
			<p><?php esc_html_e('The reserved post type, statuses, object types, and meta keys below define the approvals data model used by the extension hooks.', 'client-approval-workflow'); ?></p>

			<pre><?php echo esc_html(wp_json_encode(self::get_schema(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)); ?></pre>
		</div>
		<?php
	}

	/**
	 * Get the approvals schema reserved for extension plugins.
	 *
	 * @return array<string, mixed>
	 */
	public static function get_schema()
	{
		$schema = array(
			'post_type'    => self::POST_TYPE,
			'statuses'     => array(
				'pending',
				'approved',
				'rejected',
				'cancelled',
			),
			'object_types' => array(
				'update',
				'file',
				'page',
				'custom',
			),
			'meta_keys'    => array(
				'client_id'          => self::CLIENT_META_KEY,
				'object_id'          => self::OBJECT_ID_META_KEY,
				'object_type'        => self::OBJECT_TYPE_META_KEY,
				'status'             => self::STATUS_META_KEY,
				'requested_by'       => self::REQUESTED_BY_META_KEY,
				'decided_by'         => self::DECIDED_BY_META_KEY,
				'note'               => self::NOTE_META_KEY,
				'requested_at_gmt'   => self::REQUESTED_AT_META_KEY,
				'decision_at_gmt'    => self::DECIDED_AT_META_KEY,
			),
		);

		/**
		 * Filter the approvals schema exposed to extension plugins.
		 *
		 * @param array<string, mixed> $schema Approvals schema.
		 */
		return apply_filters('cliapwo_approvals_schema', $schema);
	}
}
