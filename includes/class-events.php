<?php

/**
 * Event logging and client email notifications.
 *
 * @package VzisisClientApprovalWorkflow
 */

namespace Vzisis\ClientApprovalWorkflow;

defined('ABSPATH') || exit;

/**
 * Handles internal event logging and outbound client emails.
 */
class Events
{
	/**
	 * Event log post type slug.
	 */
	public const POST_TYPE = 'cliapwo_event';

	/**
	 * Event type meta key.
	 */
	public const TYPE_META_KEY = 'cliapwo_event_type';

	/**
	 * Linked client meta key.
	 */
	public const CLIENT_META_KEY = 'cliapwo_client_id';

	/**
	 * Related object meta key.
	 */
	public const OBJECT_ID_META_KEY = 'cliapwo_related_object_id';

	/**
	 * Request-event actor metadata.
	 */
	public const ACTOR_ID_META_KEY = 'cliapwo_event_actor_id';

	public const ACTOR_NAME_META_KEY = 'cliapwo_event_actor_name';

	public const ACTOR_TYPE_META_KEY = 'cliapwo_event_actor_type';

	/**
	 * Request transition snapshot metadata.
	 */
	public const PREVIOUS_STATUS_META_KEY = 'cliapwo_event_previous_status';

	public const NEW_STATUS_META_KEY = 'cliapwo_event_new_status';

	public const RESPONSE_NOTE_META_KEY = 'cliapwo_event_response_note';

	public const IS_BACKFILL_META_KEY = 'cliapwo_event_is_backfill';

	/**
	 * Request lifecycle event types.
	 */
	public const TYPE_REQUEST_CREATED = 'request_created';

	public const TYPE_REQUEST_RESPONSE = 'request_response';

	public const TYPE_REQUEST_REOPENED = 'request_reopened';

	public const TYPE_REQUEST_STATUS_CHANGED = 'request_status_changed';

	/**
	 * Actor types stored on request lifecycle events.
	 */
	public const ACTOR_TYPE_CLIENT = 'client';

	public const ACTOR_TYPE_STAFF = 'staff';

	/**
	 * Request-history data version option and current version.
	 */
	public const DATA_VERSION_OPTION = 'cliapwo_data_version';

	public const DATA_VERSION = '1.4.0';

	/**
	 * Maximum legacy responses migrated on one admin request.
	 */
	private const BACKFILL_BATCH_SIZE = 25;

	/**
	 * User-scoped transient prefix for admin mail failure notices.
	 */
	public const MAIL_FAILURE_NOTICE_TRANSIENT_PREFIX = 'cliapwo_mail_failure_notice_';

	/**
	 * Register the event hooks.
	 *
	 * @return void
	 */
	public function register()
	{
		add_action('init', array($this, 'register_post_type'));
		add_filter('manage_' . self::POST_TYPE . '_posts_columns', array($this, 'filter_event_columns'));
		add_action('manage_' . self::POST_TYPE . '_posts_custom_column', array($this, 'render_event_column'), 10, 2);
		add_filter('post_row_actions', array($this, 'filter_event_row_actions'), 10, 2);
		add_filter('bulk_actions-edit-' . self::POST_TYPE, array($this, 'filter_event_bulk_actions'));
		add_action('cliapwo_request_created', array($this, 'handle_request_created'), 10, 2);
		add_action('cliapwo_update_created', array($this, 'handle_update_created'), 10, 2);
		add_action('cliapwo_file_uploaded', array($this, 'handle_file_uploaded'), 10, 3);
		add_action('admin_init', array($this, 'maybe_backfill_request_histories'));
		add_action('admin_notices', array($this, 'render_mail_failure_notice'));
	}

	/**
	 * Register the private event log post type.
	 *
	 * @return void
	 */
	public function register_post_type()
	{
		register_post_type(
			self::POST_TYPE,
			array(
				'labels'              => array(
					'name'          => __('Event Log', 'signoffflow-client-approval-workflow'),
					'singular_name' => __('Event', 'signoffflow-client-approval-workflow'),
					'menu_name'     => __('Event Log', 'signoffflow-client-approval-workflow'),
					'view_item'     => __('View Event', 'signoffflow-client-approval-workflow'),
					'search_items'  => __('Search Events', 'signoffflow-client-approval-workflow'),
					'not_found'     => __('No events found.', 'signoffflow-client-approval-workflow'),
				),
				'public'              => false,
				'show_ui'             => true,
				'show_in_menu'        => Settings::PAGE_SLUG,
				'show_in_rest'        => false,
				'has_archive'         => false,
				'exclude_from_search' => true,
				'publicly_queryable'  => false,
				'supports'            => array('title', 'editor'),
				'capability_type'     => 'post',
				'capabilities'        => array(
					'edit_post'              => 'do_not_allow',
					'read_post'              => 'cliapwo_manage_portal',
					'delete_post'            => 'do_not_allow',
					'edit_posts'             => 'cliapwo_manage_portal',
					'edit_others_posts'      => 'do_not_allow',
					'publish_posts'          => 'do_not_allow',
					'read_private_posts'     => 'cliapwo_manage_portal',
					'delete_posts'           => 'do_not_allow',
					'delete_private_posts'   => 'do_not_allow',
					'delete_published_posts' => 'do_not_allow',
					'delete_others_posts'    => 'do_not_allow',
					'edit_private_posts'     => 'do_not_allow',
					'edit_published_posts'   => 'do_not_allow',
					'create_posts'           => 'do_not_allow',
				),
				'map_meta_cap'        => false,
			)
		);
	}

