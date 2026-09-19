<?php

use Vzisis\ClientApprovalWorkflow\Clients;
use Vzisis\ClientApprovalWorkflow\Events;
use Vzisis\ClientApprovalWorkflow\Requests;

/**
 * Shared fixture helpers for SignoffFlow integration tests.
 */
abstract class Cliapwo_Test_Case extends WP_UnitTestCase
{
	/**
	 * Administrator used by privileged fixture operations.
	 *
	 * @var int
	 */
	protected $administrator_id;

	/**
	 * Prepare a clean privileged test user.
	 */
	public function set_up()
	{
		parent::set_up();

		Vzisis\ClientApprovalWorkflow\Lifecycle::ensure_roles();
		$this->administrator_id = self::factory()->user->create(array('role' => 'administrator'));
		wp_set_current_user($this->administrator_id);
	}

	/**
	 * Clear request globals after each test.
	 */
	public function tear_down()
	{
		$_GET   = array();
		$_POST  = array();
		$_FILES = array();
		wp_set_current_user(0);

		parent::tear_down();
	}

	/**
	 * Create a published client.
	 *
	 * @param string $title Client title.
	 * @return int
	 */
	protected function create_client($title = 'Test Client')
	{
		return self::factory()->post->create(
			array(
				'post_type'   => Clients::POST_TYPE,
				'post_status' => 'publish',
				'post_title'  => $title,
			)
		);
	}

	/**
	 * Create and assign a portal user.
	 *
	 * @param int $client_id Client post ID.
	 * @return int
	 */
	protected function create_client_user($client_id)
	{
		$user_id = self::factory()->user->create(array('role' => 'cliapwo_client'));
		update_post_meta($client_id, Clients::ASSIGNED_USERS_META_KEY, array($user_id));

		return $user_id;
	}

	/**
	 * Create a published request without dispatching form-save side effects.
	 *
	 * @param int    $client_id Client post ID.
	 * @param string $status    Request status.
	 * @return int
	 */
	protected function create_request($client_id, $status = Requests::STATUS_OPEN)
	{
		$request_id = self::factory()->post->create(
			array(
				'post_type'    => Requests::POST_TYPE,
				'post_status'  => 'publish',
				'post_title'   => 'Approval Request',
				'post_content' => 'Please review this request.',
			)
		);

		update_post_meta($request_id, Requests::CLIENT_META_KEY, $client_id);
		update_post_meta($request_id, Requests::STATUS_META_KEY, $status);

		return $request_id;
	}

	/**
	 * Invoke the centralized internal transition seam.
	 *
	 * @param int                  $request_id Request post ID.
	 * @param string               $status     New status.
	 * @param array<string, mixed> $context    Transition context.
	 * @return bool
	 */
	protected function transition_request($request_id, $status, array $context = array())
	{
		$method = new ReflectionMethod(Requests::class, 'transition_status');

		if (PHP_VERSION_ID < 80100) {
			$method->setAccessible(true);
		}

		return (bool) $method->invoke(new Requests(), $request_id, $status, $context);
	}

	/**
	 * Return request lifecycle events in chronological order.
	 *
	 * @param int $request_id Request post ID.
	 * @param int $client_id  Client post ID.
	 * @return array<int, WP_Post>
	 */
	protected function get_request_events($request_id, $client_id)
	{
		$histories = Events::get_request_histories(array($request_id), $client_id);

		return isset($histories[$request_id]) ? $histories[$request_id] : array();
	}
}
