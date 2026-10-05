<?php

use Vzisis\ClientApprovalWorkflow\Events;
use Vzisis\ClientApprovalWorkflow\Portal;
use Vzisis\ClientApprovalWorkflow\Request_Dates;
use Vzisis\ClientApprovalWorkflow\Requests;
use Vzisis\ClientApprovalWorkflow\Sample_Content;
use Vzisis\ClientApprovalWorkflow\Settings;

/** Calendar deadlines, query ordering and history regression coverage. */
class Cliapwo_Request_Dates_Test extends Cliapwo_Test_Case
{
	/**
	 * Submit the real request-details save handler.
	 *
	 * @param int    $id Request ID.
	 * @param mixed  $date Submitted date.
	 * @param string $status Status to save.
	 */
	private function save_date($id, $date, $status = 'open')
	{
		$_POST = array(
			Requests::SAVE_NONCE_NAME => wp_create_nonce(Requests::SAVE_NONCE_ACTION),
			'cliapwo_request_client_id' => (string) Requests::get_client_id_for_request($id),
			'cliapwo_request_status' => $status,
			Request_Dates::META_KEY => $date,
		);
		(new Requests())->save_request_meta($id, get_post($id));
	}

	/** Invalid dates do not normalize and cannot erase a valid deadline. */
	public function test_validation_and_invalid_save()
	{
		foreach (array('2026-02-29', '2024-02-30', '2026-13-01', '2026-04-31', '26-01-01', '2026-1-01', '0000-01-01', '2026-01-01<script>', array()) as $invalid) {
			$this->assertFalse(Request_Dates::is_valid($invalid));
		}
		$this->assertTrue(Request_Dates::is_valid('2024-02-29'));
		$this->assertTrue(Request_Dates::is_valid('1000-01-01'));
		$this->assertTrue(Request_Dates::is_valid('9999-12-31'));
		$id = $this->create_request($this->create_client());
		$this->save_date($id, '2024-02-29');
		$this->save_date($id, '2026-02-29');
		$this->assertSame('2024-02-29', Request_Dates::get_date($id));
		$this->save_date($id, array());
		$this->assertSame('2024-02-29', Request_Dates::get_date($id));
	}

	/** Creation snapshots once; changes, removal, and resolution retain immutable history. */
	public function test_date_history_and_status_are_independent()
	{
		$client = $this->create_client();
		$id = $this->create_request($client);
		$this->save_date($id, '2026-10-05');
		$this->save_date($id, '2026-10-05');
		$this->assertCount(1, $this->get_request_events($id, $client));
		$created = $this->get_request_events($id, $client)[0];
		$this->assertSame('2026-10-05', get_post_meta($created->ID, Events::NEW_DUE_DATE_META_KEY, true));
		$this->save_date($id, '2026-10-10', Requests::STATUS_APPROVED);
		$this->assertSame(Requests::STATUS_APPROVED, Requests::get_status_for_request($id));
		$this->assertSame('2026-10-10', Request_Dates::get_date($id));
		$this->assertSame('', Request_Dates::get_state($id));
		$this->save_date($id, '2026-10-10');
		$this->save_date($id, '');
		$events = $this->get_request_events($id, $client);
		$this->assertCount(5, $events);
		$this->assertSame('2026-10-05', get_post_meta($events[1]->ID, Events::PREVIOUS_DUE_DATE_META_KEY, true));
		$this->assertSame('2026-10-10', get_post_meta($events[1]->ID, Events::NEW_DUE_DATE_META_KEY, true));
		$this->assertSame('Your team', Events::get_request_event_view_data($events[1], true)['actor_name']);
		$this->assertSame('', Request_Dates::get_date($id));
		$this->assertSame('', get_post_meta($id, Requests::RESPONSE_STATUS_META_KEY, true));
	}

