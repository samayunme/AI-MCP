<?php
/**
 * Site-level SEO tools: robots.txt rules, llms.txt for AI search, <head> cleanup, RSS
 * footer links, and importing titles, descriptions and keyphrases from Yoast or Rank Math.
 *
 * @package AI_MCP
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class MoshAI_SEO_Tools {

	public static function init() {
		add_filter( 'robots_txt', array( __CLASS__, 'robots_txt' ), 20, 2 );
		add_action( 'parse_request', array( __CLASS__, 'llms_txt' ), 0 );
		add_action( 'wp', array( __CLASS__, 'cleanup' ) );
		add_filter( 'the_content_feed', array( __CLASS__, 'rss' ) );
		add_filter( 'the_excerpt_rss', array( __CLASS__, 'rss' ) );
		add_action( 'save_post', array( __CLASS__, 'flush_llms' ) );
	}

	public static function robots_txt( $out, $public ) {
		$extra = trim( (string) MoshAI_SEO_Settings::get( 'robots.extra' ) );
		if ( $public && '' !== $extra ) {
			$out = rtrim( $out ) . "\n\n" . $extra . "\n";
		}
		return $out;
	}

	public static function cleanup() {
		$c = (array) MoshAI_SEO_Settings::get( 'cleanup', array() );
		if ( ! empty( $c['shortlinks'] ) ) {
			remove_action( 'wp_head', 'wp_shortlink_wp_head' );
			remove_action( 'template_redirect', 'wp_shortlink_header', 11 );
		}
		if ( ! empty( $c['rsd'] ) ) {
			remove_action( 'wp_head', 'rsd_link' );
		}
		if ( ! empty( $c['wlw'] ) ) {
			remove_action( 'wp_head', 'wlwmanifest_link' );
		}
		if ( ! empty( $c['generator'] ) ) {
			remove_action( 'wp_head', 'wp_generator' );
			add_filter( 'the_generator', '__return_empty_string' );
		}
		if ( ! empty( $c['rest_link'] ) ) {
			remove_action( 'wp_head', 'rest_output_link_wp_head' );
			remove_action( 'template_redirect', 'rest_output_link_header', 11 );
		}
		if ( ! empty( $c['oembed'] ) ) {
			remove_action( 'wp_head', 'wp_oembed_add_discovery_links' );
		}
		if ( ! empty( $c['emoji'] ) ) {
			remove_action( 'wp_head', 'print_emoji_detection_script', 7 );
			remove_action( 'wp_print_styles', 'print_emoji_styles' );
			remove_action( 'admin_print_scripts', 'print_emoji_detection_script' );
			remove_action( 'admin_print_styles', 'print_emoji_styles' );
		}
		if ( ! empty( $c['comment_feeds'] ) ) {
			add_filter( 'feed_links_show_comments_feed', '__return_false' );
		}
	}

	public static function rss( $content ) {
		if ( ! is_feed() ) {
			return $content;
		}
		$post = get_post();
		if ( ! $post ) {
			return $content;
		}
		$swap = array(
			'%%POSTLINK%%' => '<a href="' . esc_url( get_permalink( $post ) ) . '">' . esc_html( get_the_title( $post ) ) . '</a>',
			'%%BLOGLINK%%' => '<a href="' . esc_url( home_url( '/' ) ) . '">' . esc_html( get_bloginfo( 'name' ) ) . '</a>',
		);
		$before = (string) MoshAI_SEO_Settings::get( 'rss.before' );
		$after  = (string) MoshAI_SEO_Settings::get( 'rss.after' );
		$before = '' !== $before ? '<p>' . strtr( esc_html( $before ), $swap ) . '</p>' : '';
		$after  = '' !== $after ? '<p>' . strtr( esc_html( $after ), $swap ) . '</p>' : '';
		return $before . $content . $after;
	}

	/* ---------- llms.txt ---------- */

	public static function llms_txt( $wp ) {
		if ( ! (int) MoshAI_SEO_Settings::get( 'llms.enabled' ) ) {
			return;
		}
		$path = wp_parse_url( isset( $_SERVER['REQUEST_URI'] ) ? esc_url_raw( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '', PHP_URL_PATH );
		if ( '/llms.txt' !== MoshAI_SEO_Redirects::normalize( (string) $path ) ) {
			return;
		}
		header( 'Content-Type: text/plain; charset=utf-8' );
		header( 'X-Robots-Tag: noindex' );
		echo self::llms_body(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- plain text, built from stripped strings.
		exit;
	}

	public static function flush_llms() {
		delete_transient( 'moshai_seo_llms' );
	}

	/** Markdown map of the site for AI assistants (llmstxt.org format). */
	public static function llms_body() {
		$cached = get_transient( 'moshai_seo_llms' );
		if ( false !== $cached ) {
			return $cached;
		}
		$clean = function ( $s ) {
			return trim( preg_replace( '/\s+/', ' ', wp_strip_all_tags( (string) $s ) ) );
		};
		$out   = '# ' . $clean( get_bloginfo( 'name' ) ) . "\n\n";
		$desc  = $clean( get_bloginfo( 'description' ) );
		if ( '' !== $desc ) {
			$out .= '> ' . $desc . "\n\n";
		}
		$intro = trim( (string) MoshAI_SEO_Settings::get( 'llms.intro' ) );
		if ( '' !== $intro ) {
			$out .= $intro . "\n\n";
		}
		foreach ( MoshAI_SEO::post_types() as $type => $label ) {
			if ( ! (int) MoshAI_SEO_Meta::type_setting( $type, 'index' ) ) {
				continue;
			}
			$posts = get_posts(
				array(
					'post_type'      => $type,
					'post_status'    => 'publish',
					'posts_per_page' => 50,
					'orderby'        => 'menu_order date',
					'order'          => 'DESC',
					'no_found_rows'  => true,
				)
			);
			$lines = '';
			foreach ( $posts as $p ) {
				if ( MoshAI_SEO_Meta::is_noindex( $p ) || post_password_required( $p ) ) {
					continue;
				}
				$d      = $clean( MoshAI_SEO_Meta::resolved_description( $p ) );
				$lines .= '- [' . $clean( get_the_title( $p ) ) . '](' . get_permalink( $p ) . ')' . ( '' !== $d ? ': ' . $d : '' ) . "\n";
			}
			if ( '' !== $lines ) {
				$out .= '## ' . $clean( $label ) . "\n\n" . $lines . "\n";
			}
		}
		set_transient( 'moshai_seo_llms', $out, 12 * HOUR_IN_SECONDS );
		return $out;
	}

	/* ---------- Import ---------- */

	/** Which other SEO plugins left data behind, with post counts. */
	public static function import_sources() {
		global $wpdb;
		$out = array();
		foreach ( array(
			'yoast'    => array( 'Yoast SEO', '_yoast_wpseo_%' ),
			'rankmath' => array( 'Rank Math', 'rank_math_%' ),
		) as $k => $def ) {
			$out[ $k ] = array(
				'name'  => $def[0],
				'posts' => (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(DISTINCT post_id) FROM $wpdb->postmeta WHERE meta_key LIKE %s", $def[1] ) ), // phpcs:ignore
			);
		}
		return $out;
	}

	private static function convert_vars( $text ) {
		$map  = array(
			'term'     => 'term_title',
			'tagline'  => 'sitedesc',
			'site_description' => 'sitedesc',
			'seo_title' => 'title',
			'focuskw'  => 'focuskw',
			'keywords' => 'focuskw',
		);
		return preg_replace_callback(
			'/(?<!%)%([a-z_]+)%(?!%)/',
			function ( $m ) use ( $map ) {
				return '%%' . ( isset( $map[ $m[1] ] ) ? $map[ $m[1] ] : $m[1] ) . '%%';
			},
			(string) $text
		);
	}

	/**
	 * Copies SEO data from Yoast or Rank Math, 200 posts per call. Returns the next offset
	 * (or null when done). Existing AI MCP values stay unless $overwrite.
	 */
	public static function import( $from, $offset = 0, $overwrite = false ) {
		global $wpdb;
		$from = sanitize_key( $from );
		if ( 'yoast' === $from ) {
			$keys = array(
				'_yoast_wpseo_title'                 => 'title',
				'_yoast_wpseo_metadesc'              => 'description',
				'_yoast_wpseo_focuskw'               => 'focus_keyphrase',
				'_yoast_wpseo_canonical'             => 'canonical',
				'_yoast_wpseo_opengraph-title'       => 'og_title',
				'_yoast_wpseo_opengraph-description' => 'og_description',
				'_yoast_wpseo_opengraph-image'       => 'og_image',
				'_yoast_wpseo_twitter-title'         => 'twitter_title',
				'_yoast_wpseo_twitter-description'   => 'twitter_description',
				'_yoast_wpseo_twitter-image'         => 'twitter_image',
				'_yoast_wpseo_bctitle'               => 'breadcrumb_title',
				'_yoast_wpseo_primary_category'      => 'primary_term',
				'_yoast_wpseo_meta-robots-noindex'   => 'robots_index',
				'_yoast_wpseo_meta-robots-nofollow'  => 'nofollow',
				'_yoast_wpseo_is_cornerstone'        => 'cornerstone',
			);
			$like = '_yoast_wpseo_%';
		} elseif ( 'rankmath' === $from ) {
			$keys = array(
				'rank_math_title'                => 'title',
				'rank_math_description'          => 'description',
				'rank_math_focus_keyword'        => 'focus_keyphrase',
				'rank_math_canonical_url'        => 'canonical',
				'rank_math_facebook_title'       => 'og_title',
				'rank_math_facebook_description' => 'og_description',
				'rank_math_facebook_image'       => 'og_image',
				'rank_math_twitter_title'        => 'twitter_title',
				'rank_math_twitter_description'  => 'twitter_description',
				'rank_math_breadcrumb_title'     => 'breadcrumb_title',
				'rank_math_primary_category'     => 'primary_term',
				'rank_math_robots'               => 'robots_index',
				'rank_math_pillar_content'       => 'cornerstone',
			);
			$like = 'rank_math_%';
		} else {
			return new WP_Error( 'moshai_seo_import', 'from must be "yoast" or "rankmath".' );
		}
		$ids = $wpdb->get_col( $wpdb->prepare( "SELECT DISTINCT post_id FROM $wpdb->postmeta WHERE meta_key LIKE %s ORDER BY post_id ASC LIMIT 200 OFFSET %d", $like, (int) $offset ) ); // phpcs:ignore
		$done = 0;
		foreach ( $ids as $id ) {
			$id     = (int) $id;
			$values = array();
			foreach ( $keys as $src => $field ) {
				$v = get_post_meta( $id, $src, true );
				if ( '' === $v || null === $v || ( ! $overwrite && '' !== MoshAI_SEO_Meta::get( $id, $field ) ) ) {
					continue;
				}
				if ( 'robots_index' === $field ) {
					if ( 'rankmath' === $from ) {
						$arr = (array) $v;
						if ( in_array( 'nofollow', $arr, true ) ) {
							$values['nofollow'] = '1';
						}
						foreach ( array( 'noarchive', 'nosnippet', 'noimageindex' ) as $r ) {
							if ( in_array( $r, $arr, true ) ) {
								$values[ $r ] = '1';
							}
						}
						$v = in_array( 'noindex', $arr, true ) ? 'noindex' : ( in_array( 'index', $arr, true ) ? 'index' : '' );
					} else {
						$v = '1' === (string) $v ? 'noindex' : ( '2' === (string) $v ? 'index' : '' );
					}
				} elseif ( in_array( $field, array( 'nofollow', 'cornerstone' ), true ) ) {
					$v = in_array( (string) $v, array( '1', 'on' ), true ) ? '1' : '';
				} elseif ( 'focus_keyphrase' === $field && 'rankmath' === $from && false !== strpos( (string) $v, ',' ) ) {
					$all                          = array_map( 'trim', explode( ',', (string) $v ) );
					$v                            = array_shift( $all );
					$values['related_keyphrases'] = implode( ', ', $all );
				} elseif ( in_array( $field, array( 'title', 'description', 'og_title', 'og_description', 'twitter_title', 'twitter_description' ), true ) ) {
					$v = self::convert_vars( $v );
				}
				if ( ! is_scalar( $v ) || '' === (string) $v ) {
					continue;
				}
				$values[ $field ] = (string) $v;
			}
			if ( $values ) {
				foreach ( $values as $f => $v ) {
					if ( is_wp_error( MoshAI_SEO_Meta::sanitize( $f, $v ) ) ) {
						unset( $values[ $f ] );
					}
				}
				MoshAI_SEO_Meta::update( $id, $values );
				++$done;
			}
		}
		return array(
			'from'        => $from,
			'imported'    => $done,
			'checked'     => count( $ids ),
			'next_offset' => count( $ids ) === 200 ? (int) $offset + 200 : null,
		);
	}
}
