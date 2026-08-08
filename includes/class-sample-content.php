<?php

/**
 * Opt-in sample content creation and cleanup.
 *
 * @package VzisisClientApprovalWorkflow
 */

namespace Vzisis\ClientApprovalWorkflow;

defined('ABSPATH') || exit;

/**
 * Manages the small, non-notifying sample workflow shown from Settings.
 */
class Sample_Content
{
	/**
	 * Stored IDs for the current generated sample set.
	 */
	public const OPTION_KEY = 'cliapwo_sample_content_ids';

	/**
	 * Admin-post action and submitted operation field.
	 */
	public const ACTION = 'cliapwo_manage_sample_content';

	public const OPERATION_FIELD = 'cliapwo_sample_content_operation';

	/**
	 * Action nonce details.
	 */
	public const NONCE_ACTION = 'cliapwo_manage_sample_content';

	public const NONCE_NAME = 'cliapwo_sample_content_nonce';

	/**
	 * Cleanup confirmation field.
	 */
	public const CLEANUP_CONFIRMATION_FIELD = 'cliapwo_confirm_sample_cleanup';

	/**
	 * Signed status notice query arguments.
	 */
	public const NOTICE_STATUS_QUERY_KEY = 'cliapwo_sample_status';

	public const NOTICE_NONCE_QUERY_KEY = 'cliapwo_sample_notice_nonce';

	private const NOTICE_NONCE_ACTION = 'cliapwo_sample_content_notice';

	/**
	 * Signed staff-preview query arguments.
	 */
	public const PREVIEW_CLIENT_QUERY_KEY = 'cliapwo_sample_preview_client';

	public const PREVIEW_NONCE_QUERY_KEY = 'cliapwo_sample_preview_nonce';

	private const PREVIEW_NONCE_ACTION_PREFIX = 'cliapwo_sample_preview_';

	/**
	 * Register sample-content hooks.
	 *
	 * @return void
	 */
	public function register()
	{
		add_action('admin_post_' . self::ACTION, array($this, 'handle_action'));
	}

	/**
	 * Handle an explicit create, repair, or cleanup request.
	 *
	 * @return void
	 */
	public function handle_action()
	{
		check_admin_referer(self::NONCE_ACTION, self::NONCE_NAME);

		if (! current_user_can('cliapwo_manage_portal')) {
			wp_die(
				esc_html__('You are not allowed to manage SignoffFlow sample content.', 'signoffflow-client-approval-workflow'),
				esc_html__('Forbidden', 'signoffflow-client-approval-workflow'),
				array(
					'response' => 403,
				)
			);
		}

		$operation = '';

		if (isset($_POST[self::OPERATION_FIELD])) {
			$operation = sanitize_key(wp_unslash($_POST[self::OPERATION_FIELD]));
		}

		if (! in_array($operation, array('create', 'cleanup'), true)) {
			wp_die(
				esc_html__('Choose a valid sample-content action.', 'signoffflow-client-approval-workflow'),
				esc_html__('Invalid request', 'signoffflow-client-approval-workflow'),
				array(
					'response' => 400,
				)
			);
		}

		if ('cleanup' === $operation) {
			$confirmed = isset($_POST[self::CLEANUP_CONFIRMATION_FIELD])
				? sanitize_key(wp_unslash($_POST[self::CLEANUP_CONFIRMATION_FIELD]))
				: '';

			if ('1' !== $confirmed) {
				$this->redirect_to_settings('confirmation_required');
			}

			$this->redirect_to_settings($this->cleanup());
		}

		$state = $this->get_state();

		if (! empty($state['is_complete'])) {
			$this->redirect_to_settings('existing');
		}

		$this->redirect_to_settings($this->create_or_repair($state));
	}

