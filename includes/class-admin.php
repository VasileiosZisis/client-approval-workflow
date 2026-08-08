<?php

/**
 * Admin menu and settings page rendering.
 *
 * @package VzisisClientApprovalWorkflow
 */

namespace Vzisis\ClientApprovalWorkflow;

defined('ABSPATH') || exit;

/**
 * Registers the plugin admin pages.
 */
class Admin
{
	/**
	 * Settings-page stylesheet handle.
	 */
	private const STYLE_HANDLE = 'cliapwo-onboarding-admin';

	/**
	 * Settings service.
	 *
	 * @var Settings
	 */
	private $settings;

	/**
	 * Onboarding state service.
	 *
	 * @var Onboarding
	 */
	private $onboarding;

	/**
	 * Opt-in sample content service.
	 *
	 * @var Sample_Content
	 */
	private $sample_content;

	/**
	 * Registered settings screen hook suffixes.
	 *
	 * @var array<int, string>
	 */
	private $settings_screen_hooks = array();

	/**
	 * Constructor.
	 *
	 * @param Settings       $settings       Settings service.
	 * @param Onboarding     $onboarding     Onboarding service.
	 * @param Sample_Content $sample_content Sample content service.
	 */
	public function __construct(Settings $settings, Onboarding $onboarding, Sample_Content $sample_content)
	{
		$this->settings       = $settings;
		$this->onboarding     = $onboarding;
		$this->sample_content = $sample_content;
	}

	/**
	 * Register admin hooks.
	 *
	 * @return void
	 */
	public function register()
	{
		add_action('admin_menu', array($this, 'register_menu'));
		add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_assets'));
		add_action('admin_post_cliapwo_create_portal_page', array($this, 'handle_create_portal_page'));
	}

	/**
	 * Register the top-level menu and settings submenu.
	 *
	 * @return void
	 */
	public function register_menu()
	{
		$top_level_hook = add_menu_page(
			__('SignoffFlow', 'signoffflow-client-approval-workflow'),
			__('SignoffFlow', 'signoffflow-client-approval-workflow'),
			'cliapwo_manage_portal',
			Settings::PAGE_SLUG,
			array($this, 'render_settings_page'),
			'dashicons-yes-alt',
			56
		);

		$submenu_hook = add_submenu_page(
			Settings::PAGE_SLUG,
			__('Settings', 'signoffflow-client-approval-workflow'),
			__('Settings', 'signoffflow-client-approval-workflow'),
			'cliapwo_manage_portal',
			Settings::PAGE_SLUG,
			array($this, 'render_settings_page')
		);

		foreach (array($top_level_hook, $submenu_hook) as $screen_hook) {
			if (is_string($screen_hook) && '' !== $screen_hook) {
				$this->settings_screen_hooks[] = $screen_hook;
			}
		}

		$this->settings_screen_hooks = array_values(array_unique($this->settings_screen_hooks));
	}

	/**
	 * Enqueue onboarding styles only on the plugin settings screen.
	 *
	 * @param string $hook_suffix Current admin screen hook suffix.
	 * @return void
	 */
	public function enqueue_admin_assets($hook_suffix)
	{
		if (! in_array((string) $hook_suffix, $this->settings_screen_hooks, true)) {
			return;
		}

		wp_enqueue_style(
			self::STYLE_HANDLE,
			CLIAPWO_PLUGIN_URL . 'assets/css/cliapwo-onboarding-admin.css',
			array(),
			CLIAPWO_VERSION
		);
	}

	/**
	 * Render the settings page.
	 *
	 * @return void
	 */
	public function render_settings_page()
	{
		if (! current_user_can('cliapwo_manage_portal')) {
			wp_die(
				esc_html__('You are not allowed to manage SignoffFlow settings.', 'signoffflow-client-approval-workflow'),
				esc_html__('Forbidden', 'signoffflow-client-approval-workflow'),
				array(
					'response' => 403,
				)
			);
		}
?>
		<div class="wrap">
			<h1><?php esc_html_e('SignoffFlow Settings', 'signoffflow-client-approval-workflow'); ?></h1>

			<?php settings_errors(); ?>
			<?php $this->render_onboarding_panel(); ?>
			<?php $this->render_sample_content_card(); ?>

			<form action="options.php" method="post">
				<?php
				settings_fields(Settings::OPTION_GROUP);
				do_settings_sections(Settings::PAGE_SLUG);
				submit_button(__('Save Settings', 'signoffflow-client-approval-workflow'));
				?>
			</form>
		</div>
<?php
	}

