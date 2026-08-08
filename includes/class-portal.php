<?php

/**
 * Portal shortcode rendering.
 *
 * @package VzisisClientApprovalWorkflow
 */

namespace Vzisis\ClientApprovalWorkflow;

defined('ABSPATH') || exit;

/**
 * Renders the client portal shortcode.
 */
class Portal
{
	/**
	 * Portal stylesheet handle.
	 */
	public const STYLE_HANDLE = 'cliapwo-portal';

	/**
	 * Register portal hooks.
	 *
	 * @return void
	 */
	public function register()
	{
		add_action('wp_enqueue_scripts', array($this, 'register_assets'));
		add_action('wp_enqueue_scripts', array($this, 'maybe_enqueue_assets'));
		add_action('template_redirect', array($this, 'redirect_guests_from_portal_page'), 1);
		add_filter('template_include', array($this, 'filter_portal_template'), 99);
		add_shortcode('cliapwo_portal', array($this, 'render_shortcode'));
	}

	/**
	 * Register frontend portal assets.
	 *
	 * @return void
	 */
	public function register_assets()
	{
		wp_register_style(
			self::STYLE_HANDLE,
			CLIAPWO_PLUGIN_URL . 'assets/css/portal.css',
			array(),
			CLIAPWO_VERSION
		);
	}

	/**
	 * Enqueue portal assets only on likely portal pages.
	 *
	 * @return void
	 */
	public function maybe_enqueue_assets()
	{
		if (! is_singular()) {
			return;
		}

		$post = get_queried_object();

		if (! $post instanceof \WP_Post) {
			return;
		}

		$settings       = Settings::get_settings();
		$portal_page_id = isset($settings['portal_page_id']) ? absint($settings['portal_page_id']) : 0;
		$post_content   = is_string($post->post_content) ? $post->post_content : '';

		if ( ( $portal_page_id > 0 && $post->ID === $portal_page_id ) || has_shortcode( $post_content, 'cliapwo_portal' ) ) {
			wp_enqueue_style(self::STYLE_HANDLE);
		}
	}

	/**
	 * Redirect signed-out visitors before the dedicated portal template outputs.
	 *
	 * @return void
	 */
	public function redirect_guests_from_portal_page()
	{
		if (! $this->is_configured_portal_page() || is_user_logged_in()) {
			return;
		}

		wp_safe_redirect(wp_login_url($this->get_portal_url()));
		exit;
	}

	/**
	 * Use the focused app template for the configured portal page.
	 *
	 * @param string $template Resolved theme template path.
	 * @return string
	 */
	public function filter_portal_template($template)
	{
		if (! $this->is_configured_portal_page()) {
			return $template;
		}

		$portal_template = CLIAPWO_PLUGIN_DIR . 'templates/portal.php';

		return file_exists($portal_template) ? $portal_template : $template;
	}

