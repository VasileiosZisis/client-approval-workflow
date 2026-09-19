<?php

use Vzisis\ClientApprovalWorkflow\Clients;
use Vzisis\ClientApprovalWorkflow\Onboarding;
use Vzisis\ClientApprovalWorkflow\Requests;
use Vzisis\ClientApprovalWorkflow\Sample_Content;
use Vzisis\ClientApprovalWorkflow\Settings;

/**
 * Onboarding, sample-content, and handler security regressions.
 */
class SupportingWorkflowsTest extends Cliapwo_Test_Case
{
	/**
	 * Invalid request-save nonces cannot change client linkage or status.
	 */
	public function test_request_save_rejects_invalid_nonce()
	{
		$client_a = $this->create_client('Original');
		$client_b = $this->create_client('Attempted');
		$request_id = $this->create_request($client_a);
		$_POST[Requests::SAVE_NONCE_NAME] = 'invalid';
		$_POST['cliapwo_request_client_id'] = (string) $client_b;
		$_POST['cliapwo_request_status'] = Requests::STATUS_REJECTED;

		(new Requests())->save_request_meta($request_id, get_post($request_id));

		$this->assertSame($client_a, Requests::get_client_id_for_request($request_id));
		$this->assertSame(Requests::STATUS_OPEN, Requests::get_status_for_request($request_id));
	}

	/**
	 * Valid intent without the management capability still cannot mutate a request.
	 */
	public function test_request_save_rejects_insufficient_capability()
	{
		$client_a = $this->create_client('Original');
		$client_b = $this->create_client('Attempted');
		$request_id = $this->create_request($client_a);
		$user_id = self::factory()->user->create(array('role' => 'subscriber'));
		wp_set_current_user($user_id);
		$_POST[Requests::SAVE_NONCE_NAME] = wp_create_nonce(Requests::SAVE_NONCE_ACTION);
		$_POST['cliapwo_request_client_id'] = (string) $client_b;
		$_POST['cliapwo_request_status'] = Requests::STATUS_REJECTED;

		(new Requests())->save_request_meta($request_id, get_post($request_id));

		$this->assertSame($client_a, Requests::get_client_id_for_request($request_id));
		$this->assertSame(Requests::STATUS_OPEN, Requests::get_status_for_request($request_id));
	}

	/**
	 * Sample records remain idempotent and excluded from onboarding milestones.
	 */
	public function test_sample_content_is_idempotent_and_excluded_from_onboarding()
	{
		update_option(Settings::OPTION_KEY, Settings::get_default_settings());
		$sample = new Sample_Content();
		$create = new ReflectionMethod(Sample_Content::class, 'create_or_repair');
		if (PHP_VERSION_ID < 80100) {
			$create->setAccessible(true);
		}

		$this->assertSame('created', $create->invoke($sample, $sample->get_state()));
		$first_state = $sample->get_state();
		$this->assertTrue($first_state['is_complete']);
		$this->assertSame('repaired', $create->invoke($sample, $sample->get_state()));
		$this->assertSame($first_state['recorded_ids'], $sample->get_state()['recorded_ids']);

		$progress = (new Onboarding())->get_progress();
		$this->assertFalse($progress['steps']['client']);
		$this->assertFalse($progress['steps']['request']);
		$this->assertFalse($progress['steps']['response']);
	}

	/**
	 * Activation preserves an existing settings option.
	 */
	public function test_activation_does_not_replace_existing_settings()
	{
		$settings = Settings::get_default_settings();
		$settings['primary_color'] = '#123456';
		update_option(Settings::OPTION_KEY, $settings);

		Vzisis\ClientApprovalWorkflow\Lifecycle::activate();

		$this->assertSame('#123456', Settings::get_settings()['primary_color']);
	}
}
