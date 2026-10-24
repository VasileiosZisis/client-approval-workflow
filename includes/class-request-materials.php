<?php
/**
 * Internal request review context and protected-file associations.
 *
 * @package VzisisClientApprovalWorkflow
 */

namespace Vzisis\ClientApprovalWorkflow;

defined('ABSPATH') || exit;

/** Records descriptive review context, never binary archives or access grants. @internal */
class Request_Materials
{
	const META_KEY = 'cliapwo_request_material';
	const RESPONSE_META_KEY = 'cliapwo_request_response_material';
	const EVENT_META_KEY = 'cliapwo_event_material';
	const PREVIOUS_META_KEY = 'cliapwo_event_previous_material';
	const CYCLE_META_KEY = 'cliapwo_request_review_cycle';
	const TOKEN_FIELD = 'cliapwo_review_token';
	const EDITOR_TOKEN = 'cliapwo_material_editor_token';
	const EVENT_TYPE = 'request_material_changed';

	/**
	 * Connection-owned, reentrant file locks.
	 *
	 * @var array<int,int>
	 */
	private static $locks = array();

	/**
	 * Shared operation mutex nesting depth.
	 *
	 * @var int
	 */
	private static $operation_depth = 0;

	/** One database mutex also works on MySQL versions supporting only one named lock. */
	public static function lock_operation()
	{
		global $wpdb;
		if (self::$operation_depth > 0) {
			++self::$operation_depth;
			return true;
		}
		$name = 'cliapwo_operation_' . md5(DB_NAME . ':' . $wpdb->postmeta);
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- connection-owned mutex cannot be cached.
		if ('1' !== (string) $wpdb->get_var($wpdb->prepare('SELECT GET_LOCK(%s, 0)', $name))) {
			return false;
		}
		self::$operation_depth = 1;
		return true;
	}

	/** Release the shared mutex only when the outer operation completes. */
	public static function unlock_operation()
	{
		global $wpdb;
		if (self::$operation_depth < 1 || --self::$operation_depth > 0) {
			return;
		}
		$name = 'cliapwo_operation_' . md5(DB_NAME . ':' . $wpdb->postmeta);
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- release only this connection's mutex.
		$wpdb->get_var($wpdb->prepare('SELECT RELEASE_LOCK(%s)', $name));
	}

	/** Register only hooked processing. */
	public function register()
	{
		add_action('add_meta_boxes', array($this, 'add_meta_box'));
		add_action('admin_notices', array($this, 'notice'));
		add_filter('wp_insert_post_empty_content', array($this, 'lock_file_save'), 20, 2);
		add_action('wp_after_insert_post', array($this, 'unlock_file_save'));
		add_filter('pre_delete_post', array($this, 'lock_file_delete'), 20, 3);
		add_filter('pre_trash_post', array($this, 'lock_file_delete'), 20, 2);
		add_filter('pre_untrash_post', array($this, 'lock_file_delete'), 20, 2);
		add_action('deleted_post', array($this, 'unlock_file_save'));
	}

	/**
	 * Locks spanning core file writes and save hooks.
	 *
	 * @var array<int,bool>
	 */
	private $core_locks = array();

	/** Serialize a core file write before its database mutation. */
	public function lock_file_save($is_empty, $data)
	{
		$id = isset($data['ID']) ? absint($data['ID']) : 0;
		if ($is_empty || ! $id || ! in_array(get_post_type($id), array(Files::POST_TYPE, Requests::POST_TYPE), true) || isset($this->core_locks[$id])) {
			return $is_empty;
		}
		if (! self::lock_files(array($id))) {
			return true;
		}
		$this->core_locks[$id] = true;
		return $is_empty;
	}

	/** Serialize permanent deletion, including managed asset cleanup. */
	public function lock_file_delete($delete, $post)
	{
		if (null !== $delete || ! $post instanceof \WP_Post || ! in_array($post->post_type, array(Files::POST_TYPE, Requests::POST_TYPE), true)) {
			return $delete;
		}
		if (! self::lock_files(array($post->ID))) {
			return false;
		}
		$this->core_locks[$post->ID] = true;
		return $delete;
	}

	/** Release the lock held across a core file mutation. */
	public function unlock_file_save($post_id)
	{
		if (isset($this->core_locks[$post_id])) {
			unset($this->core_locks[$post_id]);
			self::unlock_files(array($post_id));
		}
	}

