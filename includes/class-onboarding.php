<?php

/**
 * State-aware first-run onboarding.
 *
 * @package VzisisClientApprovalWorkflow
 */

namespace Vzisis\ClientApprovalWorkflow;

defined('ABSPATH') || exit;

/**
 * Detects setup progress and stores onboarding presentation state.
 */
class Onboarding
{
	/**
	 * Initial activation timestamp option.
	 */
	public const FIRST_RUN_OPTION = 'cliapwo_onboarding_first_run';

	/**
	 * First successful onboarding completion timestamp option.
	 */
	public const COMPLETED_AT_OPTION = 'cliapwo_onboarding_completed_at';

	/**
	 * Per-user onboarding dismissal timestamp meta key.
	 */
	public const DISMISSED_USER_META_KEY = 'cliapwo_onboarding_dismissed';

	/**
	 * Marker used by plugin-created sample records.
	 */
	public const SAMPLE_CONTENT_META_KEY = 'cliapwo_sample_content';

	/**
	 * Admin-post action used to update onboarding visibility.
	 */
	public const VISIBILITY_ACTION = 'cliapwo_update_onboarding_visibility';

	/**
	 * Visibility action nonce name and action.
	 */
	public const VISIBILITY_NONCE_NAME = 'cliapwo_onboarding_visibility_nonce';

	public const VISIBILITY_NONCE_ACTION = 'cliapwo_update_onboarding_visibility';

	/**
	 * Number of posts inspected in each milestone query batch.
	 */
	private const QUERY_BATCH_SIZE = 50;

	/**
	 * Register onboarding hooks.
	 *
	 * @return void
	 */
	public function register()
	{
		add_action('admin_post_' . self::VISIBILITY_ACTION, array($this, 'handle_visibility_update'));
	}

	/**
	 * Record that this is a genuinely new installation.
	 *
	 * @return void
	 */
	public static function mark_fresh_install()
	{
		if (false === get_option(self::FIRST_RUN_OPTION, false)) {
			add_option(self::FIRST_RUN_OPTION, time(), '', 'no');
		}
	}

	/**
	 * Return the current setup progress.
	 *
	 * @return array<string, mixed>
	 */
	public function get_progress()
	{
		$portal_state = $this->get_portal_state();
		$completed_at = absint(get_option(self::COMPLETED_AT_OPTION, 0));

		if ($completed_at > 0) {
			$steps = array(
				'portal'    => ! empty($portal_state['complete']),
				'client'    => true,
				'assignment' => true,
				'request'   => true,
				'response'  => true,
			);
			$client_state = array(
				'client_id'             => 0,
				'assignment_client_id' => 0,
			);
			$request_id = 0;
			$response_event_id = 0;
		} else {
			$client_state      = $this->get_client_state();
			$request_id        = $this->find_qualifying_request_id();
			$response_event_id = $this->find_qualifying_response_event_id();
			$steps             = array(
				'portal'    => ! empty($portal_state['complete']),
				'client'    => $client_state['client_id'] > 0,
				'assignment' => $client_state['assignment_client_id'] > 0,
				'request'   => $request_id > 0,
				'response'  => $response_event_id > 0,
			);
		}

		$completed_count = count(array_filter($steps));
		$is_complete     = count($steps) === $completed_count;

		if ($is_complete && 0 === $completed_at) {
			$completed_at = time();
			add_option(self::COMPLETED_AT_OPTION, $completed_at, '', 'no');
			delete_option(self::FIRST_RUN_OPTION);
		}

		return array(
			'steps'                => $steps,
			'completed_count'      => $completed_count,
			'total_count'          => count($steps),
			'is_complete'          => $is_complete,
			'is_first_run'         => absint(get_option(self::FIRST_RUN_OPTION, 0)) > 0,
			'is_dismissed'         => $this->is_dismissed(),
			'completed_at'         => $completed_at,
			'portal_page_id'       => absint($portal_state['page_id']),
			'portal_page_exists'   => ! empty($portal_state['exists']),
			'client_id'            => absint($client_state['client_id']),
			'assignment_client_id' => absint($client_state['assignment_client_id']),
			'request_id'           => absint($request_id),
			'response_event_id'    => absint($response_event_id),
		);
	}

