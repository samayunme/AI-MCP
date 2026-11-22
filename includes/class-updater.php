<?php
/**
 * Updates AI MCP from its official GitHub releases, through the normal WordPress updater.
 * Only ever pulls from github.com/samayunme/AI-MCP-By-WEBMOSH and checks the file's SHA-256.
 *
 * @package AI_MCP
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class MoshAI_Updater {

	const REPO  = 'samayunme/AI-MCP-By-WEBMOSH';
	const CACHE = 'moshai_gh_release';

	public static function init() {
		add_filter( 'update_plugins_github.com', array( __CLASS__, 'check' ), 10, 4 );
		add_filter( 'plugins_api', array( __CLASS__, 'info' ), 20, 3 );
		add_filter( 'upgrader_pre_download', array( __CLASS__, 'verified_download' ), 10, 4 );
		add_filter( 'upgrader_source_selection', array( __CLASS__, 'fix_folder' ), 10, 4 );
		add_action( 'load-update-core.php', array( __CLASS__, 'force_check' ) );
		add_action( 'admin_init', array( __CLASS__, 'default_autoupdate' ) );
		add_filter( 'moshai_mcp_tools', array( __CLASS__, 'register' ), 20 );
		add_filter( 'moshai_mcp_call', array( __CLASS__, 'call' ), 5, 3 );
	}

	private static function file() {
		return plugin_basename( MOSHAI_FILE );
	}

	/** Latest published release: version, zip URL and checksum. Cached for 3 hours. */
	public static function release( $force = false ) {
		if ( ! $force ) {
			$cached = get_site_transient( self::CACHE );
			if ( is_array( $cached ) ) {
				return $cached ? $cached : null;
			}
		}
		$r = wp_remote_get(
			'https://api.github.com/repos/' . self::REPO . '/releases/latest',
			array(
				'timeout' => 10,
				'headers' => array(
					'Accept'     => 'application/vnd.github+json',
					'User-Agent' => 'AI-MCP/' . MOSHAI_VERSION,
				),
			)
		);
		$d = ( ! is_wp_error( $r ) && 200 === (int) wp_remote_retrieve_response_code( $r ) ) ? json_decode( wp_remote_retrieve_body( $r ), true ) : null;
		$rel = is_array( $d ) ? self::parse( $d ) : null;
		set_site_transient( self::CACHE, $rel ? $rel : array(), $rel ? 3 * HOUR_IN_SECONDS : HOUR_IN_SECONDS );
		return $rel;
	}

	private static function parse( $d ) {
		if ( ! empty( $d['draft'] ) || ! empty( $d['prerelease'] ) || empty( $d['tag_name'] ) ) {
			return null;
		}
		$ver = ltrim( (string) $d['tag_name'], 'vV' );
		if ( ! preg_match( '/^\d+\.\d+\.\d+$/', $ver ) ) {
			return null;
		}
		$pkg    = '';
		$digest = '';
		foreach ( (array) ( isset( $d['assets'] ) ? $d['assets'] : array() ) as $asset ) {
			if ( isset( $asset['name'], $asset['browser_download_url'] ) && 'ai-mcp-' . $ver . '.zip' === $asset['name'] ) {
				$pkg    = (string) $asset['browser_download_url'];
				$digest = isset( $asset['digest'] ) ? (string) $asset['digest'] : '';
				break;
			}
		}
		if ( 0 !== stripos( $pkg, 'https://github.com/' . self::REPO . '/releases/download/' ) ) {
			return null;
		}
		return array(
			'version'   => $ver,
			'package'   => $pkg,
			'digest'    => $digest,
			'notes'     => mb_substr( isset( $d['body'] ) ? (string) $d['body'] : '', 0, 20000 ),
			'url'       => isset( $d['html_url'] ) ? (string) $d['html_url'] : 'https://github.com/' . self::REPO . '/releases',
			'published' => isset( $d['published_at'] ) ? (string) $d['published_at'] : '',
		);
	}

	/** WordPress asks this for plugins whose Update URI points at github.com. */
	public static function check( $update, $plugin_data, $plugin_file, $locales ) {
		if ( self::file() !== $plugin_file ) {
			return $update;
		}
		$rel = self::release();
		if ( ! $rel ) {
			return $update;
		}
		return array(
			'id'           => 'https://github.com/' . self::REPO,
			'slug'         => 'ai-mcp',
			'version'      => $rel['version'],
			'url'          => 'https://github.com/' . self::REPO,
			'package'      => $rel['package'],
			'requires'     => '6.0',
			'requires_php' => '7.4',
			'icons'        => array( 'svg' => MOSHAI_URL . 'assets/img/logo.svg' ),
		);
	}

	/** The "View details" popup on the Plugins page. */
	public static function info( $res, $action, $args ) {
		if ( 'plugin_information' !== $action || empty( $args->slug ) || 'ai-mcp' !== $args->slug ) {
			return $res;
		}
		$rel = self::release();
		if ( ! $rel ) {
			return $res;
		}
		return (object) array(
			'name'          => 'AI MCP',
			'slug'          => 'ai-mcp',
			'version'       => $rel['version'],
			'author'        => '<a href="https://webmosh.com">WEBMOSH</a>',
			'homepage'      => 'https://github.com/' . self::REPO,
			'requires'      => '6.0',
			'requires_php'  => '7.4',
			'last_updated'  => $rel['published'],
			'download_link' => $rel['package'],
			'sections'      => array( 'changelog' => wpautop( esc_html( $rel['notes'] ) ) ),
		);
	}

	/** Downloads the release zip ourselves and refuses it if the SHA-256 does not match GitHub's. */
	public static function verified_download( $reply, $package, $upgrader, $hook_extra = array() ) {
		if ( false !== $reply || ! is_string( $package ) || 0 !== stripos( $package, 'https://github.com/' . self::REPO . '/releases/download/' ) ) {
			return $reply;
		}
		$rel = self::release();
		if ( ! $rel || $rel['package'] !== $package || 0 !== strpos( $rel['digest'], 'sha256:' ) || ! function_exists( 'download_url' ) ) {
			return $reply;
		}
		$tmp = download_url( $package, 300 );
		if ( is_wp_error( $tmp ) ) {
			return $tmp;
		}
		if ( ! hash_equals( strtolower( substr( $rel['digest'], 7 ) ), (string) hash_file( 'sha256', $tmp ) ) ) {
			wp_delete_file( $tmp );
			return new WP_Error( 'moshai_bad_checksum', 'The AI MCP update file failed its security checksum, so the update was stopped.' );
		}
		return $tmp;
	}

	/** Makes sure the update lands in wp-content/plugins/ai-mcp. */
	public static function fix_folder( $source, $remote_source, $upgrader, $hook_extra = array() ) {
		global $wp_filesystem;
		if ( empty( $hook_extra['plugin'] ) || self::file() !== $hook_extra['plugin'] || is_wp_error( $source ) ) {
			return $source;
		}
		$want = trailingslashit( $remote_source ) . 'ai-mcp/';
		if ( untrailingslashit( $source ) === untrailingslashit( $want ) ) {
			return $source;
		}
		if ( $wp_filesystem && $wp_filesystem->move( $source, $want, true ) ) {
			return $want;
		}
		return new WP_Error( 'moshai_folder', 'Could not prepare the AI MCP update folder.' );
	}

	/** Dashboard > Updates > "Check again" also re-checks GitHub. */
	public static function force_check() {
		if ( isset( $_GET['force-check'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
			delete_site_transient( self::CACHE );
		}
	}

	/** Turns WordPress auto-updates on for AI MCP once. You can switch it off on the Plugins page. */
	public static function default_autoupdate() {
		if ( get_site_option( 'moshai_autoupdate_default' ) || ! current_user_can( 'update_plugins' ) ) {
			return;
		}
		$list = (array) get_site_option( 'auto_update_plugins', array() );
		if ( ! in_array( self::file(), $list, true ) ) {
			$list[] = self::file();
			update_site_option( 'auto_update_plugins', $list );
		}
		update_site_option( 'moshai_autoupdate_default', 1 );
	}

	private static function auto_on() {
		return in_array( self::file(), (array) get_site_option( 'auto_update_plugins', array() ), true );
	}

	public static function register( $tools ) {
		$tools[] = MoshAI_MCP::tool( 'wp_get_ai_mcp_update', 'Check whether a newer AI MCP release is available on GitHub, and whether auto-updates are on.' );
		$tools[] = MoshAI_MCP::tool( 'wp_install_plugin_update', 'Update AI MCP itself to the latest official release from github.com/' . self::REPO . '. Only this plugin and only that repo; the file is checksum-verified.' );
		return $tools;
	}

	public static function call( $result, $name, $a ) {
		if ( null !== $result ) {
			return $result;
		}
		if ( 'wp_get_ai_mcp_update' === $name ) {
			$rel = self::release( true );
			if ( ! $rel ) {
				return new WP_Error( 'moshai_no_release', 'Could not read the latest release from GitHub. The repo must be public and have a published release.' );
			}
			return array(
				'installed'        => MOSHAI_VERSION,
				'latest'           => $rel['version'],
				'update_available' => version_compare( $rel['version'], MOSHAI_VERSION, '>' ),
				'auto_updates'     => self::auto_on(),
				'checksum'         => '' !== $rel['digest'],
				'release'          => $rel['url'],
			);
		}
		if ( 'wp_install_plugin_update' === $name ) {
			return self::update_now();
		}
		return null;
	}

	private static function update_now() {
		if ( ! current_user_can( 'update_plugins' ) ) {
			return new WP_Error( 'moshai_forbidden', 'Plugin updates are not allowed on this site for this account.' );
		}
		$rel = self::release( true );
		if ( ! $rel ) {
			return new WP_Error( 'moshai_no_release', 'Could not read the latest release from GitHub. The repo must be public and have a published release.' );
		}
		if ( ! version_compare( $rel['version'], MOSHAI_VERSION, '>' ) ) {
			return array(
				'updated'   => false,
				'installed' => MOSHAI_VERSION,
				'latest'    => $rel['version'],
				'message'   => 'AI MCP is already up to date.',
			);
		}
		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/misc.php';
		require_once ABSPATH . 'wp-admin/includes/plugin.php';
		require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';
		$file       = self::file();
		$was_active = is_plugin_active( $file );
		wp_clean_plugins_cache( true );
		if ( function_exists( 'wp_update_plugins' ) ) {
			wp_update_plugins();
		}
		$t = get_site_transient( 'update_plugins' );
		if ( ! is_object( $t ) ) {
			$t = new stdClass();
		}
		if ( empty( $t->response[ $file ] ) || $t->response[ $file ]->package !== $rel['package'] ) {
			$t->response[ $file ] = (object) array(
				'id'          => 'https://github.com/' . self::REPO,
				'slug'        => 'ai-mcp',
				'plugin'      => $file,
				'new_version' => $rel['version'],
				'url'         => 'https://github.com/' . self::REPO,
				'package'     => $rel['package'],
			);
			set_site_transient( 'update_plugins', $t );
		}
		$skin     = new WP_Ajax_Upgrader_Skin();
		$upgrader = new Plugin_Upgrader( $skin );
		ob_start();
		$ok = $upgrader->upgrade( $file );
		ob_end_clean();
		if ( is_wp_error( $ok ) ) {
			return $ok;
		}
		if ( is_wp_error( $skin->result ) ) {
			return $skin->result;
		}
		if ( $skin->get_errors()->has_errors() ) {
			return new WP_Error( 'moshai_update_failed', $skin->get_error_messages() );
		}
		if ( true !== $ok ) {
			return new WP_Error( 'moshai_update_failed', 'WordPress could not write the plugin files (the host may need FTP details). Update from Plugins > Installed Plugins instead.' );
		}
		if ( $was_active && ! is_plugin_active( $file ) ) {
			activate_plugin( $file, '', false, true );
		}
		delete_site_transient( self::CACHE );
		return array(
			'updated' => true,
			'from'    => MOSHAI_VERSION,
			'to'      => $rel['version'],
			'release' => $rel['url'],
		);
	}
}
