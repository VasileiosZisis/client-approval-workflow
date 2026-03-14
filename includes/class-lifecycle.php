<?php

/**
 * Activation and deactivation routines.
 *
 * @package ClientApprovalWorkflow
 */

namespace ClientApprovalWorkflow;

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
		self::add_capabilities();

		if (false === get_option(Settings::OPTION_KEY, false)) {
			add_option(Settings::OPTION_KEY, Settings::get_default_settings());
		}
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
				__('Client', 'signoffflow'),
				array(
					'read'                => true,
					'cliapwo_view_portal' => true,
				)
			);
		}

		if ($client_role instanceof \WP_Role) {
			$client_role->add_cap('cliapwo_view_portal');
		}
	}
}
