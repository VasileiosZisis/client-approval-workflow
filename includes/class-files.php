<?php

/**
 * File uploads, listing, and protected downloads.
 *
 * @package ClientApprovalWorkflow
 */

namespace ClientApprovalWorkflow;

defined('ABSPATH') || exit;

/**
 * Handles client files.
 */
class Files
{
	/**
	 * File post type slug.
	 */
	public const POST_TYPE = 'cliapwo_file';

	/**
	 * Linked client meta key.
	 */
	public const CLIENT_META_KEY = 'cliapwo_client_id';

	/**
	 * Attachment ID meta key.
	 */
	public const ATTACHMENT_META_KEY = 'cliapwo_attachment_id';

	/**
	 * Original filename meta key.
	 */
	public const ORIGINAL_FILENAME_META_KEY = 'cliapwo_original_filename';

	/**
	 * File size meta key.
	 */
	public const FILE_SIZE_META_KEY = 'cliapwo_file_size';

	/**
	 * Mime type meta key.
	 */
	public const MIME_TYPE_META_KEY = 'cliapwo_mime_type';

	/**
	 * Save nonce action.
	 */
	public const SAVE_NONCE_ACTION = 'cliapwo_save_file_details';

	/**
	 * Save nonce field name.
	 */
	public const SAVE_NONCE_NAME = 'cliapwo_file_nonce';

	/**
	 * Download action.
	 */
	public const DOWNLOAD_ACTION = 'cliapwo_download_file';

	/**
	 * Download nonce name.
	 */
	public const DOWNLOAD_NONCE_NAME = 'cliapwo_download_nonce';

	/**
	 * Upload field name.
	 */
	public const UPLOAD_FIELD_NAME = 'cliapwo_file_upload';

	/**
	 * Last notified attachment ID meta key.
	 */
	public const LAST_NOTIFIED_ATTACHMENT_META_KEY = 'cliapwo_last_notified_attachment_id';

	/**
	 * Register module hooks.
	 *
	 * @return void
	 */
	public function register()
	{
		add_action('init', array($this, 'register_post_type'));
		add_action('add_meta_boxes', array($this, 'register_meta_boxes'));
		add_action('post_edit_form_tag', array($this, 'add_upload_form_enctype'));
		add_action('save_post_' . self::POST_TYPE, array($this, 'save_file_meta'), 10, 2);
		add_action('admin_notices', array($this, 'render_admin_notices'));
		add_filter('manage_' . self::POST_TYPE . '_posts_columns', array($this, 'filter_file_columns'));
		add_action('manage_' . self::POST_TYPE . '_posts_custom_column', array($this, 'render_file_column'), 10, 2);
		add_action('admin_post_' . self::DOWNLOAD_ACTION, array($this, 'handle_download'));
		add_action('admin_post_nopriv_' . self::DOWNLOAD_ACTION, array($this, 'handle_unauthorized_download'));
	}

	/**
	 * Add multipart enctype to the file post edit form.
	 *
	 * @return void
	 */
	public function add_upload_form_enctype()
	{
		$screen = function_exists('get_current_screen') ? get_current_screen() : null;

		if (! $screen instanceof \WP_Screen || self::POST_TYPE !== $screen->post_type) {
			return;
		}

		echo ' enctype="multipart/form-data"';
	}

