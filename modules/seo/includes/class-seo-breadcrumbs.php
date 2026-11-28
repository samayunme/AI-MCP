<?php
/**
 * Breadcrumbs: [ai_mcp_breadcrumbs] shortcode, moshai_breadcrumbs() for themes, and the trail
 * used by the BreadcrumbList schema. Works on posts, pages, products, terms and archives.
 *
 * @package AI_MCP
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class MoshAI_SEO_Breadcrumbs {

	private static $trail = null;

	public static function init() {
		add_shortcode( 'ai_mcp_breadcrumbs', array( __CLASS__, 'shortcode' ) );
	}

	public static function reset() {
		self::$trail = null;
	}

	private static function crumb( $name, $url ) {
		return array(
			'name' => wp_strip_all_tags( (string) $name ),
			'url'  => (string) $url,
		);
	}

	private static function post_name( $post ) {
		$own = MoshAI_SEO_Meta::get( $post->ID, 'breadcrumb_title' );
		return '' !== $own ? $own : get_the_title( $post );
	}

	private static function term_chain( $term ) {
		$out = array();
		foreach ( array_reverse( get_ancestors( $term->term_id, $term->taxonomy, 'taxonomy' ) ) as $id ) {
			$t = get_term( $id, $term->taxonomy );
			if ( $t && ! is_wp_error( $t ) ) {
				$own   = MoshAI_SEO_Meta::term_get( $t->term_id, 'breadcrumb_title' );
				$out[] = self::crumb( '' !== $own ? $own : $t->name, get_term_link( $t ) );
			}
		}
		$own   = MoshAI_SEO_Meta::term_get( $term->term_id, 'breadcrumb_title' );
		$link  = get_term_link( $term );
		$out[] = self::crumb( '' !== $own ? $own : $term->name, is_wp_error( $link ) ? '' : $link );
		return $out;
	}

	/** The trail for the current page: list of array( name, url ). */
	public static function trail() {
		if ( null !== self::$trail ) {
			return self::$trail;
		}
		$s     = 'MoshAI_SEO_Settings';
		$trail = array( self::crumb( $s::get( 'breadcrumbs.home', 'Home' ), home_url( '/' ) ) );
		$shop  = function_exists( 'wc_get_page_id' ) ? (int) wc_get_page_id( 'shop' ) : 0;
		$blog  = (int) get_option( 'page_for_posts' );

		if ( is_front_page() ) {
			self::$trail = $trail;
			return $trail;
		}
		if ( is_singular() ) {
			$post = get_queried_object();
			if ( $post instanceof WP_Post ) {
				if ( 'post' === $post->post_type && $blog && (int) $s::get( 'breadcrumbs.blog_page' ) ) {
					$trail[] = self::crumb( get_the_title( $blog ), get_permalink( $blog ) );
				} elseif ( 'product' === $post->post_type && $shop > 0 ) {
					$trail[] = self::crumb( get_the_title( $shop ), get_permalink( $shop ) );
				} elseif ( ! in_array( $post->post_type, array( 'post', 'page' ), true ) ) {
					$obj = get_post_type_object( $post->post_type );
					if ( $obj && $obj->has_archive ) {
						$trail[] = self::crumb( $obj->labels->name, get_post_type_archive_link( $post->post_type ) );
					}
				}
				if ( is_post_type_hierarchical( $post->post_type ) ) {
					foreach ( array_reverse( get_post_ancestors( $post ) ) as $pid ) {
						$trail[] = self::crumb( self::post_name( get_post( $pid ) ), get_permalink( $pid ) );
					}
				} else {
					$term = MoshAI_SEO_Meta::primary_term( $post );
					if ( $term ) {
						$trail = array_merge( $trail, self::term_chain( $term ) );
					}
				}
				$trail[] = self::crumb( self::post_name( $post ), get_permalink( $post ) );
			}
		} elseif ( is_home() && $blog ) {
			$trail[] = self::crumb( get_the_title( $blog ), get_permalink( $blog ) );
		} elseif ( is_category() || is_tag() || is_tax() ) {
			$term = get_queried_object();
			if ( $term instanceof WP_Term ) {
				if ( in_array( $term->taxonomy, array( 'product_cat', 'product_tag' ), true ) && $shop > 0 ) {
					$trail[] = self::crumb( get_the_title( $shop ), get_permalink( $shop ) );
				} elseif ( in_array( $term->taxonomy, array( 'category', 'post_tag' ), true ) && $blog && (int) $s::get( 'breadcrumbs.blog_page' ) ) {
					$trail[] = self::crumb( get_the_title( $blog ), get_permalink( $blog ) );
				}
				$trail = array_merge( $trail, self::term_chain( $term ) );
			}
		} elseif ( is_post_type_archive() ) {
			$type = get_query_var( 'post_type' );
			$type = is_array( $type ) ? reset( $type ) : $type;
			$obj  = get_post_type_object( $type );
			if ( $obj ) {
				$trail[] = self::crumb( 'product' === $type && $shop > 0 ? get_the_title( $shop ) : $obj->labels->name, get_post_type_archive_link( $type ) );
			}
		} elseif ( is_author() ) {
			$user    = get_queried_object();
			$trail[] = self::crumb( $user instanceof WP_User ? $user->display_name : '', $user instanceof WP_User ? get_author_posts_url( $user->ID ) : '' );
		} elseif ( is_search() ) {
			/* translators: %s search terms */
			$trail[] = self::crumb( sprintf( __( 'Search results for "%s"', 'ai-mcp' ), get_search_query() ), '' );
		} elseif ( is_404() ) {
			$trail[] = self::crumb( __( 'Page not found', 'ai-mcp' ), '' );
		} elseif ( is_date() ) {
			$trail[] = self::crumb( get_the_archive_title(), '' );
		}
		self::$trail = apply_filters( 'moshai_seo_breadcrumbs', $trail );
		return self::$trail;
	}

	public static function render() {
		if ( ! (int) MoshAI_SEO_Settings::get( 'breadcrumbs.enabled' ) ) {
			return '';
		}
		$trail = self::trail();
		if ( count( $trail ) < 2 ) {
			return '';
		}
		$sep   = ' <span class="moshai-bc-sep" aria-hidden="true">' . esc_html( MoshAI_SEO_Settings::get( 'breadcrumbs.sep', '»' ) ) . '</span> ';
		$parts = array();
		$last  = count( $trail ) - 1;
		foreach ( $trail as $i => $c ) {
			if ( $i === $last ) {
				$name    = esc_html( $c['name'] );
				$parts[] = '<span class="moshai-bc-current" aria-current="page">' . ( (int) MoshAI_SEO_Settings::get( 'breadcrumbs.bold_last' ) ? '<strong>' . $name . '</strong>' : $name ) . '</span>';
			} else {
				$parts[] = $c['url'] ? '<a href="' . esc_url( $c['url'] ) . '">' . esc_html( $c['name'] ) . '</a>' : esc_html( $c['name'] );
			}
		}
		$prefix = (string) MoshAI_SEO_Settings::get( 'breadcrumbs.prefix' );
		return '<nav class="moshai-breadcrumbs" aria-label="' . esc_attr__( 'Breadcrumb', 'ai-mcp' ) . '">' . ( '' !== $prefix ? esc_html( $prefix ) . ' ' : '' ) . implode( $sep, $parts ) . '</nav>';
	}

	public static function shortcode() {
		return self::render();
	}
}

if ( ! function_exists( 'moshai_breadcrumbs' ) ) {
	/** Theme template tag: call moshai_breadcrumbs() where the breadcrumbs should appear. */
	function moshai_breadcrumbs( $echo = true ) {
		$html = MoshAI_SEO_Breadcrumbs::render();
		if ( $echo ) {
			echo $html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in render().
		}
		return $html;
	}
}