	/**
	 * Create a sample portal page and store it in plugin settings.
	 *
	 * @return void
	 */
	public function handle_create_portal_page()
	{
		check_admin_referer('cliapwo_create_portal_page', 'cliapwo_create_portal_page_nonce');

		if (! current_user_can('cliapwo_manage_portal')) {
			wp_die(
				esc_html__('You are not allowed to create the portal page.', 'signoffflow-client-approval-workflow'),
				esc_html__('Forbidden', 'signoffflow-client-approval-workflow'),
				array(
					'response' => 403,
				)
			);
		}

		$settings       = Settings::get_settings();
		$portal_page_id = isset($settings['portal_page_id']) ? absint($settings['portal_page_id']) : 0;

		if ($portal_page_id > 0) {
			$portal_page = get_post($portal_page_id);

			if ($portal_page instanceof \WP_Post && 'page' === $portal_page->post_type && 'trash' !== $portal_page->post_status) {
				$this->redirect_to_settings(
					array(
						'cliapwo_onboarding_status' => 'existing',
					)
				);
			}
		}

		$portal_page_id = wp_insert_post(
			array(
				'post_title'   => __('Client Portal', 'signoffflow-client-approval-workflow'),
				'post_type'    => 'page',
				'post_status'  => 'publish',
				'post_content' => '[cliapwo_portal]',
			),
			true
		);

		if (is_wp_error($portal_page_id) || ! is_numeric($portal_page_id)) {
			$this->redirect_to_settings(
				array(
					'cliapwo_onboarding_status' => 'error',
				)
			);
		}

		$settings['portal_page_id'] = absint($portal_page_id);
		update_option(Settings::OPTION_KEY, $settings);

		$this->redirect_to_settings(
			array(
				'cliapwo_onboarding_status' => 'created',
			)
		);
	}

	/**
	 * Render state-aware onboarding on the settings page.
	 *
	 * @return void
	 */
	private function render_onboarding_panel()
	{
		$progress = $this->onboarding->get_progress();
		$status   = $this->get_verified_onboarding_status();

		$this->render_onboarding_status_notice($status);

		if (! empty($progress['is_dismissed'])) {
			$this->render_dismissed_onboarding($progress);
			return;
		}

		if (! empty($progress['is_complete'])) {
			$this->render_completed_onboarding($progress);
			return;
		}

		$steps = $this->get_onboarding_steps($progress);
		?>
		<details class="cliapwo-onboarding" <?php echo ! empty($progress['is_first_run']) ? 'open' : ''; ?>>
			<summary class="cliapwo-onboarding__summary">
				<span class="cliapwo-onboarding__summary-title"><?php esc_html_e('Set up SignoffFlow', 'signoffflow-client-approval-workflow'); ?></span>
				<span class="cliapwo-onboarding__summary-progress">
					<?php
					printf(
						/* translators: 1: completed onboarding step count, 2: total onboarding step count */
						esc_html__('%1$d of %2$d complete', 'signoffflow-client-approval-workflow'),
						absint($progress['completed_count']),
						absint($progress['total_count'])
					);
					?>
				</span>
			</summary>
			<div class="cliapwo-onboarding__body">
				<p><?php esc_html_e('Follow these steps to reach your first client response. Progress updates automatically from your SignoffFlow data.', 'signoffflow-client-approval-workflow'); ?></p>
				<progress
					class="cliapwo-onboarding__progress"
					value="<?php echo esc_attr((string) absint($progress['completed_count'])); ?>"
					max="<?php echo esc_attr((string) absint($progress['total_count'])); ?>"
					aria-label="<?php esc_attr_e('SignoffFlow setup progress', 'signoffflow-client-approval-workflow'); ?>"></progress>

				<ol class="cliapwo-onboarding__steps">
					<?php foreach ($steps as $step) : ?>
						<?php $this->render_onboarding_step($step); ?>
					<?php endforeach; ?>
				</ol>

				<?php $this->render_visibility_form('dismiss', __('Dismiss setup', 'signoffflow-client-approval-workflow')); ?>
			</div>
		</details>
		<?php
	}

