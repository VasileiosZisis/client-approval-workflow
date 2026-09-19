<?php

use Vzisis\ClientApprovalWorkflow\Events;
use Vzisis\ClientApprovalWorkflow\Requests;

/**
 * Request lifecycle regression coverage.
 */
class RequestLifecycleTest extends Cliapwo_Test_Case
{
	/**
	 * Creation produces one structured event and repeated saves do not duplicate it.
	 */
	public function test_request_creation_is_recorded_once()
	{
		$client_id = $this->create_client();
		$request_id = $this->create_request($client_id);
		$_POST[Requests::SAVE_NONCE_NAME] = wp_create_nonce(Requests::SAVE_NONCE_ACTION);
		$_POST['cliapwo_request_client_id'] = (string) $client_id;
		$_POST['cliapwo_request_status'] = Requests::STATUS_OPEN;
		$request = get_post($request_id);
		$service = new Requests();

		$service->save_request_meta($request_id, $request);
		$service->save_request_meta($request_id, $request);

		$events = $this->get_request_events($request_id, $client_id);
		$this->assertCount(1, $events);
		$this->assertSame(Events::TYPE_REQUEST_CREATED, get_post_meta($events[0]->ID, Events::TYPE_META_KEY, true));
	}

	/**
	 * All client outcomes record immutable snapshots and enforce semantic changes.
	 *
	 * @dataProvider client_outcome_provider
	 * @param string $outcome Response outcome.
	 * @param string $note    Response note.
	 */
	public function test_client_outcomes_record_one_immutable_event($outcome, $note)
	{
		$client_id = $this->create_client();
		$user_id   = $this->create_client_user($client_id);
		$request_id = $this->create_request($client_id);

		$result = $this->transition_request(
			$request_id,
			$outcome,
			array(
				'actor_id'           => $user_id,
				'actor_type'         => Events::ACTOR_TYPE_CLIENT,
				'is_client_response' => true,
				'response_note'      => $note,
			)
		);

		$this->assertTrue($result);
		$this->assertSame($outcome, Requests::get_status_for_request($request_id));
		$this->assertSame($note, Requests::get_response_note_for_request($request_id));
		$events = $this->get_request_events($request_id, $client_id);
		$this->assertCount(1, $events);
		$this->assertSame(Events::TYPE_REQUEST_RESPONSE, get_post_meta($events[0]->ID, Events::TYPE_META_KEY, true));
		$this->assertSame($note, get_post_meta($events[0]->ID, Events::RESPONSE_NOTE_META_KEY, true));

		$this->assertTrue($this->transition_request($request_id, $outcome, array('actor_id' => $user_id)));
		$this->assertCount(1, $this->get_request_events($request_id, $client_id));
	}

	/**
	 * Provide every supported client outcome.
	 *
	 * @return array<string, array<int, string>>
	 */
	public function client_outcome_provider()
	{
		return array(
			'approved'          => array(Requests::STATUS_APPROVED, ''),
			'changes requested' => array(Requests::STATUS_CHANGES_REQUESTED, 'Please revise the heading.'),
			'rejected'          => array(Requests::STATUS_REJECTED, 'This direction is not suitable.'),
			'blocked'           => array(Requests::STATUS_BLOCKED, 'Waiting for legal review.'),
		);
	}

	/**
	 * Reopen and a second response preserve the earlier response snapshot.
	 */
	public function test_reopen_and_second_response_preserve_history()
	{
		$client_id = $this->create_client();
		$user_id   = $this->create_client_user($client_id);
		$request_id = $this->create_request($client_id);
		$client_context = array(
			'actor_id'           => $user_id,
			'actor_type'         => Events::ACTOR_TYPE_CLIENT,
			'is_client_response' => true,
			'response_note'      => 'First note.',
		);

		$this->assertTrue($this->transition_request($request_id, Requests::STATUS_CHANGES_REQUESTED, $client_context));
		$this->assertTrue($this->transition_request($request_id, Requests::STATUS_OPEN, array('actor_id' => $this->administrator_id)));
		$client_context['response_note'] = 'Final approval.';
		$this->assertTrue($this->transition_request($request_id, Requests::STATUS_APPROVED, $client_context));

		$events = $this->get_request_events($request_id, $client_id);
		$this->assertCount(3, $events);
		$this->assertSame('First note.', get_post_meta($events[0]->ID, Events::RESPONSE_NOTE_META_KEY, true));
		$this->assertSame(Events::TYPE_REQUEST_REOPENED, get_post_meta($events[1]->ID, Events::TYPE_META_KEY, true));
		$this->assertSame('Final approval.', get_post_meta($events[2]->ID, Events::RESPONSE_NOTE_META_KEY, true));
	}

	/**
	 * Legacy complete normalizes without inventing an event.
	 */
	public function test_legacy_complete_normalizes_without_event()
	{
		$client_id = $this->create_client();
		$request_id = $this->create_request($client_id, Requests::STATUS_COMPLETE);

		$this->assertTrue($this->transition_request($request_id, Requests::STATUS_APPROVED));
		$this->assertSame(Requests::STATUS_APPROVED, get_post_meta($request_id, Requests::STATUS_META_KEY, true));
		$this->assertCount(0, $this->get_request_events($request_id, $client_id));
	}

	/**
	 * Failed event insertion rolls all request metadata back.
	 */
	public function test_failed_event_insert_restores_request_metadata()
	{
		$client_id = $this->create_client();
		$user_id   = $this->create_client_user($client_id);
		$request_id = $this->create_request($client_id);
		$reject_events = static function ($maybe_empty, $post_data) {
			return Events::POST_TYPE === $post_data['post_type'] ? true : $maybe_empty;
		};

		add_filter('wp_insert_post_empty_content', $reject_events, 10, 2);
		$result = $this->transition_request(
			$request_id,
			Requests::STATUS_APPROVED,
			array(
				'actor_id'           => $user_id,
				'actor_type'         => Events::ACTOR_TYPE_CLIENT,
				'is_client_response' => true,
				'response_note'      => 'Must roll back.',
			)
		);
		remove_filter('wp_insert_post_empty_content', $reject_events, 10);

		$this->assertFalse($result);
		$this->assertSame(Requests::STATUS_OPEN, Requests::get_status_for_request($request_id));
		$this->assertSame('', Requests::get_response_note_for_request($request_id));
	}
}