	/**
	 * Register the file post type.
	 *
	 * @return void
	 */
	public function register_post_type()
	{
		register_post_type(
			self::POST_TYPE,
			array(
				'labels'              => array(
					'name'          => __('Files', 'client-approval-workflow'),
					'singular_name' => __('File', 'client-approval-workflow'),
					'menu_name'     => __('Files', 'client-approval-workflow'),
					'add_new_item'  => __('Add File', 'client-approval-workflow'),
					'edit_item'     => __('Edit File', 'client-approval-workflow'),
					'new_item'      => __('New File', 'client-approval-workflow'),
					'view_item'     => __('View File', 'client-approval-workflow'),
					'search_items'  => __('Search Files', 'client-approval-workflow'),
					'not_found'     => __('No files found.', 'client-approval-workflow'),
				),
				'public'              => false,
				'show_ui'             => true,
				'show_in_menu'        => Settings::PAGE_SLUG,
				'show_in_rest'        => false,
				'has_archive'         => false,
				'exclude_from_search' => true,
				'publicly_queryable'  => false,
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
	 * Register meta boxes.
	 *
	 * @return void
	 */
	public function register_meta_boxes()
	{
		add_meta_box(
			'cliapwo_file_details',
			__('File Details', 'client-approval-workflow'),
			array($this, 'render_file_details_meta_box'),
			self::POST_TYPE,
			'normal',
			'default'
		);
	}

	/**
	 * Render the file details meta box.
	 *
	 * @param \WP_Post $post Current file post.
	 * @return void
	 */
	public function render_file_details_meta_box($post)
	{
		if (! $post instanceof \WP_Post) {
			return;
		}

		wp_nonce_field(self::SAVE_NONCE_ACTION, self::SAVE_NONCE_NAME);

		$client_id     = self::get_client_id_for_file($post->ID);
		$attachment_id = self::get_attachment_id_for_file($post->ID);
		$attachment    = $attachment_id > 0 ? get_post($attachment_id) : null;
		$clients       = get_posts(
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
			<label for="cliapwo_file_client_id"><strong><?php esc_html_e('Client', 'client-approval-workflow'); ?></strong></label><br />
			<select
				class="widefat"
				id="cliapwo_file_client_id"
				name="cliapwo_file_client_id">
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
		</p>

		<p>
			<label for="cliapwo_file_upload"><strong><?php esc_html_e('Upload file', 'client-approval-workflow'); ?></strong></label><br />
			<input
				type="file"
				id="cliapwo_file_upload"
				name="<?php echo esc_attr(self::UPLOAD_FIELD_NAME); ?>" />
		</p>

		<?php if ($attachment instanceof \WP_Post) : ?>
			<p class="description">
				<?php
				printf(
					/* translators: %s: file name */
					esc_html__('Current file: %s', 'client-approval-workflow'),
					esc_html((string) get_post_meta($post->ID, self::ORIGINAL_FILENAME_META_KEY, true))
				);
				?>
			</p>
		<?php else : ?>
			<p class="description"><?php esc_html_e('No file uploaded yet.', 'client-approval-workflow'); ?></p>
		<?php endif; ?>

		<p class="description">
			<?php esc_html_e('Allowed file types follow the site upload settings. Uploading a new file replaces the linked download for this record.', 'client-approval-workflow'); ?>
		</p>
		<?php
	}

	/**
	 * Save file metadata and uploads.
	 *
	 * @param int      $post_id File post ID.
	 * @param \WP_Post $post    Post object.
	 * @return void
	 */
	public function save_file_meta($post_id, $post)
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

		if (isset($_POST['cliapwo_file_client_id'])) {
			$client_id = absint(wp_unslash($_POST['cliapwo_file_client_id']));
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

		$attachment_id = self::get_attachment_id_for_file($post_id);

		if (! empty($_FILES[self::UPLOAD_FIELD_NAME]) && is_array($_FILES[self::UPLOAD_FIELD_NAME])) {
			$file_data = wp_unslash($_FILES[self::UPLOAD_FIELD_NAME]);

			if (isset($file_data['error']) && UPLOAD_ERR_NO_FILE !== (int) $file_data['error']) {
				if (UPLOAD_ERR_OK !== (int) $file_data['error']) {
					set_transient(
						'cliapwo_file_upload_error_' . $post_id,
						__('The file upload failed. Please try again.', 'client-approval-workflow'),
						MINUTE_IN_SECONDS
					);
					return;
				}

				if (! isset($file_data['name']) || ! isset($file_data['tmp_name'])) {
					set_transient(
						'cliapwo_file_upload_error_' . $post_id,
						__('The uploaded file data is incomplete.', 'client-approval-workflow'),
						MINUTE_IN_SECONDS
					);
					return;
				}

				$original_name = sanitize_file_name((string) $file_data['name']);
				$file_check    = wp_check_filetype_and_ext((string) $file_data['tmp_name'], $original_name);
				$allowed_mimes = get_allowed_mime_types();
				$extension     = is_array($file_check) && isset($file_check['ext']) ? (string) $file_check['ext'] : '';
				$mime_type     = is_array($file_check) && isset($file_check['type']) ? (string) $file_check['type'] : '';

				if ('' === $extension || '' === $mime_type || ! in_array($mime_type, $allowed_mimes, true)) {
					set_transient(
						'cliapwo_file_upload_error_' . $post_id,
						__('That file type is not allowed.', 'client-approval-workflow'),
						MINUTE_IN_SECONDS
					);
					return;
				}

				require_once ABSPATH . 'wp-admin/includes/file.php';
				require_once ABSPATH . 'wp-admin/includes/media.php';
				require_once ABSPATH . 'wp-admin/includes/image.php';

				$attachment_id = media_handle_upload(self::UPLOAD_FIELD_NAME, $post_id);

				if (is_wp_error($attachment_id)) {
					set_transient(
						'cliapwo_file_upload_error_' . $post_id,
						$attachment_id->get_error_message(),
						MINUTE_IN_SECONDS
					);
					return;
				}

				$file_path = get_attached_file($attachment_id);
				$file_size = is_string($file_path) && '' !== $file_path && file_exists($file_path) ? filesize($file_path) : 0;

				update_post_meta($post_id, self::ATTACHMENT_META_KEY, $attachment_id);
				update_post_meta($post_id, self::ORIGINAL_FILENAME_META_KEY, $original_name);
				update_post_meta($post_id, self::MIME_TYPE_META_KEY, $mime_type);
				update_post_meta($post_id, self::FILE_SIZE_META_KEY, absint($file_size));
			}
		}

		$this->maybe_dispatch_upload_event($post_id, $post, $client_id, $attachment_id);
	}

	/**
	 * Filter file columns.
	 *
	 * @param array<string, string> $columns Existing columns.
	 * @return array<string, string>
	 */
	public function filter_file_columns($columns)
	{
		$columns['cliapwo_file_client'] = __('Client', 'client-approval-workflow');
		$columns['cliapwo_file_name']   = __('Stored file', 'client-approval-workflow');

		return $columns;
	}

	/**
	 * Render custom file columns.
	 *
	 * @param string $column  Column name.
	 * @param int    $post_id File post ID.
	 * @return void
	 */
	public function render_file_column($column, $post_id)
	{
		if ('cliapwo_file_client' === $column) {
			$client = get_post(self::get_client_id_for_file($post_id));
			echo $client instanceof \WP_Post ? esc_html($client->post_title) : esc_html__('Unassigned', 'client-approval-workflow');
			return;
		}

		if ('cliapwo_file_name' !== $column) {
			return;
		}

		$file_name = get_post_meta($post_id, self::ORIGINAL_FILENAME_META_KEY, true);
		echo '' !== (string) $file_name ? esc_html((string) $file_name) : esc_html__('No file uploaded', 'client-approval-workflow');
	}

	/**
	 * Handle protected file downloads.
	 *
	 * @return void
	 */
	public function handle_download()
	{
		$nonce = '';

		if (isset($_GET[self::DOWNLOAD_NONCE_NAME])) {
			$nonce = sanitize_text_field(wp_unslash($_GET[self::DOWNLOAD_NONCE_NAME]));
		}

		if (! wp_verify_nonce($nonce, self::DOWNLOAD_ACTION)) {
			wp_die(
				esc_html__('Invalid download request.', 'client-approval-workflow'),
				esc_html__('Forbidden', 'client-approval-workflow'),
				array(
					'response' => 403,
				)
			);
		}

		if (! is_user_logged_in()) {
			wp_die(
				esc_html__('You must be logged in to download files.', 'client-approval-workflow'),
				esc_html__('Forbidden', 'client-approval-workflow'),
				array(
					'response' => 403,
				)
			);
		}

		if (! current_user_can('cliapwo_manage_portal') && ! current_user_can('cliapwo_view_portal')) {
			wp_die(
				esc_html__('You are not allowed to download files.', 'client-approval-workflow'),
				esc_html__('Forbidden', 'client-approval-workflow'),
				array(
					'response' => 403,
				)
			);
		}

		$file_post_id = 0;

		if (isset($_GET['cliapwo_file_id'])) {
			$file_post_id = absint(wp_unslash($_GET['cliapwo_file_id']));
		}

		$file_post = get_post($file_post_id);

		if (! $file_post instanceof \WP_Post || self::POST_TYPE !== $file_post->post_type || 'publish' !== $file_post->post_status) {
			wp_die(
				esc_html__('The requested file could not be found.', 'client-approval-workflow'),
				esc_html__('Not Found', 'client-approval-workflow'),
				array(
					'response' => 404,
				)
			);
		}

		$client_id = self::get_client_id_for_file($file_post_id);

		if (! Clients::user_can_view_client($client_id, get_current_user_id())) {
			wp_die(
				esc_html__('You do not have access to this file.', 'client-approval-workflow'),
				esc_html__('Forbidden', 'client-approval-workflow'),
				array(
					'response' => 403,
				)
			);
		}

		$attachment_id = self::get_attachment_id_for_file($file_post_id);
		$file_path     = $attachment_id > 0 ? get_attached_file($attachment_id) : '';
		$file_name     = (string) get_post_meta($file_post_id, self::ORIGINAL_FILENAME_META_KEY, true);
		$mime_type     = (string) get_post_meta($file_post_id, self::MIME_TYPE_META_KEY, true);

		if (! is_string($file_path) || '' === $file_path || ! file_exists($file_path)) {
			wp_die(
				esc_html__('The requested file is missing from storage.', 'client-approval-workflow'),
				esc_html__('Not Found', 'client-approval-workflow'),
				array(
					'response' => 404,
				)
			);
		}

		if ('' === $file_name) {
			$file_name = basename($file_path);
		}

		if ('' === $mime_type) {
			$mime_type = 'application/octet-stream';
		}

		nocache_headers();
		header('Content-Description: File Transfer');
		header('Content-Type: ' . $mime_type);
		header('Content-Disposition: attachment; filename="' . str_replace('"', '', $file_name) . '"');
		header('Content-Length: ' . (string) filesize($file_path));
		header('X-Robots-Tag: noindex, nofollow', true);
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_readfile -- direct streaming is required for protected downloads.
		readfile($file_path);
		exit;
	}

	/**
	 * Reject unauthenticated download attempts.
	 *
	 * @return void
	 */
	public function handle_unauthorized_download()
	{
		wp_die(
			esc_html__('You must be logged in to download files.', 'client-approval-workflow'),
			esc_html__('Forbidden', 'client-approval-workflow'),
			array(
				'response' => 403,
			)
		);
	}

	/**
	 * Render file upload errors on the edit screen.
	 *
	 * @return void
	 */
	public function render_admin_notices()
	{
		if (! current_user_can('cliapwo_manage_portal')) {
			return;
		}

		$screen = function_exists('get_current_screen') ? get_current_screen() : null;

		if (! $screen instanceof \WP_Screen || self::POST_TYPE !== $screen->post_type) {
			return;
		}

		$post_id = 0;

		$raw_post_id = filter_input(INPUT_GET, 'post', FILTER_SANITIZE_NUMBER_INT);

		if (is_string($raw_post_id) && '' !== $raw_post_id) {
			$post_id = absint($raw_post_id);
		}

		if ($post_id <= 0) {
			return;
		}

		$error_message = get_transient('cliapwo_file_upload_error_' . $post_id);

		if (! is_string($error_message) || '' === $error_message) {
			return;
		}

		delete_transient('cliapwo_file_upload_error_' . $post_id);
		?>
		<div class="notice notice-error is-dismissible">
			<p><?php echo esc_html($error_message); ?></p>
		</div>
		<?php
	}

	/**
	 * Get the linked client ID for a file post.
	 *
	 * @param int $file_post_id File post ID.
	 * @return int
	 */
	public static function get_client_id_for_file($file_post_id)
	{
		return absint(get_post_meta($file_post_id, self::CLIENT_META_KEY, true));
	}

	/**
	 * Get the linked attachment ID for a file post.
	 *
	 * @param int $file_post_id File post ID.
	 * @return int
	 */
	public static function get_attachment_id_for_file($file_post_id)
	{
		return absint(get_post_meta($file_post_id, self::ATTACHMENT_META_KEY, true));
	}

	/**
	 * Get a protected download URL.
	 *
	 * @param int $file_post_id File post ID.
	 * @return string
	 */
	public static function get_download_url($file_post_id)
	{
		$file_post_id = absint($file_post_id);

		if ($file_post_id <= 0) {
			return '';
		}

		return wp_nonce_url(
			add_query_arg(
				array(
					'action'          => self::DOWNLOAD_ACTION,
					'cliapwo_file_id' => $file_post_id,
				),
				admin_url('admin-post.php')
			),
			self::DOWNLOAD_ACTION,
			self::DOWNLOAD_NONCE_NAME
		);
	}

	/**
	 * Get a paged files query for a client.
	 *
	 * @param int   $client_id Client post ID.
	 * @param array $args      Optional query overrides.
	 * @return \WP_Query
	 */
	public static function get_files_query_for_client($client_id, array $args = array())
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
			'posts_per_page'         => 20,
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
					'key'     => self::ATTACHMENT_META_KEY,
					'compare' => 'EXISTS',
				),
			),
		);

		return new \WP_Query(wp_parse_args($args, $defaults));
	}

