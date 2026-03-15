<?php

/**
 * Portal shortcode rendering.
 *
 * @package ClientApprovalWorkflow
 */

namespace ClientApprovalWorkflow;

defined('ABSPATH') || exit;

/**
 * Renders the client portal shortcode.
 */
class Portal
{
	/**
	 * Register portal hooks.
	 *
	 * @return void
	 */
	public function register()
	{
		add_shortcode('cliapwo_portal', array($this, 'render_shortcode'));
	}

	/**
	 * Render the client portal shortcode.
	 *
	 * @param array<string, mixed> $atts Shortcode attributes.
	 * @return string
	 */
	public function render_shortcode($atts)
	{
		if (is_admin() && ! wp_doing_ajax()) {
			return '';
		}

		if (! is_user_logged_in()) {
			$login_url = wp_login_url($this->get_portal_url());

			if (! headers_sent()) {
				wp_safe_redirect($login_url);
				exit;
			}

			return sprintf(
				'<p>%s</p>',
				sprintf(
					/* translators: %s: login URL */
					esc_html__('Please log in to view your client portal: %s', 'client-approval-workflow'),
					esc_url($login_url)
				)
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
		$client          = $this->resolve_client($requested_id, $current_user_id);

		if (! $client instanceof \WP_Post) {
			return '<div class="cliapwo-portal"><p>' . esc_html__('No portal assigned.', 'client-approval-workflow') . '</p></div>';
		}

		if (! Clients::user_can_view_client($client->ID, $current_user_id)) {
			return '<div class="cliapwo-portal"><p>' . esc_html__('You do not have access to this portal.', 'client-approval-workflow') . '</p></div>';
		}

		$settings         = Settings::get_settings();
		$paged            = $this->get_current_page();
		$updates_query    = Updates::get_updates_query_for_client(
			$client->ID,
			array(
				'paged' => $paged,
			)
		);
		$requests_query = Requests::get_requests_query_for_client($client->ID);
		$files_query      = Files::get_files_query_for_client($client->ID);
		$open_requests    = Requests::get_open_request_count_for_client($client->ID);
		$logo_url         = $this->get_branding_logo_url($settings);
		$primary_color    = isset($settings['primary_color']) ? sanitize_hex_color((string) $settings['primary_color']) : false;
		$is_staff_preview = current_user_can('cliapwo_manage_portal') && ! in_array($current_user_id, Clients::get_assigned_user_ids($client->ID), true);

		ob_start();
		?>
		<div
			class="cliapwo-portal"
			<?php if (is_string($primary_color) && '' !== $primary_color) : ?>
				style="<?php echo esc_attr('border-top: 4px solid ' . $primary_color . '; padding-top: 1rem;'); ?>"
			<?php endif; ?>>
			<?php do_action('cliapwo_before_render_portal', $client->ID, $current_user_id); ?>

			<header class="cliapwo-portal__header">
				<?php if ('' !== $logo_url) : ?>
					<p class="cliapwo-portal__brand">
						<img
							src="<?php echo esc_url($logo_url); ?>"
							alt="<?php echo esc_attr__('client-approval-workflow logo', 'client-approval-workflow'); ?>"
							style="max-width:180px; height:auto;" />
					</p>
				<?php endif; ?>
				<h2><?php echo esc_html($client->post_title); ?></h2>
				<p><?php esc_html_e('Welcome to your SignoffFlow portal.', 'client-approval-workflow'); ?></p>
				<?php if ($is_staff_preview) : ?>
					<p class="cliapwo-portal__preview-note">
						<?php esc_html_e('You are previewing this portal as staff.', 'client-approval-workflow'); ?>
					</p>
				<?php endif; ?>
			</header>

			<section class="cliapwo-portal__summary">
				<h3><?php esc_html_e('Waiting on you', 'client-approval-workflow'); ?></h3>
				<?php if ($open_requests > 0) : ?>
					<p>
						<?php
						printf(
							/* translators: %d: number of open requests */
							esc_html(_n('%d request needs your attention.', '%d requests need your attention.', $open_requests, 'client-approval-workflow')),
							esc_html($open_requests)
						);
						?>
					</p>
				<?php else : ?>
					<p><?php esc_html_e('Nothing is waiting on you right now.', 'client-approval-workflow'); ?></p>
				<?php endif; ?>
			</section>

			<section class="cliapwo-portal__requests">
				<h3><?php esc_html_e('Requests', 'client-approval-workflow'); ?></h3>

				<?php if ($requests_query->have_posts()) : ?>
					<ul class="cliapwo-portal__request-list">
						<?php while ($requests_query->have_posts()) : ?>
							<?php
							$requests_query->the_post();
							$request_id      = get_the_ID();
							$request_status  = Requests::get_status_for_request($request_id);
							$can_manage      = current_user_can('cliapwo_manage_portal');
							$can_complete    = ! $can_manage && Requests::STATUS_OPEN === $request_status;
							$can_reopen      = $can_manage && Requests::STATUS_COMPLETE === $request_status;
							$can_force_close = $can_manage && Requests::STATUS_OPEN === $request_status;
							?>
							<li class="cliapwo-portal__request">
								<div class="cliapwo-portal__request-header">
									<strong><?php echo esc_html(get_the_title($request_id)); ?></strong>
									<span class="cliapwo-portal__request-status">
										<?php echo esc_html(Requests::get_status_label($request_status)); ?>
									</span>
								</div>

								<?php if ('' !== (string) get_post_field('post_content', $request_id)) : ?>
									<div class="cliapwo-portal__request-content">
										<?php echo wp_kses_post(wpautop((string) get_post_field('post_content', $request_id))); ?>
									</div>
								<?php endif; ?>

								<?php if ($can_complete || $can_reopen || $can_force_close) : ?>
									<form
										method="post"
										action="<?php echo esc_url(admin_url('admin-post.php')); ?>"
										class="cliapwo-portal__request-form">
										<input type="hidden" name="action" value="<?php echo esc_attr(Requests::STATUS_UPDATE_ACTION); ?>" />
										<input type="hidden" name="cliapwo_request_id" value="<?php echo esc_attr((string) $request_id); ?>" />
										<?php wp_nonce_field(Requests::STATUS_UPDATE_ACTION, Requests::STATUS_UPDATE_NONCE_NAME); ?>

										<?php if ($can_complete) : ?>
											<input type="hidden" name="cliapwo_request_status" value="<?php echo esc_attr(Requests::STATUS_COMPLETE); ?>" />
											<button type="submit"><?php esc_html_e('Mark complete', 'client-approval-workflow'); ?></button>
										<?php elseif ($can_reopen) : ?>
											<input type="hidden" name="cliapwo_request_status" value="<?php echo esc_attr(Requests::STATUS_OPEN); ?>" />
											<button type="submit"><?php esc_html_e('Reopen', 'client-approval-workflow'); ?></button>
										<?php elseif ($can_force_close) : ?>
											<input type="hidden" name="cliapwo_request_status" value="<?php echo esc_attr(Requests::STATUS_COMPLETE); ?>" />
											<button type="submit"><?php esc_html_e('Complete for client', 'client-approval-workflow'); ?></button>
										<?php endif; ?>
									</form>
								<?php endif; ?>
							</li>
						<?php endwhile; ?>
					</ul>
				<?php else : ?>
					<p>
						<?php
						echo esc_html(
							$is_staff_preview
								? __('No requests yet. Add one from client-approval-workflow > Requests.', 'client-approval-workflow')
								: __('No requests yet. Your team will add anything they still need from you here.', 'client-approval-workflow')
						);
						?>
					</p>
				<?php endif; ?>
			</section>

			<section class="cliapwo-portal__updates">
				<h3><?php esc_html_e('Updates', 'client-approval-workflow'); ?></h3>

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
								<h4><?php echo esc_html($update_title); ?></h4>
								<p class="cliapwo-portal__meta">
									<?php
									printf(
										/* translators: 1: date, 2: author name */
										esc_html__('Posted on %1$s by %2$s', 'client-approval-workflow'),
										esc_html(get_the_date('', $update_id)),
										esc_html($author_name ? $author_name : __('SignoffFlow', 'client-approval-workflow'))
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
						echo wp_kses_post($pagination);
					}
					?>
				<?php else : ?>
					<p>
						<?php
						echo esc_html(
							$is_staff_preview
								? __('No updates yet. Publish a client update from client-approval-workflow > Updates.', 'client-approval-workflow')
								: __('No updates yet. New project updates will appear here.', 'client-approval-workflow')
						);
						?>
					</p>
				<?php endif; ?>
			</section>

			<section class="cliapwo-portal__files">
				<h3><?php esc_html_e('Files', 'client-approval-workflow'); ?></h3>

				<?php if ($files_query->have_posts()) : ?>
					<ul class="cliapwo-portal__file-list">
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
								<a href="<?php echo esc_url($download_url); ?>">
									<?php echo esc_html('' !== $file_name ? $file_name : get_the_title($file_post_id)); ?>
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

										echo esc_html(implode(' | ', $file_meta));
										?>
									</span>
								<?php endif; ?>
							</li>
						<?php endwhile; ?>
					</ul>
				<?php else : ?>
					<p>
						<?php
						echo esc_html(
							$is_staff_preview
								? __('No files yet. Upload one from client-approval-workflow > Files.', 'client-approval-workflow')
								: __('No files yet. Shared deliverables and downloads will appear here.', 'client-approval-workflow')
						);
						?>
					</p>
				<?php endif; ?>
			</section>

			<?php do_action('cliapwo_after_render_portal', $client->ID, $current_user_id); ?>
		</div>
		<?php
		wp_reset_postdata();

		return (string) ob_get_clean();
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
}
