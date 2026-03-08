<?php

/**
 * Client CPT registration, admin UI, and access helpers.
 *
 * @package ClientApprovalWorkflow
 */

namespace ClientApprovalWorkflow;

defined('ABSPATH') || exit;

/**
 * Handles client entities and assignments.
 */
class Clients
{
	/**
	 * Client post type slug.
	 */
	public const POST_TYPE = 'cliapwo_client';

	/**
	 * Assigned users meta key.
	 */
	public const ASSIGNED_USERS_META_KEY = 'cliapwo_assigned_users';

	/**
	 * Contact email meta key.
	 */
	public const CONTACT_EMAIL_META_KEY = 'cliapwo_contact_email';

	/**
	 * Notes meta key.
	 */
	public const NOTES_META_KEY = 'cliapwo_client_notes';

	/**
	 * Meta box nonce action.
	 */
	public const SAVE_NONCE_ACTION = 'cliapwo_save_client_details';

	/**
	 * Meta box nonce field name.
	 */
	public const SAVE_NONCE_NAME = 'cliapwo_client_nonce';

	/**
	 * Register the client module hooks.
	 *
	 * @return void
	 */
	public function register()
	{
		add_action('init', array($this, 'register_post_type'));
		add_action('add_meta_boxes', array($this, 'register_meta_boxes'));
		add_action('save_post_' . self::POST_TYPE, array($this, 'save_client_meta'), 10, 2);
	}

