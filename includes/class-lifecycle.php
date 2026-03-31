<?php

/**
 * Activation and deactivation routines.
 *
 * @package VzisisClientApprovalWorkflow
 */

namespace Vzisis\ClientApprovalWorkflow;

defined('ABSPATH') || exit;

/**
 * Handles plugin lifecycle hooks.
 */
class Lifecycle
{
	/**
	 * Activate the plugin.
	 *
	 * @return void
	 */
	public static function activate()
	{
		self::ensure_roles();

		if (false === get_option(Settings::OPTION_KEY, false)) {
			add_option(Settings::OPTION_KEY, Settings::get_default_settings());
		}

		set_transient('cliapwo_plugin_activated', 1, MINUTE_IN_SECONDS);
	}

	/**
	 * Deactivate the plugin.
	 *
	 * Data, roles, and capabilities are intentionally retained so a later
	 * reactivation does not silently strip portal access configuration.
	 *
	 * @return void
	 */
	public static function deactivate()
	{
		// Intentionally left blank.
	}

	/**
	 * Ensure plugin roles and capabilities exist.
	 *
	 * @return void
	 */
	public static function ensure_roles()
	{
		self::add_capabilities();
	}

	/**
	 * Add plugin capabilities and the default client role.
	 *
	 * @return void
	 */
	private static function add_capabilities()
	{
		$administrator = get_role('administrator');

		if ($administrator instanceof \WP_Role) {
			$administrator->add_cap('cliapwo_manage_portal');
			$administrator->add_cap('cliapwo_view_portal');
		}

		$client_role = get_role('cliapwo_client');

		if (! $client_role instanceof \WP_Role) {
			$client_role = add_role(
				'cliapwo_client',
				__('Client', 'signoffflow-client-approval-workflow'),
				array(
					'read'                => true,
					'cliapwo_view_portal' => true,
				)
			);
		}

		if ($client_role instanceof \WP_Role) {
			self::sync_client_role_capabilities($client_role);
			$client_role->add_cap('cliapwo_view_portal');
		}
	}

	/**
	 * Keep the client role limited to the minimum portal capabilities.
	 *
	 * @param \WP_Role $client_role Client role object.
	 * @return void
	 */
	private static function sync_client_role_capabilities(\WP_Role $client_role)
	{
		$allowed_caps = array(
			'read',
			'cliapwo_view_portal',
		);

		foreach (array_keys((array) $client_role->capabilities) as $capability) {
			if (in_array($capability, $allowed_caps, true)) {
				continue;
			}

			$client_role->remove_cap($capability);
		}

		$client_role->add_cap('read');
		$client_role->add_cap('cliapwo_view_portal');
	}
}