	/**
	 * Return the current recorded sample-set state.
	 *
	 * @return array<string, mixed>
	 */
	public function get_state()
	{
		$recorded_ids = self::get_recorded_ids();
		$existing_ids = array();

		foreach ($this->get_expected_post_types() as $key => $post_type) {
			$post_id = isset($recorded_ids[$key]) ? absint($recorded_ids[$key]) : 0;

			if ($this->is_marked_post_of_type($post_id, $post_type)) {
				$existing_ids[$key] = $post_id;
			}
		}

		$complete_keys = array();
		$client_id     = isset($existing_ids['client']) ? absint($existing_ids['client']) : 0;
		$update_id     = isset($existing_ids['update']) ? absint($existing_ids['update']) : 0;
		$request_id    = isset($existing_ids['request']) ? absint($existing_ids['request']) : 0;

		if ($client_id > 0 && 'publish' === get_post_status($client_id) && empty(Clients::get_assigned_user_ids($client_id))) {
			$complete_keys['client'] = $client_id;
		}

		if (
			$update_id > 0
			&& 'publish' === get_post_status($update_id)
			&& $client_id > 0
			&& Updates::get_client_id_for_update($update_id) === $client_id
			&& Updates::VISIBILITY_CLIENT === (string) get_post_meta($update_id, Updates::VISIBILITY_META_KEY, true)
			&& '1' === (string) get_post_meta($update_id, Updates::NOTIFIED_META_KEY, true)
		) {
			$complete_keys['update'] = $update_id;
		}

		if (
			$request_id > 0
			&& 'publish' === get_post_status($request_id)
			&& $client_id > 0
			&& Requests::get_client_id_for_request($request_id) === $client_id
			&& '1' === (string) get_post_meta($request_id, Requests::NOTIFIED_META_KEY, true)
		) {
			$complete_keys['request'] = $request_id;
		}

		if ($this->event_matches(isset($existing_ids['update_event']) ? $existing_ids['update_event'] : 0, Events::TYPE_UPDATE_CREATED, $client_id, $update_id)) {
			$complete_keys['update_event'] = absint($existing_ids['update_event']);
		}

		if ($this->event_matches(isset($existing_ids['request_event']) ? $existing_ids['request_event'] : 0, Events::TYPE_REQUEST_CREATED, $client_id, $request_id)) {
			$complete_keys['request_event'] = absint($existing_ids['request_event']);
		}

		$total_count     = count($this->get_expected_post_types());
		$complete_count  = count($complete_keys);
		$recorded_count  = count(array_filter($recorded_ids));
		$is_complete     = $total_count === $complete_count;
		$status          = $is_complete ? 'ready' : ($recorded_count > 0 ? 'partial' : 'empty');

		return array(
			'status'          => $status,
			'is_complete'     => $is_complete,
			'has_records'     => $recorded_count > 0,
			'recorded_ids'    => $recorded_ids,
			'existing_ids'    => $existing_ids,
			'complete_ids'    => $complete_keys,
			'complete_count'  => $complete_count,
			'total_count'     => $total_count,
			'client_id'       => $client_id,
			'update_id'       => $update_id,
			'request_id'      => $request_id,
		);
	}

	/**
	 * Return a nonce-protected URL that selects the sample client for staff.
	 *
	 * @param array<string, mixed>|null $state Optional preloaded sample state.
	 * @return string
	 */
	public function get_preview_url($state = null)
	{
		if (! is_array($state)) {
			$state = $this->get_state();
		}

		$client_id = isset($state['client_id']) ? absint($state['client_id']) : 0;

		if ($client_id <= 0 || ! $this->is_configured_portal_available()) {
			return '';
		}

		$settings       = Settings::get_settings();
		$portal_page_id = isset($settings['portal_page_id']) ? absint($settings['portal_page_id']) : 0;
		$portal_url     = get_permalink($portal_page_id);

		if (! is_string($portal_url) || '' === $portal_url) {
			return '';
		}

		return add_query_arg(
			array(
				self::PREVIEW_CLIENT_QUERY_KEY => $client_id,
				self::PREVIEW_NONCE_QUERY_KEY  => wp_create_nonce(self::PREVIEW_NONCE_ACTION_PREFIX . $client_id),
			),
			$portal_url
		);
	}

