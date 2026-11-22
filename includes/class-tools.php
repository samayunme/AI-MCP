<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Extra tools shared by the MCP server and the dashboard AI Agent:
 * WooCommerce (orders, refunds, products, stock, customers, coupons, sales),
 * comments, terms, users, plugins, options and a raw REST bridge that reaches every
 * WordPress / WooCommerce REST route.
 */
class MoshAI_Tools {

	public static function init() {
		add_filter( 'moshai_mcp_tools', array( __CLASS__, 'register' ) );
		add_filter( 'moshai_mcp_call', array( __CLASS__, 'call' ), 10, 3 );
	}

	private static function woo() {
		return class_exists( 'WooCommerce' ) && function_exists( 'wc_get_order' );
	}

	public static function register( $tools ) {
		$t   = array( 'MoshAI_MCP', 'tool' );
		$int = array( 'type' => 'integer' );
		$str = array( 'type' => 'string' );
		$num = array( 'type' => 'number' );
		$bool = array( 'type' => 'boolean' );
		$obj = array(
			'type'        => 'object',
			'description' => 'Key/value object',
		);

		$tools[] = call_user_func(
			$t,
			'wp_rest_request',
			'Call any WordPress or WooCommerce REST route as the current admin. Use it for anything the other tools do not cover. Examples: GET /wp/v2/settings, POST /wp/v2/pages/12, GET /wc/v3/orders?status=processing, PUT /wc/v3/products/55.',
			array(
				'method' => array(
					'type'        => 'string',
					'enum'        => array( 'GET', 'POST', 'PUT', 'PATCH', 'DELETE' ),
					'description' => 'HTTP method',
				),
				'path'   => array(
					'type'        => 'string',
					'description' => 'Route starting with /, e.g. /wp/v2/posts. Query strings allowed.',
				),
				'params' => $obj,
			),
			array( 'method', 'path' )
		);
		$tools[] = call_user_func(
			$t,
			'wp_list_comments',
			'List comments or product reviews.',
			array(
				'status'   => array(
					'type'        => 'string',
					'description' => 'all, hold, approve, spam, trash. Default all.',
				),
				'post_id'  => $int,
				'search'   => $str,
				'per_page' => $int,
			)
		);
		$tools[] = call_user_func(
			$t,
			'wp_moderate_comment',
			'Approve, hold, spam or trash a comment, or reply to it.',
			array(
				'id'     => $int,
				'status' => array(
					'type' => 'string',
					'enum' => array( 'approve', 'hold', 'spam', 'trash' ),
				),
				'reply'  => $str,
			),
			array( 'id' )
		);
		$tools[] = call_user_func(
			$t,
			'wp_create_term',
			'Create a category, tag or any taxonomy term (product_cat, product_tag...).',
			array(
				'taxonomy' => $str,
				'name'     => $str,
				'slug'     => $str,
				'parent'   => $int,
			),
			array( 'taxonomy', 'name' )
		);
		$tools[] = call_user_func(
			$t,
			'wp_set_post_terms',
			'Assign terms (IDs or names) to a post or product.',
			array(
				'id'       => $int,
				'taxonomy' => $str,
				'terms'    => array(
					'type'  => 'array',
					'items' => $str,
				),
				'append'   => $bool,
			),
			array( 'id', 'taxonomy', 'terms' )
		);
		$tools[] = call_user_func( $t, 'wp_set_featured_image', 'Set the featured image of a post or product.', array( 'id' => $int, 'media_id' => $int ), array( 'id', 'media_id' ) );
		$tools[] = call_user_func(
			$t,
			'wp_create_user',
			'Create a user. A password is generated and emailed if notify is true.',
			array(
				'username'     => $str,
				'email'        => $str,
				'role'         => $str,
				'display_name' => $str,
				'notify'       => $bool,
			),
			array( 'username', 'email' )
		);
		$tools[] = call_user_func(
			$t,
			'wp_update_user',
			'Update a user: role, display name, email, first/last name.',
			array(
				'id'           => $int,
				'role'         => $str,
				'display_name' => $str,
				'email'        => $str,
				'first_name'   => $str,
				'last_name'    => $str,
			),
			array( 'id' )
		);
		$tools[] = call_user_func(
			$t,
			'wp_toggle_plugin',
			'Activate or deactivate a plugin by its file, e.g. akismet/akismet.php (see wp_list_plugins).',
			array(
				'plugin' => $str,
				'active' => $bool,
			),
			array( 'plugin', 'active' )
		);
		$tools[] = call_user_func(
			$t,
			'wp_update_option',
			'Change any site or plugin setting (WordPress option): blogname, page_on_front, WooCommerce, Elementor, CartFlows... Use path to change one key inside an array option (e.g. path "general.enable" sets $option["general"]["enable"]). value may be text, a number, true/false or JSON. Site URLs, roles, admin email, active plugins and secrets are blocked.',
			array(
				'key'   => $str,
				'value' => array( 'description' => 'New value: text, number, boolean, or a JSON object/array' ),
				'path'  => array(
					'type'        => 'string',
					'description' => 'Optional dot path inside an array option',
				),
			),
			array( 'key', 'value' )
		);

		/* ---- Site control: plugin settings, Elementor, CSS, caches ---- */
		$tools[] = call_user_func(
			$t,
			'wp_list_options',
			'Find site and plugin settings (WordPress options) by name, e.g. "elementor", "woocommerce_", "cartflows". Returns names with a short value preview. Secrets are hidden.',
			array(
				'search' => $str,
				'limit'  => $int,
			),
			array( 'search' )
		);
		$tools[] = call_user_func(
			$t,
			'wp_install_plugin',
			'Install a plugin from wordpress.org by its slug (e.g. "wordpress-seo", "woocommerce-gateway-stripe") and optionally activate it.',
			array(
				'slug'     => $str,
				'activate' => $bool,
			),
			array( 'slug' )
		);
		$tools[] = call_user_func( $t, 'elementor_list_features', 'List Elementor features and experiments (Atomic editor / V4, containers, MCP and AI features, performance options) with their current state.' );
		$tools[] = call_user_func(
			$t,
			'elementor_set_feature',
			'Turn an Elementor feature or experiment on or off. Use the name from elementor_list_features.',
			array(
				'name'  => $str,
				'state' => array(
					'type' => 'string',
					'enum' => array( 'active', 'inactive', 'default' ),
				),
			),
			array( 'name', 'state' )
		);
		$tools[] = call_user_func( $t, 'elementor_get_kit', 'Read Elementor Site Settings (global colors, fonts, layout, WooCommerce pages) of the active kit.' );
		$tools[] = call_user_func(
			$t,
			'elementor_update_kit',
			'Change Elementor Site Settings. By default MERGES your keys into the existing settings, so nothing else is lost. Lists (like system_colors) are replaced as a whole, so send the full list.',
			array(
				'settings' => $obj,
				'merge'    => $bool,
			),
			array( 'settings' )
		);
		$tools[] = call_user_func( $t, 'wp_get_custom_css', 'Read the theme\'s Additional CSS (Customizer).' );
		$tools[] = call_user_func(
			$t,
			'wp_update_custom_css',
			'Replace or append to the theme\'s Additional CSS.',
			array(
				'css'  => $str,
				'mode' => array(
					'type' => 'string',
					'enum' => array( 'replace', 'append' ),
				),
			),
			array( 'css' )
		);
		$tools[] = call_user_func( $t, 'wp_flush_cache', 'Flush WordPress object cache, rewrite rules, Elementor CSS and popular cache plugins (LiteSpeed, WP Rocket, W3 Total Cache, WP Super Cache, SG Optimizer, Breeze).' );

		if ( ! self::woo() ) {
			return $tools;
		}

		$tools[] = call_user_func(
			$t,
			'wc_list_orders',
			'List WooCommerce orders, newest first.',
			array(
				'status'   => array(
					'type'        => 'string',
					'description' => 'any, pending, processing, on-hold, completed, cancelled, refunded, failed. Default any.',
				),
				'search'   => array(
					'type'        => 'string',
					'description' => 'Customer email, name or order number',
				),
				'after'    => array(
					'type'        => 'string',
					'description' => 'Only orders created on/after this date, YYYY-MM-DD',
				),
				'per_page' => $int,
				'page'     => $int,
			)
		);
		$tools[] = call_user_func( $t, 'wc_get_order', 'Full details of one order: items, totals, addresses, notes.', array( 'id' => $int ), array( 'id' ) );
		$tools[] = call_user_func(
			$t,
			'wc_update_order',
			'Change an order status and/or add a note (private, or sent to the customer).',
			array(
				'id'            => $int,
				'status'        => array(
					'type'        => 'string',
					'description' => 'pending, processing, on-hold, completed, cancelled, refunded, failed',
				),
				'note'          => $str,
				'customer_note' => array(
					'type'        => 'boolean',
					'description' => 'true = email the note to the customer',
				),
			),
			array( 'id' )
		);
		$tools[] = call_user_func(
			$t,
			'wc_create_refund',
			'Refund an order fully or partly. refund_payment=true also refunds through the payment gateway.',
			array(
				'id'             => $int,
				'amount'         => $num,
				'reason'         => $str,
				'refund_payment' => $bool,
				'restock'        => $bool,
			),
			array( 'id', 'amount' )
		);
		$tools[] = call_user_func(
			$t,
			'wc_list_products',
			'List or search products with price and stock.',
			array(
				'search'       => $str,
				'status'       => array(
					'type'        => 'string',
					'description' => 'publish, draft, any. Default any.',
				),
				'stock_status' => array(
					'type'        => 'string',
					'description' => 'instock, outofstock, onbackorder',
				),
				'low_stock'    => array(
					'type'        => 'integer',
					'description' => 'Only products with stock at or below this number',
				),
				'category'     => array(
					'type'        => 'string',
					'description' => 'Category slug',
				),
				'sku'          => $str,
				'per_page'     => $int,
				'page'         => $int,
			)
		);
		$tools[] = call_user_func( $t, 'wc_get_product', 'Full details of one product, including variations.', array( 'id' => $int ), array( 'id' ) );
		$tools[] = call_user_func(
			$t,
			'wc_update_product',
			'Update a product or variation. Only send fields that change. Empty sale_price removes the sale.',
			array(
				'id'                => $int,
				'name'              => $str,
				'regular_price'     => $str,
				'sale_price'        => $str,
				'sku'               => $str,
				'status'            => $str,
				'description'       => $str,
				'short_description' => $str,
				'manage_stock'      => $bool,
				'stock_quantity'    => $int,
				'stock_status'      => $str,
				'featured'          => $bool,
				'category_ids'      => array(
					'type'  => 'array',
					'items' => $int,
				),
			),
			array( 'id' )
		);
		$tools[] = call_user_func(
			$t,
			'wc_update_stock',
			'Set or adjust stock. Use quantity to set an exact number, or adjust (+5 / -2) to change it.',
			array(
				'id'       => $int,
				'quantity' => $int,
				'adjust'   => $int,
			),
			array( 'id' )
		);
		$tools[] = call_user_func(
			$t,
			'wc_create_product',
			'Create a simple product. Status defaults to draft.',
			array(
				'name'              => $str,
				'regular_price'     => $str,
				'sale_price'        => $str,
				'description'       => $str,
				'short_description' => $str,
				'sku'               => $str,
				'stock_quantity'    => $int,
				'virtual'           => $bool,
				'downloadable'      => $bool,
				'status'            => $str,
				'category_ids'      => array(
					'type'  => 'array',
					'items' => $int,
				),
			),
			array( 'name' )
		);
		$tools[] = call_user_func(
			$t,
			'wc_list_customers',
			'List or search customers with order count and total spent.',
			array(
				'search'   => $str,
				'per_page' => $int,
			)
		);
		$tools[] = call_user_func(
			$t,
			'wc_sales_report',
			'Sales summary: orders, revenue, refunds, average order and top products for the last N days.',
			array( 'days' => $int )
		);
		$tools[] = call_user_func( $t, 'wc_list_coupons', 'List coupons.', array( 'per_page' => $int ) );
		$tools[] = call_user_func(
			$t,
			'wc_create_coupon',
			'Create a coupon.',
			array(
				'code'          => $str,
				'discount_type' => array(
					'type' => 'string',
					'enum' => array( 'percent', 'fixed_cart', 'fixed_product' ),
				),
				'amount'        => $num,
				'expires'       => array(
					'type'        => 'string',
					'description' => 'YYYY-MM-DD',
				),
				'usage_limit'   => $int,
				'individual'    => $bool,
				'free_shipping' => $bool,
			),
			array( 'code', 'amount' )
		);

		return $tools;
	}

