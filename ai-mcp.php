<?php
/**
 * Plugin Name:       AI MCP
 * Plugin URI:        https://webmosh.com
 * Description:       AI for WordPress: chatbots, AI playground, content and image generation, AI forms, knowledge base (embeddings), editor and WooCommerce assistant, discussions log, usage limits and a built-in MCP server. Works with OpenAI, Anthropic (Claude), Google Gemini and OpenRouter.
 * Version:           1.4.0
 * Requires at least: 6.0
 * Requires PHP:      7.4
 * Author:            WEBMOSH
 * Author URI:        https://webmosh.com
 * License:           GPLv2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       ai-mcp
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'MOSHAI_VERSION', '1.4.0' );
define( 'MOSHAI_FILE', __FILE__ );
define( 'MOSHAI_DIR', plugin_dir_path( __FILE__ ) );
define( 'MOSHAI_URL', plugin_dir_url( __FILE__ ) );

foreach ( array( 'settings', 'db', 'providers', 'core', 'chatbots', 'forms', 'rest', 'mcp', 'activity', 'tools', 'agent', 'editor', 'admin' ) as $moshai_part ) {
	require_once MOSHAI_DIR . 'includes/class-' . $moshai_part . '.php';
}
unset( $moshai_part );

register_activation_hook( __FILE__, array( 'MoshAI_DB', 'install' ) );
register_deactivation_hook( __FILE__, 'moshai_deactivate' );

function moshai_deactivate() {
	wp_clear_scheduled_hook( 'moshai_daily' );
	wp_unschedule_hook( 'moshai_kb_sync_post' );
}

add_action( 'plugins_loaded', 'moshai_boot' );

function moshai_boot() {
	MoshAI_DB::maybe_upgrade();
	MoshAI_Settings::migrate();
	MoshAI_Core::init();
	MoshAI_Chatbots::init();
	MoshAI_Forms::init();
	MoshAI_REST::init();
	MoshAI_MCP::init();
	MoshAI_Tools::init();
	MoshAI_Agent::init();
	MoshAI_Editor::init();
	add_action( 'admin_bar_menu', array( 'MoshAI_Admin', 'admin_bar' ), 90 );
	if ( is_admin() ) {
		MoshAI_Admin::init();
	}
}

add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), 'moshai_action_links' );

function moshai_action_links( $links ) {
	array_unshift( $links, '<a href="' . esc_url( admin_url( 'admin.php?page=moshai-settings' ) ) . '">' . esc_html__( 'Settings', 'ai-mcp' ) . '</a>' );
	return $links;
}