	/** Acquire file locks in stable order, without waiting on another writer. */
	public static function lock_files(array $ids)
	{
		$ids = array_values(array_unique(array_filter(array_map('absint', $ids))));
		sort($ids, SORT_NUMERIC);
		$acquired = array();
		foreach ($ids as $id) {
			if (! self::lock_operation()) {
				self::unlock_files($acquired);
				return false;
			}
			if (! isset(self::$locks[$id])) {
				self::$locks[$id] = 0;
			}
			++self::$locks[$id];
			$acquired[] = $id;
			clean_post_cache($id);
		}
		return true;
	}

	/** Release only this operation's file locks. */
	public static function unlock_files(array $ids)
	{
		foreach (array_unique(array_map('absint', $ids)) as $id) {
			if (! isset(self::$locks[$id])) {
				continue;
			}
			if (--self::$locks[$id] < 1) {
				unset(self::$locks[$id]);
			}
			self::unlock_operation();
		}
	}

	/** Return the saved, client-bound descriptive context. */
	public static function saved($request_id)
	{
		return self::safe_context(get_post_meta($request_id, self::META_KEY, true), Requests::get_client_id_for_request($request_id));
	}

	/** Allowlist stored snapshots; never return arbitrary event metadata. */
	public static function safe_context($context, $client_id)
	{
		if (! is_array($context) || absint($client_id) !== absint(isset($context['client_id']) ? $context['client_id'] : 0)) {
			return null;
		}
		$result = array('client_id' => absint($client_id), 'reference' => '', 'revision' => '', 'files' => array());
		foreach (array('reference', 'revision') as $key) {
			$result[$key] = isset($context[$key]) && is_string($context[$key]) ? sanitize_text_field($context[$key]) : '';
		}
		foreach (isset($context['files']) && is_array($context['files']) ? $context['files'] : array() as $file) {
			if (! is_array($file) || empty($file['id'])) {
				continue;
			}
			$item = array('id' => absint($file['id']), 'size' => isset($file['size']) ? absint($file['size']) : 0, 'available' => ! empty($file['available']));
			foreach (array('title', 'name', 'version', 'mime') as $key) {
				$item[$key] = isset($file[$key]) && is_string($file[$key]) ? sanitize_text_field($file[$key]) : '';
			}
			$result['files'][] = $item;
		}
		return $result;
	}

	/** Read an eligible managed file; the opaque version never contains its path. */
	public static function file_context($file_id, $client_id)
	{
		$post = get_post($file_id);
		$path = Files::get_stored_file_path($file_id);
		$client = get_post($client_id);
		if (! $post instanceof \WP_Post || Files::POST_TYPE !== $post->post_type || 'publish' !== $post->post_status
			|| ! $client instanceof \WP_Post || Clients::POST_TYPE !== $client->post_type || 'publish' !== $client->post_status
			|| absint($client_id) !== Files::get_client_id_for_file($file_id) || '' === $path || ! is_file($path) || ! is_readable($path)) {
			return null;
		}
		return array(
			'id' => absint($file_id), 'title' => sanitize_text_field($post->post_title),
			'name' => sanitize_file_name((string) get_post_meta($file_id, Files::ORIGINAL_FILENAME_META_KEY, true)),
			'version' => hash('sha256', Files::get_stored_relative_path_for_file($file_id) . ':' . get_post_meta($file_id, Files::UPLOADED_AT_META_KEY, true)),
			'size' => absint(get_post_meta($file_id, Files::FILE_SIZE_META_KEY, true)),
			'mime' => sanitize_mime_type((string) get_post_meta($file_id, Files::MIME_TYPE_META_KEY, true)), 'available' => true,
		);
	}

	/** Get current availability without rewriting descriptive association snapshots. */
	public static function current($request_id)
	{
		$client_id = Requests::get_client_id_for_request($request_id);
		$context = self::saved($request_id);
		if (null === $context) {
			$context = array('client_id' => $client_id, 'reference' => '', 'revision' => '', 'files' => array());
		}
		foreach ($context['files'] as &$file) {
			$live = self::file_context($file['id'], $client_id);
			$file['available'] = is_array($live) && hash_equals($file['version'], $live['version']);
		}
		unset($file);
		return $context;
	}

