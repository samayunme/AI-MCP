<?php
/**
 * %%variables%% for title and description templates. Same names as Yoast, so imported
 * templates keep working.
 *
 * @package AI_MCP
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class MoshAI_SEO_Vars {

	public static function help() {
		return array(
			'%%title%%'            => 'Post, page or product title',
			'%%sitename%%'         => 'Site title',
			'%%sitedesc%%'         => 'Site tagline',
			'%%sep%%'              => 'Separator from General settings',
			'%%excerpt%%'          => 'Excerpt, or the first 155 characters of the content',
			'%%excerpt_only%%'     => 'Excerpt only (empty when none)',
			'%%category%%'         => 'Categories of the post, comma separated',
			'%%primary_category%%' => 'Primary category',
			'%%tag%%'              => 'Tags of the post',
			'%%term_title%%'       => 'Category, tag or term name on archive pages',
			'%%term_description%%' => 'Term description',
			'%%pt_single%%'        => 'Post type name (singular)',
			'%%pt_plural%%'        => 'Post type name (plural)',
			'%%date%%'             => 'Publish date, or the archive date',
			'%%modified%%'         => 'Last modified date',
			'%%name%%'             => 'Author name',
			'%%page%%'             => '"Page 2 of 4" on paginated pages',
			'%%pagenumber%%'       => 'Current page number',
			'%%pagetotal%%'        => 'Total pages',
			'%%searchphrase%%'     => 'Search terms',
			'%%focuskw%%'          => 'Focus keyphrase',
			'%%currentyear%%'      => 'Current year',
			'%%currentmonth%%'     => 'Current month',
			'%%currentdate%%'      => 'Today\'s date',
			'%%id%%'               => 'Post ID',
		);
	}

	/**
	 * Replaces variables. $ctx: array( 'post' => WP_Post, 'term' => WP_Term, 'user' => WP_User,
	 * 'type' => post type for archives ).
	 */
	public static function replace( $text, $ctx = array() ) {
		$text = (string) $text;
		if ( false === strpos( $text, '%%' ) ) {
			return self::tidy( $text );
		}
		$post = isset( $ctx['post'] ) && $ctx['post'] instanceof WP_Post ? $ctx['post'] : null;
		$term = isset( $ctx['term'] ) && $ctx['term'] instanceof WP_Term ? $ctx['term'] : null;
		$user = isset( $ctx['user'] ) && $ctx['user'] instanceof WP_User ? $ctx['user'] : null;

		$text = preg_replace_callback(
			'/%%([a-z_]+)%%/i',
			function ( $m ) use ( $post, $term, $user, $ctx ) {
				return self::value( strtolower( $m[1] ), $post, $term, $user, $ctx );
			},
			$text
		);
		return self::tidy( $text );
	}

	/** Removes doubled separators and spaces left by empty variables. */
	private static function tidy( $text ) {
		$sep  = preg_quote( self::sep(), '/' );
		$text = preg_replace( '/\s+/u', ' ', $text );
		$text = preg_replace( '/(\s*' . $sep . '\s*){2,}/u', ' ' . self::sep() . ' ', $text );
		$text = preg_replace( '/^\s*' . $sep . '\s*|\s*' . $sep . '\s*$/u', '', $text );
		return trim( wp_strip_all_tags( $text ) );
	}

	public static function sep() {
		$sep = (string) MoshAI_SEO_Settings::get( 'general.sep', '-' );
		return '' === $sep ? '-' : $sep;
	}

	private static function value( $name, $post, $term, $user, $ctx ) {
		global $wp_query;
		switch ( $name ) {
			case 'sitename':
				return get_bloginfo( 'name' );
			case 'sitedesc':
				return get_bloginfo( 'description' );
			case 'sep':
				return self::sep();
			case 'title':
				return $post ? get_the_title( $post ) : ( $term ? $term->name : '' );
			case 'excerpt':
				return $post ? self::excerpt( $post ) : '';
			case 'excerpt_only':
				return $post ? wp_strip_all_tags( $post->post_excerpt ) : '';
			case 'category':
				if ( $post ) {
					$names = wp_get_post_categories( $post->ID, array( 'fields' => 'names' ) );
					return is_array( $names ) ? implode( ', ', $names ) : '';
				}
				return $term ? $term->name : '';
			case 'primary_category':
				if ( $post ) {
					$t = MoshAI_SEO_Meta::primary_term( $post );
					return $t ? $t->name : '';
				}
				return '';
			case 'tag':
				if ( $post ) {
					$names = wp_get_post_tags( $post->ID, array( 'fields' => 'names' ) );
					return is_array( $names ) ? implode( ', ', $names ) : '';
				}
				return $term ? $term->name : '';
			case 'term_title':
				return $term ? $term->name : '';
			case 'term_description':
			case 'category_description':
			case 'tag_description':
				return $term ? wp_strip_all_tags( term_description( $term ) ) : '';
			case 'pt_single':
			case 'pt_plural':
				$type = $post ? $post->post_type : ( isset( $ctx['type'] ) ? $ctx['type'] : '' );
				$obj  = $type ? get_post_type_object( $type ) : null;
				if ( ! $obj ) {
					return '';
				}
				return 'pt_single' === $name ? $obj->labels->singular_name : $obj->labels->name;
			case 'date':
				if ( $post ) {
					return get_the_date( '', $post );
				}
				if ( is_date() ) {
					if ( is_day() ) {
						return get_the_date();
					}
					return is_month() ? get_the_date( 'F Y' ) : get_the_date( 'Y' );
				}
				return '';
			case 'modified':
				return $post ? get_the_modified_date( '', $post ) : '';
			case 'name':
				if ( $user ) {
					return $user->display_name;
				}
				return $post ? get_the_author_meta( 'display_name', $post->post_author ) : '';
			case 'page':
				$page  = self::page_number();
				$total = isset( $wp_query->max_num_pages ) ? (int) $wp_query->max_num_pages : 1;
				if ( $post && is_singular() ) {
					$total = substr_count( $post->post_content, '<!--nextpage-->' ) + 1;
				}
				/* translators: 1: current page, 2: total pages */
				return $page > 1 ? self::sep() . ' ' . sprintf( __( 'Page %1$d of %2$d', 'ai-mcp' ), $page, max( $page, $total ) ) : '';
			case 'pagenumber':
				return (string) self::page_number();
			case 'pagetotal':
				return isset( $wp_query->max_num_pages ) ? (string) max( 1, (int) $wp_query->max_num_pages ) : '1';
			case 'searchphrase':
				return esc_html( get_search_query() );
			case 'focuskw':
				return $post ? MoshAI_SEO_Meta::get( $post->ID, 'focus_keyphrase' ) : '';
			case 'currentyear':
				return wp_date( 'Y' );
			case 'currentmonth':
				return wp_date( 'F' );
			case 'currentdate':
				return wp_date( get_option( 'date_format' ) );
			case 'id':
				return $post ? (string) $post->ID : '';
		}
		return '';
	}

	public static function page_number() {
		$paged = (int) get_query_var( 'paged' );
		$page  = (int) get_query_var( 'page' );
		return max( 1, $paged, $page );
	}

	/** Excerpt or the start of the content, about 155 characters, on a word boundary. */
	public static function excerpt( $post, $length = 155 ) {
		$text = trim( wp_strip_all_tags( $post->post_excerpt ) );
		if ( '' === $text ) {
			$text = MoshAI_SEO_Analyzer::plain_text( MoshAI_SEO_Analyzer::content( $post ) );
		}
		$text = trim( preg_replace( '/\s+/u', ' ', $text ) );
		if ( mb_strlen( $text ) <= $length ) {
			return $text;
		}
		$cut   = mb_substr( $text, 0, $length );
		$space = mb_strrpos( $cut, ' ' );
		return rtrim( false !== $space && $space > 60 ? mb_substr( $cut, 0, $space ) : $cut, ' ,.;:' ) . '…';
	}
}
