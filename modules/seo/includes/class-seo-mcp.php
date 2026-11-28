<?php
/**
 * MCP tools for the SEO module. An AI can audit the whole site, analyze any page, fix titles,
 * descriptions, keyphrases, robots and social tags in bulk, change any SEO setting, manage
 * redirects and preview exactly what search engines will see.
 *
 * @package AI_MCP
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class MoshAI_SEO_MCP {

	public static function init() {
		add_filter( 'moshai_mcp_tools', array( __CLASS__, 'tools' ), 15 );
		add_filter( 'moshai_mcp_call', array( __CLASS__, 'call' ), 10, 3 );
		add_filter( 'moshai_activity_summary', array( __CLASS__, 'summary' ), 10, 4 );
	}

	/** One readable line per SEO change in AI MCP > Activity. */
	public static function summary( $s, $tool, $a, $r ) {
		if ( '' !== $s || 0 !== strpos( $tool, 'seo_' ) ) {
			return $s;
		}
		$title = function ( $id ) {
			$p = get_post( (int) $id );
			return $p ? '"' . wp_html_excerpt( get_the_title( $p ), 60, '…' ) . '"' : '#' . (int) $id;
		};
		switch ( $tool ) {
			case 'seo_update_post':
				return 'Updated SEO of ' . $title( isset( $a['id'] ) ? $a['id'] : 0 ) . ': ' . implode( ', ', array_keys( (array) ( isset( $a['fields'] ) ? $a['fields'] : array() ) ) );
			case 'seo_bulk_update_posts':
				return 'Updated SEO of ' . count( (array) ( isset( $a['items'] ) ? $a['items'] : array() ) ) . ' pages';
			case 'seo_update_settings':
				return 'Changed SEO settings: ' . wp_html_excerpt( implode( ', ', array_keys( (array) ( isset( $a['changes'] ) ? $a['changes'] : array() ) ) ), 120, '…' );
			case 'seo_update_term':
				return 'Updated SEO of term #' . (int) ( isset( $a['id'] ) ? $a['id'] : 0 );
			case 'seo_create_redirect':
				return 'Redirect ' . ( isset( $a['source'] ) ? $a['source'] : '' ) . ' → ' . ( isset( $a['target'] ) && '' !== $a['target'] ? $a['target'] : (string) ( isset( $a['code'] ) ? $a['code'] : 410 ) );
			case 'seo_delete_redirect':
				return 'Deleted redirect #' . (int) ( isset( $a['id'] ) ? $a['id'] : 0 );
			case 'seo_import':
				return 'Imported SEO data from ' . ( isset( $a['from'] ) ? $a['from'] : '' );
		}
		return $s;
	}

	private static function field_props() {
		$props = array();
		foreach ( MoshAI_SEO_Meta::fields() as $f => $def ) {
			$type = 'bool' === $def[0] ? 'boolean' : ( 'term' === $def[0] ? 'integer' : 'string' );
			$desc = $def[1] . ( $def[2] ? '. ' . $def[2] : '' );
			if ( 'robots' === $def[0] ) {
				$desc = 'Search visibility: "" = content type default, "index", or "noindex".';
			} elseif ( 0 === strpos( $def[0], 'choice:' ) ) {
				$choices = MoshAI_SEO_Settings::choices();
				$desc   .= ' One of: ' . implode( ', ', $choices[ substr( $def[0], 7 ) ] ) . ' ("" = default).';
			}
			$props[ $f ] = array(
				'type'        => $type,
				'description' => $desc,
			);
		}
		return $props;
	}

	public static function tools( $tools ) {
		$t      = 'MoshAI_MCP';
		$id     = array(
			'type'        => 'integer',
			'description' => 'Post, page or product ID',
		);
		$fields = array(
			'type'        => 'object',
			'description' => 'SEO fields to change. Empty string clears a field (back to the template).',
			'properties'  => self::field_props(),
		);
		$tools[] = $t::tool( 'seo_get_settings', 'Read every SEO setting (title templates per content type, social, schema, sitemaps, breadcrumbs, robots.txt, verification, cleanup...) with allowed choices and %%variables%%. Optional section, e.g. "types" or "social".', array( 'section' => array( 'type' => 'string' ) ) );
		$tools[] = $t::tool(
			'seo_update_settings',
			'Change SEO settings by dot path. Example: {"changes": {"types.post.title": "%%title%% %%sep%% %%sitename%%", "general.home_desc": "...", "social.default_image": "https://...", "sitemap.exclude_types": ["product"], "robots.extra": "Disallow: /private/"}}. Read seo_get_settings first for paths.',
			array(
				'changes' => array(
					'type'        => 'object',
					'description' => 'path => new value',
				),
			),
			array( 'changes' )
		);
		$tools[] = $t::tool(
			'seo_audit_site',
			'Audit the whole site. action "summary" (default): counts of content missing descriptions, keyphrases, low scores, noindex, plus site health. "list": content with its SEO state, filter with problem. "rescore": re-analyze content in batches of 25 (repeat with next_offset).',
			array(
				'action'    => array(
					'type' => 'string',
					'enum' => array( 'summary', 'list', 'rescore' ),
				),
				'post_type' => array( 'type' => 'string' ),
				'problem'   => array(
					'type' => 'string',
					'enum' => MoshAI_SEO_Audit::PROBLEMS,
				),
				'search'    => array( 'type' => 'string' ),
				'page'      => array( 'type' => 'integer' ),
				'per_page'  => array( 'type' => 'integer' ),
				'offset'    => array( 'type' => 'integer' ),
			)
		);
		$tools[] = $t::tool( 'seo_get_post', 'SEO state of one post, page or product: saved fields, the title and description Google sees, scores and failing checks.', array( 'id' => $id ), array( 'id' ) );
		$tools[] = $t::tool(
			'seo_analyze_post',
			'Full keyphrase and readability analysis of a page with every check and a 0-100 score. Pass focus_keyphrase to test a different keyphrase without saving.',
			array(
				'id'              => $id,
				'focus_keyphrase' => array( 'type' => 'string' ),
			),
			array( 'id' )
		);
		$tools[] = $t::tool(
			'seo_update_post',
			'Set SEO fields of one post, page or product: title, description, focus_keyphrase, canonical, robots_index, og_*, twitter_*, schema... Returns the new scores.',
			array(
				'id'     => $id,
				'fields' => $fields,
			),
			array( 'id', 'fields' )
		);
		$tools[] = $t::tool(
			'seo_bulk_update_posts',
			'Set SEO fields on up to 50 posts in one call: {"items": [{"id": 12, "fields": {"description": "..."}}, ...]}.',
			array(
				'items' => array(
					'type'  => 'array',
					'items' => array(
						'type'       => 'object',
						'properties' => array(
							'id'     => $id,
							'fields' => $fields,
						),
					),
				),
			),
			array( 'items' )
		);
		$tools[] = $t::tool(
			'seo_get_term',
			'SEO fields of a category, tag or product category.',
			array(
				'taxonomy' => array( 'type' => 'string' ),
				'id'       => array( 'type' => 'integer' ),
			),
			array( 'taxonomy', 'id' )
		);
		$tools[] = $t::tool(
			'seo_update_term',
			'Set SEO fields of a term: title, description, focus_keyphrase, canonical, robots_index, nofollow, breadcrumb_title, og_title, og_description, og_image.',
			array(
				'taxonomy' => array( 'type' => 'string' ),
				'id'       => array( 'type' => 'integer' ),
				'fields'   => array( 'type' => 'object' ),
			),
			array( 'taxonomy', 'id', 'fields' )
		);
		$tools[] = $t::tool(
			'seo_preview_head',
			'Exactly what search engines and social networks get for a page: title, meta tags, canonical, robots and the JSON-LD schema. Give id, or url (homepage too).',
			array(
				'id'  => $id,
				'url' => array( 'type' => 'string' ),
			)
		);
		$tools[] = $t::tool(
			'seo_list_redirects',
			'List redirects with hit counts.',
			array(
				'search' => array( 'type' => 'string' ),
				'page'   => array( 'type' => 'integer' ),
			)
		);
		$tools[] = $t::tool(
			'seo_create_redirect',
			'Create or update a redirect. source: old path like /old-page/ (or a regex with regex=true). target: new path or full URL (empty for 410/451). code: 301 (default), 302, 307, 308, 410 gone, 451.',
			array(
				'source' => array( 'type' => 'string' ),
				'target' => array( 'type' => 'string' ),
				'code'   => array( 'type' => 'integer' ),
				'regex'  => array( 'type' => 'boolean' ),
			),
			array( 'source' )
		);
		$tools[] = $t::tool( 'seo_delete_redirect', 'Delete a redirect by id.', array( 'id' => array( 'type' => 'integer' ) ), array( 'id' ) );
		$tools[] = $t::tool( 'seo_list_404s', 'Broken URLs visitors hit (404 monitor), most hits first. Turn logging on with seo_update_settings {"redirects.log_404": 1}. Fix them with seo_create_redirect.', array( 'page' => array( 'type' => 'integer' ) ) );
		$tools[] = $t::tool(
			'seo_import',
			'Copy titles, descriptions, keyphrases and social fields from Yoast SEO or Rank Math (200 posts per call, repeat with next_offset). Existing values stay unless overwrite.',
			array(
				'from'      => array(
					'type' => 'string',
					'enum' => array( 'yoast', 'rankmath' ),
				),
				'offset'    => array( 'type' => 'integer' ),
				'overwrite' => array( 'type' => 'boolean' ),
			),
			array( 'from' )
		);
		return $tools;
	}

	private static function arg( $a, $k, $default = null ) {
		return isset( $a[ $k ] ) ? $a[ $k ] : $default;
	}

	private static function post( $a ) {
		$post = get_post( (int) self::arg( $a, 'id', 0 ) );
		return $post ? $post : new WP_Error( 'moshai_seo_post', 'Post not found.' );
	}

	private static function post_state( $post ) {
		$analysis = MoshAI_SEO_Analyzer::analyze( $post );
		$failing  = array();
		foreach ( array( 'seo', 'readability' ) as $k ) {
			foreach ( $analysis[ $k ]['checks'] as $c ) {
				if ( 'good' !== $c['status'] ) {
					$failing[] = $k . ': ' . $c['text'];
				}
			}
		}
		update_post_meta( $post->ID, MoshAI_SEO_Meta::PREFIX . 'score', $analysis['seo']['score'] );
		update_post_meta( $post->ID, MoshAI_SEO_Meta::PREFIX . 'read_score', $analysis['readability']['score'] );
		return array_merge(
			MoshAI_SEO_Audit::row( $post ),
			array(
				'fields'            => MoshAI_SEO_Meta::all( $post->ID ),
				'seo_score'         => $analysis['seo']['score'],
				'readability'       => $analysis['readability']['score'],
				'to_improve'        => $failing,
				'stats'             => $analysis['stats'],
			)
		);
	}

	private static function update_post( $a ) {
		$post = self::post( $a );
		if ( is_wp_error( $post ) ) {
			return $post;
		}
		$fields = self::arg( $a, 'fields', array() );
		if ( ! is_array( $fields ) || ! $fields ) {
			return new WP_Error( 'moshai_seo_fields', 'fields is required, e.g. {"description": "..."}' );
		}
		$res = MoshAI_SEO_Meta::update( $post->ID, $fields );
		if ( is_wp_error( $res ) ) {
			return $res;
		}
		$row = MoshAI_SEO_Audit::row( get_post( $post->ID ) );
		return array_merge(
			$row,
			array(
				'changed'  => array_keys( $res ),
				'edit_url' => get_edit_post_link( $post->ID, 'raw' ),
			)
		);
	}

	public static function call( $result, $name, $a ) {
		if ( null !== $result || 0 !== strpos( $name, 'seo_' ) ) {
			return $result;
		}
		$a = is_array( $a ) ? $a : array();
		switch ( $name ) {
			case 'seo_get_settings':
				return MoshAI_SEO_Settings::describe( sanitize_key( (string) self::arg( $a, 'section', '' ) ) );
			case 'seo_update_settings':
				$changes = self::arg( $a, 'changes' );
				if ( is_string( $changes ) ) {
					$changes = json_decode( $changes, true );
				}
				return MoshAI_SEO_Settings::update( (array) $changes );
			case 'seo_audit_site':
				$action = self::arg( $a, 'action', 'summary' );
				$type   = sanitize_key( (string) self::arg( $a, 'post_type', '' ) );
				if ( 'rescore' === $action ) {
					return MoshAI_SEO_Audit::rescore( (int) self::arg( $a, 'offset', 0 ), 25, $type );
				}
				if ( 'list' === $action ) {
					return MoshAI_SEO_Audit::report( $type, sanitize_key( (string) self::arg( $a, 'problem', '' ) ), (int) self::arg( $a, 'page', 1 ), (int) self::arg( $a, 'per_page', 20 ), sanitize_text_field( (string) self::arg( $a, 'search', '' ) ) );
				}
				return array(
					'content'  => MoshAI_SEO_Audit::summary( $type ),
					'health'   => MoshAI_SEO_Audit::health(),
					'conflict' => MoshAI_SEO::conflict(),
					'sitemap'  => (int) MoshAI_SEO_Settings::get( 'sitemap.enabled' ) ? MoshAI_SEO_Sitemaps::url() : '',
					'tip'      => 'Use action "list" with a problem (e.g. missing_description) to get the pages, then seo_bulk_update_posts to fix them. Run action "rescore" if many pages are unscored.',
				);
			case 'seo_get_post':
				$post = self::post( $a );
				return is_wp_error( $post ) ? $post : self::post_state( $post );
			case 'seo_analyze_post':
				$post = self::post( $a );
				if ( is_wp_error( $post ) ) {
					return $post;
				}
				$over = array();
				if ( null !== self::arg( $a, 'focus_keyphrase' ) ) {
					$over['focus_keyphrase'] = sanitize_text_field( (string) self::arg( $a, 'focus_keyphrase' ) );
				}
				return MoshAI_SEO_Analyzer::analyze( $post, $over );
			case 'seo_update_post':
				return self::update_post( $a );
			case 'seo_bulk_update_posts':
				$items = self::arg( $a, 'items', array() );
				if ( ! is_array( $items ) || ! $items ) {
					return new WP_Error( 'moshai_seo_items', 'items is required.' );
				}
				if ( count( $items ) > 50 ) {
					return new WP_Error( 'moshai_seo_items', 'At most 50 items per call.' );
				}
				$out = array();
				foreach ( $items as $item ) {
					$res   = self::update_post( is_array( $item ) ? $item : array() );
					$out[] = is_wp_error( $res ) ? array(
						'id'    => isset( $item['id'] ) ? (int) $item['id'] : 0,
						'error' => $res->get_error_message(),
					) : array(
						'id'        => $res['id'],
						'changed'   => $res['changed'],
						'seo_score' => $res['seo_score'],
					);
				}
				return array(
					'updated' => count(
						array_filter(
							$out,
							function ( $r ) {
								return ! isset( $r['error'] );
							}
						)
					),
					'results' => $out,
				);
			case 'seo_get_term':
			case 'seo_update_term':
				$term = get_term( (int) self::arg( $a, 'id', 0 ), sanitize_key( (string) self::arg( $a, 'taxonomy', '' ) ) );
				if ( ! $term || is_wp_error( $term ) ) {
					return new WP_Error( 'moshai_seo_term', 'Term not found. Check taxonomy and id (wp_list_terms).' );
				}
				if ( 'seo_update_term' === $name ) {
					$res = MoshAI_SEO_Meta::term_update( $term->term_id, (array) self::arg( $a, 'fields', array() ) );
					if ( is_wp_error( $res ) ) {
						return $res;
					}
				}
				$link = get_term_link( $term );
				return array(
					'id'       => $term->term_id,
					'taxonomy' => $term->taxonomy,
					'name'     => $term->name,
					'url'      => is_wp_error( $link ) ? '' : $link,
					'fields'   => MoshAI_SEO_Meta::term_all( $term->term_id ),
					'noindex'  => MoshAI_SEO_Meta::is_term_noindex( $term ),
				);
			case 'seo_preview_head':
				return self::preview( $a );
			case 'seo_list_redirects':
				return MoshAI_SEO_Redirects::query( sanitize_text_field( (string) self::arg( $a, 'search', '' ) ), (int) self::arg( $a, 'page', 1 ) );
			case 'seo_create_redirect':
				return MoshAI_SEO_Redirects::create( (string) self::arg( $a, 'source', '' ), (string) self::arg( $a, 'target', '' ), (int) self::arg( $a, 'code', 301 ), (bool) self::arg( $a, 'regex', false ), 'Created by AI' );
			case 'seo_delete_redirect':
				return MoshAI_SEO_Redirects::delete( (int) self::arg( $a, 'id', 0 ) );
			case 'seo_list_404s':
				return MoshAI_SEO_Redirects::query_404( (int) self::arg( $a, 'page', 1 ) );
			case 'seo_import':
				return MoshAI_SEO_Tools::import( (string) self::arg( $a, 'from', '' ), (int) self::arg( $a, 'offset', 0 ), (bool) self::arg( $a, 'overwrite', false ) );
		}
		return $result;
	}

	/** Builds the head for a page by running a real query for it, then restores the request. */
	private static function preview( $a ) {
		$url = trim( (string) self::arg( $a, 'url', '' ) );
		$id  = (int) self::arg( $a, 'id', 0 );
		if ( ! $id && $url ) {
			$id = url_to_postid( $url );
			if ( ! $id && untrailingslashit( $url ) !== untrailingslashit( home_url() ) ) {
				return new WP_Error( 'moshai_seo_url', 'Could not match that URL to a post or page. Pass the id instead.' );
			}
		}
		global $wp_query, $wp_the_query, $post;
		$saved = array( $wp_query, $wp_the_query, $post );
		if ( $id ) {
			$target = get_post( $id );
			if ( ! $target ) {
				return new WP_Error( 'moshai_seo_post', 'Post not found.' );
			}
			$args = 'page' === $target->post_type ? array( 'page_id' => $id ) : array(
				'p'         => $id,
				'post_type' => $target->post_type,
			);
		} else {
			$front = (int) get_option( 'page_on_front' );
			$args  = $front && 'page' === get_option( 'show_on_front' ) ? array( 'page_id' => $front ) : array();
		}
		$args['post_status'] = 'any';
		$q                   = new WP_Query( $args );
		if ( ! $id && ! isset( $args['page_id'] ) ) {
			$q->is_home = true;
		}
		$wp_query     = $q; // phpcs:ignore WordPress.WP.GlobalVariablesOverride
		$wp_the_query = $q; // phpcs:ignore WordPress.WP.GlobalVariablesOverride
		if ( $q->posts ) {
			$post = $q->posts[0]; // phpcs:ignore WordPress.WP.GlobalVariablesOverride
		}
		MoshAI_SEO_Head::reset();
		MoshAI_SEO_Breadcrumbs::reset();
		$c    = MoshAI_SEO_Head::context();
		$tags = array();
		foreach ( MoshAI_SEO_Head::tags() as $t ) {
			$tags[] = array(
				'tag'   => $t[0],
				$t[1]   => $t[2],
				'value' => $t[3],
			);
		}
		$robots = MoshAI_SEO_Head::robots( array() );
		$graph  = MoshAI_SEO_Schema::graph();
		$crumbs = MoshAI_SEO_Breadcrumbs::trail();
		list( $wp_query, $wp_the_query, $post ) = $saved; // phpcs:ignore WordPress.WP.GlobalVariablesOverride
		MoshAI_SEO_Head::reset();
		MoshAI_SEO_Breadcrumbs::reset();
		return array(
			'title'       => $c['title'],
			'robots'      => implode( ', ', array_keys( array_filter( $robots ) ) ),
			'tags'        => $tags,
			'breadcrumbs' => $crumbs,
			'schema'      => $graph,
			'conflict'    => MoshAI_SEO::conflict() ? MoshAI_SEO::conflict() . ' is active: these tags are NOT printed until it is deactivated.' : '',
		);
	}
}
