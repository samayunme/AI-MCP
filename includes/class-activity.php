<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Audit log of everything AI changed on the site (MCP clients and the dashboard agent),
 * plus blocked attempts, and the list of MCP clients that connected.
 */
class MoshAI_Activity {

	const CLIENTS = 'moshai_mcp_clients';

	/* ---------- Changes ---------- */

	public static function log( $source, $client, $tool, $args, $ok, $result = null, $summary = '' ) {
		global $wpdb;
		$args = is_array( $args ) ? $args : array();
		list( $type, $id ) = self::object_of( $tool, $args, $result );
		$wpdb->insert( // phpcs:ignore
			MoshAI_DB::table( 'activity' ),
			array(
				'time'        => current_time( 'mysql', true ),
				'source'      => substr( sanitize_key( $source ), 0, 20 ),
				'client'      => mb_substr( sanitize_text_field( (string) $client ), 0, 100 ),
				'user_id'     => get_current_user_id(),
				'tool'        => substr( sanitize_key( $tool ), 0, 80 ),
				'summary'     => mb_substr( sanitize_text_field( '' !== $summary ? $summary : self::summary( $tool, $args, $result, $ok ) ), 0, 250 ),
				'object_type' => $type,
				'object_id'   => $id,
				'ok'          => $ok ? 1 : 0,
				'args'        => mb_substr( (string) wp_json_encode( self::redact( $args ), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ), 0, 4000 ),
				'ip_hash'     => MoshAI_Core::ip_hash(),
			)
		);
	}

	private static function redact( $args ) {
		$out = array();
		foreach ( (array) $args as $k => $v ) {
			if ( preg_match( '/(pass|token|secret|key|auth)/i', (string) $k ) && ! in_array( $k, array( 'key' ), true ) ) {
				$out[ $k ] = '***';
			} elseif ( is_array( $v ) ) {
				$out[ $k ] = self::redact( $v );
			} elseif ( is_string( $v ) && mb_strlen( $v ) > 400 ) {
				$out[ $k ] = mb_substr( $v, 0, 400 ) . '…';
			} else {
				$out[ $k ] = $v;
			}
		}
		return $out;
	}

	private static function object_of( $tool, $a, $r ) {
		$id = 0;
		foreach ( array( 'id', 'order_id', 'product_id', 'post_id', 'media_id' ) as $k ) {
			if ( ! empty( $a[ $k ] ) && is_numeric( $a[ $k ] ) ) {
				$id = (int) $a[ $k ];
				break;
			}
		}
		if ( ! $id && is_array( $r ) && ! empty( $r['id'] ) && is_numeric( $r['id'] ) ) {
			$id = (int) $r['id'];
		}
		$type = 'post';
		if ( preg_match( '/order|refund/', $tool ) ) {
			$type = 'order';
		} elseif ( preg_match( '/^wc_.*(product|stock)/', $tool ) ) {
			$type = 'product';
		} elseif ( preg_match( '/user/', $tool ) ) {
			$type = 'user';
		} elseif ( preg_match( '/comment/', $tool ) ) {
			$type = 'comment';
		} elseif ( preg_match( '/media|image/', $tool ) ) {
			$type = 'media';
		} elseif ( preg_match( '/plugin|option|elementor_set|kit|css|cache|coupon|auth|rest/', $tool ) ) {
			$type = 'site';
		}
		return array( $type, $id );
	}

	private static function short( $s, $n = 40 ) {
		$s = wp_strip_all_tags( (string) $s );
		return mb_strlen( $s ) > $n ? mb_substr( $s, 0, $n ) . '…' : $s;
	}

	private static function title( $a ) {
		$id = isset( $a['id'] ) ? (int) $a['id'] : 0;
		if ( $id && get_post( $id ) ) {
			return '"' . self::short( get_the_title( $id ), 50 ) . '" (#' . $id . ')';
		}
		return $id ? '#' . $id : '';
	}

