<?php

/**
 * Internal calendar-date and attention helpers.
 *
 * @package VzisisClientApprovalWorkflow
 */

namespace Vzisis\ClientApprovalWorkflow;

defined('ABSPATH') || exit;

/**
 * Shared due-date rules. Dates represent site calendar days, never UTC instants.
 *
 * @internal
 */
class Request_Dates
{
	public const META_KEY = 'cliapwo_request_due_date';
	public const FILTER_KEY = 'cliapwo_request_due_filter';
	public const ORDERBY = 'cliapwo_request_due_date';
	public const NONCE_ACTION = 'cliapwo_filter_requests';
	public const NONCE_NAME = 'cliapwo_request_filter_nonce';

	/** Register scoped admin query handling. */
	public function register()
	{
		add_action('pre_get_posts', array($this, 'configure_admin_query'));
		add_filter('posts_clauses', array($this, 'filter_query_clauses'), 10, 2);
	}

	/**
	 * Validate a storage date without normalizing impossible dates.
	 *
	 * @param mixed $date Candidate date.
	 * @return bool
	 */
	public static function is_valid($date)
	{
		if (! is_string($date) || ! preg_match('/^[1-9][0-9]{3}-[0-9]{2}-[0-9]{2}$/D', $date)) {
			return false;
		}
		return checkdate((int) substr($date, 5, 2), (int) substr($date, 8, 2), (int) substr($date, 0, 4));
	}

	/**
	 * Read a validated date, treating malformed legacy metadata as absent.
	 *
	 * @param int $request_id Request ID.
	 * @return string
	 */
	public static function get_date($request_id)
	{
		$date = get_post_meta($request_id, self::META_KEY, true);
		return self::is_valid($date) ? $date : '';
	}

	/**
	 * Format a calendar day in the site's locale and timezone.
	 *
	 * @param string $date Valid storage date or empty string.
	 * @return string
	 */
	public static function format($date)
	{
		if (! self::is_valid($date)) {
			return __('No due date', 'signoffflow-client-approval-workflow');
		}
		$day = new \DateTimeImmutable($date . ' 12:00:00', wp_timezone());
		return wp_date((string) get_option('date_format'), $day->getTimestamp(), wp_timezone());
	}

	/**
	 * Get one shared site-date window per operation.
	 *
	 * @return array<string, string>
	 */
	public static function window()
	{
		$today = current_datetime()->setTime(0, 0);
		return array('today' => $today->format('Y-m-d'), 'soon' => $today->modify('+7 days')->format('Y-m-d'));
	}

	/**
	 * Compute urgency only for open requests.
	 *
	 * @param int                  $request_id Request ID.
	 * @param array<string,string> $window Optional fixed window for batched rendering.
	 * @return string
	 */
	public static function get_state($request_id, array $window = array())
	{
		$date = self::get_date($request_id);
		if ('' === $date || Requests::STATUS_OPEN !== Requests::get_status_for_request($request_id)) {
			return '';
		}
		$window = empty($window) ? self::window() : $window;
		if ($date < $window['today']) {
			return 'overdue';
		}
		return $date <= $window['soon'] ? 'soon' : '';
	}

	/**
	 * Render a date and optional text urgency label.
	 *
	 * @param int $request_id Request ID.
	 * @return void
	 */
	public static function render($request_id)
	{
		$date = self::get_date($request_id);
		$window = self::window();
		$state = self::get_state($request_id, $window);
		echo '<p class="cliapwo-request-due cliapwo-request-due--' . esc_attr($state) . '">';
		if ('' !== $date) {
			echo '<span>' . esc_html__('Due:', 'signoffflow-client-approval-workflow') . ' <time datetime="' . esc_attr($date) . '">' . esc_html(self::format($date)) . '</time></span>';
		} else {
			echo '<span>' . esc_html(self::format('')) . '</span>';
		}
		if ('overdue' === $state) {
			$days = (new \DateTimeImmutable($date, wp_timezone()))->diff(new \DateTimeImmutable($window['today'], wp_timezone()))->days;
			/* translators: %d: calendar days since the request's due date. */
			$label = sprintf(_n('Overdue by %d day', 'Overdue by %d days', $days, 'signoffflow-client-approval-workflow'), $days);
		} elseif ('soon' === $state) {
			$label = $date === $window['today'] ? __('Due today', 'signoffflow-client-approval-workflow') : __('Due soon', 'signoffflow-client-approval-workflow');
		} else {
			$label = '';
		}
		if ('' !== $label) {
			echo ' <strong class="cliapwo-request-due__state">' . esc_html($label) . '</strong>';
		}
		echo '</p>';
	}

