<?php

/**
 * Plugin Name:       SignoffFlow
 * Description:       Client approval workflow and client portal foundations for service businesses.
 * Version:           0.1.0
 * Author:      Vasileios Zisis
 * Author URI:  https://profiles.wordpress.org/vzisis/
 * Text Domain:       client-approval-workflow
 * Requires at least: 6.0
 * Requires PHP:      7.4
 * License:           GPL v2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 *
 * @package ClientApprovalWorkflow
 */

defined('ABSPATH') || exit;

define('CLIAPWO_VERSION', '0.1.0');
define('CLIAPWO_PLUGIN_FILE', __FILE__);
define('CLIAPWO_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('CLIAPWO_PLUGIN_URL', plugin_dir_url(__FILE__));

require_once CLIAPWO_PLUGIN_DIR . 'includes/class-plugin.php';
require_once CLIAPWO_PLUGIN_DIR . 'includes/class-lifecycle.php';
require_once CLIAPWO_PLUGIN_DIR . 'includes/class-settings.php';
require_once CLIAPWO_PLUGIN_DIR . 'includes/class-admin.php';
require_once CLIAPWO_PLUGIN_DIR . 'includes/class-clients.php';

register_activation_hook(CLIAPWO_PLUGIN_FILE, array(\ClientApprovalWorkflow\Lifecycle::class, 'activate'));
register_deactivation_hook(CLIAPWO_PLUGIN_FILE, array(\ClientApprovalWorkflow\Lifecycle::class, 'deactivate'));

$cliapwo_plugin = new \ClientApprovalWorkflow\Plugin();
$cliapwo_plugin->run();

/**
 * Get the assigned client posts for a user.
 *
 * @param int $user_id WordPress user ID.
 * @return array<int, \WP_Post>
 */
function cliapwo_get_clients_for_user($user_id = 0)
{
	return \ClientApprovalWorkflow\Clients::get_clients_for_user($user_id);
}

/**
 * Get the first assigned client for a user.
 *
 * @param int $user_id WordPress user ID.
 * @return \WP_Post|null
 */
function cliapwo_get_client_for_user($user_id = 0)
{
	return \ClientApprovalWorkflow\Clients::get_client_for_user($user_id);
}

/**
 * Determine whether a user can view a client portal.
 *
 * @param int $client_id Client post ID.
 * @param int $user_id   WordPress user ID.
 * @return bool
 */
function cliapwo_user_can_view_client($client_id, $user_id = 0)
{
	return \ClientApprovalWorkflow\Clients::user_can_view_client($client_id, $user_id);
}