	/**
	 * Resolve a signed sample preview request without falling back to real data.
	 *
	 * @return array{requested: bool, client_id: int}
	 */
	public static function get_preview_request()
	{
		if (! isset($_GET[self::PREVIEW_CLIENT_QUERY_KEY], $_GET[self::PREVIEW_NONCE_QUERY_KEY])) {
			return array(
				'requested' => isset($_GET[self::PREVIEW_CLIENT_QUERY_KEY]) || isset($_GET[self::PREVIEW_NONCE_QUERY_KEY]),
				'client_id' => 0,
			);
		}

		$client_id = absint(wp_unslash($_GET[self::PREVIEW_CLIENT_QUERY_KEY]));
		$nonce     = sanitize_text_field(wp_unslash($_GET[self::PREVIEW_NONCE_QUERY_KEY]));

		if (
			$client_id <= 0
			|| ! wp_verify_nonce($nonce, self::PREVIEW_NONCE_ACTION_PREFIX . $client_id)
			|| ! current_user_can('cliapwo_manage_portal')
		) {
			return array(
				'requested' => true,
				'client_id' => 0,
			);
		}

		$recorded_ids = self::get_recorded_ids();

		if (! isset($recorded_ids['client']) || absint($recorded_ids['client']) !== $client_id) {
			return array(
				'requested' => true,
				'client_id' => 0,
			);
		}

		$client = get_post($client_id);

		if (
			! $client instanceof \WP_Post
			|| Clients::POST_TYPE !== $client->post_type
			|| 'publish' !== $client->post_status
			|| '1' !== (string) get_post_meta($client_id, Onboarding::SAMPLE_CONTENT_META_KEY, true)
		) {
			return array(
				'requested' => true,
				'client_id' => 0,
			);
		}

		return array(
			'requested' => true,
			'client_id' => $client_id,
		);
	}

	/**
	 * Return an allowlisted, nonce-verified redirect status.
	 *
	 * @return string
	 */
	public function get_verified_notice_status()
	{
		if (! isset($_GET[self::NOTICE_STATUS_QUERY_KEY], $_GET[self::NOTICE_NONCE_QUERY_KEY])) {
			return '';
		}

		$status = sanitize_key(wp_unslash($_GET[self::NOTICE_STATUS_QUERY_KEY]));
		$nonce  = sanitize_text_field(wp_unslash($_GET[self::NOTICE_NONCE_QUERY_KEY]));

		if (
			! wp_verify_nonce($nonce, self::NOTICE_NONCE_ACTION)
			|| ! current_user_can('cliapwo_manage_portal')
			|| ! in_array($status, self::get_notice_statuses(), true)
		) {
			return '';
		}

		return $status;
	}