	/**
	 * Log and notify for new client updates.
	 *
	 * @param int $update_id Update post ID.
	 * @param int $client_id Client post ID.
	 * @return void
	 */
	public function handle_update_created($update_id, $client_id)
	{
		$update_id = absint($update_id);
		$client_id = absint($client_id);
		$update    = get_post($update_id);
		$client    = get_post($client_id);

		if (! $update instanceof \WP_Post || Updates::POST_TYPE !== $update->post_type) {
			return;
		}

		if (! $client instanceof \WP_Post || Clients::POST_TYPE !== $client->post_type) {
			return;
		}

		$title = sprintf(
			/* translators: %s: update title */
			__('Update posted: %s', 'signoffflow-client-approval-workflow'),
			$update->post_title
		);

		$details = sprintf(
			/* translators: 1: client name, 2: update title */
			__("Client: %1\$s\nUpdate: %2\$s", 'signoffflow-client-approval-workflow'),
			$client->post_title,
			$update->post_title
		);

		$this->create_event_entry($title, $details, 'update_created', $client_id, $update_id);

		if (! $this->should_send_notification('notify_updates')) {
			return;
		}

		$portal_url = $this->get_portal_url();
		$subject    = sprintf(
			/* translators: 1: site name, 2: client name */
			__('[%1$s] New portal update for %2$s', 'signoffflow-client-approval-workflow'),
			wp_specialchars_decode(get_bloginfo('name'), ENT_QUOTES),
			$client->post_title
		);
		$message    = implode(
			"\n\n",
			array(
				__('A new update has been posted in your client-approval-workflow portal.', 'signoffflow-client-approval-workflow'),
				sprintf(
					/* translators: %s: client name */
					__('Client: %s', 'signoffflow-client-approval-workflow'),
					$client->post_title
				),
				sprintf(
					/* translators: %s: update title */
					__('Update: %s', 'signoffflow-client-approval-workflow'),
					$update->post_title
				),
				sprintf(
					/* translators: %s: portal URL */
					__('Portal link: %s', 'signoffflow-client-approval-workflow'),
					$portal_url
				),
			)
		);

		$this->send_email_to_client_users(
			$client_id,
			$subject,
			$message,
			$update_id,
			sprintf(
				/* translators: %s: update title */
				__('update "%s"', 'signoffflow-client-approval-workflow'),
				$update->post_title
			)
		);
	}

	/**
	 * Log and notify for new client requests.
	 *
	 * @param int $request_id Request post ID.
	 * @param int $client_id  Client post ID.
	 * @return void
	 */
	public function handle_request_created($request_id, $client_id)
	{
		$request_id = absint($request_id);
		$client_id  = absint($client_id);
		$request    = get_post($request_id);
		$client     = get_post($client_id);

		if (! $request instanceof \WP_Post || Requests::POST_TYPE !== $request->post_type) {
			return;
		}

		if (! $client instanceof \WP_Post || Clients::POST_TYPE !== $client->post_type) {
			return;
		}

		$title = sprintf(
			/* translators: %s: request title */
			__('Request created: %s', 'signoffflow-client-approval-workflow'),
			$request->post_title
		);

		$details = sprintf(
			/* translators: 1: client name, 2: request title */
			__("Client: %1\$s\nRequest: %2\$s", 'signoffflow-client-approval-workflow'),
			$client->post_title,
			$request->post_title
		);

		$actor_id   = get_current_user_id();
		$actor_name = self::get_actor_name($actor_id, __('Staff user', 'signoffflow-client-approval-workflow'));

		self::create_event_entry(
			$title,
			$details,
			self::TYPE_REQUEST_CREATED,
			$client_id,
			$request_id,
			array(
				self::ACTOR_ID_META_KEY       => $actor_id,
				self::ACTOR_NAME_META_KEY     => $actor_name,
				self::ACTOR_TYPE_META_KEY     => self::ACTOR_TYPE_STAFF,
				self::NEW_STATUS_META_KEY     => Requests::STATUS_OPEN,
			)
		);

		if (! $this->should_send_notification('notify_requests')) {
			return;
		}

		$portal_url = $this->get_portal_url();
		$subject    = sprintf(
			/* translators: 1: site name, 2: client name */
			__('[%1$s] New request for %2$s', 'signoffflow-client-approval-workflow'),
			wp_specialchars_decode(get_bloginfo('name'), ENT_QUOTES),
			$client->post_title
		);
		$message    = implode(
			"\n\n",
			array(
				__('A new request has been added to your client-approval-workflow portal.', 'signoffflow-client-approval-workflow'),
				sprintf(
					/* translators: %s: client name */
					__('Client: %s', 'signoffflow-client-approval-workflow'),
					$client->post_title
				),
				sprintf(
					/* translators: %s: request title */
					__('Request: %s', 'signoffflow-client-approval-workflow'),
					$request->post_title
				),
				sprintf(
					/* translators: %s: portal URL */
					__('Portal link: %s', 'signoffflow-client-approval-workflow'),
					$portal_url
				),
			)
		);

		$this->send_email_to_client_users(
			$client_id,
			$subject,
			$message,
			$request_id,
			sprintf(
				/* translators: %s: request title */
				__('request "%s"', 'signoffflow-client-approval-workflow'),
				$request->post_title
			)
		);
	}