	/**
	 * Render the client portal shortcode.
	 *
	 * @param array<string, mixed> $atts Shortcode attributes.
	 * @return string
	 */
	public function render_shortcode($atts)
	{
		$settings = Settings::get_settings();

		if (is_admin() && ! wp_doing_ajax()) {
			return '';
		}

		wp_enqueue_style(self::STYLE_HANDLE);

		if (! is_user_logged_in()) {
			$login_url = wp_login_url($this->get_portal_url());

			if (! headers_sent()) {
				wp_safe_redirect($login_url);
				exit;
			}

			return $this->wrap_empty_state(
				sprintf(
					'<p class="cliapwo-empty">%s</p>',
					sprintf(
						/* translators: %s: login URL */
						esc_html__('Please log in to view your client portal: %s', 'signoffflow-client-approval-workflow'),
						esc_url($login_url)
					)
				),
				$settings
			);
		}

		$atts            = shortcode_atts(
			array(
				'client_id' => 0,
			),
			$atts,
			'cliapwo_portal'
		);
		$current_user_id = get_current_user_id();
		$requested_id    = absint($atts['client_id']);
		$sample_preview  = Sample_Content::get_preview_request();

		if (! empty($sample_preview['requested']) && absint($sample_preview['client_id']) <= 0) {
			return $this->wrap_empty_state(
				'<p class="cliapwo-empty">' . esc_html__('This sample preview link is invalid or the sample content is no longer available.', 'signoffflow-client-approval-workflow') . '</p>',
				$settings
			);
		}

		if (! empty($sample_preview['requested'])) {
			$requested_id = absint($sample_preview['client_id']);
		}

		$client          = $this->resolve_client($requested_id, $current_user_id);

		if (! $client instanceof \WP_Post) {
			return $this->wrap_empty_state(
				'<p class="cliapwo-empty">' . esc_html__('No portal assigned.', 'signoffflow-client-approval-workflow') . '</p>',
				$settings
			);
		}

		if (! Clients::user_can_view_client($client->ID, $current_user_id)) {
			return $this->wrap_empty_state(
				'<p class="cliapwo-empty">' . esc_html__('You do not have access to this portal.', 'signoffflow-client-approval-workflow') . '</p>',
				$settings
			);
		}

		$paged            = $this->get_current_page();
		$updates_query    = Updates::get_updates_query_for_client(
			$client->ID,
			array(
				'paged' => $paged,
			)
		);
		$requests_query = Requests::get_requests_query_for_client($client->ID);
		$request_ids   = array();

		foreach ($requests_query->posts as $request_post) {
			if ($request_post instanceof \WP_Post) {
				$request_ids[] = $request_post->ID;
			}
		}

		$request_histories = Events::get_request_histories($request_ids, $client->ID);
		$files_query      = Files::get_files_query_for_client($client->ID);
		$open_requests    = Requests::get_open_request_count_for_client($client->ID);
		$logo_url         = $this->get_branding_logo_url($settings);
		$primary_color    = isset($settings['primary_color']) ? sanitize_hex_color((string) $settings['primary_color']) : false;
		$updates_count    = $this->get_query_count($updates_query);
		$requests_count   = $this->get_query_count($requests_query);
		$files_count      = $this->get_query_count($files_query);
		$is_staff_preview = current_user_can('cliapwo_manage_portal') && ! in_array($current_user_id, Clients::get_assigned_user_ids($client->ID), true);
		$current_user     = wp_get_current_user();
		$account_name     = $current_user instanceof \WP_User ? $current_user->display_name : '';
		$logout_url       = wp_logout_url($this->get_portal_url());
		$wrapper_classes  = $this->get_wrapper_classes($client->ID, $current_user_id, $is_staff_preview);
		$root_style       = $this->get_root_style_attribute($primary_color, $settings, $client->ID, $current_user_id, $is_staff_preview);

		ob_start();
?>
		<div
			class="<?php echo esc_attr($wrapper_classes); ?>"
			style="<?php echo esc_attr($root_style); ?>">
			<?php do_action('cliapwo_before_render_portal', $client->ID, $current_user_id); ?>

			<header class="<?php echo esc_attr($this->get_section_classes('header', array('cliapwo-portal__header'), $client->ID, $current_user_id)); ?>">
				<div class="cliapwo-portal__topbar">
					<div class="cliapwo-portal__topbar-context">
						<div class="cliapwo-portal__brand">
							<?php if ('' !== $logo_url) : ?>
								<img
									src="<?php echo esc_url($logo_url); ?>"
									alt="<?php echo esc_attr__('SignoffFlow logo', 'signoffflow-client-approval-workflow'); ?>"
									class="cliapwo-portal__logo" />
							<?php else : ?>
								<span class="cliapwo-portal__brand-mark" aria-hidden="true">
									<svg viewBox="0 0 40 40" focusable="false">
										<path d="M33 19a14 14 0 1 1-6.4-11.7" />
										<path d="m12.5 19.2 5 5.1L32 7.8" />
									</svg>
								</span>
								<span class="cliapwo-portal__brand-name"><span>Signoff</span><span class="cliapwo-portal__brand-name-accent">Flow</span></span>
							<?php endif; ?>
						</div>

						<?php if ($is_staff_preview) : ?>
							<p class="cliapwo-portal__preview-note cliapwo-status cliapwo-status--preview">
								<?php esc_html_e('Staff preview', 'signoffflow-client-approval-workflow'); ?>
							</p>
						<?php endif; ?>
					</div>
					<div class="cliapwo-portal__account">
						<span class="cliapwo-portal__account-copy">
							<span class="cliapwo-portal__account-label"><?php esc_html_e('Signed in as', 'signoffflow-client-approval-workflow'); ?></span>
							<strong><?php echo esc_html($account_name); ?></strong>
						</span>
						<a class="cliapwo-portal__logout" href="<?php echo esc_url($logout_url); ?>"><?php esc_html_e('Sign out', 'signoffflow-client-approval-workflow'); ?></a>
					</div>
				</div>

				<div class="cliapwo-portal__identity">
					<div class="cliapwo-portal__header-copy">
						<div class="cliapwo-portal__eyebrow"><?php esc_html_e('Client workspace', 'signoffflow-client-approval-workflow'); ?></div>
						<h1 class="cliapwo-portal__title"><?php echo esc_html($client->post_title); ?></h1>
						<p class="cliapwo-portal__intro"><?php esc_html_e('Updates, decisions, and shared deliverables in one secure place.', 'signoffflow-client-approval-workflow'); ?></p>
					</div>
					<div class="cliapwo-portal__stats" aria-label="<?php echo esc_attr__('Workspace totals', 'signoffflow-client-approval-workflow'); ?>">
						<span><strong><?php echo esc_html((string) $updates_count); ?></strong><?php esc_html_e('Updates', 'signoffflow-client-approval-workflow'); ?></span>
						<span><strong><?php echo esc_html((string) $requests_count); ?></strong><?php esc_html_e('Requests', 'signoffflow-client-approval-workflow'); ?></span>
						<span><strong><?php echo esc_html((string) $files_count); ?></strong><?php esc_html_e('Files', 'signoffflow-client-approval-workflow'); ?></span>
					</div>
				</div>

				<nav class="cliapwo-portal__nav" aria-label="<?php echo esc_attr__('Portal sections', 'signoffflow-client-approval-workflow'); ?>">
					<a href="#cliapwo-overview"><?php esc_html_e('Overview', 'signoffflow-client-approval-workflow'); ?></a>
					<a href="#cliapwo-updates"><?php esc_html_e('Updates', 'signoffflow-client-approval-workflow'); ?></a>
					<a href="#cliapwo-requests"><?php esc_html_e('Requests', 'signoffflow-client-approval-workflow'); ?></a>
					<a href="#cliapwo-files"><?php esc_html_e('Files', 'signoffflow-client-approval-workflow'); ?></a>
				</nav>
			</header>

			<section id="cliapwo-overview" class="<?php echo esc_attr($this->get_section_classes('summary', array('cliapwo-portal__section', 'cliapwo-portal__summary'), $client->ID, $current_user_id)); ?>">
				<div>
					<p class="cliapwo-portal__summary-label"><?php esc_html_e('Action required', 'signoffflow-client-approval-workflow'); ?></p>
					<?php if ($open_requests > 0) : ?>
						<p class="cliapwo-portal__summary-copy">
							<?php
							printf(
								/* translators: %d: number of open requests */
								esc_html(_n('%d request needs your attention.', '%d requests need your attention.', $open_requests, 'signoffflow-client-approval-workflow')),
								esc_html($open_requests)
							);
							?>
						</p>
					<?php else : ?>
						<p class="cliapwo-portal__summary-copy"><?php esc_html_e('You are all caught up.', 'signoffflow-client-approval-workflow'); ?></p>
					<?php endif; ?>
				</div>
				<?php if ($open_requests > 0) : ?>
					<a class="cliapwo-portal__summary-link" href="#cliapwo-requests"><?php esc_html_e('Review requests', 'signoffflow-client-approval-workflow'); ?></a>
				<?php endif; ?>
			</section>

			<div class="cliapwo-portal__main">
				<section id="cliapwo-updates" class="<?php echo esc_attr($this->get_section_classes('updates', array('cliapwo-portal__section', 'cliapwo-portal__updates'), $client->ID, $current_user_id)); ?>">
					<div class="cliapwo-portal__section-header">
						<div>
							<h2 class="cliapwo-portal__section-title"><?php esc_html_e('Updates', 'signoffflow-client-approval-workflow'); ?></h2>
							<p class="cliapwo-portal__section-intro"><?php esc_html_e('Latest progress and delivery notes from your team.', 'signoffflow-client-approval-workflow'); ?></p>
						</div>
					</div>

					<?php if ($updates_query->have_posts()) : ?>
						<div class="cliapwo-portal__timeline">
							<?php while ($updates_query->have_posts()) : ?>
								<?php
								$updates_query->the_post();
								$update_id    = get_the_ID();
								$author_name  = get_the_author_meta('display_name', (int) get_post_field('post_author', $update_id));
								$update_title = get_the_title($update_id);
								?>
								<article class="cliapwo-portal__update">
									<h3><?php echo esc_html($update_title); ?></h3>
									<p class="cliapwo-portal__meta">
										<?php
										printf(
											/* translators: 1: date, 2: author name */
											esc_html__('Posted on %1$s by %2$s', 'signoffflow-client-approval-workflow'),
											esc_html(get_the_date('', $update_id)),
											esc_html($author_name ? $author_name : __('SignoffFlow', 'signoffflow-client-approval-workflow'))
										);
										?>
									</p>
									<div class="cliapwo-portal__content">
										<?php echo wp_kses_post(wpautop((string) get_post_field('post_content', $update_id))); ?>
									</div>
								</article>
							<?php endwhile; ?>
						</div>

						<?php
						$pagination = paginate_links(
							array(
								'current' => $paged,
								'total'   => (int) $updates_query->max_num_pages,
								'type'    => 'list',
							)
						);

						if (is_string($pagination) && '' !== $pagination) {
							echo '<div class="cliapwo-portal__pagination">';
							echo wp_kses_post($pagination);
							echo '</div>';
						}
						?>
					<?php else : ?>
						<p class="cliapwo-empty">
							<?php
							echo esc_html(
								$is_staff_preview
									? __('No updates yet. Publish a client update from client-approval-workflow > Updates.', 'signoffflow-client-approval-workflow')
									: __('No updates yet. New project updates will appear here.', 'signoffflow-client-approval-workflow')
							);
							?>
						</p>
					<?php endif; ?>
				</section>

				<div class="cliapwo-portal__grid">
					<section id="cliapwo-requests" class="<?php echo esc_attr($this->get_section_classes('requests', array('cliapwo-portal__section', 'cliapwo-portal__requests'), $client->ID, $current_user_id)); ?>">
						<div class="cliapwo-portal__section-header">
							<div>
								<h2 class="cliapwo-portal__section-title"><?php esc_html_e('Requests', 'signoffflow-client-approval-workflow'); ?></h2>
								<p class="cliapwo-portal__section-intro"><?php esc_html_e('Outstanding actions and confirmations for this client account.', 'signoffflow-client-approval-workflow'); ?></p>
							</div>
						</div>
						<?php if ($requests_query->have_posts()) : ?>
							<ul class="cliapwo-portal__request-list cliapwo-list">
								<?php while ($requests_query->have_posts()) : ?>
									<?php
									$requests_query->the_post();
									$request_id      = get_the_ID();
									$request_status  = Requests::get_status_for_request($request_id);
									$can_manage      = current_user_can('cliapwo_manage_portal');
									$can_choose_outcome = ! $can_manage && Requests::STATUS_OPEN === $request_status;
									$can_reopen      = $can_manage && Requests::is_resolved_status($request_status);
									?>
									<li class="cliapwo-portal__request">
										<div class="cliapwo-portal__request-header">
											<strong><?php echo esc_html(get_the_title($request_id)); ?></strong>
											<span class="cliapwo-portal__request-status cliapwo-status cliapwo-status--<?php echo esc_attr(sanitize_html_class($request_status)); ?>">
												<?php echo esc_html(Requests::get_status_label($request_status)); ?>
											</span>
										</div>

										<?php if ('' !== (string) get_post_field('post_content', $request_id)) : ?>
											<div class="cliapwo-portal__request-content">
												<?php echo wp_kses_post(wpautop((string) get_post_field('post_content', $request_id))); ?>
											</div>
										<?php endif; ?>

										<?php $this->render_request_response_summary($request_id, $request_status); ?>
										<?php $this->render_request_history($request_id, isset($request_histories[$request_id]) ? $request_histories[$request_id] : array()); ?>

										<?php if ($can_choose_outcome || $can_reopen) : ?>
											<form
												method="post"
												action="<?php echo esc_url(admin_url('admin-post.php')); ?>"
												class="cliapwo-portal__request-form">
												<input type="hidden" name="action" value="<?php echo esc_attr(Requests::STATUS_UPDATE_ACTION); ?>" />
												<input type="hidden" name="cliapwo_request_id" value="<?php echo esc_attr((string) $request_id); ?>" />
												<?php wp_nonce_field(Requests::STATUS_UPDATE_ACTION, Requests::STATUS_UPDATE_NONCE_NAME); ?>

												<?php if ($can_choose_outcome) : ?>
													<div class="cliapwo-portal__request-note-field">
														<label for="cliapwo_request_response_note_<?php echo esc_attr((string) $request_id); ?>">
															<strong><?php esc_html_e('Response note', 'signoffflow-client-approval-workflow'); ?></strong>
														</label>
														<textarea
															id="cliapwo_request_response_note_<?php echo esc_attr((string) $request_id); ?>"
															name="cliapwo_request_response_note"
															class="cliapwo-portal__request-note"
															rows="4"
															maxlength="<?php echo esc_attr((string) Requests::RESPONSE_NOTE_MAX_LENGTH); ?>"
															required
															aria-describedby="cliapwo_request_response_note_help_<?php echo esc_attr((string) $request_id); ?>"></textarea>
														<p id="cliapwo_request_response_note_help_<?php echo esc_attr((string) $request_id); ?>" class="cliapwo-portal__request-note-help">
															<?php esc_html_e('Required when requesting changes, rejecting, or blocking. Optional when approving. Maximum 500 characters.', 'signoffflow-client-approval-workflow'); ?>
														</p>
													</div>
													<button type="submit" name="cliapwo_request_status" value="<?php echo esc_attr(Requests::STATUS_APPROVED); ?>" class="cliapwo-button cliapwo-button--approve" formnovalidate><?php esc_html_e('Approve', 'signoffflow-client-approval-workflow'); ?></button>
													<button type="submit" name="cliapwo_request_status" value="<?php echo esc_attr(Requests::STATUS_CHANGES_REQUESTED); ?>" class="cliapwo-button cliapwo-button--secondary cliapwo-button--changes"><?php esc_html_e('Request changes', 'signoffflow-client-approval-workflow'); ?></button>
													<button type="submit" name="cliapwo_request_status" value="<?php echo esc_attr(Requests::STATUS_REJECTED); ?>" class="cliapwo-button cliapwo-button--secondary cliapwo-button--reject"><?php esc_html_e('Reject', 'signoffflow-client-approval-workflow'); ?></button>
													<button type="submit" name="cliapwo_request_status" value="<?php echo esc_attr(Requests::STATUS_BLOCKED); ?>" class="cliapwo-button cliapwo-button--secondary cliapwo-button--block"><?php esc_html_e('Block', 'signoffflow-client-approval-workflow'); ?></button>
												<?php elseif ($can_reopen) : ?>
													<input type="hidden" name="cliapwo_request_status" value="<?php echo esc_attr(Requests::STATUS_OPEN); ?>" />
													<button type="submit" class="cliapwo-button cliapwo-button--secondary cliapwo-button--reopen"><?php esc_html_e('Reopen', 'signoffflow-client-approval-workflow'); ?></button>
												<?php endif; ?>
											</form>
										<?php endif; ?>
									</li>
								<?php endwhile; ?>
							</ul>
						<?php else : ?>
							<p class="cliapwo-empty">
								<?php
								echo esc_html(
									$is_staff_preview
										? __('No requests yet. Add one from client-approval-workflow > Requests.', 'signoffflow-client-approval-workflow')
										: __('No requests yet. Your team will add anything they still need from you here.', 'signoffflow-client-approval-workflow')
								);
								?>
							</p>
						<?php endif; ?>
					</section>

					<section id="cliapwo-files" class="<?php echo esc_attr($this->get_section_classes('files', array('cliapwo-portal__section', 'cliapwo-portal__files'), $client->ID, $current_user_id)); ?>">
						<div class="cliapwo-portal__section-header">
							<div>
								<h2 class="cliapwo-portal__section-title"><?php esc_html_e('Files', 'signoffflow-client-approval-workflow'); ?></h2>
								<p class="cliapwo-portal__section-intro"><?php esc_html_e('Shared deliverables and protected downloads for this client account.', 'signoffflow-client-approval-workflow'); ?></p>
							</div>
						</div>

						<?php if ($files_query->have_posts()) : ?>
							<ul class="cliapwo-portal__file-list cliapwo-list">
								<?php while ($files_query->have_posts()) : ?>
									<?php
									$files_query->the_post();
									$file_post_id = get_the_ID();
									$file_name    = (string) get_post_meta($file_post_id, Files::ORIGINAL_FILENAME_META_KEY, true);
									$mime_type    = (string) get_post_meta($file_post_id, Files::MIME_TYPE_META_KEY, true);
									$file_size    = absint(get_post_meta($file_post_id, Files::FILE_SIZE_META_KEY, true));
									$download_url = Files::get_download_url($file_post_id);
									?>
									<li class="cliapwo-portal__file">
										<a href="<?php echo esc_url($download_url); ?>" class="cliapwo-portal__file-link">
											<span class="cliapwo-portal__file-name"><?php echo esc_html('' !== $file_name ? $file_name : get_the_title($file_post_id)); ?></span>
										</a>
										<?php if ($file_size > 0 || '' !== $mime_type) : ?>
											<span class="cliapwo-portal__file-meta">
												<?php
												$file_meta = array();

												if ($file_size > 0) {
													$file_meta[] = size_format($file_size);
												}

												if ('' !== $mime_type) {
													$file_meta[] = $mime_type;
												}

												echo esc_html(implode(' / ', $file_meta));
												?>
											</span>
										<?php endif; ?>
									</li>
								<?php endwhile; ?>
							</ul>
						<?php else : ?>
							<p class="cliapwo-empty">
								<?php
								echo esc_html(
									$is_staff_preview
										? __('No files yet. Upload one from client-approval-workflow > Files.', 'signoffflow-client-approval-workflow')
										: __('No files yet. Shared deliverables and downloads will appear here.', 'signoffflow-client-approval-workflow')
								);
								?>
							</p>
						<?php endif; ?>
					</section>
				</div>
			</div>

			<footer class="cliapwo-portal__footer">
				<span><?php esc_html_e('Secure client workspace', 'signoffflow-client-approval-workflow'); ?></span>
				<span aria-hidden="true">&middot;</span>
				<span><?php esc_html_e('Private to your account', 'signoffflow-client-approval-workflow'); ?></span>
			</footer>

			<?php do_action('cliapwo_after_render_portal', $client->ID, $current_user_id); ?>
		</div>
<?php
		wp_reset_postdata();

		return (string) ob_get_clean();
	}