	/** Return associated IDs, including unavailable records. */
	public static function file_ids($request_id)
	{
		$context = self::saved($request_id);
		return is_array($context) ? array_column($context['files'], 'id') : array();
	}

	/** Batch-prime linked file records for the portal's already bounded request list. */
	public static function prime_files(array $request_ids)
	{
		$ids = array();
		foreach ($request_ids as $request_id) {
			$ids = array_merge($ids, self::file_ids($request_id));
		}
		$ids = array_values(array_unique($ids));
		if ($ids) {
			get_posts(array('post_type' => Files::POST_TYPE, 'post_status' => array_keys(get_post_stati()), 'post__in' => $ids, 'posts_per_page' => count($ids), 'update_post_meta_cache' => true, 'update_post_term_cache' => false));
		}
	}

	/** Bind forms to the current viewer, client, review material, and response cycle. */
	public static function token($request_id)
	{
		return hash_hmac('sha256', wp_json_encode(array(absint($request_id), get_current_user_id(), self::current($request_id), (string) get_post_meta($request_id, self::CYCLE_META_KEY, true))), wp_salt('nonce'));
	}

	/** Determine whether an asset has a current association anywhere, including trash. */
	public static function is_linked($file_id)
	{
		$page = 1;
		do {
			$ids = get_posts(array('post_type' => Requests::POST_TYPE, 'post_status' => array_keys(get_post_stati()), 'fields' => 'ids', 'posts_per_page' => 100, 'paged' => $page++, 'orderby' => 'ID', 'order' => 'ASC', 'meta_key' => self::META_KEY)); // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- bounded internal associations scan, used only during file replacement.
			foreach ($ids as $id) {
				if (in_array(absint($file_id), self::file_ids($id), true)) {
					return true;
				}
			}
			$batch_count = count($ids);
		} while (100 === $batch_count);
		return false;
	}