	/**
	 * Return presentation data for onboarding steps.
	 *
	 * @param array<string, mixed> $progress Current onboarding progress.
	 * @return array<int, array<string, mixed>>
	 */
	private function get_onboarding_steps(array $progress)
	{
		$portal_page_id     = absint($progress['portal_page_id']);
		$client_id          = absint($progress['client_id']);
		$portal_edit_url    = $portal_page_id > 0 ? get_edit_post_link($portal_page_id, '') : '';
		$client_edit_url    = $client_id > 0 ? get_edit_post_link($client_id, '') : '';
		$portal_url         = $portal_page_id > 0 ? get_permalink($portal_page_id) : '';
		$steps              = array();
		$steps['portal']    = array(
			'complete'    => ! empty($progress['steps']['portal']),
			'title'       => __('Configure the portal page', 'signoffflow-client-approval-workflow'),
			'description' => __('Publish the page selected in Settings and include the [cliapwo_portal] shortcode.', 'signoffflow-client-approval-workflow'),
			'action_url'  => is_string($portal_edit_url) ? $portal_edit_url : '',
			'action_label' => __('Edit portal page', 'signoffflow-client-approval-workflow'),
			'action_type' => ! empty($progress['portal_page_exists']) ? 'link' : 'create_portal',
		);
		$steps['client']    = array(
			'complete'    => ! empty($progress['steps']['client']),
			'title'       => __('Create a client account', 'signoffflow-client-approval-workflow'),
			'description' => __('Add the client or account that will receive portal updates and approval requests.', 'signoffflow-client-approval-workflow'),
			'action_url'  => admin_url('post-new.php?post_type=' . Clients::POST_TYPE),
			'action_label' => __('Create client', 'signoffflow-client-approval-workflow'),
			'action_type' => 'link',
		);
		$steps['assignment'] = array(
			'complete'    => ! empty($progress['steps']['assignment']),
			'title'       => __('Assign a portal user', 'signoffflow-client-approval-workflow'),
			'description' => __('Assign an existing non-staff WordPress user who has permission to view the client portal.', 'signoffflow-client-approval-workflow'),
			'action_url'  => is_string($client_edit_url) ? $client_edit_url : admin_url('post-new.php?post_type=' . Clients::POST_TYPE),
			'action_label' => $client_id > 0 ? __('Assign portal user', 'signoffflow-client-approval-workflow') : __('Create client first', 'signoffflow-client-approval-workflow'),
			'action_type' => 'link',
		);
		$steps['request']   = array(
			'complete'    => ! empty($progress['steps']['request']),
			'title'       => __('Create an approval request', 'signoffflow-client-approval-workflow'),
			'description' => __('Publish a request for a real client account so it appears in their portal.', 'signoffflow-client-approval-workflow'),
			'action_url'  => admin_url('post-new.php?post_type=' . Requests::POST_TYPE),
			'action_label' => __('Create request', 'signoffflow-client-approval-workflow'),
			'action_type' => 'link',
		);
		$steps['response']  = array(
			'complete'    => ! empty($progress['steps']['response']),
			'title'       => __('Record a client response', 'signoffflow-client-approval-workflow'),
			'description' => __('Sign in as the assigned client user and submit any valid approval outcome from the portal.', 'signoffflow-client-approval-workflow'),
			'action_url'  => is_string($portal_url) ? $portal_url : '',
			'action_label' => __('Open client portal', 'signoffflow-client-approval-workflow'),
			'action_type' => 'external_link',
		);
		$next_step_found = false;

		foreach ($steps as &$step) {
			$step['is_next'] = false;

			if (! $next_step_found && empty($step['complete'])) {
				$step['is_next'] = true;
				$next_step_found = true;
			}
		}

		unset($step);

		return array_values($steps);
	}