	/**
	 * Render the latest client response for a request.
	 *
	 * @param int    $request_id     Request post ID.
	 * @param string $request_status Current request status.
	 * @return void
	 */
	private function render_request_response_summary($request_id, $request_status)
	{
		$response_status = Requests::get_response_status_for_request($request_id);
		$response_client = absint(get_post_meta($request_id, Requests::RESPONSE_CLIENT_META_KEY, true));
		$current_client  = Requests::get_client_id_for_request($request_id);
		$response_client_is_set = metadata_exists('post', $request_id, Requests::RESPONSE_CLIENT_META_KEY);

		if ('' === $response_status || ($response_client_is_set && $current_client !== $response_client)) {
			return;
		}

		$response_note = Requests::get_response_note_for_request($request_id);
		$responder_id  = Requests::get_responder_id_for_request($request_id);
		$responded_at  = Requests::get_response_timestamp_for_request($request_id);
		$responder     = $responder_id > 0 ? get_userdata($responder_id) : false;
		$heading       = Requests::STATUS_OPEN === $request_status
			? __('Previous client response', 'signoffflow-client-approval-workflow')
			: __('Latest client response', 'signoffflow-client-approval-workflow');
		$date_format   = trim((string) get_option('date_format') . ' ' . (string) get_option('time_format'));
		?>
		<div class="cliapwo-portal__request-response">
			<p class="cliapwo-portal__request-response-title"><strong><?php echo esc_html($heading); ?></strong></p>
			<p class="cliapwo-portal__request-response-outcome">
				<span><?php esc_html_e('Outcome:', 'signoffflow-client-approval-workflow'); ?></span>
				<span class="cliapwo-status cliapwo-status--<?php echo esc_attr(sanitize_html_class($response_status)); ?>">
					<?php echo esc_html(Requests::get_status_label($response_status)); ?>
				</span>
			</p>
			<?php if ('' !== $response_note) : ?>
				<div class="cliapwo-portal__request-response-note"><?php echo nl2br(esc_html($response_note)); ?></div>
			<?php endif; ?>
			<?php if ($responder instanceof \WP_User && $responded_at > 0) : ?>
				<p class="cliapwo-portal__request-response-meta">
					<?php
					printf(
						/* translators: 1: client user display name, 2: response date and time */
						esc_html__('Responded by %1$s on %2$s', 'signoffflow-client-approval-workflow'),
						esc_html($responder->display_name),
						esc_html(wp_date($date_format, $responded_at))
					);
					?>
				</p>
			<?php endif; ?>
		</div>
		<?php
	}