	private static function arg( $a, $k, $default = null ) {
		return isset( $a[ $k ] ) && '' !== $a[ $k ] ? $a[ $k ] : $default;
	}

	private static function per_page( $a, $default = 20 ) {
		return min( 100, max( 1, (int) self::arg( $a, 'per_page', $default ) ) );
	}

	public static function call( $result, $name, $a ) {
		if ( null !== $result ) {
			return $result;
		}
		$a = (array) $a;
		if ( 0 === strpos( $name, 'wc_' ) && ! self::woo() ) {
			return new WP_Error( 'no_woo', 'WooCommerce is not active on this site.' );
		}
		switch ( $name ) {
			case 'wp_rest_request':
				return self::rest( $a );
			case 'wp_list_comments':
				return self::comments( $a );
			case 'wp_moderate_comment':
				return self::moderate( $a );
			case 'wp_create_term':
				$res = wp_insert_term(
					sanitize_text_field( (string) $a['name'] ),
					sanitize_key( (string) $a['taxonomy'] ),
					array(
						'slug'   => sanitize_title( (string) self::arg( $a, 'slug', '' ) ),
						'parent' => (int) self::arg( $a, 'parent', 0 ),
					)
				);
				return is_wp_error( $res ) ? $res : array( 'term_id' => (int) $res['term_id'] );
			case 'wp_set_post_terms':
				$terms = array();
				foreach ( (array) $a['terms'] as $term ) {
					$terms[] = is_numeric( $term ) ? (int) $term : sanitize_text_field( (string) $term );
				}
				$res = wp_set_object_terms( (int) $a['id'], $terms, sanitize_key( (string) $a['taxonomy'] ), ! empty( $a['append'] ) );
				return is_wp_error( $res ) ? $res : array( 'term_ids' => array_map( 'intval', $res ) );
			case 'wp_set_featured_image':
				$ok = set_post_thumbnail( (int) $a['id'], (int) $a['media_id'] );
				return $ok ? array( 'updated' => true ) : new WP_Error( 'failed', 'Could not set the featured image. Check both IDs.' );
			case 'wp_create_user':
				return self::create_user( $a );
			case 'wp_update_user':
				return self::update_user( $a );
			case 'wp_toggle_plugin':
				return self::toggle_plugin( $a );
			case 'wp_update_option':
				return self::update_option( $a );
			case 'wp_list_options':
				return self::list_options( $a );
			case 'wp_install_plugin':
				return self::install_plugin( $a );
			case 'elementor_list_features':
				return self::elementor_features();
			case 'elementor_set_feature':
				return self::elementor_set_feature( $a );
			case 'elementor_get_kit':
				return self::elementor_kit();
			case 'elementor_update_kit':
				return self::elementor_update_kit( $a );
			case 'wp_get_custom_css':
				return array( 'css' => wp_get_custom_css() );
			case 'wp_update_custom_css':
				$css = (string) $a['css'];
				if ( preg_match( '#</?\s*(script|style)#i', $css ) ) {
					return new WP_Error( 'blocked', 'CSS cannot contain script or style tags.' );
				}
				if ( 'append' === self::arg( $a, 'mode', 'replace' ) ) {
					$css = rtrim( wp_get_custom_css() ) . "\n\n" . $css;
				}
				$res = wp_update_custom_css_post( $css );
				return is_wp_error( $res ) ? $res : array(
					'updated' => true,
					'length'  => strlen( $css ),
				);
			case 'wp_flush_cache':
				return self::flush_cache();
			case 'wc_list_orders':
				return self::orders( $a );
			case 'wc_get_order':
				return self::order( (int) $a['id'] );
			case 'wc_update_order':
				return self::update_order( $a );
			case 'wc_create_refund':
				return self::refund( $a );
			case 'wc_list_products':
				return self::products( $a );
			case 'wc_get_product':
				return self::product( (int) $a['id'], true );
			case 'wc_update_product':
				return self::update_product( $a );
			case 'wc_update_stock':
				return self::stock( $a );
			case 'wc_create_product':
				return self::create_product( $a );
			case 'wc_list_customers':
				return self::customers( $a );
			case 'wc_sales_report':
				return self::sales( $a );
			case 'wc_list_coupons':
				return self::coupons( $a );
			case 'wc_create_coupon':
				return self::create_coupon( $a );
		}
		return null;
	}

