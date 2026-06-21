<?php

/**
 * Dedicated frontend template for the configured client portal page.
 *
 * @package VzisisClientApprovalWorkflow
 */

defined('ABSPATH') || exit;

?><!doctype html>
<html <?php language_attributes(); ?>>
<head>
	<meta charset="<?php bloginfo('charset'); ?>" />
	<meta name="viewport" content="width=device-width, initial-scale=1" />
	<?php wp_head(); ?>
</head>
<body <?php body_class('cliapwo-portal-page'); ?>>
<?php wp_body_open(); ?>
<a class="screen-reader-text cliapwo-portal-page__skip-link" href="#cliapwo-portal-main">
	<?php esc_html_e('Skip to client workspace', 'signoffflow-client-approval-workflow'); ?>
</a>
<main id="cliapwo-portal-main" class="cliapwo-portal-page__main">
	<?php
	while (have_posts()) {
		the_post();
		the_content();
	}
	?>
</main>
<?php wp_footer(); ?>
</body>
</html>
