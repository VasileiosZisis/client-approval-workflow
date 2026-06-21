<?php

/**
 * Client requests and portal status actions.
 *
 * @package VzisisClientApprovalWorkflow
 */

namespace Vzisis\ClientApprovalWorkflow;

defined('ABSPATH') || exit;

/**
 * Handles client requests/tasks.
 */
class Requests
{
	/**
	 * Request post type slug.
	 */
	public const POST_TYPE = 'cliapwo_request';

	/**
	 * Linked client meta key.
	 */
	public const CLIENT_META_KEY = 'cliapwo_client_id';

	/**
	 * Request status meta key.
	 */
	public const STATUS_META_KEY = 'cliapwo_request_status';

	/**
	 * Latest client response note meta key.
	 */
	public const RESPONSE_NOTE_META_KEY = 'cliapwo_request_response_note';

	/**
	 * Latest client response outcome meta key.
	 */
	public const RESPONSE_STATUS_META_KEY = 'cliapwo_request_response_status';

	/**
	 * Latest client responder user ID meta key.
	 */
	public const RESPONDED_BY_META_KEY = 'cliapwo_request_responded_by';

	/**
	 * Latest client response UTC timestamp meta key.
	 */
	public const RESPONDED_AT_META_KEY = 'cliapwo_request_responded_at';

	/**
	 * Maximum client response note length.
	 */
	public const RESPONSE_NOTE_MAX_LENGTH = 500;

	/**
	 * Open status value.
	 */
	public const STATUS_OPEN = 'open';

	/**
	 * Complete status value.
	 *
	 * @deprecated 1.1.0 Use STATUS_APPROVED for new writes.
	 */
	public const STATUS_COMPLETE = 'complete';

	/**
	 * Approved status value.
	 */
	public const STATUS_APPROVED = 'approved';

	/**
	 * Changes requested status value.
	 */
	public const STATUS_CHANGES_REQUESTED = 'changes_requested';

	/**
	 * Rejected status value.
	 */
	public const STATUS_REJECTED = 'rejected';

	/**
	 * Blocked status value.
	 */
	public const STATUS_BLOCKED = 'blocked';

	/**
	 * Save nonce action.
	 */
	public const SAVE_NONCE_ACTION = 'cliapwo_save_request_details';

	/**
	 * Save nonce field name.
	 */
	public const SAVE_NONCE_NAME = 'cliapwo_request_nonce';

	/**
	 * Portal status update action.
	 */
	public const STATUS_UPDATE_ACTION = 'cliapwo_update_request_status';

	/**
	 * Portal status update nonce field name.
	 */
	public const STATUS_UPDATE_NONCE_NAME = 'cliapwo_request_status_nonce';

	/**
	 * Client notification marker meta key.
	 */
	public const NOTIFIED_META_KEY = 'cliapwo_request_notified';

	/**
	 * Register module hooks.
	 *
	 * @return void
	 */
	public function register()
	{
		add_action('init', array($this, 'register_post_type'));
		add_action('add_meta_boxes', array($this, 'register_meta_boxes'));
		add_action('save_post_' . self::POST_TYPE, array($this, 'save_request_meta'), 10, 2);
		add_filter('manage_' . self::POST_TYPE . '_posts_columns', array($this, 'filter_request_columns'));
		add_action('manage_' . self::POST_TYPE . '_posts_custom_column', array($this, 'render_request_column'), 10, 2);
		add_action('admin_post_' . self::STATUS_UPDATE_ACTION, array($this, 'handle_status_update'));
		add_action('admin_post_nopriv_' . self::STATUS_UPDATE_ACTION, array($this, 'handle_unauthorized_status_update'));
	}

