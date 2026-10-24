<?php

use Vzisis\ClientApprovalWorkflow\Clients;
use Vzisis\ClientApprovalWorkflow\Events;
use Vzisis\ClientApprovalWorkflow\Files;
use Vzisis\ClientApprovalWorkflow\Request_Materials as Materials;
use Vzisis\ClientApprovalWorkflow\Requests;
use Vzisis\ClientApprovalWorkflow\Request_Dates;
use Vzisis\ClientApprovalWorkflow\Settings;

/** Request-linked review context, stale forms, and protected asset regressions. */
class RequestMaterialsTest extends Cliapwo_Test_Case
{
	private $files = array();

	public function set_up()
	{
		parent::set_up();
		$settings = Settings::get_default_settings();
		foreach (array('notify_requests', 'notify_files', 'notify_updates') as $key) {
			$settings[$key] = false;
		}
		update_option(Settings::OPTION_KEY, $settings);
	}

	public function tear_down()
	{
		$_POST = array();
		foreach ($this->files as $id) {
			wp_delete_post($id, true);
		}
		parent::tear_down();
	}

	private function file($client, $name = 'homepage.txt')
	{
		$directory = new ReflectionMethod(Files::class, 'ensure_storage_directory');
		if (PHP_VERSION_ID < 80100) {
			$directory->setAccessible(true);
		}
		$root = $directory->invoke(null);
		$this->assertIsString($root);
		$basename = 'cliapwo-qa-' . wp_generate_uuid4() . '.txt';
		file_put_contents($root . '/' . $basename, 'Original review material.');
		$id = self::factory()->post->create(array('post_type' => Files::POST_TYPE, 'post_status' => 'publish', 'post_title' => $name));
		update_post_meta($id, Files::CLIENT_META_KEY, $client);
		update_post_meta($id, Files::STORED_RELATIVE_PATH_META_KEY, 'cliapwo-private/' . $basename);
		update_post_meta($id, Files::ORIGINAL_FILENAME_META_KEY, $name);
		update_post_meta($id, Files::FILE_SIZE_META_KEY, 25);
		update_post_meta($id, Files::MIME_TYPE_META_KEY, 'text/plain');
		update_post_meta($id, Files::UPLOADED_AT_META_KEY, '2026-01-01 00:00:00');
		$this->files[] = $id;
		return $id;
	}

	private function save($request, $client, $reference = 'Homepage concepts', $revision = 'v1', array $add = array(), array $remove = array(), $status = 'open', $due = null)
	{
		wp_set_current_user($this->administrator_id);
		$_POST = array(
			Requests::SAVE_NONCE_NAME => wp_create_nonce(Requests::SAVE_NONCE_ACTION),
			'cliapwo_request_client_id' => (string) $client,
			'cliapwo_request_status' => $status,
			Materials::EDITOR_TOKEN => Materials::token($request),
			'cliapwo_material_reference' => wp_slash($reference),
			'cliapwo_material_revision' => wp_slash($revision),
			'cliapwo_material_add' => $add,
			'cliapwo_material_remove' => $remove,
		);
		if (null !== $due) {
			$_POST[Request_Dates::META_KEY] = $due;
		}
		(new Requests())->save_request_meta($request, get_post($request));
		$_POST = array();
	}

	private function token_for($request, $user)
	{
		$previous = get_current_user_id();
		wp_set_current_user($user);
		$token = Materials::token($request);
		wp_set_current_user($previous);
		return $token;
	}

	public function test_creation_material_changes_and_unchanged_saves_have_exact_events()
	{
		$client = $this->create_client();
		$request = $this->create_request($client);
		$file = $this->file($client);
		$this->save($request, $client, 'Homepage', 'v1', array($file));
		$events = $this->get_request_events($request, $client);
		$this->assertCount(1, $events);
		$this->assertSame(Events::TYPE_REQUEST_CREATED, get_post_meta($events[0]->ID, Events::TYPE_META_KEY, true));
		$this->assertSame('v1', get_post_meta($events[0]->ID, Materials::EVENT_META_KEY, true)['revision']);
		$this->save($request, $client, 'Homepage', 'v1');
		$this->assertCount(1, $this->get_request_events($request, $client));
		$this->save($request, $client, 'Homepage', 'v2');
		$events = $this->get_request_events($request, $client);
		$this->assertCount(2, $events);
		$this->assertSame(Materials::EVENT_TYPE, get_post_meta($events[1]->ID, Events::TYPE_META_KEY, true));
		$this->save($request, $client, 'Homepage', 'v2', array(), array($file));
		$this->assertSame(array(), Materials::file_ids($request));
		$this->assertFileExists(Files::get_stored_file_path($file));
	}

