<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * AI assistant meta box on posts, pages and WooCommerce products, plus a lightweight meta description output.
 */
class MoshAI_Editor {

	public static function init() {
		if ( MoshAI_Settings::get( 'editor_assistant' ) ) {
			add_action( 'add_meta_boxes', array( __CLASS__, 'add_box' ) );
			add_action( 'admin_enqueue_scripts', array( __CLASS__, 'assets' ) );
		}
		if ( MoshAI_Settings::get( 'meta_description' ) ) {
			add_action( 'save_post', array( __CLASS__, 'save_post' ) );
			add_action( 'wp_head', array( __CLASS__, 'head' ), 1 );
		}
	}

	public static function post_types() {
		$types = get_post_types( array( 'show_ui' => true ) );
		unset( $types['attachment'], $types['wp_block'], $types['wp_navigation'], $types['elementor_library'] );
		return array_values( apply_filters( 'moshai_editor_post_types', $types ) );
	}

	public static function add_box() {
		if ( ! current_user_can( 'edit_posts' ) || ! MoshAI_Settings::has_ai() ) {
			return;
		}
		add_meta_box( 'moshai-assistant', __( 'AI MCP Assistant', 'ai-mcp' ), array( __CLASS__, 'render' ), self::post_types(), 'side', 'high' );
	}

	public static function assets( $hook ) {
		if ( ! in_array( $hook, array( 'post.php', 'post-new.php' ), true ) ) {
			return;
		}
		wp_enqueue_style( 'moshai-admin', MOSHAI_URL . 'assets/css/admin.css', array(), MOSHAI_VERSION );
		wp_enqueue_script( 'moshai-editor', MOSHAI_URL . 'assets/js/editor.js', array(), MOSHAI_VERSION, true );
		wp_localize_script(
			'moshai-editor',
			'MoshAIEditor',
			array(
				'rest'  => esc_url_raw( rest_url( 'ai-mcp/v1/' ) ),
				'nonce' => wp_create_nonce( 'wp_rest' ),
			)
		);
	}

	public static function render( $post ) {
		$is_product = 'product' === $post->post_type;
		$buttons    = array(
			'titles'  => __( 'Suggest titles', 'ai-mcp' ),
			'excerpt' => __( 'Write excerpt', 'ai-mcp' ),
			'seo'     => __( 'SEO description', 'ai-mcp' ),
			'tags'    => __( 'Suggest tags', 'ai-mcp' ),
			'expand'  => __( 'Write / expand content', 'ai-mcp' ),
		);
		if ( $is_product ) {
			$buttons = array(
				'titles'              => __( 'Suggest names', 'ai-mcp' ),
				'product_description' => __( 'Product description', 'ai-mcp' ),
				'product_short'       => __( 'Short description', 'ai-mcp' ),
				'seo'                 => __( 'SEO description', 'ai-mcp' ),
				'tags'                => __( 'Suggest tags', 'ai-mcp' ),
			);
		}
		echo '<div id="moshai-assistant-box" data-post="' . esc_attr( $post->ID ) . '" data-type="' . esc_attr( $post->post_type ) . '">';
		echo '<div class="moshai-actions">';
		foreach ( $buttons as $task => $label ) {
			echo '<button type="button" class="button" data-task="' . esc_attr( $task ) . '">' . esc_html( $label ) . '</button>';
		}
		echo '</div>';
		echo '<textarea class="moshai-instruction widefat" rows="3" placeholder="' . esc_attr__( 'Optional: extra instructions, or a custom request (e.g. "Rewrite the intro shorter").', 'ai-mcp' ) . '"></textarea>';
		echo '<p><button type="button" class="button button-primary" data-task="custom">' . esc_html__( 'Ask AI', 'ai-mcp' ) . '</button></p>';
		echo '<div class="moshai-result" hidden></div>';
		if ( MoshAI_Settings::get( 'meta_description' ) ) {
			wp_nonce_field( 'moshai_meta', 'moshai_meta_nonce' );
			$value = get_post_meta( $post->ID, '_moshai_meta_description', true );
			echo '<hr><p><label for="moshai-meta"><strong>' . esc_html__( 'Meta description', 'ai-mcp' ) . '</strong> <span class="moshai-meta-count"></span></label>';
			echo '<textarea id="moshai-meta" name="moshai_meta_description" class="widefat" rows="3" maxlength="320">' . esc_textarea( $value ) . '</textarea>';
			echo '<span class="description">' . esc_html__( 'Saved with the post. Skipped when an SEO plugin is active.', 'ai-mcp' ) . '</span></p>';
		}
		echo '</div>';
	}

	public static function save_post( $post_id ) {
		if ( ! isset( $_POST['moshai_meta_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['moshai_meta_nonce'] ) ), 'moshai_meta' ) ) {
			return;
		}
		if ( ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) || wp_is_post_revision( $post_id ) || ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}
		if ( isset( $_POST['moshai_meta_description'] ) ) {
			update_post_meta( $post_id, '_moshai_meta_description', sanitize_text_field( wp_unslash( $_POST['moshai_meta_description'] ) ) );
		}
	}

	public static function head() {
		if ( ! is_singular() ) {
			return;
		}
		if ( defined( 'WPSEO_VERSION' ) || defined( 'RANK_MATH_VERSION' ) || defined( 'AIOSEO_VERSION' ) || defined( 'SEOPRESS_VERSION' ) || defined( 'THE_SEO_FRAMEWORK_VERSION' ) ) {
			return;
		}
		$desc = get_post_meta( get_queried_object_id(), '_moshai_meta_description', true );
		if ( $desc ) {
			echo '<meta name="description" content="' . esc_attr( $desc ) . '" />' . "\n";
		}
	}
}