	/** Failed event writes and active locks preserve the previous date. */
	public function test_event_failure_and_locks_preserve_date()
	{
		$client = $this->create_client();
		$id = $this->create_request($client);
		$this->save_date($id, '2026-10-05');
		$reject = static function ($empty, $post) { return Events::POST_TYPE === $post['post_type'] ? true : $empty; };
		add_filter('wp_insert_post_empty_content', $reject, 10, 2);
		$this->save_date($id, '2026-10-07');
		remove_filter('wp_insert_post_empty_content', $reject, 10);
		$this->assertSame('2026-10-05', Request_Dates::get_date($id));
		update_post_meta($id, 'cliapwo_request_transition_lock', 'processing:' . time() . ':other');
		$this->save_date($id, '2026-10-08');
		$this->assertSame('2026-10-05', Request_Dates::get_date($id));
		update_post_meta($id, 'cliapwo_request_transition_lock', 'processing:' . (time() - 61) . ':stale');
		$this->save_date($id, '2026-10-09');
		$this->assertSame('2026-10-09', Request_Dates::get_date($id));
		$this->assertCount(2, $this->get_request_events($id, $client));
	}

	/** Missing fields/nonces, revisions and nonstaff users cannot write deadlines. */
	public function test_save_authorization_and_missing_field()
	{
		$client = $this->create_client();
		$id = $this->create_request($client);
		$this->save_date($id, '2026-10-05');
		unset($_POST[Request_Dates::META_KEY]);
		(new Requests())->save_request_meta($id, get_post($id));
		$this->assertSame('2026-10-05', Request_Dates::get_date($id));
		$_POST[Request_Dates::META_KEY] = '';
		$_POST[Requests::SAVE_NONCE_NAME] = 'invalid';
		(new Requests())->save_request_meta($id, get_post($id));
		$this->assertSame('2026-10-05', Request_Dates::get_date($id));
		wp_set_current_user($this->create_client_user($client));
		$this->save_date($id, '2026-10-09');
		$this->assertSame('2026-10-05', Request_Dates::get_date($id));
	}

	/** The seven-day window uses site days, including DST boundaries. */
	public function test_calendar_window_and_boundaries()
	{
		update_option('timezone_string', 'Europe/Chisinau');
		$window = Request_Dates::window();
		$this->assertSame(current_datetime()->format('Y-m-d'), $window['today']);
		$this->assertSame(current_datetime()->modify('+7 days')->format('Y-m-d'), $window['soon']);
		$id = $this->create_request($this->create_client());
		$fixed = array('today' => '2026-10-24', 'soon' => '2026-10-31');
		foreach (array('2026-10-23' => 'overdue', '2026-10-24' => 'soon', '2026-10-31' => 'soon', '2026-11-01' => '') as $date => $state) {
			update_post_meta($id, Request_Dates::META_KEY, $date);
			$this->assertSame($state, Request_Dates::get_state($id, $fixed));
		}
	}

	/** SQL orders before the display limit and handles missing/invalid metadata. */
	public function test_attention_ordering_and_counts_before_limit()
	{
		$client = $this->create_client();
		$window = Request_Dates::window();
		$old = $this->create_request($client);
		wp_update_post(array('ID' => $old, 'post_date' => '2020-01-01 00:00:00'));
		update_post_meta($old, Request_Dates::META_KEY, '2020-01-01');
		delete_post_meta($old, Requests::STATUS_META_KEY);
		for ($i = 0; $i < 55; ++$i) {
			$this->create_request($client, Requests::STATUS_APPROVED);
		}
		$soon = $this->create_request($client);
		update_post_meta($soon, Request_Dates::META_KEY, $window['soon']);
		$missing = $this->create_request($client);
		$foreign = $this->create_request($this->create_client());
		update_post_meta($foreign, Request_Dates::META_KEY, '1000-01-01');
		$query = Requests::get_requests_query_for_client($client, array('cliapwo_attention_order' => true, 'fields' => 'ids'));
		$this->assertCount(50, $query->posts);
		$this->assertSame(array($old, $soon, $missing), array_slice($query->posts, 0, 3));
		$this->assertNotContains($foreign, $query->posts);
		$this->assertSame(array('open' => 3, 'overdue' => 1, 'soon' => 1), Request_Dates::attention_counts($client));
	}