	/** Save association and client together; caller holds the request lock and verified nonce/capability. */
	public static function save_from_post($request_id, $client_id)
	{
		// phpcs:disable WordPress.Security.NonceVerification.Missing -- Requests::save_request_meta verifies nonce and capability before this internal call.
		$old_client = Requests::get_client_id_for_request($request_id);
		$old = self::saved($request_id);
		$next = $old;
		$submitted = isset($_POST[self::EDITOR_TOKEN]);
		if ($submitted && (! is_string($_POST[self::EDITOR_TOKEN]) || ! hash_equals(self::token($request_id), sanitize_text_field(wp_unslash($_POST[self::EDITOR_TOKEN]))))) {
			return false;
		}
		if ($old_client !== $client_id) {
			$next = array('client_id' => $client_id, 'reference' => '', 'revision' => '', 'files' => array());
			if (0 === $old_client && null === $old && $submitted) {
				foreach (array('reference', 'revision') as $key) {
					$field = 'cliapwo_material_' . $key;
					if (! isset($_POST[$field]) || ! is_string($_POST[$field])) {
						return false;
					}
					$next[$key] = trim(sanitize_text_field(wp_unslash($_POST[$field])));
				}
			}
		} elseif ($submitted) {
			$next = array('client_id' => $client_id, 'reference' => '', 'revision' => '', 'files' => is_array($old) ? $old['files'] : array());
			foreach (array('reference', 'revision') as $key) {
				$field = 'cliapwo_material_' . $key;
				if (! isset($_POST[$field]) || ! is_string($_POST[$field])) {
					return false;
				}
				$next[$key] = trim(sanitize_text_field(wp_unslash($_POST[$field])));
			}
			foreach (array('cliapwo_material_add', 'cliapwo_material_remove') as $field) {
				if (isset($_POST[$field]) && (! is_array($_POST[$field]) || count(array_filter($_POST[$field], 'is_scalar')) !== count($_POST[$field]))) {
					return false;
				}
				foreach (isset($_POST[$field]) ? $_POST[$field] : array() as $id) {
					if (! preg_match('/^[1-9][0-9]*$/D', (string) $id) || (string) absint($id) !== (string) $id) {
						return false;
					}
				}
			}
			$remove = isset($_POST['cliapwo_material_remove']) ? array_map('absint', wp_unslash($_POST['cliapwo_material_remove'])) : array();
			$next['files'] = array_values(array_filter($next['files'], static function ($file) use ($remove) {
				return ! in_array($file['id'], $remove, true);
			}));
		}
		$add = $submitted && $old_client === $client_id && isset($_POST['cliapwo_material_add']) ? array_map('absint', wp_unslash($_POST['cliapwo_material_add'])) : array();
		// phpcs:enable WordPress.Security.NonceVerification.Missing
		$ids = array_values(array_unique(array_merge(self::file_ids($request_id), $add)));
		if (! self::lock_files($ids)) {
			return false;
		}
		try {
			if ($submitted && ! hash_equals(self::token($request_id), sanitize_text_field(wp_unslash($_POST[self::EDITOR_TOKEN])))) { // phpcs:ignore WordPress.Security.NonceVerification.Missing -- verified by the caller; recheck optimistic state after file locks.
				return false;
			}
			foreach ($add as $file_id) {
				$file = self::file_context($file_id, $client_id);
				if (null === $file) {
					return false;
				}
				if (! in_array($file_id, array_column($next['files'], 'id'), true)) {
					$next['files'][] = $file;
				}
			}
			$changed = $old_client !== $client_id || (null !== $next && $old !== $next && (! empty($next['files']) || '' !== $next['reference'] || '' !== $next['revision'] || null !== $old));
			if (! $changed) {
				return true;
			}
			if (Requests::is_resolved_status(Requests::get_status_for_request($request_id))) {
				return false;
			}
			if ($old_client !== $client_id && ! metadata_exists('post', $request_id, Requests::RESPONSE_CLIENT_META_KEY)) {
				// Preserve reliable legacy response ownership before changing the
				// relationship. Unreliable/partial old summaries must remain private.
				if (! Events::backfill_request_history_if_needed($request_id)) {
					return false;
				}
				if (! metadata_exists('post', $request_id, Requests::RESPONSE_CLIENT_META_KEY)) {
					update_post_meta($request_id, Requests::RESPONSE_CLIENT_META_KEY, 0);
					if (! metadata_exists('post', $request_id, Requests::RESPONSE_CLIENT_META_KEY) || 0 !== absint(get_post_meta($request_id, Requests::RESPONSE_CLIENT_META_KEY, true))) {
						return false;
					}
				}
			}
			$keys = array(Requests::CLIENT_META_KEY, self::META_KEY, self::CYCLE_META_KEY);
			$before = array();
			foreach ($keys as $key) {
				$before[$key] = array(metadata_exists('post', $request_id, $key), get_post_meta($request_id, $key, true));
			}
			$cycle = wp_generate_uuid4();
			update_post_meta($request_id, Requests::CLIENT_META_KEY, $client_id);
			update_post_meta($request_id, self::META_KEY, wp_slash($next));
			update_post_meta($request_id, self::CYCLE_META_KEY, $cycle);
			$ok = Requests::get_client_id_for_request($request_id) === $client_id && get_post_meta($request_id, self::META_KEY, true) === $next && get_post_meta($request_id, self::CYCLE_META_KEY, true) === $cycle;
			if ($ok && ('1' === (string) get_post_meta($request_id, Requests::NOTIFIED_META_KEY, true) || Events::request_creation_record($request_id))) {
				$ok = Events::record_material_change($request_id, $old, $next) > 0;
			} elseif ($ok && 'publish' === get_post_status($request_id) && $client_id > 0) {
				$ok = Events::ensure_request_creation_record($request_id, $client_id) > 0;
			}
			if (! $ok) {
				foreach ($before as $key => $value) {
					if ($value[0]) {
						update_post_meta($request_id, $key, wp_slash($value[1]));
					} else {
						delete_post_meta($request_id, $key);
					}
				}
			}
			return $ok;
		} finally {
			self::unlock_files($ids);
		}
	}

	/** Add the request-only editor panel. */
	public function add_meta_box()
	{
		add_meta_box('cliapwo_request_material', __('Review material', 'signoffflow-client-approval-workflow'), array($this, 'render_meta_box'), Requests::POST_TYPE, 'normal');
	}

