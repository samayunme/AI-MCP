<?php
/**
 * Redirect manager: 301/302/307/308 redirects, 410 Gone and 451, plain or regex rules,
 * automatic redirects when a published URL changes, and an optional 404 monitor.
 *
 * Fast path: rules are kept in one cached map, so a normal page view costs a single array lookup.
 *
 * @package AI_MCP
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class MoshAI_SEO_Redirects {

	const MAP = 'moshai_seo_redirect_map';

	const CODES = array( 301, 302, 307, 308, 410, 451 );

	public static function table() {
		global $wpdb;
		return $wpdb->prefix . 'moshai_seo_redirects';
	}

	public static function log_table() {
		global $wpdb;
		return $wpdb->prefix . 'moshai_seo_404';
	}

	public static function maybe_install() {
		if ( get_option( 'moshai_seo_db' ) === MoshAI_SEO::DB_VERSION ) {
			return;
		}
		global $wpdb;
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		$charset = $wpdb->get_charset_collate();
		dbDelta(
			'CREATE TABLE ' . self::table() . " (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			source varchar(255) NOT NULL,
			target text NOT NULL,
			code smallint(3) NOT NULL DEFAULT 301,
			regex tinyint(1) NOT NULL DEFAULT 0,
			hits bigint(20) unsigned NOT NULL DEFAULT 0,
			last_hit datetime NULL DEFAULT NULL,
			note varchar(190) NOT NULL DEFAULT '',
			created datetime NOT NULL,
			PRIMARY KEY  (id),
			KEY source (source(191))
			) $charset;"
		);
		dbDelta(
			'CREATE TABLE ' . self::log_table() . " (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			url varchar(191) NOT NULL,
			referrer varchar(255) NOT NULL DEFAULT '',
			hits bigint(20) unsigned NOT NULL DEFAULT 1,
			last_seen datetime NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY url (url)
			) $charset;"
		);
		update_option( 'moshai_seo_db', MoshAI_SEO::DB_VERSION, false );
	}

	public static function init() {
		add_action( 'template_redirect', array( __CLASS__, 'maybe_redirect' ), 0 );
		add_action( 'template_redirect', array( __CLASS__, 'maybe_log_404' ), 99 );
		add_action( 'post_updated', array( __CLASS__, 'slug_changed' ), 10, 3 );
	}

	/** Path relative to the site root, lowercased, no trailing slash. Query kept when $keep_query. */
	public static function normalize( $url, $keep_query = false ) {
		$url   = trim( (string) $url );
		$parts = wp_parse_url( $url );
		$path  = isset( $parts['path'] ) ? rawurldecode( $parts['path'] ) : '/';
		$base  = (string) wp_parse_url( home_url( '/' ), PHP_URL_PATH );
		if ( '/' !== $base && 0 === stripos( $path, untrailingslashit( $base ) ) ) {
			$path = substr( $path, strlen( untrailingslashit( $base ) ) );
		}
		$path = '/' . trim( mb_strtolower( $path ), '/' );
		if ( $keep_query && ! empty( $parts['query'] ) ) {
			$path .= '?' . $parts['query'];
		}
		return $path;
	}

	/** Rebuilds the cached rule map after any change. */
	public static function rebuild() {
		global $wpdb;
		$rows = $wpdb->get_results( 'SELECT id, source, target, code, regex FROM ' . self::table() . ' ORDER BY id ASC', ARRAY_A ); // phpcs:ignore
		$map  = array(
			'exact' => array(),
			'regex' => array(),
		);
		foreach ( (array) $rows as $r ) {
			$rule = array( (int) $r['id'], $r['target'], (int) $r['code'] );
			if ( (int) $r['regex'] ) {
				$map['regex'][] = array( (int) $r['id'], $r['source'], $r['target'], (int) $r['code'] );
			} else {
				$map['exact'][ self::normalize( $r['source'], false !== strpos( $r['source'], '?' ) ) ] = $rule;
			}
		}
		update_option( self::MAP, $map, true );
		return $map;
	}

	public static function maybe_redirect() {
		$map = get_option( self::MAP, null );
		if ( null === $map ) {
			$map = self::rebuild();
		}
		if ( empty( $map['exact'] ) && empty( $map['regex'] ) ) {
			return;
		}
		$uri  = isset( $_SERVER['REQUEST_URI'] ) ? esc_url_raw( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '/';
		$path = self::normalize( $uri );
		$full = self::normalize( $uri, true );
		$hit  = null;
		if ( isset( $map['exact'][ $full ] ) ) {
			$hit = $map['exact'][ $full ];
		} elseif ( isset( $map['exact'][ $path ] ) ) {
			$hit = $map['exact'][ $path ];
		} else {
			foreach ( $map['regex'] as $r ) {
				$pattern = '#' . str_replace( '#', '\#', $r[1] ) . '#i';
				if ( @preg_match( $pattern, $path ) ) { // phpcs:ignore WordPress.PHP.NoSilencedErrors -- user-entered pattern.
					$hit = array( $r[0], preg_replace( $pattern, $r[2], $path ), $r[3] );
					break;
				}
			}
		}
		if ( ! $hit ) {
			return;
		}
		global $wpdb;
		$wpdb->query( $wpdb->prepare( 'UPDATE ' . self::table() . ' SET hits = hits + 1, last_hit = %s WHERE id = %d', current_time( 'mysql' ), $hit[0] ) ); // phpcs:ignore
		if ( in_array( $hit[2], array( 410, 451 ), true ) ) {
			global $wp_query;
			$wp_query->set_404();
			status_header( $hit[2] );
			nocache_headers();
			return;
		}
		$target = $hit[1];
		if ( 0 === strpos( $target, '/' ) ) {
			$target = home_url( $target );
		}
		if ( $target && self::normalize( $target, true ) !== $full ) {
			wp_redirect( esc_url_raw( $target ), $hit[2], 'AI MCP' ); // phpcs:ignore WordPress.Security.SafeRedirect -- owner-defined redirects may point to other sites.
			exit;
		}
	}

	public static function maybe_log_404() {
		if ( ! is_404() || ! (int) MoshAI_SEO_Settings::get( 'redirects.log_404' ) ) {
			return;
		}
		$uri = isset( $_SERVER['REQUEST_URI'] ) ? esc_url_raw( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '';
		if ( '' === $uri || preg_match( '#\.(css|js|map|png|jpe?g|gif|webp|svg|ico|woff2?|ttf)(\?|$)#i', $uri ) ) {
			return;
		}
		global $wpdb;
		$ref = isset( $_SERVER['HTTP_REFERER'] ) ? esc_url_raw( wp_unslash( $_SERVER['HTTP_REFERER'] ) ) : '';
		$wpdb->query( $wpdb->prepare( 'INSERT INTO ' . self::log_table() . ' (url, referrer, hits, last_seen) VALUES (%s, %s, 1, %s) ON DUPLICATE KEY UPDATE hits = hits + 1, last_seen = VALUES(last_seen), referrer = IF(VALUES(referrer) = \'\', referrer, VALUES(referrer))', substr( $uri, 0, 191 ), substr( $ref, 0, 255 ), current_time( 'mysql' ) ) ); // phpcs:ignore
		if ( 1 === wp_rand( 1, 50 ) ) {
			$count = (int) $wpdb->get_var( 'SELECT COUNT(*) FROM ' . self::log_table() ); // phpcs:ignore
			if ( $count > 1000 ) {
				$wpdb->query( 'DELETE FROM ' . self::log_table() . ' ORDER BY last_seen ASC LIMIT ' . ( $count - 1000 ) ); // phpcs:ignore
			}
		}
	}

	/** A published URL changed (new slug or parent): send the old address to the new one. */
	public static function slug_changed( $post_id, $after, $before ) {
		if ( ! (int) MoshAI_SEO_Settings::get( 'redirects.auto_slug' ) || 'publish' !== $before->post_status || 'publish' !== $after->post_status ) {
			return;
		}
		if ( ! in_array( $after->post_type, array_keys( MoshAI_SEO::post_types() ), true ) ) {
			return;
		}
		$old = get_permalink( $before );
		$new = get_permalink( $after );
		if ( ! $old || ! $new || self::normalize( $old ) === self::normalize( $new ) || '/' === self::normalize( $old ) ) {
			return;
		}
		self::create( self::normalize( $old ), self::normalize( $new ), 301, false, __( 'Automatic: URL changed', 'ai-mcp' ) );
	}

	/* ---------- CRUD ---------- */

	public static function create( $source, $target, $code = 301, $regex = false, $note = '' ) {
		global $wpdb;
		$code   = (int) $code;
		$source = trim( (string) $source );
		$target = trim( (string) $target );
		if ( ! in_array( $code, self::CODES, true ) ) {
			return new WP_Error( 'moshai_seo_redirect', 'code must be 301, 302, 307, 308, 410 or 451.' );
		}
		if ( '' === $source ) {
			return new WP_Error( 'moshai_seo_redirect', 'source is required, e.g. /old-page/' );
		}
		if ( $regex ) {
			if ( false === @preg_match( '#' . str_replace( '#', '\#', $source ) . '#i', '' ) ) { // phpcs:ignore WordPress.PHP.NoSilencedErrors
				return new WP_Error( 'moshai_seo_redirect', 'The regex is not valid.' );
			}
		} else {
			$source = self::normalize( $source, false !== strpos( $source, '?' ) );
		}
		if ( ! in_array( $code, array( 410, 451 ), true ) ) {
			if ( '' === $target ) {
				return new WP_Error( 'moshai_seo_redirect', 'target is required for this redirect type.' );
			}
			$target = 0 === strpos( $target, '/' ) ? '/' . ltrim( $target, '/' ) : esc_url_raw( $target );
			if ( ! $regex && self::normalize( $target, true ) === $source && 0 === strpos( $target, '/' ) ) {
				return new WP_Error( 'moshai_seo_redirect', 'source and target are the same page.' );
			}
		} else {
			$target = '';
		}
		$existing = $wpdb->get_var( $wpdb->prepare( 'SELECT id FROM ' . self::table() . ' WHERE source = %s AND regex = %d', $source, $regex ? 1 : 0 ) ); // phpcs:ignore
		if ( $existing ) {
			$wpdb->update( self::table(), array( 'target' => $target, 'code' => $code ), array( 'id' => (int) $existing ) ); // phpcs:ignore
			$id = (int) $existing;
		} else {
			// Avoid chains: anything that pointed to this source now points to the new target.
			if ( ! $regex && $target ) {
				$wpdb->query( $wpdb->prepare( 'UPDATE ' . self::table() . ' SET target = %s WHERE regex = 0 AND target = %s', $target, $source ) ); // phpcs:ignore
			}
			$wpdb->insert( // phpcs:ignore
				self::table(),
				array(
					'source'  => $source,
					'target'  => $target,
					'code'    => $code,
					'regex'   => $regex ? 1 : 0,
					'note'    => substr( sanitize_text_field( $note ), 0, 190 ),
					'created' => current_time( 'mysql' ),
				)
			);
			$id = (int) $wpdb->insert_id;
		}
		// The 404 is fixed now.
		$wpdb->query( $wpdb->prepare( 'DELETE FROM ' . self::log_table() . ' WHERE url = %s OR url = %s', $source, trailingslashit( $source ) ) ); // phpcs:ignore
		self::rebuild();
		return self::get( $id );
	}

	public static function get( $id ) {
		global $wpdb;
		$row = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . self::table() . ' WHERE id = %d', $id ), ARRAY_A ); // phpcs:ignore
		return $row ? $row : new WP_Error( 'moshai_seo_redirect', 'Redirect not found.' );
	}

	public static function delete( $id ) {
		global $wpdb;
		$row = self::get( $id );
		if ( is_wp_error( $row ) ) {
			return $row;
		}
		$wpdb->delete( self::table(), array( 'id' => (int) $id ) ); // phpcs:ignore
		self::rebuild();
		return array(
			'deleted' => true,
			'id'      => (int) $id,
			'source'  => $row['source'],
		);
	}

	public static function query( $search = '', $page = 1, $per = 50 ) {
		global $wpdb;
		$per    = min( 200, max( 1, (int) $per ) );
		$offset = ( max( 1, (int) $page ) - 1 ) * $per;
		$t      = self::table();
		if ( '' !== $search ) {
			$like  = '%' . $wpdb->esc_like( $search ) . '%';
			$rows  = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM $t WHERE source LIKE %s OR target LIKE %s ORDER BY id DESC LIMIT %d OFFSET %d", $like, $like, $per, $offset ), ARRAY_A ); // phpcs:ignore
			$total = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM $t WHERE source LIKE %s OR target LIKE %s", $like, $like ) ); // phpcs:ignore
		} else {
			$rows  = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM $t ORDER BY id DESC LIMIT %d OFFSET %d", $per, $offset ), ARRAY_A ); // phpcs:ignore
			$total = (int) $wpdb->get_var( "SELECT COUNT(*) FROM $t" ); // phpcs:ignore
		}
		return array(
			'total' => $total,
			'items' => (array) $rows,
		);
	}

	public static function query_404( $page = 1, $per = 50 ) {
		global $wpdb;
		$per    = min( 200, max( 1, (int) $per ) );
		$offset = ( max( 1, (int) $page ) - 1 ) * $per;
		$t      = self::log_table();
		return array(
			'logging' => (bool) (int) MoshAI_SEO_Settings::get( 'redirects.log_404' ),
			'total'   => (int) $wpdb->get_var( "SELECT COUNT(*) FROM $t" ), // phpcs:ignore
			'items'   => (array) $wpdb->get_results( $wpdb->prepare( "SELECT * FROM $t ORDER BY hits DESC, last_seen DESC LIMIT %d OFFSET %d", $per, $offset ), ARRAY_A ), // phpcs:ignore
		);
	}

	public static function clear_404( $id = 0 ) {
		global $wpdb;
		if ( $id ) {
			$wpdb->delete( self::log_table(), array( 'id' => (int) $id ) ); // phpcs:ignore
		} else {
			$wpdb->query( 'TRUNCATE TABLE ' . self::log_table() ); // phpcs:ignore
		}
		return array( 'cleared' => true );
	}

	public static function count() {
		global $wpdb;
		return (int) $wpdb->get_var( 'SELECT COUNT(*) FROM ' . self::table() ); // phpcs:ignore
	}
}
