<?php
/**
 * SEO module: everything a site needs to rank, controllable by an AI through MCP.
 *
 * Folder layout (one job per file):
 *   includes/class-seo-settings.php    settings store, defaults and safe updates
 *   includes/class-seo-vars.php        %%variables%% used in title and description templates
 *   includes/class-seo-meta.php        per-post and per-term SEO fields, meta box, term fields
 *   includes/class-seo-analyzer.php    keyphrase and readability analysis with a 0-100 score
 *   includes/class-seo-head.php        <title>, meta description, robots, canonical, Open Graph, X/Twitter
 *   includes/class-seo-schema.php      JSON-LD schema graph
 *   includes/class-seo-sitemaps.php    XML sitemaps (built on the WordPress core sitemaps)
 *   includes/class-seo-redirects.php   redirect manager, automatic slug redirects, 404 monitor
 *   includes/class-seo-breadcrumbs.php breadcrumbs shortcode and function
 *   includes/class-seo-tools.php       robots.txt, llms.txt, head cleanup, RSS, import from Yoast / Rank Math
 *   includes/class-seo-audit.php       site health checks, content report, bulk re-scoring
 *   includes/class-seo-rest.php        REST routes for the dashboard and the meta box
 *   includes/class-seo-mcp.php         MCP tools so an AI can audit and optimize the whole site
 *   includes/class-seo-admin.php       AI MCP > SEO dashboard tab
 *
 * @package AI_MCP
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'MOSHAI_SEO_DIR', __DIR__ . '/' );
define( 'MOSHAI_SEO_URL', MOSHAI_URL . 'modules/seo/' );

foreach ( array( 'settings', 'vars', 'meta', 'analyzer', 'head', 'schema', 'sitemaps', 'redirects', 'breadcrumbs', 'tools', 'audit', 'rest', 'mcp', 'admin' ) as $moshai_seo_part ) {
	require_once MOSHAI_SEO_DIR . 'includes/class-seo-' . $moshai_seo_part . '.php';
}
unset( $moshai_seo_part );

class MoshAI_SEO {

	const DB_VERSION = '1.0';

	public static function init() {
		MoshAI_SEO_Redirects::maybe_install();
		MoshAI_SEO_Meta::init();
		MoshAI_SEO_REST::init();
		MoshAI_SEO_MCP::init();
		MoshAI_SEO_Admin::init();
		MoshAI_SEO_Sitemaps::init();
		MoshAI_SEO_Redirects::init();
		MoshAI_SEO_Tools::init();
		MoshAI_SEO_Breadcrumbs::init();
		if ( ! self::conflict() ) {
			MoshAI_SEO_Head::init();
		}
	}

	/**
	 * Another SEO plugin that already prints titles, meta tags and schema. When one is active
	 * the front-end output stays off so pages never get duplicate tags.
	 */
	public static function conflict() {
		$plugins = array(
			'WPSEO_VERSION'       => 'Yoast SEO',
			'RANK_MATH_VERSION'   => 'Rank Math',
			'AIOSEO_VERSION'      => 'All in One SEO',
			'SEOPRESS_VERSION'    => 'SEOPress',
			'THE_SEO_FRAMEWORK_VERSION' => 'The SEO Framework',
			'SLIM_SEO_VER'        => 'Slim SEO',
			'SQ_VERSION'          => 'Squirrly SEO',
		);
		foreach ( $plugins as $const => $name ) {
			if ( defined( $const ) ) {
				return $name;
			}
		}
		return '';
	}

	/** Public post types that can appear in search results. */
	public static function post_types() {
		$types = get_post_types( array( 'public' => true ), 'objects' );
		unset( $types['attachment'], $types['elementor_library'], $types['e-landing-page'], $types['cartflows_step'], $types['cartflows_flow'] );
		$types = apply_filters( 'moshai_seo_post_types', $types );
		$out   = array();
		foreach ( $types as $name => $obj ) {
			$out[ $name ] = $obj->labels->name;
		}
		return $out;
	}

	public static function taxonomies() {
		$tax = get_taxonomies( array( 'public' => true ), 'objects' );
		unset( $tax['post_format'], $tax['product_shipping_class'], $tax['elementor_library_type'], $tax['elementor_library_category'] );
		$tax = apply_filters( 'moshai_seo_taxonomies', $tax );
		$out = array();
		foreach ( $tax as $name => $obj ) {
			$out[ $name ] = $obj->labels->name;
		}
		return $out;
	}
}
