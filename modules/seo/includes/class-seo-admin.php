<?php
/**
 * AI MCP > SEO: overview and every SEO setting, split into small sections. Settings save
 * instantly; lists (redirects, 404s, bulk editor) load over REST.
 *
 * @package AI_MCP
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class MoshAI_SEO_Admin {

	public static function init() {
		add_filter( 'moshai_admin_tabs', array( __CLASS__, 'tab' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'assets' ), 20 );
		add_action( 'admin_bar_menu', array( __CLASS__, 'admin_bar' ), 91 );
	}

	public static function tab( $tabs ) {
		$tabs['seo'] = array( __( 'SEO', 'ai-mcp' ), 'manage_options', 'dashicons-search', array( __CLASS__, 'panel' ), 'site' );
		return $tabs;
	}

	public static function sections() {
		return array(
			'overview'   => array( __( 'Overview', 'ai-mcp' ), 'dashicons-chart-pie' ),
			'appearance' => array( __( 'Search appearance', 'ai-mcp' ), 'dashicons-visibility' ),
			'types'      => array( __( 'Content types', 'ai-mcp' ), 'dashicons-admin-page' ),
			'taxonomies' => array( __( 'Taxonomies & archives', 'ai-mcp' ), 'dashicons-category' ),
			'social'     => array( __( 'Social', 'ai-mcp' ), 'dashicons-share' ),
			'sitemaps'   => array( __( 'Sitemaps', 'ai-mcp' ), 'dashicons-networking' ),
			'breadcrumbs' => array( __( 'Breadcrumbs', 'ai-mcp' ), 'dashicons-arrow-right-alt2' ),
			'redirects'  => array( __( 'Redirects & 404s', 'ai-mcp' ), 'dashicons-randomize' ),
			'bulk'       => array( __( 'Bulk editor', 'ai-mcp' ), 'dashicons-editor-table' ),
			'tools'      => array( __( 'Tools', 'ai-mcp' ), 'dashicons-admin-tools' ),
		);
	}

	public static function assets( $hook ) {
		if ( false === strpos( (string) $hook, 'moshai' ) ) {
			return;
		}
		wp_enqueue_media();
		wp_enqueue_style( 'moshai-seo', MOSHAI_SEO_URL . 'assets/seo.css', array( 'moshai-admin' ), MOSHAI_VERSION );
		wp_enqueue_script( 'moshai-seo-admin', MOSHAI_SEO_URL . 'assets/seo-admin.js', array( 'moshai-admin' ), MOSHAI_VERSION, true );
		wp_localize_script(
			'moshai-seo-admin',
			'MoshAISEO',
			array(
				'rest'  => esc_url_raw( rest_url( 'ai-mcp/v1/' ) ),
				'nonce' => wp_create_nonce( 'wp_rest' ),
				'types' => MoshAI_SEO::post_types(),
			)
		);
	}

	public static function admin_bar( $bar ) {
		if ( is_admin() || ! current_user_can( 'edit_posts' ) || ! is_singular() ) {
			return;
		}
		$post = get_queried_object();
		if ( ! $post instanceof WP_Post ) {
			return;
		}
		$score = get_post_meta( $post->ID, MoshAI_SEO_Meta::PREFIX . 'score', true );
		$color = array(
			'good' => '#3ddc84',
			'ok'   => '#ffb020',
			'bad'  => '#ff5a5f',
			'none' => '#8c8f94',
		);
		$bar->add_node(
			array(
				'parent' => 'moshai',
				'id'     => 'moshai-seo-score',
				/* translators: %s score */
				'title'  => '<span style="display:inline-block;width:8px;height:8px;border-radius:50%;margin-right:6px;background:' . esc_attr( $color[ MoshAI_SEO_Analyzer::rating( $score ) ] ) . '"></span>' . esc_html( sprintf( __( 'SEO score: %s', 'ai-mcp' ), '' === $score ? '-' : $score ) ),
				'href'   => get_edit_post_link( $post->ID ),
			)
		);
	}

	/* ---------- Field helpers (every setting is a dot path) ---------- */

	private static function attr( $path ) {
		return ' name="' . esc_attr( $path ) . '" data-seo-setting="' . esc_attr( $path ) . '"';
	}

	private static function toggle( $path, $label, $help = '' ) {
		$on = (int) MoshAI_SEO_Settings::get( $path );
		return '<label class="mx-toggle"><input type="checkbox" value="1"' . self::attr( $path ) . checked( 1, $on, false ) . '><span class="mx-switch"></span><span class="mx-toggle-text"><strong>' . esc_html( $label ) . '</strong>' . ( $help ? '<small>' . esc_html( $help ) . '</small>' : '' ) . '</span></label>';
	}

	private static function text( $path, $label, $help = '', $placeholder = '', $vars = false ) {
		$v = (string) MoshAI_SEO_Settings::get( $path );
		return '<div class="mx-field"><label class="mx-label">' . esc_html( $label ) . '</label><input type="text" value="' . esc_attr( $v ) . '" placeholder="' . esc_attr( $placeholder ) . '"' . self::attr( $path ) . ( $vars ? ' data-seo-vars' : '' ) . '>' . ( $help ? '<p class="mx-help">' . esc_html( $help ) . '</p>' : '' ) . '</div>';
	}

	private static function area( $path, $label, $help = '', $rows = 4, $class = '' ) {
		$v = (string) MoshAI_SEO_Settings::get( $path );
		return '<div class="mx-field"><label class="mx-label">' . esc_html( $label ) . '</label><textarea rows="' . (int) $rows . '" class="' . esc_attr( $class ) . '"' . self::attr( $path ) . '>' . esc_textarea( $v ) . '</textarea>' . ( $help ? '<p class="mx-help">' . esc_html( $help ) . '</p>' : '' ) . '</div>';
	}

	private static function select( $path, $label, $options, $help = '' ) {
		$v = (string) MoshAI_SEO_Settings::get( $path );
		$h = '<div class="mx-field"><label class="mx-label">' . esc_html( $label ) . '</label><select' . self::attr( $path ) . '>';
		foreach ( $options as $k => $l ) {
			$k  = is_int( $k ) ? $l : $k;
			$h .= '<option value="' . esc_attr( $k ) . '"' . selected( $v, (string) $k, false ) . '>' . esc_html( $l ) . '</option>';
		}
		return $h . '</select>' . ( $help ? '<p class="mx-help">' . esc_html( $help ) . '</p>' : '' ) . '</div>';
	}

	private static function image( $path, $label, $help = '' ) {
		$v = (string) MoshAI_SEO_Settings::get( $path );
		return '<div class="mx-field"><label class="mx-label">' . esc_html( $label ) . '</label><div class="mx-row mx-seo-image-row"><span class="mx-seo-thumb"' . ( $v ? ' style="background:url(' . esc_url( $v ) . ') center / cover no-repeat"' : '' ) . '></span><input type="text" value="' . esc_attr( $v ) . '" placeholder="https://"' . self::attr( $path ) . '><button type="button" class="button" data-seo-pick="' . esc_attr( $path ) . '">' . esc_html__( 'Choose', 'ai-mcp' ) . '</button></div>' . ( $help ? '<p class="mx-help">' . esc_html( $help ) . '</p>' : '' ) . '</div>';
	}

	private static function checklist( $path, $label, $options, $help = '' ) {
		$v = (array) MoshAI_SEO_Settings::get( $path, array() );
		$h = '<div class="mx-field" data-seo-list="' . esc_attr( $path ) . '"><span class="mx-label">' . esc_html( $label ) . '</span><div class="mx-seo-checklist">';
		foreach ( $options as $k => $l ) {
			$h .= '<label><input type="checkbox" value="' . esc_attr( $k ) . '"' . checked( in_array( $k, $v, true ), true, false ) . '> ' . esc_html( $l ) . '</label>';
		}
		return $h . '</div>' . ( $help ? '<p class="mx-help">' . esc_html( $help ) . '</p>' : '' ) . '</div>';
	}

	private static function card( $title, $body, $help = '', $icon = '' ) {
		return '<div class="mx-card"><h2>' . ( $icon ? '<span class="dashicons ' . esc_attr( $icon ) . '"></span>' : '' ) . esc_html( $title ) . '</h2>' . ( $help ? '<p class="mx-help">' . esc_html( $help ) . '</p>' : '' ) . $body . '</div>';
	}

	/* ---------- Panel ---------- */

	public static function panel() {
		$conflict = MoshAI_SEO::conflict();
		echo '<div class="mx-page-head"><div><h2>' . esc_html__( 'SEO', 'ai-mcp' ) . '</h2><p class="mx-help">' . esc_html__( 'Everything search engines and social networks see. Changes save instantly. Any AI connected over MCP can audit and optimize all of it.', 'ai-mcp' ) . '</p></div><div class="mx-row">';
		if ( (int) MoshAI_SEO_Settings::get( 'sitemap.enabled' ) ) {
			echo '<a class="button" href="' . esc_url( MoshAI_SEO_Sitemaps::url() ) . '" target="_blank" rel="noopener">' . esc_html__( 'View sitemap', 'ai-mcp' ) . '</a>';
		}
		echo '</div></div>';
		if ( $conflict ) {
			/* translators: %s plugin name */
			echo '<div class="mx-card mx-seo-warn"><strong>' . esc_html( sprintf( __( '%s is active.', 'ai-mcp' ), $conflict ) ) . '</strong> ' . esc_html__( 'To avoid duplicate tags, AI MCP prints no SEO tags while it runs. Import its data under Tools, check the preview, then deactivate it.', 'ai-mcp' ) . '</div>';
		}
		echo '<div class="mx-seo-layout"><nav class="mx-seo-nav">';
		$first = true;
		foreach ( self::sections() as $k => $s ) {
			echo '<button type="button" data-seo-sub="' . esc_attr( $k ) . '" class="' . ( $first ? 'is-active' : '' ) . '"><span class="dashicons ' . esc_attr( $s[1] ) . '"></span>' . esc_html( $s[0] ) . '</button>';
			$first = false;
		}
		echo '</nav><div class="mx-seo-main">';
		$first = true;
		foreach ( array_keys( self::sections() ) as $k ) {
			echo '<div class="mx-seo-section" data-seo-section="' . esc_attr( $k ) . '"' . ( $first ? '' : ' hidden' ) . '>';
			call_user_func( array( __CLASS__, 'section_' . $k ) );
			echo '</div>';
			$first = false;
		}
		echo '</div></div>';
	}

	private static function ring( $score ) {
		$rating = MoshAI_SEO_Analyzer::rating( $score );
		$val    = null === $score ? 0 : (int) $score;
		return '<div class="mx-seo-ring is-' . esc_attr( $rating ) . '" style="--p:' . esc_attr( $val ) . '"><span>' . esc_html( null === $score ? '–' : $val ) . '</span><small>' . esc_html__( 'avg score', 'ai-mcp' ) . '</small></div>';
	}

	private static function section_overview() {
		$sum    = MoshAI_SEO_Audit::summary();
		$health = MoshAI_SEO_Audit::health();
		$stats  = array(
			'missing_description' => __( 'No meta description', 'ai-mcp' ),
			'missing_keyphrase'   => __( 'No focus keyphrase', 'ai-mcp' ),
			'low_score'           => __( 'Low SEO score', 'ai-mcp' ),
			'unscored'            => __( 'Not analyzed yet', 'ai-mcp' ),
			'noindex'             => __( 'Hidden (noindex)', 'ai-mcp' ),
		);
		$h  = '<div class="mx-seo-overview"><div class="mx-card mx-seo-hero">' . self::ring( $sum['average_score'] ) . '<div><h2>' . esc_html__( 'Site SEO', 'ai-mcp' ) . '</h2>';
		/* translators: 1: published count, 2: redirects */
		$h .= '<p class="mx-help">' . esc_html( sprintf( __( '%1$d published pages, posts and products. %2$d redirects.', 'ai-mcp' ), $sum['published'], $sum['redirects'] ) ) . '</p>';
		$h .= '<p class="mx-row"><button type="button" class="button button-primary" data-seo-rescore>' . esc_html__( 'Analyze all content', 'ai-mcp' ) . '</button><span class="mx-help" data-seo-rescore-status></span></p></div></div>';
		$h .= '<div class="mx-seo-stats">';
		foreach ( $stats as $k => $label ) {
			$h .= '<button type="button" class="mx-stat mx-seo-stat" data-seo-problem="' . esc_attr( $k ) . '"><span class="mx-stat-val">' . esc_html( number_format_i18n( $sum[ $k ] ) ) . '</span><span class="mx-stat-label">' . esc_html( $label ) . '</span></button>';
		}
		$h .= '</div></div>';
		$list = '<ul class="mx-seo-health">';
		foreach ( $health as $it ) {
			$fix = '';
			if ( $it['fix'] ) {
				$fix = 0 === strpos( $it['fix'], 'http' ) ? '<a href="' . esc_url( $it['fix'] ) . '">' . esc_html__( 'Fix', 'ai-mcp' ) . '</a>' : '<button type="button" class="button-link" data-seo-go="' . esc_attr( $it['fix'] ) . '">' . esc_html__( 'Fix', 'ai-mcp' ) . '</button>';
			}
			$list .= '<li class="is-' . esc_attr( $it['status'] ) . '"><span class="mx-seo-dot" data-rating="' . esc_attr( $it['status'] ) . '"></span><span>' . esc_html( $it['text'] ) . '</span>' . $fix . '</li>';
		}
		$list .= '</ul>';
		$h    .= '<div class="mx-grid mx-grid-2">' . self::card( __( 'Site health', 'ai-mcp' ), $list, '', 'dashicons-heart' );
		$ai    = '<p>' . esc_html__( 'Connect Claude, ChatGPT, Cursor or ClickUp over MCP (AI MCP > MCP) and ask:', 'ai-mcp' ) . '</p><ul class="mx-seo-prompts"><li><code class="moshai-copy">Audit my site\'s SEO and fix every missing meta description.</code></li><li><code class="moshai-copy">Pick a focus keyphrase for each page and rewrite the SEO titles around it.</code></li><li><code class="moshai-copy">Find pages scoring under 40 and tell me how to fix them.</code></li><li><code class="moshai-copy">Set up Open Graph and the organization schema with my logo.</code></li></ul>';
		$h    .= self::card( __( 'Let an AI optimize it', 'ai-mcp' ), $ai, '', 'dashicons-superhero' ) . '</div>';
		MoshAI_Kses::out( $h );
	}

	private static function section_appearance() {
		$seps = array( '-', '–', '—', '|', '·', '•', '»', '~', '/', ':' );
		$sep  = '<div class="mx-field"><span class="mx-label">' . esc_html__( 'Title separator', 'ai-mcp' ) . '</span><div class="mx-seo-seps" data-seo-sep>';
		foreach ( $seps as $s ) {
			$sep .= '<button type="button" data-value="' . esc_attr( $s ) . '" class="' . ( MoshAI_SEO_Settings::get( 'general.sep' ) === $s ? 'is-active' : '' ) . '">' . esc_html( $s ) . '</button>';
		}
		$sep .= '</div><input type="hidden" value="' . esc_attr( MoshAI_SEO_Settings::get( 'general.sep' ) ) . '"' . self::attr( 'general.sep' ) . '></div>';
		$home = $sep . self::text( 'general.home_title', __( 'Homepage SEO title', 'ai-mcp' ), '', '', true ) . self::area( 'general.home_desc', __( 'Homepage meta description', 'ai-mcp' ), __( 'Used when the homepage has no description of its own.', 'ai-mcp' ), 3 ) . self::image( 'general.og_home_image', __( 'Homepage social image', 'ai-mcp' ) );
		$kg   = self::select(
			'knowledge.type',
			__( 'This site represents', 'ai-mcp' ),
			array(
				'organization' => __( 'An organization or business', 'ai-mcp' ),
				'person'       => __( 'A person', 'ai-mcp' ),
			)
		) . '<div class="mx-grid mx-grid-2">' . self::text( 'knowledge.name', __( 'Organization name', 'ai-mcp' ), '', get_bloginfo( 'name' ) ) . self::text( 'knowledge.alt_name', __( 'Alternate name', 'ai-mcp' ) ) . '</div>' . self::image( 'knowledge.logo', __( 'Organization logo', 'ai-mcp' ), __( 'Square, at least 112 x 112 px. Shown in Google\'s knowledge panel.', 'ai-mcp' ) ) . '<div class="mx-grid mx-grid-2">' . self::text( 'knowledge.person_name', __( 'Person name', 'ai-mcp' ) ) . self::image( 'knowledge.person_image', __( 'Person image', 'ai-mcp' ) ) . '</div>';
		$vars = '<div class="mx-seo-vars">';
		foreach ( MoshAI_SEO_Vars::help() as $v => $d ) {
			$vars .= '<code class="moshai-copy" title="' . esc_attr( $d ) . '">' . esc_html( $v ) . '</code>';
		}
		$vars .= '</div>';
		MoshAI_Kses::out( '<div class="mx-grid mx-grid-2">' . self::card( __( 'Homepage & separator', 'ai-mcp' ), $home, '', 'dashicons-admin-home' ) . self::card( __( 'Knowledge graph (schema)', 'ai-mcp' ), $kg, __( 'Tells Google who is behind the site.', 'ai-mcp' ), 'dashicons-building' ) . '</div>' . self::card( __( 'Variables', 'ai-mcp' ), $vars, __( 'Use these in any title or description template. Click to copy.', 'ai-mcp' ), 'dashicons-editor-code' ) );
	}

	private static function section_types() {
		$choices = MoshAI_SEO_Settings::choices();
		$h       = '';
		foreach ( MoshAI_SEO::post_types() as $type => $label ) {
			$p    = 'types.' . $type . '.';
			$obj  = get_post_type_object( $type );
			$body = self::toggle( $p . 'index', __( 'Show in search results', 'ai-mcp' ), __( 'Off = noindex and left out of the sitemap.', 'ai-mcp' ) ) . self::toggle( $p . 'box', __( 'Show the SEO box in the editor', 'ai-mcp' ) );
			$body .= self::text( $p . 'title', __( 'SEO title template', 'ai-mcp' ), '', '', true ) . self::text( $p . 'desc', __( 'Meta description template', 'ai-mcp' ), '', '', true );
			$body .= '<div class="mx-grid mx-grid-2">' . self::select( $p . 'schema_page', __( 'Page type (schema)', 'ai-mcp' ), $choices['types.*.schema_page'] ) . self::select( $p . 'schema_article', __( 'Article type (schema)', 'ai-mcp' ), $choices['types.*.schema_article'] ) . '</div>';
			if ( $obj && $obj->has_archive ) {
				$body .= '<details class="mx-advanced"><summary>' . esc_html__( 'Archive page', 'ai-mcp' ) . '</summary>' . self::text( $p . 'archive_title', __( 'Archive SEO title', 'ai-mcp' ), '', '', true ) . self::text( $p . 'archive_desc', __( 'Archive meta description', 'ai-mcp' ) ) . '</details>';
			}
			$h .= self::card( $label . ' (' . $type . ')', $body, '', 'dashicons-admin-page' );
		}
		MoshAI_Kses::out( '<div class="mx-grid mx-grid-2">' . $h . '</div>' );
	}

	private static function section_taxonomies() {
		$h = '';
		foreach ( MoshAI_SEO::taxonomies() as $tax => $label ) {
			$p  = 'tax.' . $tax . '.';
			$h .= self::card( $label . ' (' . $tax . ')', self::toggle( $p . 'index', __( 'Show in search results', 'ai-mcp' ) ) . self::text( $p . 'title', __( 'SEO title template', 'ai-mcp' ), '', '', true ) . self::text( $p . 'desc', __( 'Meta description template', 'ai-mcp' ), '', '', true ), '', 'dashicons-category' );
		}
		$arch  = self::toggle( 'archives.author_index', __( 'Author archives in search results', 'ai-mcp' ), __( 'Turn off on single-author sites: the author page copies the blog.', 'ai-mcp' ) ) . self::text( 'archives.author_title', __( 'Author archive title', 'ai-mcp' ), '', '', true ) . self::text( 'archives.author_desc', __( 'Author archive description', 'ai-mcp' ) );
		$arch .= self::toggle( 'archives.date_index', __( 'Date archives in search results', 'ai-mcp' ) ) . self::text( 'archives.date_title', __( 'Date archive title', 'ai-mcp' ), '', '', true );
		$spec  = self::text( 'archives.search_title', __( 'Search results title', 'ai-mcp' ), '', '', true ) . self::text( 'archives.notfound_title', __( '404 page title', 'ai-mcp' ), '', '', true ) . self::toggle( 'archives.noindex_paged', __( 'Hide page 2, 3... of archives', 'ai-mcp' ) ) . self::toggle( 'archives.attachment_redirect', __( 'Redirect attachment pages to the file', 'ai-mcp' ), __( 'Recommended: attachment pages are thin content.', 'ai-mcp' ) );
		MoshAI_Kses::out( '<div class="mx-grid mx-grid-2">' . $h . self::card( __( 'Author & date archives', 'ai-mcp' ), $arch, '', 'dashicons-admin-users' ) . self::card( __( 'Special pages', 'ai-mcp' ), $spec, '', 'dashicons-admin-generic' ) . '</div>' );
	}

	private static function section_social() {
		$og       = self::toggle( 'social.og_enabled', __( 'Open Graph tags', 'ai-mcp' ), __( 'Facebook, LinkedIn, WhatsApp, Slack, Discord previews.', 'ai-mcp' ) ) . self::image( 'social.default_image', __( 'Default social image', 'ai-mcp' ), __( '1200 x 630 px. Used when a page has no image.', 'ai-mcp' ) ) . self::text( 'social.fb_app_id', __( 'Facebook App ID', 'ai-mcp' ), __( 'Optional.', 'ai-mcp' ) );
		$tw       = self::toggle( 'social.twitter_enabled', __( 'X (Twitter) card tags', 'ai-mcp' ) ) . self::select(
			'social.twitter_card',
			__( 'Card type', 'ai-mcp' ),
			array(
				'summary_large_image' => __( 'Large image', 'ai-mcp' ),
				'summary'             => __( 'Small image', 'ai-mcp' ),
			)
		) . self::text( 'social.twitter_site', __( 'X username', 'ai-mcp' ), '', '@yourbrand' );
		$profiles = '<div class="mx-grid mx-grid-2">';
		foreach ( array( 'facebook' => 'Facebook', 'instagram' => 'Instagram', 'linkedin' => 'LinkedIn', 'youtube' => 'YouTube', 'pinterest' => 'Pinterest', 'tiktok' => 'TikTok' ) as $k => $l ) {
			$profiles .= self::text( 'social.' . $k, $l, '', 'https://' );
		}
		$profiles .= '</div>' . self::area( 'social.profiles_other', __( 'Other profiles (one URL per line)', 'ai-mcp' ), __( 'Wikipedia, Crunchbase, GitHub... All profiles go into the schema sameAs list.', 'ai-mcp' ), 3 );
		MoshAI_Kses::out( '<div class="mx-grid mx-grid-2">' . self::card( 'Open Graph', $og, '', 'dashicons-facebook' ) . self::card( 'X (Twitter)', $tw, '', 'dashicons-twitter' ) . '</div>' . self::card( __( 'Social profiles', 'ai-mcp' ), $profiles, '', 'dashicons-share' ) );
	}

	private static function section_sitemaps() {
		$on   = self::toggle( 'sitemap.enabled', __( 'XML sitemaps', 'ai-mcp' ), __( 'Built on WordPress core sitemaps: fast and cached.', 'ai-mcp' ) ) . self::toggle( 'sitemap.authors', __( 'Include author pages', 'ai-mcp' ) );
		$on  .= '<p class="mx-help">' . esc_html__( 'Submit this address in Google Search Console and Bing Webmaster Tools:', 'ai-mcp' ) . ' <code class="moshai-copy">' . esc_html( MoshAI_SEO_Sitemaps::url() ) . '</code></p>';
		$ex   = self::checklist( 'sitemap.exclude_types', __( 'Leave out content types', 'ai-mcp' ), MoshAI_SEO::post_types() ) . self::checklist( 'sitemap.exclude_tax', __( 'Leave out taxonomies', 'ai-mcp' ), MoshAI_SEO::taxonomies() ) . self::text( 'sitemap.exclude_ids', __( 'Leave out post IDs', 'ai-mcp' ), __( 'Comma separated, e.g. 12, 45. Noindex pages are always left out.', 'ai-mcp' ) );
		MoshAI_Kses::out( '<div class="mx-grid mx-grid-2">' . self::card( __( 'Sitemaps', 'ai-mcp' ), $on, '', 'dashicons-networking' ) . self::card( __( 'Exclusions', 'ai-mcp' ), $ex, '', 'dashicons-hidden' ) . '</div>' );
	}

	private static function section_breadcrumbs() {
		$b  = self::toggle( 'breadcrumbs.enabled', __( 'Breadcrumbs', 'ai-mcp' ), __( 'The breadcrumb schema is always added for Google.', 'ai-mcp' ) ) . '<div class="mx-grid mx-grid-2">' . self::text( 'breadcrumbs.sep', __( 'Separator', 'ai-mcp' ) ) . self::text( 'breadcrumbs.home', __( 'Home label', 'ai-mcp' ) ) . self::text( 'breadcrumbs.prefix', __( 'Prefix', 'ai-mcp' ), '', __( 'You are here:', 'ai-mcp' ) ) . '</div>' . self::toggle( 'breadcrumbs.blog_page', __( 'Show the blog page for posts', 'ai-mcp' ) ) . self::toggle( 'breadcrumbs.bold_last', __( 'Bold the current page', 'ai-mcp' ) );
		$u  = '<p>' . esc_html__( 'Elementor: add a Shortcode widget with:', 'ai-mcp' ) . '</p><p><code class="moshai-copy">[ai_mcp_breadcrumbs]</code></p><p>' . esc_html__( 'Theme files:', 'ai-mcp' ) . '</p><p><code class="moshai-copy">&lt;?php if ( function_exists( \'moshai_breadcrumbs\' ) ) { moshai_breadcrumbs(); } ?&gt;</code></p>';
		MoshAI_Kses::out( '<div class="mx-grid mx-grid-2">' . self::card( __( 'Breadcrumbs', 'ai-mcp' ), $b, '', 'dashicons-arrow-right-alt2' ) . self::card( __( 'How to show them', 'ai-mcp' ), $u, '', 'dashicons-editor-help' ) . '</div>' );
	}

	private static function section_redirects() {
		$form  = '<div class="mx-seo-redirect-form" data-seo-redirect-form><div class="mx-grid mx-grid-4"><div class="mx-field mx-span-2"><label class="mx-label">' . esc_html__( 'Old URL or path', 'ai-mcp' ) . '</label><input type="text" name="source" placeholder="/old-page/"></div><div class="mx-field mx-span-2"><label class="mx-label">' . esc_html__( 'New URL or path', 'ai-mcp' ) . '</label><input type="text" name="target" placeholder="/new-page/"></div></div>';
		$form .= '<div class="mx-row"><select name="code" style="width:auto"><option value="301">301 ' . esc_html__( 'Moved permanently', 'ai-mcp' ) . '</option><option value="302">302 ' . esc_html__( 'Found (temporary)', 'ai-mcp' ) . '</option><option value="307">307 ' . esc_html__( 'Temporary', 'ai-mcp' ) . '</option><option value="308">308 ' . esc_html__( 'Permanent', 'ai-mcp' ) . '</option><option value="410">410 ' . esc_html__( 'Gone (deleted for good)', 'ai-mcp' ) . '</option><option value="451">451 ' . esc_html__( 'Unavailable for legal reasons', 'ai-mcp' ) . '</option></select><label><input type="checkbox" name="regex" value="1"> ' . esc_html__( 'Regex', 'ai-mcp' ) . '</label><button type="button" class="button button-primary" data-seo-redirect-add>' . esc_html__( 'Add redirect', 'ai-mcp' ) . '</button></div></div>';
		$form .= self::toggle( 'redirects.auto_slug', __( 'Redirect automatically when a published URL changes', 'ai-mcp' ) );
		$list  = '<div class="mx-row"><input type="search" placeholder="' . esc_attr__( 'Search redirects', 'ai-mcp' ) . '" data-seo-redirect-search></div><div data-seo-redirects><p class="mx-help">' . esc_html__( 'Loading…', 'ai-mcp' ) . '</p></div>';
		$log   = self::toggle( 'redirects.log_404', __( 'Log broken URLs (404 monitor)', 'ai-mcp' ), __( 'Off by default. Keeps the latest 1,000.', 'ai-mcp' ) ) . '<div data-seo-404><p class="mx-help">' . esc_html__( 'Loading…', 'ai-mcp' ) . '</p></div>';
		MoshAI_Kses::out( self::card( __( 'Add a redirect', 'ai-mcp' ), $form, '', 'dashicons-randomize' ) . self::card( __( 'Redirects', 'ai-mcp' ), $list, '', 'dashicons-list-view' ) . self::card( __( '404 monitor', 'ai-mcp' ), $log, '', 'dashicons-warning' ) );
	}

	private static function section_bulk() {
		$types = '<select data-seo-bulk-type style="width:auto"><option value="">' . esc_html__( 'All content', 'ai-mcp' ) . '</option>';
		foreach ( MoshAI_SEO::post_types() as $k => $l ) {
			$types .= '<option value="' . esc_attr( $k ) . '">' . esc_html( $l ) . '</option>';
		}
		$types .= '</select>';
		$probs  = '<select data-seo-bulk-problem style="width:auto"><option value="">' . esc_html__( 'Any state', 'ai-mcp' ) . '</option>';
		foreach ( array(
			'missing_description' => __( 'No meta description', 'ai-mcp' ),
			'missing_keyphrase'   => __( 'No focus keyphrase', 'ai-mcp' ),
			'missing_title'       => __( 'No custom SEO title', 'ai-mcp' ),
			'low_score'           => __( 'Low SEO score', 'ai-mcp' ),
			'low_readability'     => __( 'Low readability', 'ai-mcp' ),
			'unscored'            => __( 'Not analyzed', 'ai-mcp' ),
			'noindex'             => __( 'Hidden (noindex)', 'ai-mcp' ),
			'cornerstone'         => __( 'Cornerstone', 'ai-mcp' ),
		) as $k => $l ) {
			$probs .= '<option value="' . esc_attr( $k ) . '">' . esc_html( $l ) . '</option>';
		}
		$probs .= '</select>';
		$h      = '<div class="mx-row">' . $types . $probs . '<input type="search" placeholder="' . esc_attr__( 'Search', 'ai-mcp' ) . '" data-seo-bulk-search style="max-width:260px"></div><div data-seo-bulk><p class="mx-help">' . esc_html__( 'Loading…', 'ai-mcp' ) . '</p></div>';
		MoshAI_Kses::out( self::card( __( 'Bulk editor', 'ai-mcp' ), $h, __( 'Edit SEO titles, descriptions and keyphrases for many pages at once. Every field saves when you leave it.', 'ai-mcp' ), 'dashicons-editor-table' ) );
	}

	private static function section_tools() {
		$robots = self::area( 'robots.extra', __( 'Extra robots.txt rules', 'ai-mcp' ), __( 'Added to the robots.txt WordPress makes (only when there is no robots.txt file on the server). The sitemap line is added automatically.', 'ai-mcp' ), 5, 'code' ) . '<p><a href="' . esc_url( home_url( '/robots.txt' ) ) . '" target="_blank" rel="noopener">' . esc_html__( 'View robots.txt', 'ai-mcp' ) . '</a></p>';
		$verify = '';
		foreach ( array( 'google' => 'Google Search Console', 'bing' => 'Bing Webmaster Tools', 'pinterest' => 'Pinterest', 'yandex' => 'Yandex', 'baidu' => 'Baidu' ) as $k => $l ) {
			$verify .= self::text( 'verify.' . $k, $l, '', __( 'Paste the code or the whole meta tag', 'ai-mcp' ) );
		}
		$llms   = self::toggle( 'llms.enabled', __( 'llms.txt', 'ai-mcp' ), __( 'A clean map of your site for ChatGPT, Claude and other AI search tools.', 'ai-mcp' ) ) . self::area( 'llms.intro', __( 'Intro text', 'ai-mcp' ), __( 'A few lines about what you do and who it is for.', 'ai-mcp' ), 3 ) . '<p><a href="' . esc_url( home_url( '/llms.txt' ) ) . '" target="_blank" rel="noopener">' . esc_html__( 'View llms.txt', 'ai-mcp' ) . '</a></p>';
		$clean  = self::toggle( 'cleanup.shortlinks', __( 'Remove shortlinks', 'ai-mcp' ) ) . self::toggle( 'cleanup.rsd', __( 'Remove RSD link', 'ai-mcp' ) ) . self::toggle( 'cleanup.wlw', __( 'Remove WLW manifest link', 'ai-mcp' ) ) . self::toggle( 'cleanup.generator', __( 'Hide the WordPress version', 'ai-mcp' ) ) . self::toggle( 'cleanup.rest_link', __( 'Remove REST API link', 'ai-mcp' ) ) . self::toggle( 'cleanup.oembed', __( 'Remove oEmbed links', 'ai-mcp' ) ) . self::toggle( 'cleanup.emoji', __( 'Remove emoji scripts', 'ai-mcp' ) ) . self::toggle( 'cleanup.comment_feeds', __( 'Remove comment feeds', 'ai-mcp' ) );
		$rss    = self::area( 'rss.before', __( 'Before each post in the feed', 'ai-mcp' ), '', 2 ) . self::area( 'rss.after', __( 'After each post in the feed', 'ai-mcp' ), __( '%%POSTLINK%% and %%BLOGLINK%% become links. Stops scrapers from passing off your posts.', 'ai-mcp' ), 2 );
		$import = '';
		foreach ( MoshAI_SEO_Tools::import_sources() as $k => $src ) {
			/* translators: 1: plugin, 2: count */
			$import .= '<div class="mx-row"><span>' . esc_html( sprintf( __( '%1$s: data on %2$d posts', 'ai-mcp' ), $src['name'], $src['posts'] ) ) . '</span>' . ( $src['posts'] ? '<button type="button" class="button" data-seo-import="' . esc_attr( $k ) . '">' . esc_html__( 'Import', 'ai-mcp' ) . '</button>' : '' ) . '</div>';
		}
		$import .= '<p class="mx-help" data-seo-import-status>' . esc_html__( 'Titles, descriptions, keyphrases, canonical, robots and social fields. Values you already set here are kept.', 'ai-mcp' ) . '</p>';
		MoshAI_Kses::out( '<div class="mx-grid mx-grid-2">' . self::card( 'robots.txt', $robots, '', 'dashicons-shield' ) . self::card( __( 'Site verification', 'ai-mcp' ), $verify, '', 'dashicons-yes-alt' ) . self::card( __( 'AI search (llms.txt)', 'ai-mcp' ), $llms, '', 'dashicons-superhero' ) . self::card( __( 'Import from another SEO plugin', 'ai-mcp' ), $import, '', 'dashicons-download' ) . self::card( __( 'Clean up <head>', 'ai-mcp' ), $clean, '', 'dashicons-editor-removeformatting' ) . self::card( __( 'RSS', 'ai-mcp' ), $rss, '', 'dashicons-rss' ) . '</div>' );
	}
}