	/**
	 * Create or repair the recorded sample workflow.
	 *
	 * @param array<string, mixed> $state Existing sample state.
	 * @return string Redirect status.
	 */
	private function create_or_repair(array $state)
	{
		$ids          = isset($state['recorded_ids']) && is_array($state['recorded_ids']) ? $state['recorded_ids'] : array();
		$existing_ids = isset($state['existing_ids']) && is_array($state['existing_ids']) ? $state['existing_ids'] : array();
		$had_records  = ! empty($state['has_records']);
		$actor_id     = get_current_user_id();

		$client_id = isset($existing_ids['client']) ? absint($existing_ids['client']) : 0;

		if ($client_id <= 0) {
			$client_id = $this->insert_sample_post(
				Clients::POST_TYPE,
				__('Sample Client — Northstar Studio', 'signoffflow-client-approval-workflow'),
				'',
				array()
			);
		}

		if ($client_id <= 0) {
			return 'error';
		}

		delete_post_meta($client_id, Clients::ASSIGNED_USERS_META_KEY);
		update_post_meta($client_id, Onboarding::SAMPLE_CONTENT_META_KEY, '1');
		$this->publish_post($client_id);
		$ids['client'] = $client_id;
		self::persist_ids($ids);

		$update_id = isset($existing_ids['update']) ? absint($existing_ids['update']) : 0;

		if ($update_id <= 0) {
			$update_id = $this->insert_sample_post(
				Updates::POST_TYPE,
				__('Sample update: Homepage concepts ready', 'signoffflow-client-approval-workflow'),
				__('We prepared the first homepage concepts and refined the navigation, calls to action, and mobile layout. Review the approval request and share your decision.', 'signoffflow-client-approval-workflow'),
				array(
					Updates::CLIENT_META_KEY     => $client_id,
					Updates::VISIBILITY_META_KEY => Updates::VISIBILITY_CLIENT,
					Updates::NOTIFIED_META_KEY   => '1',
				)
			);
		}

		if ($update_id <= 0) {
			return 'partial';
		}

		update_post_meta($update_id, Onboarding::SAMPLE_CONTENT_META_KEY, '1');
		update_post_meta($update_id, Updates::CLIENT_META_KEY, $client_id);
		update_post_meta($update_id, Updates::VISIBILITY_META_KEY, Updates::VISIBILITY_CLIENT);
		update_post_meta($update_id, Updates::NOTIFIED_META_KEY, '1');
		$this->publish_post($update_id);
		$ids['update'] = $update_id;
		self::persist_ids($ids);

		$request_id = isset($existing_ids['request']) ? absint($existing_ids['request']) : 0;

		if ($request_id <= 0) {
			$request_id = $this->insert_sample_post(
				Requests::POST_TYPE,
				__('Sample request: Approve the homepage direction', 'signoffflow-client-approval-workflow'),
				__('Please review the homepage direction and confirm whether the team should move into final design.', 'signoffflow-client-approval-workflow'),
				array(
					Requests::CLIENT_META_KEY   => $client_id,
					Requests::STATUS_META_KEY   => Requests::STATUS_OPEN,
					Requests::NOTIFIED_META_KEY => '1',
				)
			);
		}

		if ($request_id <= 0) {
			return 'partial';
		}

		update_post_meta($request_id, Onboarding::SAMPLE_CONTENT_META_KEY, '1');
		update_post_meta($request_id, Requests::CLIENT_META_KEY, $client_id);
		update_post_meta($request_id, Requests::NOTIFIED_META_KEY, '1');

		if ('' === (string) get_post_meta($request_id, Requests::STATUS_META_KEY, true)) {
			update_post_meta($request_id, Requests::STATUS_META_KEY, Requests::STATUS_OPEN);
		}

		$this->publish_post($request_id);
		$ids['request'] = $request_id;
		self::persist_ids($ids);

		$update_event_id = isset($existing_ids['update_event']) ? absint($existing_ids['update_event']) : 0;

		if ($update_event_id <= 0) {
			$update_event_id = Events::record_sample_created_event($update_id, $client_id, Events::TYPE_UPDATE_CREATED, $actor_id);
		}

		if ($update_event_id <= 0) {
			return 'partial';
		}

		$this->repair_event($update_event_id, Events::TYPE_UPDATE_CREATED, $client_id, $update_id, $actor_id);
		$ids['update_event'] = $update_event_id;
		self::persist_ids($ids);

		$request_event_id = isset($existing_ids['request_event']) ? absint($existing_ids['request_event']) : 0;

		if ($request_event_id <= 0) {
			$request_event_id = Events::record_sample_created_event($request_id, $client_id, Events::TYPE_REQUEST_CREATED, $actor_id);
		}

		if ($request_event_id <= 0) {
			return 'partial';
		}

		$this->repair_event($request_event_id, Events::TYPE_REQUEST_CREATED, $client_id, $request_id, $actor_id);
		$ids['request_event'] = $request_event_id;
		self::persist_ids($ids);

		$final_state = $this->get_state();

		if (empty($final_state['is_complete'])) {
			return 'partial';
		}

		return $had_records ? 'repaired' : 'created';
	}

	/**
	 * Permanently remove recorded posts that still match their sample contract.
	 *
	 * @return string Redirect status.
	 */
	private function cleanup()
	{
		$ids            = self::get_recorded_ids();
		$expected_types = $this->get_expected_post_types();
		$delete_order   = array('request_event', 'update_event', 'request', 'update', 'client');
		$remaining      = $ids;

		foreach ($delete_order as $key) {
			$post_id = isset($ids[$key]) ? absint($ids[$key]) : 0;

			if ($post_id <= 0 || ! get_post($post_id) instanceof \WP_Post) {
				unset($remaining[$key]);
				continue;
			}

			if (! isset($expected_types[$key]) || ! $this->is_marked_post_of_type($post_id, $expected_types[$key])) {
				continue;
			}

			if (false !== wp_delete_post($post_id, true)) {
				unset($remaining[$key]);
			}
		}

		if (empty(array_filter($remaining))) {
			delete_option(self::OPTION_KEY);
			return 'removed';
		}

		self::persist_ids($remaining);

		return 'cleanup_partial';
	}