	/**
	 * Log and notify for uploaded client files.
	 *
	 * @param int    $file_post_id      File post ID.
	 * @param int    $client_id         Client post ID.
	 * @param string $stored_file_path  Stored relative path.
	 * @return void
	 */
	public function handle_file_uploaded($file_post_id, $client_id, $stored_file_path)
	{
		$file_post_id     = absint($file_post_id);
		$client_id        = absint($client_id);
		$stored_file_path = ltrim(wp_normalize_path((string) $stored_file_path), '/');
		$file_post        = get_post($file_post_id);
		$client           = get_post($client_id);

		if (! $file_post instanceof \WP_Post || Files::POST_TYPE !== $file_post->post_type) {
			return;
		}

		if (! $client instanceof \WP_Post || Clients::POST_TYPE !== $client->post_type) {
			return;
		}

		$file_name = (string) get_post_meta($file_post_id, Files::ORIGINAL_FILENAME_META_KEY, true);

		if ('' === $file_name && '' !== $stored_file_path) {
			$file_name = basename($stored_file_path);
		}

		if ('' === $file_name) {
			$file_name = $file_post->post_title;
		}

		$title = sprintf(
			/* translators: %s: file name */
			__('File uploaded: %s', 'signoffflow-client-approval-workflow'),
			$file_name
		);

		$details = sprintf(
			/* translators: 1: client name, 2: file name */
			__("Client: %1\$s\nFile: %2\$s", 'signoffflow-client-approval-workflow'),
			$client->post_title,
			$file_name
		);

		$this->create_event_entry($title, $details, 'file_uploaded', $client_id, $file_post_id);

		if (! $this->should_send_notification('notify_files')) {
			return;
		}

		$portal_url = $this->get_portal_url();
		$subject    = sprintf(
			/* translators: 1: site name, 2: client name */
			__('[%1$s] New file available for %2$s', 'signoffflow-client-approval-workflow'),
			wp_specialchars_decode(get_bloginfo('name'), ENT_QUOTES),
			$client->post_title
		);
		$message    = implode(
			"\n\n",
			array(
				__('A new file has been uploaded to your client-approval-workflow portal.', 'signoffflow-client-approval-workflow'),
				sprintf(
					/* translators: %s: client name */
					__('Client: %s', 'signoffflow-client-approval-workflow'),
					$client->post_title
				),
				sprintf(
					/* translators: %s: file name */
					__('File: %s', 'signoffflow-client-approval-workflow'),
					$file_name
				),
				sprintf(
					/* translators: %s: portal URL */
					__('Portal link: %s', 'signoffflow-client-approval-workflow'),
					$portal_url
				),
			)
		);

		$this->send_email_to_client_users(
			$client_id,
			$subject,
			$message,
			$file_post_id,
			sprintf(
				/* translators: %s: file name */
				__('file "%s"', 'signoffflow-client-approval-workflow'),
				$file_name
			)
		);
	}

	/**
	 * Filter event log columns.
	 *
	 * @param array<string, string> $columns Existing columns.
	 * @return array<string, string>
	 */
	public function filter_event_columns($columns)
	{
		$columns['cliapwo_event_type']   = __('Type', 'signoffflow-client-approval-workflow');
		$columns['cliapwo_event_client'] = __('Client', 'signoffflow-client-approval-workflow');
		$columns['cliapwo_event_object'] = __('Related item', 'signoffflow-client-approval-workflow');

		return $columns;
	}

	/**
	 * Render custom event log columns.
	 *
	 * @param string $column  Column name.
	 * @param int    $post_id Event post ID.
	 * @return void
	 */
	public function render_event_column($column, $post_id)
	{
		if ('cliapwo_event_type' === $column) {
			$event_type = (string) get_post_meta($post_id, self::TYPE_META_KEY, true);
			echo esc_html($this->get_event_type_label($event_type));
			return;
		}

		if ('cliapwo_event_client' === $column) {
			$client = get_post(absint(get_post_meta($post_id, self::CLIENT_META_KEY, true)));
			echo $client instanceof \WP_Post ? esc_html($client->post_title) : esc_html__('Unknown', 'signoffflow-client-approval-workflow');
			return;
		}

		if ('cliapwo_event_object' !== $column) {
			return;
		}

		$object = get_post(absint(get_post_meta($post_id, self::OBJECT_ID_META_KEY, true)));
		echo $object instanceof \WP_Post ? esc_html($object->post_title) : esc_html__('Unknown', 'signoffflow-client-approval-workflow');
	}