	/**
	 * Handle a per-user dismiss or reopen request.
	 *
	 * @return void
	 */
	public function handle_visibility_update()
	{
		check_admin_referer(self::VISIBILITY_NONCE_ACTION, self::VISIBILITY_NONCE_NAME);

		if (! current_user_can('cliapwo_manage_portal')) {
			wp_die(
				esc_html__('You are not allowed to change onboarding preferences.', 'signoffflow-client-approval-workflow'),
				esc_html__('Forbidden', 'signoffflow-client-approval-workflow'),
				array(
					'response' => 403,
				)
			);
		}

		$visibility = '';

		if (isset($_POST['cliapwo_onboarding_visibility'])) {
			$visibility = sanitize_key(wp_unslash($_POST['cliapwo_onboarding_visibility']));
		}

		if (! in_array($visibility, array('dismiss', 'reopen'), true)) {
			wp_die(
				esc_html__('Choose a valid onboarding visibility action.', 'signoffflow-client-approval-workflow'),
				esc_html__('Invalid request', 'signoffflow-client-approval-workflow'),
				array(
					'response' => 400,
				)
			);
		}

		$user_id = get_current_user_id();

		if ('dismiss' === $visibility) {
			update_user_meta($user_id, self::DISMISSED_USER_META_KEY, time());
		} else {
			delete_user_meta($user_id, self::DISMISSED_USER_META_KEY);
		}

		$redirect_url = add_query_arg(
			array(
				'page' => Settings::PAGE_SLUG,
			),
			admin_url('admin.php')
		);

		wp_safe_redirect($redirect_url);
		exit;
	}

	/**
	 * Determine whether onboarding is dismissed for the current user.
	 *
	 * @return bool
	 */
	private function is_dismissed()
	{
		$user_id = get_current_user_id();

		return $user_id > 0 && absint(get_user_meta($user_id, self::DISMISSED_USER_META_KEY, true)) > 0;
	}

	/**
	 * Return configured portal page state.
	 *
	 * @return array{page_id: int, exists: bool, complete: bool}
	 */
	private function get_portal_state()
	{
		$settings       = Settings::get_settings();
		$portal_page_id = isset($settings['portal_page_id']) ? absint($settings['portal_page_id']) : 0;
		$portal_page    = $portal_page_id > 0 ? get_post($portal_page_id) : null;
		$exists         = $portal_page instanceof \WP_Post && 'page' === $portal_page->post_type && 'trash' !== $portal_page->post_status;
		$complete       = $exists
			&& 'publish' === $portal_page->post_status
			&& has_shortcode((string) $portal_page->post_content, 'cliapwo_portal');

		return array(
			'page_id'  => $portal_page_id,
			'exists'   => $exists,
			'complete' => $complete,
		);
	}

	/**
	 * Find real client and portal-user assignment milestones.
	 *
	 * @return array{client_id: int, assignment_client_id: int}
	 */
	private function get_client_state()
	{
		$client_id             = 0;
		$assignment_client_id = 0;
		$page                  = 1;

		do {
			$query = new \WP_Query(
				array(
					'post_type'              => Clients::POST_TYPE,
					'post_status'            => 'publish',
					'posts_per_page'         => self::QUERY_BATCH_SIZE,
					'paged'                  => $page,
					'fields'                 => 'ids',
					'orderby'                => 'ID',
					'order'                  => 'ASC',
					'no_found_rows'          => true,
					'update_post_meta_cache' => true,
					'update_post_term_cache' => false,
				)
			);

			$post_ids      = array_map('absint', is_array($query->posts) ? $query->posts : array());
			$post_id_count = count($post_ids);

			foreach ($post_ids as $post_id) {
				if ($this->is_sample_content($post_id)) {
					continue;
				}

				if (0 === $client_id) {
					$client_id = $post_id;
				}

				if ($this->client_has_portal_user($post_id)) {
					$assignment_client_id = $post_id;
					break 2;
				}
			}

			++$page;
		} while (self::QUERY_BATCH_SIZE === $post_id_count);

		return array(
			'client_id'             => $client_id,
			'assignment_client_id' => $assignment_client_id,
		);
	}