	/**
	 * Register the request post type.
	 *
	 * @return void
	 */
	public function register_post_type()
	{
		register_post_type(
			self::POST_TYPE,
			array(
				'labels'              => array(
					'name'          => __('Requests', 'signoffflow-client-approval-workflow'),
					'singular_name' => __('Request', 'signoffflow-client-approval-workflow'),
					'menu_name'     => __('Requests', 'signoffflow-client-approval-workflow'),
					'add_new_item'  => __('Add Request', 'signoffflow-client-approval-workflow'),
					'edit_item'     => __('Edit Request', 'signoffflow-client-approval-workflow'),
					'new_item'      => __('New Request', 'signoffflow-client-approval-workflow'),
					'view_item'     => __('View Request', 'signoffflow-client-approval-workflow'),
					'search_items'  => __('Search Requests', 'signoffflow-client-approval-workflow'),
					'not_found'     => __('No requests found.', 'signoffflow-client-approval-workflow'),
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
					'edit_post'              => 'cliapwo_manage_portal',
					'read_post'              => 'cliapwo_manage_portal',
					'delete_post'            => 'cliapwo_manage_portal',
					'edit_posts'             => 'cliapwo_manage_portal',
					'edit_others_posts'      => 'cliapwo_manage_portal',
					'publish_posts'          => 'cliapwo_manage_portal',
					'read_private_posts'     => 'cliapwo_manage_portal',
					'delete_posts'           => 'cliapwo_manage_portal',
					'delete_private_posts'   => 'cliapwo_manage_portal',
					'delete_published_posts' => 'cliapwo_manage_portal',
					'delete_others_posts'    => 'cliapwo_manage_portal',
					'edit_private_posts'     => 'cliapwo_manage_portal',
					'edit_published_posts'   => 'cliapwo_manage_portal',
					'create_posts'           => 'cliapwo_manage_portal',
				),
				'map_meta_cap'        => false,
			)
		);
	}

	/**
	 * Register request meta boxes.
	 *
	 * @return void
	 */
	public function register_meta_boxes()
	{
		add_meta_box(
			'cliapwo_request_details',
			__('Request Details', 'signoffflow-client-approval-workflow'),
			array($this, 'render_request_details_meta_box'),
			self::POST_TYPE,
			'side',
			'default'
		);
	}

	/**
	 * Render the request meta box.
	 *
	 * @param \WP_Post $post Current request post.
	 * @return void
	 */
	public function render_request_details_meta_box($post)
	{
		if (! $post instanceof \WP_Post) {
			return;
		}

		wp_nonce_field(self::SAVE_NONCE_ACTION, self::SAVE_NONCE_NAME);

		$client_id = self::get_client_id_for_request($post->ID);
		$status    = self::get_status_for_request($post->ID);
		$selected_status = self::normalize_status_for_ui($status);
		$clients   = get_posts(
			array(
				'post_type'              => Clients::POST_TYPE,
				'post_status'            => 'publish',
				'posts_per_page'         => -1,
				'orderby'                => 'title',
				'order'                  => 'ASC',
				'update_post_meta_cache' => false,
				'update_post_term_cache' => false,
			)
		);
		?>
		<p>
			<label for="cliapwo_request_client_id"><strong><?php esc_html_e('Client', 'signoffflow-client-approval-workflow'); ?></strong></label><br />
			<select
				class="widefat"
				id="cliapwo_request_client_id"
				name="cliapwo_request_client_id">
				<option value="0"><?php esc_html_e('Select a client', 'signoffflow-client-approval-workflow'); ?></option>
				<?php foreach ($clients as $client) : ?>
					<?php if (! $client instanceof \WP_Post) : ?>
						<?php continue; ?>
					<?php endif; ?>
					<option
						value="<?php echo esc_attr((string) $client->ID); ?>"
						<?php selected($client_id, $client->ID); ?>>
						<?php echo esc_html($client->post_title); ?>
					</option>
				<?php endforeach; ?>
			</select>
		</p>

		<p>
			<label for="cliapwo_request_status"><strong><?php esc_html_e('Status', 'signoffflow-client-approval-workflow'); ?></strong></label><br />
			<select
				class="widefat"
				id="cliapwo_request_status"
				name="cliapwo_request_status">
				<?php foreach (self::get_status_options() as $status_value => $status_label) : ?>
					<option
						value="<?php echo esc_attr($status_value); ?>"
						<?php selected($selected_status, $status_value); ?>>
						<?php echo esc_html($status_label); ?>
					</option>
				<?php endforeach; ?>
			</select>
		</p>
		<p class="description"><?php esc_html_e('Assigned portal users can choose an approval outcome from the portal. Staff can reopen resolved requests from the portal preview or override status here.', 'signoffflow-client-approval-workflow'); ?></p>
		<?php $this->render_client_response_summary($post->ID, $status); ?>
		<?php
	}

