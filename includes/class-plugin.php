<?php

/**
 * Plugin bootstrap.
 *
 * @package ClientApprovalWorkflow
 */

namespace ClientApprovalWorkflow;

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
	 * Client module service.
	 *
	 * @var Clients
	 */
	private $clients;

	/**
	 * Constructor.
	 */
	public function __construct()
	{
		$this->settings = new Settings();
		$this->admin    = new Admin($this->settings);
		$this->clients  = new Clients();
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
		$this->clients->register();
	}

	/**
	 * Load translations for the plugin.
	 *
	 * @return void
	 */
	public function load_textdomain()
	{
		load_plugin_textdomain(
			'client-approval-workflow',
			false,
			dirname(plugin_basename(CLIAPWO_PLUGIN_FILE)) . '/languages'
		);
	}
}
