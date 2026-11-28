<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * One-page admin app: every feature is a tab, every setting saves instantly over the REST API.
 */
class MoshAI_Admin {

	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'menu' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'assets' ) );
	}

	const LOGO_PATH = 'M545 405L857 410L618 1416L778 1416L953 719L1248 722L1025 1642L267 1642ZM1493 406L1780 405L1485 1642L1195 1642Z';

	/** WEBMOSH logo as a data URI. Black fill for the admin menu (WordPress recolors it), blue elsewhere. */
	public static function logo_uri( $fill = 'black' ) {
		$svg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="223 223 1600 1600"><path fill="' . $fill . '" d="' . self::LOGO_PATH . '"/></svg>';
		return 'data:image/svg+xml;base64,' . base64_encode( $svg ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions
	}

	/**
	 * "AI MCP" in the top toolbar, front end and admin, so settings are one click away anywhere.
	 */
	public static function admin_bar( $bar ) {
		if ( ! current_user_can( 'edit_posts' ) ) {
			return;
		}
		$mcp = current_user_can( 'manage_options' ) ? MoshAI_Activity::mcp_status() : array( 'state' => '' );
		$dot = 'live' === $mcp['state'] ? '<span style="display:inline-block;width:7px;height:7px;border-radius:50%;background:#3ddc84;margin-left:6px;vertical-align:middle" title="' . esc_attr( $mcp['label'] ) . '"></span>' : '';
		$bar->add_node(
			array(
				'id'    => 'moshai',
				'title' => '<span class="ab-icon" style="background:url(' . esc_attr( self::logo_uri( '#31B5F2' ) ) . ') center/18px no-repeat;width:20px;height:20px;margin-top:6px"></span><span class="ab-label">AI MCP</span>' . $dot,
				'href'  => self::url( 'home' ),
			)
		);
		foreach ( self::tabs() as $tab => $t ) {
			if ( 'home' !== $tab && current_user_can( $t[1] ) ) {
				$bar->add_node(
					array(
						'parent' => 'moshai',
						'id'     => 'moshai-' . $tab,
						'title'  => esc_html( $t[0] ),
						'href'   => self::url( $tab ),
					)
				);
			}
		}
	}

	/** Sidebar groups, in order. */
	public static function groups() {
		return array(
			'main'   => '',
			'ai'     => __( 'AI', 'ai-mcp' ),
			'site'   => __( 'Website', 'ai-mcp' ),
			'system' => __( 'Control', 'ai-mcp' ),
		);
	}

	/**
	 * Every tab: key => array( label, capability, dashicon, render callback or null, group ).
	 * Core tabs render with panel_<key>(). Modules add their own tabs with the moshai_admin_tabs filter.
	 */
	public static function tabs() {
		$tabs = array(
			'home'      => array( __( 'Home', 'ai-mcp' ), 'edit_posts', 'dashicons-admin-home', null, 'main' ),
			'agent'     => array( __( 'AI Agent', 'ai-mcp' ), 'manage_options', 'dashicons-format-status', null, 'ai' ),
			'chatbots'  => array( __( 'Chatbots', 'ai-mcp' ), 'manage_options', 'dashicons-format-chat', null, 'ai' ),
			'studio'    => array( __( 'Studio', 'ai-mcp' ), 'edit_posts', 'dashicons-edit-large', null, 'ai' ),
			'forms'     => array( __( 'AI Forms', 'ai-mcp' ), 'manage_options', 'dashicons-feedback', null, 'ai' ),
			'knowledge' => array( __( 'Knowledge', 'ai-mcp' ), 'manage_options', 'dashicons-database', null, 'ai' ),
			'activity'  => array( __( 'Activity', 'ai-mcp' ), 'manage_options', 'dashicons-chart-bar', null, 'system' ),
			'mcp'       => array( __( 'MCP', 'ai-mcp' ), 'manage_options', 'dashicons-rest-api', null, 'system' ),
			'modules'   => array( __( 'Modules', 'ai-mcp' ), 'manage_options', 'dashicons-screenoptions', array( 'MoshAI_Modules', 'panel' ), 'system' ),
			'settings'  => array( __( 'Settings', 'ai-mcp' ), 'manage_options', 'dashicons-admin-generic', null, 'system' ),
		);
		$tabs = (array) apply_filters( 'moshai_admin_tabs', $tabs );
		$out  = array();
		foreach ( array_keys( self::groups() ) as $group ) {
			foreach ( $tabs as $k => $t ) {
				if ( ( isset( $t[4] ) ? $t[4] : 'site' ) === $group ) {
					$out[ $k ] = $t;
				}
			}
		}
		return $out + $tabs;
	}

	public static function slug( $tab ) {
		return 'home' === $tab ? 'moshai' : 'moshai-' . $tab;
	}

	public static function url( $tab = 'home' ) {
		return admin_url( 'admin.php?page=' . self::slug( $tab ) );
	}

	public static function menu() {
		add_menu_page( 'AI MCP', 'AI MCP', 'edit_posts', 'moshai', array( __CLASS__, 'page' ), self::logo_uri(), 58 );
		foreach ( self::tabs() as $tab => $t ) {
			add_submenu_page( 'moshai', $t[0] . ' - AI MCP', $t[0], $t[1], self::slug( $tab ), array( __CLASS__, 'page' ) );
		}
	}

	private static function current_tab() {
		$page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : 'moshai'; // phpcs:ignore WordPress.Security.NonceVerification
		$tab  = 'moshai' === $page ? 'home' : substr( $page, 7 );
		$tabs = self::tabs();
		return isset( $tabs[ $tab ] ) && current_user_can( $tabs[ $tab ][1] ) ? $tab : 'home';
	}

	public static function assets( $hook ) {
		if ( false === strpos( $hook, 'moshai' ) ) {
			return;
		}
		MoshAI_Chatbots::register_assets();
		wp_enqueue_style( 'moshai-front' );
		wp_enqueue_script( 'moshai-front' );
		wp_enqueue_style( 'moshai-admin', MOSHAI_URL . 'assets/css/admin.css', array(), MOSHAI_VERSION );
		wp_enqueue_script( 'moshai-admin', MOSHAI_URL . 'assets/js/admin.js', array(), MOSHAI_VERSION, true );
		$slugs = array();
		foreach ( array_keys( self::tabs() ) as $tab ) {
			$slugs[ $tab ] = self::slug( $tab );
		}
		$manage = current_user_can( 'manage_options' );
		wp_localize_script(
			'moshai-admin',
			'MoshAIAdmin',
			array(
				'rest'            => esc_url_raw( rest_url( 'ai-mcp/v1/' ) ),
				'nonce'           => wp_create_nonce( 'wp_rest' ),
				'adminUrl'        => admin_url( 'admin.php' ),
				'slugs'           => $slugs,
				'tab'             => self::current_tab(),
				'defaultProvider' => MoshAI_Settings::get( 'default_provider' ),
				'bots'            => $manage ? MoshAI_Chatbots::all() : new stdClass(),
				'botDefaults'     => MoshAI_Chatbots::defaults( '' ),
				'forms'           => $manage ? (object) MoshAI_Forms::all() : new stdClass(),
				'formDefaults'    => MoshAI_Forms::defaults( '' ),
				'popupBot'        => MoshAI_Settings::get( 'popup_bot' ),
				'canManage'       => $manage,
				'hasAI'           => MoshAI_Settings::has_ai(),
				'hasMediaAI'      => MoshAI_Settings::has_media_ai(),
			)
		);
	}

	/* ---------- Small UI helpers ---------- */

	private static function provider_select( $name, $selected, $with_default = true ) {
		$h = '<select name="' . esc_attr( $name ) . '" data-moshai-provider>';
		if ( $with_default ) {
			$h .= '<option value="">' . esc_html__( 'Default (from Settings)', 'ai-mcp' ) . '</option>';
		}
		foreach ( MoshAI_Providers::names() as $k => $label ) {
			$h .= '<option value="' . esc_attr( $k ) . '"' . selected( $selected, $k, false ) . '>' . esc_html( $label ) . '</option>';
		}
		return $h . '</select>';
	}

	private static function model_input( $name, $value, $provider = '' ) {
		$p = $provider ? $provider : MoshAI_Settings::get( 'default_provider' );
		return '<input type="text" name="' . esc_attr( $name ) . '" value="' . esc_attr( $value ) . '" list="moshai-models-' . esc_attr( $p ) . '" placeholder="' . esc_attr__( 'Default model', 'ai-mcp' ) . '" data-moshai-model autocomplete="off">';
	}

	private static function field( $label, $control, $help = '', $class = '' ) {
		return '<div class="mx-field ' . esc_attr( $class ) . '"><span class="mx-label">' . esc_html( $label ) . '</span>' . $control . ( $help ? '<span class="mx-help">' . esc_html( $help ) . '</span>' : '' ) . '</div>';
	}

	private static function toggle( $name, $on, $label, $desc = '', $extra = '' ) {
		return '<label class="mx-toggle"><input type="checkbox" name="' . esc_attr( $name ) . '" value="1"' . checked( ! empty( $on ), true, false ) . ' ' . $extra . '><span class="mx-switch" aria-hidden="true"></span><span class="mx-toggle-text"><strong>' . esc_html( $label ) . '</strong>' . ( $desc ? '<small>' . esc_html( $desc ) . '</small>' : '' ) . '</span></label>';
	}

	private static function num( $name, $value, $min, $max, $step = 1 ) {
		return '<input type="number" name="' . esc_attr( $name ) . '" value="' . esc_attr( $value ) . '" min="' . esc_attr( $min ) . '" max="' . esc_attr( $max ) . '" step="' . esc_attr( $step ) . '">';
	}

	private static function text( $name, $value, $placeholder = '' ) {
		return '<input type="text" name="' . esc_attr( $name ) . '" value="' . esc_attr( $value ) . '" placeholder="' . esc_attr( $placeholder ) . '">';
	}

	private static function area( $name, $value, $rows = 4, $placeholder = '', $class = '' ) {
		return '<textarea name="' . esc_attr( $name ) . '" rows="' . (int) $rows . '" placeholder="' . esc_attr( $placeholder ) . '" class="' . esc_attr( $class ) . '">' . esc_textarea( $value ) . '</textarea>';
	}

	private static function go( $tab, $label, $class = 'button' ) {
		return '<a class="' . esc_attr( $class ) . '" href="' . esc_url( self::url( strtok( $tab, ':' ) ) ) . '" data-go="' . esc_attr( $tab ) . '">' . esc_html( $label ) . '</a>';
	}

	private static function connected() {
		return MoshAI_Settings::connected();
	}

	/** Tabs that need a connected AI. */
	public static function locked( $tab ) {
		if ( in_array( $tab, array( 'agent', 'chatbots', 'studio', 'forms', 'knowledge' ), true ) && ! MoshAI_Settings::has_ai() ) {
			return 'ai';
		}
		if ( 'knowledge' === $tab && ! MoshAI_Settings::has_media_ai() ) {
			return 'media';
		}
		return '';
	}

	private static function lock_card( $tab, $why ) {
		$tabs = self::tabs();
		echo '<div class="mx-card mx-locked"><div class="mx-lock-icon"><span class="dashicons dashicons-lock"></span></div>';
		if ( 'media' === $why ) {
			/* translators: %s feature name */
			echo '<h2>' . esc_html( sprintf( __( '%s needs OpenAI or Google Gemini', 'ai-mcp' ), $tabs[ $tab ][0] ) ) . '</h2><p>' . esc_html__( 'Your connected AI can chat and write, but it cannot read documents into a knowledge base. Add an OpenAI key, or a free Gemini key, to unlock it.', 'ai-mcp' ) . '</p>';
		} else {
			/* translators: %s feature name */
			echo '<h2>' . esc_html( sprintf( __( 'Connect an AI to unlock %s', 'ai-mcp' ), $tabs[ $tab ][0] ) ) . '</h2><p>' . esc_html__( 'AI features stay locked until an AI is connected, so nothing on your site calls an AI by accident. OpenAI, Claude, Gemini, OpenRouter or any other AI with an OpenAI-compatible API works. Gemini has a free tier.', 'ai-mcp' ) . '</p>';
		}
		MoshAI_Kses::out( '<p>' . self::go( 'settings', __( 'Connect an AI', 'ai-mcp' ), 'button button-primary button-hero' ) . '</p>' );
		MoshAI_Kses::out( '<p class="mx-help">' . esc_html__( 'MCP works without an API key: Claude, ChatGPT, Cursor or ClickUp bring their own AI.', 'ai-mcp' ) . ' ' . self::go( 'mcp', __( 'Set up MCP', 'ai-mcp' ), '' ) . '</p></div>' );
	}

	/** One line of the AI changes feed. Mirrors feedHtml() in admin.js. */
	private static function feed_item( $it ) {
		$cls  = $it['blocked'] ? 'is-blocked' : ( $it['ok'] ? 'is-ok' : 'is-fail' );
		$icon = $it['blocked'] ? 'shield' : ( $it['ok'] ? 'yes-alt' : 'warning' );
		$what = $it['link'] ? '<a href="' . esc_url( $it['link'] ) . '">' . esc_html( $it['summary'] ) . '</a>' : esc_html( $it['summary'] );
		return '<li class="' . $cls . '"><span class="dashicons dashicons-' . $icon . '"></span><div><div class="mx-feed-what">' . $what . '</div><div class="mx-feed-meta">' . esc_html( $it['who'] ) . ' · <span title="' . esc_attr( $it['date'] ) . '">' . esc_html( $it['ago'] ) . '</span></div></div></li>';
	}

	private static function feed( $limit = 8 ) {
		$items = MoshAI_Activity::recent( $limit );
		$html  = '';
		foreach ( $items as $it ) {
			$html .= self::feed_item( $it );
		}
		return '<ul class="mx-feed" data-feed>' . ( $html ? $html : '<li class="mx-feed-empty">' . esc_html__( 'No AI changes yet. When an AI edits something, it shows up here.', 'ai-mcp' ) . '</li>' ) . '</ul>';
	}

	private static function mcp_pill() {
		$m = MoshAI_Activity::mcp_status();
		return '<a class="mx-pill mcp-' . esc_attr( $m['state'] ) . '" href="' . esc_url( self::url( 'mcp' ) ) . '" data-go="mcp" data-mcp-pill><span class="mx-dot"></span><span data-mcp-text>' . esc_html( $m['label'] ) . '</span></a>';
	}

	private static function stat( $label, $value, $sub = '' ) {
		return '<div class="mx-stat"><span class="mx-stat-val">' . esc_html( $value ) . '</span><span class="mx-stat-label">' . esc_html( $label ) . '</span>' . ( $sub ? '<span class="mx-stat-sub">' . wp_kses_post( $sub ) . '</span>' : '' ) . '</div>';
	}

	/* ---------- Page shell ---------- */

	public static function page() {
		$tab       = self::current_tab();
		$s         = MoshAI_Settings::all();
		$connected = self::connected();
		$names     = MoshAI_Providers::names();

		echo '<div class="wrap moshai-app"><h1 class="screen-reader-text">AI MCP</h1>';
		echo '<header class="mx-top"><div class="mx-brand"><img src="' . esc_url( MOSHAI_URL . 'assets/img/logo.svg' ) . '" alt="WEBMOSH" width="34" height="34"><span>AI MCP<small>' . esc_html__( 'by WEBMOSH', 'ai-mcp' ) . '</small></span><span class="mx-ver">' . esc_html( MOSHAI_VERSION ) . '</span></div><div class="mx-pills">';
		if ( $connected ) {
			$label = isset( $connected[ $s['default_provider'] ] ) ? $names[ $s['default_provider'] ] . ' · ' . $s['default_model'] : implode( ', ', $connected );
			/* translators: %s provider and model */
			echo '<a class="mx-pill is-on" href="' . esc_url( self::url( 'settings' ) ) . '" data-go="settings"><span class="mx-dot"></span>' . esc_html( sprintf( __( 'API connected · %s', 'ai-mcp' ), $label ) ) . '</a>';
		} else {
			echo '<a class="mx-pill is-off" href="' . esc_url( self::url( 'settings' ) ) . '" data-go="settings"><span class="mx-dot"></span>' . esc_html__( 'No API connected', 'ai-mcp' ) . '</a>';
		}
		if ( current_user_can( 'manage_options' ) ) {
			MoshAI_Kses::out( self::mcp_pill() );
		}
		echo '</div>';
		echo '</header><div class="mx-shell"><nav class="mx-tabs mx-side" role="tablist" aria-label="AI MCP">';
		$groups = self::groups();
		$group  = null;
		foreach ( self::tabs() as $k => $t ) {
			if ( current_user_can( $t[1] ) ) {
				$g = isset( $t[4] ) ? $t[4] : 'site';
				if ( $g !== $group ) {
					$group = $g;
					if ( ! empty( $groups[ $g ] ) ) {
						echo '<span class="mx-side-label">' . esc_html( $groups[ $g ] ) . '</span>';
					}
				}
				$lock = self::locked( $k );
				echo '<a role="tab" href="' . esc_url( self::url( $k ) ) . '" data-go="' . esc_attr( $k ) . '" class="mx-tab' . ( $k === $tab ? ' is-active' : '' ) . ( $lock ? ' is-locked' : '' ) . '"' . ( $lock ? ' title="' . esc_attr__( 'Connect an AI to unlock', 'ai-mcp' ) . '"' : '' ) . '><span class="dashicons ' . esc_attr( $lock ? 'dashicons-lock' : $t[2] ) . '"></span>' . esc_html( $t[0] ) . '</a>';
			}
		}
		echo '</nav><main class="mx-main">';

		foreach ( MoshAI_Providers::models() as $p => $models ) {
			echo '<datalist id="moshai-models-' . esc_attr( $p ) . '">';
			foreach ( $models as $m ) {
				echo '<option value="' . esc_attr( $m ) . '"></option>';
			}
			echo '</datalist>';
		}

		foreach ( self::tabs() as $k => $t ) {
			if ( ! current_user_can( $t[1] ) ) {
				continue;
			}
			echo '<section class="mx-panel" data-panel="' . esc_attr( $k ) . '"' . ( $k === $tab ? '' : ' hidden' ) . '>';
			$lock = self::locked( $k );
			if ( $lock ) {
				self::lock_card( $k, $lock );
			} elseif ( method_exists( __CLASS__, 'panel_' . $k ) ) {
				call_user_func( array( __CLASS__, 'panel_' . $k ) );
			} elseif ( ! empty( $t[3] ) && is_callable( $t[3] ) ) {
				call_user_func( $t[3] );
			}
			echo '</section>';
		}
		echo '</main></div><div class="mx-toast" role="status" aria-live="polite" hidden></div></div>';
	}

	/* ---------- Home ---------- */

	private static function panel_home() {
		global $wpdb;
		$s         = MoshAI_Settings::all();
		$connected = self::connected();
		$logs      = MoshAI_DB::table( 'logs' );
		$req24     = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM $logs WHERE time > %s", gmdate( 'Y-m-d H:i:s', time() - DAY_IN_SECONDS ) ) ); // phpcs:ignore
		$tok30     = (int) $wpdb->get_var( $wpdb->prepare( "SELECT SUM(tokens_in + tokens_out) FROM $logs WHERE time > %s", gmdate( 'Y-m-d H:i:s', time() - 30 * DAY_IN_SECONDS ) ) ); // phpcs:ignore
		$manage    = current_user_can( 'manage_options' );

		if ( ! $connected && $manage ) {
			echo '<div class="mx-card mx-hero"><div><h2>' . esc_html__( 'Connect your AI in 30 seconds', 'ai-mcp' ) . '</h2><p>' . esc_html__( 'Pick a provider, paste your key, done. Everything else works out of the box.', 'ai-mcp' ) . '</p></div>';
			echo '<div class="mx-connect" data-connect-box><select name="provider">';
			foreach ( MoshAI_Providers::names() as $p => $label ) {
				if ( 'custom' !== $p ) {
					echo '<option value="' . esc_attr( $p ) . '">' . esc_html( $label ) . '</option>';
				}
			}
			echo '</select>';
			echo '<input type="password" name="key" placeholder="' . esc_attr__( 'Paste your API key', 'ai-mcp' ) . '" autocomplete="new-password" spellcheck="false">';
			echo '<button type="button" class="button button-primary button-hero" data-connect>' . esc_html__( 'Connect', 'ai-mcp' ) . '</button></div>';
			MoshAI_Kses::out( '<p class="mx-help">' . esc_html__( 'OpenAI or Gemini unlock everything (chat, images, knowledge base). Gemini has a free tier. Claude and OpenRouter work for chat and writing.', 'ai-mcp' ) . ' <a href="https://aistudio.google.com/app/apikey" target="_blank" rel="noopener">' . esc_html__( 'Get a free Gemini key', 'ai-mcp' ) . '</a> · ' . self::go( 'settings', __( 'Using DeepSeek, Groq, Mistral, Grok, Ollama or another AI? Set it up under Others', 'ai-mcp' ), '' ) . '</p></div>' );
		}

		echo '<div class="mx-grid mx-grid-4">';
		MoshAI_Kses::out( self::stat( __( 'Requests today', 'ai-mcp' ), number_format_i18n( $req24 ) ) );
		MoshAI_Kses::out( self::stat( __( 'Tokens, 30 days', 'ai-mcp' ), number_format_i18n( $tok30 ) ) );
		MoshAI_Kses::out( self::stat( __( 'Knowledge chunks', 'ai-mcp' ), number_format_i18n( MoshAI_Core::kb_count() ) ) );
		MoshAI_Kses::out( self::stat( __( 'Chatbots', 'ai-mcp' ), count( MoshAI_Chatbots::all() ) ) );
		echo '</div>';

		if ( $manage ) {
			$m = MoshAI_Activity::mcp_status();
			echo '<div class="mx-grid mx-grid-home">';
			MoshAI_Kses::out( '<div class="mx-card"><h2><span class="dashicons dashicons-backup"></span>' . esc_html__( 'What AI changed on your site', 'ai-mcp' ) . ' <span class="mx-h2-link">' . self::go( 'activity', __( 'See all', 'ai-mcp' ), '' ) . '</span></h2>' );
			echo '<p class="mx-help">' . esc_html__( 'Every change made through MCP or the AI Agent, plus blocked attempts. Updates live.', 'ai-mcp' ) . '</p>';
			MoshAI_Kses::out( self::feed( 8 ) );
			echo '</div>';
			echo '<div class="mx-card mx-mcp-card"><h2><span class="dashicons dashicons-rest-api"></span>' . esc_html__( 'MCP connection', 'ai-mcp' ) . '</h2>';
			MoshAI_Kses::out( '<div class="mx-mcp-big">' . self::mcp_pill() . '</div>' );
			$clients = array_slice( MoshAI_Activity::clients(), 0, 3 );
			if ( $clients ) {
				echo '<ul class="mx-mini-list">';
				foreach ( $clients as $c ) {
					echo '<li><span class="mx-state mx-state-' . esc_attr( $c['state'] ) . '"></span><strong>' . esc_html( $c['name'] ) . '</strong><span>' . esc_html( $c['ago'] ) . '</span></li>';
				}
				echo '</ul>';
			} else {
				echo '<p class="mx-help">' . esc_html__( 'No AI has connected yet. Connect Claude, ChatGPT, Cursor or ClickUp in a minute, no API key needed.', 'ai-mcp' ) . '</p>';
			}
			MoshAI_Kses::out( '<p>' . self::go( 'mcp', 'disabled' === $m['state'] ? __( 'Turn on MCP', 'ai-mcp' ) : __( 'Manage MCP', 'ai-mcp' ), 'button' ) . '</p></div>' );
			echo '</div>';
		}

		echo '<div class="mx-grid mx-grid-2">';
		echo '<div class="mx-card"><h2>' . esc_html__( 'Quick actions', 'ai-mcp' ) . '</h2><div class="mx-actions">';
		MoshAI_Kses::out( self::go( 'studio:article', __( 'Write an article', 'ai-mcp' ), 'mx-action' ) );
		MoshAI_Kses::out( self::go( 'studio:image', __( 'Generate an image', 'ai-mcp' ), 'mx-action' ) );
		MoshAI_Kses::out( self::go( 'studio:chat', __( 'Open the playground', 'ai-mcp' ), 'mx-action' ) );
		if ( $manage ) {
			MoshAI_Kses::out( self::go( 'agent', __( 'Ask the AI Agent', 'ai-mcp' ), 'mx-action' ) );
			MoshAI_Kses::out( self::go( 'chatbots', __( 'Edit chatbot', 'ai-mcp' ), 'mx-action' ) );
			MoshAI_Kses::out( self::go( 'knowledge', __( 'Sync knowledge', 'ai-mcp' ), 'mx-action' ) );
			MoshAI_Kses::out( self::go( 'mcp', __( 'Connect Claude / Cursor', 'ai-mcp' ), 'mx-action' ) );
		}
		echo '</div></div>';

		if ( $manage ) {
			echo '<div class="mx-card"><h2>' . esc_html__( 'Site chatbot', 'ai-mcp' ) . '</h2>';
			echo '<div class="mx-field"><span class="mx-label">' . esc_html__( 'Floating chat bubble on every page', 'ai-mcp' ) . '</span><select name="popup_bot" data-instant data-popup-select><option value="">' . esc_html__( 'Off', 'ai-mcp' ) . '</option>';
			foreach ( MoshAI_Chatbots::all() as $id => $b ) {
				echo '<option value="' . esc_attr( $id ) . '"' . selected( $s['popup_bot'], $id, false ) . '>' . esc_html( $b['name'] ) . '</option>';
			}
			echo '</select></div>';
			echo '<p class="mx-help">' . esc_html__( 'Or place it inside a page with a shortcode (click to copy):', 'ai-mcp' ) . '</p><p><code class="moshai-copy">[ai_mcp_chatbot id="default"]</code></p>';
			echo '<p class="mx-help">' . esc_html__( 'Works in Elementor too: drop a Shortcode widget and paste it.', 'ai-mcp' ) . '</p></div>';
		}
		echo '</div>';
	}

	/* ---------- AI Agent ---------- */

	private static function panel_agent() {
		$s     = MoshAI_Settings::all();
		$woo   = class_exists( 'WooCommerce' );
		$tools = MoshAI_MCP::tools();
		$names = MoshAI_Providers::names();
		$prov  = $s['agent_provider'] ? $s['agent_provider'] : $s['default_provider'];
		$model = $s['agent_model'] ? $s['agent_model'] : ( $prov === $s['default_provider'] ? $s['default_model'] : '' );

		$suggest = array(
			__( 'What can you do on this site?', 'ai-mcp' ),
			__( 'List my last 5 drafts', 'ai-mcp' ),
			__( 'Write a blog post about choosing a WordPress theme and save it as a draft', 'ai-mcp' ),
			__( 'Show comments waiting for approval', 'ai-mcp' ),
		);
		if ( $woo ) {
			$suggest = array_merge(
				array(
					__( 'Show today\'s orders', 'ai-mcp' ),
					__( 'Sales report for the last 7 days', 'ai-mcp' ),
					__( 'Which products are low on stock (5 or less)?', 'ai-mcp' ),
					__( 'Mark order #123 as completed', 'ai-mcp' ),
					__( 'Set the stock of [product name] to 20', 'ai-mcp' ),
					__( 'Create a 15% coupon WELCOME15 that expires in 30 days', 'ai-mcp' ),
				),
				array_slice( $suggest, 0, 2 )
			);
		}

		echo '<div class="mx-agent" data-agent>';
		echo '<div class="mx-card mx-agent-main">';
		echo '<div class="mx-agent-head"><div><h2>' . esc_html__( 'AI Agent', 'ai-mcp' ) . '</h2><p class="mx-help">' . esc_html( sprintf( /* translators: %s: provider and model */ __( 'Talk to your site. Running on %s.', 'ai-mcp' ), $names[ $prov ] . ( $model ? ' · ' . $model : '' ) ) ) . '</p></div>';
		echo '<button type="button" class="button" data-agent-new><span class="dashicons dashicons-plus-alt2"></span> ' . esc_html__( 'New chat', 'ai-mcp' ) . '</button></div>';
		echo '<div class="mx-agent-log" data-agent-log aria-live="polite"></div>';
		echo '<div class="mx-agent-empty" data-agent-empty><span class="dashicons dashicons-format-status"></span><h3>' . esc_html__( 'What should we get done?', 'ai-mcp' ) . '</h3><p>' . esc_html__( 'It can write and publish posts, edit pages and Elementor text, manage orders, refunds, stock, products, coupons, users, comments, plugins and settings.', 'ai-mcp' ) . '</p><div class="mx-chips">';
		foreach ( $suggest as $q ) {
			echo '<button type="button" class="mx-chip" data-agent-suggest>' . esc_html( $q ) . '</button>';
		}
		echo '</div></div>';
		echo '<div class="mx-agent-pending" data-agent-pending hidden></div>';
		echo '<form class="mx-agent-input" data-agent-form><textarea rows="1" data-agent-text placeholder="' . esc_attr__( 'Tell it what to do... (Enter to send, Shift+Enter for a new line)', 'ai-mcp' ) . '"></textarea><button type="submit" class="button button-primary" data-agent-send>' . esc_html__( 'Send', 'ai-mcp' ) . '</button></form>';
		echo '</div>';

		echo '<aside class="mx-agent-side">';
		echo '<div class="mx-card"><h3>' . esc_html__( 'Agent brain', 'ai-mcp' ) . '</h3>';
		MoshAI_Kses::out( self::field( __( 'Provider', 'ai-mcp' ), str_replace( '<select ', '<select data-instant ', self::provider_select( 'agent_provider', $s['agent_provider'] ) ) ) );
		MoshAI_Kses::out( self::field( __( 'Model', 'ai-mcp' ), str_replace( '<input ', '<input data-instant ', self::model_input( 'agent_model', $s['agent_model'], $prov ) ), __( 'Use a strong tool-calling model: gpt-4.1, gpt-5, Claude Sonnet, Gemini 2.5 Pro.', 'ai-mcp' ) ) );
		MoshAI_Kses::out( self::toggle( 'agent_confirm', $s['agent_confirm'], __( 'Ask before making changes', 'ai-mcp' ), __( 'Reading is instant. Posting, editing, orders, stock, refunds wait for your OK.', 'ai-mcp' ), 'data-instant' ) );
		MoshAI_Kses::out( self::field( __( 'Max steps per request', 'ai-mcp' ), str_replace( '<input ', '<input data-instant ', self::num( 'agent_max_steps', $s['agent_max_steps'], 3, 40 ) ) ) );
		MoshAI_Kses::out( self::field( __( 'House rules for the agent', 'ai-mcp' ), str_replace( '<textarea ', '<textarea data-instant ', self::area( 'agent_instructions', $s['agent_instructions'], 3, __( 'e.g. Always write in US English. Never publish without asking.', 'ai-mcp' ) ) ) ) );
		echo '</div>';
		echo '<div class="mx-card"><h3>' . esc_html( sprintf( /* translators: %d: number of tools */ __( '%d tools ready', 'ai-mcp' ), count( $tools ) ) ) . '</h3><ul class="mx-tool-groups">';
		$groups = array(
			__( 'Posts, pages & Elementor', 'ai-mcp' ) => true,
			__( 'Media & alt text', 'ai-mcp' )        => true,
			__( 'Comments, users, plugins, options', 'ai-mcp' ) => true,
			__( 'WooCommerce: orders, refunds, products, stock, coupons, sales', 'ai-mcp' ) => $woo,
			__( 'Any REST route (/wp/v2, /wc/v3...)', 'ai-mcp' ) => true,
		);
		foreach ( $groups as $label => $on ) {
			echo '<li class="' . ( $on ? 'is-on' : 'is-off' ) . '"><span class="dashicons dashicons-' . ( $on ? 'yes' : 'minus' ) . '"></span>' . esc_html( $label ) . '</li>';
		}
		MoshAI_Kses::out( '</ul><p class="mx-help">' . esc_html__( 'Prefer Claude Desktop, ChatGPT or Cursor? Same tools over MCP:', 'ai-mcp' ) . ' ' . self::go( 'mcp', __( 'set it up', 'ai-mcp' ), 'button-link' ) . '</p></div>' );
		echo '</aside></div>';
	}

	/* ---------- Chatbots ---------- */

	private static function panel_chatbots() {
		echo '<div class="mx-editor" data-editor="bots">';
		echo '<aside class="mx-card mx-list-col"><div class="mx-list-head"><h2>' . esc_html__( 'Chatbots', 'ai-mcp' ) . '</h2><button type="button" class="button button-primary" data-new>+ ' . esc_html__( 'New', 'ai-mcp' ) . '</button></div><ul class="mx-list" data-list></ul></aside>';
		echo '<div class="mx-card mx-edit-col" data-fields>';
		echo '<div class="mx-edit-head"><h2 data-edit-title></h2><code class="moshai-copy" data-shortcode></code></div><input type="hidden" name="id">';
		echo '<h3 class="mx-section">' . esc_html__( 'Basics', 'ai-mcp' ) . '</h3><div class="mx-grid mx-grid-2">';
		MoshAI_Kses::out( self::field( __( 'Name (admin only)', 'ai-mcp' ), self::text( 'name', '' ) ) );
		MoshAI_Kses::out( self::field( __( 'Window title', 'ai-mcp' ), self::text( 'title', '' ) ) );
		MoshAI_Kses::out( self::field( __( 'ID for the shortcode', 'ai-mcp' ), self::text( 'slug', '', __( 'auto from name', 'ai-mcp' ) ), '', 'mx-new-only' ) );
		MoshAI_Kses::out( self::field( __( 'Welcome message', 'ai-mcp' ), self::text( 'start', '' ), '', 'mx-span-2' ) );
		MoshAI_Kses::out( self::field( __( 'Instructions', 'ai-mcp' ), self::area( 'instructions', '', 6 ), __( 'Who the bot is and how it answers. Placeholders: {site}, {site_url}, {date}, {user_name}', 'ai-mcp' ), 'mx-span-2' ) );
		echo '</div>';
		echo '<h3 class="mx-section">' . esc_html__( 'Brain', 'ai-mcp' ) . '</h3><div class="mx-grid mx-grid-2">';
		MoshAI_Kses::out( self::field( __( 'Provider', 'ai-mcp' ), self::provider_select( 'provider', '' ) ) );
		MoshAI_Kses::out( self::field( __( 'Model', 'ai-mcp' ), self::model_input( 'model', '' ) ) );
		MoshAI_Kses::out( '<div class="mx-span-2">' . self::toggle( 'kb', 0, __( 'Answer from the knowledge base', 'ai-mcp' ), __( 'Uses your synced site content. Sync it in the Knowledge tab.', 'ai-mcp' ) ) . '</div>' );
		MoshAI_Kses::out( '<div class="mx-span-2">' . self::toggle( 'sources', 1, __( 'Show source links under answers', 'ai-mcp' ) ) . '</div>' );
		echo '</div>';
		echo '<h3 class="mx-section">' . esc_html__( 'Look and access', 'ai-mcp' ) . '</h3><div class="mx-grid mx-grid-2">';
		MoshAI_Kses::out( self::field( __( 'Color', 'ai-mcp' ), '<input type="color" name="color">' ) );
		MoshAI_Kses::out( self::field( __( 'Bubble position', 'ai-mcp' ), '<select name="position"><option value="right">' . esc_html__( 'Bottom right', 'ai-mcp' ) . '</option><option value="left">' . esc_html__( 'Bottom left', 'ai-mcp' ) . '</option></select>' ) );
		MoshAI_Kses::out( '<div class="mx-span-2">' . self::toggle( 'site_popup', 0, __( 'Show as the floating chat bubble on every page', 'ai-mcp' ) ) . '</div>' );
		MoshAI_Kses::out( '<div class="mx-span-2">' . self::toggle( 'guests', 1, __( 'Visitors who are not logged in can chat', 'ai-mcp' ) ) . '</div>' );
		echo '</div>';
		echo '<details class="mx-advanced"><summary>' . esc_html__( 'Advanced', 'ai-mcp' ) . '</summary><div class="mx-grid mx-grid-3">';
		MoshAI_Kses::out( self::field( __( 'Input placeholder', 'ai-mcp' ), self::text( 'placeholder', '' ) ) );
		MoshAI_Kses::out( self::field( __( 'Send button', 'ai-mcp' ), self::text( 'send', '' ) ) );
		MoshAI_Kses::out( self::field( __( 'Creativity (temperature)', 'ai-mcp' ), self::num( 'temperature', 0.7, 0, 2, 0.1 ) ) );
		MoshAI_Kses::out( self::field( __( 'Max answer tokens', 'ai-mcp' ), self::num( 'max_tokens', 1024, 16, 64000 ) ) );
		MoshAI_Kses::out( self::field( __( 'Messages of memory', 'ai-mcp' ), self::num( 'context', 10, 0, 50 ) ) );
		MoshAI_Kses::out( self::field( __( 'Max characters per message', 'ai-mcp' ), self::num( 'max_input', 1000, 50, 10000 ) ) );
		MoshAI_Kses::out( self::field( __( 'Knowledge chunks per answer', 'ai-mcp' ), self::num( 'kb_k', 3, 1, 10 ) ) );
		echo '</div></details>';
		echo '<div class="mx-edit-actions"><button type="button" class="button button-primary button-large" data-save>' . esc_html__( 'Save chatbot', 'ai-mcp' ) . '</button><button type="button" class="button-link button-link-delete" data-delete>' . esc_html__( 'Delete', 'ai-mcp' ) . '</button></div>';
		echo '</div>';
		echo '<aside class="mx-card mx-preview-col"><h2>' . esc_html__( 'Live test', 'ai-mcp' ) . '</h2><p class="mx-help">' . esc_html__( 'Chat with the saved version. Save to test changes.', 'ai-mcp' ) . '</p><div data-preview></div></aside>';
		echo '</div>';
	}

	/* ---------- Studio ---------- */

	private static function panel_studio() {
		$s = MoshAI_Settings::all();
		echo '<div class="mx-subtabs" data-subtabs><button type="button" data-sub="chat" class="is-active">' . esc_html__( 'Playground', 'ai-mcp' ) . '</button><button type="button" data-sub="article">' . esc_html__( 'Article writer', 'ai-mcp' ) . '</button><button type="button" data-sub="image">' . esc_html__( 'Images', 'ai-mcp' ) . '</button></div>';

		echo '<div class="mx-card moshai-tool" data-tool="playground" data-subpanel="chat">';
		echo '<div class="mx-grid mx-grid-4">';
		MoshAI_Kses::out( self::field( __( 'Provider', 'ai-mcp' ), self::provider_select( 'provider', '' ) ) );
		MoshAI_Kses::out( self::field( __( 'Model', 'ai-mcp' ), self::model_input( 'model', '' ) ) );
		MoshAI_Kses::out( self::field( __( 'Creativity', 'ai-mcp' ), self::num( 'temperature', $s['temperature'], 0, 2, 0.1 ) ) );
		MoshAI_Kses::out( self::field( __( 'Max tokens', 'ai-mcp' ), self::num( 'max_tokens', $s['max_tokens'], 16, 64000 ) ) );
		echo '</div>';
		MoshAI_Kses::out( self::field( __( 'System instructions (optional)', 'ai-mcp' ), self::area( 'system', '', 2 ) ) );
		MoshAI_Kses::out( self::field( __( 'Prompt', 'ai-mcp' ), self::area( 'prompt', '', 7, __( 'Ask anything... (Ctrl+Enter to run)', 'ai-mcp' ) ) ) );
		echo '<div class="mx-row"><button type="button" class="button button-primary button-large" data-run>' . esc_html__( 'Run', 'ai-mcp' ) . '</button><span class="moshai-meta"></span></div>';
		echo '<div class="moshai-output" hidden></div></div>';

		echo '<div class="mx-card moshai-tool" data-tool="content" data-subpanel="article" hidden>';
		MoshAI_Kses::out( self::field( __( 'Topic or working title', 'ai-mcp' ), self::text( 'topic', '', __( 'e.g. How to pick a logo color palette that converts', 'ai-mcp' ) ) ) );
		echo '<div class="mx-grid mx-grid-3">';
		MoshAI_Kses::out( self::field( __( 'Language', 'ai-mcp' ), self::text( 'language', 'English' ) ) );
		$tones = '<select name="tone">';
		foreach ( array( 'Friendly and expert', 'Professional', 'Casual', 'Bold and opinionated', 'Playful', 'Academic' ) as $t ) {
			$tones .= '<option>' . esc_html( $t ) . '</option>';
		}
		MoshAI_Kses::out( self::field( __( 'Tone', 'ai-mcp' ), $tones . '</select>' ) );
		MoshAI_Kses::out( self::field( __( 'Length (words)', 'ai-mcp' ), self::num( 'words', 1200, 200, 5000, 100 ) ) );
		MoshAI_Kses::out( self::field( __( 'Sections', 'ai-mcp' ), self::num( 'sections', 5, 2, 15 ) ) );
		MoshAI_Kses::out( self::field( __( 'Provider', 'ai-mcp' ), self::provider_select( 'provider', '' ) ) );
		MoshAI_Kses::out( self::field( __( 'Model', 'ai-mcp' ), self::model_input( 'model', '' ) ) );
		echo '</div>';
		MoshAI_Kses::out( self::field( __( 'Extra instructions (optional)', 'ai-mcp' ), self::area( 'extra', '', 2, __( 'Audience, keywords, call to action...', 'ai-mcp' ) ) ) );
		echo '<div class="mx-row"><button type="button" class="button button-primary button-large" data-run>' . esc_html__( 'Write article', 'ai-mcp' ) . '</button><span class="moshai-meta"></span></div>';
		echo '<div class="moshai-content-result" hidden><hr>';
		echo '<div class="mx-field"><span class="mx-label">' . esc_html__( 'Title', 'ai-mcp' ) . '</span><input type="text" data-field="title"></div>';
		echo '<div class="mx-field"><span class="mx-label">' . esc_html__( 'Excerpt', 'ai-mcp' ) . '</span><textarea data-field="excerpt" rows="2"></textarea></div>';
		echo '<div class="mx-grid mx-grid-2"><div class="mx-field"><span class="mx-label">' . esc_html__( 'HTML', 'ai-mcp' ) . '</span><textarea data-field="content" rows="18" class="code"></textarea></div><div class="mx-field"><span class="mx-label">' . esc_html__( 'Preview', 'ai-mcp' ) . '</span><div class="moshai-preview"></div></div></div>';
		echo '<div class="mx-row"><button type="button" class="button button-primary" data-save-draft="post">' . esc_html__( 'Save as draft post', 'ai-mcp' ) . '</button><button type="button" class="button" data-save-draft="page">' . esc_html__( 'Save as draft page', 'ai-mcp' ) . '</button><span class="moshai-saved"></span></div>';
		echo '</div><div class="moshai-output" hidden></div></div>';

		if ( ! MoshAI_Settings::has_media_ai() ) {
			MoshAI_Kses::out( '<div class="mx-card mx-locked" data-subpanel="image" hidden><div class="mx-lock-icon"><span class="dashicons dashicons-lock"></span></div><h2>' . esc_html__( 'Images need OpenAI or Google Gemini', 'ai-mcp' ) . '</h2><p>' . esc_html__( 'Add an OpenAI key or a free Gemini key to generate images.', 'ai-mcp' ) . '</p><p>' . self::go( 'settings', __( 'Add a key', 'ai-mcp' ), 'button button-primary' ) . '</p></div>' );
		} else {
		echo '<div class="mx-card moshai-tool" data-tool="image" data-subpanel="image" hidden>';
		MoshAI_Kses::out( self::field( __( 'Describe the image', 'ai-mcp' ), self::area( 'prompt', '', 3, __( 'Flat-lay of a designer desk with a laptop showing a logo sketch, soft daylight, minimal', 'ai-mcp' ) ) ) );
		$sizes = '<select name="size">';
		foreach ( array( '1024x1024' => __( 'Square', 'ai-mcp' ), '1536x1024' => __( 'Landscape', 'ai-mcp' ), '1024x1536' => __( 'Portrait', 'ai-mcp' ), '1792x1024' => __( 'Wide (dall-e-3)', 'ai-mcp' ), '1024x1792' => __( 'Tall (dall-e-3)', 'ai-mcp' ) ) as $v => $l ) {
			$sizes .= '<option value="' . esc_attr( $v ) . '">' . esc_html( $l . ' · ' . $v ) . '</option>';
		}
		MoshAI_Kses::out( '<div class="mx-grid mx-grid-3">' . self::field( __( 'Size', 'ai-mcp' ), $sizes . '</select>' ) );
		MoshAI_Kses::out( self::field( __( 'Model', 'ai-mcp' ), '<input type="text" name="model" list="moshai-image-models" placeholder="' . esc_attr( $s['image_model'] ) . '">' ) . '</div>' );
		echo '<datalist id="moshai-image-models"><option value="gpt-image-1"></option><option value="gpt-image-1-mini"></option><option value="dall-e-3"></option></datalist>';
		echo '<div class="mx-row"><button type="button" class="button button-primary button-large" data-run>' . esc_html__( 'Generate image', 'ai-mcp' ) . '</button><span class="mx-help">' . esc_html__( 'Saved to the Media Library with alt text.', 'ai-mcp' ) . '</span></div>';
		echo '<div class="moshai-output" hidden></div><div class="moshai-gallery"></div></div>';
		}
	}

	/* ---------- AI Forms ---------- */

	private static function panel_forms() {
		echo '<div class="mx-editor" data-editor="forms">';
		echo '<aside class="mx-card mx-list-col"><div class="mx-list-head"><h2>' . esc_html__( 'AI Forms', 'ai-mcp' ) . '</h2><button type="button" class="button button-primary" data-new>+ ' . esc_html__( 'New', 'ai-mcp' ) . '</button></div><ul class="mx-list" data-list></ul></aside>';
		echo '<div class="mx-card mx-edit-col" data-fields>';
		echo '<div class="mx-edit-head"><h2 data-edit-title></h2><code class="moshai-copy" data-shortcode></code></div><input type="hidden" name="id">';
		echo '<div class="mx-grid mx-grid-2">';
		MoshAI_Kses::out( self::field( __( 'Name', 'ai-mcp' ), self::text( 'name', '' ) ) );
		MoshAI_Kses::out( self::field( __( 'Button label', 'ai-mcp' ), self::text( 'button', '' ) ) );
		MoshAI_Kses::out( self::field( __( 'ID for the shortcode', 'ai-mcp' ), self::text( 'slug', '', __( 'auto from name', 'ai-mcp' ) ), '', 'mx-new-only' ) );
		echo '</div>';
		MoshAI_Kses::out( self::field( __( 'Fields', 'ai-mcp' ), self::area( 'fields', '', 5, '', 'code' ), __( 'One per line: name|Label|type|option1,option2. Types: text, textarea, select, number, email, url.', 'ai-mcp' ) ) );
		MoshAI_Kses::out( self::field( __( 'Prompt template', 'ai-mcp' ), self::area( 'prompt', '', 5 ), __( 'Use {name} to insert what the visitor typed in that field.', 'ai-mcp' ) ) );
		echo '<details class="mx-advanced"><summary>' . esc_html__( 'Advanced', 'ai-mcp' ) . '</summary>';
		MoshAI_Kses::out( self::field( __( 'System instructions', 'ai-mcp' ), self::area( 'system', '', 2 ) ) );
		echo '<div class="mx-grid mx-grid-4">';
		MoshAI_Kses::out( self::field( __( 'Provider', 'ai-mcp' ), self::provider_select( 'provider', '' ) ) );
		MoshAI_Kses::out( self::field( __( 'Model', 'ai-mcp' ), self::model_input( 'model', '' ) ) );
		MoshAI_Kses::out( self::field( __( 'Creativity', 'ai-mcp' ), self::num( 'temperature', 0.8, 0, 2, 0.1 ) ) );
		MoshAI_Kses::out( self::field( __( 'Max tokens', 'ai-mcp' ), self::num( 'max_tokens', 1024, 16, 64000 ) ) );
		echo '</div></details>';
		MoshAI_Kses::out( self::toggle( 'guests', 1, __( 'Visitors who are not logged in can use it', 'ai-mcp' ) ) );
		echo '<div class="mx-edit-actions"><button type="button" class="button button-primary button-large" data-save>' . esc_html__( 'Save form', 'ai-mcp' ) . '</button><button type="button" class="button-link button-link-delete" data-delete>' . esc_html__( 'Delete', 'ai-mcp' ) . '</button></div>';
		echo '</div>';
		echo '<aside class="mx-card mx-preview-col"><h2>' . esc_html__( 'Live test', 'ai-mcp' ) . '</h2><p class="mx-help">' . esc_html__( 'Uses the saved version.', 'ai-mcp' ) . '</p><div data-preview></div></aside>';
		echo '</div>';
	}

	/* ---------- Knowledge ---------- */

	private static function panel_knowledge() {
		$s      = MoshAI_Settings::all();
		$synced = (int) get_option( 'moshai_kb_synced' );
		echo '<div class="moshai-tool" data-tool="kb"><div class="mx-grid mx-grid-2">';
		echo '<div class="mx-card"><h2>' . esc_html__( 'Teach the chatbot your site', 'ai-mcp' ) . '</h2><p>' . esc_html__( 'Indexes every published post, page and product so chatbots answer from your real content. Re-running replaces, never duplicates.', 'ai-mcp' ) . '</p>';
		echo '<div class="mx-row"><button type="button" class="button button-primary button-large" data-kb-sync>' . esc_html__( 'Sync all content', 'ai-mcp' ) . '</button><span class="moshai-progress">' . ( $synced ? esc_html( sprintf( /* translators: %s time ago */ __( 'Last full sync %s ago', 'ai-mcp' ), human_time_diff( $synced ) ) ) : '' ) . '</span></div>';
		echo '<div class="mx-progressbar" hidden><span></span></div>';
		MoshAI_Kses::out( self::toggle( 'kb_autosync', $s['kb_autosync'], __( 'Keep it up to date automatically', 'ai-mcp' ), __( 'Re-indexes a post 30 seconds after you update it, removes it when trashed.', 'ai-mcp' ), 'data-instant' ) );
		echo '<h3 class="mx-section">' . esc_html__( 'Test a question', 'ai-mcp' ) . '</h3><div class="mx-row"><input type="text" data-kb-query placeholder="' . esc_attr__( 'What is your refund policy?', 'ai-mcp' ) . '"><button type="button" class="button" data-kb-search>' . esc_html__( 'Search', 'ai-mcp' ) . '</button></div><div class="moshai-kb-hits"></div></div>';
		echo '<div class="mx-card"><h2>' . esc_html__( 'Add custom knowledge', 'ai-mcp' ) . '</h2><p>' . esc_html__( 'FAQs, policies, pricing, opening hours... anything that is not on a page.', 'ai-mcp' ) . '</p>';
		echo '<div class="mx-field"><span class="mx-label">' . esc_html__( 'Title', 'ai-mcp' ) . '</span><input type="text" data-kb-title></div>';
		echo '<div class="mx-field"><span class="mx-label">' . esc_html__( 'Content', 'ai-mcp' ) . '</span><textarea rows="8" data-kb-content></textarea></div>';
		echo '<button type="button" class="button button-primary" data-kb-add>' . esc_html__( 'Add to knowledge', 'ai-mcp' ) . '</button></div>';
		echo '</div>';
		echo '<div class="mx-card"><div class="mx-list-head"><h2>' . esc_html__( 'Indexed chunks', 'ai-mcp' ) . ' <span class="mx-badge moshai-kb-total"></span></h2><button type="button" class="button-link button-link-delete" data-kb-clear>' . esc_html__( 'Delete everything', 'ai-mcp' ) . '</button></div>';
		echo '<table class="widefat striped mx-table"><thead><tr><th>' . esc_html__( 'Title', 'ai-mcp' ) . '</th><th>' . esc_html__( 'Preview', 'ai-mcp' ) . '</th><th>' . esc_html__( 'Source', 'ai-mcp' ) . '</th><th></th></tr></thead><tbody class="moshai-kb-rows"><tr><td colspan="4">' . esc_html__( 'Loading...', 'ai-mcp' ) . '</td></tr></tbody></table></div>';
		echo '<div class="moshai-output" hidden></div></div>';
	}

	/* ---------- Activity ---------- */

	private static function panel_activity() {
		global $wpdb;
		$t      = MoshAI_DB::table( 'logs' );
		$since  = gmdate( 'Y-m-d H:i:s', time() - 30 * DAY_IN_SECONDS );
		$tot    = $wpdb->get_row( $wpdb->prepare( "SELECT COUNT(*) n, SUM(tokens_in) i, SUM(tokens_out) o, SUM(units) u FROM $t WHERE time > %s", $since ), ARRAY_A ); // phpcs:ignore
		$models = $wpdb->get_results( $wpdb->prepare( "SELECT model, COUNT(*) n, SUM(tokens_in + tokens_out) tok FROM $t WHERE time > %s GROUP BY model ORDER BY n DESC LIMIT 8", $since ), ARRAY_A ); // phpcs:ignore
		$src    = $wpdb->get_results( $wpdb->prepare( "SELECT source, COUNT(*) n, SUM(tokens_in + tokens_out) tok FROM $t WHERE time > %s GROUP BY source ORDER BY n DESC", $since ), ARRAY_A ); // phpcs:ignore
		$days   = $wpdb->get_results( $wpdb->prepare( "SELECT DATE(time) d, COUNT(*) n FROM $t WHERE time > %s GROUP BY DATE(time) ORDER BY d ASC", $since ), ARRAY_A ); // phpcs:ignore

		$changes = MoshAI_Activity::recent( 150 );
		echo '<div class="mx-card"><h2><span class="dashicons dashicons-backup"></span>' . esc_html__( 'Changes made by AI', 'ai-mcp' ) . '</h2><p class="mx-help">' . esc_html__( 'Audit log of everything MCP clients and the AI Agent changed, and every blocked attempt. AI cannot edit or delete this log.', 'ai-mcp' ) . '</p>';
		echo '<div class="mx-subtabs mx-filter" data-activity-filter><button type="button" data-f="all" class="is-active">' . esc_html__( 'All', 'ai-mcp' ) . '</button><button type="button" data-f="mcp">MCP</button><button type="button" data-f="agent">' . esc_html__( 'AI Agent', 'ai-mcp' ) . '</button><button type="button" data-f="blocked">' . esc_html__( 'Blocked', 'ai-mcp' ) . '</button></div>';
		if ( $changes ) {
			echo '<div class="mx-table-wrap"><table class="widefat striped mx-table"><thead><tr><th>' . esc_html__( 'When', 'ai-mcp' ) . '</th><th>' . esc_html__( 'Who', 'ai-mcp' ) . '</th><th>' . esc_html__( 'What changed', 'ai-mcp' ) . '</th><th>' . esc_html__( 'Details', 'ai-mcp' ) . '</th></tr></thead><tbody data-activity-rows>';
			foreach ( $changes as $c ) {
				$state = $c['blocked'] ? '<span class="mx-badge is-warn">' . esc_html__( 'Blocked', 'ai-mcp' ) . '</span> ' : ( $c['ok'] ? '' : '<span class="mx-badge is-off">' . esc_html__( 'Failed', 'ai-mcp' ) . '</span> ' );
				$what  = $c['link'] ? '<a href="' . esc_url( $c['link'] ) . '">' . esc_html( $c['summary'] ) . '</a>' : esc_html( $c['summary'] );
				MoshAI_Kses::out( '<tr data-source="' . esc_attr( $c['source'] ) . '" data-blocked="' . ( $c['blocked'] ? '1' : '0' ) . '"><td title="' . esc_attr( $c['date'] ) . '">' . esc_html( $c['ago'] ) . '</td><td>' . esc_html( $c['who'] ) . '</td><td>' . $state . $what . '</td><td>' . ( '[]' !== $c['args'] && '' !== $c['args'] ? '<details><summary>' . esc_html( $c['tool'] ) . '</summary><pre class="mx-code">' . esc_html( $c['args'] ) . '</pre></details>' : '<code>' . esc_html( $c['tool'] ) . '</code>' ) . '</td></tr>' );
			}
			echo '</tbody></table></div>';
		} else {
			echo '<p class="mx-help">' . esc_html__( 'Nothing yet.', 'ai-mcp' ) . '</p>';
		}
		echo '</div>';
		echo '<h2 class="mx-section">' . esc_html__( 'AI usage, last 30 days', 'ai-mcp' ) . '</h2>';

		echo '<div class="mx-grid mx-grid-4">';
		MoshAI_Kses::out( self::stat( __( 'Requests, 30 days', 'ai-mcp' ), number_format_i18n( (int) $tot['n'] ) ) );
		MoshAI_Kses::out( self::stat( __( 'Tokens in', 'ai-mcp' ), number_format_i18n( (int) $tot['i'] ) ) );
		MoshAI_Kses::out( self::stat( __( 'Tokens out', 'ai-mcp' ), number_format_i18n( (int) $tot['o'] ) ) );
		MoshAI_Kses::out( self::stat( __( 'Images and embeddings', 'ai-mcp' ), number_format_i18n( (int) $tot['u'] ) ) );
		echo '</div>';

		$max = 1;
		foreach ( (array) $days as $d ) {
			$max = max( $max, (int) $d['n'] );
		}
		echo '<div class="mx-card"><h2>' . esc_html__( 'Requests per day', 'ai-mcp' ) . '</h2><div class="mx-bars">';
		if ( ! $days ) {
			echo '<p class="mx-help">' . esc_html__( 'No usage yet.', 'ai-mcp' ) . '</p>';
		}
		foreach ( (array) $days as $d ) {
			echo '<span class="mx-bar" style="height:' . esc_attr( max( 3, round( 100 * (int) $d['n'] / $max ) ) ) . '%" title="' . esc_attr( $d['d'] . ': ' . $d['n'] ) . '"></span>';
		}
		echo '</div></div>';

		echo '<div class="mx-grid mx-grid-2">';
		foreach ( array( __( 'By feature', 'ai-mcp' ) => array( $src, 'source' ), __( 'By model', 'ai-mcp' ) => array( $models, 'model' ) ) as $title => $data ) {
			echo '<div class="mx-card"><h2>' . esc_html( $title ) . '</h2><table class="widefat striped mx-table"><tbody>';
			if ( ! $data[0] ) {
				echo '<tr><td>' . esc_html__( 'No usage yet.', 'ai-mcp' ) . '</td></tr>';
			}
			foreach ( (array) $data[0] as $r ) {
				echo '<tr><td><strong>' . esc_html( $r[ $data[1] ] ) . '</strong></td><td>' . esc_html( number_format_i18n( (int) $r['n'] ) ) . ' ' . esc_html__( 'requests', 'ai-mcp' ) . '</td><td>' . esc_html( number_format_i18n( (int) $r['tok'] ) ) . ' ' . esc_html__( 'tokens', 'ai-mcp' ) . '</td></tr>';
			}
			echo '</tbody></table></div>';
		}
		echo '</div>';

		$dt    = MoshAI_DB::table( 'discussions' );
		$rows  = $wpdb->get_results( "SELECT * FROM $dt ORDER BY updated DESC LIMIT 40", ARRAY_A ); // phpcs:ignore
		$bots  = MoshAI_Chatbots::all();
		$count = (int) $wpdb->get_var( "SELECT COUNT(*) FROM $dt" ); // phpcs:ignore
		echo '<div class="mx-card"><div class="mx-list-head"><h2>' . esc_html__( 'Chat conversations', 'ai-mcp' ) . ' <span class="mx-badge">' . esc_html( number_format_i18n( $count ) ) . '</span></h2><span><button type="button" class="button-link button-link-delete" data-clear="discussions">' . esc_html__( 'Delete conversations', 'ai-mcp' ) . '</button> · <button type="button" class="button-link button-link-delete" data-clear="logs">' . esc_html__( 'Reset usage log', 'ai-mcp' ) . '</button></span></div>';
		if ( ! $rows ) {
			echo '<p class="mx-help">' . esc_html__( 'No conversations yet.', 'ai-mcp' ) . '</p>';
		}
		foreach ( (array) $rows as $r ) {
			$msgs = (array) json_decode( $r['messages'], true );
			$user = $r['user_id'] ? get_userdata( (int) $r['user_id'] ) : null;
			echo '<details class="mx-convo"><summary><span class="mx-convo-title">' . esc_html( $r['title'] ) . '</span><span class="mx-convo-meta">' . esc_html( ( isset( $bots[ $r['bot_id'] ] ) ? $bots[ $r['bot_id'] ]['name'] : $r['bot_id'] ) . ' · ' . ( $user ? $user->display_name : __( 'Guest', 'ai-mcp' ) ) . ' · ' . human_time_diff( strtotime( $r['updated'] . ' UTC' ) ) . ' · ' . count( $msgs ) . ' msgs' ) . '</span></summary><div class="mx-thread">';
			foreach ( $msgs as $m ) {
				$role = isset( $m['role'] ) && 'assistant' === $m['role'] ? 'assistant' : 'user';
				echo '<div class="mx-dmsg mx-dmsg-' . esc_attr( $role ) . '">' . nl2br( esc_html( isset( $m['content'] ) ? $m['content'] : '' ) ) . '</div>';
			}
			echo '</div></details>';
		}
		echo '</div>';
	}

	/* ---------- MCP ---------- */

	private static function panel_mcp() {
		$s       = MoshAI_Settings::all();
		$url     = rest_url( 'ai-mcp/v1/mcp' );
		$m       = MoshAI_Activity::mcp_status();
		$has_tok = '' !== (string) $s['mcp_token_hash'];
		$claude  = wp_json_encode(
			array(
				'mcpServers' => array(
					'ai-mcp' => array(
						'command' => 'npx',
						'args'    => array( '-y', 'mcp-remote', $url, '--header', 'Authorization: Bearer YOUR_TOKEN' ),
					),
				),
			),
			JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES
		);
		echo '<div class="mx-settings mx-settings-single" data-settings data-mcp-panel><div class="mx-settings-body">';

		// 1. Connection.
		MoshAI_Kses::out( '<div class="mx-card mx-mcp-status"><div class="mx-mcp-head"><div><h2>' . esc_html__( 'Connection', 'ai-mcp' ) . '</h2><div class="mx-mcp-big">' . self::mcp_pill() . '</div></div>' );
		echo '<button type="button" class="button mx-danger" data-mcp-disconnect' . ( $has_tok ? '' : ' hidden' ) . '><span class="dashicons dashicons-dismiss"></span>' . esc_html__( 'Disconnect everything', 'ai-mcp' ) . '</button></div>';
		MoshAI_Kses::out( self::toggle( 'mcp_enabled', $s['mcp_enabled'], __( 'MCP server on', 'ai-mcp' ), __( 'Lets Claude, ChatGPT, Cursor, ClickUp and other AI apps work on this site with their own AI. No API key needed here.', 'ai-mcp' ) ) );
		echo '<div class="mx-table-wrap"><table class="widefat mx-table"><thead><tr><th>' . esc_html__( 'Client', 'ai-mcp' ) . '</th><th>' . esc_html__( 'Status', 'ai-mcp' ) . '</th><th>' . esc_html__( 'Last seen', 'ai-mcp' ) . '</th><th>' . esc_html__( 'Changes (30 days)', 'ai-mcp' ) . '</th><th>' . esc_html__( 'First seen', 'ai-mcp' ) . '</th></tr></thead><tbody data-clients>';
		$clients = MoshAI_Activity::clients();
		if ( $clients ) {
			$labels = array(
				'live' => __( 'Connected', 'ai-mcp' ),
				'idle' => __( 'Idle', 'ai-mcp' ),
				'off'  => __( 'Not active', 'ai-mcp' ),
			);
			foreach ( $clients as $c ) {
				echo '<tr><td><strong>' . esc_html( $c['name'] ) . '</strong>' . ( $c['version'] ? ' <small>' . esc_html( $c['version'] ) . '</small>' : '' ) . '</td><td><span class="mx-state mx-state-' . esc_attr( $c['state'] ) . '"></span>' . esc_html( $labels[ $c['state'] ] ) . '</td><td>' . esc_html( $c['ago'] ) . '</td><td>' . (int) $c['changes'] . '</td><td>' . esc_html( $c['first'] ) . '</td></tr>';
			}
		} else {
			echo '<tr><td colspan="5">' . esc_html__( 'No AI app has connected yet.', 'ai-mcp' ) . '</td></tr>';
		}
		echo '</tbody></table></div></div>';

		// 2. Token.
		echo '<div class="mx-card"><h2>' . esc_html__( 'Access token', 'ai-mcp' ) . ' <span class="mx-badge ' . ( $has_tok ? 'is-on">' . esc_html__( 'Active', 'ai-mcp' ) : 'is-off">' . esc_html__( 'None yet', 'ai-mcp' ) ) . '</span></h2>';
		echo '<p class="mx-help">' . esc_html__( 'The token is stored encrypted (hashed), so nobody can read it back, not even from the database. You see a new token once: copy it into your AI app straight away.', 'ai-mcp' ) . '</p>';
		echo '<p><button type="button" class="button button-primary" data-mcp-new-token data-has-token="' . ( $has_tok ? '1' : '0' ) . '">' . ( $has_tok ? esc_html__( 'Create a new token', 'ai-mcp' ) : esc_html__( 'Create token', 'ai-mcp' ) ) . '</button></p>';
		echo '<div class="mx-token-reveal" data-token-reveal hidden><p><strong>' . esc_html__( 'Your new token (shown only now):', 'ai-mcp' ) . '</strong></p><code class="moshai-copy mx-token" data-token-value></code><p class="mx-help">' . esc_html__( 'The boxes below now include it. Click any box to copy.', 'ai-mcp' ) . '</p></div>';
		echo '<details class="mx-advanced" open><summary>' . esc_html__( 'How to connect (click any box to copy)', 'ai-mcp' ) . '</summary>';
		echo '<p><span class="mx-label">' . esc_html__( 'Server URL', 'ai-mcp' ) . '</span><code class="moshai-copy">' . esc_html( $url ) . '</code></p>';
		echo '<p><span class="mx-label">' . esc_html__( 'Header', 'ai-mcp' ) . '</span><code class="moshai-copy" data-token-tpl="Authorization: Bearer YOUR_TOKEN">Authorization: Bearer YOUR_TOKEN</code></p>';
		echo '<p><span class="mx-label">Claude Desktop / Cursor / Windsurf</span></p><pre class="mx-code moshai-copy" data-token-tpl="' . esc_attr( $claude ) . '">' . esc_html( $claude ) . '</pre>';
		echo '<p><span class="mx-label">' . esc_html__( 'URL with token (only if your app cannot send headers, and "Allow token in URL" is on)', 'ai-mcp' ) . '</span><code class="moshai-copy" data-token-tpl="' . esc_attr( $url . '?token=YOUR_TOKEN' ) . '">' . esc_html( $url . '?token=YOUR_TOKEN' ) . '</code></p>';
		/* translators: %d number of tools */
		echo '<p class="mx-help">' . esc_html( sprintf( __( '%d tools right now: posts, pages, Elementor content, features and global styles, WooCommerce orders, refunds, products and stock, media, users, comments, plugins (install, switch on/off, change their settings), Additional CSS, caches, and a bridge to every REST route.', 'ai-mcp' ), count( MoshAI_MCP::tools_for( 'mcp' ) ) ) ) . '</p></details></div>';

		// 3. Permissions.
		echo '<div class="mx-card"><h2>' . esc_html__( 'What AI can change', 'ai-mcp' ) . '</h2><p class="mx-help">' . esc_html__( 'Applies to MCP apps and the dashboard AI Agent. Reading is always allowed. Switched-off areas disappear from the AI\'s tool list.', 'ai-mcp' ) . '</p>';
		MoshAI_Kses::out( self::toggle( 'mcp_allow_writes', $s['mcp_allow_writes'], __( 'MCP apps can make changes', 'ai-mcp' ), __( 'Off = read-only: MCP apps can look, but not touch anything.', 'ai-mcp' ) ) );
		echo '<div class="mx-grid mx-grid-2">';
		foreach ( MoshAI_MCP::scopes() as $scope => $label ) {
			MoshAI_Kses::out( self::toggle( 'perm_' . $scope, $s[ 'perm_' . $scope ], $label ) );
		}
		echo '</div>';
		echo '<div class="mx-hardlimits"><strong><span class="dashicons dashicons-shield"></span>' . esc_html__( 'Always blocked, whatever you switch on:', 'ai-mcp' ) . '</strong> ' . esc_html__( 'creating or promoting administrators, editing admin accounts, login credentials (application passwords), changing the site URL or admin email, reading secrets and API keys, switching off or deleting AI MCP, editing this activity log.', 'ai-mcp' ) . '</div></div>';

		// 4. Security.
		echo '<div class="mx-card"><h2>' . esc_html__( 'Security', 'ai-mcp' ) . '</h2><div class="mx-grid mx-grid-2">';
		MoshAI_Kses::out( self::field(
			__( 'Run as', 'ai-mcp' ),
			wp_dropdown_users(
				array(
					'name'              => 'mcp_user',
					'role'              => 'administrator',
					'selected'          => (int) $s['mcp_user'],
					'show_option_none'  => __( 'First administrator', 'ai-mcp' ),
					'option_none_value' => 0,
					'echo'              => 0,
				)
			),
			__( 'Changes are made as this administrator.', 'ai-mcp' )
		) );
		MoshAI_Kses::out( self::field( __( 'Only allow these IPs (optional)', 'ai-mcp' ), self::area( 'mcp_ip_allow', $s['mcp_ip_allow'], 3, "203.0.113.7\n2001:db8::/32" ), __( 'One IP or CIDR range per line. Empty = any IP with the token.', 'ai-mcp' ) ) );
		echo '</div>';
		MoshAI_Kses::out( self::toggle( 'mcp_allow_query', $s['mcp_allow_query'], __( 'Allow the token in the URL (?token=)', 'ai-mcp' ), __( 'Less safe: URLs end up in server logs. Only turn on if your AI app cannot send an Authorization header.', 'ai-mcp' ) ) );
		echo '<ul class="mx-checks"><li>' . esc_html__( 'Brute-force lockout: 10 wrong tokens in 15 minutes blocks that IP for an hour.', 'ai-mcp' ) . '</li><li>' . esc_html__( 'Requests from other websites in a browser are refused (stops phishing pages and DNS-rebinding tricks).', 'ai-mcp' ) . '</li><li>' . esc_html__( 'The AI is told to treat website content as data and to ignore instructions hidden in posts, comments, orders or emails.', 'ai-mcp' ) . '</li><li>' . esc_html__( 'Every change and every blocked attempt lands in the activity log.', 'ai-mcp' ) . '</li></ul></div>';

		echo '</div>';
		echo '<div class="mx-savebar" data-savebar hidden><span>' . esc_html__( 'You have unsaved changes', 'ai-mcp' ) . '</span><button type="button" class="button" data-discard>' . esc_html__( 'Discard', 'ai-mcp' ) . '</button><button type="button" class="button button-primary" data-save-settings>' . esc_html__( 'Save MCP settings', 'ai-mcp' ) . '</button></div>';
		echo '</div>';
	}

	/* ---------- Settings ---------- */

	private static function panel_settings() {
		$s     = MoshAI_Settings::all();
		$keys  = MoshAI_Settings::key_status();
		$names = MoshAI_Providers::names();
		$info  = array(
			'openai'     => array( 'https://platform.openai.com/api-keys', __( 'Chat, images, knowledge base and moderation.', 'ai-mcp' ) ),
			'anthropic'  => array( 'https://console.anthropic.com/settings/keys', __( 'Claude models for chat and writing.', 'ai-mcp' ) ),
			'google'     => array( 'https://aistudio.google.com/app/apikey', __( 'Gemini models, generous free tier.', 'ai-mcp' ) ),
			'openrouter' => array( 'https://openrouter.ai/keys', __( 'One key, hundreds of models.', 'ai-mcp' ) ),
		);

		echo '<div class="mx-settings" data-settings>';
		echo '<nav class="mx-side"><a href="#mx-s-providers">' . esc_html__( 'AI providers', 'ai-mcp' ) . '</a><a href="#mx-s-defaults">' . esc_html__( 'Defaults', 'ai-mcp' ) . '</a><a href="#mx-s-limits">' . esc_html__( 'Limits and safety', 'ai-mcp' ) . '</a><a href="#mx-s-editor">' . esc_html__( 'Editor and SEO', 'ai-mcp' ) . '</a><a href="#mx-s-data">' . esc_html__( 'Data', 'ai-mcp' ) . '</a></nav>';
		echo '<div class="mx-settings-body">';

		echo '<div class="mx-card" id="mx-s-providers"><h2>' . esc_html__( 'AI providers', 'ai-mcp' ) . '</h2><p class="mx-help">' . esc_html__( 'Paste a key and hit Test. Keys are never shown again in full.', 'ai-mcp' ) . '</p><div class="mx-grid mx-grid-2">';
		foreach ( $names as $p => $label ) {
			if ( 'custom' === $p ) {
				continue;
			}
			$has = '' !== $keys[ $p ]['mask'];
			echo '<div class="mx-key" data-provider="' . esc_attr( $p ) . '"><div class="mx-key-head"><strong>' . esc_html( $label ) . '</strong><span class="mx-badge ' . ( $has ? 'is-on' : 'is-off' ) . '" data-key-status>' . ( $has ? esc_html__( 'Connected', 'ai-mcp' ) : esc_html__( 'Not set', 'ai-mcp' ) ) . '</span></div>';
			if ( $keys[ $p ]['config'] ) {
				echo '<p class="mx-help">' . esc_html__( 'Set in wp-config.php', 'ai-mcp' ) . ' (MOSHAI_' . esc_html( strtoupper( $p ) ) . '_KEY)</p><div class="mx-row"><button type="button" class="button" data-test-key="' . esc_attr( $p ) . '">' . esc_html__( 'Test', 'ai-mcp' ) . '</button></div>';
			} else {
				echo '<div class="mx-row"><input type="password" name="' . esc_attr( $p ) . '_key" data-key-input placeholder="' . esc_attr( $has ? $keys[ $p ]['mask'] : __( 'Paste your key', 'ai-mcp' ) ) . '" autocomplete="new-password" spellcheck="false"><button type="button" class="button" data-test-key="' . esc_attr( $p ) . '">' . esc_html__( 'Test', 'ai-mcp' ) . '</button></div>';
				echo '<button type="button" class="button-link button-link-delete mx-remove-key" data-remove-key="' . esc_attr( $p ) . '"' . ( $has ? '' : ' hidden' ) . '>' . esc_html__( 'Remove key', 'ai-mcp' ) . '</button>';
			}
			echo '<p class="mx-help">' . esc_html( $info[ $p ][1] ) . ' <a href="' . esc_url( $info[ $p ][0] ) . '" target="_blank" rel="noopener">' . esc_html__( 'Get a key', 'ai-mcp' ) . '</a></p></div>';
		}
		$on = MoshAI_Settings::is_connected( 'custom' );
		echo '<div class="mx-key mx-key-wide" data-provider="custom"><div class="mx-key-head"><strong>' . esc_html__( 'Others', 'ai-mcp' ) . ( $on ? ' · ' . esc_html( MoshAI_Providers::custom_label() ) : '' ) . '</strong><span class="mx-badge ' . ( $on ? 'is-on' : 'is-off' ) . '" data-key-status>' . ( $on ? esc_html__( 'Connected', 'ai-mcp' ) : esc_html__( 'Not set', 'ai-mcp' ) ) . '</span></div>';
		echo '<p class="mx-help">' . esc_html__( 'Any AI with an OpenAI-compatible API: DeepSeek, Groq, Mistral, xAI Grok, Together, Perplexity, Qwen, or your own Ollama / LM Studio server.', 'ai-mcp' ) . '</p>';
		echo '<div class="mx-grid mx-grid-4">';
		$opts = '<select data-custom-preset><option value="">' . esc_html__( 'Pick a preset…', 'ai-mcp' ) . '</option>';
		foreach ( MoshAI_Providers::custom_presets() as $k => $pr ) {
			$opts .= '<option value="' . esc_attr( $k ) . '" data-name="' . esc_attr( $pr[0] ) . '" data-url="' . esc_attr( $pr[1] ) . '" data-model="' . esc_attr( $pr[2] ) . '">' . esc_html( $pr[0] ) . '</option>';
		}
		MoshAI_Kses::out( self::field( __( 'Preset', 'ai-mcp' ), $opts . '</select>' ) );
		MoshAI_Kses::out( self::field( __( 'Name', 'ai-mcp' ), self::text( 'custom_name', $s['custom_name'], 'DeepSeek' ) ) );
		MoshAI_Kses::out( self::field( __( 'API URL', 'ai-mcp' ), self::text( 'custom_url', $s['custom_url'], 'https://api.example.com/v1' ) ) );
		MoshAI_Kses::out( self::field( __( 'Model', 'ai-mcp' ), '<input type="text" name="custom_model" value="' . esc_attr( $s['custom_model'] ) . '" list="moshai-models-custom" placeholder="model-name">' ) );
		echo '</div><div class="mx-row"><input type="password" name="custom_key" data-key-input placeholder="' . esc_attr( '' !== $keys['custom']['mask'] ? $keys['custom']['mask'] : __( 'API key (leave empty for Ollama / LM Studio)', 'ai-mcp' ) ) . '" autocomplete="new-password" spellcheck="false"><button type="button" class="button" data-test-key="custom">' . esc_html__( 'Test and save', 'ai-mcp' ) . '</button></div>';
		echo '<button type="button" class="button-link button-link-delete mx-remove-key" data-remove-key="custom"' . ( $on ? '' : ' hidden' ) . '>' . esc_html__( 'Disconnect', 'ai-mcp' ) . '</button></div>';
		echo '</div></div>';

		echo '<div class="mx-card" id="mx-s-defaults"><h2>' . esc_html__( 'Defaults', 'ai-mcp' ) . '</h2><p class="mx-help">' . esc_html__( 'Used everywhere unless a chatbot or form picks its own.', 'ai-mcp' ) . '</p><div class="mx-grid mx-grid-2">';
		MoshAI_Kses::out( self::field( __( 'Provider', 'ai-mcp' ), self::provider_select( 'default_provider', $s['default_provider'], false ) ) );
		MoshAI_Kses::out( self::field( __( 'Model', 'ai-mcp' ), self::model_input( 'default_model', $s['default_model'], $s['default_provider'] ), __( 'Test a key to load its full model list.', 'ai-mcp' ) ) );
		MoshAI_Kses::out( self::field( __( 'Creativity (temperature)', 'ai-mcp' ), self::num( 'temperature', $s['temperature'], 0, 2, 0.1 ), __( '0 = precise, 1 = creative.', 'ai-mcp' ) ) );
		MoshAI_Kses::out( self::field( __( 'Max answer tokens', 'ai-mcp' ), self::num( 'max_tokens', $s['max_tokens'], 16, 64000 ) ) );
		MoshAI_Kses::out( self::field( __( 'Image model', 'ai-mcp' ), '<input type="text" name="image_model" value="' . esc_attr( $s['image_model'] ) . '" list="moshai-image-models">' ) );
		MoshAI_Kses::out( self::field( __( 'Embedding model', 'ai-mcp' ), '<input type="text" name="embedding_model" value="' . esc_attr( $s['embedding_model'] ) . '" list="moshai-emb-models"><datalist id="moshai-emb-models"><option value="text-embedding-3-small"></option><option value="text-embedding-3-large"></option></datalist>', __( 'Changing it means re-syncing knowledge.', 'ai-mcp' ) ) );
		echo '</div></div>';

		echo '<div class="mx-card" id="mx-s-limits"><h2>' . esc_html__( 'Limits and safety', 'ai-mcp' ) . '</h2><p class="mx-help">' . esc_html__( 'Protects your API bill from abuse. Admins are never limited.', 'ai-mcp' ) . '</p>';
		MoshAI_Kses::out( self::toggle( 'limits_enabled', $s['limits_enabled'], __( 'Daily limits for chatbots and forms', 'ai-mcp' ) ) );
		echo '<div class="mx-grid mx-grid-3">';
		MoshAI_Kses::out( self::field( __( 'Per visitor, 24h', 'ai-mcp' ), self::num( 'limit_guest', $s['limit_guest'], 0, 100000 ) ) );
		MoshAI_Kses::out( self::field( __( 'Per logged-in user, 24h', 'ai-mcp' ), self::num( 'limit_user', $s['limit_user'], 0, 100000 ) ) );
		MoshAI_Kses::out( self::field( __( 'Per minute (burst)', 'ai-mcp' ), self::num( 'rate_per_minute', $s['rate_per_minute'], 0, 1000 ), __( '0 = off', 'ai-mcp' ) ) );
		echo '</div>';
		MoshAI_Kses::out( self::field( __( 'Message when the limit is hit', 'ai-mcp' ), self::text( 'limit_message', $s['limit_message'] ) ) );
		MoshAI_Kses::out( self::toggle( 'moderation', $s['moderation'], __( 'Block abusive messages', 'ai-mcp' ), __( 'Free OpenAI moderation, needs an OpenAI key.', 'ai-mcp' ) ) );
		MoshAI_Kses::out( self::toggle( 'log_discussions', $s['log_discussions'], __( 'Save chat conversations', 'ai-mcp' ), __( 'Mention it in your privacy policy.', 'ai-mcp' ) ) );
		echo '</div>';

		echo '<div class="mx-card" id="mx-s-editor"><h2>' . esc_html__( 'Editor and SEO', 'ai-mcp' ) . '</h2>';
		MoshAI_Kses::out( self::toggle( 'editor_assistant', $s['editor_assistant'], __( 'AI Assistant box on posts, pages and products', 'ai-mcp' ), __( 'Titles, excerpts, SEO descriptions, tags, full content, product copy.', 'ai-mcp' ) ) );
		MoshAI_Kses::out( self::toggle( 'meta_description', $s['meta_description'], __( 'Meta description field', 'ai-mcp' ), __( 'Printed in the page head. Not needed when the SEO module is on (it has a full SEO box), and skipped when Yoast, Rank Math, AIOSEO or SEOPress is active.', 'ai-mcp' ) ) );
		echo '</div>';

		echo '<div class="mx-card" id="mx-s-data"><h2>' . esc_html__( 'Data', 'ai-mcp' ) . '</h2><div class="mx-grid mx-grid-2">';
		MoshAI_Kses::out( self::field( __( 'Keep logs and conversations for (days)', 'ai-mcp' ), self::num( 'retention_days', $s['retention_days'], 0, 3650 ), __( 'Older entries are deleted daily. 0 = keep forever.', 'ai-mcp' ) ) );
		echo '</div>';
		MoshAI_Kses::out( self::toggle( 'clean_uninstall', $s['clean_uninstall'], __( 'Delete all AI MCP data when the plugin is deleted', 'ai-mcp' ) ) );
		echo '</div>';

		echo '</div>';
		echo '<div class="mx-savebar" data-savebar hidden><span>' . esc_html__( 'You have unsaved changes', 'ai-mcp' ) . '</span><button type="button" class="button" data-discard>' . esc_html__( 'Discard', 'ai-mcp' ) . '</button><button type="button" class="button button-primary" data-save-settings>' . esc_html__( 'Save settings', 'ai-mcp' ) . '</button></div>';
		echo '</div>';
	}
}
