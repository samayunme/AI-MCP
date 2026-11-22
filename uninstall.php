<?php
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}
$moshai_settings = get_option( 'moshai_settings', array() );
if ( empty( $moshai_settings['clean_uninstall'] ) ) {
	return;
}
global $wpdb;
foreach ( array( 'logs', 'discussions', 'vectors', 'activity' ) as $moshai_table ) {
	$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}moshai_{$moshai_table}" ); // phpcs:ignore
}
foreach ( array( 'moshai_settings', 'moshai_chatbots', 'moshai_forms', 'moshai_db_version', 'moshai_models_cache', 'moshai_kb_synced', 'moshai_mcp_clients' ) as $moshai_option ) {
	delete_option( $moshai_option );
}
delete_post_meta_by_key( '_moshai_meta_description' );
wp_clear_scheduled_hook( 'moshai_daily' );
