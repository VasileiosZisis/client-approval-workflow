<?php

/**
 * Plugin bootstrap.
 *
 * @package SignoffFlow
 */

namespace SignoffFlow;

defined('ABSPATH') || exit;

/**
 * Wires the plugin services into WordPress.
 */
class Plugin
{
	/**
	 * Settings service.
	 *
	 * @var Settings
	 */
	private $settings;

	/**
	 * Admin UI service.
	 *
	 * @var Admin
	 */
	private $admin;

	/**
	 * Constructor.
	 */
	public function __construct()
	{
		$this->settings = new Settings();
		$this->admin    = new Admin($this->settings);
	}

	/**
	 * Register the plugin hooks.
	 *
	 * @return void
	 */
	public function run()
	{
		add_action('plugins_loaded', array($this, 'load_textdomain'));
		$this->settings->register();
		$this->admin->register();
	}

	/**
	 * Load translations for the plugin.
	 *
	 * @return void
	 */
	public function load_textdomain()
	{
		load_plugin_textdomain(
			'signoffflow',
			false,
			dirname(plugin_basename(CLIAPWO_PLUGIN_FILE)) . '/languages'
		);
	}
}
