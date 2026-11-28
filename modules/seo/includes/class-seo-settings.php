<?php
/**
 * SEO settings: one option, sensible defaults, and updates by dot path ("social.og_enabled").
 * Every value is sanitized against the type of its default, so an AI can change any setting
 * but never store something the module cannot use.
 *
 * @package AI_MCP
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class MoshAI_SEO_Settings {

	const OPTION = 'moshai_seo';

	private static $cache = null;

	/** Settings stored as multi-line text (newlines kept). */
	const TEXTAREAS = array( 'robots.extra', 'rss.before', 'rss.after', 'social.profiles_other', 'sitemap.exclude_ids', 'llms.intro' );

	/** Settings stored as lists of strings. */
	const LISTS = array( 'sitemap.exclude_types', 'sitemap.exclude_tax' );

	/** Allowed values for choice settings. */
	public static function choices() {
		return array(
			'knowledge.type'        => array( 'organization', 'person' ),
			'social.twitter_card'   => array( 'summary_large_image', 'summary' ),
			'types.*.schema_page'   => array( 'WebPage', 'ItemPage', 'AboutPage', 'FAQPage', 'QAPage', 'ProfilePage', 'ContactPage', 'MedicalWebPage', 'CollectionPage', 'CheckoutPage', 'RealEstateListing', 'SearchResultsPage' ),
			'types.*.schema_article' => array( 'None', 'Article', 'BlogPosting', 'SocialMediaPosting', 'NewsArticle', 'AdvertiserContentArticle', 'SatiricalArticle', 'ScholarlyArticle', 'TechArticle', 'Report' ),
		);
	}

	public static function defaults() {
		$d = array(
			'general'     => array(
				'sep'        => '-',
				'home_title' => '%%sitename%% %%page%% %%sep%% %%sitedesc%%',
				'home_desc'  => '',
				'og_home_image' => '',
			),
			'knowledge'   => array(
				'type'        => 'organization',
				'name'        => '',
				'alt_name'    => '',
				'logo'        => '',
				'person_name' => '',
				'person_image' => '',
			),
			'social'      => array(
				'og_enabled'      => 1,
				'twitter_enabled' => 1,
				'default_image'   => '',
				'fb_app_id'       => '',
				'twitter_card'    => 'summary_large_image',
				'twitter_site'    => '',
				'facebook'        => '',
				'instagram'       => '',
				'linkedin'        => '',
				'youtube'         => '',
				'pinterest'       => '',
				'tiktok'          => '',
				'profiles_other'  => '',
			),
			'types'       => array(),
			'tax'         => array(),
			'archives'    => array(
				'author_index' => 1,
				'author_title' => '%%name%%, Author at %%sitename%% %%page%%',
				'author_desc'  => '',
				'date_index'   => 0,
				'date_title'   => '%%date%% %%page%% %%sep%% %%sitename%%',
				'search_title' => 'You searched for %%searchphrase%% %%page%% %%sep%% %%sitename%%',
				'notfound_title' => 'Page not found %%sep%% %%sitename%%',
				'noindex_paged' => 0,
				'attachment_redirect' => 1,
			),
			'sitemap'     => array(
				'enabled'       => 1,
				'authors'       => 0,
				'exclude_types' => array(),
				'exclude_tax'   => array(),
				'exclude_ids'   => '',
			),
			'breadcrumbs' => array(
				'enabled'   => 1,
				'sep'       => '»',
				'home'      => 'Home',
				'prefix'    => '',
				'blog_page' => 1,
				'bold_last' => 1,
			),
			'robots'      => array(
				'extra' => '',
			),
			'verify'      => array(
				'google'    => '',
				'bing'      => '',
				'pinterest' => '',
				'yandex'    => '',
				'baidu'     => '',
			),
			'cleanup'     => array(
				'shortlinks'  => 1,
				'rsd'         => 1,
				'wlw'         => 1,
				'generator'   => 1,
				'rest_link'   => 0,
				'oembed'      => 0,
				'emoji'       => 0,
				'comment_feeds' => 0,
			),
			'rss'         => array(
				'before' => '',
				'after'  => 'The post %%POSTLINK%% appeared first on %%BLOGLINK%%.',
			),
			'redirects'   => array(
				'auto_slug' => 1,
				'log_404'   => 0,
			),
			'llms'        => array(
				'enabled' => 0,
				'intro'   => '',
			),
			'analysis'    => array(
				'keyphrase'   => 1,
				'readability' => 1,
			),
		);
		foreach ( MoshAI_SEO::post_types() as $type => $label ) {
			$d['types'][ $type ] = array(
				'index'          => 1,
				'title'          => '%%title%% %%page%% %%sep%% %%sitename%%',
				'desc'           => '%%excerpt%%',
				'schema_page'    => 'product' === $type ? 'ItemPage' : 'WebPage',
				'schema_article' => 'post' === $type ? 'Article' : 'None',
				'box'            => 1,
				'archive_title'  => '%%pt_plural%% Archive %%page%% %%sep%% %%sitename%%',
				'archive_desc'   => '',
			);
		}
		foreach ( MoshAI_SEO::taxonomies() as $tax => $label ) {
			$d['tax'][ $tax ] = array(
				'index' => 1,
				'title' => '%%term_title%% Archives %%page%% %%sep%% %%sitename%%',
				'desc'  => '%%term_description%%',
			);
		}
		return apply_filters( 'moshai_seo_defaults', $d );
	}

	/** All settings, defaults filled in. */
	public static function all() {
		if ( null !== self::$cache ) {
			return self::$cache;
		}
		$saved = get_option( self::OPTION, array() );
		$all   = self::merge( self::defaults(), is_array( $saved ) ? $saved : array() );
		// Post types and taxonomies are complete only after init: cache from then on.
		if ( did_action( 'wp_loaded' ) ) {
			self::$cache = $all;
		}
		return $all;
	}

	/** One setting by dot path, e.g. get( 'types.post.title' ). */
	public static function get( $path, $fallback = null ) {
		$v = self::all();
		foreach ( explode( '.', $path ) as $k ) {
			if ( ! is_array( $v ) || ! array_key_exists( $k, $v ) ) {
				return $fallback;
			}
			$v = $v[ $k ];
		}
		return $v;
	}

	private static function merge( $defaults, $saved ) {
		foreach ( $saved as $k => $v ) {
			if ( isset( $defaults[ $k ] ) && is_array( $defaults[ $k ] ) && is_array( $v ) && ! self::is_list( $defaults[ $k ] ) ) {
				$defaults[ $k ] = self::merge( $defaults[ $k ], $v );
			} elseif ( array_key_exists( $k, $defaults ) ) {
				$defaults[ $k ] = $v;
			}
		}
		return $defaults;
	}

	private static function is_list( $arr ) {
		return array() === $arr || array_keys( $arr ) === range( 0, count( $arr ) - 1 );
	}

	/** The default for a path, or null when the path does not exist. */
	private static function default_for( $path ) {
		$v = self::defaults();
		foreach ( explode( '.', $path ) as $k ) {
			if ( ! is_array( $v ) || ! array_key_exists( $k, $v ) ) {
				return null;
			}
			$v = $v[ $k ];
		}
		return $v;
	}

	private static function choice_key( $path ) {
		$parts = explode( '.', $path );
		if ( 3 === count( $parts ) && 'types' === $parts[0] ) {
			return 'types.*.' . $parts[2];
		}
		return $path;
	}

	/** Sanitizes one value for a path. Returns WP_Error when the path or value is not allowed. */
	public static function clean( $path, $value ) {
		$default = self::default_for( $path );
		if ( null === $default ) {
			return new WP_Error( 'moshai_seo_path', sprintf( 'Unknown SEO setting: %s', $path ) );
		}
		if ( is_array( $default ) && ! in_array( $path, self::LISTS, true ) ) {
			return new WP_Error( 'moshai_seo_path', sprintf( '%s is a group. Change its settings one by one, e.g. %s.%s', $path, $path, (string) key( $default ) ) );
		}
		if ( in_array( $path, self::LISTS, true ) ) {
			$value = is_array( $value ) ? $value : array_filter( array_map( 'trim', explode( ',', (string) $value ) ) );
			return array_values( array_unique( array_map( 'sanitize_key', $value ) ) );
		}
		if ( is_int( $default ) ) {
			if ( is_string( $value ) ) {
				$value = in_array( strtolower( $value ), array( '1', 'true', 'yes', 'on' ), true ) ? 1 : ( is_numeric( $value ) ? (int) $value : 0 );
			}
			return (int) ( is_bool( $value ) ? $value : $value );
		}
		$value   = is_scalar( $value ) ? (string) $value : '';
		$choices = self::choices();
		$ckey    = self::choice_key( $path );
		if ( isset( $choices[ $ckey ] ) ) {
			if ( ! in_array( $value, $choices[ $ckey ], true ) ) {
				return new WP_Error( 'moshai_seo_value', sprintf( '%s must be one of: %s', $path, implode( ', ', $choices[ $ckey ] ) ) );
			}
			return $value;
		}
		if ( preg_match( '/(image|logo)$/', $path ) || 'knowledge.person_image' === $path ) {
			return esc_url_raw( $value );
		}
		if ( preg_match( '/^social\.(facebook|instagram|linkedin|youtube|pinterest|tiktok)$/', $path ) ) {
			return esc_url_raw( $value );
		}
		if ( in_array( $path, self::TEXTAREAS, true ) ) {
			return sanitize_textarea_field( $value );
		}
		if ( 0 === strpos( $path, 'verify.' ) ) {
			// People often paste the whole <meta> tag: keep only the code.
			if ( preg_match( '/content=["\']([^"\']+)["\']/i', $value, $m ) ) {
				$value = $m[1];
			}
			return sanitize_text_field( $value );
		}
		return sanitize_text_field( $value );
	}

	/**
	 * Applies several changes at once: array( 'social.og_enabled' => 0, 'types.post.title' => '...' ).
	 * Returns the list of changed paths with their new values, or WP_Error on the first bad change.
	 */
	public static function update( $changes ) {
		if ( ! is_array( $changes ) || ! $changes ) {
			return new WP_Error( 'moshai_seo_empty', 'Nothing to change.' );
		}
		$saved = get_option( self::OPTION, array() );
		$saved = is_array( $saved ) ? $saved : array();
		$done  = array();
		foreach ( $changes as $path => $value ) {
			$path  = trim( (string) $path, '. ' );
			$clean = self::clean( $path, $value );
			if ( is_wp_error( $clean ) ) {
				return $clean;
			}
			$ref  = &$saved;
			$keys = explode( '.', $path );
			$last = array_pop( $keys );
			foreach ( $keys as $k ) {
				if ( ! isset( $ref[ $k ] ) || ! is_array( $ref[ $k ] ) ) {
					$ref[ $k ] = array();
				}
				$ref = &$ref[ $k ];
			}
			$ref[ $last ] = $clean;
			unset( $ref );
			$done[ $path ] = $clean;
		}
		update_option( self::OPTION, $saved, true );
		self::$cache = null;
		do_action( 'moshai_seo_settings_updated', $done );
		return $done;
	}

	/** Every setting path with its type and current value, for the AI. */
	public static function describe( $section = '' ) {
		$all = self::all();
		if ( $section ) {
			if ( ! isset( $all[ $section ] ) ) {
				return new WP_Error( 'moshai_seo_path', 'Unknown section. Sections: ' . implode( ', ', array_keys( $all ) ) );
			}
			$all = array( $section => $all[ $section ] );
		}
		return array(
			'settings'  => $all,
			'choices'   => self::choices(),
			'variables' => MoshAI_SEO_Vars::help(),
			'conflict'  => MoshAI_SEO::conflict(),
			'how_to'    => 'Change settings with seo_update_settings using dot paths, e.g. {"changes": {"types.post.title": "%%title%% %%sep%% %%sitename%%", "social.og_enabled": 1}}.',
		);
	}
}
