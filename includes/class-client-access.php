<?php

/**
 * Client-only access controls.
 *
 * @package ClientApprovalWorkflow
 */

namespace ClientApprovalWorkflow;

defined('ABSPATH') || exit;

/**
 * Redirects client users into the frontend portal experience.
 */
class Client_Access
{
	/**
	 * Register client access hooks.
	 *
	 * @return void
	 */
	public function register()
	{
		add_filter('login_redirect', array($this, 'redirect_clients_after_login'), 10, 3);
		add_action('admin_init', array($this, 'redirect_clients_away_from_admin'));
		add_filter('show_admin_bar', array($this, 'maybe_hide_admin_bar'));
	}

	/**
	 * Redirect client users to the portal page after login.
	 *
	 * @param string             $redirect_to           Redirect destination.
	 * @param string             $requested_redirect_to Requested redirect destination.
	 * @param \WP_User|\WP_Error $user               Authenticated user or error.
	 * @return string
	 */
	public function redirect_clients_after_login($redirect_to, $requested_redirect_to, $user)
	{
		if (! $user instanceof \WP_User || ! $this->is_client_user($user)) {
			return $redirect_to;
		}

		return $this->get_portal_url();
	}

	/**
	 * Redirect client users away from wp-admin screens.
	 *
	 * @return void
	 */
	public function redirect_clients_away_from_admin()
	{
		if (! is_admin() || ! is_user_logged_in()) {
			return;
		}

		$current_user = wp_get_current_user();

		if (! $current_user instanceof \WP_User || ! $this->is_client_user($current_user)) {
			return;
		}

		if (wp_doing_ajax()) {
			return;
		}

		global $pagenow;

		$allowed_admin_endpoints = array(
			'admin-post.php',
			'admin-ajax.php',
		);

		if (in_array((string) $pagenow, $allowed_admin_endpoints, true)) {
			return;
		}

		wp_safe_redirect($this->get_portal_url());
		exit;
	}

	/**
	 * Hide the admin bar for client users.
	 *
	 * @param bool $show Whether to show the admin bar.
	 * @return bool
	 */
	public function maybe_hide_admin_bar($show)
	{
		if (! is_user_logged_in()) {
			return $show;
		}

		$current_user = wp_get_current_user();

		if (! $current_user instanceof \WP_User || ! $this->is_client_user($current_user)) {
			return $show;
		}

		return false;
	}

	/**
	 * Determine whether a user should be restricted to the portal experience.
	 *
	 * @param \WP_User $user User object.
	 * @return bool
	 */
	private function is_client_user(\WP_User $user)
	{
		if (user_can($user, 'cliapwo_manage_portal')) {
			return false;
		}

		return in_array('cliapwo_client', (array) $user->roles, true) && user_can($user, 'cliapwo_view_portal');
	}

	/**
	 * Get the frontend portal URL or a safe site fallback.
	 *
	 * @return string
	 */
	private function get_portal_url()
	{
		$settings       = Settings::get_settings();
		$portal_page_id = isset($settings['portal_page_id']) ? absint($settings['portal_page_id']) : 0;

		if ($portal_page_id > 0) {
			$portal_page = get_post($portal_page_id);

			if ($portal_page instanceof \WP_Post && 'page' === $portal_page->post_type) {
				$portal_url = get_permalink($portal_page_id);

				if (is_string($portal_url) && '' !== $portal_url) {
					return $portal_url;
				}
			}
		}

		return home_url('/');
	}
}
