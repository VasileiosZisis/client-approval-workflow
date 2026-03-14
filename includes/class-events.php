<?php

/**
 * Event logging and client email notifications.
 *
 * @package ClientApprovalWorkflow
 */

namespace ClientApprovalWorkflow;

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
	 * User-scoped transient prefix for admin mail debug notices.
	 */
	public const MAIL_DEBUG_NOTICE_TRANSIENT_PREFIX = 'cliapwo_mail_debug_notice_';

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
		add_action('cliapwo_request_created', array($this, 'handle_request_created'), 10, 2);
		add_action('cliapwo_update_created', array($this, 'handle_update_created'), 10, 2);
		add_action('cliapwo_file_uploaded', array($this, 'handle_file_uploaded'), 10, 3);
		add_action('admin_notices', array($this, 'render_mail_debug_notice'));
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
					'name'          => __('Event Log', 'client-approval-workflow'),
					'singular_name' => __('Event', 'client-approval-workflow'),
					'menu_name'     => __('Event Log', 'client-approval-workflow'),
					'view_item'     => __('View Event', 'client-approval-workflow'),
					'search_items'  => __('Search Events', 'client-approval-workflow'),
					'not_found'     => __('No events found.', 'client-approval-workflow'),
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
					'publish_posts'          => 'do_not_allow',
					'read_private_posts'     => 'cliapwo_manage_portal',
					'delete_posts'           => 'cliapwo_manage_portal',
					'delete_private_posts'   => 'cliapwo_manage_portal',
					'delete_published_posts' => 'cliapwo_manage_portal',
					'delete_others_posts'    => 'cliapwo_manage_portal',
					'edit_private_posts'     => 'cliapwo_manage_portal',
					'edit_published_posts'   => 'cliapwo_manage_portal',
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
			__('Update posted: %s', 'client-approval-workflow'),
			$update->post_title
		);

		$details = sprintf(
			/* translators: 1: client name, 2: update title */
			__("Client: %1\$s\nUpdate: %2\$s", 'client-approval-workflow'),
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
			__('[%1$s] New portal update for %2$s', 'client-approval-workflow'),
			wp_specialchars_decode(get_bloginfo('name'), ENT_QUOTES),
			$client->post_title
		);
		$message    = implode(
			"\n\n",
			array(
				__('A new update has been posted in your SignoffFlow portal.', 'client-approval-workflow'),
				sprintf(
					/* translators: %s: client name */
					__('Client: %s', 'client-approval-workflow'),
					$client->post_title
				),
				sprintf(
					/* translators: %s: update title */
					__('Update: %s', 'client-approval-workflow'),
					$update->post_title
				),
				sprintf(
					/* translators: %s: portal URL */
					__('Portal link: %s', 'client-approval-workflow'),
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
				__('update "%s"', 'client-approval-workflow'),
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
			__('Request created: %s', 'client-approval-workflow'),
			$request->post_title
		);

		$details = sprintf(
			/* translators: 1: client name, 2: request title */
			__("Client: %1\$s\nRequest: %2\$s", 'client-approval-workflow'),
			$client->post_title,
			$request->post_title
		);

		$this->create_event_entry($title, $details, 'request_created', $client_id, $request_id);

		if (! $this->should_send_notification('notify_requests')) {
			return;
		}

		$portal_url = $this->get_portal_url();
		$subject    = sprintf(
			/* translators: 1: site name, 2: client name */
			__('[%1$s] New request for %2$s', 'client-approval-workflow'),
			wp_specialchars_decode(get_bloginfo('name'), ENT_QUOTES),
			$client->post_title
		);
		$message    = implode(
			"\n\n",
			array(
				__('A new request has been added to your SignoffFlow portal.', 'client-approval-workflow'),
				sprintf(
					/* translators: %s: client name */
					__('Client: %s', 'client-approval-workflow'),
					$client->post_title
				),
				sprintf(
					/* translators: %s: request title */
					__('Request: %s', 'client-approval-workflow'),
					$request->post_title
				),
				sprintf(
					/* translators: %s: portal URL */
					__('Portal link: %s', 'client-approval-workflow'),
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
				__('request "%s"', 'client-approval-workflow'),
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
			__('File uploaded: %s', 'client-approval-workflow'),
			$file_name
		);

		$details = sprintf(
			/* translators: 1: client name, 2: file name */
			__("Client: %1\$s\nFile: %2\$s", 'client-approval-workflow'),
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
			__('[%1$s] New file available for %2$s', 'client-approval-workflow'),
			wp_specialchars_decode(get_bloginfo('name'), ENT_QUOTES),
			$client->post_title
		);
		$message    = implode(
			"\n\n",
			array(
				__('A new file has been uploaded to your SignoffFlow portal.', 'client-approval-workflow'),
				sprintf(
					/* translators: %s: client name */
					__('Client: %s', 'client-approval-workflow'),
					$client->post_title
				),
				sprintf(
					/* translators: %s: file name */
					__('File: %s', 'client-approval-workflow'),
					$file_name
				),
				sprintf(
					/* translators: %s: portal URL */
					__('Portal link: %s', 'client-approval-workflow'),
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
				__('file "%s"', 'client-approval-workflow'),
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
		$columns['cliapwo_event_type']   = __('Type', 'client-approval-workflow');
		$columns['cliapwo_event_client'] = __('Client', 'client-approval-workflow');
		$columns['cliapwo_event_object'] = __('Related item', 'client-approval-workflow');

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
			echo $client instanceof \WP_Post ? esc_html($client->post_title) : esc_html__('Unknown', 'client-approval-workflow');
			return;
		}

		if ('cliapwo_event_object' !== $column) {
			return;
		}

		$object = get_post(absint(get_post_meta($post_id, self::OBJECT_ID_META_KEY, true)));
		echo $object instanceof \WP_Post ? esc_html($object->post_title) : esc_html__('Unknown', 'client-approval-workflow');
	}

	/**
	 * Render the one-time admin notice for a mail attempt.
	 *
	 * @return void
	 */
	public function render_mail_debug_notice()
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

		$notice = get_transient(self::MAIL_DEBUG_NOTICE_TRANSIENT_PREFIX . $user_id);

		if (! is_array($notice) || empty($notice['message'])) {
			return;
		}

		delete_transient(self::MAIL_DEBUG_NOTICE_TRANSIENT_PREFIX . $user_id);
		?>
		<div class="notice notice-info is-dismissible">
			<p><?php echo esc_html((string) $notice['message']); ?></p>
		</div>
		<?php
	}

	/**
	 * Create an event log entry.
	 *
	 * @param string $title     Event title.
	 * @param string $content   Event details.
	 * @param string $type      Event type.
	 * @param int    $client_id Client post ID.
	 * @param int    $object_id Related object post ID.
	 * @return void
	 */
	private function create_event_entry($title, $content, $type, $client_id, $object_id)
	{
		$event_id = wp_insert_post(
			array(
				'post_type'    => self::POST_TYPE,
				'post_status'  => 'publish',
				'post_title'   => wp_strip_all_tags($title),
				'post_content' => $content,
			),
			true
		);

		if (is_wp_error($event_id)) {
			return;
		}

		update_post_meta($event_id, self::TYPE_META_KEY, sanitize_key($type));
		update_post_meta($event_id, self::CLIENT_META_KEY, absint($client_id));
		update_post_meta($event_id, self::OBJECT_ID_META_KEY, absint($object_id));
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
			return __('Email attempt', 'client-approval-workflow');
		}

		if ('request_created' === $event_type) {
			return __('Request created', 'client-approval-workflow');
		}

		if ('file_uploaded' === $event_type) {
			return __('File uploaded', 'client-approval-workflow');
		}

		if ('update_created' === $event_type) {
			return __('Update created', 'client-approval-workflow');
		}

		return __('Event', 'client-approval-workflow');
	}

	/**
	 * Record a wp_mail() attempt in the event log and next admin response.
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
			? __('accepted by wp_mail()', 'client-approval-workflow')
			: __('wp_mail() returned false', 'client-approval-workflow');
		$recipients   = implode(', ', $emails);
		$title        = sprintf(
			/* translators: %s: object label */
			__('Email attempt: %s', 'client-approval-workflow'),
			$context_label
		);
		$content      = implode(
			"\n",
			array(
				sprintf(
					/* translators: %s: object label */
					__('Context: %s', 'client-approval-workflow'),
					$context_label
				),
				sprintf(
					/* translators: %s: mail result */
					__('Result: %s', 'client-approval-workflow'),
					$result_label
				),
				sprintf(
					/* translators: %s: recipient list */
					__('Recipients: %s', 'client-approval-workflow'),
					$recipients
				),
			)
		);

		$this->create_event_entry($title, $content, 'email_attempt', $client_id, $object_id);
		$this->set_mail_debug_notice($context_label, $recipients, $result_label);
	}

	/**
	 * Store a one-time admin notice about a mail attempt for the current user.
	 *
	 * @param string $context_label Human-readable object label.
	 * @param string $recipients    Comma-separated recipient list.
	 * @param string $result_label  Human-readable mail result.
	 * @return void
	 */
	private function set_mail_debug_notice($context_label, $recipients, $result_label)
	{
		$user_id = get_current_user_id();

		if ($user_id <= 0 || ! current_user_can('cliapwo_manage_portal')) {
			return;
		}

		$message = sprintf(
			/* translators: 1: object label, 2: mail result, 3: recipient list */
			__('SignoffFlow attempted wp_mail() for %1$s. Result: %2$s. Recipients: %3$s', 'client-approval-workflow'),
			$context_label,
			$result_label,
			$recipients
		);

		set_transient(
			self::MAIL_DEBUG_NOTICE_TRANSIENT_PREFIX . $user_id,
			array(
				'message' => $message,
			),
			MINUTE_IN_SECONDS
		);
	}
}