	/**
	 * Render the latest client response in the request meta box.
	 *
	 * @param int    $request_id    Request post ID.
	 * @param string $request_status Current request status.
	 * @return void
	 */
	private function render_client_response_summary($request_id, $request_status)
	{
		$response_status = self::get_response_status_for_request($request_id);

		if ('' === $response_status) {
			return;
		}

		$response_note = self::get_response_note_for_request($request_id);
		$responder_id  = self::get_responder_id_for_request($request_id);
		$responded_at  = self::get_response_timestamp_for_request($request_id);
		$responder     = $responder_id > 0 ? get_userdata($responder_id) : false;
		$heading       = self::STATUS_OPEN === $request_status
			? __('Previous client response', 'signoffflow-client-approval-workflow')
			: __('Latest client response', 'signoffflow-client-approval-workflow');
		?>
		<hr />
		<h4><?php echo esc_html($heading); ?></h4>
		<p>
			<strong><?php esc_html_e('Outcome:', 'signoffflow-client-approval-workflow'); ?></strong>
			<?php echo esc_html(self::get_status_label($response_status)); ?>
		</p>
		<?php if ('' !== $response_note) : ?>
			<p>
				<strong><?php esc_html_e('Note:', 'signoffflow-client-approval-workflow'); ?></strong><br />
				<?php echo nl2br(esc_html($response_note)); ?>
			</p>
		<?php endif; ?>
		<?php if ($responder instanceof \WP_User && $responded_at > 0) : ?>
			<p class="description">
				<?php
				printf(
					/* translators: 1: client user display name, 2: response date and time */
					esc_html__('Responded by %1$s on %2$s', 'signoffflow-client-approval-workflow'),
					esc_html($responder->display_name),
					esc_html(self::format_response_timestamp($responded_at))
				);
				?>
			</p>
		<?php endif; ?>
		<?php
	}

	/**
	 * Save request metadata.
	 *
	 * @param int      $post_id Request post ID.
	 * @param \WP_Post $post    Post object.
	 * @return void
	 */
	public function save_request_meta($post_id, $post)
	{
		if (! $post instanceof \WP_Post || self::POST_TYPE !== $post->post_type) {
			return;
		}

		if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
			return;
		}

		if (wp_is_post_revision($post_id)) {
			return;
		}

		if (! isset($_POST[self::SAVE_NONCE_NAME])) {
			return;
		}

		$nonce = sanitize_text_field(wp_unslash($_POST[self::SAVE_NONCE_NAME]));

		if (! wp_verify_nonce($nonce, self::SAVE_NONCE_ACTION)) {
			return;
		}

		if (! current_user_can('cliapwo_manage_portal')) {
			return;
		}

		$client_id = 0;

		if (isset($_POST['cliapwo_request_client_id'])) {
			$client_id = absint(wp_unslash($_POST['cliapwo_request_client_id']));
		}

		if ($client_id > 0) {
			$client = get_post($client_id);

			if (! $client instanceof \WP_Post || Clients::POST_TYPE !== $client->post_type) {
				$client_id = 0;
			}
		}

		if ($client_id > 0) {
			update_post_meta($post_id, self::CLIENT_META_KEY, $client_id);
		} else {
			delete_post_meta($post_id, self::CLIENT_META_KEY);
		}

		$status = self::STATUS_OPEN;

		if (isset($_POST['cliapwo_request_status'])) {
			$status = sanitize_key(wp_unslash($_POST['cliapwo_request_status']));
		}

		if (! in_array($status, self::get_allowed_statuses(), true)) {
			$status = self::STATUS_OPEN;
		}

