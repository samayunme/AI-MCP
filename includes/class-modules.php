<?php
/**
 * Modules: optional feature packs that live in their own folder (modules/<id>/module.php).
 *
 * Each module is switched on or off from AI MCP > Modules (or by an AI through MCP). Off modules
 * load no code at all, so they cost nothing. A module that fails to load is switched off
 * automatically instead of breaking the site.
 *
 * Adding a module later: create modules/<id>/module.php, register it in registry() below
 * (or through the moshai_modules filter), and let it add its own tabs, REST routes and MCP tools.
 *
 * @package AI_MCP
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class MoshAI_Modules {

	const OPTION = 'moshai_modules';

	/** @var array Modules that loaded in this request. */
	private static $loaded = array();

	/**
	 * Every known module. 'soon' modules are shown in the dashboard but cannot be switched on yet.
	 */
	public static function registry() {
		$modules = array(
			'seo'       => array(
				'name'        => __( 'SEO', 'ai-mcp' ),
				'description' => __( 'Titles, meta descriptions, Open Graph, schema, XML sitemaps, breadcrumbs, redirects, 404 monitor, robots.txt, content and readability analysis. Every setting can be optimized by an AI through MCP.', 'ai-mcp' ),
				'icon'        => 'dashicons-search',
				'file'        => 'modules/seo/module.php',
				'class'       => 'MoshAI_SEO',
				'default'     => 1,
			),
			'cartflows' => array(
				'name'        => __( 'CartFlows', 'ai-mcp' ),
				'description' => __( 'Let an AI build and edit funnels, checkout and thank-you steps, order bumps and offers.', 'ai-mcp' ),
				'icon'        => 'dashicons-randomize',
				'soon'        => true,
			),
			'backup'    => array(
				'name'        => __( 'Backups', 'ai-mcp' ),
				'description' => __( 'Ask an AI to back up the site before big changes, list backups and restore them, using your backup plugin.', 'ai-mcp' ),
				'icon'        => 'dashicons-backup',
				'soon'        => true,
			),
		);
		return apply_filters( 'moshai_modules', $modules );
	}

	/** Saved on/off states merged with each module's default. */
	public static function states() {
		$saved = get_option( self::OPTION, array() );
		$saved = is_array( $saved ) ? $saved : array();
		$out   = array();
		foreach ( self::registry() as $id => $m ) {
			if ( ! empty( $m['soon'] ) ) {
				$out[ $id ] = 0;
				continue;
			}
			$out[ $id ] = isset( $saved[ $id ] ) ? (int) (bool) $saved[ $id ] : (int) ( isset( $m['default'] ) ? $m['default'] : 0 );
		}
		return $out;
	}

	public static function enabled( $id ) {
		$states = self::states();
		return ! empty( $states[ $id ] );
	}

	/** True when the module is switched on and its code loaded without errors. */
	public static function active( $id ) {
		return isset( self::$loaded[ $id ] );
	}

	/** Switch a module on or off. Takes effect on the next page load. */
	public static function set( $id, $on ) {
		$registry = self::registry();
		if ( ! isset( $registry[ $id ] ) ) {
			return new WP_Error( 'moshai_module', __( 'Unknown module.', 'ai-mcp' ) );
		}
		if ( ! empty( $registry[ $id ]['soon'] ) ) {
			return new WP_Error( 'moshai_module', __( 'This module is coming soon.', 'ai-mcp' ) );
		}
		$saved = get_option( self::OPTION, array() );
		$saved = is_array( $saved ) ? $saved : array();
		$saved[ $id ] = $on ? 1 : 0;
		update_option( self::OPTION, $saved, true );
		if ( $on ) {
			delete_option( 'moshai_module_error_' . $id );
		}
		do_action( 'moshai_module_toggled', $id, (bool) $on );
		return self::info( $id );
	}

	public static function info( $id ) {
		$registry = self::registry();
		$m        = $registry[ $id ];
		$error    = get_option( 'moshai_module_error_' . $id, '' );
		return array(
			'id'          => $id,
			'name'        => $m['name'],
			'description' => $m['description'],
			'enabled'     => self::enabled( $id ),
			'loaded'      => self::active( $id ),
			'coming_soon' => ! empty( $m['soon'] ),
			'error'       => $error ? (string) $error : '',
		);
	}

	public static function all_info() {
		$out = array();
		foreach ( array_keys( self::registry() ) as $id ) {
			$out[] = self::info( $id );
		}
		return $out;
	}

	/**
	 * Loads every switched-on module. Runs on plugins_loaded, before the rest of AI MCP boots.
	 */
	public static function init() {
		foreach ( self::registry() as $id => $m ) {
			if ( ! empty( $m['soon'] ) || empty( $m['file'] ) || ! self::enabled( $id ) ) {
				continue;
			}
			$file = MOSHAI_DIR . ltrim( $m['file'], '/' );
			if ( ! file_exists( $file ) ) {
				continue;
			}
			try {
				require_once $file;
				if ( ! empty( $m['class'] ) && is_callable( array( $m['class'], 'init' ) ) ) {
					call_user_func( array( $m['class'], 'init' ) );
				}
				self::$loaded[ $id ] = true;
			} catch ( Throwable $e ) {
				// A broken module switches itself off instead of taking the site down.
				$saved        = get_option( self::OPTION, array() );
				$saved        = is_array( $saved ) ? $saved : array();
				$saved[ $id ] = 0;
				update_option( self::OPTION, $saved, true );
				update_option( 'moshai_module_error_' . $id, substr( $e->getMessage() . ' (' . basename( $e->getFile() ) . ':' . $e->getLine() . ')', 0, 500 ), false );
			}
		}
		add_action( 'rest_api_init', array( __CLASS__, 'routes' ) );
		add_filter( 'moshai_mcp_tools', array( __CLASS__, 'tools' ), 5 );
		add_filter( 'moshai_mcp_call', array( __CLASS__, 'call' ), 10, 3 );
		add_action( 'admin_notices', array( __CLASS__, 'notices' ) );
	}

	public static function notices() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		foreach ( self::registry() as $id => $m ) {
			$error = get_option( 'moshai_module_error_' . $id, '' );
			if ( $error ) {
				/* translators: 1: module name, 2: error */
				echo '<div class="notice notice-error"><p>' . esc_html( sprintf( __( 'AI MCP switched off the %1$s module because it failed to load: %2$s', 'ai-mcp' ), $m['name'], $error ) ) . '</p></div>';
			}
		}
	}

	/* ---------- REST (dashboard) ---------- */

	public static function routes() {
		register_rest_route(
			'ai-mcp/v1',
			'/admin/modules',
			array(
				'methods'             => 'POST',
				'callback'            => array( __CLASS__, 'rest_set' ),
				'permission_callback' => function () {
					return current_user_can( 'manage_options' );
				},
			)
		);
	}

	public static function rest_set( WP_REST_Request $r ) {
		$res = self::set( sanitize_key( (string) $r->get_param( 'id' ) ), (bool) $r->get_param( 'on' ) );
		return is_wp_error( $res ) ? $res : rest_ensure_response( $res );
	}

	/* ---------- MCP tools ---------- */

	public static function tools( $tools ) {
		$tools[] = MoshAI_MCP::tool( 'module_list', 'List AI MCP modules (SEO and future packs) with their on/off state. Each module adds its own tools when on.' );
		$tools[] = MoshAI_MCP::tool(
			'module_set',
			'Switch an AI MCP module on or off, e.g. id "seo". Takes effect on the next request: list tools again afterwards.',
			array(
				'id' => array( 'type' => 'string' ),
				'on' => array( 'type' => 'boolean' ),
			),
			array( 'id', 'on' )
		);
		return $tools;
	}

	public static function call( $result, $name, $a ) {
		if ( null !== $result ) {
			return $result;
		}
		if ( 'module_list' === $name ) {
			return self::all_info();
		}
		if ( 'module_set' === $name ) {
			return self::set( sanitize_key( isset( $a['id'] ) ? (string) $a['id'] : '' ), ! empty( $a['on'] ) );
		}
		return null;
	}

	/* ---------- Dashboard panel ---------- */

	public static function panel() {
		echo '<div class="mx-page-head"><div><h2>' . esc_html__( 'Modules', 'ai-mcp' ) . '</h2><p class="mx-help">' . esc_html__( 'Switch feature packs on or off. Off modules load no code, so they never slow your site down.', 'ai-mcp' ) . '</p></div></div>';
		echo '<div class="mx-modules">';
		foreach ( self::registry() as $id => $m ) {
			$info = self::info( $id );
			$cls  = 'mx-module' . ( $info['enabled'] ? ' is-on' : '' ) . ( $info['coming_soon'] ? ' is-soon' : '' );
			echo '<article class="' . esc_attr( $cls ) . '"><div class="mx-module-icon"><span class="dashicons ' . esc_attr( $m['icon'] ) . '"></span></div><div class="mx-module-body"><h3>' . esc_html( $m['name'] );
			if ( $info['coming_soon'] ) {
				echo ' <span class="mx-badge is-off">' . esc_html__( 'Coming soon', 'ai-mcp' ) . '</span>';
			} elseif ( $info['enabled'] ) {
				echo ' <span class="mx-badge is-on">' . esc_html__( 'On', 'ai-mcp' ) . '</span>';
			}
			echo '</h3><p>' . esc_html( $m['description'] ) . '</p>';
			if ( $info['error'] ) {
				echo '<p class="mx-module-error">' . esc_html( $info['error'] ) . '</p>';
			}
			echo '</div>';
			if ( ! $info['coming_soon'] ) {
				echo '<label class="mx-toggle mx-module-switch"><input type="checkbox" data-module="' . esc_attr( $id ) . '"' . checked( $info['enabled'], true, false ) . '><span class="mx-switch"></span><span class="screen-reader-text">' . esc_html( $m['name'] ) . '</span></label>';
			}
			echo '</article>';
		}
		echo '</div>';
	}
}