	private static function fields( $a ) {
		return implode( ', ', array_diff( array_keys( (array) $a ), array( 'id' ) ) );
	}

	public static function summary( $tool, $a, $r, $ok ) {
		$g = function ( $k, $d = '' ) use ( $a ) {
			return isset( $a[ $k ] ) && ! is_array( $a[ $k ] ) ? (string) $a[ $k ] : $d;
		};
		$s = '';
		switch ( $tool ) {
			case 'wp_create_post':
				$s = 'Created ' . $g( 'post_type', 'post' ) . ' "' . self::short( $g( 'title' ), 60 ) . '"' . ( is_array( $r ) && ! empty( $r['id'] ) ? ' (#' . (int) $r['id'] . ')' : '' );
				break;
			case 'wp_update_post':
				$s = 'Updated ' . self::title( $a ) . ': ' . self::fields( $a );
				break;
			case 'wp_delete_post':
				$s = ( ! empty( $a['force'] ) ? 'Permanently deleted ' : 'Moved to trash ' ) . self::title( $a );
				break;
			case 'wp_replace_in_post':
				$s = 'Replaced "' . self::short( $g( 'search' ) ) . '" with "' . self::short( $g( 'replace' ) ) . '" in ' . self::title( $a );
				break;
			case 'wp_replace_in_post_meta':
				$s = 'Replaced "' . self::short( $g( 'search' ) ) . '" with "' . self::short( $g( 'replace' ) ) . '" in ' . $g( 'key', 'meta' ) . ' of ' . self::title( $a );
				break;
			case 'wp_update_post_meta':
				$s = 'Set ' . $g( 'key', 'meta' ) . ' on ' . self::title( $a );
				break;
			case 'wc_update_stock':
				$s = 'Stock of ' . self::title( $a ) . ': ' . ( is_array( $r ) && isset( $r['before'] ) ? $r['before'] : '?' ) . ' → ' . ( is_array( $r ) && isset( $r['after'] ) ? $r['after'] : '?' );
				break;
			case 'wc_update_product':
				$s = 'Updated product ' . self::title( $a ) . ': ' . self::fields( $a );
				break;
			case 'wc_update_order':
				$s = 'Order #' . (int) $g( 'id', 0 ) . ( '' !== $g( 'status' ) ? ' → ' . $g( 'status' ) : '' ) . ( '' !== $g( 'note' ) ? ' (note added)' : '' );
				break;
			case 'wc_create_refund':
				$s = 'Refunded ' . $g( 'amount', 'the full amount' ) . ' on order #' . (int) $g( 'order_id', $g( 'id', 0 ) );
				break;
			case 'wc_create_product':
				$s = 'Created product "' . self::short( $g( 'name' ), 60 ) . '"';
				break;
			case 'wc_create_coupon':
				$s = 'Created coupon ' . $g( 'code' );
				break;
			case 'wp_toggle_plugin':
				$s = ( ! empty( $a['active'] ) ? 'Activated plugin ' : 'Deactivated plugin ' ) . $g( 'plugin' );
				break;
			case 'wp_install_plugin':
				$s = 'Installed plugin ' . $g( 'slug' ) . ( ! empty( $a['activate'] ) ? ' and activated it' : '' );
				break;
			case 'wp_update_option':
				$s = 'Changed setting ' . $g( 'key' ) . ( '' !== $g( 'path' ) ? ' → ' . $g( 'path' ) : '' );
				break;
			case 'elementor_set_feature':
				$s = 'Elementor feature ' . $g( 'name' ) . ' → ' . $g( 'state' );
				break;
			case 'elementor_update_kit':
				$s = 'Updated Elementor global styles (Site Settings)';
				break;
			case 'elementor_clear_cache':
				$s = 'Cleared Elementor cache';
				break;
			case 'wp_update_custom_css':
				$s = ( 'append' === $g( 'mode' ) ? 'Added to' : 'Replaced' ) . ' Additional CSS';
				break;
			case 'wp_flush_cache':
				$s = 'Flushed site caches';
				break;
			case 'wp_rest_request':
				$s = strtoupper( $g( 'method', 'GET' ) ) . ' ' . $g( 'path' );
				break;
			case 'wp_create_user':
				$s = 'Created user ' . $g( 'username' ) . ' (' . $g( 'role', 'subscriber' ) . ')';
				break;
			case 'wp_update_user':
				$s = 'Updated user #' . (int) $g( 'id', 0 ) . ': ' . self::fields( $a );
				break;
			case 'wp_moderate_comment':
				$s = 'Comment #' . (int) $g( 'id', 0 ) . ' → ' . $g( 'status', 'reply' );
				break;
			case 'wp_update_media':
				$s = 'Updated media #' . (int) $g( 'id', 0 ) . ': ' . self::fields( $a );
				break;
			case 'ai_generate_image':
				$s = 'Generated image "' . self::short( $g( 'prompt' ) ) . '"';
				break;
		}
		if ( '' === $s ) {
			$s = (string) apply_filters( 'moshai_activity_summary', '', $tool, $a, $r );
		}
		if ( '' === $s ) {
			$s = ucfirst( str_replace( '_', ' ', preg_replace( '/^(wp|wc)_/', '', $tool ) ) ) . ( ! empty( $a['id'] ) ? ' #' . (int) $a['id'] : '' );
		}
		return $ok ? $s : 'Failed: ' . $s;
	}