	/**
	 * Fire an upload event once per attachment when the file is client-visible.
	 *
	 * @param int      $post_id       File post ID.
	 * @param \WP_Post $post          File post object.
	 * @param int      $client_id     Linked client ID.
	 * @param int      $attachment_id Attachment ID.
	 * @return void
	 */
	private function maybe_dispatch_upload_event($post_id, $post, $client_id, $attachment_id)
	{
		if (! $post instanceof \WP_Post || 'publish' !== $post->post_status) {
			return;
		}

		$client_id     = absint($client_id);
		$attachment_id = absint($attachment_id);

		if ($client_id <= 0 || $attachment_id <= 0) {
			return;
		}

		$last_notified_attachment_id = absint(get_post_meta($post_id, self::LAST_NOTIFIED_ATTACHMENT_META_KEY, true));

		if ($attachment_id === $last_notified_attachment_id) {
			return;
		}

		/**
		 * Fires when a file upload becomes visible to a client.
		 *
		 * @param int $post_id       File post ID.
		 * @param int $client_id     Client post ID.
		 * @param int $attachment_id Attachment ID.
		 */
		do_action('cliapwo_file_uploaded', $post_id, $client_id, $attachment_id);
		do_action('cliapwo_after_file_uploaded', $post_id, $client_id, $attachment_id);
		update_post_meta($post_id, self::LAST_NOTIFIED_ATTACHMENT_META_KEY, $attachment_id);
	}
}