		$status = self::normalize_status_for_storage($status);

		update_post_meta($post_id, self::STATUS_META_KEY, $status);
		$this->maybe_dispatch_created_event($post_id, $post, $client_id);
	}

	/**
	 * Filter request list columns.
	 *
	 * @param array<string, string> $columns Existing columns.
	 * @return array<string, string>
	 */
	public function filter_request_columns($columns)
	{
		$columns['cliapwo_request_client'] = __('Client', 'signoffflow-client-approval-workflow');
		$columns['cliapwo_request_status'] = __('Status', 'signoffflow-client-approval-workflow');

		return $columns;
	}

	/**
	 * Render custom request list columns.
	 *
	 * @param string $column  Column name.
	 * @param int    $post_id Request post ID.
	 * @return void
	 */
	public function render_request_column($column, $post_id)
	{
		if ('cliapwo_request_client' === $column) {
			$client = get_post(self::get_client_id_for_request($post_id));
			echo $client instanceof \WP_Post ? esc_html($client->post_title) : esc_html__('Unassigned', 'signoffflow-client-approval-workflow');
			return;
		}

		if ('cliapwo_request_status' !== $column) {
			return;
		}

		echo esc_html(self::get_status_label(self::get_status_for_request($post_id)));
	}

	/**
	 * Handle portal request status updates.
	 *
	 * @return void
	 */
	public function handle_status_update()
	{
		$nonce = '';

		if (isset($_POST[self::STATUS_UPDATE_NONCE_NAME])) {
			$nonce = sanitize_text_field(wp_unslash($_POST[self::STATUS_UPDATE_NONCE_NAME]));
		}

		if (! wp_verify_nonce($nonce, self::STATUS_UPDATE_ACTION)) {
			wp_die(
				esc_html__('Invalid request update.', 'signoffflow-client-approval-workflow'),
				esc_html__('Forbidden', 'signoffflow-client-approval-workflow'),
				array(
					'response' => 403,
				)
			);
		}

		if (! is_user_logged_in()) {
			wp_die(
				esc_html__('You must be logged in to update requests.', 'signoffflow-client-approval-workflow'),
				esc_html__('Forbidden', 'signoffflow-client-approval-workflow'),
				array(
					'response' => 403,
				)
			);
		}

		$is_manager     = current_user_can('cliapwo_manage_portal');
		$is_client_user = current_user_can('cliapwo_view_portal');

		if (! $is_manager && ! $is_client_user) {
			wp_die(
				esc_html__('You are not allowed to update requests.', 'signoffflow-client-approval-workflow'),
				esc_html__('Forbidden', 'signoffflow-client-approval-workflow'),
				array(
					'response' => 403,
				)
			);
		}

		$request_id    = 0;
		$status        = '';
		$response_note = '';

		if (isset($_POST['cliapwo_request_id'])) {
			$request_id = absint(wp_unslash($_POST['cliapwo_request_id']));
		}

		if (isset($_POST['cliapwo_request_status'])) {
			$status = sanitize_key(wp_unslash($_POST['cliapwo_request_status']));
		}

		if (isset($_POST['cliapwo_request_response_note'])) {
			$response_note = trim(sanitize_textarea_field(wp_unslash($_POST['cliapwo_request_response_note'])));
		}

		if (! in_array($status, self::get_allowed_statuses(), true)) {
			wp_die(
				esc_html__('Invalid request status.', 'signoffflow-client-approval-workflow'),
				esc_html__('Forbidden', 'signoffflow-client-approval-workflow'),
				array(
					'response' => 403,
				)
			);
		}

		$request = get_post($request_id);

		if (! $request instanceof \WP_Post || self::POST_TYPE !== $request->post_type || 'publish' !== $request->post_status) {
			wp_die(
				esc_html__('The requested task could not be found.', 'signoffflow-client-approval-workflow'),
				esc_html__('Not Found', 'signoffflow-client-approval-workflow'),
				array(
					'response' => 404,
				)
			);
		}

		$current_user_id = get_current_user_id();
		$client_id       = self::get_client_id_for_request($request_id);

		if ($is_manager) {
			update_post_meta($request_id, self::STATUS_META_KEY, self::normalize_status_for_storage($status));
			$this->redirect_back();
		}

		$current_status = self::get_status_for_request($request_id);

		if (
			! Clients::user_can_view_client($client_id, $current_user_id)
			|| self::STATUS_OPEN !== $current_status
			|| ! in_array($status, self::get_client_outcome_statuses(), true)
		) {
			wp_die(
				esc_html__('You are not allowed to update this request.', 'signoffflow-client-approval-workflow'),
				esc_html__('Forbidden', 'signoffflow-client-approval-workflow'),
				array(
					'response' => 403,
				)
			);
		}

		if (self::get_string_length($response_note) > self::RESPONSE_NOTE_MAX_LENGTH) {
			wp_die(
				esc_html__('Response notes must be 500 characters or fewer.', 'signoffflow-client-approval-workflow'),
				esc_html__('Invalid response note', 'signoffflow-client-approval-workflow'),
				array(
					'response' => 400,
				)
			);
		}

		if (self::response_note_is_required($status) && '' === $response_note) {
			wp_die(
				esc_html__('Add a response note when requesting changes, rejecting, or blocking a request.', 'signoffflow-client-approval-workflow'),
				esc_html__('Response note required', 'signoffflow-client-approval-workflow'),
				array(
					'response' => 400,
				)
			);
		}

		if ('' === $response_note) {
			delete_post_meta($request_id, self::RESPONSE_NOTE_META_KEY);
		} else {
			update_post_meta($request_id, self::RESPONSE_NOTE_META_KEY, $response_note);
		}

		update_post_meta($request_id, self::RESPONSE_STATUS_META_KEY, self::normalize_status_for_storage($status));
		update_post_meta($request_id, self::RESPONDED_BY_META_KEY, $current_user_id);
		update_post_meta($request_id, self::RESPONDED_AT_META_KEY, time());
		update_post_meta($request_id, self::STATUS_META_KEY, self::normalize_status_for_storage($status));
		$this->redirect_back();
	}

	/**
	 * Reject unauthenticated status updates.
	 *
	 * @return void
	 */
	public function handle_unauthorized_status_update()
	{
		wp_die(
			esc_html__('You must be logged in to update requests.', 'signoffflow-client-approval-workflow'),
			esc_html__('Forbidden', 'signoffflow-client-approval-workflow'),
			array(
				'response' => 403,
			)
		);
	}

	/**
	 * Get the client ID for a request.
	 *
	 * @param int $request_id Request post ID.
	 * @return int
	 */
	public static function get_client_id_for_request($request_id)
	{
		return absint(get_post_meta($request_id, self::CLIENT_META_KEY, true));
	}

	/**
	 * Get the current status for a request.
	 *
	 * @param int $request_id Request post ID.
	 * @return string
	 */
	public static function get_status_for_request($request_id)
	{
		$status = (string) get_post_meta($request_id, self::STATUS_META_KEY, true);

		return in_array($status, self::get_allowed_statuses(), true) ? $status : self::STATUS_OPEN;
	}

	/**
	 * Get the latest client response note for a request.
	 *
	 * @param int $request_id Request post ID.
	 * @return string
	 */
	public static function get_response_note_for_request($request_id)
	{
		return (string) get_post_meta($request_id, self::RESPONSE_NOTE_META_KEY, true);
	}

	/**
	 * Get the latest client response outcome for a request.
	 *
	 * @param int $request_id Request post ID.
	 * @return string
	 */
	public static function get_response_status_for_request($request_id)
	{
		$status = self::normalize_status_for_ui((string) get_post_meta($request_id, self::RESPONSE_STATUS_META_KEY, true));

		return in_array($status, self::get_client_outcome_statuses(), true) ? $status : '';
	}

	/**
	 * Get the latest client responder user ID for a request.
	 *
	 * @param int $request_id Request post ID.
	 * @return int
	 */
	public static function get_responder_id_for_request($request_id)
	{
		return absint(get_post_meta($request_id, self::RESPONDED_BY_META_KEY, true));
	}

	/**
	 * Get the latest client response UTC timestamp for a request.
	 *
	 * @param int $request_id Request post ID.
	 * @return int
	 */
	public static function get_response_timestamp_for_request($request_id)
	{
		return absint(get_post_meta($request_id, self::RESPONDED_AT_META_KEY, true));
	}

	/**
	 * Get a request query for a client.
	 *
	 * @param int   $client_id Client post ID.
	 * @param array $args      Optional query overrides.
	 * @return \WP_Query
	 */
	public static function get_requests_query_for_client($client_id, array $args = array())
	{
		$client_id = absint($client_id);

		if ($client_id <= 0) {
			return new \WP_Query(
				array(
					'post_type'      => self::POST_TYPE,
					'post__in'       => array(0),
					'posts_per_page' => 0,
				)
			);
		}

		$defaults = array(
			'post_type'              => self::POST_TYPE,
			'post_status'            => 'publish',
			'posts_per_page'         => 50,
			'orderby'                => 'date',
			'order'                  => 'DESC',
			'no_found_rows'          => true,
			'update_post_meta_cache' => true,
			'update_post_term_cache' => false,
			// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- requests are private plugin-managed records filtered by client linkage stored in post meta.
			'meta_query'             => array(
				array(
					'key'   => self::CLIENT_META_KEY,
					'value' => $client_id,
				),
			),
		);

		return new \WP_Query(wp_parse_args($args, $defaults));
	}

	/**
	 * Get the number of open requests for a client.
	 *
	 * @param int $client_id Client post ID.
	 * @return int
	 */
	public static function get_open_request_count_for_client($client_id)
	{
		$query = self::get_requests_query_for_client(
			$client_id,
			array(
				'posts_per_page' => -1,
				'fields'         => 'ids',
				// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- open request counts require filtering private request records by client and request status stored in post meta.
				'meta_query'     => array(
					array(
						'key'   => self::CLIENT_META_KEY,
						'value' => absint($client_id),
					),
					array(
						'key'   => self::STATUS_META_KEY,
						'value' => self::STATUS_OPEN,
					),
				),
			)
		);

		return $query instanceof \WP_Query ? count($query->posts) : 0;
	}

	/**
	 * Get the UI label for a request status.
	 *
	 * @param string $status Request status.
	 * @return string
	 */
	public static function get_status_label($status)
	{
		$status = self::normalize_status_for_ui((string) $status);

		if (self::STATUS_APPROVED === $status) {
			return __('Approved', 'signoffflow-client-approval-workflow');
		}

		if (self::STATUS_CHANGES_REQUESTED === $status) {
			return __('Changes requested', 'signoffflow-client-approval-workflow');
		}

		if (self::STATUS_REJECTED === $status) {
			return __('Rejected', 'signoffflow-client-approval-workflow');
		}

		if (self::STATUS_BLOCKED === $status) {
			return __('Blocked', 'signoffflow-client-approval-workflow');
		}

		return __('Open', 'signoffflow-client-approval-workflow');
	}

	/**
	 * Determine whether a status is resolved.
	 *
	 * @param string $status Request status.
	 * @return bool
	 */
	public static function is_resolved_status($status)
	{
		return in_array(
			(string) $status,
			array(
				self::STATUS_COMPLETE,
				self::STATUS_APPROVED,
				self::STATUS_CHANGES_REQUESTED,
				self::STATUS_REJECTED,
				self::STATUS_BLOCKED,
			),
			true
		);
	}

	/**
	 * Get the allowed status values.
	 *
	 * @return array<int, string>
	 */
	private static function get_allowed_statuses()
	{
		return array(
			self::STATUS_OPEN,
			self::STATUS_COMPLETE,
			self::STATUS_APPROVED,
			self::STATUS_CHANGES_REQUESTED,
			self::STATUS_REJECTED,
			self::STATUS_BLOCKED,
		);
	}

	/**
	 * Get status options shown in new user-facing controls.
	 *
	 * @return array<string, string>
	 */
	private static function get_status_options()
	{
		return array(
			self::STATUS_OPEN              => __('Open', 'signoffflow-client-approval-workflow'),
			self::STATUS_APPROVED          => __('Approved', 'signoffflow-client-approval-workflow'),
			self::STATUS_CHANGES_REQUESTED => __('Changes requested', 'signoffflow-client-approval-workflow'),
			self::STATUS_REJECTED          => __('Rejected', 'signoffflow-client-approval-workflow'),
			self::STATUS_BLOCKED           => __('Blocked', 'signoffflow-client-approval-workflow'),
		);
	}

	/**
	 * Get statuses that assigned client users can submit.
	 *
	 * @return array<int, string>
	 */
	private static function get_client_outcome_statuses()
	{
		return array(
			self::STATUS_APPROVED,
			self::STATUS_CHANGES_REQUESTED,
			self::STATUS_REJECTED,
			self::STATUS_BLOCKED,
		);
	}

	/**
	 * Normalize legacy statuses for new writes.
	 *
	 * @param string $status Request status.
	 * @return string
	 */
	private static function normalize_status_for_storage($status)
	{
		return self::STATUS_COMPLETE === $status ? self::STATUS_APPROVED : $status;
	}

	/**
	 * Normalize legacy statuses for new controls and labels.
	 *
	 * @param string $status Request status.
	 * @return string
	 */
	private static function normalize_status_for_ui($status)
	{
		return self::STATUS_COMPLETE === $status ? self::STATUS_APPROVED : $status;
	}

	/**
	 * Determine whether an outcome requires an explanatory note.
	 *
	 * @param string $status Request outcome status.
	 * @return bool
	 */
	private static function response_note_is_required($status)
	{
		return in_array(
			(string) $status,
			array(
				self::STATUS_CHANGES_REQUESTED,
				self::STATUS_REJECTED,
				self::STATUS_BLOCKED,
			),
			true
		);
	}

	/**
	 * Get the character length of a response note.
	 *
	 * @param string $value Response note.
	 * @return int
	 */
	private static function get_string_length($value)
	{
		return function_exists('mb_strlen') ? mb_strlen($value, 'UTF-8') : strlen($value);
	}

	/**
	 * Format a UTC response timestamp in the site's timezone.
	 *
	 * @param int $timestamp UTC timestamp.
	 * @return string
	 */
	private static function format_response_timestamp($timestamp)
	{
		$format = trim((string) get_option('date_format') . ' ' . (string) get_option('time_format'));

		return wp_date($format, absint($timestamp));
	}

	/**
	 * Fire a one-time event when a request becomes client-visible.
	 *
	 * @param int      $post_id   Request post ID.
	 * @param \WP_Post $post      Request post object.
	 * @param int      $client_id Linked client ID.
	 * @return void
	 */
	private function maybe_dispatch_created_event($post_id, $post, $client_id)
	{
		if (! $post instanceof \WP_Post || 'publish' !== $post->post_status) {
			return;
		}

		$client_id = absint($client_id);

		if ($client_id <= 0) {
			return;
		}

		if ('1' === (string) get_post_meta($post_id, self::NOTIFIED_META_KEY, true)) {
			return;
		}

		/**
		 * Fires when a published request becomes visible to a client.
		 *
		 * @param int $post_id   Request post ID.
		 * @param int $client_id Client post ID.
		 */
		do_action('cliapwo_request_created', $post_id, $client_id);
		do_action('cliapwo_after_request_created', $post_id, $client_id);
		update_post_meta($post_id, self::NOTIFIED_META_KEY, '1');
	}

	/**
	 * Redirect back after a portal request action.
	 *
	 * @return void
	 */
	private function redirect_back()
	{
		$redirect_url = wp_get_referer();

		if (! is_string($redirect_url) || '' === $redirect_url) {
			$redirect_url = home_url('/');
		}

		wp_safe_redirect($redirect_url);
		exit;
	}
}