	/**
	 * Render a collapsed, client-safe immutable history for a request.
	 *
	 * @param int                  $request_id Request post ID.
	 * @param array<int, \WP_Post> $events     Request lifecycle events.
	 * @return void
	 */
	private function render_request_history($request_id, array $events)
	{
		$request_id = absint($request_id);

		if ($request_id <= 0 || empty($events)) {
			return;
		}

		$event_count = count($events);
		$date_format = trim((string) get_option('date_format') . ' ' . (string) get_option('time_format'));
		?>
		<details class="cliapwo-portal__request-history">
			<summary class="cliapwo-portal__request-history-summary">
				<?php
				printf(
					/* translators: %d: number of request activity entries */
					esc_html(_n('Activity history (%d item)', 'Activity history (%d items)', $event_count, 'signoffflow-client-approval-workflow')),
					esc_html($event_count)
				);
				?>
			</summary>
			<ol class="cliapwo-portal__request-history-list">
				<?php foreach ($events as $event) : ?>
					<?php $event_data = Events::get_request_event_view_data($event, true); ?>
					<?php if (! is_array($event_data)) : ?>
						<?php continue; ?>
					<?php endif; ?>
					<li class="cliapwo-portal__request-history-item">
						<div class="cliapwo-portal__request-history-header">
							<strong><?php echo esc_html((string) $event_data['label']); ?></strong>
							<?php if (Events::TYPE_REQUEST_CREATED !== $event_data['type']) : ?>
								<span class="cliapwo-status cliapwo-status--<?php echo esc_attr(sanitize_html_class((string) $event_data['new_status'])); ?>">
									<?php echo esc_html(Requests::get_status_label((string) $event_data['new_status'])); ?>
								</span>
							<?php endif; ?>
						</div>
						<p class="cliapwo-portal__request-history-meta">
							<?php
							printf(
								/* translators: 1: actor display name, 2: event date and time */
								esc_html__('%1$s on %2$s', 'signoffflow-client-approval-workflow'),
								esc_html((string) $event_data['actor_name']),
								esc_html(wp_date($date_format, (int) $event_data['timestamp']))
							);
							?>
						</p>
						<?php if (Events::TYPE_REQUEST_CREATED !== $event_data['type']) : ?>
							<p class="cliapwo-portal__request-history-transition">
								<?php
								printf(
									/* translators: 1: previous request status, 2: new request status */
									esc_html__('%1$s to %2$s', 'signoffflow-client-approval-workflow'),
									esc_html(Requests::get_status_label((string) $event_data['previous_status'])),
									esc_html(Requests::get_status_label((string) $event_data['new_status']))
								);
								?>
							</p>
						<?php endif; ?>
						<?php if ('' !== (string) $event_data['response_note']) : ?>
							<div class="cliapwo-portal__request-history-note"><?php echo nl2br(esc_html((string) $event_data['response_note'])); ?></div>
						<?php endif; ?>
					</li>
				<?php endforeach; ?>
			</ol>
		</details>
		<?php
	}