	/**
	 * Register the client post type.
	 *
	 * @return void
	 */
	public function register_post_type()
	{
		register_post_type(
			self::POST_TYPE,
			array(
				'labels'              => array(
					'name'          => __('Clients', 'client-approval-workflow'),
					'singular_name' => __('Client', 'client-approval-workflow'),
					'menu_name'     => __('Clients', 'client-approval-workflow'),
					'add_new_item'  => __('Add Client', 'client-approval-workflow'),
					'edit_item'     => __('Edit Client', 'client-approval-workflow'),
					'new_item'      => __('New Client', 'client-approval-workflow'),
					'view_item'     => __('View Client', 'client-approval-workflow'),
					'search_items'  => __('Search Clients', 'client-approval-workflow'),
					'not_found'     => __('No clients found.', 'client-approval-workflow'),
				),
				'public'              => false,
				'show_ui'             => true,
				'show_in_menu'        => Settings::PAGE_SLUG,
				'show_in_rest'        => false,
				'has_archive'         => false,
				'exclude_from_search' => true,
				'publicly_queryable'  => false,
				'menu_position'       => null,
				'menu_icon'           => 'dashicons-groups',
				'supports'            => array('title'),
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
	 * Register the client details meta box.
	 *
	 * @return void
	 */
	public function register_meta_boxes()
	{
		add_meta_box(
			'cliapwo_client_details',
			__('Client Details', 'client-approval-workflow'),
			array($this, 'render_client_details_meta_box'),
			self::POST_TYPE,
			'normal',
			'default'
		);
	}

	/**
	 * Render the client details meta box.
	 *
	 * @param \WP_Post $post Current post object.
	 * @return void
	 */
	public function render_client_details_meta_box($post)
	{
		if (! $post instanceof \WP_Post) {
			return;
		}

		wp_nonce_field(self::SAVE_NONCE_ACTION, self::SAVE_NONCE_NAME);

		$assigned_user_ids = self::get_assigned_user_ids($post->ID);
		$contact_email     = get_post_meta($post->ID, self::CONTACT_EMAIL_META_KEY, true);
		$notes             = get_post_meta($post->ID, self::NOTES_META_KEY, true);
		$users             = get_users(
			array(
				'orderby' => 'display_name',
				'order'   => 'ASC',
			)
		);
		?>
		<p>
			<label for="cliapwo_contact_email"><strong><?php esc_html_e('Contact email', 'client-approval-workflow'); ?></strong></label><br />
			<input
				type="email"
				class="regular-text"
				id="cliapwo_contact_email"
				name="cliapwo_contact_email"
				value="<?php echo esc_attr((string) $contact_email); ?>" />
		</p>

		<p>
			<label for="cliapwo_client_notes"><strong><?php esc_html_e('Internal notes', 'client-approval-workflow'); ?></strong></label><br />
			<textarea
				class="large-text"
				rows="5"
				id="cliapwo_client_notes"
				name="cliapwo_client_notes"><?php echo esc_textarea((string) $notes); ?></textarea>
		</p>

		<p><strong><?php esc_html_e('Assigned users', 'client-approval-workflow'); ?></strong></p>
		<?php if (empty($users)) : ?>
			<p><?php esc_html_e('No WordPress users are available to assign.', 'client-approval-workflow'); ?></p>
		<?php else : ?>
			<fieldset>
				<?php foreach ($users as $user) : ?>
					<?php if (! $user instanceof \WP_User) : ?>
						<?php continue; ?>
					<?php endif; ?>
					<label style="display:block; margin-bottom:8px;">
						<input
							type="checkbox"
							name="cliapwo_assigned_users[]"
							value="<?php echo esc_attr((string) $user->ID); ?>"
							<?php checked(in_array((int) $user->ID, $assigned_user_ids, true)); ?> />
						<?php echo esc_html($user->display_name); ?>
						<?php if (! empty($user->user_email)) : ?>
							<?php echo esc_html(' (' . $user->user_email . ')'); ?>
						<?php endif; ?>
					</label>
				<?php endforeach; ?>
			</fieldset>
		<?php endif; ?>
		<?php
	}

	/**
	 * Save the client details meta box values.
	 *
	 * @param int      $post_id Client post ID.
	 * @param \WP_Post $post    Post object.
	 * @return void
	 */
	public function save_client_meta($post_id, $post)
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

		$assigned_user_ids = array();

		if (isset($_POST['cliapwo_assigned_users']) && is_array($_POST['cliapwo_assigned_users'])) {
			$assigned_user_ids = array_map('absint', wp_unslash($_POST['cliapwo_assigned_users']));
			$assigned_user_ids = array_filter($assigned_user_ids);
			$assigned_user_ids = array_values(array_unique($assigned_user_ids));
			$assigned_user_ids = self::filter_existing_user_ids($assigned_user_ids);
		}

		if (empty($assigned_user_ids)) {
			delete_post_meta($post_id, self::ASSIGNED_USERS_META_KEY);
		} else {
			update_post_meta($post_id, self::ASSIGNED_USERS_META_KEY, $assigned_user_ids);
		}

		$contact_email = '';

		if (isset($_POST['cliapwo_contact_email'])) {
			$contact_email = sanitize_email(wp_unslash($_POST['cliapwo_contact_email']));
		}

		if ('' === $contact_email) {
			delete_post_meta($post_id, self::CONTACT_EMAIL_META_KEY);
		} else {
			update_post_meta($post_id, self::CONTACT_EMAIL_META_KEY, $contact_email);
		}

		$notes = '';

		if (isset($_POST['cliapwo_client_notes'])) {
			$notes = sanitize_textarea_field(wp_unslash($_POST['cliapwo_client_notes']));
		}

		if ('' === $notes) {
			delete_post_meta($post_id, self::NOTES_META_KEY);
		} else {
			update_post_meta($post_id, self::NOTES_META_KEY, $notes);
		}
	}

	/**
	 * Get the assigned client posts for a user.
	 *
	 * @param int $user_id WordPress user ID.
	 * @return array<int, \WP_Post>
	 */
	public static function get_clients_for_user($user_id = 0)
	{
		$user_id = absint($user_id);

		if ($user_id <= 0) {
			$user_id = get_current_user_id();
		}

		if ($user_id <= 0) {
			return array();
		}

		$client_ids = self::get_client_ids_for_user($user_id);

		if (empty($client_ids)) {
			return array();
		}

		$clients = get_posts(
			array(
				'post_type'              => self::POST_TYPE,
				'post_status'            => 'publish',
				'post__in'               => $client_ids,
				'orderby'                => 'post__in',
				'posts_per_page'         => count($client_ids),
				'update_post_meta_cache' => true,
				'update_post_term_cache' => false,
			)
		);

		return is_array($clients) ? $clients : array();
	}

	/**
	 * Get the first assigned client post for a user.
	 *
	 * @param int $user_id WordPress user ID.
	 * @return \WP_Post|null
	 */
	public static function get_client_for_user($user_id = 0)
	{
		$clients = self::get_clients_for_user($user_id);

		return empty($clients) ? null : $clients[0];
	}

	/**
	 * Get the first published client.
	 *
	 * Useful for staff/admin preview flows when no explicit assignment exists.
	 *
	 * @return \WP_Post|null
	 */
	public static function get_first_client()
	{
		$clients = get_posts(
			array(
				'post_type'              => self::POST_TYPE,
				'post_status'            => 'publish',
				'posts_per_page'         => 1,
				'orderby'                => 'title',
				'order'                  => 'ASC',
				'update_post_meta_cache' => false,
				'update_post_term_cache' => false,
			)
		);

		if (! is_array($clients) || empty($clients) || ! $clients[0] instanceof \WP_Post) {
			return null;
		}

		return $clients[0];
	}

	/**
	 * Determine whether a user can view a client.
	 *
	 * @param int $client_id Client post ID.
	 * @param int $user_id   WordPress user ID.
	 * @return bool
	 */
	public static function user_can_view_client($client_id, $user_id = 0)
	{
		$client_id = absint($client_id);
		$user_id   = absint($user_id);

		if ($client_id <= 0) {
			return false;
		}

		if ($user_id <= 0) {
			$user_id = get_current_user_id();
		}

		if ($user_id <= 0) {
			return false;
		}

		$client = get_post($client_id);

		if (! $client instanceof \WP_Post || self::POST_TYPE !== $client->post_type) {
			return false;
		}

		if (user_can($user_id, 'cliapwo_manage_portal')) {
			return true;
		}

		if (! user_can($user_id, 'cliapwo_view_portal') || 'publish' !== $client->post_status) {
			return false;
		}

		return in_array($user_id, self::get_assigned_user_ids($client_id), true);
	}

	/**
	 * Get the client IDs assigned to a user.
	 *
	 * @param int $user_id WordPress user ID.
	 * @return array<int, int>
	 */
	public static function get_client_ids_for_user($user_id)
	{
		$user_id = absint($user_id);

		if ($user_id <= 0) {
			return array();
		}

		$client_ids = get_posts(
			array(
				'post_type'              => self::POST_TYPE,
				'post_status'            => 'publish',
				'posts_per_page'         => -1,
				'fields'                 => 'ids',
				'no_found_rows'          => true,
				'update_post_meta_cache' => false,
				'update_post_term_cache' => false,
				'meta_query'             => array(
					'relation' => 'OR',
					array(
						'key'     => self::ASSIGNED_USERS_META_KEY,
						'value'   => 'i:' . $user_id . ';',
						'compare' => 'LIKE',
					),
					array(
						'key'     => self::ASSIGNED_USERS_META_KEY,
						'value'   => '"' . $user_id . '"',
						'compare' => 'LIKE',
					),
				),
			)
		);

		return array_map('absint', is_array($client_ids) ? $client_ids : array());
	}

	/**
	 * Get the assigned user IDs stored for a client.
	 *
	 * @param int $client_id Client post ID.
	 * @return array<int, int>
	 */
	public static function get_assigned_user_ids($client_id)
	{
		$assigned_user_ids = get_post_meta($client_id, self::ASSIGNED_USERS_META_KEY, true);

		if (! is_array($assigned_user_ids)) {
			return array();
		}

		$assigned_user_ids = array_map('absint', $assigned_user_ids);
		$assigned_user_ids = array_filter($assigned_user_ids);

		return array_values(array_unique($assigned_user_ids));
	}

	/**
	 * Filter user IDs down to existing users.
	 *
	 * @param array<int, int> $user_ids Candidate user IDs.
	 * @return array<int, int>
	 */
	private static function filter_existing_user_ids(array $user_ids)
	{
		if (empty($user_ids)) {
			return array();
		}

		$existing_user_ids = get_users(
			array(
				'include' => $user_ids,
				'fields'  => 'ids',
			)
		);

		return array_map('absint', is_array($existing_user_ids) ? $existing_user_ids : array());
	}
}
