<?php
/**
 * JSON-LD schema graph: Organization or Person, WebSite with site search, WebPage, Article,
 * author, primary image and BreadcrumbList, all linked by @id like Google recommends.
 *
 * @package AI_MCP
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class MoshAI_SEO_Schema {

	public static function graph() {
		$c    = MoshAI_SEO_Head::context();
		if ( '404' === $c['kind'] ) {
			return array();
		}
		$s     = 'MoshAI_SEO_Settings';
		$home  = home_url( '/' );
		$lang  = get_bloginfo( 'language' );
		$url   = $c['canonical'] ? $c['canonical'] : $home;
		$graph = array();

		// Publisher.
		$is_person = 'person' === $s::get( 'knowledge.type' );
		$pub_id    = $is_person ? $home . '#/schema/person/site' : $home . '#organization';
		$profiles  = array();
		foreach ( array( 'facebook', 'instagram', 'linkedin', 'youtube', 'pinterest', 'tiktok' ) as $k ) {
			if ( $s::get( 'social.' . $k ) ) {
				$profiles[] = $s::get( 'social.' . $k );
			}
		}
		$tw = ltrim( (string) $s::get( 'social.twitter_site' ), '@' );
		if ( '' !== $tw ) {
			$profiles[] = 'https://x.com/' . $tw;
		}
		foreach ( preg_split( '/\r\n|\n/', (string) $s::get( 'social.profiles_other' ) ) as $line ) {
			$line = esc_url_raw( trim( $line ) );
			if ( $line ) {
				$profiles[] = $line;
			}
		}
		if ( $is_person ) {
			$node = array(
				'@type' => array( 'Person', 'Organization' ),
				'@id'   => $pub_id,
				'name'  => $s::get( 'knowledge.person_name' ) ? $s::get( 'knowledge.person_name' ) : get_bloginfo( 'name' ),
			);
			$img  = $s::get( 'knowledge.person_image' );
		} else {
			$node = array(
				'@type' => 'Organization',
				'@id'   => $pub_id,
				'name'  => $s::get( 'knowledge.name' ) ? $s::get( 'knowledge.name' ) : get_bloginfo( 'name' ),
				'url'   => $home,
			);
			if ( $s::get( 'knowledge.alt_name' ) ) {
				$node['alternateName'] = $s::get( 'knowledge.alt_name' );
			}
			$img = $s::get( 'knowledge.logo' );
			if ( ! $img && get_theme_mod( 'custom_logo' ) ) {
				$img = wp_get_attachment_image_url( (int) get_theme_mod( 'custom_logo' ), 'full' );
			}
		}
		if ( $img ) {
			$node['logo']  = array(
				'@type'      => 'ImageObject',
				'@id'        => $home . '#/schema/logo/image/',
				'url'        => $img,
				'contentUrl' => $img,
				'caption'    => $node['name'],
				'inLanguage' => $lang,
			);
			$node['image'] = array( '@id' => $home . '#/schema/logo/image/' );
		}
		if ( $profiles ) {
			$node['sameAs'] = array_values( array_unique( $profiles ) );
		}
		$graph[] = $node;

		$graph[] = array(
			'@type'           => 'WebSite',
			'@id'             => $home . '#website',
			'url'             => $home,
			'name'            => get_bloginfo( 'name' ),
			'description'     => get_bloginfo( 'description' ),
			'publisher'       => array( '@id' => $pub_id ),
			'potentialAction' => array(
				array(
					'@type'       => 'SearchAction',
					'target'      => array(
						'@type'       => 'EntryPoint',
						'urlTemplate' => $home . '?s={search_term_string}',
					),
					'query-input' => array(
						'@type'          => 'PropertyValueSpecification',
						'valueRequired'  => true,
						'valueName'      => 'search_term_string',
					),
				),
			),
			'inLanguage'      => $lang,
		);

		$post  = $c['post'];
		$type  = 'WebPage';
		if ( $post ) {
			$own  = MoshAI_SEO_Meta::get( $post->ID, 'schema_page' );
			$type = $own ? $own : MoshAI_SEO_Meta::type_setting( $post->post_type, 'schema_page' );
		} elseif ( in_array( $c['kind'], array( 'term', 'archive', 'author', 'date' ), true ) || ( 'home' === $c['kind'] && is_home() ) ) {
			$type = 'CollectionPage';
		} elseif ( 'search' === $c['kind'] ) {
			$type = 'SearchResultsPage';
		}
		$page = array(
			'@type'      => $type,
			'@id'        => $url,
			'url'        => $url,
			'name'       => $c['title'],
			'isPartOf'   => array( '@id' => $home . '#website' ),
			'inLanguage' => $lang,
		);
		if ( '' !== $c['desc'] ) {
			$page['description'] = $c['desc'];
		}
		if ( 'home' === $c['kind'] ) {
			$page['about'] = array( '@id' => $pub_id );
		}
		if ( $post ) {
			$page['datePublished'] = get_post_time( 'c', true, $post );
			$page['dateModified']  = get_post_modified_time( 'c', true, $post );
		}
		if ( $c['image'] ) {
			$graph[]                    = array(
				'@type'      => 'ImageObject',
				'@id'        => $url . '#primaryimage',
				'url'        => $c['image'],
				'contentUrl' => $c['image'],
				'inLanguage' => $lang,
			);
			$page['primaryImageOfPage'] = array( '@id' => $url . '#primaryimage' );
			$page['image']              = array( '@id' => $url . '#primaryimage' );
		}
		$trail = MoshAI_SEO_Breadcrumbs::trail();
		if ( count( $trail ) > 1 ) {
			$items = array();
			foreach ( $trail as $i => $crumb ) {
				$item = array(
					'@type'    => 'ListItem',
					'position' => $i + 1,
					'name'     => $crumb['name'],
				);
				if ( $i < count( $trail ) - 1 && $crumb['url'] ) {
					$item['item'] = $crumb['url'];
				}
				$items[] = $item;
			}
			$graph[]            = array(
				'@type'           => 'BreadcrumbList',
				'@id'             => $url . '#breadcrumb',
				'itemListElement' => $items,
			);
			$page['breadcrumb'] = array( '@id' => $url . '#breadcrumb' );
		}
		$graph[] = $page;

		// Article.
		if ( $post && 'home' !== $c['kind'] ) {
			$own     = MoshAI_SEO_Meta::get( $post->ID, 'schema_article' );
			$article = $own ? $own : MoshAI_SEO_Meta::type_setting( $post->post_type, 'schema_article' );
			if ( $article && 'None' !== $article ) {
				$author_id = $home . '#/schema/person/' . md5( (string) $post->post_author );
				$words     = count( MoshAI_SEO_Analyzer::words( MoshAI_SEO_Analyzer::plain_text( MoshAI_SEO_Analyzer::content( $post ) ) ) );
				$node      = array(
					'@type'            => $article,
					'@id'              => $url . '#article',
					'isPartOf'         => array( '@id' => $url ),
					'author'           => array(
						'name' => get_the_author_meta( 'display_name', $post->post_author ),
						'@id'  => $author_id,
					),
					'headline'         => mb_substr( get_the_title( $post ), 0, 110 ),
					'datePublished'    => get_post_time( 'c', true, $post ),
					'dateModified'     => get_post_modified_time( 'c', true, $post ),
					'mainEntityOfPage' => array( '@id' => $url ),
					'wordCount'        => $words,
					'publisher'        => array( '@id' => $pub_id ),
					'inLanguage'       => $lang,
				);
				if ( $c['image'] ) {
					$node['image']        = array( '@id' => $url . '#primaryimage' );
					$node['thumbnailUrl'] = $c['image'];
				}
				$cats = 'post' === $post->post_type ? wp_get_post_categories( $post->ID, array( 'fields' => 'names' ) ) : array();
				if ( $cats ) {
					$node['articleSection'] = $cats;
				}
				$tags = 'post' === $post->post_type ? wp_get_post_tags( $post->ID, array( 'fields' => 'names' ) ) : array();
				if ( $tags ) {
					$node['keywords'] = $tags;
				}
				if ( comments_open( $post ) || get_comments_number( $post ) ) {
					$node['commentCount'] = (int) get_comments_number( $post );
				}
				$graph[] = $node;
				$graph[] = array(
					'@type' => 'Person',
					'@id'   => $author_id,
					'name'  => get_the_author_meta( 'display_name', $post->post_author ),
					'url'   => get_author_posts_url( $post->post_author ),
					'image' => array(
						'@type'      => 'ImageObject',
						'url'        => get_avatar_url( $post->post_author ),
						'contentUrl' => get_avatar_url( $post->post_author ),
						'caption'    => get_the_author_meta( 'display_name', $post->post_author ),
					),
				);
			}
		}
		return apply_filters( 'moshai_seo_schema_graph', $graph, $c );
	}

	public static function output() {
		$graph = self::graph();
		if ( ! $graph ) {
			return;
		}
		$json = wp_json_encode(
			array(
				'@context' => 'https://schema.org',
				'@graph'   => $graph,
			),
			JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
		);
		// JSON-LD must not close the script tag early.
		echo '<script type="application/ld+json" class="moshai-seo-schema">' . str_replace( '</', '<\/', (string) $json ) . "</script>\n"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- JSON encoded above.
	}
}