	/**
	 * Render one onboarding checklist item.
	 *
	 * @param array<string, mixed> $step Step presentation data.
	 * @return void
	 */
	private function render_onboarding_step(array $step)
	{
		$complete = ! empty($step['complete']);
		?>
		<li class="cliapwo-onboarding__step <?php echo $complete ? 'is-complete' : 'is-incomplete'; ?>">
			<div class="cliapwo-onboarding__step-marker" aria-hidden="true"><?php echo $complete ? '&#10003;' : '&#8226;'; ?></div>
			<div class="cliapwo-onboarding__step-content">
				<div class="cliapwo-onboarding__step-heading">
					<strong><?php echo esc_html((string) $step['title']); ?></strong>
					<span class="cliapwo-onboarding__step-status">
						<?php
						if ($complete) {
							esc_html_e('Completed', 'signoffflow-client-approval-workflow');
						} elseif (! empty($step['is_next'])) {
							esc_html_e('Next step', 'signoffflow-client-approval-workflow');
						} else {
							esc_html_e('To do', 'signoffflow-client-approval-workflow');
						}
						?>
					</span>
				</div>
				<p><?php echo esc_html((string) $step['description']); ?></p>
				<?php if (! $complete) : ?>
					<?php $this->render_onboarding_step_action($step); ?>
				<?php endif; ?>
			</div>
		</li>
		<?php
	}

	/**
	 * Render an incomplete step action.
	 *
	 * @param array<string, mixed> $step Step presentation data.
	 * @return void
	 */
	private function render_onboarding_step_action(array $step)
	{
		if ('create_portal' === $step['action_type']) {
			?>
			<form class="cliapwo-onboarding__action-form" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" method="post">
				<input type="hidden" name="action" value="cliapwo_create_portal_page" />
				<?php wp_nonce_field('cliapwo_create_portal_page', 'cliapwo_create_portal_page_nonce'); ?>
				<?php submit_button(__('Create portal page', 'signoffflow-client-approval-workflow'), 'primary', 'submit', false); ?>
			</form>
			<?php
			return;
		}

		if ('' === (string) $step['action_url']) {
			return;
		}
		?>
		<a
			class="button button-primary"
			href="<?php echo esc_url((string) $step['action_url']); ?>"
			<?php echo 'external_link' === $step['action_type'] ? 'target="_blank" rel="noopener noreferrer"' : ''; ?>><?php echo esc_html((string) $step['action_label']); ?></a>
		<?php
	}

	/**
	 * Render compact state for a dismissed administrator.
	 *
	 * @param array<string, mixed> $progress Current onboarding progress.
	 * @return void
	 */
	private function render_dismissed_onboarding(array $progress)
	{
		?>
		<section class="cliapwo-onboarding cliapwo-onboarding--compact">
			<div>
				<h2><?php esc_html_e('SignoffFlow setup progress', 'signoffflow-client-approval-workflow'); ?></h2>
				<p>
					<?php
					printf(
						/* translators: 1: completed onboarding step count, 2: total onboarding step count */
						esc_html__('Setup is hidden. %1$d of %2$d steps are complete.', 'signoffflow-client-approval-workflow'),
						absint($progress['completed_count']),
						absint($progress['total_count'])
					);
					?>
				</p>
			</div>
			<?php $this->render_visibility_form('reopen', __('Show setup progress', 'signoffflow-client-approval-workflow')); ?>
		</section>
		<?php
	}