	/* ---------- General WordPress ---------- */

	private static function rest( $a ) {
		$method = strtoupper( (string) self::arg( $a, 'method', 'GET' ) );
		if ( ! in_array( $method, array( 'GET', 'POST', 'PUT', 'PATCH', 'DELETE' ), true ) ) {
			return new WP_Error( 'bad_method', 'Unsupported method.' );
		}
		$path = '/' . ltrim( (string) self::arg( $a, 'path', '' ), '/' );
		$path = preg_replace( '#^/wp-json#', '', $path );
		if ( 0 === strpos( $path, '/ai-mcp/' ) ) {
			return new WP_Error( 'blocked', 'This plugin\'s own routes are not available through the bridge.' );
		}
		$query = array();
		if ( false !== strpos( $path, '?' ) ) {
			list( $path, $qs ) = explode( '?', $path, 2 );
			parse_str( $qs, $query );
		}
		$req = new WP_REST_Request( $method, $path );
		$params = isset( $a['params'] ) ? $a['params'] : array();
		if ( is_string( $params ) ) {
			$params = json_decode( $params, true );
		}
		$params = is_array( $params ) ? $params : array();
		$guard  = self::rest_guard( $method, $path, array_merge( $query, $params ) );
		if ( is_wp_error( $guard ) ) {
			return $guard;
		}
		if ( 'GET' === $method || 'DELETE' === $method ) {
			$req->set_query_params( array_merge( $query, $params ) );
		} else {
			$req->set_query_params( $query );
			$req->set_body_params( $params );
			$req->set_header( 'content-type', 'application/json' );
			$req->set_body( wp_json_encode( $params ) );
		}
		$res    = rest_do_request( $req );
		$server = rest_get_server();
		$data   = $server->response_to_data( $res, false );
		$out    = array(
			'status' => $res->get_status(),
			'data'   => $data,
		);
		$headers = $res->get_headers();
		if ( isset( $headers['X-WP-Total'] ) ) {
			$out['total'] = (int) $headers['X-WP-Total'];
		}
		return $out;
	}

	private static function comments( $a ) {
		$args = array(
			'number' => self::per_page( $a ),
			'status' => in_array( self::arg( $a, 'status', 'all' ), array( 'all', 'hold', 'approve', 'spam', 'trash' ), true ) ? self::arg( $a, 'status', 'all' ) : 'all',
			'search' => sanitize_text_field( (string) self::arg( $a, 'search', '' ) ),
			'type'   => '',
		);
		if ( self::arg( $a, 'post_id' ) ) {
			$args['post_id'] = (int) $a['post_id'];
		}
		$out = array();
		foreach ( get_comments( $args ) as $c ) {
			$out[] = array(
				'id'      => (int) $c->comment_ID,
				'post_id' => (int) $c->comment_post_ID,
				'post'    => get_the_title( $c->comment_post_ID ),
				'author'  => $c->comment_author,
				'email'   => $c->comment_author_email,
				'date'    => $c->comment_date,
				'status'  => wp_get_comment_status( $c ),
				'type'    => $c->comment_type,
				'content' => wp_trim_words( $c->comment_content, 60 ),
			);
		}
		return $out;
	}

	private static function moderate( $a ) {
		$c = get_comment( (int) $a['id'] );
		if ( ! $c ) {
			return new WP_Error( 'not_found', 'Comment not found.' );
		}
		$out = array( 'id' => (int) $c->comment_ID );
		if ( self::arg( $a, 'status' ) ) {
			$st  = (string) $a['status'];
			$map = array(
				'approve' => '1',
				'hold'    => '0',
				'spam'    => 'spam',
				'trash'   => 'trash',
			);
			if ( ! isset( $map[ $st ] ) ) {
				return new WP_Error( 'bad_status', 'Status must be approve, hold, spam or trash.' );
			}
			$out['status'] = wp_set_comment_status( $c, $map[ $st ] ) ? $st : 'unchanged';
		}
		if ( self::arg( $a, 'reply' ) ) {
			$user          = wp_get_current_user();
			$out['reply_id'] = wp_insert_comment(
				array(
					'comment_post_ID'      => (int) $c->comment_post_ID,
					'comment_parent'       => (int) $c->comment_ID,
					'comment_content'      => wp_kses_post( (string) $a['reply'] ),
					'user_id'              => $user->ID,
					'comment_author'       => $user->display_name,
					'comment_author_email' => $user->user_email,
					'comment_approved'     => 1,
				)
			);
		}
		return $out;
	}