	/**
	 * Remove row actions from the Event Log list table.
	 *
	 * @param array<string, string> $actions Existing row actions.
	 * @param \WP_Post              $post    Current post object.
	 * @return array<string, string>
	 */
	public function filter_event_row_actions($actions, $post)
	{
		if (! $post instanceof \WP_Post || self::POST_TYPE !== $post->post_type) {
			return $actions;
		}

		return array();
	}

	/**
	 * Remove bulk actions from the Event Log list table.
	 *
	 * @param array<string, string> $actions Existing bulk actions.
	 * @return array<string, string>
	 */
	public function filter_event_bulk_actions($actions)
	{
		unset($actions);

		return array();
	}

	/**
	 * Render the one-time admin notice for a mail delivery failure.
	 *
	 * @return void
	 */
	public function render_mail_failure_notice()
	{
		if (! current_user_can('cliapwo_manage_portal')) {
			return;
		}

		$screen = function_exists('get_current_screen') ? get_current_screen() : null;

		if ($screen instanceof \WP_Screen) {
			$allowed_post_types = array(
				Requests::POST_TYPE,
				Updates::POST_TYPE,
				Files::POST_TYPE,
				self::POST_TYPE,
			);

			if (! in_array((string) $screen->post_type, $allowed_post_types, true) && 'toplevel_page_' . Settings::PAGE_SLUG !== (string) $screen->id) {
				return;
			}
		}

		$user_id = get_current_user_id();

		if ($user_id <= 0) {
			return;
		}

		$notice = get_transient(self::MAIL_FAILURE_NOTICE_TRANSIENT_PREFIX . $user_id);

		if (! is_array($notice) || empty($notice['message'])) {
			return;
		}

		delete_transient(self::MAIL_FAILURE_NOTICE_TRANSIENT_PREFIX . $user_id);
		?>
		<div class="notice notice-warning is-dismissible">
			<p><?php echo esc_html((string) $notice['message']); ?></p>
		</div>
		<?php
	}

	/**
	 * Backfill reliable latest-response metadata in small admin-side batches.
	 *
	 * @return void
	 */
	public function maybe_backfill_request_histories()
	{
		if (! current_user_can('cliapwo_manage_portal')) {
			return;
		}

		$current_version = (string) get_option(self::DATA_VERSION_OPTION, '');

		if ('' !== $current_version && version_compare($current_version, self::DATA_VERSION, '>=')) {
			return;
		}

		$request_ids = get_posts(
			array(
				'post_type'              => Requests::POST_TYPE,
				'post_status'            => 'any',
				'posts_per_page'         => self::BACKFILL_BATCH_SIZE,
				'fields'                 => 'ids',
				'orderby'                => 'ID',
				'order'                  => 'ASC',
				'no_found_rows'          => true,
				'update_post_meta_cache' => true,
				'update_post_term_cache' => false,
				// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- bounded one-time migration locates legacy response metadata that has not been processed.
				'meta_query'             => array(
					'relation' => 'AND',
					array(
						'key'     => Requests::RESPONSE_STATUS_META_KEY,
						'compare' => 'EXISTS',
					),
					array(
						'relation' => 'OR',
						array(
							'key'     => Requests::HISTORY_BACKFILL_META_KEY,
							'compare' => 'NOT EXISTS',
						),
						array(
							'key'     => Requests::HISTORY_BACKFILL_META_KEY,
							'value'   => 'processing:',
							'compare' => 'LIKE',
						),
					),
				),
			)
		);

		$all_succeeded = true;

		foreach ($request_ids as $request_id) {
			if (! self::backfill_request_history_if_needed((int) $request_id)) {
				$all_succeeded = false;
			}
		}

		if ($all_succeeded && count($request_ids) < self::BACKFILL_BATCH_SIZE) {
			update_option(self::DATA_VERSION_OPTION, self::DATA_VERSION, false);
		}
	}