	/**
	 * Render the compact successful onboarding state.
	 *
	 * @param array<string, mixed> $progress Current onboarding progress.
	 * @return void
	 */
	private function render_completed_onboarding(array $progress)
	{
		$portal_page_id = absint($progress['portal_page_id']);
		$portal_url     = $portal_page_id > 0 ? get_permalink($portal_page_id) : '';
		?>
		<section class="cliapwo-onboarding cliapwo-onboarding--complete">
			<div>
				<h2><?php esc_html_e('SignoffFlow setup complete', 'signoffflow-client-approval-workflow'); ?></h2>
				<p><?php esc_html_e('Your portal has reached its first recorded client response. You can continue managing normal settings below.', 'signoffflow-client-approval-workflow'); ?></p>
			</div>
			<?php if (is_string($portal_url) && '' !== $portal_url) : ?>
				<a class="button button-primary" href="<?php echo esc_url($portal_url); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e('View portal', 'signoffflow-client-approval-workflow'); ?></a>
			<?php endif; ?>
		</section>
		<?php
	}

	/**
	 * Render a standalone onboarding visibility form.
	 *
	 * @param string $visibility Requested visibility action.
	 * @param string $label      Submit button label.
	 * @return void
	 */
	private function render_visibility_form($visibility, $label)
	{
		?>
		<form class="cliapwo-onboarding__visibility-form" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" method="post">
			<input type="hidden" name="action" value="<?php echo esc_attr(Onboarding::VISIBILITY_ACTION); ?>" />
			<input type="hidden" name="cliapwo_onboarding_visibility" value="<?php echo esc_attr($visibility); ?>" />
			<?php wp_nonce_field(Onboarding::VISIBILITY_NONCE_ACTION, Onboarding::VISIBILITY_NONCE_NAME); ?>
			<?php submit_button($label, 'secondary', 'submit', false); ?>
		</form>
		<?php
	}

	/**
	 * Render an allowlisted status message from a verified action redirect.
	 *
	 * @param string $status Verified status key.
	 * @return void
	 */
	private function render_onboarding_status_notice($status)
	{
		$messages = array(
			'created'  => __('Portal page created and saved to SignoffFlow settings.', 'signoffflow-client-approval-workflow'),
			'existing' => __('A portal page is already configured in SignoffFlow settings.', 'signoffflow-client-approval-workflow'),
			'error'    => __('The portal page could not be created automatically. Create a page manually, add the [cliapwo_portal] shortcode, and select it below.', 'signoffflow-client-approval-workflow'),
		);

		if (! isset($messages[$status])) {
			return;
		}

		$notice_class = 'error' === $status ? 'notice-error' : 'notice-success';
		?>
		<div class="notice <?php echo esc_attr($notice_class); ?> is-dismissible"><p><?php echo esc_html($messages[$status]); ?></p></div>
		<?php
	}

