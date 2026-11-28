<?php
/**
 * Site audit: health checks for the whole site, a content report with problem filters,
 * and bulk re-scoring. Shared by the SEO dashboard and the MCP tools.
 *
 * @package AI_MCP
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class MoshAI_SEO_Audit {

	const PROBLEMS = array( 'missing_description', 'missing_keyphrase', 'missing_title', 'low_score', 'low_readability', 'unscored', 'noindex', 'cornerstone' );

	private static function item( $id, $status, $text, $fix = '' ) {
		return array(
			'id'     => $id,
			'status' => $status,
			'text'   => $text,
			'fix'    => $fix,
		);
	}

	/** Site-wide checks, worst first. */
	public static function health() {
		$s   = 'MoshAI_SEO_Settings';
		$out = array();
		if ( ! get_option( 'blog_public' ) ) {
			$out[] = self::item( 'blog_public', 'bad', __( 'Search engines are blocked: Settings > Reading > "Discourage search engines" is ticked. Nothing on this site can rank.', 'ai-mcp' ), admin_url( 'options-reading.php' ) );
		}
		$conflict = MoshAI_SEO::conflict();
		if ( $conflict ) {
			/* translators: %s plugin name */
			$out[] = self::item( 'conflict', 'bad', sprintf( __( '%s is active, so AI MCP SEO does not print any tags. Import its data under Tools, then deactivate it.', 'ai-mcp' ), $conflict ), 'tools' );
		}
		if ( '' === get_option( 'permalink_structure' ) ) {
			$out[] = self::item( 'permalinks', 'bad', __( 'Plain permalinks (?p=123). Switch to "Post name" in Settings > Permalinks.', 'ai-mcp' ), admin_url( 'options-permalink.php' ) );
		}
		if ( 0 !== strpos( home_url(), 'https://' ) ) {
			$out[] = self::item( 'https', 'bad', __( 'The site does not use HTTPS. Google ranks secure sites higher.', 'ai-mcp' ) );
		}
		$tagline = get_bloginfo( 'description' );
		if ( '' === $tagline || 'Just another WordPress site' === $tagline ) {
			$out[] = self::item( 'tagline', 'ok', __( 'The site tagline is empty or the WordPress default. It shows in titles and schema.', 'ai-mcp' ), admin_url( 'options-general.php' ) );
		}
		if ( '' === trim( (string) $s::get( 'general.home_desc' ) ) && ! ( get_option( 'page_on_front' ) && MoshAI_SEO_Meta::get( (int) get_option( 'page_on_front' ), 'description' ) ) ) {
			$out[] = self::item( 'home_desc', 'bad', __( 'The homepage has no meta description.', 'ai-mcp' ), 'appearance' );
		}
		if ( ! (int) $s::get( 'sitemap.enabled' ) ) {
			$out[] = self::item( 'sitemap', 'bad', __( 'XML sitemaps are off. Google finds new pages much faster with them.', 'ai-mcp' ), 'sitemaps' );
		}
		if ( 'organization' === $s::get( 'knowledge.type' ) && ! $s::get( 'knowledge.logo' ) && ! get_theme_mod( 'custom_logo' ) ) {
			$out[] = self::item( 'logo', 'ok', __( 'No organization logo set for Google\'s knowledge panel.', 'ai-mcp' ), 'general' );
		}
		if ( ! $s::get( 'social.default_image' ) ) {
			$out[] = self::item( 'og_image', 'ok', __( 'No default social image. Pages without a featured image share with no picture.', 'ai-mcp' ), 'social' );
		}
		if ( ! $s::get( 'verify.google' ) ) {
			$out[] = self::item( 'google_verify', 'ok', __( 'Google Search Console is not verified here (optional if you verified by DNS).', 'ai-mcp' ), 'tools' );
		}
		if ( ! $out ) {
			$out[] = self::item( 'all_good', 'good', __( 'No site-wide problems found.', 'ai-mcp' ) );
		}
		$rank = array(
			'bad'  => 0,
			'ok'   => 1,
			'good' => 2,
		);
		usort(
			$out,
			function ( $a, $b ) use ( $rank ) {
				return $rank[ $a['status'] ] - $rank[ $b['status'] ];
			}
		);
		return $out;
	}

	private static function types( $type = '' ) {
		$all = array();
		foreach ( array_keys( MoshAI_SEO::post_types() ) as $t ) {
			if ( (int) MoshAI_SEO_Meta::type_setting( $t, 'index' ) ) {
				$all[] = $t;
			}
		}
		if ( $type ) {
			return in_array( $type, $all, true ) ? array( $type ) : array( $type );
		}
		return $all;
	}

	private static function problem_query( $problem ) {
		$p = MoshAI_SEO_Meta::PREFIX;
		switch ( $problem ) {
			case 'missing_description':
				return array(
					'relation' => 'AND',
					array(
						'key'     => $p . 'description',
						'compare' => 'NOT EXISTS',
					),
					array(
						'key'     => '_moshai_meta_description',
						'compare' => 'NOT EXISTS',
					),
				);
			case 'missing_keyphrase':
				return array(
					array(
						'key'     => $p . 'focus_keyphrase',
						'compare' => 'NOT EXISTS',
					),
				);
			case 'missing_title':
				return array(
					array(
						'key'     => $p . 'title',
						'compare' => 'NOT EXISTS',
					),
				);
			case 'low_score':
			case 'low_readability':
				return array(
					array(
						'key'     => $p . ( 'low_score' === $problem ? 'score' : 'read_score' ),
						'value'   => 40,
						'compare' => '<',
						'type'    => 'NUMERIC',
					),
				);
			case 'unscored':
				return array(
					array(
						'key'     => $p . 'score',
						'compare' => 'NOT EXISTS',
					),
				);
			case 'noindex':
				return array(
					array(
						'key'   => $p . 'robots_index',
						'value' => 'noindex',
					),
				);
			case 'cornerstone':
				return array(
					array(
						'key'   => $p . 'cornerstone',
						'value' => '1',
					),
				);
		}
		return array();
	}

	private static function count_query( $types, $meta ) {
		$q = new WP_Query(
			array(
				'post_type'      => $types,
				'post_status'    => 'publish',
				'posts_per_page' => 1,
				'fields'         => 'ids',
				'meta_query'     => $meta, // phpcs:ignore WordPress.DB.SlowDBQuery
			)
		);
		return (int) $q->found_posts;
	}

	/** Counts per problem for one or all content types. */
	public static function summary( $type = '' ) {
		$types = self::types( $type );
		$out   = array(
			'published' => self::count_query( $types, array() ),
		);
		foreach ( self::PROBLEMS as $p ) {
			$out[ $p ] = self::count_query( $types, self::problem_query( $p ) );
		}
		global $wpdb;
		$avg = $wpdb->get_row( $wpdb->prepare( "SELECT AVG(CAST(m.meta_value AS UNSIGNED)) seo FROM $wpdb->postmeta m INNER JOIN $wpdb->posts p ON p.ID = m.post_id WHERE m.meta_key = %s AND p.post_status = 'publish'", MoshAI_SEO_Meta::PREFIX . 'score' ), ARRAY_A ); // phpcs:ignore
		$out['average_score'] = $avg && null !== $avg['seo'] ? (int) round( (float) $avg['seo'] ) : null;
		$out['redirects']     = MoshAI_SEO_Redirects::count();
		return $out;
	}

	/** Content report: published content with its SEO state, filtered by a problem. */
	public static function report( $type = '', $problem = '', $page = 1, $per = 20, $search = '' ) {
		$args = array(
			'post_type'      => self::types( $type ),
			'post_status'    => 'publish',
			'posts_per_page' => min( 100, max( 1, (int) $per ) ),
			'paged'          => max( 1, (int) $page ),
			'orderby'        => 'date',
			'order'          => 'DESC',
		);
		if ( $problem ) {
			if ( ! in_array( $problem, self::PROBLEMS, true ) ) {
				return new WP_Error( 'moshai_seo_problem', 'problem must be one of: ' . implode( ', ', self::PROBLEMS ) );
			}
			$args['meta_query'] = self::problem_query( $problem ); // phpcs:ignore WordPress.DB.SlowDBQuery
		}
		if ( '' !== $search ) {
			$args['s'] = $search;
		}
		$q     = new WP_Query( $args );
		$items = array();
		foreach ( $q->posts as $post ) {
			$items[] = self::row( $post );
		}
		return array(
			'total' => (int) $q->found_posts,
			'pages' => (int) $q->max_num_pages,
			'page'  => max( 1, (int) $page ),
			'items' => $items,
		);
	}

	public static function row( $post ) {
		$score = get_post_meta( $post->ID, MoshAI_SEO_Meta::PREFIX . 'score', true );
		$read  = get_post_meta( $post->ID, MoshAI_SEO_Meta::PREFIX . 'read_score', true );
		return array(
			'id'               => $post->ID,
			'type'             => $post->post_type,
			'title'            => get_the_title( $post ),
			'url'              => get_permalink( $post ),
			'edit'             => get_edit_post_link( $post->ID, 'raw' ),
			'seo_title'        => MoshAI_SEO_Meta::get( $post->ID, 'title' ),
			'description'      => MoshAI_SEO_Meta::get( $post->ID, 'description' ),
			'focus_keyphrase'  => MoshAI_SEO_Meta::get( $post->ID, 'focus_keyphrase' ),
			'resolved_title'   => MoshAI_SEO_Meta::resolved_title( $post ),
			'resolved_description' => MoshAI_SEO_Meta::resolved_description( $post ),
			'seo_score'        => '' === $score ? null : (int) $score,
			'readability'      => '' === $read ? null : (int) $read,
			'noindex'          => MoshAI_SEO_Meta::is_noindex( $post ),
			'cornerstone'      => '1' === MoshAI_SEO_Meta::get( $post->ID, 'cornerstone' ),
		);
	}

	/** Re-analyzes published content in batches. Returns the next offset or null when finished. */
	public static function rescore( $offset = 0, $limit = 25, $type = '' ) {
		$ids = get_posts(
			array(
				'post_type'      => self::types( $type ),
				'post_status'    => 'publish',
				'posts_per_page' => min( 50, max( 1, (int) $limit ) ),
				'offset'         => max( 0, (int) $offset ),
				'orderby'        => 'ID',
				'order'          => 'ASC',
				'fields'         => 'ids',
			)
		);
		foreach ( $ids as $id ) {
			MoshAI_SEO_Meta::rescore( $id );
		}
		$total = self::count_query( self::types( $type ), array() );
		$next  = (int) $offset + count( $ids );
		return array(
			'scored'      => count( $ids ),
			'total'       => $total,
			'next_offset' => $next < $total && $ids ? $next : null,
		);
	}
}