	/**
	 * Preserve one reliable legacy latest response as an immutable event.
	 *
	 * @param int $request_id Request post ID.
	 * @return bool Whether the request is safe to continue processing.
	 */
	public static function backfill_request_history_if_needed($request_id)
	{
		$request_id = absint($request_id);
		$request    = get_post($request_id);

		if (! $request instanceof \WP_Post || Requests::POST_TYPE !== $request->post_type) {
			return false;
		}

		$backfill_marker = (string) get_post_meta($request_id, Requests::HISTORY_BACKFILL_META_KEY, true);

		if (in_array($backfill_marker, array('complete', 'skipped'), true)) {
			return true;
		}

		if (0 === strpos($backfill_marker, 'processing:')) {
			$lock_timestamp = absint(substr($backfill_marker, strlen('processing:')));

			if ($lock_timestamp > 0 && (time() - $lock_timestamp) < (5 * MINUTE_IN_SECONDS)) {
				return false;
			}

			delete_post_meta($request_id, Requests::HISTORY_BACKFILL_META_KEY, $backfill_marker);
		}

		$status       = Requests::get_response_status_for_request($request_id);
		$responded_at = Requests::get_response_timestamp_for_request($request_id);
		$client_id    = Requests::get_client_id_for_request($request_id);

		if ('' === $status || $responded_at <= 0 || $client_id <= 0) {
			update_post_meta($request_id, Requests::HISTORY_BACKFILL_META_KEY, 'skipped');
			return true;
		}

		$lock_value = 'processing:' . time();

		if (! add_post_meta($request_id, Requests::HISTORY_BACKFILL_META_KEY, $lock_value, true)) {
			$backfill_marker = (string) get_post_meta($request_id, Requests::HISTORY_BACKFILL_META_KEY, true);

			return in_array($backfill_marker, array('complete', 'skipped'), true);
		}

		$actor_id   = Requests::get_responder_id_for_request($request_id);
		$actor_name = self::get_actor_name($actor_id, __('Client user', 'signoffflow-client-approval-workflow'));
		$event_client_id = $actor_id > 0 && get_userdata($actor_id) instanceof \WP_User && Clients::user_can_view_client($client_id, $actor_id) ? $client_id : 0;
		$event_id   = self::record_request_transition(
			$request_id,
			array(
				'client_id'       => $event_client_id,
				'event_type'      => self::TYPE_REQUEST_RESPONSE,
				'actor_id'        => $actor_id,
				'actor_name'      => $actor_name,
				'actor_type'      => self::ACTOR_TYPE_CLIENT,
				'previous_status' => Requests::STATUS_OPEN,
				'new_status'      => $status,
				'response_note'   => Requests::get_response_note_for_request($request_id),
				'occurred_at'     => $responded_at,
				'is_backfill'     => true,
			)
		);

		if ($event_id <= 0) {
			delete_post_meta($request_id, Requests::HISTORY_BACKFILL_META_KEY, $lock_value);
			return false;
		}

		update_post_meta($request_id, Requests::RESPONSE_CLIENT_META_KEY, $event_client_id);
		update_post_meta($request_id, Requests::HISTORY_BACKFILL_META_KEY, 'complete');

		return true;
	}

	/**
	 * Record a structured request transition event.
	 *
	 * @param int                  $request_id Request post ID.
	 * @param array<string, mixed> $transition Sanitized transition context.
	 * @return int Event post ID, or zero on failure.
	 */
	public static function record_request_transition($request_id, array $transition)
	{
		$request_id = absint($request_id);
		$request    = get_post($request_id);

		if (! $request instanceof \WP_Post || Requests::POST_TYPE !== $request->post_type) {
			return 0;
		}

		$allowed_types = array(
			self::TYPE_REQUEST_RESPONSE,
			self::TYPE_REQUEST_REOPENED,
			self::TYPE_REQUEST_STATUS_CHANGED,
		);
		$event_type = isset($transition['event_type']) ? sanitize_key((string) $transition['event_type']) : '';

		if (! in_array($event_type, $allowed_types, true)) {
			return 0;
		}

		$client_id       = isset($transition['client_id']) ? absint($transition['client_id']) : Requests::get_client_id_for_request($request_id);
		$actor_id        = isset($transition['actor_id']) ? absint($transition['actor_id']) : 0;
		$actor_type      = isset($transition['actor_type']) ? sanitize_key((string) $transition['actor_type']) : self::ACTOR_TYPE_STAFF;
		$actor_type      = self::ACTOR_TYPE_CLIENT === $actor_type ? self::ACTOR_TYPE_CLIENT : self::ACTOR_TYPE_STAFF;
		$actor_fallback  = self::ACTOR_TYPE_CLIENT === $actor_type
			? __('Client user', 'signoffflow-client-approval-workflow')
			: __('Staff user', 'signoffflow-client-approval-workflow');
		$actor_name      = isset($transition['actor_name']) ? sanitize_text_field((string) $transition['actor_name']) : '';
		$actor_name      = '' !== $actor_name ? $actor_name : self::get_actor_name($actor_id, $actor_fallback);
		$previous_status = isset($transition['previous_status']) ? self::normalize_request_status((string) $transition['previous_status']) : Requests::STATUS_OPEN;
		$new_status      = isset($transition['new_status']) ? self::normalize_request_status((string) $transition['new_status']) : Requests::STATUS_OPEN;
		$response_note   = isset($transition['response_note']) ? trim(sanitize_textarea_field((string) $transition['response_note'])) : '';
		$occurred_at     = isset($transition['occurred_at']) ? absint($transition['occurred_at']) : time();
		$is_backfill     = ! empty($transition['is_backfill']);

		if ($occurred_at <= 0) {
			return 0;
		}

		if (self::TYPE_REQUEST_RESPONSE === $event_type) {
			$title = sprintf(
				/* translators: %s: request title */
				__('Client response: %s', 'signoffflow-client-approval-workflow'),
				$request->post_title
			);
		} elseif (self::TYPE_REQUEST_REOPENED === $event_type) {
			$title = sprintf(
				/* translators: %s: request title */
				__('Request reopened: %s', 'signoffflow-client-approval-workflow'),
				$request->post_title
			);
		} else {
			$title = sprintf(
				/* translators: %s: request title */
				__('Request status changed: %s', 'signoffflow-client-approval-workflow'),
				$request->post_title
			);
		}

		$content_lines = array(
			sprintf(
				/* translators: 1: previous request status, 2: new request status */
				__('Status: %1$s to %2$s', 'signoffflow-client-approval-workflow'),
				Requests::get_status_label($previous_status),
				Requests::get_status_label($new_status)
			),
			sprintf(
				/* translators: %s: actor display name */
				__('Actor: %s', 'signoffflow-client-approval-workflow'),
				$actor_name
			),
		);

		if ('' !== $response_note) {
			$content_lines[] = sprintf(
				/* translators: %s: client response note */
				__('Response note: %s', 'signoffflow-client-approval-workflow'),
				$response_note
			);
		}

		$event_id = self::create_event_entry(
			$title,
			implode("\n", $content_lines),
			$event_type,
			$client_id,
			$request_id,
			array(
				self::ACTOR_ID_META_KEY       => $actor_id,
				self::ACTOR_NAME_META_KEY     => $actor_name,
				self::ACTOR_TYPE_META_KEY     => $actor_type,
				self::PREVIOUS_STATUS_META_KEY => $previous_status,
				self::NEW_STATUS_META_KEY     => $new_status,
				self::RESPONSE_NOTE_META_KEY  => $response_note,
				self::IS_BACKFILL_META_KEY    => $is_backfill ? '1' : '0',
			),
			$occurred_at,
			$actor_id
		);

		if ($event_id <= 0) {
			return 0;
		}

		return $event_id;
	}