	/**
	 * The REST bridge gets the same hard limits as the dedicated tools: no admin creation or
	 * promotion, no editing admin accounts, no switching off or deleting AI MCP, no admin email change.
	 */
	private static function rest_guard( $method, $path, $params ) {
		if ( 'GET' === $method ) {
			return true;
		}
		$p = strtolower( untrailingslashit( $path ) );
		if ( preg_match( '#^/wp/v2/users(/(\d+|me))?$#', $p, $m ) ) {
			$roles = array();
			foreach ( array( 'roles', 'role' ) as $k ) {
				if ( isset( $params[ $k ] ) ) {
					$roles = array_merge( $roles, (array) $params[ $k ] );
				}
			}
			foreach ( $roles as $role ) {
				if ( self::powerful_role( sanitize_key( (string) $role ) ) ) {
					return new WP_Error( 'blocked', 'For security, AI cannot give anyone a role that can manage the site.' );
				}
			}
			$uid = isset( $m[2] ) ? ( 'me' === $m[2] ? get_current_user_id() : (int) $m[2] ) : 0;
			if ( $uid && ( user_can( $uid, 'manage_options' ) || user_can( $uid, 'edit_users' ) ) ) {
				return new WP_Error( 'blocked', 'For security, AI cannot edit or delete administrator accounts.' );
			}
		}
		if ( 0 === strpos( $p, '/wp/v2/plugins/' ) && false !== strpos( $p, 'ai-mcp' ) ) {
			return new WP_Error( 'blocked', 'AI MCP cannot switch itself off or delete itself.' );
		}
		if ( 0 === strpos( $p, '/wp/v2/settings' ) && ( isset( $params['email'] ) || isset( $params['url'] ) ) ) {
			return new WP_Error( 'blocked', 'The admin email and site URL are protected.' );
		}
		if ( 0 === strpos( $p, '/wp/v2/application-passwords' ) || preg_match( '#^/wp/v2/users/[^/]+/application-passwords#', $p ) ) {
			return new WP_Error( 'blocked', 'AI cannot create login credentials.' );
		}
		return true;
	}

	/** Roles that can manage the site or other users. AI can never hand these out. */
	public static function powerful_role( $role ) {
		$r = get_role( $role );
		if ( ! $r ) {
			return false;
		}
		foreach ( array( 'manage_options', 'promote_users', 'edit_users', 'create_users', 'delete_users', 'activate_plugins', 'install_plugins', 'unfiltered_html' ) as $cap ) {
			if ( $r->has_cap( $cap ) ) {
				return true;
			}
		}
		return false;
	}

	private static function create_user( $a ) {
		$role = sanitize_key( (string) self::arg( $a, 'role', get_option( 'default_role', 'subscriber' ) ) );
		if ( ! get_role( $role ) ) {
			return new WP_Error( 'bad_role', 'Unknown role: ' . $role );
		}
		if ( self::powerful_role( $role ) ) {
			return new WP_Error( 'blocked', 'For security, AI cannot create administrators or users who can manage the site. Do it yourself in Users.' );
		}
		$id = wp_insert_user(
			array(
				'user_login'   => sanitize_user( (string) $a['username'], true ),
				'user_email'   => sanitize_email( (string) $a['email'] ),
				'user_pass'    => wp_generate_password( 20 ),
				'display_name' => sanitize_text_field( (string) self::arg( $a, 'display_name', $a['username'] ) ),
				'role'         => $role,
			)
		);
		if ( is_wp_error( $id ) ) {
			return $id;
		}
		if ( ! empty( $a['notify'] ) ) {
			wp_new_user_notification( $id, null, 'user' );
		}
		return array(
			'id'   => $id,
			'role' => $role,
		);
	}

	private static function update_user( $a ) {
		$id = (int) $a['id'];
		if ( ! get_userdata( $id ) ) {
			return new WP_Error( 'not_found', 'User not found.' );
		}
		if ( user_can( $id, 'manage_options' ) || user_can( $id, 'edit_users' ) ) {
			return new WP_Error( 'blocked', 'For security, AI cannot edit administrator accounts.' );
		}
		$data = array( 'ID' => $id );
		foreach ( array( 'display_name', 'first_name', 'last_name' ) as $k ) {
			if ( isset( $a[ $k ] ) ) {
				$data[ $k ] = sanitize_text_field( (string) $a[ $k ] );
			}
		}
		if ( isset( $a['email'] ) ) {
			$data['user_email'] = sanitize_email( (string) $a['email'] );
		}
		if ( self::arg( $a, 'role' ) ) {
			$role = sanitize_key( (string) $a['role'] );
			if ( ! get_role( $role ) ) {
				return new WP_Error( 'bad_role', 'Unknown role: ' . $role );
			}
			if ( get_current_user_id() === $id ) {
				return new WP_Error( 'blocked', 'You cannot change your own role from here.' );
			}
			if ( self::powerful_role( $role ) ) {
				return new WP_Error( 'blocked', 'For security, AI cannot promote anyone to a role that can manage the site.' );
			}
			$data['role'] = $role;
		}
		$res = wp_update_user( $data );
		return is_wp_error( $res ) ? $res : array( 'updated' => true );
	}

	private static function toggle_plugin( $a ) {
		require_once ABSPATH . 'wp-admin/includes/plugin.php';
		$plugin = (string) $a['plugin'];
		if ( plugin_basename( MOSHAI_FILE ) === $plugin ) {
			return new WP_Error( 'blocked', 'AI MCP cannot switch itself off.' );
		}
		if ( ! array_key_exists( $plugin, get_plugins() ) ) {
			return new WP_Error( 'not_found', 'Plugin not found. Use wp_list_plugins for exact file names.' );
		}
		if ( ! empty( $a['active'] ) ) {
			$res = activate_plugin( $plugin );
			return is_wp_error( $res ) ? $res : array( 'active' => true );
		}
		deactivate_plugins( $plugin );
		return array( 'active' => false );
	}

	/** Options AI may never touch: site URLs, roles, admin identity, plugin list, this plugin's own data, secrets. */
	private static function option_blocked( $key ) {
		$blocked = array( 'siteurl', 'home', 'active_plugins', 'active_sitewide_plugins', 'template', 'stylesheet', 'users_can_register', 'default_role', 'admin_email', 'new_admin_email', 'cron', 'rewrite_rules', 'db_version', 'initial_db_version', 'recently_activated', 'uninstall_plugins', 'auto_updater.lock', 'auth_key', 'secure_auth_key', 'logged_in_key', 'nonce_key' );
		return in_array( $key, $blocked, true )
			|| 0 === strpos( $key, 'moshai_' )
			|| (bool) preg_match( '/(user_roles|secret|password|passwd|token|api_?key|license|salt|auth_key|nonce|private_key|webhook)/i', $key );
	}