	/**
	 * Render the always-available sample workflow card.
	 *
	 * @return void
	 */
	private function render_sample_content_card()
	{
		$state       = $this->sample_content->get_state();
		$status      = isset($state['status']) ? sanitize_key((string) $state['status']) : 'empty';
		$preview_url = $this->sample_content->get_preview_url($state);

		$this->render_sample_content_notice($this->sample_content->get_verified_notice_status());
		?>
		<section class="cliapwo-sample-content cliapwo-sample-content--<?php echo esc_attr(sanitize_html_class($status)); ?>" aria-labelledby="cliapwo-sample-content-title">
			<div class="cliapwo-sample-content__header">
				<div>
					<h2 id="cliapwo-sample-content-title"><?php esc_html_e('Explore with sample content', 'signoffflow-client-approval-workflow'); ?></h2>
					<p><?php esc_html_e('Create a clearly labeled client, update, and open approval request to preview the workflow before entering real client data.', 'signoffflow-client-approval-workflow'); ?></p>
				</div>
				<span class="cliapwo-sample-content__status">
					<span aria-hidden="true"><?php echo 'ready' === $status ? '&#10003;' : ('partial' === $status ? '!' : '&#8226;'); ?></span>
					<?php
					if ('ready' === $status) {
						esc_html_e('Sample ready', 'signoffflow-client-approval-workflow');
					} elseif ('partial' === $status) {
						esc_html_e('Repair needed', 'signoffflow-client-approval-workflow');
					} else {
						esc_html_e('Not created', 'signoffflow-client-approval-workflow');
					}
					?>
				</span>
			</div>

			<p class="cliapwo-sample-content__privacy-note">
				<?php esc_html_e('Sample creation does not add or assign users, create files, send email, contact external services, or count toward onboarding progress.', 'signoffflow-client-approval-workflow'); ?>
			</p>

			<?php if ('partial' === $status) : ?>
				<p class="cliapwo-sample-content__warning"><strong><?php esc_html_e('Some recorded sample items are missing or no longer connected correctly. Repair recreates only what is needed and keeps edited titles and content.', 'signoffflow-client-approval-workflow'); ?></strong></p>
			<?php endif; ?>

			<?php if (! empty($state['has_records'])) : ?>
				<?php $this->render_sample_content_links($state); ?>
			<?php endif; ?>

			<div class="cliapwo-sample-content__actions">
				<?php if ('ready' !== $status) : ?>
					<?php $this->render_sample_content_action_form('create', 'partial' === $status ? __('Repair sample content', 'signoffflow-client-approval-workflow') : __('Create sample content', 'signoffflow-client-approval-workflow'), 'primary'); ?>
				<?php elseif ('' !== $preview_url) : ?>
					<a class="button button-primary" href="<?php echo esc_url($preview_url); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e('Preview sample portal', 'signoffflow-client-approval-workflow'); ?></a>
				<?php else : ?>
					<a class="button button-primary" href="#cliapwo_portal_page_id"><?php esc_html_e('Configure portal to preview', 'signoffflow-client-approval-workflow'); ?></a>
				<?php endif; ?>
			</div>

			<?php if (! empty($state['has_records'])) : ?>
				<form class="cliapwo-sample-content__cleanup" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" method="post">
					<input type="hidden" name="action" value="<?php echo esc_attr(Sample_Content::ACTION); ?>" />
					<input type="hidden" name="<?php echo esc_attr(Sample_Content::OPERATION_FIELD); ?>" value="cleanup" />
					<?php wp_nonce_field(Sample_Content::NONCE_ACTION, Sample_Content::NONCE_NAME); ?>
					<label>
						<input type="checkbox" name="<?php echo esc_attr(Sample_Content::CLEANUP_CONFIRMATION_FIELD); ?>" value="1" />
						<?php esc_html_e('I understand that cleanup permanently deletes every recorded item that still has the SignoffFlow sample marker, including items I edited.', 'signoffflow-client-approval-workflow'); ?>
					</label>
					<?php submit_button(__('Delete sample content', 'signoffflow-client-approval-workflow'), 'delete', 'submit', false); ?>
				</form>
			<?php endif; ?>
		</section>
		<?php
	}

	/**
	 * Render direct edit links for valid sample workflow records.
	 *
	 * @param array<string, mixed> $state Sample state.
	 * @return void
	 */
	private function render_sample_content_links(array $state)
	{
		$links = array();
		$labels = array(
			'client_id'  => __('Edit sample client', 'signoffflow-client-approval-workflow'),
			'update_id'  => __('Edit sample update', 'signoffflow-client-approval-workflow'),
			'request_id' => __('Edit sample request', 'signoffflow-client-approval-workflow'),
		);

		foreach ($labels as $state_key => $label) {
			$post_id  = isset($state[$state_key]) ? absint($state[$state_key]) : 0;
			$edit_url = $post_id > 0 ? get_edit_post_link($post_id, '') : '';

			if (is_string($edit_url) && '' !== $edit_url) {
				$links[] = sprintf('<a href="%1$s">%2$s</a>', esc_url($edit_url), esc_html($label));
			}
		}

		if (empty($links)) {
			return;
		}
		?>
		<nav class="cliapwo-sample-content__links" aria-label="<?php echo esc_attr__('Sample content records', 'signoffflow-client-approval-workflow'); ?>">
			<?php echo wp_kses_post(implode('<span aria-hidden="true">&middot;</span>', $links)); ?>
		</nav>
		<?php
	}