	/**
	 * Get lifecycle histories for multiple requests in one query.
	 *
	 * @param array<int, int> $request_ids Request post IDs.
	 * @param int             $client_id   Optional client ID used to constrain portal history.
	 * @return array<int, array<int, \WP_Post>> Histories keyed by request ID.
	 */
	public static function get_request_histories(array $request_ids, $client_id = 0)
	{
		$request_ids = array_values(array_unique(array_filter(array_map('absint', $request_ids))));
		$histories   = array_fill_keys($request_ids, array());

		if (empty($request_ids)) {
			return $histories;
		}

		$meta_query = array(
			'relation' => 'AND',
			array(
				'key'     => self::OBJECT_ID_META_KEY,
				'value'   => $request_ids,
				'compare' => 'IN',
				'type'    => 'NUMERIC',
			),
			array(
				'key'     => self::TYPE_META_KEY,
				'value'   => self::get_request_lifecycle_event_types(),
				'compare' => 'IN',
			),
		);

		$client_id = absint($client_id);

		if ($client_id > 0) {
			$meta_query[] = array(
				'key'   => self::CLIENT_META_KEY,
				'value' => $client_id,
			);
		}

		$events = get_posts(
			array(
				'post_type'              => self::POST_TYPE,
				'post_status'            => 'publish',
				'posts_per_page'         => -1,
				'orderby'                => array(
					'date' => 'ASC',
					'ID'   => 'ASC',
				),
				'no_found_rows'          => true,
				'update_post_meta_cache' => true,
				'update_post_term_cache' => false,
				// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- one batched query loads immutable histories for already-bounded request IDs.
				'meta_query'             => $meta_query,
			)
		);

		foreach ($events as $event) {
			if (! $event instanceof \WP_Post) {
				continue;
			}

			$request_id = absint(get_post_meta($event->ID, self::OBJECT_ID_META_KEY, true));

			if (isset($histories[$request_id])) {
				$histories[$request_id][] = $event;
			}
		}

		return $histories;
	}

	/**
	 * Build escaped-at-output view data for one request event.
	 *
	 * @param \WP_Post $event       Event post.
	 * @param bool     $client_view Whether data is intended for the client portal.
	 * @return array<string, mixed>|null Event view data, or null for unsupported events.
	 */
	public static function get_request_event_view_data($event, $client_view = false)
	{
		if (! $event instanceof \WP_Post || self::POST_TYPE !== $event->post_type) {
			return null;
		}

		$event_type = (string) get_post_meta($event->ID, self::TYPE_META_KEY, true);

		if (! in_array($event_type, self::get_request_lifecycle_event_types(), true)) {
			return null;
		}

		$actor_id   = absint(get_post_meta($event->ID, self::ACTOR_ID_META_KEY, true));
		$actor_name = sanitize_text_field((string) get_post_meta($event->ID, self::ACTOR_NAME_META_KEY, true));
		$actor_type = sanitize_key((string) get_post_meta($event->ID, self::ACTOR_TYPE_META_KEY, true));
		$actor_type = self::ACTOR_TYPE_CLIENT === $actor_type ? self::ACTOR_TYPE_CLIENT : self::ACTOR_TYPE_STAFF;

		if ('' === $actor_name) {
			$actor_name = self::get_actor_name(
				$actor_id > 0 ? $actor_id : (int) $event->post_author,
				self::ACTOR_TYPE_CLIENT === $actor_type
					? __('Client user', 'signoffflow-client-approval-workflow')
					: __('Unknown user', 'signoffflow-client-approval-workflow')
			);
		}

		if ($client_view && self::ACTOR_TYPE_STAFF === $actor_type) {
			$actor_name = __('Your team', 'signoffflow-client-approval-workflow');
		}

		if (self::TYPE_REQUEST_CREATED === $event_type) {
			$label = __('Request created', 'signoffflow-client-approval-workflow');
		} elseif (self::TYPE_REQUEST_RESPONSE === $event_type) {
			$label = __('Client responded', 'signoffflow-client-approval-workflow');
		} elseif (self::TYPE_REQUEST_REOPENED === $event_type) {
			$label = __('Request reopened', 'signoffflow-client-approval-workflow');
		} else {
			$label = __('Status changed', 'signoffflow-client-approval-workflow');
		}

		return array(
			'type'            => $event_type,
			'label'           => $label,
			'actor_name'      => $actor_name,
			'actor_type'      => $actor_type,
			'previous_status' => self::normalize_request_status((string) get_post_meta($event->ID, self::PREVIOUS_STATUS_META_KEY, true)),
			'new_status'      => self::normalize_request_status((string) get_post_meta($event->ID, self::NEW_STATUS_META_KEY, true)),
			'response_note'   => (string) get_post_meta($event->ID, self::RESPONSE_NOTE_META_KEY, true),
			'timestamp'       => (int) get_post_time('U', true, $event),
		);
	}

