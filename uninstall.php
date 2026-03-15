<?php
/**
 * Uninstall handler for SignoffFlow.
 *
 * @package ClientApprovalWorkflow
 */

if (! defined('WP_UNINSTALL_PLUGIN')) {
	exit;
}

$cliapwo_settings = get_option('cliapwo_settings', array());

if (! is_array($cliapwo_settings) || empty($cliapwo_settings['delete_data_on_uninstall'])) {
	return;
}

$cliapwo_uploads = wp_upload_dir();
$cliapwo_basedir = '';

if (is_array($cliapwo_uploads) && empty($cliapwo_uploads['error']) && ! empty($cliapwo_uploads['basedir']) && is_string($cliapwo_uploads['basedir'])) {
	$cliapwo_basedir = wp_normalize_path($cliapwo_uploads['basedir']);
}

$cliapwo_file_post_ids = get_posts(
	array(
		'post_type'              => 'cliapwo_file',
		'post_status'            => 'any',
		'posts_per_page'         => -1,
		'fields'                 => 'ids',
		'no_found_rows'          => true,
		'update_post_meta_cache' => false,
		'update_post_term_cache' => false,
	)
);

foreach ($cliapwo_file_post_ids as $cliapwo_file_post_id) {
	delete_transient('cliapwo_file_upload_error_' . (int) $cliapwo_file_post_id);

	$cliapwo_relative_path = get_post_meta((int) $cliapwo_file_post_id, 'cliapwo_stored_relative_path', true);

	if (is_string($cliapwo_relative_path) && '' !== $cliapwo_relative_path && '' !== $cliapwo_basedir) {
		$cliapwo_relative_path = ltrim(wp_normalize_path($cliapwo_relative_path), '/');

		if (0 === strpos($cliapwo_relative_path, 'cliapwo-private/')) {
			$cliapwo_file_name = basename($cliapwo_relative_path);
			$cliapwo_file_path = wp_normalize_path(trailingslashit($cliapwo_basedir) . 'cliapwo-private/' . $cliapwo_file_name);

			if (file_exists($cliapwo_file_path)) {
				// phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink -- uninstall cleanup must remove protected files from disk.
				unlink($cliapwo_file_path);
			}
		}
	}

	$cliapwo_attachment_id = absint(get_post_meta((int) $cliapwo_file_post_id, 'cliapwo_attachment_id', true));

	if ($cliapwo_attachment_id > 0) {
		wp_delete_attachment($cliapwo_attachment_id, true);
	}
}

$cliapwo_post_types = array(
	'cliapwo_client',
	'cliapwo_update',
	'cliapwo_file',
	'cliapwo_request',
	'cliapwo_event',
);

foreach ($cliapwo_post_types as $cliapwo_post_type) {
	$cliapwo_post_ids = get_posts(
		array(
			'post_type'              => $cliapwo_post_type,
			'post_status'            => 'any',
			'posts_per_page'         => -1,
			'fields'                 => 'ids',
			'no_found_rows'          => true,
			'update_post_meta_cache' => false,
			'update_post_term_cache' => false,
		)
	);

	foreach ($cliapwo_post_ids as $cliapwo_post_id) {
		wp_delete_post((int) $cliapwo_post_id, true);
	}
}

delete_option('cliapwo_settings');
delete_transient('cliapwo_plugin_activated');

$cliapwo_manager_user_ids = get_users(
	array(
		'fields'     => 'ids',
		'capability' => 'cliapwo_manage_portal',
	)
);

if (is_array($cliapwo_manager_user_ids)) {
	foreach ($cliapwo_manager_user_ids as $cliapwo_manager_user_id) {
		delete_transient('cliapwo_mail_debug_notice_' . (int) $cliapwo_manager_user_id);
	}
}

if ('' !== $cliapwo_basedir) {
	$cliapwo_private_directory = wp_normalize_path(trailingslashit($cliapwo_basedir) . 'cliapwo-private');

	if (is_dir($cliapwo_private_directory)) {
		$cliapwo_directory_files = array(
			wp_normalize_path(trailingslashit($cliapwo_private_directory) . 'index.php'),
			wp_normalize_path(trailingslashit($cliapwo_private_directory) . '.htaccess'),
		);

		foreach ($cliapwo_directory_files as $cliapwo_directory_file) {
			if (file_exists($cliapwo_directory_file)) {
				// phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink -- uninstall cleanup must remove protected storage hardening files from disk.
				unlink($cliapwo_directory_file);
			}
		}

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_rmdir -- uninstall cleanup removes the plugin-managed protected storage directory.
		rmdir($cliapwo_private_directory);
	}
}

$cliapwo_admin_role = get_role('administrator');

if ($cliapwo_admin_role instanceof WP_Role) {
	$cliapwo_admin_role->remove_cap('cliapwo_manage_portal');
	$cliapwo_admin_role->remove_cap('cliapwo_view_portal');
}

remove_role('cliapwo_client');
