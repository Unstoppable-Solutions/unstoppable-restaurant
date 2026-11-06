<?php
/**
 * Uninstall handler for Unstoppable Restaurant for WooCommerce.
 *
 * Runs only when the plugin is deleted from Plugins -> Installed Plugins.
 * Deactivation does NOT trigger this file.
 *
 * Removes plugin settings and transients. Menus and modifier flows (custom
 * post types) are intentionally kept: they are the restaurant's own content,
 * and deleting them on uninstall would destroy work a site owner may want
 * back if they reinstall.
 *
 * @package Unstoppable_Restaurant
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

$urst_options = array(
	'urst_settings',
	'urst_product_labels',
	'urst_menu_migration_complete',
	'urst_health_warnings_dismissed',
	'urst_health_warnings_dismissed_time',
);

foreach ( $urst_options as $urst_option ) {
	delete_option( $urst_option );
}

// Short-lived per-post modifier validation warnings (urst_modifier_warnings_{post_id}).
global $wpdb;
// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- One-time cleanup on uninstall; no API exists to delete transients by prefix.
$wpdb->query(
	$wpdb->prepare(
		"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s",
		$wpdb->esc_like( '_transient_urst_modifier_warnings_' ) . '%',
		$wpdb->esc_like( '_transient_timeout_urst_modifier_warnings_' ) . '%'
	)
);
