<?php
/**
 * XML sitemaps on top of the WordPress core sitemaps (/wp-sitemap.xml): fast, cached by core,
 * and controlled here: which content types, no noindex pages, excluded IDs, last-modified dates.
 *
 * @package AI_MCP
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class MoshAI_SEO_Sitemaps {

	public static function init() {
		add_filter( 'wp_sitemaps_enabled', array( __CLASS__, 'enabled' ) );
		add_filter( 'wp_sitemaps_post_types', array( __CLASS__, 'post_types' ) );
		add_filter( 'wp_sitemaps_taxonomies', array( __CLASS__, 'taxonomies' ) );
		add_filter( 'wp_sitemaps_add_provider', array( __CLASS__, 'provider' ), 10, 2 );
		add_filter( 'wp_sitemaps_posts_query_args', array( __CLASS__, 'posts_args' ), 10, 2 );
		add_filter( 'wp_sitemaps_taxonomies_query_args', array( __CLASS__, 'terms_args' ), 10, 2 );
		add_filter( 'wp_sitemaps_posts_entry', array( __CLASS__, 'post_entry' ), 10, 2 );
		add_action( 'template_redirect', array( __CLASS__, 'legacy_urls' ), 0 );
	}

	public static function url() {
		return home_url( '/wp-sitemap.xml' );
	}

	public static function enabled( $on ) {
		return $on && (int) MoshAI_SEO_Settings::get( 'sitemap.enabled' );
	}

	public static function post_types( $types ) {
		$exclude = (array) MoshAI_SEO_Settings::get( 'sitemap.exclude_types', array() );
		foreach ( array_keys( $types ) as $t ) {
			if ( in_array( $t, $exclude, true ) || ! (int) MoshAI_SEO_Meta::type_setting( $t, 'index' ) ) {
				unset( $types[ $t ] );
			}
		}
		unset( $types['elementor_library'], $types['e-landing-page'] );
		return $types;
	}

	public static function taxonomies( $tax ) {
		$exclude = (array) MoshAI_SEO_Settings::get( 'sitemap.exclude_tax', array() );
		foreach ( array_keys( $tax ) as $t ) {
			if ( in_array( $t, $exclude, true ) || ! (int) MoshAI_SEO_Settings::get( 'tax.' . $t . '.index', 1 ) ) {
				unset( $tax[ $t ] );
			}
		}
		return $tax;
	}

	public static function provider( $provider, $name ) {
		if ( 'users' === $name && ( ! (int) MoshAI_SEO_Settings::get( 'sitemap.authors' ) || ! (int) MoshAI_SEO_Settings::get( 'archives.author_index' ) ) ) {
			return false;
		}
		return $provider;
	}

	private static function excluded_ids() {
		return array_filter( array_map( 'absint', preg_split( '/[\s,]+/', (string) MoshAI_SEO_Settings::get( 'sitemap.exclude_ids' ) ) ) );
	}

	public static function posts_args( $args, $type ) {
		$ids = self::excluded_ids();
		if ( $ids ) {
			$args['post__not_in'] = isset( $args['post__not_in'] ) ? array_merge( (array) $args['post__not_in'], $ids ) : $ids;
		}
		$args['meta_query'] = array( // phpcs:ignore WordPress.DB.SlowDBQuery
			'relation' => 'OR',
			array(
				'key'     => MoshAI_SEO_Meta::PREFIX . 'robots_index',
				'compare' => 'NOT EXISTS',
			),
			array(
				'key'     => MoshAI_SEO_Meta::PREFIX . 'robots_index',
				'value'   => 'noindex',
				'compare' => '!=',
			),
		);
		return $args;
	}

	public static function terms_args( $args, $tax ) {
		$args['meta_query'] = array( // phpcs:ignore WordPress.DB.SlowDBQuery
			'relation' => 'OR',
			array(
				'key'     => MoshAI_SEO_Meta::PREFIX . 'robots_index',
				'compare' => 'NOT EXISTS',
			),
			array(
				'key'     => MoshAI_SEO_Meta::PREFIX . 'robots_index',
				'value'   => 'noindex',
				'compare' => '!=',
			),
		);
		return $args;
	}

	public static function post_entry( $entry, $post ) {
		$entry['lastmod'] = get_post_modified_time( 'c', true, $post );
		$own              = MoshAI_SEO_Meta::get( $post->ID, 'canonical' );
		if ( $own && $own !== $entry['loc'] ) {
			// This page says another URL is the original: list that one instead of a duplicate.
			$entry['loc'] = $own;
		}
		return $entry;
	}

	/** Old sitemap addresses (Yoast, Rank Math) keep working. */
	public static function legacy_urls() {
		if ( ! is_404() ) {
			return;
		}
		$path = wp_parse_url( isset( $_SERVER['REQUEST_URI'] ) ? esc_url_raw( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '', PHP_URL_PATH );
		$base = wp_parse_url( home_url( '/' ), PHP_URL_PATH );
		$rel  = '/' . ltrim( substr( (string) $path, strlen( (string) $base ) ), '/' );
		if ( in_array( $rel, array( '/sitemap_index.xml', '/sitemap.xml', '/sitemaps.xml', '/sitemap-index.xml' ), true ) && (int) MoshAI_SEO_Settings::get( 'sitemap.enabled' ) ) {
			wp_safe_redirect( self::url(), 301 );
			exit;
		}
	}
}
