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
	 * Stored relative path meta key.
	 */
	public const STORED_RELATIVE_PATH_META_KEY = 'cliapwo_stored_relative_path';

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
	 * Uploader ID meta key.
	 */
	public const UPLOADER_ID_META_KEY = 'cliapwo_uploaded_by';

	/**
	 * Upload timestamp meta key.
	 */
	public const UPLOADED_AT_META_KEY = 'cliapwo_uploaded_at';

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
	 * Last notified relative path meta key.
	 */
	public const LAST_NOTIFIED_FILE_META_KEY = 'cliapwo_last_notified_file_path';

	/**
	 * Protected storage subdirectory name.
	 */
	public const STORAGE_DIRECTORY_NAME = 'cliapwo-private';

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
		add_action('before_delete_post', array($this, 'delete_file_assets'));
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

		$client_id          = self::get_client_id_for_file($post->ID);
		$stored_relative    = self::get_stored_relative_path_for_file($post->ID);
		$stored_file_path   = self::get_stored_file_path($post->ID);
		$has_stored_file    = '' !== $stored_relative && '' !== $stored_file_path && file_exists($stored_file_path);
		$original_file_name = (string) get_post_meta($post->ID, self::ORIGINAL_FILENAME_META_KEY, true);
		$clients            = get_posts(
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

		<?php if ($has_stored_file) : ?>
			<p>
				<strong><?php esc_html_e('Current file', 'client-approval-workflow'); ?>:</strong>
				<?php echo esc_html('' !== $original_file_name ? $original_file_name : basename($stored_file_path)); ?>
			</p>
		<?php elseif ('' !== $stored_relative) : ?>
			<p class="description"><?php esc_html_e('A protected file is referenced for this record, but it is missing from storage.', 'client-approval-workflow'); ?></p>
		<?php else : ?>
			<p class="description"><?php esc_html_e('No file uploaded yet.', 'client-approval-workflow'); ?></p>
		<?php endif; ?>

		<p class="description">
			<?php esc_html_e('Allowed file types follow your WordPress upload settings. Uploading a new file replaces the current file for this record.', 'client-approval-workflow'); ?>
		</p>
		<p class="description">
			<?php esc_html_e('Client downloads always go through client-approval-workflow access checks. On Nginx hosts, add a matching server deny rule for the protected uploads directory.', 'client-approval-workflow'); ?>
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

		$file_data = $this->get_uploaded_file_data();

		if (UPLOAD_ERR_NO_FILE !== $file_data['error']) {
			$stored_file = $this->store_uploaded_file($file_data);

			if (is_wp_error($stored_file)) {
				set_transient(
					'cliapwo_file_upload_error_' . $post_id,
					$stored_file->get_error_message(),
					MINUTE_IN_SECONDS
				);
				return;
			}

			$this->delete_current_stored_file($post_id);
			$this->delete_legacy_attachment($post_id);

			update_post_meta($post_id, self::STORED_RELATIVE_PATH_META_KEY, $stored_file['relative_path']);
			update_post_meta($post_id, self::ORIGINAL_FILENAME_META_KEY, $stored_file['original_name']);
			update_post_meta($post_id, self::MIME_TYPE_META_KEY, $stored_file['mime_type']);
			update_post_meta($post_id, self::FILE_SIZE_META_KEY, $stored_file['file_size']);
			update_post_meta($post_id, self::UPLOADER_ID_META_KEY, get_current_user_id());
			update_post_meta($post_id, self::UPLOADED_AT_META_KEY, current_time('mysql', true));
		}

		$this->maybe_dispatch_upload_event($post_id, $post, $client_id, self::get_stored_relative_path_for_file($post_id));
	}

	/**
	 * Delete stored assets when a file post is permanently deleted.
	 *
	 * @param int $post_id Post ID being deleted.
	 * @return void
	 */
	public function delete_file_assets($post_id)
	{
		$post = get_post($post_id);

		if (! $post instanceof \WP_Post || self::POST_TYPE !== $post->post_type) {
			return;
		}

		$this->delete_current_stored_file($post_id);
		$this->delete_legacy_attachment($post_id);
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

		$file_path = self::get_stored_file_path($file_post_id);
		$file_name = (string) get_post_meta($file_post_id, self::ORIGINAL_FILENAME_META_KEY, true);
		$mime_type = (string) get_post_meta($file_post_id, self::MIME_TYPE_META_KEY, true);

		if ('' === $file_path || ! file_exists($file_path)) {
			wp_die(
				esc_html__('The requested file is missing from protected storage.', 'client-approval-workflow'),
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

		$file_size = filesize($file_path);

		nocache_headers();
		header('Content-Description: File Transfer');
		header('Content-Type: ' . $mime_type);
		header('Content-Disposition: attachment; filename="' . str_replace('"', '', $file_name) . '"');
		header('Content-Length: ' . (string) absint($file_size));
		header('X-Robots-Tag: noindex, nofollow', true);
		header('Content-Transfer-Encoding: binary');
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
	 * Get the stored relative path for a file post.
	 *
	 * @param int $file_post_id File post ID.
	 * @return string
	 */
	public static function get_stored_relative_path_for_file($file_post_id)
	{
		$relative_path = get_post_meta($file_post_id, self::STORED_RELATIVE_PATH_META_KEY, true);

		if (! is_string($relative_path) || '' === $relative_path) {
			return '';
		}

		$relative_path = ltrim(wp_normalize_path($relative_path), '/');

		return self::is_valid_relative_path($relative_path) ? $relative_path : '';
	}

	/**
	 * Get the absolute stored file path for a file post.
	 *
	 * @param int $file_post_id File post ID.
	 * @return string
	 */
	public static function get_stored_file_path($file_post_id)
	{
		$relative_path = self::get_stored_relative_path_for_file($file_post_id);

		if ('' === $relative_path) {
			return '';
		}

		$storage_directory = self::get_storage_directory_path();

		if (is_wp_error($storage_directory)) {
			return '';
		}

		$file_name = basename($relative_path);

		return wp_normalize_path(trailingslashit($storage_directory) . $file_name);
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
			// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- file visibility depends on client linkage plus the existence of a protected stored path in private plugin-managed post meta.
			'meta_query'             => array(
				array(
					'key'   => self::CLIENT_META_KEY,
					'value' => $client_id,
				),
				array(
					'key'     => self::STORED_RELATIVE_PATH_META_KEY,
					'compare' => 'EXISTS',
				),
			),
		);

		return new \WP_Query(wp_parse_args($args, $defaults));
	}

	/**
	 * Read and sanitize uploaded file data.
	 *
	 * @return array<string, int|string>
	 */
	private function get_uploaded_file_data()
	{
		$file_data = array(
			'name'     => '',
			'type'     => '',
			'tmp_name' => '',
			'error'    => UPLOAD_ERR_NO_FILE,
			'size'     => 0,
		);

		// phpcs:disable WordPress.Security.NonceVerification.Missing -- save_file_meta() verifies capability and nonce before calling this helper.
		if (! isset($_FILES[self::UPLOAD_FIELD_NAME]) || ! is_array($_FILES[self::UPLOAD_FIELD_NAME])) {
			return $file_data;
		}

		if (isset($_FILES[self::UPLOAD_FIELD_NAME]['name'])) {
			$file_data['name'] = sanitize_file_name(wp_unslash((string) $_FILES[self::UPLOAD_FIELD_NAME]['name']));
		}

		if (isset($_FILES[self::UPLOAD_FIELD_NAME]['type'])) {
			$file_data['type'] = sanitize_mime_type(wp_unslash((string) $_FILES[self::UPLOAD_FIELD_NAME]['type']));
		}

		if (isset($_FILES[self::UPLOAD_FIELD_NAME]['tmp_name'])) {
			// Sanitize without unslashing so Windows temp paths remain valid.
			$file_data['tmp_name'] = sanitize_text_field((string) $_FILES[self::UPLOAD_FIELD_NAME]['tmp_name']);
		}

		if (isset($_FILES[self::UPLOAD_FIELD_NAME]['error'])) {
			$file_data['error'] = absint(wp_unslash($_FILES[self::UPLOAD_FIELD_NAME]['error']));
		}

		if (isset($_FILES[self::UPLOAD_FIELD_NAME]['size'])) {
			$file_data['size'] = absint(wp_unslash($_FILES[self::UPLOAD_FIELD_NAME]['size']));
		}
		// phpcs:enable WordPress.Security.NonceVerification.Missing

		return $file_data;
	}

	/**
	 * Store an uploaded file in protected plugin-managed storage.
	 *
	 * @param array<string, int|string> $file_data Sanitized uploaded file data.
	 * @return array<string, int|string>|\WP_Error
	 */
	private function store_uploaded_file(array $file_data)
	{
		if (UPLOAD_ERR_OK !== (int) $file_data['error']) {
			return new \WP_Error(
				'cliapwo_upload_failed',
				__('The file upload failed. Please try again.', 'client-approval-workflow')
			);
		}

		$original_name = isset($file_data['name']) ? (string) $file_data['name'] : '';
		$tmp_name      = isset($file_data['tmp_name']) ? (string) $file_data['tmp_name'] : '';

		if ('' === $original_name || '' === $tmp_name) {
			return new \WP_Error(
				'cliapwo_upload_incomplete',
				__('The uploaded file data is incomplete.', 'client-approval-workflow')
			);
		}

		if (! is_uploaded_file($tmp_name)) {
			return new \WP_Error(
				'cliapwo_upload_invalid',
				__('The uploaded file could not be validated.', 'client-approval-workflow')
			);
		}

		$file_check    = wp_check_filetype_and_ext($tmp_name, $original_name);
		$allowed_mimes = get_allowed_mime_types();
		$extension     = is_array($file_check) && isset($file_check['ext']) ? sanitize_file_name((string) $file_check['ext']) : '';
		$mime_type     = is_array($file_check) && isset($file_check['type']) ? sanitize_mime_type((string) $file_check['type']) : '';

		if ('' === $extension || '' === $mime_type || ! in_array($mime_type, $allowed_mimes, true)) {
			return new \WP_Error(
				'cliapwo_upload_mime_not_allowed',
				__('That file type is not allowed.', 'client-approval-workflow')
			);
		}

		$storage_directory = self::ensure_storage_directory();

		if (is_wp_error($storage_directory)) {
			return $storage_directory;
		}

		$stored_file_name = sanitize_file_name(wp_generate_password(20, false, false) . '.' . $extension);
		$stored_file_name = wp_unique_filename($storage_directory, $stored_file_name);
		$destination_path = wp_normalize_path(trailingslashit($storage_directory) . $stored_file_name);

		$move_succeeded = self::move_uploaded_file_to_storage($tmp_name, $destination_path);

		if (! $move_succeeded || ! file_exists($destination_path)) {
			return new \WP_Error(
				'cliapwo_upload_move_failed',
				__('The uploaded file could not be moved into protected storage.', 'client-approval-workflow')
			);
		}

		$file_size     = filesize($destination_path);
		$relative_path = self::build_relative_path($stored_file_name);

		return array(
			'relative_path' => $relative_path,
			'original_name' => $original_name,
			'mime_type'     => $mime_type,
			'file_size'     => absint($file_size),
		);
	}

	/**
	 * Ensure the protected storage directory exists and has hardening files.
	 *
	 * @return string|\WP_Error
	 */
	private static function ensure_storage_directory()
	{
		$storage_directory = self::get_storage_directory_path();

		if (is_wp_error($storage_directory)) {
			return $storage_directory;
		}

		if (! is_dir($storage_directory) && ! wp_mkdir_p($storage_directory)) {
			return new \WP_Error(
				'cliapwo_storage_directory_creation_failed',
				__('The protected storage directory could not be created.', 'client-approval-workflow')
			);
		}

		$hardening_result = self::write_storage_hardening_files($storage_directory);

		if (is_wp_error($hardening_result)) {
			return $hardening_result;
		}

		return $storage_directory;
	}

	/**
	 * Move an uploaded file into protected storage using the WordPress filesystem API.
	 *
	 * @param string $source_path      Uploaded temporary file path.
	 * @param string $destination_path Protected destination path.
	 * @return bool
	 */
	private static function move_uploaded_file_to_storage($source_path, $destination_path)
	{
		if ('' === $source_path || '' === $destination_path) {
			return false;
		}

		if (! function_exists('WP_Filesystem')) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
		}

		WP_Filesystem();

		global $wp_filesystem;

		if (! $wp_filesystem instanceof \WP_Filesystem_Base) {
			return false;
		}

		return (bool) $wp_filesystem->move($source_path, $destination_path, false);
	}

	/**
	 * Resolve the protected storage directory path.
	 *
	 * @return string|\WP_Error
	 */
	private static function get_storage_directory_path()
	{
		$uploads = wp_upload_dir();

		if (! is_array($uploads) || ! empty($uploads['error']) || empty($uploads['basedir']) || ! is_string($uploads['basedir'])) {
			return new \WP_Error(
				'cliapwo_storage_directory_unavailable',
				__('The uploads directory is not available for protected files.', 'client-approval-workflow')
			);
		}

		return wp_normalize_path(trailingslashit($uploads['basedir']) . self::STORAGE_DIRECTORY_NAME);
	}

	/**
	 * Write server hardening files for the protected storage directory.
	 *
	 * Note: Nginx does not honor .htaccess, so equivalent server rules still
	 * need to be configured outside the plugin on Nginx-based hosts.
	 *
	 * @param string $storage_directory Absolute storage directory path.
	 * @return true|\WP_Error
	 */
	private static function write_storage_hardening_files($storage_directory)
	{
		$hardening_files = array(
			'index.php' => "<?php\n// Silence is golden.\n",
			'.htaccess' => "Options -Indexes\n<IfModule mod_authz_core.c>\nRequire all denied\n</IfModule>\n<IfModule !mod_authz_core.c>\nDeny from all\n</IfModule>\n",
		);

		foreach ($hardening_files as $file_name => $file_contents) {
			$file_path = wp_normalize_path(trailingslashit($storage_directory) . $file_name);

			if (file_exists($file_path)) {
				continue;
			}

			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- writing minimal hardening files is required for protected storage setup.
			$bytes_written = file_put_contents($file_path, $file_contents, LOCK_EX);

			if (false === $bytes_written) {
				return new \WP_Error(
					'cliapwo_storage_hardening_failed',
					__('The protected storage directory could not be hardened.', 'client-approval-workflow')
				);
			}
		}

		return true;
	}

	/**
	 * Build the stored relative path from a server-side file name.
	 *
	 * @param string $stored_file_name Stored file name.
	 * @return string
	 */
	private static function build_relative_path($stored_file_name)
	{
		return self::STORAGE_DIRECTORY_NAME . '/' . basename(sanitize_file_name($stored_file_name));
	}

	/**
	 * Validate a stored relative path.
	 *
	 * @param string $relative_path Relative file path.
	 * @return bool
	 */
	private static function is_valid_relative_path($relative_path)
	{
		$expected_prefix = self::STORAGE_DIRECTORY_NAME . '/';

		if (0 !== strpos($relative_path, $expected_prefix)) {
			return false;
		}

		$file_name = basename($relative_path);

		return '' !== $file_name && $expected_prefix . $file_name === $relative_path;
	}

	/**
	 * Delete the current stored file for a file post, if present.
	 *
	 * @param int $post_id File post ID.
	 * @return void
	 */
	private function delete_current_stored_file($post_id)
	{
		$file_path = self::get_stored_file_path($post_id);

		if ('' !== $file_path && file_exists($file_path)) {
			// phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink -- protected storage cleanup requires direct file deletion.
			unlink($file_path);
		}

		delete_post_meta($post_id, self::STORED_RELATIVE_PATH_META_KEY);
	}

	/**
	 * Delete any legacy attachment left over from pre-hardening builds.
	 *
	 * @param int $post_id File post ID.
	 * @return void
	 */
	private function delete_legacy_attachment($post_id)
	{
		$attachment_id = absint(get_post_meta($post_id, 'cliapwo_attachment_id', true));

		if ($attachment_id > 0) {
			wp_delete_attachment($attachment_id, true);
		}

		delete_post_meta($post_id, 'cliapwo_attachment_id');
		delete_post_meta($post_id, 'cliapwo_last_notified_attachment_id');
	}

	/**
	 * Fire an upload event once per stored file when the file is client-visible.
	 *
	 * @param int      $post_id          File post ID.
	 * @param \WP_Post $post             File post object.
	 * @param int      $client_id        Linked client ID.
	 * @param string   $stored_file_path Stored relative path.
	 * @return void
	 */
	private function maybe_dispatch_upload_event($post_id, $post, $client_id, $stored_file_path)
	{
		if (! $post instanceof \WP_Post || 'publish' !== $post->post_status) {
			return;
		}

		$client_id        = absint($client_id);
		$stored_file_path = ltrim(wp_normalize_path((string) $stored_file_path), '/');

		if ($client_id <= 0 || ! self::is_valid_relative_path($stored_file_path)) {
			return;
		}

		$last_notified_file_path = (string) get_post_meta($post_id, self::LAST_NOTIFIED_FILE_META_KEY, true);

		if ($stored_file_path === $last_notified_file_path) {
			return;
		}

		/**
		 * Fires when a file upload becomes visible to a client.
		 *
		 * @param int    $post_id          File post ID.
		 * @param int    $client_id        Client post ID.
		 * @param string $stored_file_path Stored relative path.
		 */
		do_action('cliapwo_file_uploaded', $post_id, $client_id, $stored_file_path);
		do_action('cliapwo_after_file_uploaded', $post_id, $client_id, $stored_file_path);
		update_post_meta($post_id, self::LAST_NOTIFIED_FILE_META_KEY, $stored_file_path);
	}
}