	public function test_invalid_selection_is_atomic_and_cross_client_files_never_link()
	{
		$a = $this->create_client();
		$b = $this->create_client('Other account');
		$request = $this->create_request($a);
		$good = $this->file($a);
		$bad = $this->file($b);
		$this->save($request, $a, 'Original', 'v1', array($good));
		$before = Materials::saved($request);
		$this->save($request, $a, 'Forged', 'v2', array($bad), array($good), 'approved');
		$this->assertSame($before, Materials::saved($request));
		$this->assertSame('open', Requests::get_status_for_request($request));
		$this->assertCount(1, $this->get_request_events($request, $a));
	}

	public function test_resolved_edits_require_a_separately_saved_reopen_and_preserve_response()
	{
		$client = $this->create_client();
		$user = $this->create_client_user($client);
		$request = $this->create_request($client);
		$one = $this->file($client, 'original.txt');
		$two = $this->file($client, 'revision.txt');
		$this->save($request, $client, 'Homepage', 'v1', array($one));
		$this->assertTrue($this->transition_request($request, 'approved', array('actor_id' => $user, 'is_client_response' => true, 'actor_type' => Events::ACTOR_TYPE_CLIENT)));
		$snapshot = get_post_meta($request, Materials::RESPONSE_META_KEY, true);
		$this->save($request, $client, 'Homepage', 'v2', array($two), array($one), 'open');
		$this->assertSame('approved', Requests::get_status_for_request($request));
		$this->assertSame('v1', Materials::saved($request)['revision']);
		$this->assertTrue($this->transition_request($request, 'open'));
		$this->save($request, $client, 'Homepage', 'v2', array($two), array($one));
		$this->assertTrue($this->transition_request($request, 'approved', array('actor_id' => $user, 'is_client_response' => true, 'actor_type' => Events::ACTOR_TYPE_CLIENT)));
		$this->assertSame('v2', get_post_meta($request, Materials::RESPONSE_META_KEY, true)['revision']);
		$history = $this->get_request_events($request, $client);
		$this->assertSame($snapshot, get_post_meta($history[1]->ID, Materials::EVENT_META_KEY, true));
	}

	public function test_stale_and_missing_response_tokens_fail_without_writes()
	{
		$client = $this->create_client();
		$user = $this->create_client_user($client);
		$request = $this->create_request($client);
		$old = $this->token_for($request, $user);
		$this->save($request, $client, 'Updated material', 'v2');
		foreach (array('', $old) as $token) {
			$this->assertFalse($this->transition_request($request, 'approved', array('actor_id' => $user, 'is_client_response' => true, 'review_token' => $token)));
		}
		$this->assertSame('open', Requests::get_status_for_request($request));
		$this->assertFalse(metadata_exists('post', $request, Materials::RESPONSE_META_KEY));
	}

	public function test_reopened_cycle_invalidates_other_tabs_even_with_same_material()
	{
		$client = $this->create_client();
		$user = $this->create_client_user($client);
		$request = $this->create_request($client);
		$token = $this->token_for($request, $user);
		$this->assertTrue($this->transition_request($request, 'approved', array('actor_id' => $user, 'is_client_response' => true, 'review_token' => $token)));
		$this->assertTrue($this->transition_request($request, 'open'));
		$this->assertFalse($this->transition_request($request, 'approved', array('actor_id' => $user, 'is_client_response' => true, 'review_token' => $token)));
	}

