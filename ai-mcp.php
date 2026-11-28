<?php
/**
 * Plugin Name:       AI MCP
 * Plugin URI:        https://github.com/samayunme/AI-MCP
 * Description:       AI for WordPress: chatbots, AI playground, content and image generation, AI forms, knowledge base (embeddings), editor and WooCommerce assistant, a full SEO module, discussions log, usage limits and a built-in MCP server. Works with OpenAI, Anthropic (Claude), Google Gemini, OpenRouter and any OpenAI-compatible API.
 * Version:           1.1.0
 * Requires at least: 6.2
 * Requires PHP:      7.4
 * Author:            WEBMOSH
 * Author URI:        https://webmosh.com
 * License:           GPLv2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       ai-mcp
 * Update URI:        https://github.com/samayunme/AI-MCP
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'MOSHAI_VERSION', '1.1.0' );
define( 'MOSHAI_FILE', __FILE__ );
define( 'MOSHAI_DIR', plugin_dir_path( __FILE__ ) );
define( 'MOSHAI_URL', plugin_dir_url( __FILE__ ) );

/*
 * Hosts without the mbstring extension: WordPress only polyfills mb_substr() and mb_strlen().
 * AI MCP also uses these three, so give them a safe fallback instead of a fatal error.
 */
if ( ! function_exists( 'mb_strtolower' ) ) {
	function mb_strtolower( $string, $encoding = null ) { // phpcs:ignore
		return strtolower( (string) $string );
	}
}
if ( ! function_exists( 'mb_strpos' ) ) {
	function mb_strpos( $haystack, $needle, $offset = 0, $encoding = null ) { // phpcs:ignore
		$haystack = (string) $haystack;
		$start    = $offset > 0 ? strlen( mb_substr( $haystack, 0, (int) $offset ) ) : 0;
		$pos      = '' === (string) $needle ? false : strpos( $haystack, (string) $needle, $start );
		return false === $pos ? false : mb_strlen( substr( $haystack, 0, $pos ) );
	}
}
if ( ! function_exists( 'mb_strrpos' ) ) {
	function mb_strrpos( $haystack, $needle, $offset = 0, $encoding = null ) { // phpcs:ignore
		$haystack = (string) $haystack;
		$pos      = '' === (string) $needle ? false : strrpos( $haystack, (string) $needle );
		return false === $pos ? false : mb_strlen( substr( $haystack, 0, $pos ) );
	}
}

foreach ( array( 'kses', 'settings', 'db', 'providers', 'core', 'chatbots', 'forms', 'rest', 'mcp', 'activity', 'tools', 'switches', 'updater', 'agent', 'editor', 'modules', 'admin' ) as $moshai_part ) {
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
	MoshAI_Modules::init(); // Optional feature packs from /modules (SEO...). Off modules load nothing.
	MoshAI_Core::init();
	MoshAI_Chatbots::init();
	MoshAI_Forms::init();
	MoshAI_REST::init();
	MoshAI_MCP::init();
	MoshAI_Tools::init();
	MoshAI_Switches::init();
	MoshAI_Updater::init();
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