	/** Filters compose with statuses and date sorting includes undated requests last. */
	public function test_due_queries_and_sorting()
	{
		$client = $this->create_client();
		$today = Request_Dates::window()['today'];
		$early = $this->create_request($client);
		$late = $this->create_request($client);
		$none = $this->create_request($client);
		$invalid = $this->create_request($client);
		$resolved = $this->create_request($client, Requests::STATUS_COMPLETE);
		update_post_meta($early, Request_Dates::META_KEY, '2020-01-01');
		update_post_meta($late, Request_Dates::META_KEY, $today);
		update_post_meta($resolved, Request_Dates::META_KEY, '2020-01-01');
		update_post_meta($invalid, Request_Dates::META_KEY, '2026-02-30');
		$args = array('fields' => 'ids', 'cliapwo_due_filter' => 'overdue');
		$this->assertSame(array($early), Requests::get_requests_query_for_client($client, $args)->posts);
		$args['cliapwo_due_filter'] = 'soon';
		$this->assertSame(array($late), Requests::get_requests_query_for_client($client, $args)->posts);
		$args['cliapwo_due_filter'] = 'none';
		$this->assertEqualsCanonicalizing(array($none, $invalid), Requests::get_requests_query_for_client($client, $args)->posts);
		unset($args['cliapwo_due_filter']);
		$args['cliapwo_due_order'] = 'DESC';
		$ids = Requests::get_requests_query_for_client($client, $args)->posts;
		$this->assertSame($late, $ids[0]);
		$this->assertEqualsCanonicalizing(array($invalid, $none), array_slice($ids, -2));
		$args['cliapwo_due_order'] = 'ASC';
		$ids = Requests::get_requests_query_for_client($client, $args)->posts;
		$this->assertEqualsCanonicalizing(array($early, $resolved), array_slice($ids, 0, 2));
	}

	/** New-request mail includes the saved date, and editing never sends mail. */
	public function test_notification_uses_creation_date_only()
	{
		$client = $this->create_client();
		$this->create_client_user($client);
		update_option(Settings::OPTION_KEY, array('notify_requests' => 1));
		$messages = array();
		$intercept = static function ($return, $args) use (&$messages) { $messages[] = $args['message']; return true; };
		add_filter('pre_wp_mail', $intercept, 10, 2);
		$id = $this->create_request($client);
		$this->save_date($id, '2026-10-05');
		$this->save_date($id, '2026-10-09');
		remove_filter('pre_wp_mail', $intercept, 10);
		$this->assertCount(1, $messages);
		$this->assertStringContainsString(Request_Dates::format('2026-10-05'), $messages[0]);
		$this->assertStringNotContainsString(Request_Dates::format('2026-10-09'), $messages[0]);
	}

	/** Never-published drafts defer creation; formerly published drafts still audit edits. */
	public function test_draft_publication_and_republication()
	{
		$client = $this->create_client();
		$id = self::factory()->post->create(array('post_type' => Requests::POST_TYPE, 'post_status' => 'draft', 'post_title' => 'Draft request'));
		update_post_meta($id, Requests::CLIENT_META_KEY, $client);
		$this->save_date($id, '2026-10-05');
		$this->save_date($id, '2026-10-06');
		$this->assertCount(0, $this->get_request_events($id, $client));
		$_POST = array();
		wp_update_post(array('ID' => $id, 'post_status' => 'publish'));
		$this->save_date($id, '2026-10-06');
		$events = $this->get_request_events($id, $client);
		$this->assertCount(1, $events);
		$this->assertSame('2026-10-06', get_post_meta($events[0]->ID, Events::NEW_DUE_DATE_META_KEY, true));
		$_POST = array();
		wp_update_post(array('ID' => $id, 'post_status' => 'draft'));
		$this->save_date($id, '2026-10-07');
		$_POST = array();
		wp_update_post(array('ID' => $id, 'post_status' => 'publish'));
		$this->save_date($id, '2026-10-07');
		$this->assertCount(2, $this->get_request_events($id, $client));
	}

