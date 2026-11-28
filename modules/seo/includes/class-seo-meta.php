<?php
/**
 * Per-post and per-term SEO fields: one field list shared by the meta box, the REST API and MCP.
 *
 * @package AI_MCP
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class MoshAI_SEO_Meta {

	const PREFIX = '_moshai_seo_';

	/**
	 * field => array( type, label, help ). Types: text, textarea, url, bool, robots, choice:<path>, term.
	 */
	public static function fields() {
		return array(
			'title'               => array( 'text', __( 'SEO title', 'ai-mcp' ), __( 'Empty = the template from Content types. Variables like %%sitename%% work here.', 'ai-mcp' ) ),
			'description'         => array( 'textarea', __( 'Meta description', 'ai-mcp' ), __( 'About 120 to 156 characters. Empty = the template (usually the excerpt).', 'ai-mcp' ) ),
			'focus_keyphrase'     => array( 'text', __( 'Focus keyphrase', 'ai-mcp' ), __( 'The search phrase this page should rank for.', 'ai-mcp' ) ),
			'related_keyphrases'  => array( 'text', __( 'Related keyphrases', 'ai-mcp' ), __( 'Comma separated synonyms and related searches.', 'ai-mcp' ) ),
			'cornerstone'         => array( 'bool', __( 'Cornerstone content', 'ai-mcp' ), __( 'Your most important pages. They get stricter checks.', 'ai-mcp' ) ),
			'canonical'           => array( 'url', __( 'Canonical URL', 'ai-mcp' ), __( 'Only when this content is a copy of another page.', 'ai-mcp' ) ),
			'robots_index'        => array( 'robots', __( 'Show in search results', 'ai-mcp' ), '' ),
			'nofollow'            => array( 'bool', __( 'Nofollow links on this page', 'ai-mcp' ), '' ),
			'noarchive'           => array( 'bool', __( 'No archive (no cached copy)', 'ai-mcp' ), '' ),
			'nosnippet'           => array( 'bool', __( 'No snippet (no text preview in results)', 'ai-mcp' ), '' ),
			'noimageindex'        => array( 'bool', __( 'Do not index images', 'ai-mcp' ), '' ),
			'breadcrumb_title'    => array( 'text', __( 'Breadcrumb title', 'ai-mcp' ), '' ),
			'primary_term'        => array( 'term', __( 'Primary category', 'ai-mcp' ), __( 'Used in breadcrumbs, permalinks of %%primary_category%% and schema.', 'ai-mcp' ) ),
			'schema_page'         => array( 'choice:types.*.schema_page', __( 'Page type (schema)', 'ai-mcp' ), '' ),
			'schema_article'      => array( 'choice:types.*.schema_article', __( 'Article type (schema)', 'ai-mcp' ), '' ),
			'og_title'            => array( 'text', __( 'Social title', 'ai-mcp' ), __( 'Facebook, LinkedIn, WhatsApp... Empty = SEO title.', 'ai-mcp' ) ),
			'og_description'      => array( 'textarea', __( 'Social description', 'ai-mcp' ), '' ),
			'og_image'            => array( 'url', __( 'Social image', 'ai-mcp' ), __( '1200 x 630 px works everywhere. Empty = featured image.', 'ai-mcp' ) ),
			'twitter_title'       => array( 'text', __( 'X (Twitter) title', 'ai-mcp' ), '' ),
			'twitter_description' => array( 'textarea', __( 'X (Twitter) description', 'ai-mcp' ), '' ),
			'twitter_image'       => array( 'url', __( 'X (Twitter) image', 'ai-mcp' ), '' ),
		);
	}

	/** Fields available on categories, tags and other terms. */
	public static function term_fields() {
		return array_intersect_key( self::fields(), array_flip( array( 'title', 'description', 'focus_keyphrase', 'canonical', 'robots_index', 'nofollow', 'breadcrumb_title', 'og_title', 'og_description', 'og_image' ) ) );
	}

	public static function init() {
		add_action( 'add_meta_boxes', array( __CLASS__, 'add_box' ), 5 );
		add_action( 'save_post', array( __CLASS__, 'save_box' ), 20, 2 );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'assets' ) );
		add_action( 'admin_init', array( __CLASS__, 'term_hooks' ) );
		add_action( 'admin_init', array( __CLASS__, 'column_hooks' ) );
	}

	/** Post types exist only after init, so list columns are hooked on admin_init. */
	public static function column_hooks() {
		foreach ( self::box_types() as $type ) {
			add_filter( 'manage_' . $type . '_posts_columns', array( __CLASS__, 'columns' ) );
			add_action( 'manage_' . $type . '_posts_custom_column', array( __CLASS__, 'column' ), 10, 2 );
		}
	}

	/* ---------- Reading and writing ---------- */

	public static function sanitize( $field, $value, $object_type = 'post' ) {
		$fields = self::fields();
		if ( ! isset( $fields[ $field ] ) ) {
			return new WP_Error( 'moshai_seo_field', sprintf( 'Unknown SEO field: %1$s. Fields: %2$s', $field, implode( ', ', array_keys( 'term' === $object_type ? self::term_fields() : $fields ) ) ) );
		}
		$type = $fields[ $field ][0];
		if ( is_array( $value ) || is_object( $value ) ) {
			$value = '';
		}
		$value = is_bool( $value ) ? ( $value ? '1' : '' ) : (string) $value;
		switch ( true ) {
			case 'bool' === $type:
				return in_array( strtolower( $value ), array( '1', 'true', 'yes', 'on' ), true ) ? '1' : '';
			case 'robots' === $type:
				$value = strtolower( trim( $value ) );
				if ( in_array( $value, array( '', 'default', 'index', 'noindex' ), true ) ) {
					return 'default' === $value ? '' : $value;
				}
				return new WP_Error( 'moshai_seo_value', 'robots_index must be "", "index" or "noindex".' );
			case 'url' === $type:
				return esc_url_raw( trim( $value ) );
			case 'textarea' === $type:
				return sanitize_textarea_field( $value );
			case 'term' === $type:
				return '' === $value ? '' : (string) absint( $value );
			case 0 === strpos( $type, 'choice:' ):
				if ( '' === $value ) {
					return '';
				}
				$choices = MoshAI_SEO_Settings::choices();
				$key     = substr( $type, 7 );
				if ( ! in_array( $value, $choices[ $key ], true ) ) {
					return new WP_Error( 'moshai_seo_value', sprintf( '%1$s must be one of: %2$s', $field, implode( ', ', $choices[ $key ] ) ) );
				}
				return $value;
		}
		return sanitize_text_field( $value );
	}

	public static function get( $post_id, $field ) {
		$v = get_post_meta( $post_id, self::PREFIX . $field, true );
		if ( 'description' === $field && '' === $v ) {
			// Written by the AI MCP editor box before the SEO module existed.
			$v = get_post_meta( $post_id, '_moshai_meta_description', true );
		}
		return is_string( $v ) ? $v : '';
	}

	public static function all( $post_id ) {
		$out = array();
		foreach ( array_keys( self::fields() ) as $f ) {
			$out[ $f ] = self::get( $post_id, $f );
		}
		return $out;
	}

	/** Saves fields for a post. Unknown fields or bad values return WP_Error before anything is saved. */
	public static function update( $post_id, $values ) {
		$clean = array();
		foreach ( (array) $values as $f => $v ) {
			$c = self::sanitize( $f, $v );
			if ( is_wp_error( $c ) ) {
				return $c;
			}
			$clean[ $f ] = $c;
		}
		foreach ( $clean as $f => $v ) {
			if ( '' === $v ) {
				delete_post_meta( $post_id, self::PREFIX . $f );
				if ( 'description' === $f ) {
					delete_post_meta( $post_id, '_moshai_meta_description' );
				}
			} else {
				update_post_meta( $post_id, self::PREFIX . $f, wp_slash( $v ) );
			}
		}
		self::rescore( $post_id );
		return $clean;
	}

	public static function term_get( $term_id, $field ) {
		$v = get_term_meta( $term_id, self::PREFIX . $field, true );
		return is_string( $v ) ? $v : '';
	}

	public static function term_all( $term_id ) {
		$out = array();
		foreach ( array_keys( self::term_fields() ) as $f ) {
			$out[ $f ] = self::term_get( $term_id, $f );
		}
		return $out;
	}

	public static function term_update( $term_id, $values ) {
		$allowed = self::term_fields();
		$clean   = array();
		foreach ( (array) $values as $f => $v ) {
			if ( ! isset( $allowed[ $f ] ) ) {
				return new WP_Error( 'moshai_seo_field', sprintf( 'Terms support: %s', implode( ', ', array_keys( $allowed ) ) ) );
			}
			$c = self::sanitize( $f, $v, 'term' );
			if ( is_wp_error( $c ) ) {
				return $c;
			}
			$clean[ $f ] = $c;
		}
		foreach ( $clean as $f => $v ) {
			if ( '' === $v ) {
				delete_term_meta( $term_id, self::PREFIX . $f );
			} else {
				update_term_meta( $term_id, self::PREFIX . $f, wp_slash( $v ) );
			}
		}
		return $clean;
	}

	/** Re-runs the analysis and stores both scores for list columns and audits. */
	public static function rescore( $post_id ) {
		$post = get_post( $post_id );
		if ( ! $post ) {
			return null;
		}
		$a = MoshAI_SEO_Analyzer::analyze( $post );
		update_post_meta( $post_id, self::PREFIX . 'score', $a['seo']['score'] );
		update_post_meta( $post_id, self::PREFIX . 'read_score', $a['readability']['score'] );
		return $a;
	}

	public static function primary_term( $post ) {
		$tax = 'product' === $post->post_type ? 'product_cat' : 'category';
		if ( ! is_object_in_taxonomy( $post->post_type, $tax ) ) {
			return null;
		}
		$id = (int) self::get( $post->ID, 'primary_term' );
		if ( $id ) {
			$t = get_term( $id, $tax );
			if ( $t && ! is_wp_error( $t ) ) {
				return $t;
			}
		}
		$terms = get_the_terms( $post, $tax );
		return $terms && ! is_wp_error( $terms ) ? $terms[0] : null;
	}

	/* ---------- Resolved values (what search engines see) ---------- */

	public static function type_setting( $type, $key ) {
		return MoshAI_SEO_Settings::get( 'types.' . $type . '.' . $key, MoshAI_SEO_Settings::get( 'types.post.' . $key ) );
	}

	public static function resolved_title( $post ) {
		$own = self::get( $post->ID, 'title' );
		if ( (int) get_option( 'page_on_front' ) === $post->ID && '' === $own ) {
			$tpl = MoshAI_SEO_Settings::get( 'general.home_title' );
		} else {
			$tpl = '' !== $own ? $own : self::type_setting( $post->post_type, 'title' );
		}
		$title = MoshAI_SEO_Vars::replace( $tpl, array( 'post' => $post ) );
		return '' !== $title ? $title : get_the_title( $post );
	}

	public static function resolved_description( $post ) {
		$own = self::get( $post->ID, 'description' );
		if ( '' === $own && (int) get_option( 'page_on_front' ) === $post->ID ) {
			$own = (string) MoshAI_SEO_Settings::get( 'general.home_desc' );
		}
		$tpl = '' !== $own ? $own : self::type_setting( $post->post_type, 'desc' );
		return MoshAI_SEO_Vars::replace( $tpl, array( 'post' => $post ) );
	}

	public static function is_noindex( $post ) {
		$own = self::get( $post->ID, 'robots_index' );
		if ( 'noindex' === $own ) {
			return true;
		}
		if ( 'index' === $own ) {
			return false;
		}
		if ( 'publish' !== $post->post_status && 'inherit' !== $post->post_status ) {
			return true;
		}
		return ! (int) self::type_setting( $post->post_type, 'index' );
	}

	public static function is_term_noindex( $term ) {
		$own = self::term_get( $term->term_id, 'robots_index' );
		if ( 'noindex' === $own ) {
			return true;
		}
		if ( 'index' === $own ) {
			return false;
		}
		return ! (int) MoshAI_SEO_Settings::get( 'tax.' . $term->taxonomy . '.index', 1 );
	}

	/* ---------- Meta box ---------- */

	public static function box_types() {
		$out = array();
		foreach ( array_keys( MoshAI_SEO::post_types() ) as $type ) {
			if ( (int) self::type_setting( $type, 'box' ) ) {
				$out[] = $type;
			}
		}
		return $out;
	}

	public static function add_box() {
		if ( ! current_user_can( 'edit_posts' ) ) {
			return;
		}
		add_meta_box( 'moshai-seo', __( 'SEO · AI MCP', 'ai-mcp' ), array( __CLASS__, 'render_box' ), self::box_types(), 'normal', 'high' );
	}

	public static function assets( $hook ) {
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		$is_post = in_array( $hook, array( 'post.php', 'post-new.php' ), true ) && $screen && in_array( $screen->post_type, self::box_types(), true );
		$is_term = in_array( $hook, array( 'term.php', 'edit-tags.php' ), true );
		if ( ! $is_post && ! $is_term ) {
			return;
		}
		wp_enqueue_style( 'moshai-seo', MOSHAI_SEO_URL . 'assets/seo.css', array(), MOSHAI_VERSION );
		if ( $is_post ) {
			wp_enqueue_media();
			wp_enqueue_script( 'moshai-seo-box', MOSHAI_SEO_URL . 'assets/seo-metabox.js', array(), MOSHAI_VERSION, true );
			wp_localize_script(
				'moshai-seo-box',
				'MoshAISEOBox',
				array(
					'rest'    => esc_url_raw( rest_url( 'ai-mcp/v1/' ) ),
					'nonce'   => wp_create_nonce( 'wp_rest' ),
					'sep'     => MoshAI_SEO_Vars::sep(),
					'site'    => get_bloginfo( 'name' ),
					'home'    => home_url( '/' ),
					'hasAI'   => class_exists( 'MoshAI_Settings' ) && MoshAI_Settings::has_ai(),
					'i18n'    => array(
						'good'    => __( 'Good', 'ai-mcp' ),
						'ok'      => __( 'Needs improvement', 'ai-mcp' ),
						'bad'     => __( 'Problems', 'ai-mcp' ),
						'writing' => __( 'Writing…', 'ai-mcp' ),
					),
				)
			);
		}
	}

	private static function input( $name, $field, $value, $post = null ) {
		$def   = self::fields();
		$def   = $def[ $field ];
		$id    = 'moshai-seo-' . $field;
		$attr  = ' id="' . esc_attr( $id ) . '" name="' . esc_attr( $name ) . '" data-seo-field="' . esc_attr( $field ) . '"';
		$label = '<label class="mx-seo-label" for="' . esc_attr( $id ) . '">' . esc_html( $def[1] ) . '</label>';
		$help  = $def[2] ? '<p class="mx-seo-help">' . esc_html( $def[2] ) . '</p>' : '';
		switch ( $def[0] ) {
			case 'textarea':
				return '<div class="mx-seo-field">' . $label . '<textarea rows="3"' . $attr . '>' . esc_textarea( $value ) . '</textarea>' . $help . '</div>';
			case 'bool':
				return '<div class="mx-seo-field mx-seo-check"><label><input type="checkbox" value="1"' . $attr . checked( '1', $value, false ) . '> ' . esc_html( $def[1] ) . '</label>' . $help . '</div>';
			case 'robots':
				$opts = array(
					''        => __( 'Default for this content type', 'ai-mcp' ),
					'index'   => __( 'Yes, show it', 'ai-mcp' ),
					'noindex' => __( 'No, hide it (noindex)', 'ai-mcp' ),
				);
				$h = '<select' . $attr . '>';
				foreach ( $opts as $k => $l ) {
					$h .= '<option value="' . esc_attr( $k ) . '"' . selected( $value, $k, false ) . '>' . esc_html( $l ) . '</option>';
				}
				return '<div class="mx-seo-field">' . $label . $h . '</select>' . $help . '</div>';
			case 'term':
				if ( ! $post ) {
					return '';
				}
				$tax = 'product' === $post->post_type ? 'product_cat' : 'category';
				if ( ! is_object_in_taxonomy( $post->post_type, $tax ) ) {
					return '';
				}
				$terms = get_the_terms( $post, $tax );
				$h     = '<select' . $attr . '><option value="">' . esc_html__( 'First category', 'ai-mcp' ) . '</option>';
				foreach ( ( $terms && ! is_wp_error( $terms ) ) ? $terms : array() as $t ) {
					$h .= '<option value="' . esc_attr( $t->term_id ) . '"' . selected( $value, (string) $t->term_id, false ) . '>' . esc_html( $t->name ) . '</option>';
				}
				return '<div class="mx-seo-field">' . $label . $h . '</select>' . $help . '</div>';
		}
		if ( 0 === strpos( $def[0], 'choice:' ) ) {
			$choices = MoshAI_SEO_Settings::choices();
			$h       = '<select' . $attr . '><option value="">' . esc_html__( 'Default for this content type', 'ai-mcp' ) . '</option>';
			foreach ( $choices[ substr( $def[0], 7 ) ] as $c ) {
				$h .= '<option value="' . esc_attr( $c ) . '"' . selected( $value, $c, false ) . '>' . esc_html( $c ) . '</option>';
			}
			return '<div class="mx-seo-field">' . $label . $h . '</select>' . $help . '</div>';
		}
		$extra = 'url' === $def[0] && false !== strpos( $field, 'image' ) ? '<button type="button" class="button" data-seo-media="' . esc_attr( $id ) . '">' . esc_html__( 'Choose image', 'ai-mcp' ) . '</button>' : '';
		return '<div class="mx-seo-field">' . $label . '<div class="mx-seo-inline"><input type="' . ( 'url' === $def[0] ? 'url' : 'text' ) . '" value="' . esc_attr( $value ) . '"' . $attr . '>' . $extra . '</div>' . $help . '</div>';
	}

	public static function render_box( $post ) {
		wp_nonce_field( 'moshai_seo_box', 'moshai_seo_nonce' );
		$v        = self::all( $post->ID );
		$score    = get_post_meta( $post->ID, self::PREFIX . 'score', true );
		$read     = get_post_meta( $post->ID, self::PREFIX . 'read_score', true );
		$template = self::type_setting( $post->post_type, 'title' );
		$dtpl     = self::type_setting( $post->post_type, 'desc' );
		$n        = function ( $f ) {
			return 'moshai_seo[' . $f . ']';
		};
		$html  = '<div class="mx-seo-box" data-post="' . esc_attr( $post->ID ) . '" data-title-template="' . esc_attr( $template ) . '" data-desc-template="' . esc_attr( $dtpl ) . '" data-resolved-title="' . esc_attr( self::resolved_title( $post ) ) . '" data-resolved-desc="' . esc_attr( self::resolved_description( $post ) ) . '">';
		$html .= '<nav class="mx-seo-tabs"><button type="button" class="is-active" data-seo-tab="seo"><span class="mx-seo-dot" data-dot="seo" data-rating="' . esc_attr( MoshAI_SEO_Analyzer::rating( $score ) ) . '"></span>' . esc_html__( 'SEO', 'ai-mcp' ) . '</button><button type="button" data-seo-tab="read"><span class="mx-seo-dot" data-dot="read" data-rating="' . esc_attr( MoshAI_SEO_Analyzer::rating( $read ) ) . '"></span>' . esc_html__( 'Readability', 'ai-mcp' ) . '</button><button type="button" data-seo-tab="social">' . esc_html__( 'Social', 'ai-mcp' ) . '</button><button type="button" data-seo-tab="advanced">' . esc_html__( 'Advanced', 'ai-mcp' ) . '</button></nav>';

		// SEO tab.
		$html .= '<section class="mx-seo-pane" data-seo-pane="seo">';
		$html .= self::input( $n( 'focus_keyphrase' ), 'focus_keyphrase', $v['focus_keyphrase'] );
		$html .= '<div class="mx-seo-preview" aria-label="' . esc_attr__( 'Google preview', 'ai-mcp' ) . '"><div class="mx-seo-preview-head"><strong>' . esc_html__( 'Google preview', 'ai-mcp' ) . '</strong><span><button type="button" class="is-active" data-seo-device="desktop">' . esc_html__( 'Desktop', 'ai-mcp' ) . '</button><button type="button" data-seo-device="mobile">' . esc_html__( 'Mobile', 'ai-mcp' ) . '</button></span></div><div class="mx-serp" data-serp><div class="mx-serp-site"><span class="mx-serp-fav"></span><span><span class="mx-serp-name">' . esc_html( get_bloginfo( 'name' ) ) . '</span><span class="mx-serp-url" data-serp-url>' . esc_html( get_permalink( $post ) ) . '</span></span></div><div class="mx-serp-title" data-serp-title></div><div class="mx-serp-desc" data-serp-desc></div></div></div>';
		$ai    = class_exists( 'MoshAI_Settings' ) && MoshAI_Settings::has_ai();
		$html .= '<div class="mx-seo-field"><label class="mx-seo-label" for="moshai-seo-title">' . esc_html__( 'SEO title', 'ai-mcp' ) . ' <span class="mx-seo-count" data-count="title"></span>' . ( $ai ? ' <button type="button" class="button-link" data-seo-ai="title">✨ ' . esc_html__( 'Write with AI', 'ai-mcp' ) . '</button>' : '' ) . '</label><input type="text" id="moshai-seo-title" name="' . esc_attr( $n( 'title' ) ) . '" data-seo-field="title" value="' . esc_attr( $v['title'] ) . '" placeholder="' . esc_attr( $template ) . '"><div class="mx-seo-meter"><span data-meter="title"></span></div><p class="mx-seo-help">' . esc_html__( 'Empty = template. Variables: %%title%% %%sep%% %%sitename%% %%page%% %%primary_category%%', 'ai-mcp' ) . '</p></div>';
		$html .= '<div class="mx-seo-field"><label class="mx-seo-label" for="moshai-seo-description">' . esc_html__( 'Meta description', 'ai-mcp' ) . ' <span class="mx-seo-count" data-count="description"></span>' . ( $ai ? ' <button type="button" class="button-link" data-seo-ai="description">✨ ' . esc_html__( 'Write with AI', 'ai-mcp' ) . '</button>' : '' ) . '</label><textarea rows="3" id="moshai-seo-description" name="' . esc_attr( $n( 'description' ) ) . '" data-seo-field="description" placeholder="' . esc_attr( self::resolved_description( $post ) ) . '">' . esc_textarea( $v['description'] ) . '</textarea><div class="mx-seo-meter"><span data-meter="description"></span></div></div>';
		$html .= '<div class="mx-seo-field"><span class="mx-seo-label">' . esc_html__( 'URL slug', 'ai-mcp' ) . '</span><code class="mx-seo-slug" data-seo-slug>' . esc_html( $post->post_name ) . '</code></div>';
		$html .= self::input( $n( 'related_keyphrases' ), 'related_keyphrases', $v['related_keyphrases'] );
		$html .= self::input( $n( 'cornerstone' ), 'cornerstone', $v['cornerstone'] );
		$html .= '<h4 class="mx-seo-sub">' . esc_html__( 'SEO analysis', 'ai-mcp' ) . ' <span class="mx-seo-score" data-score-label="seo"></span></h4><ul class="mx-seo-checks" data-checks="seo"><li class="is-loading">' . esc_html__( 'Analyzing…', 'ai-mcp' ) . '</li></ul>';
		$html .= '</section>';

		// Readability tab.
		$html .= '<section class="mx-seo-pane" data-seo-pane="read" hidden><h4 class="mx-seo-sub">' . esc_html__( 'Readability analysis', 'ai-mcp' ) . ' <span class="mx-seo-score" data-score-label="read"></span></h4><ul class="mx-seo-checks" data-checks="read"></ul></section>';

		// Social tab.
		$html .= '<section class="mx-seo-pane" data-seo-pane="social" hidden><div class="mx-seo-cols"><div><h4 class="mx-seo-sub">Facebook, LinkedIn, WhatsApp</h4>';
		$html .= self::input( $n( 'og_title' ), 'og_title', $v['og_title'] ) . self::input( $n( 'og_description' ), 'og_description', $v['og_description'] ) . self::input( $n( 'og_image' ), 'og_image', $v['og_image'] );
		$html .= '</div><div><h4 class="mx-seo-sub">X (Twitter)</h4>';
		$html .= self::input( $n( 'twitter_title' ), 'twitter_title', $v['twitter_title'] ) . self::input( $n( 'twitter_description' ), 'twitter_description', $v['twitter_description'] ) . self::input( $n( 'twitter_image' ), 'twitter_image', $v['twitter_image'] );
		$html .= '</div></div></section>';

		// Advanced tab.
		$html .= '<section class="mx-seo-pane" data-seo-pane="advanced" hidden><div class="mx-seo-cols"><div>';
		$html .= self::input( $n( 'robots_index' ), 'robots_index', $v['robots_index'] ) . self::input( $n( 'nofollow' ), 'nofollow', $v['nofollow'] ) . self::input( $n( 'noarchive' ), 'noarchive', $v['noarchive'] ) . self::input( $n( 'nosnippet' ), 'nosnippet', $v['nosnippet'] ) . self::input( $n( 'noimageindex' ), 'noimageindex', $v['noimageindex'] );
		$html .= '</div><div>';
		$html .= self::input( $n( 'canonical' ), 'canonical', $v['canonical'] ) . self::input( $n( 'breadcrumb_title' ), 'breadcrumb_title', $v['breadcrumb_title'] ) . self::input( $n( 'primary_term' ), 'primary_term', $v['primary_term'], $post ) . self::input( $n( 'schema_page' ), 'schema_page', $v['schema_page'] ) . self::input( $n( 'schema_article' ), 'schema_article', $v['schema_article'] );
		$html .= '</div></div></section></div>';

		MoshAI_Kses::out( $html );
	}

	public static function save_box( $post_id, $post ) {
		if ( ! isset( $_POST['moshai_seo_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['moshai_seo_nonce'] ) ), 'moshai_seo_box' ) ) {
			return;
		}
		if ( ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) || wp_is_post_revision( $post_id ) || ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}
		$raw    = isset( $_POST['moshai_seo'] ) && is_array( $_POST['moshai_seo'] ) ? wp_unslash( $_POST['moshai_seo'] ) : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- sanitized per field below.
		$values = array();
		foreach ( array_keys( self::fields() ) as $f ) {
			if ( 'primary_term' === $f && ! isset( $raw[ $f ] ) ) {
				continue;
			}
			$values[ $f ] = isset( $raw[ $f ] ) ? $raw[ $f ] : '';
		}
		foreach ( $values as $f => $v ) {
			if ( is_wp_error( self::sanitize( $f, $v ) ) ) {
				unset( $values[ $f ] );
			}
		}
		self::update( $post_id, $values );
	}

	/* ---------- Term fields ---------- */

	public static function term_hooks() {
		foreach ( array_keys( MoshAI_SEO::taxonomies() ) as $tax ) {
			add_action( $tax . '_edit_form', array( __CLASS__, 'term_form' ), 20, 2 );
			add_action( 'edited_' . $tax, array( __CLASS__, 'term_save' ) );
		}
	}

	public static function term_form( $term, $taxonomy ) {
		wp_nonce_field( 'moshai_seo_term', 'moshai_seo_term_nonce' );
		$v    = self::term_all( $term->term_id );
		$html = '<div class="mx-seo-box mx-seo-term"><h2>' . esc_html__( 'SEO · AI MCP', 'ai-mcp' ) . '</h2><div class="mx-seo-cols"><div>';
		foreach ( array( 'title', 'description', 'focus_keyphrase', 'robots_index', 'nofollow' ) as $f ) {
			$html .= self::input( 'moshai_seo[' . $f . ']', $f, $v[ $f ] );
		}
		$html .= '</div><div>';
		foreach ( array( 'canonical', 'breadcrumb_title', 'og_title', 'og_description', 'og_image' ) as $f ) {
			$html .= self::input( 'moshai_seo[' . $f . ']', $f, $v[ $f ] );
		}
		$html .= '</div></div></div>';
		MoshAI_Kses::out( $html );
	}

	public static function term_save( $term_id ) {
		if ( ! isset( $_POST['moshai_seo_term_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['moshai_seo_term_nonce'] ) ), 'moshai_seo_term' ) || ! current_user_can( 'edit_term', $term_id ) ) {
			return;
		}
		$raw    = isset( $_POST['moshai_seo'] ) && is_array( $_POST['moshai_seo'] ) ? wp_unslash( $_POST['moshai_seo'] ) : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- sanitized per field.
		$values = array();
		foreach ( array_keys( self::term_fields() ) as $f ) {
			$values[ $f ] = isset( $raw[ $f ] ) ? $raw[ $f ] : '';
			if ( is_wp_error( self::sanitize( $f, $values[ $f ], 'term' ) ) ) {
				unset( $values[ $f ] );
			}
		}
		self::term_update( $term_id, $values );
	}

	/* ---------- List table column ---------- */

	public static function columns( $cols ) {
		$cols['moshai_seo'] = '<span class="dashicons dashicons-search" title="' . esc_attr__( 'SEO score', 'ai-mcp' ) . '"></span><span class="screen-reader-text">' . esc_html__( 'SEO', 'ai-mcp' ) . '</span>';
		return $cols;
	}

	public static function column( $col, $post_id ) {
		if ( 'moshai_seo' !== $col ) {
			return;
		}
		$s = get_post_meta( $post_id, self::PREFIX . 'score', true );
		$r = get_post_meta( $post_id, self::PREFIX . 'read_score', true );
		echo '<span class="mx-seo-dot" data-rating="' . esc_attr( MoshAI_SEO_Analyzer::rating( $s ) ) . '" title="' . esc_attr( sprintf( 'SEO: %s', '' === $s ? '-' : $s ) ) . '"></span> <span class="mx-seo-dot" data-rating="' . esc_attr( MoshAI_SEO_Analyzer::rating( $r ) ) . '" title="' . esc_attr( sprintf( 'Readability: %s', '' === $r ? '-' : $r ) ) . '"></span>';
		if ( 'noindex' === self::get( $post_id, 'robots_index' ) ) {
			echo ' <span class="mx-seo-noindex">noindex</span>';
		}
	}
}