	/**
	 * Create an event log entry.
	 *
	 * @param string $title     Event title.
	 * @param string $content   Event details.
	 * @param string $type      Event type.
	 * @param int    $client_id Client post ID.
	 * @param int                  $object_id  Related object post ID.
	 * @param array<string, mixed> $meta       Additional sanitized event metadata.
	 * @param int                  $occurred_at Optional UTC Unix timestamp.
	 * @param int                  $actor_id    Optional event author user ID.
	 * @return int Event post ID, or zero on failure.
	 */
	private static function create_event_entry($title, $content, $type, $client_id, $object_id, array $meta = array(), $occurred_at = 0, $actor_id = 0)
	{
		$post_data = array(
			'post_type'    => self::POST_TYPE,
			'post_status'  => 'publish',
			'post_title'   => wp_strip_all_tags($title),
			'post_content' => $content,
		);

		$occurred_at = absint($occurred_at);

		if ($occurred_at > 0) {
			$post_date_gmt          = gmdate('Y-m-d H:i:s', $occurred_at);
			$post_data['post_date'] = get_date_from_gmt($post_date_gmt);
			$post_data['post_date_gmt'] = $post_date_gmt;
		}

		$actor_id = absint($actor_id);

		if ($actor_id > 0 && get_userdata($actor_id) instanceof \WP_User) {
			$post_data['post_author'] = $actor_id;
		}

		$event_id = wp_insert_post(
			$post_data,
			true
		);

		if (is_wp_error($event_id)) {
			return 0;
		}

		update_post_meta($event_id, self::TYPE_META_KEY, sanitize_key($type));
		update_post_meta($event_id, self::CLIENT_META_KEY, absint($client_id));
		update_post_meta($event_id, self::OBJECT_ID_META_KEY, absint($object_id));

		foreach ($meta as $meta_key => $meta_value) {
			if (! is_string($meta_key) || 0 !== strpos($meta_key, 'cliapwo_')) {
				continue;
			}

			update_post_meta($event_id, $meta_key, $meta_value);
		}

		return absint($event_id);
	}

	/**
	 * Get request lifecycle event types eligible for request timelines.
	 *
	 * @return array<int, string>
	 */
	private static function get_request_lifecycle_event_types()
	{
		return array(
			self::TYPE_REQUEST_CREATED,
			self::TYPE_REQUEST_RESPONSE,
			self::TYPE_REQUEST_REOPENED,
			self::TYPE_REQUEST_STATUS_CHANGED,
		);
	}

	/**
	 * Normalize a stored request status for event display and comparison.
	 *
	 * @param string $status Request status.
	 * @return string
	 */
	private static function normalize_request_status($status)
	{
		$status = sanitize_key((string) $status);

		if (Requests::STATUS_COMPLETE === $status) {
			return Requests::STATUS_APPROVED;
		}

		$allowed_statuses = array(
			Requests::STATUS_OPEN,
			Requests::STATUS_APPROVED,
			Requests::STATUS_CHANGES_REQUESTED,
			Requests::STATUS_REJECTED,
			Requests::STATUS_BLOCKED,
		);

		return in_array($status, $allowed_statuses, true) ? $status : Requests::STATUS_OPEN;
	}

	/**
	 * Snapshot an actor display name with a safe fallback.
	 *
	 * @param int    $actor_id Actor WordPress user ID.
	 * @param string $fallback Fallback label.
	 * @return string
	 */
	private static function get_actor_name($actor_id, $fallback)
	{
		$actor = get_userdata(absint($actor_id));

		if ($actor instanceof \WP_User && '' !== trim((string) $actor->display_name)) {
			return sanitize_text_field((string) $actor->display_name);
		}

		return sanitize_text_field((string) $fallback);
	}

