<?php
/**
 * On/off switches that are not Elementor experiments: Elementor's own MCP server and
 * WooCommerce features (MCP integration, order storage, product editor...).
 *
 * @package AI_MCP
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class MoshAI_Switches {

	public static function init() {
		add_filter( 'moshai_mcp_tools', array( __CLASS__, 'register' ), 20 );
		add_filter( 'moshai_mcp_call', array( __CLASS__, 'before' ), 5, 3 );
		add_filter( 'moshai_mcp_call', array( __CLASS__, 'after' ), 20, 3 );
	}

	private static function woo() {
		return class_exists( 'WooCommerce' ) && function_exists( 'wc_get_container' );
	}

	private static function elementor() {
		return class_exists( '\Elementor\Plugin' );
	}

	/** Elementor settings stored as plain options instead of experiments. */
	private static function elementor_switches() {
		return array(
			'elementor_mcp' => array(
				'option'      => 'elementor_mcp_enabled',
				'title'       => 'Elementor MCP',
				'description' => 'Elementor\'s own MCP server: lets outside AI tools (Claude, Cursor, Codex...) build and edit Elementor pages. A site setting, not an experiment.',
				'on'          => '1',
				'off'         => '0',
			),
		);
	}

	private static function switch_on( $s ) {
		return in_array( strtolower( (string) get_option( $s['option'], '' ) ), array( '1', 'yes', 'on', 'true', 'active' ), true );
	}

	public static function register( $tools ) {
		foreach ( $tools as $i => $tool ) {
			if ( ! isset( $tool['name'] ) ) {
				continue;
			}
			if ( 'elementor_list_features' === $tool['name'] ) {
				$tools[ $i ]['description'] = 'List Elementor features with their current state: Elementor MCP (name elementor_mcp), Atomic editor / V4, containers, AI and performance experiments.';
			} elseif ( 'elementor_set_feature' === $tool['name'] ) {
				$tools[ $i ]['description'] = 'Turn an Elementor feature on or off, including Elementor MCP (name elementor_mcp) and every experiment. Use names from elementor_list_features. state: active = on, inactive = off, default = Elementor default.';
			}
		}
		if ( self::woo() ) {
			$tools[] = MoshAI_MCP::tool( 'wc_list_features', 'List WooCommerce features (WooCommerce > Settings > Advanced > Features) with their state: MCP integration, order storage (HPOS), new product editor, cart and checkout blocks...' );
			$tools[] = MoshAI_MCP::tool(
				'wc_set_feature',
				'Turn a WooCommerce feature on or off, e.g. mcp_integration. Use the id from wc_list_features.',
				array(
					'id'      => array( 'type' => 'string' ),
					'enabled' => array( 'type' => 'boolean' ),
				),
				array( 'id', 'enabled' )
			);
		}
		return $tools;
	}

	public static function before( $result, $name, $a ) {
		if ( null !== $result ) {
			return $result;
		}
		$a = (array) $a;
		if ( 'elementor_set_feature' === $name ) {
			$key = isset( $a['name'] ) ? sanitize_key( (string) $a['name'] ) : '';
			foreach ( self::elementor_switches() as $id => $s ) {
				if ( $key === $id || $key === $s['option'] || 'mcp' === $key ) {
					return self::set_elementor_switch( $id, $s, isset( $a['state'] ) ? (string) $a['state'] : 'active' );
				}
			}
			return null;
		}
		if ( 'wc_list_features' === $name ) {
			return self::woo() ? self::woo_features() : new WP_Error( 'no_woo', 'WooCommerce is not active on this site.' );
		}
		if ( 'wc_set_feature' === $name ) {
			return self::woo() ? self::woo_set( $a ) : new WP_Error( 'no_woo', 'WooCommerce is not active on this site.' );
		}
		return null;
	}

	/** Adds the Elementor switches to the top of elementor_list_features. */
	public static function after( $result, $name, $a ) {
		if ( 'elementor_list_features' !== $name || ! is_array( $result ) ) {
			return $result;
		}
		$extra = array();
		foreach ( self::elementor_switches() as $id => $s ) {
			$extra[] = array(
				'name'        => $id,
				'title'       => $s['title'],
				'state'       => self::switch_on( $s ) ? 'active' : 'inactive',
				'setting'     => (string) get_option( $s['option'], '' ),
				'default'     => 'inactive',
				'release'     => 'setting',
				'description' => $s['description'],
				'requires'    => array(),
			);
		}
		return array_merge( $extra, $result );
	}

	private static function set_elementor_switch( $id, $s, $state ) {
		if ( ! self::elementor() ) {
			return new WP_Error( 'no_elementor', 'Elementor is not active.' );
		}
		$before = self::switch_on( $s ) ? 'active' : 'inactive';
		$on     = 'active' === $state;
		update_option( $s['option'], $on ? $s['on'] : $s['off'] );
		MoshAI_MCP::clear_elementor();
		return array(
			'name'   => $id,
			'before' => $before,
			'set_to' => $on ? 'active' : 'inactive',
			'note'   => $on
				? $s['title'] . ' is on. If your AI client asks for a WordPress Application Password, the site owner creates it in Users > Profile: AI MCP never creates login credentials.'
				: $s['title'] . ' is off.',
		);
	}

	private static function woo_controller() {
		$cls = 'Automattic\\WooCommerce\\Internal\\Features\\FeaturesController';
		if ( ! function_exists( 'wc_get_container' ) || ! class_exists( $cls ) ) {
			return null;
		}
		try {
			return wc_get_container()->get( $cls );
		} catch ( \Throwable $e ) {
			return null;
		}
	}

	private static function woo_option( $id ) {
		return 'woocommerce_feature_' . $id . '_enabled';
	}

	private static function woo_features() {
		$out = array();
		$c   = self::woo_controller();
		if ( $c && is_callable( array( $c, 'get_features' ) ) ) {
			try {
				$feats = (array) $c->get_features( true, true );
			} catch ( \Throwable $e ) {
				$feats = array();
			}
			foreach ( $feats as $id => $f ) {
				$f     = (array) $f;
				$out[] = array(
					'id'           => (string) $id,
					'name'         => isset( $f['name'] ) ? wp_strip_all_tags( (string) $f['name'] ) : (string) $id,
					'enabled'      => isset( $f['is_enabled'] ) ? (bool) $f['is_enabled'] : ( 'yes' === get_option( self::woo_option( $id ) ) ),
					'experimental' => ! empty( $f['is_experimental'] ),
					'description'  => isset( $f['description'] ) ? mb_substr( wp_strip_all_tags( (string) $f['description'] ), 0, 160 ) : '',
				);
			}
		}
		if ( ! $out ) {
			global $wpdb;
			$like = $wpdb->esc_like( 'woocommerce_feature_' ) . '%' . $wpdb->esc_like( '_enabled' );
			foreach ( (array) $wpdb->get_col( $wpdb->prepare( "SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE %s", $like ) ) as $opt ) {
				$id    = substr( $opt, 20, -8 );
				$out[] = array(
					'id'      => $id,
					'name'    => $id,
					'enabled' => 'yes' === get_option( $opt ),
				);
			}
		}
		return $out;
	}

	private static function woo_set( $a ) {
		$id     = isset( $a['id'] ) ? sanitize_key( (string) $a['id'] ) : '';
		$enable = isset( $a['enabled'] ) ? filter_var( $a['enabled'], FILTER_VALIDATE_BOOLEAN ) : true;
		if ( '' === $id ) {
			return new WP_Error( 'missing', 'Give the feature id from wc_list_features.' );
		}
		$opt    = self::woo_option( $id );
		$before = 'yes' === get_option( $opt );
		$c      = self::woo_controller();
		$known = wp_list_pluck( self::woo_features(), 'id' );
		if ( $known && ! in_array( $id, $known, true ) ) {
			return new WP_Error( 'not_found', 'Unknown WooCommerce feature. Use wc_list_features for exact ids.' );
		}
		if ( $c && is_callable( array( $c, 'change_feature_enable' ) ) ) {
			try {
				$c->change_feature_enable( $id, $enable );
			} catch ( \Throwable $e ) {
				return new WP_Error( 'failed', $e->getMessage() );
			}
		} else {
			if ( false === get_option( $opt, false ) ) {
				return new WP_Error( 'not_found', 'Unknown WooCommerce feature. Use wc_list_features for exact ids.' );
			}
			update_option( $opt, $enable ? 'yes' : 'no' );
		}
		$after = 'yes' === get_option( $opt );
		if ( $after !== $enable ) {
			return new WP_Error( 'refused', 'WooCommerce refused this change. Some features (like order storage) can only be switched in WooCommerce > Settings > Advanced > Features.' );
		}
		return array(
			'id'      => $id,
			'before'  => $before,
			'enabled' => $after,
		);
	}
}