	/** @dataProvider non_approval_outcomes */
	public function test_unavailable_material_prevents_approval_but_allows_explained_outcomes($outcome)
	{
		$client = $this->create_client();
		$user = $this->create_client_user($client);
		$request = $this->create_request($client);
		$file = $this->file($client);
		$this->save($request, $client, 'Homepage', 'v1', array($file));
		$old = $this->token_for($request, $user);
		wp_update_post(array('ID' => $file, 'post_status' => 'draft'));
		$this->assertFalse($this->transition_request($request, 'blocked', array('actor_id' => $user, 'is_client_response' => true, 'response_note' => 'File missing.', 'review_token' => $old)));
		$this->assertFalse($this->transition_request($request, 'approved', array('actor_id' => $user, 'is_client_response' => true)));
		$this->assertTrue($this->transition_request($request, $outcome, array('actor_id' => $user, 'is_client_response' => true, 'response_note' => 'File unavailable.')));
		$this->assertFalse(get_post_meta($request, Materials::RESPONSE_META_KEY, true)['files'][0]['available']);
	}

	public function non_approval_outcomes()
	{
		return array(array('changes_requested'), array('rejected'), array('blocked'));
	}

	public function test_reassignment_clears_context_and_does_not_expose_previous_material()
	{
		$a = $this->create_client();
		$b = $this->create_client('New client');
		$request = $this->create_request($a);
		$this->save($request, $a, 'Confidential A', 'Secret revision', array($this->file($a, 'Secret A.txt')));
		$this->save($request, $b, 'Confidential A', 'Secret revision');
		$this->assertSame(array(), Materials::file_ids($request));
		$this->assertSame('', Materials::current($request)['reference']);
		$history = $this->get_request_events($request, $b);
		$this->assertCount(1, $history);
		$data = Events::get_request_event_view_data($history[0], true);
		$this->assertNull($data['previous_review_context']);
		$this->assertStringNotContainsString('Confidential', wp_json_encode($data));
	}

	public function test_download_is_bound_to_current_authorized_request_and_revision()
	{
		$a = $this->create_client();
		$user = $this->create_client_user($a);
		$request = $this->create_request($a);
		$file = $this->file($a);
		$this->save($request, $a, 'Material', 'v1', array($file));
		$context = Materials::current($request);
		$url = Materials::download_url($request, $context['files'][0]);
		$this->assertStringNotContainsString('cliapwo-private', $url);
		wp_set_current_user($user);
		$this->assertTrue(Materials::can_download($request, $file, $context['files'][0]['version']));
		$this->assertFalse(Materials::can_download($request, $file, str_repeat('0', 64)));
		wp_set_current_user($this->create_client_user($this->create_client('Other')));
		$this->assertFalse(Materials::can_download($request, $file, $context['files'][0]['version']));
		wp_set_current_user($this->administrator_id);
		$this->save($request, $a, 'Material', 'v1', array(), array($file));
		$this->assertFalse(Materials::can_download($request, $file, $context['files'][0]['version']));
	}

	public function test_linked_asset_replacement_is_rejected_before_asset_or_client_mutation()
	{
		$a = $this->create_client();
		$b = $this->create_client('Other');
		$request = $this->create_request($a);
		$file = $this->file($a);
		$this->save($request, $a, 'Material', 'v1', array($file));
		$path = Files::get_stored_file_path($file);
		$_POST = array(Files::SAVE_NONCE_NAME => wp_create_nonce(Files::SAVE_NONCE_ACTION), 'cliapwo_file_client_id' => $b);
		$_FILES[Files::UPLOAD_FIELD_NAME] = array('name' => 'replacement.txt', 'error' => UPLOAD_ERR_OK, 'size' => 5, 'tmp_name' => 'not-an-upload');
		(new Files())->save_file_meta($file, get_post($file));
		$this->assertSame($a, Files::get_client_id_for_file($file));
		$this->assertSame($path, Files::get_stored_file_path($file));
		$this->assertSame('Original review material.', file_get_contents($path));
		$this->assertStringContainsString('cannot be replaced', get_transient('cliapwo_file_upload_error_' . $file));
		$_POST = array();
		$_FILES = array();
		wp_delete_post($request, true);
		$this->assertFileExists($path);
		$this->assertFalse(Materials::is_linked($file));
	}