	/**
	 * Read an allowlisted filter only after nonce and capability checks.
	 *
	 * @return string
	 */
	public static function selected_filter()
	{
		if (! self::authorized_query()) {
			return '';
		}
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- authorized_query verifies the shared filter nonce and capability above.
		$value = isset($_GET[self::FILTER_KEY]) && is_string($_GET[self::FILTER_KEY]) ? sanitize_key(wp_unslash($_GET[self::FILTER_KEY])) : '';
		return in_array($value, array('soon', 'overdue', 'none'), true) ? $value : '';
	}

	/**
	 * Verify privileged query controls.
	 *
	 * @return bool
	 */
	private static function authorized_query()
	{
		$nonce = isset($_GET[self::NONCE_NAME]) && is_string($_GET[self::NONCE_NAME]) ? sanitize_text_field(wp_unslash($_GET[self::NONCE_NAME])) : '';
		return wp_verify_nonce($nonce, self::NONCE_ACTION) && current_user_can('cliapwo_manage_portal');
	}

	/**
	 * Build an escaped sortable heading with a signed URL.
	 *
	 * @return string
	 */
	public static function column_heading()
	{
		global $wp_query;
		$args = array('post_type' => Requests::POST_TYPE, 'orderby' => self::ORDERBY, 'order' => 'ASC');
		if ($wp_query instanceof \WP_Query) {
			foreach (array('s', 'm', 'post_status') as $key) {
				$value = $wp_query->get($key);
				if (is_string($value) && '' !== $value) {
					$args[$key] = sanitize_text_field($value);
				}
			}
			if ('ASC' === $wp_query->get('cliapwo_due_order')) {
				$args['order'] = 'DESC';
			}
		}
		if (self::authorized_query()) {
			foreach (array(self::FILTER_KEY, 'cliapwo_request_status_filter') as $key) {
				// phpcs:disable WordPress.Security.NonceVerification.Recommended -- shared nonce and capability verified by authorized_query.
				if (isset($_GET[$key]) && is_string($_GET[$key])) {
					$args[$key] = sanitize_key(wp_unslash($_GET[$key]));
				}
				// phpcs:enable WordPress.Security.NonceVerification.Recommended
			}
		}
		$args[self::NONCE_NAME] = wp_create_nonce(self::NONCE_ACTION);
		/* translators: %s: ascending or descending due-date ordering. */
		$title = sprintf(__('Sort by due date (%s)', 'signoffflow-client-approval-workflow'), 'ASC' === $args['order'] ? __('earliest first', 'signoffflow-client-approval-workflow') : __('latest first', 'signoffflow-client-approval-workflow'));
		return '<a href="' . esc_url(add_query_arg($args, admin_url('edit.php'))) . '" aria-label="' . esc_attr($title) . '"><span>' . esc_html__('Due date', 'signoffflow-client-approval-workflow') . '</span><span aria-hidden="true"> ↕</span></a>';
	}

	/**
	 * Configure only the privileged request list's main query.
	 *
	 * @param \WP_Query $query Query instance.
	 * @return void
	 */
	public function configure_admin_query($query)
	{
		if (! is_admin() || ! $query->is_main_query() || Requests::POST_TYPE !== $query->get('post_type')) {
			return;
		}
		if (! self::authorized_query()) {
			if (self::ORDERBY === $query->get('orderby')) {
				$query->set('orderby', 'date');
			}
			return;
		}
		$query->set('cliapwo_due_filter', self::selected_filter());
		if (self::ORDERBY === $query->get('orderby')) {
			$query->set('cliapwo_due_order', 'DESC' === strtoupper((string) $query->get('order')) ? 'DESC' : 'ASC');
		}
	}