	private static function update_option( $a ) {
		$key = trim( (string) $a['key'] );
		if ( '' === $key || self::option_blocked( $key ) ) {
			return new WP_Error( 'blocked', 'This setting is protected and cannot be changed by AI.' );
		}
		$value   = isset( $a['value'] ) ? $a['value'] : '';
		$decoded = is_string( $value ) ? json_decode( $value, true ) : null;
		if ( is_array( $decoded ) ) {
			$value = $decoded;
		}
		$old  = get_option( $key, null );
		$path = trim( (string) self::arg( $a, 'path', '' ) );
		if ( '' !== $path ) {
			$data = is_array( $old ) ? $old : array();
			$ref  = &$data;
			foreach ( explode( '.', $path ) as $part ) {
				if ( ! isset( $ref[ $part ] ) || ! is_array( $ref[ $part ] ) ) {
					$ref[ $part ] = array();
				}
				$ref = &$ref[ $part ];
			}
			$ref = $value;
			unset( $ref );
			$value = $data;
		}
		update_option( $key, $value );
		return array(
			'key'      => $key,
			'path'     => $path,
			'previous' => '' !== $path ? self::dig( $old, $path ) : $old,
			'value'    => '' !== $path ? self::dig( get_option( $key ), $path ) : get_option( $key ),
		);
	}

	private static function dig( $data, $path ) {
		foreach ( explode( '.', $path ) as $part ) {
			if ( ! is_array( $data ) || ! array_key_exists( $part, $data ) ) {
				return null;
			}
			$data = $data[ $part ];
		}
		return $data;
	}

	private static function list_options( $a ) {
		global $wpdb;
		$search = trim( (string) self::arg( $a, 'search', '' ) );
		if ( strlen( $search ) < 2 ) {
			return new WP_Error( 'short', 'Search with at least 2 characters.' );
		}
		$limit = min( 100, max( 1, (int) self::arg( $a, 'limit', 40 ) ) );
		$rows  = $wpdb->get_results( // phpcs:ignore
			$wpdb->prepare(
				"SELECT option_name, option_value FROM {$wpdb->options} WHERE option_name LIKE %s AND option_name NOT LIKE %s AND option_name NOT LIKE %s ORDER BY option_name LIMIT %d",
				'%' . $wpdb->esc_like( $search ) . '%',
				$wpdb->esc_like( '_transient' ) . '%',
				$wpdb->esc_like( '_site_transient' ) . '%',
				$limit
			),
			ARRAY_A
		);
		$out = array();
		foreach ( (array) $rows as $row ) {
			$name = $row['option_name'];
			if ( self::option_blocked( $name ) ) {
				$out[] = array(
					'name'      => $name,
					'protected' => true,
				);
				continue;
			}
			$val     = maybe_unserialize( $row['option_value'] );
			$preview = is_scalar( $val ) ? (string) $val : wp_json_encode( $val );
			$out[]   = array(
				'name'  => $name,
				'type'  => is_array( $val ) ? 'array' : gettype( $val ),
				'value' => mb_strlen( $preview ) > 300 ? mb_substr( $preview, 0, 300 ) . '…' : $preview,
			);
		}
		return $out;
	}

	private static function install_plugin( $a ) {
		$slug = sanitize_key( (string) $a['slug'] );
		if ( '' === $slug ) {
			return new WP_Error( 'bad_slug', 'Give the wordpress.org plugin slug.' );
		}
		require_once ABSPATH . 'wp-admin/includes/plugin.php';
		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/misc.php';
		require_once ABSPATH . 'wp-admin/includes/plugin-install.php';
		require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';
		if ( ! current_user_can( 'install_plugins' ) ) {
			return new WP_Error( 'cap', 'Plugin installs are disabled on this site (DISALLOW_FILE_MODS or missing capability).' );
		}
		// Only wordpress.org packages: no arbitrary download URLs.
		$api = plugins_api(
			'plugin_information',
			array(
				'slug'   => $slug,
				'fields' => array( 'sections' => false ),
			)
		);
		if ( is_wp_error( $api ) ) {
			return $api;
		}
		$file = '';
		foreach ( array_keys( get_plugins() ) as $f ) {
			if ( 0 === strpos( $f, $slug . '/' ) ) {
				$file = $f;
			}
		}
		if ( '' === $file ) {
			$upgrader = new Plugin_Upgrader( new WP_Ajax_Upgrader_Skin() );
			$res      = $upgrader->install( $api->download_link );
			if ( is_wp_error( $res ) || ! $res ) {
				return is_wp_error( $res ) ? $res : new WP_Error( 'install', 'Install failed. The host may block file changes.' );
			}
			$file = (string) $upgrader->plugin_info();
		}
		$active = false;
		if ( ! empty( $a['activate'] ) && $file ) {
			$act = activate_plugin( $file );
			if ( is_wp_error( $act ) ) {
				return $act;
			}
			$active = true;
		}
		return array(
			'plugin'  => $file,
			'name'    => $api->name,
			'version' => $api->version,
			'active'  => $active || is_plugin_active( $file ),
		);
	}

	private static function elementor() {
		return class_exists( '\Elementor\Plugin' ) && isset( \Elementor\Plugin::$instance );
	}

	private static function elementor_features() {
		if ( ! self::elementor() || ! isset( \Elementor\Plugin::$instance->experiments ) ) {
			return new WP_Error( 'no_elementor', 'Elementor is not active.' );
		}
		$exp = \Elementor\Plugin::$instance->experiments;
		$out = array();
		foreach ( (array) $exp->get_features() as $name => $f ) {
			$out[] = array(
				'name'        => $name,
				'title'       => isset( $f['title'] ) ? wp_strip_all_tags( (string) $f['title'] ) : $name,
				'state'       => $exp->is_feature_active( $name ) ? 'active' : 'inactive',
				'setting'     => get_option( 'elementor_experiment-' . $name, 'default' ),
				'default'     => isset( $f['default'] ) ? $f['default'] : '',
				'release'     => isset( $f['release_status'] ) ? $f['release_status'] : '',
				'description' => isset( $f['description'] ) ? mb_substr( wp_strip_all_tags( (string) $f['description'] ), 0, 160 ) : '',
				'requires'    => isset( $f['dependencies'] ) ? array_map( 'strval', array_keys( (array) $f['dependencies'] ) ) : array(),
			);
		}
		return $out;
	}

	private static function elementor_set_feature( $a ) {
		if ( ! self::elementor() || ! isset( \Elementor\Plugin::$instance->experiments ) ) {
			return new WP_Error( 'no_elementor', 'Elementor is not active.' );
		}
		$name  = sanitize_key( (string) $a['name'] );
		$state = in_array( $a['state'], array( 'active', 'inactive', 'default' ), true ) ? $a['state'] : 'default';
		$exp   = \Elementor\Plugin::$instance->experiments;
		if ( ! $exp->get_features( $name ) ) {
			return new WP_Error( 'not_found', 'Unknown Elementor feature. Use elementor_list_features for exact names.' );
		}
		$before = $exp->is_feature_active( $name ) ? 'active' : 'inactive';
		update_option( 'elementor_experiment-' . $name, $state );
		MoshAI_MCP::clear_elementor();
		return array(
			'name'   => $name,
			'before' => $before,
			'set_to' => $state,
			'note'   => 'Takes effect on the next page load. Some features need others turned on first (see requires).',
		);
	}

