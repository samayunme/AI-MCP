<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class MoshAI_Settings {

	const KEY = 'moshai_settings';

	private static $cache = null;

	public static function defaults() {
		return array(
			'openai_key'       => '',
			'anthropic_key'    => '',
			'google_key'       => '',
			'openrouter_key'   => '',
			'default_provider' => 'openai',
			'default_model'    => 'gpt-4o-mini',
			'temperature'      => 0.7,
			'max_tokens'       => 1024,
			'image_model'      => 'gpt-image-1',
			'embedding_model'  => 'text-embedding-3-small',
			'moderation'       => 0,
			'log_discussions'  => 1,
			'limits_enabled'   => 1,
			'limit_guest'      => 30,
			'limit_user'       => 150,
			'rate_per_minute'  => 10,
			'limit_message'    => 'You have reached the usage limit for today. Please try again later.',
			'popup_bot'        => '',
			'editor_assistant' => 1,
			'meta_description' => 1,
			'mcp_enabled'      => 0,
			'mcp_token'        => '',
			'mcp_user'         => 0,
			'clean_uninstall'  => 0,
			'kb_autosync'      => 1,
			'retention_days'   => 90,
			'agent_provider'   => '',
			'agent_model'      => '',
			'agent_confirm'    => 1,
			'agent_max_steps'  => 15,
			'agent_instructions' => '',
			// "Others": any OpenAI-compatible API (DeepSeek, Groq, Mistral, xAI, Together, Ollama, LM Studio...).
			'custom_name'      => '',
			'custom_url'       => '',
			'custom_key'       => '',
			'custom_model'     => '',
			// MCP security.
			'mcp_token_hash'   => '',
			'mcp_allow_query'  => 0,
			'mcp_allow_writes' => 1,
			'mcp_ip_allow'     => '',
			// What AI (MCP clients and the dashboard agent) may change.
			'perm_content'     => 1,
			'perm_media'       => 1,
			'perm_shop'        => 1,
			'perm_users'       => 1,
			'perm_site'        => 1,
			'perm_rest'        => 1,
		);
	}

	public static function all() {
		if ( null === self::$cache ) {
			self::$cache = wp_parse_args( (array) get_option( self::KEY, array() ), self::defaults() );
		}
		return self::$cache;
	}

	public static function get( $key, $fallback = null ) {
		$all = self::all();
		return array_key_exists( $key, $all ) ? $all[ $key ] : $fallback;
	}

	public static function update( $values ) {
		$all = array_merge( self::all(), (array) $values );
		update_option( self::KEY, $all, false );
		self::$cache = $all;
	}

	public static function toggles() {
		return array( 'moderation', 'log_discussions', 'limits_enabled', 'editor_assistant', 'meta_description', 'mcp_enabled', 'clean_uninstall', 'kb_autosync', 'agent_confirm', 'mcp_allow_query', 'mcp_allow_writes', 'perm_content', 'perm_media', 'perm_shop', 'perm_users', 'perm_site', 'perm_rest' );
	}

	/**
	 * Sanitizes a partial settings array. Only keys that are present are returned.
	 */
	public static function sanitize( $p ) {
		$d = self::defaults();
		$v = array();
		foreach ( (array) $p as $k => $val ) {
			// Token material is never set through the settings form.
			if ( ! array_key_exists( $k, $d ) || is_array( $val ) || in_array( $k, array( 'mcp_token', 'mcp_token_hash' ), true ) ) {
				continue;
			}
			if ( in_array( $k, self::toggles(), true ) ) {
				$v[ $k ] = ( empty( $val ) || 'false' === $val ) ? 0 : 1;
				continue;
			}
			switch ( $k ) {
				case 'default_provider':
					$v[ $k ] = array_key_exists( $val, MoshAI_Providers::names() ) ? $val : 'openai';
					break;
				case 'popup_bot':
					$val     = sanitize_key( $val );
					$v[ $k ] = ( '' !== $val && MoshAI_Chatbots::get( $val ) ) ? $val : '';
					break;
				case 'temperature':
					$v[ $k ] = max( 0, min( 2, (float) $val ) );
					break;
				case 'max_tokens':
					$v[ $k ] = max( 16, min( 64000, (int) $val ) );
					break;
				case 'agent_provider':
					$v[ $k ] = array_key_exists( $val, MoshAI_Providers::names() ) ? $val : '';
					break;
				case 'agent_max_steps':
					$v[ $k ] = max( 3, min( 40, (int) $val ) );
					break;
				case 'agent_instructions':
					$v[ $k ] = sanitize_textarea_field( (string) $val );
					break;
				case 'retention_days':
					$v[ $k ] = max( 0, min( 3650, (int) $val ) );
					break;
				case 'custom_url':
					$url     = esc_url_raw( trim( (string) $val ), array( 'http', 'https' ) );
					$url     = preg_replace( '#/(chat/completions|completions|models)/?$#', '', untrailingslashit( $url ) );
					$v[ $k ] = (string) $url;
					break;
				case 'mcp_ip_allow':
					$lines = array();
					foreach ( preg_split( '/[\s,]+/', (string) $val ) as $ip ) {
						if ( preg_match( '#^[0-9a-fA-F:.]+(/\d{1,3})?$#', $ip ) ) {
							$lines[] = $ip;
						}
					}
					$v[ $k ] = implode( "\n", array_unique( $lines ) );
					break;
				case 'mcp_user':
					$uid     = (int) $val;
					$v[ $k ] = ( $uid > 0 && user_can( $uid, 'manage_options' ) ) ? $uid : 0;
					break;
				default:
					$v[ $k ] = is_int( $d[ $k ] ) ? max( 0, (int) $val ) : trim( sanitize_text_field( (string) $val ) );
			}
		}
		foreach ( array( 'image_model', 'embedding_model', 'default_model' ) as $k ) {
			if ( isset( $v[ $k ] ) && '' === $v[ $k ] ) {
				unset( $v[ $k ] );
			}
		}
		return $v;
	}

	public static function mask( $key ) {
		$key = (string) $key;
		if ( '' === $key ) {
			return '';
		}
		return strlen( $key ) > 12 ? substr( $key, 0, 4 ) . '••••••' . substr( $key, -4 ) : '••••••';
	}

	/**
	 * Masked key per provider ('' when missing). Used by the admin UI.
	 */
	public static function key_status() {
		$out = array();
		foreach ( array_keys( MoshAI_Providers::names() ) as $p ) {
			$const     = 'MOSHAI_' . strtoupper( $p ) . '_KEY';
			$out[ $p ] = array(
				'mask'      => self::mask( self::key_for( $p ) ),
				'config'    => defined( $const ) && constant( $const ),
				'connected' => self::is_connected( $p ),
			);
		}
		$out['custom']['url'] = (string) self::get( 'custom_url', '' );
		return $out;
	}

	/** A provider counts as connected when it has a key ("Others" only needs a URL, e.g. local Ollama). */
	public static function is_connected( $provider ) {
		if ( 'custom' === $provider ) {
			return '' !== (string) self::get( 'custom_url', '' );
		}
		return '' !== self::key_for( $provider );
	}

	/** Connected providers as id => label. */
	public static function connected() {
		$out = array();
		foreach ( MoshAI_Providers::names() as $p => $label ) {
			if ( self::is_connected( $p ) ) {
				$out[ $p ] = $label;
			}
		}
		return $out;
	}

	/** True when at least one AI is connected. Every AI feature is locked until then. */
	public static function has_ai() {
		return (bool) self::connected();
	}

	/** Images and the knowledge base need OpenAI or Gemini. */
	public static function has_media_ai() {
		return self::is_connected( 'openai' ) || self::is_connected( 'google' );
	}

	/** MCP tokens are stored as a keyed hash, never in plain text. */
	public static function token_hash( $token ) {
		return hash_hmac( 'sha256', (string) $token, wp_salt( 'auth' ) );
	}

	/**
	 * One-time upgrades: hash a plain MCP token saved by older versions (existing connections keep working).
	 */
	public static function migrate() {
		$plain = (string) self::get( 'mcp_token', '' );
		if ( '' !== $plain ) {
			$update = array(
				'mcp_token'      => '',
				'mcp_token_hash' => self::token_hash( $plain ),
			);
			// Old versions accepted ?token= in the URL. Keep that working for existing connections.
			$update['mcp_allow_query'] = 1;
			self::update( $update );
		}
	}

	/**
	 * API key for a provider. A constant in wp-config.php (e.g. MOSHAI_OPENAI_KEY) wins over the saved value.
	 */
	public static function key_for( $provider ) {
		$const = 'MOSHAI_' . strtoupper( $provider ) . '_KEY';
		if ( defined( $const ) && constant( $const ) ) {
			return (string) constant( $const );
		}
		return (string) self::get( $provider . '_key', '' );
	}
}