	/**
	 * Insert a draft sample post with all safety metadata in place.
	 *
	 * @param string               $post_type Post type.
	 * @param string               $title     Post title.
	 * @param string               $content   Post content.
	 * @param array<string, mixed> $meta      Additional metadata.
	 * @return int
	 */
	private function insert_sample_post($post_type, $title, $content, array $meta)
	{
		$meta[Onboarding::SAMPLE_CONTENT_META_KEY] = '1';

		$post_id = wp_insert_post(
			array(
				'post_type'    => sanitize_key($post_type),
				'post_status'  => 'draft',
				'post_title'   => wp_strip_all_tags($title),
				'post_content' => wp_kses_post($content),
				'post_author'  => get_current_user_id(),
				'meta_input'   => $meta,
			),
			true
		);

		if (is_wp_error($post_id) || ! is_numeric($post_id)) {
			return 0;
		}

		return absint($post_id);
	}

	/**
	 * Publish a generated post without overwriting its edited title or content.
	 *
	 * @param int $post_id Post ID.
	 * @return bool
	 */
	private function publish_post($post_id)
	{
		if ('publish' === get_post_status($post_id)) {
			return true;
		}

		$result = wp_update_post(
			array(
				'ID'          => absint($post_id),
				'post_status' => 'publish',
			),
			true
		);

		return ! is_wp_error($result) && absint($result) > 0;
	}

	/**
	 * Repair structural metadata on an existing sample event.
	 *
	 * @param int    $event_id   Event post ID.
	 * @param string $event_type Event type.
	 * @param int    $client_id  Client post ID.
	 * @param int    $object_id  Related object post ID.
	 * @param int    $actor_id   Staff actor ID.
	 * @return void
	 */
	private function repair_event($event_id, $event_type, $client_id, $object_id, $actor_id)
	{
		update_post_meta($event_id, Onboarding::SAMPLE_CONTENT_META_KEY, '1');
		update_post_meta($event_id, Events::TYPE_META_KEY, sanitize_key($event_type));
		update_post_meta($event_id, Events::CLIENT_META_KEY, absint($client_id));
		update_post_meta($event_id, Events::OBJECT_ID_META_KEY, absint($object_id));

		if (Events::TYPE_REQUEST_CREATED === $event_type) {
			$actor = get_userdata($actor_id);
			$actor_name = $actor instanceof \WP_User && '' !== (string) $actor->display_name
				? $actor->display_name
				: __('Staff user', 'signoffflow-client-approval-workflow');

			update_post_meta($event_id, Events::ACTOR_ID_META_KEY, absint($actor_id));
			update_post_meta($event_id, Events::ACTOR_NAME_META_KEY, sanitize_text_field($actor_name));
			update_post_meta($event_id, Events::ACTOR_TYPE_META_KEY, Events::ACTOR_TYPE_STAFF);
			update_post_meta($event_id, Events::NEW_STATUS_META_KEY, Requests::STATUS_OPEN);
		}

		$this->publish_post($event_id);
	}

	/**
	 * Determine whether an event belongs to the current sample relationship.
	 *
	 * @param int    $event_id   Event post ID.
	 * @param string $event_type Event type.
	 * @param int    $client_id  Client post ID.
	 * @param int    $object_id  Related object ID.
	 * @return bool
	 */
	private function event_matches($event_id, $event_type, $client_id, $object_id)
	{
		$event_id = absint($event_id);

		return $event_id > 0
			&& 'publish' === get_post_status($event_id)
			&& sanitize_key($event_type) === (string) get_post_meta($event_id, Events::TYPE_META_KEY, true)
			&& absint($client_id) > 0
			&& absint($client_id) === absint(get_post_meta($event_id, Events::CLIENT_META_KEY, true))
			&& absint($object_id) > 0
			&& absint($object_id) === absint(get_post_meta($event_id, Events::OBJECT_ID_META_KEY, true));
	}