	private static function kit_id() {
		return (int) get_option( 'elementor_active_kit' );
	}

	private static function elementor_kit() {
		$id = self::kit_id();
		if ( ! $id ) {
			return new WP_Error( 'no_kit', 'No active Elementor kit.' );
		}
		return array(
			'kit_id'   => $id,
			'settings' => (array) get_post_meta( $id, '_elementor_page_settings', true ),
		);
	}

	/** Deep merge for associative arrays; lists are replaced whole. */
	private static function merge( $base, $new ) {
		if ( ! is_array( $base ) || ! is_array( $new ) || array_values( $new ) === $new ) {
			return $new;
		}
		foreach ( $new as $k => $v ) {
			$base[ $k ] = isset( $base[ $k ] ) ? self::merge( $base[ $k ], $v ) : $v;
		}
		return $base;
	}

	private static function elementor_update_kit( $a ) {
		$id = self::kit_id();
		if ( ! $id ) {
			return new WP_Error( 'no_kit', 'No active Elementor kit.' );
		}
		$new = $a['settings'];
		if ( is_string( $new ) ) {
			$new = json_decode( $new, true );
		}
		if ( ! is_array( $new ) || ! $new ) {
			return new WP_Error( 'bad_settings', 'settings must be a JSON object.' );
		}
		$old  = (array) get_post_meta( $id, '_elementor_page_settings', true );
		$data = ( isset( $a['merge'] ) && false === $a['merge'] ) ? $new : self::merge( $old, $new );
		update_post_meta( $id, '_elementor_page_settings', wp_slash( $data ) );
		MoshAI_MCP::clear_elementor( $id );
		return array(
			'kit_id'  => $id,
			'changed' => array_keys( $new ),
			'merged'  => ! ( isset( $a['merge'] ) && false === $a['merge'] ),
		);
	}

	private static function flush_cache() {
		$done = array();
		wp_cache_flush();
		$done[] = 'object cache';
		flush_rewrite_rules( false );
		$done[] = 'rewrite rules';
		if ( self::elementor() ) {
			MoshAI_MCP::clear_elementor();
			$done[] = 'Elementor';
		}
		$hooks = array(
			'litespeed_purge_all' => 'LiteSpeed',
			'w3tc_flush_all'      => 'W3 Total Cache',
			'breeze_clear_all_cache' => 'Breeze',
		);
		foreach ( $hooks as $hook => $label ) {
			if ( has_action( $hook ) ) {
				do_action( $hook );
				$done[] = $label;
			}
		}
		if ( function_exists( 'rocket_clean_domain' ) ) {
			rocket_clean_domain();
			$done[] = 'WP Rocket';
		}
		if ( function_exists( 'wp_cache_clear_cache' ) ) {
			wp_cache_clear_cache();
			$done[] = 'WP Super Cache';
		}
		if ( function_exists( 'sg_cachepress_purge_cache' ) ) {
			sg_cachepress_purge_cache();
			$done[] = 'SG Optimizer';
		}
		return array(
			'flushed' => $done,
			'note'    => 'A CDN (e.g. Cloudflare) in front of the site still needs its own purge.',
		);
	}

	/* ---------- WooCommerce: orders ---------- */

	private static function order_summary( $o ) {
		return array(
			'id'       => $o->get_id(),
			'number'   => $o->get_order_number(),
			'status'   => $o->get_status(),
			'date'     => $o->get_date_created() ? $o->get_date_created()->date( 'Y-m-d H:i' ) : '',
			'total'    => $o->get_total(),
			'currency' => $o->get_currency(),
			'customer' => trim( $o->get_billing_first_name() . ' ' . $o->get_billing_last_name() ),
			'email'    => $o->get_billing_email(),
			'items'    => $o->get_item_count(),
			'edit'     => $o->get_edit_order_url(),
		);
	}

	private static function orders( $a ) {
		$args = array(
			'limit'    => self::per_page( $a ),
			'paged'    => max( 1, (int) self::arg( $a, 'page', 1 ) ),
			'orderby'  => 'date',
			'order'    => 'DESC',
			'type'     => 'shop_order',
			'paginate' => true,
		);
		$status = sanitize_key( (string) self::arg( $a, 'status', 'any' ) );
		if ( 'any' !== $status ) {
			$args['status'] = array( 'wc-' . preg_replace( '/^wc-/', '', $status ) );
		}
		if ( self::arg( $a, 'search' ) ) {
			$s = sanitize_text_field( (string) $a['search'] );
			if ( ctype_digit( $s ) && wc_get_order( (int) $s ) ) {
				return array(
					'total'  => 1,
					'orders' => array( self::order_summary( wc_get_order( (int) $s ) ) ),
				);
			}
			if ( is_email( $s ) ) {
				$args['billing_email'] = $s;
			} else {
				$args['s'] = $s;
			}
		}
		if ( self::arg( $a, 'after' ) ) {
			$args['date_created'] = '>=' . sanitize_text_field( (string) $a['after'] );
		}
		$res = wc_get_orders( $args );
		$out = array();
		foreach ( $res->orders as $o ) {
			$out[] = self::order_summary( $o );
		}
		return array(
			'total'  => (int) $res->total,
			'orders' => $out,
		);
	}

	private static function order( $id ) {
		$o = wc_get_order( $id );
		if ( ! $o || 'shop_order_refund' === $o->get_type() ) {
			return new WP_Error( 'not_found', 'Order not found.' );
		}
		$items = array();
		foreach ( $o->get_items() as $item ) {
			$items[] = array(
				'product_id'   => $item->get_product_id(),
				'variation_id' => $item->get_variation_id(),
				'name'         => $item->get_name(),
				'qty'          => $item->get_quantity(),
				'total'        => $item->get_total(),
			);
		}
		$notes = array();
		foreach ( wc_get_order_notes(
			array(
				'order_id' => $id,
				'limit'    => 10,
			)
		) as $n ) {
			$notes[] = array(
				'date'     => $n->date_created ? $n->date_created->date( 'Y-m-d H:i' ) : '',
				'note'     => wp_strip_all_tags( $n->content ),
				'customer' => (bool) $n->customer_note,
			);
		}
		return array_merge(
			self::order_summary( $o ),
			array(
				'payment_method' => $o->get_payment_method_title(),
				'subtotal'       => $o->get_subtotal(),
				'discount'       => $o->get_discount_total(),
				'shipping_total' => $o->get_shipping_total(),
				'tax'            => $o->get_total_tax(),
				'refunded'       => $o->get_total_refunded(),
				'customer_note'  => $o->get_customer_note(),
				'billing'        => $o->get_address( 'billing' ),
				'shipping'       => $o->get_address( 'shipping' ),
				'line_items'     => $items,
				'notes'          => $notes,
			)
		);
	}