	/**
	 * Resolve the portal client for the current request.
	 *
	 * @param int $requested_id     Requested client ID from shortcode attributes.
	 * @param int $current_user_id  Current WordPress user ID.
	 * @return \WP_Post|null
	 */
	private function resolve_client($requested_id, $current_user_id)
	{
		$requested_id    = absint($requested_id);
		$current_user_id = absint($current_user_id);

		if ($requested_id > 0 && current_user_can('cliapwo_manage_portal') && Clients::user_can_view_client($requested_id, $current_user_id)) {
			$client = get_post($requested_id);

			return $client instanceof \WP_Post ? $client : null;
		}

		$client = Clients::get_client_for_user($current_user_id);

		if ($client instanceof \WP_Post) {
			return $client;
		}

		if (current_user_can('cliapwo_manage_portal')) {
			return Clients::get_first_client();
		}

		return null;
	}

	/**
	 * Get the current portal page number.
	 *
	 * @return int
	 */
	private function get_current_page()
	{
		$paged = absint(get_query_var('paged'));

		if ($paged > 0) {
			return $paged;
		}

		$page = absint(get_query_var('page'));

		return $page > 0 ? $page : 1;
	}

	/**
	 * Determine whether the current request is for the configured portal page.
	 *
	 * @return bool
	 */
	private function is_configured_portal_page()
	{
		if (! is_singular('page')) {
			return false;
		}

		$settings       = Settings::get_settings();
		$portal_page_id = isset($settings['portal_page_id']) ? absint($settings['portal_page_id']) : 0;

		return $portal_page_id > 0 && is_page($portal_page_id);
	}