	/**
	 * Determine whether a client has a usable non-staff portal user.
	 *
	 * @param int $client_id Client post ID.
	 * @return bool
	 */
	private function client_has_portal_user($client_id)
	{
		$user_ids = Clients::get_assigned_user_ids($client_id);

		foreach ($user_ids as $user_id) {
			$user = get_userdata($user_id);

			if (
				$user instanceof \WP_User
				&& user_can($user, 'cliapwo_view_portal')
				&& ! user_can($user, 'cliapwo_manage_portal')
			) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Find a qualifying published approval request.
	 *
	 * @return int
	 */
	private function find_qualifying_request_id()
	{
		$page = 1;

		do {
			$query = new \WP_Query(
				array(
					'post_type'              => Requests::POST_TYPE,
					'post_status'            => 'publish',
					'posts_per_page'         => self::QUERY_BATCH_SIZE,
					'paged'                  => $page,
					'fields'                 => 'ids',
					'orderby'                => 'ID',
					'order'                  => 'ASC',
					'no_found_rows'          => true,
					'update_post_meta_cache' => true,
					'update_post_term_cache' => false,
				)
			);

			$post_ids      = array_map('absint', is_array($query->posts) ? $query->posts : array());
			$post_id_count = count($post_ids);

			foreach ($post_ids as $post_id) {
				if ($this->is_real_published_request($post_id)) {
					return $post_id;
				}
			}

			++$page;
		} while (self::QUERY_BATCH_SIZE === $post_id_count);

		return 0;
	}

	/**
	 * Find a qualifying immutable client-response event.
	 *
	 * @return int
	 */
	private function find_qualifying_response_event_id()
	{
		$page = 1;

		do {
			$query = new \WP_Query(
				array(
					'post_type'              => Events::POST_TYPE,
					'post_status'            => 'publish',
					'posts_per_page'         => self::QUERY_BATCH_SIZE,
					'paged'                  => $page,
					'fields'                 => 'ids',
					'orderby'                => 'date',
					'order'                  => 'DESC',
					'no_found_rows'          => true,
					'update_post_meta_cache' => true,
					'update_post_term_cache' => false,
					// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- onboarding checks a private event type and actor snapshot in bounded batches only on the plugin settings screen.
					'meta_query'             => array(
						array(
							'key'   => Events::TYPE_META_KEY,
							'value' => Events::TYPE_REQUEST_RESPONSE,
						),
						array(
							'key'   => Events::ACTOR_TYPE_META_KEY,
							'value' => Events::ACTOR_TYPE_CLIENT,
						),
					),
				)
			);

			$event_ids      = array_map('absint', is_array($query->posts) ? $query->posts : array());
			$event_id_count = count($event_ids);

			foreach ($event_ids as $event_id) {
				$request_id = absint(get_post_meta($event_id, Events::OBJECT_ID_META_KEY, true));
				$client_id  = absint(get_post_meta($event_id, Events::CLIENT_META_KEY, true));
				$actor_type = sanitize_key((string) get_post_meta($event_id, Events::ACTOR_TYPE_META_KEY, true));
				$new_status = sanitize_key((string) get_post_meta($event_id, Events::NEW_STATUS_META_KEY, true));

				if (
					Events::ACTOR_TYPE_CLIENT === $actor_type
					&& in_array(
						$new_status,
						array(
							Requests::STATUS_APPROVED,
							Requests::STATUS_CHANGES_REQUESTED,
							Requests::STATUS_REJECTED,
							Requests::STATUS_BLOCKED,
						),
						true
					)
					&& $this->is_real_published_request($request_id)
					&& $this->is_real_published_client($client_id)
				) {
					return $event_id;
				}
			}

			++$page;
		} while (self::QUERY_BATCH_SIZE === $event_id_count);

		return 0;
	}

	/**
	 * Determine whether a request and its current client qualify.
	 *
	 * @param int $request_id Request post ID.
	 * @return bool
	 */
	private function is_real_published_request($request_id)
	{
		$request = get_post($request_id);

		if (
			! $request instanceof \WP_Post
			|| Requests::POST_TYPE !== $request->post_type
			|| 'publish' !== $request->post_status
			|| $this->is_sample_content($request_id)
		) {
			return false;
		}

		return $this->is_real_published_client(Requests::get_client_id_for_request($request_id));
	}

	/**
	 * Determine whether a client is published and not sample content.
	 *
	 * @param int $client_id Client post ID.
	 * @return bool
	 */
	private function is_real_published_client($client_id)
	{
		$client = get_post($client_id);

		return $client instanceof \WP_Post
			&& Clients::POST_TYPE === $client->post_type
			&& 'publish' === $client->post_status
			&& ! $this->is_sample_content($client_id);
	}

	/**
	 * Determine whether a post is marked as plugin-created sample content.
	 *
	 * @param int $post_id Post ID.
	 * @return bool
	 */
	private function is_sample_content($post_id)
	{
		return '1' === (string) get_post_meta($post_id, self::SAMPLE_CONTENT_META_KEY, true);
	}
}