	private static function update_order( $a ) {
		$o = wc_get_order( (int) $a['id'] );
		if ( ! $o ) {
			return new WP_Error( 'not_found', 'Order not found.' );
		}
		$before = $o->get_status();
		if ( self::arg( $a, 'status' ) ) {
			$status = preg_replace( '/^wc-/', '', sanitize_key( (string) $a['status'] ) );
			if ( ! array_key_exists( 'wc-' . $status, wc_get_order_statuses() ) ) {
				return new WP_Error( 'bad_status', 'Unknown order status: ' . $status );
			}
			$o->update_status( $status, __( 'Changed by the AI MCP agent.', 'ai-mcp' ), true );
		}
		if ( self::arg( $a, 'note' ) ) {
			$o->add_order_note( sanitize_textarea_field( (string) $a['note'] ), ! empty( $a['customer_note'] ) ? 1 : 0, true );
		}
		return array(
			'id'     => $o->get_id(),
			'before' => $before,
			'status' => $o->get_status(),
		);
	}

	private static function refund( $a ) {
		$o = wc_get_order( (int) $a['id'] );
		if ( ! $o ) {
			return new WP_Error( 'not_found', 'Order not found.' );
		}
		$amount = round( (float) $a['amount'], wc_get_price_decimals() );
		$max    = (float) $o->get_total() - (float) $o->get_total_refunded();
		if ( $amount <= 0 || $amount > $max + 0.001 ) {
			return new WP_Error( 'bad_amount', sprintf( 'Amount must be between 0 and %s.', wc_format_decimal( $max ) ) );
		}
		$refund = wc_create_refund(
			array(
				'order_id'       => $o->get_id(),
				'amount'         => $amount,
				'reason'         => sanitize_text_field( (string) self::arg( $a, 'reason', '' ) ),
				'refund_payment' => ! empty( $a['refund_payment'] ),
				'restock_items'  => ! empty( $a['restock'] ),
			)
		);
		if ( is_wp_error( $refund ) ) {
			return $refund;
		}
		return array(
			'refund_id' => $refund->get_id(),
			'amount'    => $amount,
			'order'     => $o->get_id(),
		);
	}

	/* ---------- WooCommerce: products ---------- */

	private static function product( $id, $full = false ) {
		$p = is_object( $id ) ? $id : wc_get_product( $id );
		if ( ! $p ) {
			return new WP_Error( 'not_found', 'Product not found.' );
		}
		$out = array(
			'id'             => $p->get_id(),
			'name'           => $p->get_name(),
			'type'           => $p->get_type(),
			'status'         => $p->get_status(),
			'sku'            => $p->get_sku(),
			'price'          => $p->get_price(),
			'regular_price'  => $p->get_regular_price(),
			'sale_price'     => $p->get_sale_price(),
			'manage_stock'   => $p->get_manage_stock(),
			'stock_quantity' => $p->get_stock_quantity(),
			'stock_status'   => $p->get_stock_status(),
			'link'           => get_permalink( $p->get_id() ),
		);
		if ( $full ) {
			$out['description']       = $p->get_description();
			$out['short_description'] = $p->get_short_description();
			$out['categories']        = wp_get_post_terms( $p->get_id(), 'product_cat', array( 'fields' => 'names' ) );
			$out['category_ids']      = $p->get_category_ids();
			$out['image_id']          = $p->get_image_id();
			$out['featured']          = $p->get_featured();
			$out['virtual']           = $p->get_virtual();
			$out['downloadable']      = $p->get_downloadable();
			$out['total_sales']       = $p->get_total_sales();
			if ( $p->is_type( 'variable' ) ) {
				$out['variations'] = array();
				foreach ( $p->get_children() as $vid ) {
					$v = wc_get_product( $vid );
					if ( $v ) {
						$out['variations'][] = array(
							'id'             => $vid,
							'attributes'     => $v->get_attributes(),
							'price'          => $v->get_price(),
							'stock_quantity' => $v->get_stock_quantity(),
							'stock_status'   => $v->get_stock_status(),
						);
					}
				}
			}
		}
		return $out;
	}

	private static function products( $a ) {
		$args = array(
			'limit'    => self::per_page( $a ),
			'page'     => max( 1, (int) self::arg( $a, 'page', 1 ) ),
			'status'   => sanitize_key( (string) self::arg( $a, 'status', 'any' ) ),
			'orderby'  => 'date',
			'order'    => 'DESC',
			'paginate' => true,
		);
		if ( 'any' === $args['status'] ) {
			$args['status'] = array( 'publish', 'draft', 'pending', 'private' );
		}
		if ( self::arg( $a, 'search' ) ) {
			$args['s'] = sanitize_text_field( (string) $a['search'] );
		}
		if ( self::arg( $a, 'stock_status' ) ) {
			$args['stock_status'] = sanitize_key( (string) $a['stock_status'] );
		}
		if ( self::arg( $a, 'category' ) ) {
			$args['category'] = array( sanitize_title( (string) $a['category'] ) );
		}
		if ( self::arg( $a, 'sku' ) ) {
			$args['sku'] = sanitize_text_field( (string) $a['sku'] );
		}
		$low = self::arg( $a, 'low_stock' );
		if ( null !== $low ) {
			$args['manage_stock'] = true;
			$args['limit']        = 200;
		}
		$res = wc_get_products( $args );
		$out = array();
		foreach ( $res->products as $p ) {
			if ( null !== $low && ( null === $p->get_stock_quantity() || $p->get_stock_quantity() > (int) $low ) ) {
				continue;
			}
			$out[] = self::product( $p );
		}
		return array(
			'total'    => null !== $low ? count( $out ) : (int) $res->total,
			'products' => $out,
		);
	}

	private static function apply_product_fields( $p, $a ) {
		$setters = array(
			'name'              => 'set_name',
			'regular_price'     => 'set_regular_price',
			'sale_price'        => 'set_sale_price',
			'sku'               => 'set_sku',
			'description'       => 'set_description',
			'short_description' => 'set_short_description',
			'stock_status'      => 'set_stock_status',
		);
		foreach ( $setters as $k => $method ) {
			if ( array_key_exists( $k, $a ) && is_callable( array( $p, $method ) ) ) {
				$val = in_array( $k, array( 'description', 'short_description' ), true ) ? wp_kses_post( (string) $a[ $k ] ) : sanitize_text_field( (string) $a[ $k ] );
				$p->$method( $val );
			}
		}
		if ( isset( $a['status'] ) && '' !== $a['status'] && ! $p->is_type( 'variation' ) ) {
			$p->set_status( sanitize_key( (string) $a['status'] ) );
		}
		if ( isset( $a['manage_stock'] ) ) {
			$p->set_manage_stock( (bool) $a['manage_stock'] );
		}
		if ( isset( $a['stock_quantity'] ) && '' !== $a['stock_quantity'] ) {
			$p->set_manage_stock( true );
			$p->set_stock_quantity( (int) $a['stock_quantity'] );
		}
		if ( isset( $a['featured'] ) && ! $p->is_type( 'variation' ) ) {
			$p->set_featured( (bool) $a['featured'] );
		}
		if ( isset( $a['virtual'] ) ) {
			$p->set_virtual( (bool) $a['virtual'] );
		}
		if ( isset( $a['downloadable'] ) ) {
			$p->set_downloadable( (bool) $a['downloadable'] );
		}
		if ( isset( $a['category_ids'] ) && ! $p->is_type( 'variation' ) ) {
			$p->set_category_ids( array_map( 'intval', (array) $a['category_ids'] ) );
		}
	}