	public function test_material_event_failure_rolls_back_and_allows_retry()
	{
		$client = $this->create_client();
		$request = $this->create_request($client);
		$this->save($request, $client, 'Original', 'v1');
		$before = Materials::saved($request);
		$deny = static function ($empty, $data) { return Events::POST_TYPE === $data['post_type'] ? true : $empty; };
		add_filter('wp_insert_post_empty_content', $deny, 10, 2);
		$this->save($request, $client, 'Should fail', 'v2');
		remove_filter('wp_insert_post_empty_content', $deny, 10);
		$this->assertSame($before, Materials::saved($request));
		$this->assertCount(1, $this->get_request_events($request, $client));
		$this->save($request, $client, 'Retry', 'v2');
		$this->assertSame('Retry', Materials::saved($request)['reference']);
	}

	public function test_legacy_snapshot_and_portal_rendering_never_infer_or_leak_paths()
	{
		$client = $this->create_client();
		$request = $this->create_request($client);
		ob_start();
		Materials::render_context(null);
		$this->assertStringContainsString('Review context not recorded', ob_get_clean());
		$file = $this->file($client);
		$this->save($request, $client, '<script>bad</script>Reference', 'v1', array($file));
		ob_start();
		Materials::render_context(Materials::current($request), $request);
		$html = ob_get_clean();
		$this->assertStringNotContainsString('<script>', $html);
		$this->assertStringNotContainsString('cliapwo-private', $html);
		$this->assertStringNotContainsString(Files::get_stored_file_path($file), $html);
		$this->assertStringContainsString('cliapwo_request_id=', $html);
	}

	public function test_initial_assignment_preserves_submitted_labels()
	{
		$client = $this->create_client();
		$request = $this->create_request(0);
		$this->save($request, $client, 'Initial concept', 'Revision 1', array(), array(), 'open', '2026-11-01');
		$this->assertSame('Initial concept', Materials::saved($request)['reference']);
		$events = $this->get_request_events($request, $client);
		$this->assertCount(1, $events);
		$this->assertSame('Revision 1', get_post_meta($events[0]->ID, Materials::EVENT_META_KEY, true)['revision']);
		$this->assertSame('2026-11-01', get_post_meta($events[0]->ID, Events::NEW_DUE_DATE_META_KEY, true));
	}

	public function test_backslashes_round_trip_through_context_and_immutable_response()
	{
		$client = $this->create_client();
		$user = $this->create_client_user($client);
		$request = $this->create_request($client);
		$reference = 'Design \\ QA';
		$this->save($request, $client, $reference, 'v1 \\ final');
		$this->assertSame($reference, Materials::saved($request)['reference']);
		$this->assertTrue($this->transition_request($request, 'approved', array('actor_id' => $user, 'is_client_response' => true, 'response_note' => 'Keep the \\ separator.')));
		$this->assertSame($reference, get_post_meta($request, Materials::RESPONSE_META_KEY, true)['reference']);
		$events = $this->get_request_events($request, $client);
		$this->assertSame($reference, get_post_meta($events[1]->ID, Materials::EVENT_META_KEY, true)['reference']);
	}

	public function test_security_salt_rotation_does_not_invalidate_file_revision_identity()
	{
		$client = $this->create_client();
		$request = $this->create_request($client);
		$this->save($request, $client, 'Material', 'v1', array($this->file($client)));
		$before = Materials::current($request)['files'][0]['version'];
		$rotate = static function ($salt) { return 'rotated-' . $salt; };
		add_filter('salt', $rotate);
		try {
			$this->assertSame($before, Materials::current($request)['files'][0]['version']);
			$this->assertTrue(Materials::current($request)['files'][0]['available']);
		} finally {
			remove_filter('salt', $rotate);
		}
	}

	public function test_initial_creation_metadata_failure_is_retryable_and_does_not_fire_notifications()
	{
		$client = $this->create_client();
		$request = $this->create_request($client);
		$fired = 0;
		$hook = static function () use (&$fired) { ++$fired; };
		$deny = static function ($check, $id, $key) { return Materials::EVENT_META_KEY === $key ? false : $check; };
		add_action('cliapwo_request_created', $hook);
		add_filter('add_post_metadata', $deny, 10, 3);
		try {
			$this->save($request, $client, 'Original', 'v1');
		} finally {
			remove_filter('add_post_metadata', $deny, 10);
			remove_action('cliapwo_request_created', $hook);
		}
		$this->assertSame(0, $fired);
		$this->assertSame('', get_post_meta($request, Requests::NOTIFIED_META_KEY, true));
		$this->assertCount(0, $this->get_request_events($request, $client));
		$this->assertNull(Materials::saved($request));
		$this->save($request, $client, 'Original', 'v1');
		$this->assertCount(1, $this->get_request_events($request, $client));
		$this->assertSame('1', get_post_meta($request, Requests::NOTIFIED_META_KEY, true));
	}