	public static function link( $row ) {
		$id = (int) $row['object_id'];
		if ( ! $id ) {
			return '';
		}
		if ( 'order' === $row['object_type'] && function_exists( 'wc_get_order' ) ) {
			$o = wc_get_order( $id );
			return $o ? $o->get_edit_order_url() : '';
		}
		if ( 'user' === $row['object_type'] ) {
			return get_edit_user_link( $id );
		}
		if ( 'comment' === $row['object_type'] ) {
			return admin_url( 'comment.php?action=editcomment&c=' . $id );
		}
		return get_post( $id ) ? (string) get_edit_post_link( $id, 'raw' ) : '';
	}

	/**
	 * Latest entries, ready for display.
	 */
	public static function recent( $limit = 10, $source = '' ) {
		global $wpdb;
		$t     = MoshAI_DB::table( 'activity' );
		$limit = max( 1, min( 500, (int) $limit ) );
		if ( $source ) {
			$rows = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM $t WHERE source = %s ORDER BY id DESC LIMIT %d", $source, $limit ), ARRAY_A ); // phpcs:ignore
		} else {
			$rows = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM $t ORDER BY id DESC LIMIT %d", $limit ), ARRAY_A ); // phpcs:ignore
		}
		$out = array();
		foreach ( (array) $rows as $row ) {
			$ts    = strtotime( $row['time'] . ' UTC' );
			$out[] = array(
				'id'      => (int) $row['id'],
				'source'  => $row['source'],
				'who'     => 'agent' === $row['source'] ? 'AI Agent' . ( $row['client'] ? ' · ' . $row['client'] : '' ) : 'MCP' . ( $row['client'] ? ' · ' . $row['client'] : '' ),
				'tool'    => $row['tool'],
				'summary' => $row['summary'],
				'ok'      => (bool) $row['ok'],
				'blocked' => ! $row['ok'] && 0 === strpos( $row['summary'], 'Blocked' ),
				'ago'     => sprintf( '%s ago', human_time_diff( $ts, time() ) ),
				'date'    => wp_date( 'M j, Y H:i', $ts ),
				'link'    => self::link( $row ),
				'args'    => $row['args'],
			);
		}
		return $out;
	}