	private static function update_product( $a ) {
		$p = wc_get_product( (int) $a['id'] );
		if ( ! $p ) {
			return new WP_Error( 'not_found', 'Product not found.' );
		}
		$before = self::product( $p );
		self::apply_product_fields( $p, $a );
		$p->save();
		return array(
			'before' => $before,
			'after'  => self::product( wc_get_product( $p->get_id() ) ),
		);
	}

	private static function stock( $a ) {
		$p = wc_get_product( (int) $a['id'] );
		if ( ! $p ) {
			return new WP_Error( 'not_found', 'Product not found.' );
		}
		$before = $p->get_stock_quantity();
		if ( ! $p->get_manage_stock() ) {
			$p->set_manage_stock( true );
			$p->save();
		}
		if ( isset( $a['quantity'] ) && '' !== $a['quantity'] ) {
			$new = wc_update_product_stock( $p, (int) $a['quantity'], 'set' );
		} elseif ( isset( $a['adjust'] ) && (int) $a['adjust'] ) {
			$adj = (int) $a['adjust'];
			$new = wc_update_product_stock( $p, abs( $adj ), $adj > 0 ? 'increase' : 'decrease' );
		} else {
			return new WP_Error( 'missing', 'Send quantity (exact) or adjust (+/-).' );
		}
		$p = wc_get_product( $p->get_id() );
		return array(
			'id'           => $p->get_id(),
			'name'         => $p->get_name(),
			'before'       => $before,
			'after'        => $new,
			'stock_status' => $p->get_stock_status(),
		);
	}

	private static function create_product( $a ) {
		$p = new WC_Product_Simple();
		if ( ! isset( $a['status'] ) || '' === $a['status'] ) {
			$a['status'] = 'draft';
		}
		self::apply_product_fields( $p, $a );
		$id = $p->save();
		return $id ? array(
			'id'   => $id,
			'edit' => get_edit_post_link( $id, 'raw' ),
			'link' => get_permalink( $id ),
		) : new WP_Error( 'failed', 'Product could not be created.' );
	}

	/* ---------- WooCommerce: customers, sales, coupons ---------- */

	private static function customers( $a ) {
		$args = array(
			'number'   => self::per_page( $a ),
			'role__in' => array( 'customer', 'subscriber' ),
			'orderby'  => 'registered',
			'order'    => 'DESC',
		);
		if ( self::arg( $a, 'search' ) ) {
			$args['search']         = '*' . sanitize_text_field( (string) $a['search'] ) . '*';
			$args['search_columns'] = array( 'user_login', 'user_email', 'display_name' );
		}
		$out = array();
		foreach ( get_users( $args ) as $u ) {
			$c     = new WC_Customer( $u->ID );
			$out[] = array(
				'id'          => $u->ID,
				'name'        => $u->display_name,
				'email'       => $u->user_email,
				'registered'  => $u->user_registered,
				'orders'      => $c->get_order_count(),
				'total_spent' => $c->get_total_spent(),
				'city'        => $c->get_billing_city(),
				'country'     => $c->get_billing_country(),
			);
		}
		return $out;
	}

	private static function sales( $a ) {
		$days   = min( 365, max( 1, (int) self::arg( $a, 'days', 7 ) ) );
		$since  = gmdate( 'Y-m-d', time() - $days * DAY_IN_SECONDS );
		$orders = wc_get_orders(
			array(
				'limit'        => 1000,
				'type'         => 'shop_order',
				'date_created' => '>=' . $since,
				'status'       => array_keys( wc_get_order_statuses() ),
			)
		);
		$revenue   = 0.0;
		$refunds   = 0.0;
		$by_status = array();
		$products  = array();
		$paid      = 0;
		foreach ( $orders as $o ) {
			$st               = $o->get_status();
			$by_status[ $st ] = isset( $by_status[ $st ] ) ? $by_status[ $st ] + 1 : 1;
			if ( in_array( $st, array( 'processing', 'completed', 'on-hold' ), true ) ) {
				$paid++;
				$revenue += (float) $o->get_total();
				$refunds += (float) $o->get_total_refunded();
				foreach ( $o->get_items() as $item ) {
					$n                = $item->get_name();
					$products[ $n ]   = isset( $products[ $n ] ) ? $products[ $n ] : array( 'qty' => 0, 'revenue' => 0 );
					$products[ $n ]['qty']     += $item->get_quantity();
					$products[ $n ]['revenue'] += (float) $item->get_total();
				}
			}
		}
		uasort(
			$products,
			function ( $x, $y ) {
				return $y['revenue'] <=> $x['revenue'];
			}
		);
		return array(
			'period'        => 'Last ' . $days . ' days (since ' . $since . ')',
			'currency'      => get_woocommerce_currency(),
			'orders_total'  => count( $orders ),
			'paid_orders'   => $paid,
			'gross_revenue' => round( $revenue, 2 ),
			'refunded'      => round( $refunds, 2 ),
			'net_revenue'   => round( $revenue - $refunds, 2 ),
			'average_order' => $paid ? round( $revenue / $paid, 2 ) : 0,
			'by_status'     => $by_status,
			'top_products'  => array_slice( $products, 0, 10, true ),
		);
	}

	private static function coupons( $a ) {
		$out = array();
		foreach ( get_posts(
			array(
				'post_type'   => 'shop_coupon',
				'post_status' => 'any',
				'numberposts' => self::per_page( $a ),
			)
		) as $post ) {
			$c     = new WC_Coupon( $post->ID );
			$out[] = array(
				'id'          => $post->ID,
				'code'        => $c->get_code(),
				'type'        => $c->get_discount_type(),
				'amount'      => $c->get_amount(),
				'expires'     => $c->get_date_expires() ? $c->get_date_expires()->date( 'Y-m-d' ) : null,
				'usage'       => $c->get_usage_count() . ( $c->get_usage_limit() ? ' / ' . $c->get_usage_limit() : '' ),
				'status'      => $post->post_status,
			);
		}
		return $out;
	}

	private static function create_coupon( $a ) {
		$code = wc_format_coupon_code( sanitize_text_field( (string) $a['code'] ) );
		if ( wc_get_coupon_id_by_code( $code ) ) {
			return new WP_Error( 'exists', 'A coupon with this code already exists.' );
		}
		$c = new WC_Coupon();
		$c->set_code( $code );
		$type = (string) self::arg( $a, 'discount_type', 'percent' );
		$c->set_discount_type( in_array( $type, array( 'percent', 'fixed_cart', 'fixed_product' ), true ) ? $type : 'percent' );
		$c->set_amount( (float) $a['amount'] );
		if ( self::arg( $a, 'expires' ) ) {
			$c->set_date_expires( sanitize_text_field( (string) $a['expires'] ) );
		}
		if ( self::arg( $a, 'usage_limit' ) ) {
			$c->set_usage_limit( (int) $a['usage_limit'] );
		}
		$c->set_individual_use( ! empty( $a['individual'] ) );
		$c->set_free_shipping( ! empty( $a['free_shipping'] ) );
		$id = $c->save();
		return array(
			'id'   => $id,
			'code' => $code,
		);
	}
}
