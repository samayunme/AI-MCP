<?php
/**
 * Content analysis: keyphrase checks and readability checks with a 0-100 score each.
 * Runs on the server, so the meta box, the dashboard audit and MCP all get the same answer.
 *
 * Scoring: every check is good (9), ok (6) or bad (3) points; the score is the share of the
 * maximum. 70+ is good (green), 40+ needs improvement (orange), below 40 has problems (red).
 *
 * @package AI_MCP
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class MoshAI_SEO_Analyzer {

	private static $content_cache = array();

	const STOPWORDS = array( 'a', 'an', 'the', 'and', 'or', 'of', 'to', 'in', 'on', 'for', 'with', 'at', 'by', 'from', 'is', 'are', 'be', 'your', 'you', 'my', 'our', 'how', 'what', 'why', 'it', 'its', 'as', 'that', 'this' );

	const TRANSITIONS = array( 'accordingly', 'additionally', 'afterward', 'afterwards', 'also', 'although', 'besides', 'because', 'but', 'consequently', 'conversely', 'finally', 'first', 'firstly', 'second', 'secondly', 'third', 'furthermore', 'hence', 'however', 'indeed', 'instead', 'likewise', 'meanwhile', 'moreover', 'nevertheless', 'next', 'nonetheless', 'otherwise', 'similarly', 'since', 'so', 'still', 'then', 'therefore', 'thus', 'whereas', 'while', 'yet', 'for example', 'for instance', 'in addition', 'in contrast', 'in fact', 'in other words', 'in short', 'as a result', 'on the other hand', 'to summarize', 'in conclusion', 'above all', 'after all', 'as well as', 'even though', 'in the same way', 'that is', 'to begin with', 'most importantly', 'in particular', 'specifically', 'especially', 'for this reason', 'of course', 'overall', 'ultimately', 'once', 'until', 'unless', 'before', 'after' );

	/* ---------- Content extraction ---------- */

	/** Post HTML for analysis. Elementor pages are rebuilt from their widget data. */
	public static function content( $post ) {
		if ( isset( self::$content_cache[ $post->ID ] ) ) {
			return self::$content_cache[ $post->ID ];
		}
		$html = (string) $post->post_content;
		if ( 'builder' === get_post_meta( $post->ID, '_elementor_edit_mode', true ) ) {
			$el = self::elementor_html( $post->ID );
			if ( strlen( wp_strip_all_tags( $el ) ) > strlen( wp_strip_all_tags( $html ) ) ) {
				$html = $el;
			}
		}
		if ( 'product' === $post->post_type && '' !== trim( $post->post_excerpt ) ) {
			$html .= "\n<p>" . $post->post_excerpt . '</p>';
		}
		$html = strip_shortcodes( preg_replace( '/<!--.*?-->/s', '', $html ) );
		self::$content_cache[ $post->ID ] = $html;
		return $html;
	}

	/** Builds plain HTML (headings, text, images, links) from Elementor's saved widget data. Fast: no rendering. */
	public static function elementor_html( $post_id ) {
		$raw  = get_post_meta( $post_id, '_elementor_data', true );
		$data = is_array( $raw ) ? $raw : json_decode( (string) $raw, true );
		if ( ! is_array( $data ) ) {
			return '';
		}
		$out = '';
		self::walk_elementor( $data, $out );
		return $out;
	}

	private static function walk_elementor( $elements, &$out ) {
		foreach ( $elements as $el ) {
			if ( ! is_array( $el ) ) {
				continue;
			}
			$s    = isset( $el['settings'] ) && is_array( $el['settings'] ) ? $el['settings'] : array();
			$type = isset( $el['widgetType'] ) ? $el['widgetType'] : ( isset( $el['elType'] ) ? $el['elType'] : '' );
			switch ( $type ) {
				case 'heading':
				case 'e-heading':
					$tag  = self::val( $s, 'header_size' );
					$tag  = $tag ? $tag : self::val( $s, 'tag' );
					$tag  = preg_match( '/^h[1-6]$/', (string) $tag ) ? $tag : 'h2';
					$out .= '<' . $tag . '>' . self::val( $s, 'title' ) . '</' . $tag . ">\n";
					break;
				case 'text-editor':
				case 'e-paragraph':
					$out .= '<div>' . self::val( $s, 'editor' ) . self::val( $s, 'paragraph' ) . "</div>\n";
					break;
				case 'image':
				case 'e-image':
					$img = isset( $s['image'] ) && is_array( $s['image'] ) ? $s['image'] : array();
					$id  = isset( $img['id'] ) ? (int) $img['id'] : 0;
					$url = isset( $img['url'] ) ? (string) $img['url'] : '';
					$alt = isset( $img['alt'] ) && is_string( $img['alt'] ) ? $img['alt'] : ( $id ? (string) get_post_meta( $id, '_wp_attachment_image_alt', true ) : '' );
					$out .= '<img src="' . esc_url( $url ) . '" alt="' . esc_attr( $alt ) . "\">\n";
					break;
				case 'button':
				case 'e-button':
					$link = isset( $s['link']['url'] ) ? (string) $s['link']['url'] : '';
					$out .= '<a href="' . esc_url( $link ) . '">' . esc_html( self::val( $s, 'text' ) ) . "</a>\n";
					break;
				default:
					foreach ( array( 'title_text' => 'h3', 'title' => 'h3', 'description_text' => 'p', 'description' => 'p', 'content' => 'p', 'testimonial_content' => 'p', 'text' => 'p', 'editor' => 'div', 'tab_content' => 'div' ) as $key => $tag ) {
						$v = self::val( $s, $key );
						if ( '' !== $v && ! preg_match( '/^(#|https?:)/', $v ) ) {
							$out .= '<' . $tag . '>' . $v . '</' . $tag . ">\n";
						}
					}
					foreach ( array( 'icon_list', 'tabs', 'slides', 'social_icon_list', 'price_list' ) as $rep ) {
						if ( isset( $s[ $rep ] ) && is_array( $s[ $rep ] ) ) {
							foreach ( $s[ $rep ] as $item ) {
								foreach ( array( 'text', 'tab_title', 'tab_content', 'heading', 'description', 'title' ) as $k ) {
									if ( isset( $item[ $k ] ) && is_string( $item[ $k ] ) && '' !== $item[ $k ] ) {
										$out .= '<p>' . $item[ $k ] . "</p>\n";
									}
								}
							}
						}
					}
			}
			if ( ! empty( $el['elements'] ) && is_array( $el['elements'] ) ) {
				self::walk_elementor( $el['elements'], $out );
			}
		}
	}

	/** A setting value from classic (string) or atomic ({"$$type":..., "value":...}) widgets. */
	private static function val( $s, $key ) {
		if ( ! isset( $s[ $key ] ) ) {
			return '';
		}
		$v = $s[ $key ];
		if ( is_array( $v ) && isset( $v['value'] ) ) {
			$v = $v['value'];
			if ( is_array( $v ) && isset( $v['value'] ) ) {
				$v = $v['value'];
			}
		}
		return is_string( $v ) ? $v : '';
	}

	public static function plain_text( $html ) {
		$html = preg_replace( '#<(script|style)[^>]*>.*?</\1>#is', ' ', (string) $html );
		$html = preg_replace( '#</(p|div|h[1-6]|li|tr|blockquote|section|br)\s*>|<br\s*/?>#i', "$0\n", $html );
		$text = html_entity_decode( wp_strip_all_tags( $html ), ENT_QUOTES, 'UTF-8' );
		return trim( preg_replace( '/[ \t]+/u', ' ', $text ) );
	}

	/* ---------- Text helpers ---------- */

	public static function words( $text ) {
		preg_match_all( "/[\p{L}\p{N}][\p{L}\p{N}'’-]*/u", mb_strtolower( (string) $text ), $m );
		return $m[0];
	}

	public static function sentences( $text ) {
		$text  = preg_replace( '/\s+/u', ' ', (string) $text );
		$parts = preg_split( '/(?<=[.!?…])\s+(?=[\p{Lu}\p{N}"“‘\'(])|\n+/u', $text );
		return array_values( array_filter( array_map( 'trim', (array) $parts ), 'strlen' ) );
	}

	private static function stem( $w ) {
		$w = preg_replace( "/['’]s$/u", '', $w );
		if ( mb_strlen( $w ) > 4 && 'ies' === mb_substr( $w, -3 ) ) {
			return mb_substr( $w, 0, -3 ) . 'y';
		}
		if ( mb_strlen( $w ) > 3 && 's' === mb_substr( $w, -1 ) && 'ss' !== mb_substr( $w, -2 ) ) {
			return mb_substr( $w, 0, -1 );
		}
		return $w;
	}

	/** Keyphrase content words (stopwords removed, stemmed). */
	public static function key_words( $phrase ) {
		$words = self::words( $phrase );
		$out   = array();
		foreach ( $words as $w ) {
			if ( ! in_array( $w, self::STOPWORDS, true ) || count( $words ) <= 2 ) {
				$out[] = self::stem( $w );
			}
		}
		return array_values( array_unique( $out ) );
	}

	/** True when every keyphrase word appears in the text, in any order. */
	public static function has_all( $text, $kw ) {
		if ( ! $kw ) {
			return false;
		}
		$stems = array_map( array( __CLASS__, 'stem' ), self::words( $text ) );
		foreach ( $kw as $k ) {
			if ( ! in_array( $k, $stems, true ) ) {
				return false;
			}
		}
		return true;
	}

	/** Number of times the whole keyphrase appears (words in order, small words in between allowed). */
	public static function occurrences( $text, $phrase ) {
		$kw = self::key_words( $phrase );
		if ( ! $kw ) {
			return 0;
		}
		$count = 0;
		foreach ( self::sentences( $text ) as $s ) {
			if ( self::has_all( $s, $kw ) ) {
				++$count;
			}
		}
		return $count;
	}

	private static function syllables( $word ) {
		$word = preg_replace( '/[^a-z]/', '', strtolower( $word ) );
		if ( '' === $word ) {
			return 0;
		}
		if ( strlen( $word ) <= 3 ) {
			return 1;
		}
		$word = preg_replace( '/(?:[^laeiouy]es|ed|[^laeiouy]e)$/', '', $word );
		$word = preg_replace( '/^y/', '', $word );
		preg_match_all( '/[aeiouy]{1,2}/', $word, $m );
		return max( 1, count( $m[0] ) );
	}

	private static function check( $id, $status, $text ) {
		$points = array(
			'good' => 9,
			'ok'   => 6,
			'bad'  => 3,
		);
		return array(
			'id'     => $id,
			'status' => $status,
			'points' => $points[ $status ],
			'text'   => $text,
		);
	}

	private static function score( $checks ) {
		if ( ! $checks ) {
			return 0;
		}
		$sum = 0;
		foreach ( $checks as $c ) {
			$sum += $c['points'];
		}
		return (int) round( $sum / ( count( $checks ) * 9 ) * 100 );
	}

	public static function rating( $score ) {
		if ( '' === $score || null === $score ) {
			return 'none';
		}
		$score = (int) $score;
		return $score >= 70 ? 'good' : ( $score >= 40 ? 'ok' : 'bad' );
	}

	/* ---------- Analysis ---------- */

	/**
	 * Full analysis of a post. $over replaces saved values for a live preview:
	 * title (SEO title field), description, focus_keyphrase, content (HTML), slug, post_title.
	 */
	public static function analyze( $post, $over = array() ) {
		$focus = isset( $over['focus_keyphrase'] ) ? (string) $over['focus_keyphrase'] : MoshAI_SEO_Meta::get( $post->ID, 'focus_keyphrase' );
		$html  = isset( $over['content'] ) ? (string) $over['content'] : self::content( $post );
		$slug  = isset( $over['slug'] ) ? (string) $over['slug'] : $post->post_name;
		$clone = clone $post;
		if ( isset( $over['post_title'] ) ) {
			$clone->post_title = (string) $over['post_title'];
		}
		$own_t = isset( $over['title'] ) ? (string) $over['title'] : MoshAI_SEO_Meta::get( $post->ID, 'title' );
		$tpl   = '' !== $own_t ? $own_t : MoshAI_SEO_Meta::type_setting( $post->post_type, 'title' );
		$title = MoshAI_SEO_Vars::replace( $tpl, array( 'post' => $clone ) );
		$own_d = isset( $over['description'] ) ? (string) $over['description'] : MoshAI_SEO_Meta::get( $post->ID, 'description' );
		$desc  = MoshAI_SEO_Vars::replace( '' !== $own_d ? $own_d : MoshAI_SEO_Meta::type_setting( $post->post_type, 'desc' ), array( 'post' => $clone ) );
		$cornerstone = '1' === MoshAI_SEO_Meta::get( $post->ID, 'cornerstone' );

		$text       = self::plain_text( $html );
		$words      = self::words( $text );
		$word_count = count( $words );
		$sentences  = self::sentences( $text );
		preg_match_all( '#<h([1-6])[^>]*>(.*?)</h\1>#is', $html, $hm );
		$headings = array();
		foreach ( $hm[1] as $i => $lvl ) {
			$headings[] = array( (int) $lvl, self::plain_text( $hm[2][ $i ] ) );
		}
		preg_match_all( '#<img\b[^>]*>#i', $html, $im );
		$images = $im[0];
		if ( has_post_thumbnail( $post ) && 'product' === $post->post_type ) {
			$images[] = '<img alt="' . esc_attr( (string) get_post_meta( get_post_thumbnail_id( $post ), '_wp_attachment_image_alt', true ) ) . '">';
		}
		preg_match_all( '#<a\b[^>]*href=["\']([^"\']+)["\']#i', $html, $lm );
		$host     = wp_parse_url( home_url(), PHP_URL_HOST );
		$internal = 0;
		$external = 0;
		foreach ( $lm[1] as $href ) {
			if ( preg_match( '#^(mailto:|tel:|\#|javascript:)#i', $href ) ) {
				continue;
			}
			$h = wp_parse_url( $href, PHP_URL_HOST );
			if ( ! $h || $h === $host ) {
				++$internal;
			} else {
				++$external;
			}
		}
		preg_match_all( '#<p[^>]*>(.*?)</p>|<div[^>]*>([^<]{80,})</div>#is', $html, $pm );
		$paragraphs = array();
		foreach ( $pm[0] as $p ) {
			$t = self::plain_text( $p );
			if ( '' !== $t ) {
				$paragraphs[] = $t;
			}
		}
		if ( ! $paragraphs && '' !== $text ) {
			$paragraphs = array_values( array_filter( array_map( 'trim', preg_split( '/\n{2,}/', $text ) ) ) );
		}

		$seo = self::seo_checks( $post, $focus, $title, $desc, $slug, $text, $words, $headings, $images, $internal, $external, $paragraphs, $cornerstone, $html );
		$read = self::readability_checks( $text, $words, $sentences, $paragraphs, $headings, $html );

		$syll = 0;
		foreach ( $words as $w ) {
			$syll += self::syllables( $w );
		}
		$flesch = $word_count && $sentences ? round( 206.835 - 1.015 * ( $word_count / count( $sentences ) ) - 84.6 * ( $syll / $word_count ), 1 ) : null;

		return array(
			'title'       => $title,
			'description' => $desc,
			'focus'       => $focus,
			'seo'         => array(
				'score'  => self::score( $seo ),
				'rating' => self::rating( self::score( $seo ) ),
				'checks' => $seo,
			),
			'readability' => array(
				'score'  => self::score( $read ),
				'rating' => self::rating( self::score( $read ) ),
				'checks' => $read,
			),
			'stats'       => array(
				'words'          => $word_count,
				'sentences'      => count( $sentences ),
				'paragraphs'     => count( $paragraphs ),
				'headings'       => count( $headings ),
				'images'         => count( $images ),
				'internal_links' => $internal,
				'external_links' => $external,
				'reading_time'   => max( 1, (int) ceil( $word_count / 200 ) ),
				'flesch'         => $flesch,
			),
		);
	}

	private static function seo_checks( $post, $focus, $title, $desc, $slug, $text, $words, $headings, $images, $internal, $external, $paragraphs, $cornerstone, $html ) {
		$c          = array();
		$word_count = count( $words );
		$kw         = self::key_words( $focus );

		if ( '' === trim( $focus ) ) {
			$c[] = self::check( 'keyphrase', 'bad', __( 'No focus keyphrase set. Add the search phrase this page should rank for.', 'ai-mcp' ) );
		} else {
			$n = count( self::words( $focus ) );
			$c[] = $n <= 4 ? self::check( 'keyphrase_length', 'good', __( 'Keyphrase length: good.', 'ai-mcp' ) ) : self::check( 'keyphrase_length', $n <= 8 ? 'ok' : 'bad', __( 'Keyphrase is long. Keep it to 4 words or fewer.', 'ai-mcp' ) );

			// Title.
			$tl = mb_strtolower( $title );
			$fl = mb_strtolower( trim( $focus ) );
			if ( 0 === mb_strpos( $tl, $fl ) ) {
				$c[] = self::check( 'keyphrase_title', 'good', __( 'Keyphrase in SEO title: it starts the title. Great.', 'ai-mcp' ) );
			} elseif ( self::has_all( $title, $kw ) ) {
				$c[] = self::check( 'keyphrase_title', 'ok', __( 'Keyphrase in SEO title, but not at the start. Move it to the beginning.', 'ai-mcp' ) );
			} else {
				$c[] = self::check( 'keyphrase_title', 'bad', __( 'The SEO title does not contain the keyphrase.', 'ai-mcp' ) );
			}

			$c[] = self::has_all( $desc, $kw ) ? self::check( 'keyphrase_description', 'good', __( 'Keyphrase in meta description: good.', 'ai-mcp' ) ) : self::check( 'keyphrase_description', 'bad', __( 'The meta description does not contain the keyphrase.', 'ai-mcp' ) );
			$c[] = self::has_all( str_replace( array( '-', '_' ), ' ', urldecode( $slug ) ), $kw ) ? self::check( 'keyphrase_slug', 'good', __( 'Keyphrase in URL slug: good.', 'ai-mcp' ) ) : self::check( 'keyphrase_slug', 'ok', __( 'The URL slug does not contain the keyphrase.', 'ai-mcp' ) );

			$intro = $paragraphs ? $paragraphs[0] : implode( ' ', array_slice( self::sentences( $text ), 0, 2 ) );
			$c[]   = self::has_all( $intro, $kw ) ? self::check( 'keyphrase_intro', 'good', __( 'Keyphrase in introduction: good.', 'ai-mcp' ) ) : self::check( 'keyphrase_intro', 'bad', __( 'Use the keyphrase in the first paragraph.', 'ai-mcp' ) );

			if ( $word_count >= 100 ) {
				$occ     = self::occurrences( $text, $focus );
				$density = $word_count ? $occ * max( 1, count( $kw ) ) / $word_count * 100 : 0;
				/* translators: 1: number of times, 2: density percent */
				$msg = sprintf( __( 'Keyphrase density: found %1$d times (%2$s%%).', 'ai-mcp' ), $occ, number_format_i18n( $density, 1 ) );
				if ( $density >= 0.5 && $density <= 3 ) {
					$c[] = self::check( 'keyphrase_density', 'good', $msg . ' ' . __( 'Good.', 'ai-mcp' ) );
				} elseif ( $density > 3 ) {
					$c[] = self::check( 'keyphrase_density', 'bad', $msg . ' ' . __( 'That is keyword stuffing. Use it less.', 'ai-mcp' ) );
				} else {
					$c[] = self::check( 'keyphrase_density', $occ > 0 ? 'ok' : 'bad', $msg . ' ' . __( 'Use it a few more times.', 'ai-mcp' ) );
				}
			}

			$sub = array_filter(
				$headings,
				function ( $h ) {
					return in_array( $h[0], array( 2, 3 ), true );
				}
			);
			if ( $sub ) {
				$with = 0;
				foreach ( $sub as $h ) {
					if ( self::has_all( $h[1], $kw ) ) {
						++$with;
					}
				}
				$share = $with / count( $sub );
				if ( $share >= 0.3 && $share <= 0.75 ) {
					$c[] = self::check( 'keyphrase_subheadings', 'good', __( 'Keyphrase in subheadings: good.', 'ai-mcp' ) );
				} elseif ( $with > 0 ) {
					$c[] = self::check( 'keyphrase_subheadings', 'ok', $share > 0.75 ? __( 'Keyphrase is in almost every subheading. Vary them.', 'ai-mcp' ) : __( 'Use the keyphrase in more H2 or H3 subheadings.', 'ai-mcp' ) );
				} else {
					$c[] = self::check( 'keyphrase_subheadings', 'bad', __( 'No H2 or H3 subheading contains the keyphrase.', 'ai-mcp' ) );
				}
			}

			if ( $images ) {
				$with = 0;
				foreach ( $images as $img ) {
					if ( preg_match( '/alt=["\']([^"\']*)["\']/i', $img, $m ) && self::has_all( $m[1], $kw ) ) {
						++$with;
					}
				}
				$c[] = $with ? self::check( 'keyphrase_images', 'good', __( 'Keyphrase in image alt text: good.', 'ai-mcp' ) ) : self::check( 'keyphrase_images', 'ok', __( 'No image alt text contains the keyphrase.', 'ai-mcp' ) );
			}

			$dupe = get_posts(
				array(
					'post_type'      => 'any',
					'post_status'    => 'publish',
					'posts_per_page' => 1,
					'fields'         => 'ids',
					'post__not_in'   => array( $post->ID ),
					'meta_key'       => MoshAI_SEO_Meta::PREFIX . 'focus_keyphrase', // phpcs:ignore WordPress.DB.SlowDBQuery
					'meta_value'     => $focus, // phpcs:ignore WordPress.DB.SlowDBQuery
				)
			);
			$c[] = $dupe ? self::check( 'keyphrase_unique', 'ok', __( 'You already used this keyphrase on another page. Pick a different one so they do not compete.', 'ai-mcp' ) ) : self::check( 'keyphrase_unique', 'good', __( 'Keyphrase not used before: good.', 'ai-mcp' ) );
		}

		$tlen = mb_strlen( $title );
		if ( $tlen >= 40 && $tlen <= 60 ) {
			$c[] = self::check( 'title_length', 'good', __( 'SEO title width: good.', 'ai-mcp' ) );
		} elseif ( $tlen > 60 ) {
			/* translators: %d characters */
			$c[] = self::check( 'title_length', $tlen <= 70 ? 'ok' : 'bad', sprintf( __( 'SEO title is %d characters. Google cuts it after about 60.', 'ai-mcp' ), $tlen ) );
		} else {
			/* translators: %d characters */
			$c[] = self::check( 'title_length', $tlen >= 25 ? 'ok' : 'bad', sprintf( __( 'SEO title is short (%d characters). Use the space: 40 to 60 is ideal.', 'ai-mcp' ), $tlen ) );
		}

		$dlen = mb_strlen( $desc );
		if ( 0 === $dlen ) {
			$c[] = self::check( 'description_length', 'bad', __( 'No meta description. Google will pick random text from the page.', 'ai-mcp' ) );
		} elseif ( $dlen >= 120 && $dlen <= 156 ) {
			$c[] = self::check( 'description_length', 'good', __( 'Meta description length: good.', 'ai-mcp' ) );
		} else {
			/* translators: %d characters */
			$c[] = self::check( 'description_length', ( $dlen >= 80 && $dlen <= 170 ) ? 'ok' : 'bad', sprintf( __( 'Meta description is %d characters. Aim for 120 to 156.', 'ai-mcp' ), $dlen ) );
		}

		$min = 'product' === $post->post_type ? 200 : ( $cornerstone ? 900 : 300 );
		if ( $word_count >= $min ) {
			/* translators: %d words */
			$c[] = self::check( 'text_length', 'good', sprintf( __( 'Text length: %d words. Good.', 'ai-mcp' ), $word_count ) );
		} else {
			/* translators: 1: words, 2: minimum */
			$c[] = self::check( 'text_length', $word_count >= $min * 0.75 ? 'ok' : 'bad', sprintf( __( 'Text length: %1$d words. Write at least %2$d.', 'ai-mcp' ), $word_count, $min ) );
		}

		$c[] = $internal ? self::check( 'internal_links', 'good', __( 'Internal links: good.', 'ai-mcp' ) ) : self::check( 'internal_links', 'bad', __( 'No internal links. Link to related pages on your site.', 'ai-mcp' ) );
		$c[] = $external ? self::check( 'outbound_links', 'good', __( 'Outbound links: good.', 'ai-mcp' ) ) : self::check( 'outbound_links', 'ok', __( 'No outbound links. Link to a trusted source where it helps the reader.', 'ai-mcp' ) );

		if ( ! $images ) {
			$c[] = self::check( 'images', 'ok', __( 'No images on this page. Add at least one relevant image.', 'ai-mcp' ) );
		} else {
			$missing = 0;
			foreach ( $images as $img ) {
				if ( ! preg_match( '/alt=["\'][^"\']+["\']/i', $img ) ) {
					++$missing;
				}
			}
			/* translators: %d images */
			$c[] = $missing ? self::check( 'image_alt', 'ok', sprintf( _n( '%d image has no alt text.', '%d images have no alt text.', $missing, 'ai-mcp' ), $missing ) ) : self::check( 'image_alt', 'good', __( 'Every image has alt text.', 'ai-mcp' ) );
		}

		$h1 = 0;
		foreach ( $headings as $h ) {
			if ( 1 === $h[0] ) {
				++$h1;
			}
		}
		if ( $h1 > 1 ) {
			/* translators: %d headings */
			$c[] = self::check( 'single_h1', 'bad', sprintf( __( 'The content has %d H1 headings. Use one H1 and H2/H3 for sections.', 'ai-mcp' ), $h1 ) );
		}

		if ( MoshAI_SEO_Meta::is_noindex( $post ) && 'publish' === $post->post_status ) {
			$c[] = self::check( 'indexable', 'bad', __( 'This page is set to noindex: it will not show in search results.', 'ai-mcp' ) );
		}
		return $c;
	}

	private static function readability_checks( $text, $words, $sentences, $paragraphs, $headings, $html ) {
		$c          = array();
		$word_count = count( $words );
		if ( $word_count < 50 ) {
			$c[] = self::check( 'not_enough_text', 'bad', __( 'Not enough text to judge readability. Add more content.', 'ai-mcp' ) );
			return $c;
		}
		$english = 0 === strpos( get_locale(), 'en' );

		if ( $english && $sentences ) {
			$syll = 0;
			foreach ( $words as $w ) {
				$syll += self::syllables( $w );
			}
			$f = 206.835 - 1.015 * ( $word_count / count( $sentences ) ) - 84.6 * ( $syll / $word_count );
			/* translators: %s score */
			$msg = sprintf( __( 'Flesch reading ease: %s.', 'ai-mcp' ), number_format_i18n( $f, 1 ) );
			$c[] = $f >= 60 ? self::check( 'flesch', 'good', $msg . ' ' . __( 'Easy to read.', 'ai-mcp' ) ) : self::check( 'flesch', $f >= 50 ? 'ok' : 'bad', $msg . ' ' . __( 'Use shorter sentences and simpler words.', 'ai-mcp' ) );
		}

		if ( $sentences ) {
			$long = 0;
			foreach ( $sentences as $s ) {
				if ( count( self::words( $s ) ) > 20 ) {
					++$long;
				}
			}
			$share = $long / count( $sentences ) * 100;
			/* translators: %s percent */
			$msg = sprintf( __( '%s%% of sentences have more than 20 words.', 'ai-mcp' ), number_format_i18n( $share, 0 ) );
			$c[] = $share <= 25 ? self::check( 'sentence_length', 'good', __( 'Sentence length: good.', 'ai-mcp' ) ) : self::check( 'sentence_length', $share <= 30 ? 'ok' : 'bad', $msg . ' ' . __( 'Keep it under 25%.', 'ai-mcp' ) );
		}

		$longest = 0;
		foreach ( $paragraphs as $p ) {
			$longest = max( $longest, count( self::words( $p ) ) );
		}
		/* translators: %d words */
		$c[] = $longest <= 150 ? self::check( 'paragraph_length', 'good', __( 'Paragraph length: good.', 'ai-mcp' ) ) : self::check( 'paragraph_length', $longest <= 200 ? 'ok' : 'bad', sprintf( __( 'A paragraph has %d words. Split paragraphs longer than 150 words.', 'ai-mcp' ), $longest ) );

		// Subheading distribution: text between headings.
		$sections = preg_split( '#<h[2-6][^>]*>.*?</h[2-6]>#is', $html );
		$max      = 0;
		foreach ( (array) $sections as $sec ) {
			$max = max( $max, count( self::words( self::plain_text( $sec ) ) ) );
		}
		$subs = 0;
		foreach ( $headings as $h ) {
			if ( $h[0] >= 2 ) {
				++$subs;
			}
		}
		if ( $word_count > 300 && 0 === $subs ) {
			$c[] = self::check( 'subheadings', 'bad', __( 'No subheadings. Break the text up with H2 and H3 headings.', 'ai-mcp' ) );
		} elseif ( $max > 300 ) {
			$c[] = self::check( 'subheadings', $max > 350 ? 'bad' : 'ok', __( 'A section has more than 300 words without a subheading.', 'ai-mcp' ) );
		} else {
			$c[] = self::check( 'subheadings', 'good', __( 'Subheading distribution: good.', 'ai-mcp' ) );
		}

		if ( $english && $sentences ) {
			$passive = 0;
			$trans   = 0;
			foreach ( $sentences as $s ) {
				$l = ' ' . mb_strtolower( $s ) . ' ';
				if ( preg_match( '/\b(am|is|are|was|were|be|been|being|get|gets|got|gotten)\s+(\w+ed|\w+en|done|made|known|seen|given|taken|written|built|sold|found|told|shown|held|brought|bought|thought|kept|left|meant|paid|put|read|said|sent|set|spent|understood|won|led)\b/', $l ) ) {
					++$passive;
				}
				foreach ( self::TRANSITIONS as $t ) {
					if ( false !== strpos( $l, ' ' . $t . ' ' ) || false !== strpos( $l, ' ' . $t . ',' ) ) {
						++$trans;
						break;
					}
				}
			}
			$ps = $passive / count( $sentences ) * 100;
			/* translators: %s percent */
			$c[] = $ps <= 10 ? self::check( 'passive_voice', 'good', __( 'Passive voice: good.', 'ai-mcp' ) ) : self::check( 'passive_voice', $ps <= 15 ? 'ok' : 'bad', sprintf( __( '%s%% of sentences use passive voice. Keep it under 10%%.', 'ai-mcp' ), number_format_i18n( $ps, 0 ) ) );
			$ts = $trans / count( $sentences ) * 100;
			/* translators: %s percent */
			$c[] = $ts >= 30 ? self::check( 'transition_words', 'good', __( 'Transition words: good.', 'ai-mcp' ) ) : self::check( 'transition_words', $ts >= 20 ? 'ok' : 'bad', sprintf( __( 'Only %s%% of sentences use transition words (however, because, for example...). Aim for 30%%.', 'ai-mcp' ), number_format_i18n( $ts, 0 ) ) );
		}

		if ( count( $sentences ) >= 3 ) {
			$run  = 1;
			$max  = 1;
			$prev = '';
			foreach ( $sentences as $s ) {
				$w     = self::words( $s );
				$first = $w ? $w[0] : '';
				$run   = ( '' !== $first && $first === $prev ) ? $run + 1 : 1;
				$max   = max( $max, $run );
				$prev  = $first;
			}
			/* translators: %d sentences */
			$c[] = $max < 3 ? self::check( 'sentence_starts', 'good', __( 'Sentence beginnings: good variety.', 'ai-mcp' ) ) : self::check( 'sentence_starts', 'bad', sprintf( __( '%d sentences in a row start with the same word. Mix it up.', 'ai-mcp' ), $max ) );
		}
		return $c;
	}
}