	/** Render saved selections separately from a bounded page of additional files. */
	public function render_meta_box($post)
	{
		if (! current_user_can('cliapwo_manage_portal')) {
			return;
		}
		$context = self::current($post->ID);
		$locked = Requests::is_resolved_status(Requests::get_status_for_request($post->ID));
		echo '<input type="hidden" name="' . esc_attr(self::EDITOR_TOKEN) . '" value="' . esc_attr(self::token($post->ID)) . '" />';
		if ($locked) {
			echo '<p>' . esc_html__('Reopen and save this request before changing its review material.', 'signoffflow-client-approval-workflow') . '</p>';
			foreach (array('reference', 'revision') as $key) {
				echo '<input type="hidden" name="cliapwo_material_' . esc_attr($key) . '" value="' . esc_attr($context[$key]) . '" />';
			}
			self::render_context($context);
			return;
		}
		foreach (array('reference' => __('Material reference', 'signoffflow-client-approval-workflow'), 'revision' => __('Revision label', 'signoffflow-client-approval-workflow')) as $key => $label) {
			echo '<p><label for="cliapwo_material_' . esc_attr($key) . '">' . esc_html($label) . '</label><input class="widefat" id="cliapwo_material_' . esc_attr($key) . '" name="cliapwo_material_' . esc_attr($key) . '" type="text" value="' . esc_attr($context[$key]) . '" /></p>';
		}
		echo '<p class="description">' . esc_html__('Optional. Identify the material and revision being reviewed. These labels are preserved with each response, not permanent file archives or proof of review.', 'signoffflow-client-approval-workflow') . '</p>';
		foreach ($context['files'] as $file) {
			echo '<p><label><input type="checkbox" name="cliapwo_material_remove[]" value="' . esc_attr($file['id']) . '" /> ' . esc_html__('Remove link:', 'signoffflow-client-approval-workflow') . ' ' . esc_html($file['title']) . '</label>' . (! $file['available'] ? ' — ' . esc_html__('Unavailable', 'signoffflow-client-approval-workflow') : '') . '</p>';
		}
		if (! $context['client_id']) {
			echo '<p>' . esc_html__('Select a client and save the request before linking protected files.', 'signoffflow-client-approval-workflow') . '</p>';
			return;
		}
		$page = 1;
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- display-only pagination, gated by the core editor capability and revalidated on save.
		if (isset($_GET['cliapwo_material_page'], $_GET['cliapwo_material_page_nonce']) && is_scalar($_GET['cliapwo_material_page']) && is_string($_GET['cliapwo_material_page_nonce']) && wp_verify_nonce(sanitize_text_field(wp_unslash($_GET['cliapwo_material_page_nonce'])), 'cliapwo_material_page_' . $post->ID)) {
			$page = max(1, absint($_GET['cliapwo_material_page']));
		}
		// phpcs:enable WordPress.Security.NonceVerification.Recommended
		$query = Files::get_files_query_for_client($context['client_id'], array('posts_per_page' => 20, 'paged' => $page));
		$candidates = 0;
		echo '<fieldset><legend>' . esc_html__('Add protected files', 'signoffflow-client-approval-workflow') . '</legend>';
		foreach ($query->posts as $file) {
			if (in_array($file->ID, self::file_ids($post->ID), true) || null === self::file_context($file->ID, $context['client_id'])) {
				continue;
			}
			echo '<p><label><input type="checkbox" name="cliapwo_material_add[]" value="' . esc_attr($file->ID) . '" /> ' . esc_html($file->post_title) . '</label></p>';
			++$candidates;
		}
		if (0 === $candidates) {
			echo '<p>' . esc_html__('No additional eligible files on this page.', 'signoffflow-client-approval-workflow') . '</p>';
		}
		echo '</fieldset><p class="description">' . esc_html__('Create files in SignoffFlow > Files. Linked files cannot be replaced in place; create a new file for each revision. Save changes before changing file-picker pages.', 'signoffflow-client-approval-workflow') . '</p>';
		$last_page = $query->max_num_pages > 1 ? min((int) $query->max_num_pages, $page + 1) : 0;
		for ($number = max(1, $page - 1); $number <= $last_page; ++$number) {
			$url = wp_nonce_url(add_query_arg(array('post' => $post->ID, 'action' => 'edit', 'cliapwo_material_page' => $number), admin_url('post.php')), 'cliapwo_material_page_' . $post->ID, 'cliapwo_material_page_nonce');
			/* translators: %d: file picker page number. */
			echo '<a class="button" href="' . esc_url($url) . '">' . esc_html(sprintf(__('Files page %d', 'signoffflow-client-approval-workflow'), $number)) . '</a> ';
		}
	}