	/** @dataProvider failed_snapshot_keys */
	public function test_failed_snapshot_metadata_does_not_commit_a_response($key)
	{
		$client = $this->create_client();
		$user = $this->create_client_user($client);
		$request = $this->create_request($client);
		$this->save($request, $client, 'Original', 'v1');
		$deny = static function ($check, $id, $meta_key) use ($key) { return $key === $meta_key ? false : $check; };
		add_filter('add_post_metadata', $deny, 10, 3);
		try {
			$this->assertFalse($this->transition_request($request, 'approved', array('actor_id' => $user, 'is_client_response' => true)));
		} finally {
			remove_filter('add_post_metadata', $deny, 10);
		}
		$this->assertSame('open', Requests::get_status_for_request($request));
		$this->assertSame('', get_post_meta($request, Materials::RESPONSE_META_KEY, true));
		$this->assertCount(1, $this->get_request_events($request, $client));
		$this->assertTrue($this->transition_request($request, 'approved', array('actor_id' => $user, 'is_client_response' => true)));
	}

	public function failed_snapshot_keys()
	{
		return array(array(Materials::EVENT_META_KEY), array(Materials::RESPONSE_META_KEY));
	}

	public function test_nested_request_and_multiple_file_operations_hold_one_database_mutex_until_outer_release()
	{
		global $wpdb;
		$name = 'cliapwo_operation_' . md5(DB_NAME . ':' . $wpdb->postmeta);
		$other = new wpdb(DB_USER, DB_PASSWORD, DB_NAME, DB_HOST);
		$this->assertTrue(Materials::lock_operation());
		try {
			$this->assertTrue(Materials::lock_files(array(100, 200)));
			try {
				$this->assertSame('0', (string) $other->get_var($other->prepare('SELECT GET_LOCK(%s, 0)', $name)));
			} finally {
				Materials::unlock_files(array(100, 200));
			}
			$this->assertSame('0', (string) $other->get_var($other->prepare('SELECT GET_LOCK(%s, 0)', $name)));
		} finally {
			Materials::unlock_operation();
		}
		$this->assertSame('1', (string) $other->get_var($other->prepare('SELECT GET_LOCK(%s, 0)', $name)));
		$other->get_var($other->prepare('SELECT RELEASE_LOCK(%s)', $name));
		$other->close();
	}

	public function test_notification_marker_failure_does_not_hide_later_material_changes()
	{
		$client = $this->create_client();
		$request = $this->create_request($client);
		$deny = static function ($check, $id, $key) { return Requests::NOTIFIED_META_KEY === $key ? false : $check; };
		add_filter('add_post_metadata', $deny, 10, 3);
		try {
			$this->save($request, $client, 'Original', 'v1', array(), array(), 'open', '2026-11-01');
		} finally {
			remove_filter('add_post_metadata', $deny, 10);
		}
		$this->assertSame('', get_post_meta($request, Requests::NOTIFIED_META_KEY, true));
		$this->save($request, $client, 'Changed before retry', 'v2', array(), array(), 'open', '2026-11-02');
		$events = $this->get_request_events($request, $client);
		$this->assertCount(3, $events);
		$this->assertSame('v1', get_post_meta($events[0]->ID, Materials::EVENT_META_KEY, true)['revision']);
		$this->assertSame('2026-11-01', get_post_meta($events[0]->ID, Events::NEW_DUE_DATE_META_KEY, true));
		$this->assertSame(Materials::EVENT_TYPE, get_post_meta($events[1]->ID, Events::TYPE_META_KEY, true));
		$this->assertSame('2026-11-02', get_post_meta($events[2]->ID, Events::NEW_DUE_DATE_META_KEY, true));
		$this->assertSame('1', get_post_meta($request, Requests::NOTIFIED_META_KEY, true));
	}