	/**
	 * Send a plain-text email to all assigned client users.
	 *
	 * @param int    $client_id     Client post ID.
	 * @param string $subject       Email subject.
	 * @param string $message       Email message.
	 * @param int    $object_id     Related object post ID.
	 * @param string $context_label Human-readable object label.
	 * @return void
	 */
	private function send_email_to_client_users($client_id, $subject, $message, $object_id, $context_label)
	{
		$emails = Clients::get_assigned_user_emails($client_id);

		if (empty($emails)) {
			return;
		}

		$mail_sent = wp_mail(
			$emails,
			wp_strip_all_tags($subject),
			$message,
			array('Content-Type: text/plain; charset=UTF-8')
		);

		$this->record_mail_attempt($client_id, $object_id, $context_label, $emails, $mail_sent);
	}

	/**
	 * Check whether a notification type is enabled.
	 *
	 * @param string $setting_key Notification setting key.
	 * @return bool
	 */
	private function should_send_notification($setting_key)
	{
		$settings = Settings::get_settings();

		return isset($settings[$setting_key]) && 1 === absint($settings[$setting_key]);
	}

	/**
	 * Get the configured portal URL.
	 *
	 * @return string
	 */
	private function get_portal_url()
	{
		$settings       = Settings::get_settings();
		$portal_page_id = isset($settings['portal_page_id']) ? absint($settings['portal_page_id']) : 0;

		if ($portal_page_id > 0) {
			$portal_page = get_post($portal_page_id);
			$portal_url  = $portal_page instanceof \WP_Post && 'page' === $portal_page->post_type ? get_permalink($portal_page_id) : '';

			if (is_string($portal_url) && '' !== $portal_url) {
				return $portal_url;
			}
		}

		return home_url('/');
	}

	/**
	 * Get a UI label for a stored event type.
	 *
	 * @param string $event_type Event type key.
	 * @return string
	 */
	private function get_event_type_label($event_type)
	{
		if ('email_attempt' === $event_type) {
			return __('Email attempt', 'signoffflow-client-approval-workflow');
		}

		if (self::TYPE_REQUEST_CREATED === $event_type) {
			return __('Request created', 'signoffflow-client-approval-workflow');
		}

		if (self::TYPE_REQUEST_RESPONSE === $event_type) {
			return __('Client response', 'signoffflow-client-approval-workflow');
		}

		if (self::TYPE_REQUEST_REOPENED === $event_type) {
			return __('Request reopened', 'signoffflow-client-approval-workflow');
		}

		if (self::TYPE_REQUEST_STATUS_CHANGED === $event_type) {
			return __('Request status changed', 'signoffflow-client-approval-workflow');
		}

		if ('file_uploaded' === $event_type) {
			return __('File uploaded', 'signoffflow-client-approval-workflow');
		}

		if ('update_created' === $event_type) {
			return __('Update created', 'signoffflow-client-approval-workflow');
		}

		return __('Event', 'signoffflow-client-approval-workflow');
	}

	/**
	 * Record a wp_mail() attempt in the event log and, on failure, queue an admin notice.
	 *
	 * @param int                $client_id     Client post ID.
	 * @param int                $object_id     Related object post ID.
	 * @param string             $context_label Human-readable object label.
	 * @param array<int, string> $emails        Recipient email addresses.
	 * @param bool               $mail_sent     Whether wp_mail() returned success.
	 * @return void
	 */
	private function record_mail_attempt($client_id, $object_id, $context_label, array $emails, $mail_sent)
	{
		$result_label = $mail_sent
			? __('accepted by wp_mail()', 'signoffflow-client-approval-workflow')
			: __('wp_mail() returned false', 'signoffflow-client-approval-workflow');
		$recipients   = implode(', ', $emails);
		$title        = sprintf(
			/* translators: %s: object label */
			__('Email attempt: %s', 'signoffflow-client-approval-workflow'),
			$context_label
		);
		$content      = implode(
			"\n",
			array(
				sprintf(
					/* translators: %s: object label */
					__('Context: %s', 'signoffflow-client-approval-workflow'),
					$context_label
				),
				sprintf(
					/* translators: %s: mail result */
					__('Result: %s', 'signoffflow-client-approval-workflow'),
					$result_label
				),
				sprintf(
					/* translators: %s: recipient list */
					__('Recipients: %s', 'signoffflow-client-approval-workflow'),
					$recipients
				),
			)
		);

		$this->create_event_entry($title, $content, 'email_attempt', $client_id, $object_id);

		if (! $mail_sent) {
			$this->set_mail_failure_notice();
		}
	}

	/**
	 * Store a one-time admin notice about a mail delivery failure for the current user.
	 *
	 * @return void
	 */
	private function set_mail_failure_notice()
	{
		$user_id = get_current_user_id();

		if ($user_id <= 0 || ! current_user_can('cliapwo_manage_portal')) {
			return;
		}

		$message = __(
			'Email delivery could not be confirmed. Check SignoffFlow > Event Log and verify your WordPress mail transport or SMTP configuration.',
			'signoffflow-client-approval-workflow'
		);

		set_transient(
			self::MAIL_FAILURE_NOTICE_TRANSIENT_PREFIX . $user_id,
			array(
				'message' => $message,
			),
			MINUTE_IN_SECONDS
		);
	}
}