	/**
	 * Apply ordering before LIMIT without dropping undated requests or duplicates.
	 *
	 * @param array<string,string> $clauses Query SQL clauses.
	 * @param \WP_Query           $query Query instance.
	 * @return array<string,string>
	 */
	public function filter_query_clauses($clauses, $query)
	{
		$attention = (bool) $query->get('cliapwo_attention_order');
		$order = $query->get('cliapwo_due_order');
		$filter = $query->get('cliapwo_due_filter');
		if (Requests::POST_TYPE !== $query->get('post_type') || (! $attention && ! in_array($order, array('ASC', 'DESC'), true) && ! in_array($filter, array('soon', 'overdue', 'none'), true))) {
			return $clauses;
		}
		global $wpdb;
		// Calendar validation matches PHP checkdate, including leap days. Malformed metadata is absent.
		$valid = "CHAR_LENGTH(meta_value) = 10 AND meta_value REGEXP '^[1-9][0-9]{3}-(0[1-9]|1[0-2])-(0[1-9]|[12][0-9]|3[01])$' AND CAST(SUBSTRING(meta_value,9,2) AS UNSIGNED) <= DAY(LAST_DAY(CONCAT(SUBSTRING(meta_value,1,7),'-01')))";
		$clauses['join'] .= $wpdb->prepare(" LEFT JOIN (SELECT post_id, MAX(CASE WHEN $valid THEN meta_value ELSE NULL END) AS due_date FROM {$wpdb->postmeta} WHERE meta_key = %s GROUP BY post_id) cliapwo_due ON {$wpdb->posts}.ID = cliapwo_due.post_id", self::META_KEY); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- trusted SQL expression and core table identifiers only.
		$open = "BINARY COALESCE(cliapwo_state.request_status, 'open') NOT IN ('approved', 'complete', 'changes_requested', 'rejected', 'blocked')";
		$clauses['join'] .= $wpdb->prepare(" LEFT JOIN (SELECT post_id, MAX(meta_value) AS request_status FROM {$wpdb->postmeta} WHERE meta_key = %s GROUP BY post_id) cliapwo_state ON {$wpdb->posts}.ID = cliapwo_state.post_id", Requests::STATUS_META_KEY);
		$window = self::window();
		$overdue = $wpdb->prepare("($open AND cliapwo_due.due_date < %s)", $window['today']); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- trusted SQL expression only.
		$soon = $wpdb->prepare("($open AND cliapwo_due.due_date BETWEEN %s AND %s)", $window['today'], $window['soon']); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- trusted SQL expression only.
		if ('none' === $filter) {
			$clauses['where'] .= ' AND cliapwo_due.due_date IS NULL';
		} elseif ('overdue' === $filter || 'soon' === $filter) {
			$clauses['where'] .= ' AND ' . ('overdue' === $filter ? $overdue : $soon);
		}
		if ($attention) {
			$clauses['orderby'] = "CASE WHEN $overdue THEN 0 WHEN $soon THEN 1 WHEN $open THEN 2 ELSE 3 END ASC, CASE WHEN $open THEN (cliapwo_due.due_date IS NULL) ELSE 0 END ASC, CASE WHEN $open THEN cliapwo_due.due_date ELSE NULL END ASC, {$wpdb->posts}.post_date DESC, {$wpdb->posts}.ID DESC";
		} elseif (in_array($order, array('ASC', 'DESC'), true)) {
			$clauses['orderby'] = "(cliapwo_due.due_date IS NULL) ASC, cliapwo_due.due_date $order, {$wpdb->posts}.post_date DESC, {$wpdb->posts}.ID DESC";
		}
		return $clauses;
	}

	/**
	 * Count all published open requests, independent of the display limit.
	 *
	 * @param int $client_id Client ID.
	 * @return array<string,int>
	 */
	public static function attention_counts($client_id)
	{
		$query = Requests::get_requests_query_for_client($client_id, array('posts_per_page' => -1, 'fields' => 'ids'));
		update_meta_cache('post', $query->posts);
		$counts = array('open' => 0, 'overdue' => 0, 'soon' => 0);
		$window = self::window();
		foreach ($query->posts as $id) {
			if (Requests::STATUS_OPEN !== Requests::get_status_for_request($id)) {
				continue;
			}
			++$counts['open'];
			$state = self::get_state($id, $window);
			if (isset($counts[$state])) {
				++$counts[$state];
			}
		}
		return $counts;
	}
}
