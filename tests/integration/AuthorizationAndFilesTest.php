<?php

use Vzisis\ClientApprovalWorkflow\Clients;
use Vzisis\ClientApprovalWorkflow\Events;
use Vzisis\ClientApprovalWorkflow\Files;
use Vzisis\ClientApprovalWorkflow\Requests;

/**
 * Cross-client authorization and protected-file regression coverage.
 */
class AuthorizationAndFilesTest extends Cliapwo_Test_Case
{
	/**
	 * Assigned users can see only their own client records and history.
	 */
	public function test_cross_client_records_are_not_returned()
	{
		$client_a = $this->create_client('Client A');
		$client_b = $this->create_client('Client B');
		$user_a   = $this->create_client_user($client_a);
		$request_a = $this->create_request($client_a);
		$request_b = $this->create_request($client_b);

		do_action('cliapwo_request_created', $request_a, $client_a);
		do_action('cliapwo_request_created', $request_b, $client_b);

		$this->assertTrue(Clients::user_can_view_client($client_a, $user_a));
		$this->assertFalse(Clients::user_can_view_client($client_b, $user_a));
		$this->assertSame(array($request_a), Requests::get_requests_query_for_client($client_a, array('fields' => 'ids'))->posts);
		$histories = Events::get_request_histories(array($request_a, $request_b), $client_a);
		$this->assertCount(1, $histories[$request_a]);
		$this->assertSame(array(), $histories[$request_b]);
	}

	/**
	 * Unpublished clients are never visible to assigned portal users.
	 */
	public function test_unpublished_client_is_not_visible_to_client_user()
	{
		$client_id = $this->create_client();
		$user_id   = $this->create_client_user($client_id);
		wp_update_post(array('ID' => $client_id, 'post_status' => 'draft'));

		$this->assertFalse(Clients::user_can_view_client($client_id, $user_id));
	}

	/**
	 * Protected file paths accept only plugin-managed relative names.
	 */
	public function test_protected_file_path_rejects_traversal_and_foreign_paths()
	{
		$client_id = $this->create_client();
		$file_id   = self::factory()->post->create(
			array(
				'post_type'   => Files::POST_TYPE,
				'post_status' => 'publish',
				'post_title'  => 'Private file',
			)
		);
		update_post_meta($file_id, Files::CLIENT_META_KEY, $client_id);

		foreach (array('../wp-config.php', 'uploads/file.pdf', '/cliapwo-private/../file.php') as $invalid_path) {
			update_post_meta($file_id, Files::STORED_RELATIVE_PATH_META_KEY, $invalid_path);
			$this->assertSame('', Files::get_stored_file_path($file_id));
		}
	}

	/**
	 * Download URLs contain only the routed file ID and a nonce, never a disk path.
	 */
	public function test_download_url_never_exposes_storage_path()
	{
		$file_id = self::factory()->post->create(array('post_type' => Files::POST_TYPE, 'post_status' => 'publish'));
		update_post_meta($file_id, Files::STORED_RELATIVE_PATH_META_KEY, 'cliapwo-private/server-secret.pdf');

		$url = Files::get_download_url($file_id);
		$this->assertStringContainsString('cliapwo_file_id=' . $file_id, $url);
		$this->assertStringNotContainsString('server-secret.pdf', $url);
		$this->assertStringNotContainsString('cliapwo-private', $url);
	}
}
