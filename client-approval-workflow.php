<?php

/**
 * Plugin Name:       SignoffFlow
 * Description:       Client approval workflow and client portal foundations for service businesses.
 * Version:           0.2.0
 * Author:      Vasileios Zisis
 * Author URI:  https://profiles.wordpress.org/vzisis/
 * Text Domain:       signoffflow
 * Domain Path:       /languages
 * Requires at least: 6.0
 * Requires PHP:      7.4
 * License:           GPL v2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 *
 * @package ClientApprovalWorkflow
 */

defined('ABSPATH') || exit;

define('CLIAPWO_VERSION', '0.2.0');
define('CLIAPWO_PLUGIN_FILE', __FILE__);
define('CLIAPWO_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('CLIAPWO_PLUGIN_URL', plugin_dir_url(__FILE__));

require_once CLIAPWO_PLUGIN_DIR . 'includes/class-plugin.php';
require_once CLIAPWO_PLUGIN_DIR . 'includes/class-lifecycle.php';
require_once CLIAPWO_PLUGIN_DIR . 'includes/class-settings.php';
require_once CLIAPWO_PLUGIN_DIR . 'includes/class-admin.php';
require_once CLIAPWO_PLUGIN_DIR . 'includes/class-clients.php';
require_once CLIAPWO_PLUGIN_DIR . 'includes/class-updates.php';
require_once CLIAPWO_PLUGIN_DIR . 'includes/class-portal.php';
require_once CLIAPWO_PLUGIN_DIR . 'includes/class-files.php';
require_once CLIAPWO_PLUGIN_DIR . 'includes/class-requests.php';
require_once CLIAPWO_PLUGIN_DIR . 'includes/class-events.php';
require_once CLIAPWO_PLUGIN_DIR . 'includes/class-approvals.php';

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

/**
 * Get a paged updates query for a client.
 *
 * @param int   $client_id Client post ID.
 * @param array $args      Optional query overrides.
 * @return \WP_Query
 */
function cliapwo_get_updates_query_for_client($client_id, array $args = array())
{
	return \ClientApprovalWorkflow\Updates::get_updates_query_for_client($client_id, $args);
}

/**
 * Get a paged files query for a client.
 *
 * @param int   $client_id Client post ID.
 * @param array $args      Optional query overrides.
 * @return \WP_Query
 */
function cliapwo_get_files_query_for_client($client_id, array $args = array())
{
	return \ClientApprovalWorkflow\Files::get_files_query_for_client($client_id, $args);
}

/**
 * Get a protected download URL for a file post.
 *
 * @param int $file_post_id File post ID.
 * @return string
 */
function cliapwo_get_file_download_url($file_post_id)
{
	return \ClientApprovalWorkflow\Files::get_download_url($file_post_id);
}

/**
 * Get a requests query for a client.
 *
 * @param int   $client_id Client post ID.
 * @param array $args      Optional query overrides.
 * @return \WP_Query
 */
function cliapwo_get_requests_query_for_client($client_id, array $args = array())
{
	return \ClientApprovalWorkflow\Requests::get_requests_query_for_client($client_id, $args);
}

/**
 * Determine whether the SignoffFlow Pro add-on is active.
 *
 * @return bool
 */
function cliapwo_is_pro_active()
{
	$is_active = defined('CLIAPWO_PRO_VERSION') || class_exists('\ClientApprovalWorkflowPro\Plugin');

	/**
	 * Filter whether the SignoffFlow Pro add-on is active.
	 *
	 * @param bool $is_active Whether Pro is active.
	 */
	return (bool) apply_filters('cliapwo_is_pro_active', $is_active);
}

/**
 * Get the approvals data contract for extension plugins.
 *
 * @return array<string, mixed>
 */
function cliapwo_get_approvals_schema()
{
	return \ClientApprovalWorkflow\Approvals::get_schema();
}