	public function test_material_save_requires_both_nonce_and_management_capability()
	{
		$client = $this->create_client();
		$request = $this->create_request($client);
		$_POST = array(Materials::EDITOR_TOKEN => Materials::token($request), 'cliapwo_material_reference' => 'Forged', 'cliapwo_material_revision' => 'v2', 'cliapwo_request_client_id' => $client);
		(new Requests())->save_request_meta($request, get_post($request));
		$this->assertNull(Materials::saved($request));
		wp_set_current_user($this->create_client_user($client));
		$_POST[Requests::SAVE_NONCE_NAME] = wp_create_nonce(Requests::SAVE_NONCE_ACTION);
		$_POST[Materials::EDITOR_TOKEN] = Materials::token($request);
		(new Requests())->save_request_meta($request, get_post($request));
		$this->assertNull(Materials::saved($request));
		$this->assertCount(0, $this->get_request_events($request, $client));
	}

	public function test_general_download_nonce_cannot_authorize_a_request_context_link()
	{
		$client = $this->create_client();
		$user = $this->create_client_user($client);
		$request = $this->create_request($client);
		$file = $this->file($client);
		$this->save($request, $client, 'Material', 'v1', array($file));
		wp_set_current_user($user);
		$_GET = array('cliapwo_file_id' => $file, 'cliapwo_request_id' => $request, 'cliapwo_file_revision' => Materials::current($request)['files'][0]['version'], Files::DOWNLOAD_NONCE_NAME => wp_create_nonce(Files::DOWNLOAD_ACTION));
		$this->expectException(WPDieException::class);
		(new Files())->handle_download();
	}

	/** @dataProvider legacy_response_shapes */
	public function test_reassignment_preserves_or_hides_unmigrated_legacy_response_ownership($reliable, $unassigned = false)
	{
		$a = $this->create_client();
		$b = $this->create_client('New client');
		$user = $this->create_client_user($a);
		$request = $this->create_request($unassigned ? 0 : $a);
		update_post_meta($request, Requests::NOTIFIED_META_KEY, '1');
		update_post_meta($request, Requests::RESPONSE_STATUS_META_KEY, 'approved');
		update_post_meta($request, Requests::RESPONSE_NOTE_META_KEY, 'Confidential old account note.');
		update_post_meta($request, Requests::RESPONDED_BY_META_KEY, $user);
		update_post_meta($request, Requests::RESPONDED_AT_META_KEY, $reliable ? time() - 100 : 0);
		$this->save($request, $b, '', '');
		$this->assertSame($b, Requests::get_client_id_for_request($request));
		$this->assertTrue(metadata_exists('post', $request, Requests::RESPONSE_CLIENT_META_KEY));
		$this->assertSame($reliable && ! $unassigned ? $a : 0, absint(get_post_meta($request, Requests::RESPONSE_CLIENT_META_KEY, true)));
		$history = $this->get_request_events($request, $b);
		foreach ($history as $event) {
			$this->assertStringNotContainsString('Confidential', wp_json_encode(Events::get_request_event_view_data($event, true)));
		}
	}

	public function legacy_response_shapes()
	{
		return array(array(true), array(false), array(true, true));
	}

	public function test_core_request_publication_and_deletion_share_the_response_mutex()
	{
		global $wpdb;
		$request = $this->create_request($this->create_client());
		$other = new wpdb(DB_USER, DB_PASSWORD, DB_NAME, DB_HOST);
		$name = 'cliapwo_operation_' . md5(DB_NAME . ':' . $wpdb->postmeta);
		$this->assertSame('1', (string) $other->get_var($other->prepare('SELECT GET_LOCK(%s, 0)', $name)));
		try {
			$this->assertSame(0, wp_update_post(array('ID' => $request, 'post_status' => 'draft')));
			$this->assertFalse(wp_trash_post($request));
			$this->assertFalse(wp_delete_post($request, true));
			$this->assertSame('publish', get_post_status($request));
		} finally {
			$other->get_var($other->prepare('SELECT RELEASE_LOCK(%s)', $name));
			$other->close();
		}
		$this->assertSame($request, wp_update_post(array('ID' => $request, 'post_status' => 'draft')));
		$this->assertSame('draft', get_post_status($request));
	}
}