	/** Incomplete event metadata must roll the date back and remove the partial event. */
	public function test_event_metadata_failure_rolls_back()
	{
		$client = $this->create_client();
		$id = $this->create_request($client);
		$this->save_date($id, '2026-10-05');
		$reject = static function ($check, $object_id, $key) { return Events::NEW_DUE_DATE_META_KEY === $key ? false : $check; };
		add_filter('add_post_metadata', $reject, 10, 3);
		$this->save_date($id, '2026-10-06');
		remove_filter('add_post_metadata', $reject, 10);
		$this->assertSame('2026-10-05', Request_Dates::get_date($id));
		$this->assertCount(1, $this->get_request_events($id, $client));
	}

	/** A lock held by a different database connection prevents any due-date mutation. */
	public function test_database_connection_lock_prevents_concurrent_writes()
	{
		global $wpdb;
		$id = $this->create_request($this->create_client());
		$this->save_date($id, '2026-10-05');
		$other = new wpdb(DB_USER, DB_PASSWORD, DB_NAME, DB_HOST);
		$name = 'cliapwo_' . md5(DB_NAME . ':' . $wpdb->postmeta . ':' . $id);
		$this->assertSame('1', (string) $other->get_var($other->prepare('SELECT GET_LOCK(%s, 0)', $name)));
		try {
			$this->save_date($id, '2026-10-06');
			$this->assertSame('2026-10-05', Request_Dates::get_date($id));
		} finally {
			$other->get_var($other->prepare('SELECT RELEASE_LOCK(%s)', $name));
			$other->close();
		}
		$this->save_date($id, '2026-10-06');
		$this->assertSame('2026-10-06', Request_Dates::get_date($id));
		$other = new wpdb(DB_USER, DB_PASSWORD, DB_NAME, DB_HOST);
		$reject = static function ($empty, $post) { return Events::POST_TYPE === $post['post_type'] ? true : $empty; };
		try {
			foreach (array('2026-10-06', '2026-10-07', '2026-10-08') as $date) {
				if ('2026-10-08' === $date) {
					add_filter('wp_insert_post_empty_content', $reject, 10, 2);
				}
				$this->save_date($id, $date);
				$this->assertSame('1', (string) $other->get_var($other->prepare('SELECT GET_LOCK(%s, 0)', $name)), 'Successful, unchanged, and failed saves release the connection-owned lock.');
				$other->get_var($other->prepare('SELECT RELEASE_LOCK(%s)', $name));
			}
			$this->assertSame('2026-10-07', Request_Dates::get_date($id));
		} finally {
			remove_filter('wp_insert_post_empty_content', $reject, 10);
			$other->get_var($other->prepare('SELECT RELEASE_LOCK(%s)', $name));
			$other->close();
		}
	}

	/** Unknown and empty statuses match the existing PHP open-status fallback in SQL. */
	public function test_malformed_statuses_and_dates_match_sql_rules()
	{
		$client = $this->create_client();
		$ids = array();
		foreach (array('', 'unknown', 'APPROVED') as $status) {
			$id = $this->create_request($client, $status);
			update_post_meta($id, Request_Dates::META_KEY, '2020-01-01');
			$ids[] = $id;
		}
		$this->assertEqualsCanonicalizing($ids, Requests::get_requests_query_for_client($client, array('fields' => 'ids', 'cliapwo_due_filter' => 'overdue'))->posts);
		$invalid = $this->create_request($client);
		update_post_meta($invalid, Request_Dates::META_KEY, "2026-10-05\n");
		$this->assertSame(array($invalid), Requests::get_requests_query_for_client($client, array('fields' => 'ids', 'cliapwo_due_filter' => 'none'))->posts);
	}