	/** Render only descriptive allowlisted snapshots; download links are current-context only. */
	public static function render_context($context, $request_id = 0)
	{
		if (! is_array($context)) {
			echo '<p class="cliapwo-review-material">' . esc_html__('Review context not recorded', 'signoffflow-client-approval-workflow') . '</p>';
			return;
		}
		echo '<div class="cliapwo-review-material">';
		if ($request_id && ($context['files'] || '' !== $context['reference'] || '' !== $context['revision'])) {
			echo '<h3>' . esc_html__('Review material', 'signoffflow-client-approval-workflow') . '</h3>';
		}
		foreach (array('reference' => __('Material:', 'signoffflow-client-approval-workflow'), 'revision' => __('Revision:', 'signoffflow-client-approval-workflow')) as $key => $label) {
			if ('' !== $context[$key]) {
				echo '<p><strong>' . esc_html($label) . '</strong> ' . esc_html($context[$key]) . '</p>';
			}
		}
		if (! empty($context['files'])) {
			echo '<ul>';
			foreach ($context['files'] as $file) {
				echo '<li>';
				$label = $file['title'] . ('' !== $file['name'] ? ' — ' . $file['name'] : '');
				if ($request_id && $file['available']) {
					echo '<a href="' . esc_url(self::download_url($request_id, $file)) . '">' . esc_html($label) . '</a>';
				} else {
					echo esc_html($label);
				}
				echo ' <span>(' . esc_html(size_format($file['size'])) . ')</span>';
				if (! $file['available']) {
					echo ' — ' . esc_html__('Unavailable; approval requires staff repair.', 'signoffflow-client-approval-workflow');
				}
				echo '</li>';
			}
			echo '</ul>';
		} elseif ('' === $context['reference'] && '' === $context['revision'] && ! $request_id) {
			echo '<p>' . esc_html__('No review material specified.', 'signoffflow-client-approval-workflow') . '</p>';
		}
		echo '</div>';
	}

	/** Request-scoped links never reuse a general file nonce. */
	public static function download_url($request_id, array $file)
	{
		return wp_nonce_url(add_query_arg(array('action' => Files::DOWNLOAD_ACTION, 'cliapwo_file_id' => $file['id'], 'cliapwo_request_id' => absint($request_id), 'cliapwo_file_revision' => $file['version']), admin_url('admin-post.php')), self::download_action($request_id, $file['id'], $file['version']), Files::DOWNLOAD_NONCE_NAME);
	}

	/** Bind download intent to all three identifiers. */
	public static function download_action($request_id, $file_id, $version)
	{
		return 'cliapwo_request_file_' . absint($request_id) . '_' . absint($file_id) . '_' . $version;
	}

	/** Revalidate a current request association; caller verified the bound download nonce. */
	public static function can_download($request_id, $file_id, $version)
	{
		$request = get_post($request_id);
		$client = Requests::get_client_id_for_request($request_id);
		if (! $request instanceof \WP_Post || Requests::POST_TYPE !== $request->post_type || 'publish' !== $request->post_status || ! Clients::user_can_view_client($client)) {
			return false;
		}
		foreach (self::current($request_id)['files'] as $file) {
			if (absint($file_id) === $file['id'] && $file['available'] && hash_equals($file['version'], $version)) {
				return true;
			}
		}
		return false;
	}

	/** Show scoped, user-specific errors without echoing submitted input. */
	public function notice()
	{
		$screen = get_current_screen();
		$key = 'cliapwo_material_notice_' . get_current_user_id();
		if (! $screen instanceof \WP_Screen || Requests::POST_TYPE !== $screen->post_type || ! current_user_can('cliapwo_manage_portal') || ! get_transient($key)) {
			return;
		}
		delete_transient($key);
		echo '<div class="notice notice-error"><p>' . esc_html__('Review material was not saved. Reopen and save resolved requests first. Otherwise refresh the editor, select valid files for the saved client, and retry. Existing material was preserved.', 'signoffflow-client-approval-workflow') . '</p></div>';
	}
}