	/**
	 * Get the current portal URL for login redirects.
	 *
	 * @return string
	 */
	private function get_portal_url()
	{
		$portal_url = '';

		if (is_singular()) {
			$portal_url = get_permalink();
		}

		if (! is_string($portal_url) || '' === $portal_url) {
			$portal_url = home_url('/');
		}

		return $portal_url;
	}

	/**
	 * Get the configured portal branding logo URL.
	 *
	 * @param array<string, mixed> $settings Plugin settings.
	 * @return string
	 */
	private function get_branding_logo_url(array $settings)
	{
		$logo_id = isset($settings['branding_logo_id']) ? absint($settings['branding_logo_id']) : 0;

		if ($logo_id > 0) {
			$logo_url = wp_get_attachment_url($logo_id);

			if (is_string($logo_url) && '' !== $logo_url) {
				return $logo_url;
			}
		}

		if (isset($settings['branding_logo_url']) && is_string($settings['branding_logo_url'])) {
			return esc_url_raw($settings['branding_logo_url']);
		}

		return '';
	}

	/**
	 * Wrap a simple portal empty state in the standard portal shell.
	 *
	 * @param string               $content  Already-escaped inner markup.
	 * @param array<string, mixed> $settings Plugin settings.
	 * @return string
	 */
	private function wrap_empty_state($content, array $settings)
	{
		$primary_color = isset($settings['primary_color']) ? sanitize_hex_color((string) $settings['primary_color']) : false;
		$root_style    = $this->get_root_style_attribute($primary_color, $settings, 0, get_current_user_id(), false);
		$classes       = $this->get_wrapper_classes(0, get_current_user_id(), false);

		return sprintf(
			'<div class="%1$s" style="%2$s">%3$s</div>',
			esc_attr($classes),
			esc_attr($root_style),
			$content
		);
	}