	public static function count_since( $seconds, $client = '' ) {
		global $wpdb;
		$t     = MoshAI_DB::table( 'activity' );
		$since = gmdate( 'Y-m-d H:i:s', time() - (int) $seconds );
		if ( $client ) {
			return (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM $t WHERE ok = 1 AND client = %s AND time > %s", $client, $since ) ); // phpcs:ignore
		}
		return (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM $t WHERE ok = 1 AND time > %s", $since ) ); // phpcs:ignore
	}

	/* ---------- MCP clients ---------- */

	/**
	 * Records that an MCP client was seen. Writes at most every 30 seconds per client.
	 */
	public static function touch_client( $name, $version = '', $force = false ) {
		$name = mb_substr( sanitize_text_field( '' !== (string) $name ? (string) $name : 'MCP client' ), 0, 60 );
		$all  = (array) get_option( self::CLIENTS, array() );
		$now  = time();
		$c    = isset( $all[ $name ] ) ? (array) $all[ $name ] : array(
			'name'    => $name,
			'version' => '',
			'first'   => $now,
			'last'    => 0,
		);
		if ( ! $force && $now - (int) $c['last'] < 30 ) {
			return;
		}
		$c['last'] = $now;
		if ( '' !== (string) $version ) {
			$c['version'] = mb_substr( sanitize_text_field( (string) $version ), 0, 30 );
		}
		$all[ $name ] = $c;
		uasort(
			$all,
			function ( $x, $y ) {
				return (int) $y['last'] <=> (int) $x['last'];
			}
		);
		update_option( self::CLIENTS, array_slice( $all, 0, 20, true ), false );
	}

	public static function clients() {
		$out = array();
		foreach ( (array) get_option( self::CLIENTS, array() ) as $c ) {
			$age   = time() - (int) $c['last'];
			$out[] = array(
				'name'    => $c['name'],
				'version' => isset( $c['version'] ) ? $c['version'] : '',
				'state'   => $age < 10 * MINUTE_IN_SECONDS ? 'live' : ( $age < DAY_IN_SECONDS ? 'idle' : 'off' ),
				'ago'     => sprintf( '%s ago', human_time_diff( (int) $c['last'], time() ) ),
				'first'   => wp_date( 'M j, Y', (int) $c['first'] ),
				'changes' => self::count_since( 30 * DAY_IN_SECONDS, $c['name'] ),
			);
		}
		return $out;
	}

	/**
	 * Overall MCP state for the header and the MCP tab: live (seen in the last 10 minutes), idle (24h) or off.
	 */
	public static function mcp_status() {
		if ( ! MoshAI_Settings::get( 'mcp_enabled' ) ) {
			return array(
				'state' => 'disabled',
				'label' => __( 'MCP off', 'ai-mcp' ),
			);
		}
		$clients = self::clients();
		if ( ! $clients || 'off' === $clients[0]['state'] ) {
			return array(
				'state' => 'waiting',
				'label' => __( 'MCP waiting for a client', 'ai-mcp' ),
			);
		}
		$c = $clients[0];
		if ( 'live' === $c['state'] ) {
			/* translators: %s client name */
			$label = sprintf( __( 'MCP connected · %s', 'ai-mcp' ), $c['name'] );
		} else {
			/* translators: 1: client name 2: time ago */
			$label = sprintf( __( 'MCP · %1$s, last seen %2$s', 'ai-mcp' ), $c['name'], $c['ago'] );
		}
		return array(
			'state'  => $c['state'],
			'label'  => $label,
			'client' => $c['name'],
			'ago'    => $c['ago'],
		);
	}

	public static function forget_clients() {
		delete_option( self::CLIENTS );
	}

	public static function prune( $days ) {
		global $wpdb;
		$days = max( 180, (int) $days ); // Audit history is kept at least six months.
		$wpdb->query( $wpdb->prepare( 'DELETE FROM ' . MoshAI_DB::table( 'activity' ) . ' WHERE time < %s', gmdate( 'Y-m-d H:i:s', time() - $days * DAY_IN_SECONDS ) ) ); // phpcs:ignore
	}
}
