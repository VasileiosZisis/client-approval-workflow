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

		$paged         = $this->get_current_page();
		$updates_query = Updates::get_updates_query_for_client(
			$client->ID,
			array(
				'paged' => $paged,
			)
		);

		ob_start();
		?>
		<div class="cliapwo-portal">
			<header class="cliapwo-portal__header">
				<h2><?php echo esc_html($client->post_title); ?></h2>
				<p><?php esc_html_e('Welcome to your SignoffFlow portal.', 'client-approval-workflow'); ?></p>
			</header>

			<section class="cliapwo-portal__summary">
				<h3><?php esc_html_e('Waiting on you', 'client-approval-workflow'); ?></h3>
				<p><?php esc_html_e('Nothing is waiting on you yet. Requests and approvals will appear here in later milestones.', 'client-approval-workflow'); ?></p>
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
					<p><?php esc_html_e('No updates yet.', 'client-approval-workflow'); ?></p>
				<?php endif; ?>
			</section>
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
}