	/**
	 * Build stable CSS variables for portal theming.
	 *
	 * CSS custom properties are exposed on the portal root so future versions
	 * can support customization without changing the markup structure.
	 *
	 * @param string|false        $primary_color    Sanitized primary color.
	 * @param array<string,mixed> $settings         Plugin settings.
	 * @param int                 $client_id        Client post ID, if available.
	 * @param int                 $current_user_id  Current user ID.
	 * @param bool                $is_staff_preview Whether this is a staff preview.
	 * @return string
	 */
	private function get_root_style_attribute($primary_color, array $settings, $client_id, $current_user_id, $is_staff_preview)
	{
		$primary_color = is_string($primary_color) && '' !== $primary_color ? $primary_color : '#1d4ed8';

		$variables = array(
			'--cliapwo-primary'        => $primary_color,
			'--cliapwo-primary-soft'   => $this->hex_to_rgba($primary_color, 0.12),
			'--cliapwo-primary-border' => $this->hex_to_rgba($primary_color, 0.2),
			'--cliapwo-text'           => '#101828',
			'--cliapwo-text-soft'      => '#475467',
			'--cliapwo-text-muted'     => '#667085',
			'--cliapwo-bg'             => '#f6f7fa',
			'--cliapwo-card-bg'        => '#ffffff',
			'--cliapwo-border'         => '#e4e7ec',
			'--cliapwo-max-width'      => '1240px',
			'--cliapwo-radius-xl'      => '24px',
			'--cliapwo-radius-lg'      => '18px',
			'--cliapwo-radius-md'      => '14px',
			'--cliapwo-shadow-lg'      => '0 24px 70px rgba(16,24,40,0.08)',
			'--cliapwo-shadow-md'      => '0 12px 30px rgba(16,24,40,0.07)',
		);

		/**
		 * Filter the CSS custom properties exposed on the portal root wrapper.
		 *
		 * @param array<string, string> $variables         Portal CSS variable map.
		 * @param array<string, mixed>  $settings          Plugin settings.
		 * @param int                   $client_id         Client post ID, if available.
		 * @param int                   $current_user_id   Current user ID.
		 * @param bool                  $is_staff_preview  Whether the portal is being previewed by staff.
		 */
		$variables = apply_filters('cliapwo_portal_style_vars', $variables, $settings, absint($client_id), absint($current_user_id), (bool) $is_staff_preview);

		$declarations = array();

		foreach ($variables as $key => $value) {
			$declarations[] = $key . ':' . $value;
		}

		return implode(';', $declarations) . ';';
	}

