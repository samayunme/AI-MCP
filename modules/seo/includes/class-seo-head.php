<?php
/**
 * Front-end <head> output: title, meta description, robots, canonical, Open Graph, X (Twitter)
 * cards, verification codes and the schema graph. Values are worked out once per request.
 *
 * @package AI_MCP
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class MoshAI_SEO_Head {

	private static $ctx = null;

	public static function init() {
		add_filter( 'pre_get_document_title', array( __CLASS__, 'document_title' ), 20 );
		add_filter( 'wp_title', array( __CLASS__, 'document_title' ), 20 );
		add_filter( 'wp_robots', array( __CLASS__, 'robots' ), 20 );
		remove_action( 'wp_head', 'rel_canonical' );
		add_action( 'wp_head', array( __CLASS__, 'head' ), 1 );
		add_action( 'template_redirect', array( __CLASS__, 'attachment_redirect' ), 1 );
		// WooCommerce prints its own product schema; Elementor Hello prints no meta description.
		add_filter( 'woocommerce_structured_data_website', '__return_empty_array' );
	}

	/** Forgets the computed context (used when previewing another page). */
	public static function reset() {
		self::$ctx = null;
	}

	/** Everything about the current request, computed once. */
	public static function context() {
		if ( null !== self::$ctx ) {
			return self::$ctx;
		}
		$c = array(
			'kind'      => 'other',
			'post'      => null,
			'term'      => null,
			'user'      => null,
			'type'      => '',
			'title'     => '',
			'desc'      => '',
			'canonical' => '',
			'noindex'   => false,
			'nofollow'  => false,
			'robots'    => array(),
			'image'     => '',
			'og_type'   => 'website',
		);
		$s    = 'MoshAI_SEO_Settings';
		$page = MoshAI_SEO_Vars::page_number();

		if ( is_front_page() && is_home() || ( is_front_page() && ! get_option( 'page_on_front' ) ) ) {
			$c['kind']      = 'home';
			$c['title']     = MoshAI_SEO_Vars::replace( $s::get( 'general.home_title' ) );
			$c['desc']      = MoshAI_SEO_Vars::replace( $s::get( 'general.home_desc' ) );
			$c['canonical'] = $page > 1 ? get_pagenum_link( $page ) : home_url( '/' );
			$c['image']     = (string) $s::get( 'general.og_home_image' );
		} elseif ( is_singular() || ( is_home() && get_option( 'page_for_posts' ) ) ) {
			$post         = is_home() ? get_post( (int) get_option( 'page_for_posts' ) ) : get_queried_object();
			$c['kind']    = 'singular';
			$c['post']    = $post;
			if ( $post instanceof WP_Post ) {
				$c['type']    = $post->post_type;
				$c['title']   = MoshAI_SEO_Meta::resolved_title( $post );
				$c['desc']    = MoshAI_SEO_Meta::resolved_description( $post );
				$c['noindex'] = MoshAI_SEO_Meta::is_noindex( $post );
				$c['nofollow'] = '1' === MoshAI_SEO_Meta::get( $post->ID, 'nofollow' );
				foreach ( array( 'noarchive', 'nosnippet', 'noimageindex' ) as $r ) {
					if ( '1' === MoshAI_SEO_Meta::get( $post->ID, $r ) ) {
						$c['robots'][] = $r;
					}
				}
				$own              = MoshAI_SEO_Meta::get( $post->ID, 'canonical' );
				$c['canonical']   = $own ? $own : ( is_home() ? get_pagenum_link( $page ) : self::paged_permalink( $post, $page ) );
				$c['image']       = self::post_image( $post );
				$c['og_type']     = ( 'post' === $post->post_type || 'Article' === MoshAI_SEO_Meta::type_setting( $post->post_type, 'schema_article' ) ) && (int) get_option( 'page_on_front' ) !== $post->ID ? 'article' : 'website';
				if ( (int) get_option( 'page_on_front' ) === $post->ID ) {
					$c['kind'] = 'home';
					$c['canonical'] = $own ? $own : home_url( '/' );
					if ( '' === $c['image'] ) {
						$c['image'] = (string) $s::get( 'general.og_home_image' );
					}
				}
			}
		} elseif ( is_category() || is_tag() || is_tax() ) {
			$term      = get_queried_object();
			$c['kind'] = 'term';
			$c['term'] = $term;
			if ( $term instanceof WP_Term ) {
				$own            = MoshAI_SEO_Meta::term_get( $term->term_id, 'title' );
				$tpl            = '' !== $own ? $own : $s::get( 'tax.' . $term->taxonomy . '.title', '%%term_title%% %%page%% %%sep%% %%sitename%%' );
				$c['title']     = MoshAI_SEO_Vars::replace( $tpl, array( 'term' => $term ) );
				$own_d          = MoshAI_SEO_Meta::term_get( $term->term_id, 'description' );
				$c['desc']      = MoshAI_SEO_Vars::replace( '' !== $own_d ? $own_d : $s::get( 'tax.' . $term->taxonomy . '.desc', '' ), array( 'term' => $term ) );
				$c['noindex']   = MoshAI_SEO_Meta::is_term_noindex( $term );
				$c['nofollow']  = '1' === MoshAI_SEO_Meta::term_get( $term->term_id, 'nofollow' );
				$own_c          = MoshAI_SEO_Meta::term_get( $term->term_id, 'canonical' );
				$link           = get_term_link( $term );
				$c['canonical'] = $own_c ? $own_c : ( is_wp_error( $link ) ? '' : ( $page > 1 ? trailingslashit( $link ) . user_trailingslashit( 'page/' . $page, 'paged' ) : $link ) );
				$c['image']     = MoshAI_SEO_Meta::term_get( $term->term_id, 'og_image' );
				if ( '' === $c['image'] && 'product_cat' === $term->taxonomy ) {
					$thumb = (int) get_term_meta( $term->term_id, 'thumbnail_id', true );
					$c['image'] = $thumb ? (string) wp_get_attachment_image_url( $thumb, 'full' ) : '';
				}
			}
		} elseif ( is_post_type_archive() ) {
			$type           = get_query_var( 'post_type' );
			$type           = is_array( $type ) ? reset( $type ) : $type;
			$c['kind']      = 'archive';
			$c['type']      = $type;
			$c['title']     = MoshAI_SEO_Vars::replace( $s::get( 'types.' . $type . '.archive_title', '%%pt_plural%% Archive %%page%% %%sep%% %%sitename%%' ), array( 'type' => $type ) );
			$c['desc']      = MoshAI_SEO_Vars::replace( $s::get( 'types.' . $type . '.archive_desc', '' ), array( 'type' => $type ) );
			$c['canonical'] = get_pagenum_link( $page );
			if ( function_exists( 'is_shop' ) && is_shop() ) {
				$shop = get_post( (int) wc_get_page_id( 'shop' ) );
				if ( $shop ) {
					$own = MoshAI_SEO_Meta::get( $shop->ID, 'title' );
					if ( '' !== $own ) {
						$c['title'] = MoshAI_SEO_Vars::replace( $own, array( 'post' => $shop ) );
					}
					$own_d = MoshAI_SEO_Meta::get( $shop->ID, 'description' );
					if ( '' !== $own_d ) {
						$c['desc'] = MoshAI_SEO_Vars::replace( $own_d, array( 'post' => $shop ) );
					}
					$c['noindex'] = 'noindex' === MoshAI_SEO_Meta::get( $shop->ID, 'robots_index' );
				}
			}
		} elseif ( is_author() ) {
			$user           = get_queried_object();
			$c['kind']      = 'author';
			$c['user']      = $user instanceof WP_User ? $user : null;
			$c['title']     = MoshAI_SEO_Vars::replace( $s::get( 'archives.author_title' ), array( 'user' => $c['user'] ) );
			$c['desc']      = MoshAI_SEO_Vars::replace( $s::get( 'archives.author_desc' ), array( 'user' => $c['user'] ) );
			$c['noindex']   = ! (int) $s::get( 'archives.author_index' );
			$c['canonical'] = $c['user'] ? get_author_posts_url( $c['user']->ID ) : '';
			$c['og_type']   = 'profile';
		} elseif ( is_date() ) {
			$c['kind']      = 'date';
			$c['title']     = MoshAI_SEO_Vars::replace( $s::get( 'archives.date_title' ) );
			$c['noindex']   = ! (int) $s::get( 'archives.date_index' );
			$c['canonical'] = get_pagenum_link( $page );
		} elseif ( is_search() ) {
			$c['kind']    = 'search';
			$c['title']   = MoshAI_SEO_Vars::replace( $s::get( 'archives.search_title' ) );
			$c['noindex'] = true;
		} elseif ( is_404() ) {
			$c['kind']    = '404';
			$c['title']   = MoshAI_SEO_Vars::replace( $s::get( 'archives.notfound_title' ) );
			$c['noindex'] = true;
		}

		if ( $page > 1 && (int) $s::get( 'archives.noindex_paged' ) && 'singular' !== $c['kind'] ) {
			$c['noindex'] = true;
		}
		if ( '' === $c['image'] ) {
			$c['image'] = (string) $s::get( 'social.default_image' );
		}
		self::$ctx = apply_filters( 'moshai_seo_context', $c );
		return self::$ctx;
	}

	private static function paged_permalink( $post, $page ) {
		$link = get_permalink( $post );
		if ( $page > 1 ) {
			$link = '' === get_option( 'permalink_structure' ) ? add_query_arg( 'page', $page, $link ) : trailingslashit( $link ) . user_trailingslashit( (string) $page, 'single_paged' );
		}
		return $link;
	}

	public static function post_image( $post ) {
		$own = MoshAI_SEO_Meta::get( $post->ID, 'og_image' );
		if ( $own ) {
			return $own;
		}
		if ( has_post_thumbnail( $post ) ) {
			return (string) get_the_post_thumbnail_url( $post, 'full' );
		}
		if ( preg_match( '#<img[^>]+src=["\']([^"\']+)["\']#i', MoshAI_SEO_Analyzer::content( $post ), $m ) ) {
			return $m[1];
		}
		return '';
	}

	public static function document_title( $title ) {
		$c = self::context();
		return '' !== $c['title'] ? $c['title'] : $title;
	}

	public static function robots( $robots ) {
		if ( ! get_option( 'blog_public' ) ) {
			return $robots; // "Discourage search engines" is on: WordPress already says noindex, nofollow.
		}
		$c = self::context();
		if ( $c['noindex'] ) {
			unset( $robots['index'], $robots['max-image-preview'], $robots['max-snippet'], $robots['max-video-preview'] );
			$robots['noindex'] = true;
		} else {
			$robots['index']             = true;
			$robots['max-snippet']       = '-1';
			$robots['max-image-preview'] = 'large';
			$robots['max-video-preview'] = '-1';
		}
		$robots[ $c['nofollow'] ? 'nofollow' : 'follow' ] = true;
		if ( $c['nofollow'] ) {
			unset( $robots['follow'] );
		}
		foreach ( $c['robots'] as $r ) {
			$robots[ $r ] = true;
		}
		return $robots;
	}

	/** Attachment pages are thin content: send visitors to the file (like Yoast). */
	public static function attachment_redirect() {
		if ( is_attachment() && (int) MoshAI_SEO_Settings::get( 'archives.attachment_redirect' ) ) {
			$url = wp_get_attachment_url( get_queried_object_id() );
			if ( $url ) {
				wp_safe_redirect( $url, 301 );
				exit;
			}
		}
	}

	/** Every tag for the current request as name => value pairs, also used by the MCP preview. */
	public static function tags() {
		$c    = self::context();
		$s    = 'MoshAI_SEO_Settings';
		$tags = array();
		if ( '' !== $c['desc'] ) {
			$tags[] = array( 'meta', 'name', 'description', $c['desc'] );
		}
		if ( $c['canonical'] && ! $c['noindex'] ) {
			$tags[] = array( 'link', 'rel', 'canonical', $c['canonical'] );
		}
		$post    = $c['post'];
		$term    = $c['term'];
		$og_t    = $post ? MoshAI_SEO_Meta::get( $post->ID, 'og_title' ) : ( $term ? MoshAI_SEO_Meta::term_get( $term->term_id, 'og_title' ) : '' );
		$og_d    = $post ? MoshAI_SEO_Meta::get( $post->ID, 'og_description' ) : ( $term ? MoshAI_SEO_Meta::term_get( $term->term_id, 'og_description' ) : '' );
		$ctx     = array(
			'post' => $post,
			'term' => $term,
		);
		$title   = '' !== $og_t ? MoshAI_SEO_Vars::replace( $og_t, $ctx ) : $c['title'];
		$desc    = '' !== $og_d ? MoshAI_SEO_Vars::replace( $og_d, $ctx ) : $c['desc'];
		$url     = $c['canonical'] ? $c['canonical'] : home_url( add_query_arg( array() ) );
		if ( (int) $s::get( 'social.og_enabled' ) ) {
			$tags[] = array( 'meta', 'property', 'og:locale', get_locale() );
			$tags[] = array( 'meta', 'property', 'og:type', $c['og_type'] );
			$tags[] = array( 'meta', 'property', 'og:title', $title );
			if ( '' !== $desc ) {
				$tags[] = array( 'meta', 'property', 'og:description', $desc );
			}
			$tags[] = array( 'meta', 'property', 'og:url', $url );
			$tags[] = array( 'meta', 'property', 'og:site_name', get_bloginfo( 'name' ) );
			if ( $post && 'article' === $c['og_type'] ) {
				$tags[] = array( 'meta', 'property', 'article:published_time', get_post_time( 'c', true, $post ) );
				$tags[] = array( 'meta', 'property', 'article:modified_time', get_post_modified_time( 'c', true, $post ) );
				if ( $s::get( 'social.facebook' ) ) {
					$tags[] = array( 'meta', 'property', 'article:publisher', $s::get( 'social.facebook' ) );
				}
			}
			if ( $c['image'] ) {
				$tags[] = array( 'meta', 'property', 'og:image', $c['image'] );
				$id     = ( $post && has_post_thumbnail( $post ) && get_the_post_thumbnail_url( $post, 'full' ) === $c['image'] ) ? (int) get_post_thumbnail_id( $post ) : attachment_url_to_postid( $c['image'] );
				$meta   = $id ? wp_get_attachment_metadata( $id ) : null;
				if ( $meta && ! empty( $meta['width'] ) ) {
					$tags[] = array( 'meta', 'property', 'og:image:width', (string) $meta['width'] );
					$tags[] = array( 'meta', 'property', 'og:image:height', (string) $meta['height'] );
					$alt    = (string) get_post_meta( $id, '_wp_attachment_image_alt', true );
					if ( '' !== $alt ) {
						$tags[] = array( 'meta', 'property', 'og:image:alt', $alt );
					}
				}
				$mime = $id ? get_post_mime_type( $id ) : '';
				if ( $mime ) {
					$tags[] = array( 'meta', 'property', 'og:image:type', $mime );
				}
			}
			if ( $s::get( 'social.fb_app_id' ) ) {
				$tags[] = array( 'meta', 'property', 'fb:app_id', $s::get( 'social.fb_app_id' ) );
			}
		}
		if ( (int) $s::get( 'social.twitter_enabled' ) ) {
			$tw_t   = $post ? MoshAI_SEO_Meta::get( $post->ID, 'twitter_title' ) : '';
			$tw_d   = $post ? MoshAI_SEO_Meta::get( $post->ID, 'twitter_description' ) : '';
			$tw_i   = $post ? MoshAI_SEO_Meta::get( $post->ID, 'twitter_image' ) : '';
			$tags[] = array( 'meta', 'name', 'twitter:card', $s::get( 'social.twitter_card' ) );
			if ( '' !== $tw_t || ! (int) $s::get( 'social.og_enabled' ) ) {
				$tags[] = array( 'meta', 'name', 'twitter:title', '' !== $tw_t ? MoshAI_SEO_Vars::replace( $tw_t, $ctx ) : $title );
			}
			if ( '' !== $tw_d || ( ! (int) $s::get( 'social.og_enabled' ) && '' !== $desc ) ) {
				$tags[] = array( 'meta', 'name', 'twitter:description', '' !== $tw_d ? MoshAI_SEO_Vars::replace( $tw_d, $ctx ) : $desc );
			}
			if ( $tw_i || ( ! (int) $s::get( 'social.og_enabled' ) && $c['image'] ) ) {
				$tags[] = array( 'meta', 'name', 'twitter:image', $tw_i ? $tw_i : $c['image'] );
			}
			$site = ltrim( (string) $s::get( 'social.twitter_site' ), '@' );
			if ( '' !== $site ) {
				$tags[] = array( 'meta', 'name', 'twitter:site', '@' . $site );
				$tags[] = array( 'meta', 'name', 'twitter:creator', '@' . $site );
			}
			if ( $post && 'article' === $c['og_type'] ) {
				$words  = count( MoshAI_SEO_Analyzer::words( MoshAI_SEO_Analyzer::plain_text( MoshAI_SEO_Analyzer::content( $post ) ) ) );
				$tags[] = array( 'meta', 'name', 'twitter:label1', __( 'Written by', 'ai-mcp' ) );
				$tags[] = array( 'meta', 'name', 'twitter:data1', get_the_author_meta( 'display_name', $post->post_author ) );
				$tags[] = array( 'meta', 'name', 'twitter:label2', __( 'Est. reading time', 'ai-mcp' ) );
				/* translators: %d minutes */
				$tags[] = array( 'meta', 'name', 'twitter:data2', sprintf( _n( '%d minute', '%d minutes', max( 1, (int) ceil( $words / 200 ) ), 'ai-mcp' ), max( 1, (int) ceil( $words / 200 ) ) ) );
			}
		}
		if ( is_front_page() ) {
			$verify = array(
				'google'    => 'google-site-verification',
				'bing'      => 'msvalidate.01',
				'pinterest' => 'p:domain_verify',
				'yandex'    => 'yandex-verification',
				'baidu'     => 'baidu-site-verification',
			);
			foreach ( $verify as $k => $name ) {
				$code = (string) $s::get( 'verify.' . $k );
				if ( '' !== $code ) {
					$tags[] = array( 'meta', 'name', $name, $code );
				}
			}
		}
		return apply_filters( 'moshai_seo_tags', $tags, $c );
	}

	public static function head() {
		if ( is_feed() || is_robots() ) {
			return;
		}
		echo "\n<!-- SEO by AI MCP (WEBMOSH) -->\n";
		foreach ( self::tags() as $t ) {
			if ( 'link' === $t[0] ) {
				echo '<link rel="' . esc_attr( $t[2] ) . '" href="' . esc_url( $t[3] ) . '" />' . "\n";
			} else {
				echo '<meta ' . esc_attr( $t[1] ) . '="' . esc_attr( $t[2] ) . '" content="' . esc_attr( $t[3] ) . '" />' . "\n";
			}
		}
		MoshAI_SEO_Schema::output();
		echo "<!-- / SEO by AI MCP -->\n\n";
	}
}
