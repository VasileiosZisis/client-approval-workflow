<?php

/**
 * Update CPT registration and client linkage.
 *
 * @package ClientApprovalWorkflow
 */

namespace ClientApprovalWorkflow;

defined('ABSPATH') || exit;

/**
 * Handles client updates and update queries.
 */
class Updates
{
	/**
	 * Update post type slug.
	 */
	public const POST_TYPE = 'cliapwo_update';

	/**
	 * Linked client meta key.
	 */
	public const CLIENT_META_KEY = 'cliapwo_client_id';

	/**
	 * Visibility meta key.
	 */
	public const VISIBILITY_META_KEY = 'cliapwo_visibility';

	/**
	 * Default visibility value.
	 */
	public const VISIBILITY_CLIENT = 'client';

	/**
	 * Meta box nonce action.
	 */
	public const SAVE_NONCE_ACTION = 'cliapwo_save_update_details';

	/**
	 * Meta box nonce field name.
	 */
	public const SAVE_NONCE_NAME = 'cliapwo_update_nonce';

	/**
	 * Register update hooks.
	 *
	 * @return void
	 */
	public function register()
	{
		add_action('init', array($this, 'register_post_type'));
		add_action('add_meta_boxes', array($this, 'register_meta_boxes'));
		add_action('save_post_' . self::POST_TYPE, array($this, 'save_update_meta'), 10, 2);
		add_filter('manage_' . self::POST_TYPE . '_posts_columns', array($this, 'filter_update_columns'));
		add_action('manage_' . self::POST_TYPE . '_posts_custom_column', array($this, 'render_update_column'), 10, 2);
	}

	/**
	 * Register the update post type.
	 *
	 * @return void
	 */
	public function register_post_type()
	{
		register_post_type(
			self::POST_TYPE,
			array(
				'labels'              => array(
					'name'          => __('Updates', 'client-approval-workflow'),
					'singular_name' => __('Update', 'client-approval-workflow'),
					'menu_name'     => __('Updates', 'client-approval-workflow'),
					'add_new_item'  => __('Add Update', 'client-approval-workflow'),
					'edit_item'     => __('Edit Update', 'client-approval-workflow'),
					'new_item'      => __('New Update', 'client-approval-workflow'),
					'view_item'     => __('View Update', 'client-approval-workflow'),
					'search_items'  => __('Search Updates', 'client-approval-workflow'),
					'not_found'     => __('No updates found.', 'client-approval-workflow'),
				),
				'public'              => false,
				'show_ui'             => true,
				'show_in_menu'        => Settings::PAGE_SLUG,
				'show_in_rest'        => false,
				'has_archive'         => false,
				'exclude_from_search' => true,
				'publicly_queryable'  => false,
				'supports'            => array('title', 'editor', 'author'),
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
	 * Register the update details meta box.
	 *
	 * @return void
	 */
	public function register_meta_boxes()
	{
		add_meta_box(
			'cliapwo_update_details',
			__('Update Details', 'client-approval-workflow'),
			array($this, 'render_update_details_meta_box'),
			self::POST_TYPE,
			'side',
			'default'
		);
	}

	/**
	 * Render the update details meta box.
	 *
	 * @param \WP_Post $post Current update post.
	 * @return void
	 */
	public function render_update_details_meta_box($post)
	{
		if (! $post instanceof \WP_Post) {
			return;
		}

		wp_nonce_field(self::SAVE_NONCE_ACTION, self::SAVE_NONCE_NAME);

		$client_id = self::get_client_id_for_update($post->ID);
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
			<label for="cliapwo_update_client_id"><strong><?php esc_html_e('Client', 'client-approval-workflow'); ?></strong></label>
		</p>
		<select
			class="widefat"
			id="cliapwo_update_client_id"
			name="cliapwo_update_client_id">
			<option value="0"><?php esc_html_e('Select a client', 'client-approval-workflow'); ?></option>
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
		<p class="description"><?php esc_html_e('Updates are shown only in the assigned client portal.', 'client-approval-workflow'); ?></p>
		<?php
	}

	/**
	 * Save update linkage metadata.
	 *
	 * @param int      $post_id Update post ID.
	 * @param \WP_Post $post    Post object.
	 * @return void
	 */
	public function save_update_meta($post_id, $post)
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

		if (isset($_POST['cliapwo_update_client_id'])) {
			$client_id = absint(wp_unslash($_POST['cliapwo_update_client_id']));
		}

		if ($client_id > 0) {
			$client = get_post($client_id);

			if (! $client instanceof \WP_Post || Clients::POST_TYPE !== $client->post_type) {
				$client_id = 0;
			}
		}

		if ($client_id > 0) {
			update_post_meta($post_id, self::CLIENT_META_KEY, $client_id);
			update_post_meta($post_id, self::VISIBILITY_META_KEY, self::VISIBILITY_CLIENT);
		} else {
			delete_post_meta($post_id, self::CLIENT_META_KEY);
			delete_post_meta($post_id, self::VISIBILITY_META_KEY);
		}
	}

	/**
	 * Add a linked client column to the update list table.
	 *
	 * @param array<string, string> $columns Existing columns.
	 * @return array<string, string>
	 */
	public function filter_update_columns($columns)
	{
		$columns['cliapwo_update_client'] = __('Client', 'client-approval-workflow');

		return $columns;
	}

	/**
	 * Render custom update list table columns.
	 *
	 * @param string $column  Column name.
	 * @param int    $post_id Update post ID.
	 * @return void
	 */
	public function render_update_column($column, $post_id)
	{
		if ('cliapwo_update_client' !== $column) {
			return;
		}

		$client_id = self::get_client_id_for_update($post_id);
		$client    = $client_id > 0 ? get_post($client_id) : null;

		if (! $client instanceof \WP_Post) {
			echo esc_html__('Unassigned', 'client-approval-workflow');
			return;
		}

		echo esc_html($client->post_title);
	}

	/**
	 * Get the linked client ID for an update.
	 *
	 * @param int $update_id Update post ID.
	 * @return int
	 */
	public static function get_client_id_for_update($update_id)
	{
		return absint(get_post_meta($update_id, self::CLIENT_META_KEY, true));
	}

	/**
	 * Get a paged update query for a client.
	 *
	 * @param int   $client_id Client post ID.
	 * @param array $args      Optional query overrides.
	 * @return \WP_Query
	 */
	public static function get_updates_query_for_client($client_id, array $args = array())
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
			'posts_per_page'         => 10,
			'paged'                  => 1,
			'orderby'                => 'date',
			'order'                  => 'DESC',
			'update_post_meta_cache' => true,
			'update_post_term_cache' => false,
			'meta_query'             => array(
				array(
					'key'   => self::CLIENT_META_KEY,
					'value' => $client_id,
				),
				array(
					'key'   => self::VISIBILITY_META_KEY,
					'value' => self::VISIBILITY_CLIENT,
				),
			),
		);

		return new \WP_Query(wp_parse_args($args, $defaults));
	}
}