	/**
	 * Determine whether a post matches the stored sample marker and post type.
	 *
	 * @param int    $post_id   Post ID.
	 * @param string $post_type Expected post type.
	 * @return bool
	 */
	private function is_marked_post_of_type($post_id, $post_type)
	{
		$post = get_post(absint($post_id));

		return $post instanceof \WP_Post
			&& $post_type === $post->post_type
			&& '1' === (string) get_post_meta($post->ID, Onboarding::SAMPLE_CONTENT_META_KEY, true);
	}

	/**
	 * Return expected option keys and post types.
	 *
	 * @return array<string, string>
	 */
	private function get_expected_post_types()
	{
		return array(
			'client'        => Clients::POST_TYPE,
			'update'        => Updates::POST_TYPE,
			'request'       => Requests::POST_TYPE,
			'update_event'  => Events::POST_TYPE,
			'request_event' => Events::POST_TYPE,
		);
	}

	/**
	 * Return sanitized IDs from storage.
	 *
	 * @return array<string, int>
	 */
	private static function get_recorded_ids()
	{
		$stored = get_option(self::OPTION_KEY, array());

		if (! is_array($stored)) {
			return array();
		}

		$ids = array();

		foreach (array('client', 'update', 'request', 'update_event', 'request_event') as $key) {
			if (isset($stored[$key]) && absint($stored[$key]) > 0) {
				$ids[$key] = absint($stored[$key]);
			}
		}

		return $ids;
	}

	/**
	 * Persist sanitized IDs without autoloading the option.
	 *
	 * @param array<string, mixed> $ids Sample post IDs.
	 * @return void
	 */
	private static function persist_ids(array $ids)
	{
		$sanitized = array();

		foreach (array('client', 'update', 'request', 'update_event', 'request_event') as $key) {
			if (isset($ids[$key]) && absint($ids[$key]) > 0) {
				$sanitized[$key] = absint($ids[$key]);
			}
		}

		if (false === get_option(self::OPTION_KEY, false)) {
			add_option(self::OPTION_KEY, $sanitized, '', 'no');
			return;
		}

		update_option(self::OPTION_KEY, $sanitized, false);
	}

	/**
	 * Check whether the configured portal can render a sample preview.
	 *
	 * @return bool
	 */
	private function is_configured_portal_available()
	{
		$settings       = Settings::get_settings();
		$portal_page_id = isset($settings['portal_page_id']) ? absint($settings['portal_page_id']) : 0;
		$portal_page    = $portal_page_id > 0 ? get_post($portal_page_id) : null;

		return $portal_page instanceof \WP_Post
			&& 'page' === $portal_page->post_type
			&& 'publish' === $portal_page->post_status
			&& has_shortcode((string) $portal_page->post_content, 'cliapwo_portal');
	}

	/**
	 * Redirect to Settings with a signed, allowlisted notice.
	 *
	 * @param string $status Notice status.
	 * @return void
	 */
	private function redirect_to_settings($status)
	{
		$status = sanitize_key($status);

		if (! in_array($status, self::get_notice_statuses(), true)) {
			$status = 'error';
		}

		$redirect_url = add_query_arg(
			array(
				'page'                        => Settings::PAGE_SLUG,
				self::NOTICE_STATUS_QUERY_KEY => $status,
				self::NOTICE_NONCE_QUERY_KEY  => wp_create_nonce(self::NOTICE_NONCE_ACTION),
			),
			admin_url('admin.php')
		);

		wp_safe_redirect($redirect_url);
		exit;
	}

	/**
	 * Return the allowed redirect status values.
	 *
	 * @return array<int, string>
	 */
	private static function get_notice_statuses()
	{
		return array(
			'created',
			'existing',
			'repaired',
			'removed',
			'partial',
			'cleanup_partial',
			'confirmation_required',
			'error',
		);
	}
}
