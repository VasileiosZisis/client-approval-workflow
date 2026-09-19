<?php

use Vzisis\ClientApprovalWorkflow\Events;
use Vzisis\ClientApprovalWorkflow\Requests;

/**
 * Historical request metadata migration coverage.
 */
class MigrationTest extends Cliapwo_Test_Case
{
	/**
	 * Reliable v1.2/v1.3 metadata produces one event with its original timestamp.
	 */
	public function test_reliable_legacy_response_is_backfilled_once()
	{
		$client_id = $this->create_client();
		$user_id   = $this->create_client_user($client_id);
		$request_id = $this->create_request($client_id, Requests::STATUS_CHANGES_REQUESTED);
		$timestamp = 1700000000;

		update_post_meta($request_id, Requests::RESPONSE_STATUS_META_KEY, Requests::STATUS_CHANGES_REQUESTED);
		update_post_meta($request_id, Requests::RESPONSE_NOTE_META_KEY, 'Historical note.');
		update_post_meta($request_id, Requests::RESPONDED_BY_META_KEY, $user_id);
		update_post_meta($request_id, Requests::RESPONDED_AT_META_KEY, $timestamp);

		$this->assertTrue(Events::backfill_request_history_if_needed($request_id));
		$this->assertTrue(Events::backfill_request_history_if_needed($request_id));
		$events = $this->get_request_events($request_id, $client_id);
		$this->assertCount(1, $events);
		$this->assertSame(Events::TYPE_REQUEST_RESPONSE, get_post_meta($events[0]->ID, Events::TYPE_META_KEY, true));
		$this->assertSame('1', get_post_meta($events[0]->ID, Events::IS_BACKFILL_META_KEY, true));
		$this->assertSame($timestamp, strtotime($events[0]->post_date_gmt . ' UTC'));
		$this->assertSame('complete', get_post_meta($request_id, Requests::HISTORY_BACKFILL_META_KEY, true));
	}

	/**
	 * Deleted responders retain a safe snapshot and do not block migration.
	 */
	public function test_deleted_legacy_responder_is_preserved_safely()
	{
		$client_id = $this->create_client();
		$request_id = $this->create_request($client_id, Requests::STATUS_APPROVED);
		update_post_meta($request_id, Requests::RESPONSE_STATUS_META_KEY, Requests::STATUS_APPROVED);
		update_post_meta($request_id, Requests::RESPONDED_BY_META_KEY, 999999);
		update_post_meta($request_id, Requests::RESPONDED_AT_META_KEY, 1700000000);

		$this->assertTrue(Events::backfill_request_history_if_needed($request_id));
		$histories = Events::get_request_histories(array($request_id));
		$events    = $histories[$request_id];
		$this->assertCount(1, $events);
		$this->assertNotSame('', get_post_meta($events[0]->ID, Events::ACTOR_NAME_META_KEY, true));
		$this->assertSame(0, (int) get_post_meta($events[0]->ID, Events::CLIENT_META_KEY, true));
	}

	/**
	 * Invalid pre-response records are marked skipped without fabricated history.
	 */
	public function test_incomplete_legacy_metadata_is_skipped()
	{
		$client_id = $this->create_client();
		$request_id = $this->create_request($client_id, Requests::STATUS_APPROVED);
		update_post_meta($request_id, Requests::RESPONSE_STATUS_META_KEY, Requests::STATUS_APPROVED);

		$this->assertTrue(Events::backfill_request_history_if_needed($request_id));
		$this->assertSame('skipped', get_post_meta($request_id, Requests::HISTORY_BACKFILL_META_KEY, true));
		$this->assertCount(0, $this->get_request_events($request_id, $client_id));
	}

	/**
	 * Failed event insertion remains pending for a later retry.
	 */
	public function test_failed_backfill_remains_pending()
	{
		$client_id = $this->create_client();
		$request_id = $this->create_request($client_id, Requests::STATUS_APPROVED);
		update_post_meta($request_id, Requests::RESPONSE_STATUS_META_KEY, Requests::STATUS_APPROVED);
		update_post_meta($request_id, Requests::RESPONDED_BY_META_KEY, $this->administrator_id);
		update_post_meta($request_id, Requests::RESPONDED_AT_META_KEY, 1700000000);
		$reject_events = static function ($maybe_empty, $post_data) {
			return Events::POST_TYPE === $post_data['post_type'] ? true : $maybe_empty;
		};

		add_filter('wp_insert_post_empty_content', $reject_events, 10, 2);
		$this->assertFalse(Events::backfill_request_history_if_needed($request_id));
		remove_filter('wp_insert_post_empty_content', $reject_events, 10);
		$this->assertSame('', get_post_meta($request_id, Requests::HISTORY_BACKFILL_META_KEY, true));
	}
}
