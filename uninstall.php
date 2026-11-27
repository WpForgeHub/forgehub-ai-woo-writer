<?php
/**
 * Uninstall cleanup for ForgeHub AI Woo Writer.
 *
 * @package ForgeHub_AI_Woo_Writer
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

delete_option( 'wpfh_aww_settings' );
delete_option( 'wpfh_aww_templates' );
delete_option( 'wpfh_aww_review_nudge' );
