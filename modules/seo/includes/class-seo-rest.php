<?php
/**
 * REST routes for the SEO dashboard tab and the post meta box (ai-mcp/v1/seo/...).
 * The MCP bridge cannot call these: AIs use the seo_* MCP tools, which have their own checks.
 *
 * @package AI_MCP
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class MoshAI_SEO_REST {

	public static function init() {
		add_action( 'rest_api_init', array( __CLASS__, 'routes' ) );
	}

	public static function manage() {
		return current_user_can( 'manage_options' );
	}

	public static function edit() {
		return current_user_can( 'edit_posts' );
	}

	public static function routes() {
		$routes = array(
			'settings'         => array( 'POST', 'settings', 'manage' ),
			'analyze'          => array( 'POST', 'analyze', 'edit' ),
			'post'             => array( 'POST', 'post', 'edit' ),
			'report'           => array( 'GET', 'report', 'manage' ),
			'rescore'          => array( 'POST', 'rescore', 'manage' ),
			'redirects'        => array( 'GET,POST', 'redirects', 'manage' ),
			'redirects/delete' => array( 'POST', 'redirect_delete', 'manage' ),
			'404'              => array( 'GET', 'log_404', 'manage' ),
			'404/clear'        => array( 'POST', 'clear_404', 'manage' ),
			'import'           => array( 'POST', 'import', 'manage' ),
			'ai'               => array( 'POST', 'ai', 'edit' ),
		);
		foreach ( $routes as $path => $def ) {
			register_rest_route(
				'ai-mcp/v1',
				'/seo/' . $path,
				array(
					'methods'             => $def[0],
					'callback'            => array( __CLASS__, $def[1] ),
					'permission_callback' => array( __CLASS__, $def[2] ),
				)
			);
		}
	}

	private static function out( $res ) {
		return is_wp_error( $res ) ? new WP_Error( $res->get_error_code(), $res->get_error_message(), array( 'status' => 400 ) ) : rest_ensure_response( $res );
	}

	public static function settings( WP_REST_Request $r ) {
		return self::out( MoshAI_SEO_Settings::update( (array) $r->get_param( 'changes' ) ) );
	}

	private static function editable_post( $id ) {
		$post = get_post( (int) $id );
		if ( ! $post ) {
			return new WP_Error( 'moshai_seo_post', __( 'Post not found.', 'ai-mcp' ) );
		}
		if ( ! current_user_can( 'edit_post', $post->ID ) ) {
			return new WP_Error( 'moshai_seo_post', __( 'You cannot edit this post.', 'ai-mcp' ) );
		}
		return $post;
	}

	public static function analyze( WP_REST_Request $r ) {
		$post = self::editable_post( $r->get_param( 'post_id' ) );
		if ( is_wp_error( $post ) ) {
			return self::out( $post );
		}
		$over = array();
		foreach ( array( 'title', 'description', 'focus_keyphrase', 'content', 'slug', 'post_title' ) as $k ) {
			$v = $r->get_param( $k );
			if ( null !== $v ) {
				$over[ $k ] = 'content' === $k ? wp_kses_post( (string) $v ) : sanitize_text_field( (string) $v );
			}
		}
		return rest_ensure_response( MoshAI_SEO_Analyzer::analyze( $post, $over ) );
	}

	public static function post( WP_REST_Request $r ) {
		$post = self::editable_post( $r->get_param( 'id' ) );
		if ( is_wp_error( $post ) ) {
			return self::out( $post );
		}
		$res = MoshAI_SEO_Meta::update( $post->ID, (array) $r->get_param( 'fields' ) );
		return is_wp_error( $res ) ? self::out( $res ) : rest_ensure_response( MoshAI_SEO_Audit::row( get_post( $post->ID ) ) );
	}

	public static function report( WP_REST_Request $r ) {
		$res = MoshAI_SEO_Audit::report( sanitize_key( (string) $r->get_param( 'type' ) ), sanitize_key( (string) $r->get_param( 'problem' ) ), (int) $r->get_param( 'page' ), 20, sanitize_text_field( (string) $r->get_param( 'search' ) ) );
		if ( ! is_wp_error( $res ) && $r->get_param( 'summary' ) ) {
			$res['summary'] = MoshAI_SEO_Audit::summary( sanitize_key( (string) $r->get_param( 'type' ) ) );
		}
		return self::out( $res );
	}

	public static function rescore( WP_REST_Request $r ) {
		return self::out( MoshAI_SEO_Audit::rescore( (int) $r->get_param( 'offset' ), 25, sanitize_key( (string) $r->get_param( 'type' ) ) ) );
	}

	public static function redirects( WP_REST_Request $r ) {
		if ( 'POST' === $r->get_method() ) {
			return self::out( MoshAI_SEO_Redirects::create( (string) $r->get_param( 'source' ), (string) $r->get_param( 'target' ), (int) ( $r->get_param( 'code' ) ? $r->get_param( 'code' ) : 301 ), (bool) $r->get_param( 'regex' ) ) );
		}
		return self::out( MoshAI_SEO_Redirects::query( sanitize_text_field( (string) $r->get_param( 'search' ) ), (int) $r->get_param( 'page' ) ) );
	}

	public static function redirect_delete( WP_REST_Request $r ) {
		return self::out( MoshAI_SEO_Redirects::delete( (int) $r->get_param( 'id' ) ) );
	}

	public static function log_404( WP_REST_Request $r ) {
		return self::out( MoshAI_SEO_Redirects::query_404( (int) $r->get_param( 'page' ) ) );
	}

	public static function clear_404( WP_REST_Request $r ) {
		return self::out( MoshAI_SEO_Redirects::clear_404( (int) $r->get_param( 'id' ) ) );
	}

	public static function import( WP_REST_Request $r ) {
		return self::out( MoshAI_SEO_Tools::import( (string) $r->get_param( 'from' ), (int) $r->get_param( 'offset' ), (bool) $r->get_param( 'overwrite' ) ) );
	}

	/** Writes an SEO title or meta description with the connected AI. */
	public static function ai( WP_REST_Request $r ) {
		if ( ! class_exists( 'MoshAI_Settings' ) || ! MoshAI_Settings::has_ai() ) {
			return new WP_Error( 'moshai_locked', __( 'Connect an AI in AI MCP > Settings to write with AI.', 'ai-mcp' ), array( 'status' => 423 ) );
		}
		$limit = MoshAI_Core::check_limits();
		if ( is_wp_error( $limit ) ) {
			return $limit;
		}
		$post = self::editable_post( $r->get_param( 'post_id' ) );
		if ( is_wp_error( $post ) ) {
			return self::out( $post );
		}
		$task    = 'title' === $r->get_param( 'task' ) ? 'title' : 'description';
		$focus   = sanitize_text_field( (string) $r->get_param( 'focus_keyphrase' ) );
		$title   = sanitize_text_field( (string) ( $r->get_param( 'post_title' ) ? $r->get_param( 'post_title' ) : $post->post_title ) );
		$content = $r->get_param( 'content' ) ? (string) $r->get_param( 'content' ) : MoshAI_SEO_Analyzer::content( $post );
		$text    = mb_substr( MoshAI_SEO_Analyzer::plain_text( $content ), 0, 6000 );
		$site    = get_bloginfo( 'name' );
		if ( 'title' === $task ) {
			$prompt = "Write one SEO title for this page. 45 to 60 characters. Start with the focus keyphrase if one is given. Do not add the site name. No quotes, no emojis, no trailing punctuation. Reply with the title only.\n\nFocus keyphrase: " . ( $focus ? $focus : '(none)' ) . "\nPage title: $title\nSite: $site\n\nContent:\n$text";
		} else {
			$prompt = "Write one meta description for this page. 130 to 155 characters. Include the focus keyphrase naturally if one is given. Active voice, specific benefit, end with a soft call to action. No quotes, no emojis. Reply with the description only.\n\nFocus keyphrase: " . ( $focus ? $focus : '(none)' ) . "\nPage title: $title\nSite: $site\n\nContent:\n$text";
		}
		$res = MoshAI_Core::ask( $prompt, 'You are an SEO copywriter. Write in the same language as the content.', 'seo', array( 'max_tokens' => 200 ) );
		if ( is_wp_error( $res ) ) {
			return $res;
		}
		$out = trim( MoshAI_Core::strip_fences( $res['text'] ), " \t\n\r\"'" );
		if ( 'title' === $task ) {
			$out .= ' %%sep%% %%sitename%%';
		}
		return rest_ensure_response( array( 'text' => $out ) );
	}
}
