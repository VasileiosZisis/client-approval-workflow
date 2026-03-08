<?php

/**
 * Plugin Name:       SignoffFlow
 * Description:       Client approval workflow and client portal foundations for service businesses.
 * Version:           0.1.0
 * Author:      Vasileios Zisis
 * Author URI:  https://profiles.wordpress.org/vzisis/
 * Text Domain:       signoffflow
 * Requires at least: 6.0
 * Requires PHP:      7.4
 * License:           GPL v2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 *
 * @package SignoffFlow
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

register_activation_hook(CLIAPWO_PLUGIN_FILE, array(\SignoffFlow\Lifecycle::class, 'activate'));
register_deactivation_hook(CLIAPWO_PLUGIN_FILE, array(\SignoffFlow\Lifecycle::class, 'deactivate'));

$cliapwo_plugin = new \SignoffFlow\Plugin();
$cliapwo_plugin->run();