	/**
	 * Convert a hex color into an rgba() string.
	 *
	 * @param string $hex_color Hex color string.
	 * @param float  $alpha     Alpha value between 0 and 1.
	 * @return string
	 */
	private function hex_to_rgba($hex_color, $alpha)
	{
		$hex_color = ltrim((string) $hex_color, '#');
		$alpha     = max(0, min(1, (float) $alpha));

		if (3 === strlen($hex_color)) {
			$hex_color = $hex_color[0] . $hex_color[0] . $hex_color[1] . $hex_color[1] . $hex_color[2] . $hex_color[2];
		}

		if (6 !== strlen($hex_color)) {
			return 'rgba(29,78,216,' . $alpha . ')';
		}

		$red   = hexdec(substr($hex_color, 0, 2));
		$green = hexdec(substr($hex_color, 2, 2));
		$blue  = hexdec(substr($hex_color, 4, 2));

		return sprintf('rgba(%d,%d,%d,%s)', $red, $green, $blue, (string) $alpha);
	}

	/**
	 * Return filterable wrapper classes for the portal root.
	 *
	 * @param int  $client_id        Client post ID, if available.
	 * @param int  $current_user_id  Current user ID.
	 * @param bool $is_staff_preview Whether the portal is being previewed by staff.
	 * @return string
	 */
	private function get_wrapper_classes($client_id, $current_user_id, $is_staff_preview)
	{
		$classes = array('cliapwo-portal');

		if ($is_staff_preview) {
			$classes[] = 'cliapwo-portal--staff-preview';
		}

		/**
		 * Filter the portal root wrapper classes.
		 *
		 * @param array<int, string> $classes          Portal wrapper classes.
		 * @param int                $client_id        Client post ID, if available.
		 * @param int                $current_user_id  Current user ID.
		 * @param bool               $is_staff_preview Whether the portal is being previewed by staff.
		 */
		$classes = apply_filters('cliapwo_portal_wrapper_classes', $classes, absint($client_id), absint($current_user_id), (bool) $is_staff_preview);

		$classes = array_filter(array_map('sanitize_html_class', (array) $classes));

		return implode(' ', array_unique($classes));
	}

	/**
	 * Return filterable classes for major portal sections.
	 *
	 * @param string             $section         Section identifier.
	 * @param array<int, string> $classes      Default section classes.
	 * @param int                $client_id       Client post ID.
	 * @param int                $current_user_id Current user ID.
	 * @return string
	 */
	private function get_section_classes($section, array $classes, $client_id, $current_user_id)
	{
		$section = sanitize_key((string) $section);

		/**
		 * Filter portal section classes.
		 *
		 * @param array<int, string> $classes         Section classes.
		 * @param string             $section         Section identifier.
		 * @param int                $client_id       Client post ID.
		 * @param int                $current_user_id Current user ID.
		 */
		$classes = apply_filters('cliapwo_portal_section_classes', $classes, $section, absint($client_id), absint($current_user_id));

		$classes = array_filter(array_map('sanitize_html_class', (array) $classes));

		return implode(' ', array_unique($classes));
	}

	/**
	 * Return a reliable count for a query used in portal summary badges.
	 *
	 * Some private portal queries intentionally disable found rows for
	 * performance, so found_posts may be zero even when posts are loaded.
	 *
	 * @param \WP_Query $query Query instance.
	 * @return int
	 */
	private function get_query_count(\WP_Query $query)
	{
		$found_posts = absint($query->found_posts);

		if ($found_posts > 0) {
			return $found_posts;
		}

		return is_array($query->posts) ? count($query->posts) : 0;
	}
}