	/**
	 * Render a sample-content create or repair form.
	 *
	 * @param string $operation Submitted operation.
	 * @param string $label     Button label.
	 * @param string $type      WordPress button type.
	 * @return void
	 */
	private function render_sample_content_action_form($operation, $label, $type)
	{
		?>
		<form class="cliapwo-sample-content__action-form" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" method="post">
			<input type="hidden" name="action" value="<?php echo esc_attr(Sample_Content::ACTION); ?>" />
			<input type="hidden" name="<?php echo esc_attr(Sample_Content::OPERATION_FIELD); ?>" value="<?php echo esc_attr($operation); ?>" />
			<?php wp_nonce_field(Sample_Content::NONCE_ACTION, Sample_Content::NONCE_NAME); ?>
			<?php submit_button($label, $type, 'submit', false); ?>
		</form>
		<?php
	}

	/**
	 * Render an allowlisted status message from a sample-content action.
	 *
	 * @param string $status Verified status key.
	 * @return void
	 */
	private function render_sample_content_notice($status)
	{
		$messages = array(
			'created'               => __('Sample content created. Preview the sample portal or edit the generated records below.', 'signoffflow-client-approval-workflow'),
			'existing'              => __('The recorded sample content is already complete, so no duplicate records were created.', 'signoffflow-client-approval-workflow'),
			'repaired'              => __('Sample content repaired without overwriting edited titles or content.', 'signoffflow-client-approval-workflow'),
			'removed'               => __('Sample content permanently deleted.', 'signoffflow-client-approval-workflow'),
			'partial'               => __('SignoffFlow created part of the sample workflow but could not finish it. Use Repair sample content to retry.', 'signoffflow-client-approval-workflow'),
			'cleanup_partial'       => __('Some recorded items were not deleted because their sample marker or post type no longer matched. No unverified content was removed.', 'signoffflow-client-approval-workflow'),
			'confirmation_required' => __('Confirm that you understand the cleanup is permanent before deleting sample content.', 'signoffflow-client-approval-workflow'),
			'error'                 => __('Sample content could not be created. No existing real content was changed.', 'signoffflow-client-approval-workflow'),
		);

		if (! isset($messages[$status])) {
			return;
		}

		$error_statuses = array('partial', 'cleanup_partial', 'confirmation_required', 'error');
		$notice_class   = in_array($status, $error_statuses, true) ? 'notice-warning' : 'notice-success';
		?>
		<div class="notice <?php echo esc_attr($notice_class); ?> is-dismissible"><p><?php echo esc_html($messages[$status]); ?></p></div>
		<?php
	}

	/**
	 * Return a nonce-verified onboarding notice status from the query string.
	 *
	 * @return string
	 */
	private function get_verified_onboarding_status()
	{
		if (! isset($_GET['cliapwo_onboarding_status'], $_GET['cliapwo_onboarding_notice_nonce'])) {
			return '';
		}

		$status = sanitize_key(wp_unslash($_GET['cliapwo_onboarding_status']));
		$nonce  = sanitize_text_field(wp_unslash($_GET['cliapwo_onboarding_notice_nonce']));

		if (
			! current_user_can('cliapwo_manage_portal')
			|| ! wp_verify_nonce($nonce, 'cliapwo_onboarding_notice')
			|| ! in_array($status, array('created', 'existing', 'error'), true)
		) {
			return '';
		}

		return $status;
	}

	/**
	 * Redirect back to the settings page with onboarding state.
	 *
	 * @param array<string, string> $query_args Query arguments to append.
	 * @return void
	 */
	private function redirect_to_settings(array $query_args = array())
	{
		if (isset($query_args['cliapwo_onboarding_status'])) {
			$query_args['cliapwo_onboarding_notice_nonce'] = wp_create_nonce('cliapwo_onboarding_notice');
		}

		$redirect_url = add_query_arg(
			array_merge(
				array(
					'page' => Settings::PAGE_SLUG,
				),
				$query_args
			),
			admin_url('admin.php')
		);

		wp_safe_redirect($redirect_url);
		exit;
	}
}
