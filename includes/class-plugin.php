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
	 * Client-only access service.
	 *
	 * @var Client_Access
	 */
	private $client_access;

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
	 * Update module service.
	 *
	 * @var Updates
	 */
	private $updates;

	/**
	 * Portal shortcode service.
	 *
	 * @var Portal
	 */
	private $portal;

	/**
	 * File module service.
	 *
	 * @var Files
	 */
	private $files;

	/**
	 * Requests module service.
	 *
	 * @var Requests
	 */
	private $requests;

	/**
	 * Event logging and notifications service.
	 *
	 * @var Events
	 */
	private $events;

	/**
	 * Approvals extension contract and placeholder service.
	 *
	 * @var Approvals
	 */
	private $approvals;

	/**
	 * Constructor.
	 */
	public function __construct()
	{
		$this->client_access = new Client_Access();
		$this->settings      = new Settings();
		$this->admin         = new Admin($this->settings);
		$this->clients       = new Clients();
		$this->updates       = new Updates();
		$this->portal        = new Portal();
		$this->files         = new Files();
		$this->requests      = new Requests();
		$this->events        = new Events();
		$this->approvals     = new Approvals();
	}

	/**
	 * Register the plugin hooks.
	 *
	 * @return void
	 */
	public function run()
	{
		$this->client_access->register();
		$this->settings->register();
		$this->admin->register();
		$this->clients->register();
		$this->updates->register();
		$this->portal->register();
		$this->files->register();
		$this->requests->register();
		$this->events->register();
		$this->approvals->register();
	}
}