	/** Due-only edits preserve all response snapshots, including earlier immutable notes. */
	public function test_due_edits_preserve_real_client_response()
	{
		$client = $this->create_client();
		$user = $this->create_client_user($client);
		$id = $this->create_request($client);
		$this->save_date($id, '2026-10-05');
		$this->assertTrue($this->transition_request($id, Requests::STATUS_REJECTED, array('actor_id' => $user, 'actor_type' => Events::ACTOR_TYPE_CLIENT, 'is_client_response' => true, 'response_note' => 'Original decision.')));
		$before = array();
		foreach (array(Requests::RESPONSE_NOTE_META_KEY, Requests::RESPONSE_STATUS_META_KEY, Requests::RESPONDED_BY_META_KEY, Requests::RESPONDED_AT_META_KEY, Requests::RESPONSE_CLIENT_META_KEY) as $key) {
			$before[$key] = get_post_meta($id, $key, true);
		}
		$this->save_date($id, '2026-10-06', Requests::STATUS_REJECTED);
		foreach ($before as $key => $value) {
			$this->assertSame($value, get_post_meta($id, $key, true));
		}
		$events = $this->get_request_events($id, $client);
		$this->assertCount(3, $events);
		$this->assertSame('Original decision.', get_post_meta($events[1]->ID, Events::RESPONSE_NOTE_META_KEY, true));
	}

	/** Sample date events are marked, tracked, excluded, and removed exactly. */
	public function test_sample_date_events_are_cleaned_up_exactly()
	{
		$real = $this->create_request($this->create_client());
		$service = new Sample_Content();
		$create = new ReflectionMethod(Sample_Content::class, 'create_or_repair');
		$cleanup = new ReflectionMethod(Sample_Content::class, 'cleanup');
		if (PHP_VERSION_ID < 80100) {
			$create->setAccessible(true);
			$cleanup->setAccessible(true);
		}
		$this->assertSame('created', $create->invoke($service, $service->get_state()));
		$state = $service->get_state();
		$this->save_date($state['request_id'], '2026-10-05');
		$this->save_date($state['request_id'], '2026-10-06');
		$ids = get_option(Sample_Content::OPTION_KEY);
		$this->assertCount(7, $ids);
		$this->assertTrue($service->get_state()['is_complete']);
		$this->assertSame('repaired', $create->invoke($service, $service->get_state()));
		$this->assertSame($ids, get_option(Sample_Content::OPTION_KEY));
		foreach ($ids as $key => $id) {
			if (0 === strpos($key, 'due_event_')) {
				$this->assertSame('1', get_post_meta($id, 'cliapwo_sample_content', true));
			}
		}
		$this->assertSame('removed', $cleanup->invoke($service));
		foreach ($ids as $id) {
			$this->assertNull(get_post($id));
		}
		$this->assertInstanceOf(WP_Post::class, get_post($real));
	}

	/** Privileged query controls require both nonce and capability; filters compose. */
	public function test_admin_query_controls_and_combined_filters()
	{
		global $wp_the_query;
		$prior = $wp_the_query;
		set_current_screen('edit-cliapwo_request');
		$client = $this->create_client();
		$id = $this->create_request($client);
		$other = $this->create_request($client, Requests::STATUS_APPROVED);
		update_post_meta($id, Request_Dates::META_KEY, '2020-01-01');
		update_post_meta($other, Request_Dates::META_KEY, '2020-01-01');
		$_GET = array(Request_Dates::NONCE_NAME => wp_create_nonce(Request_Dates::NONCE_ACTION), Request_Dates::FILTER_KEY => 'overdue', 'cliapwo_request_status_filter' => 'open');
		try {
			$wp_the_query = new WP_Query();
			$wp_the_query->query(array('post_type' => Requests::POST_TYPE, 'posts_per_page' => 1, 'orderby' => Request_Dates::ORDERBY, 'order' => 'ASC', 'fields' => 'ids'));
			$this->assertSame(array($id), $wp_the_query->posts);
			$this->assertSame(1, (int) $wp_the_query->found_posts);
			$this->assertStringContainsString(Request_Dates::NONCE_NAME, Request_Dates::column_heading());
			$_GET[Request_Dates::NONCE_NAME] = 'invalid';
			$wp_the_query = new WP_Query();
			$wp_the_query->query(array('post_type' => Requests::POST_TYPE, 'orderby' => Request_Dates::ORDERBY, 'fields' => 'ids'));
			$this->assertCount(2, $wp_the_query->posts);
			$this->assertSame('', $wp_the_query->get('cliapwo_due_order'));
		} finally {
			$wp_the_query = $prior;
			set_current_screen('front');
		}
	}
}
