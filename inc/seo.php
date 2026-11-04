<?php
/**
 * Search and sharing, without an SEO plugin.
 *
 * - Titles: the homepage uses Site content › Search and sharing › Homepage title;
 *   every other page is "Page title – Your name".
 * - A meta description on every page: the homepage's own, a page's or post's
 *   Excerpt (or the start of its text), a category's description.
 * - Open Graph and Twitter tags, so links shared on LinkedIn, WhatsApp or X show
 *   a title, text and picture.
 * - Structured data (JSON-LD): the site and you as a Person on the homepage,
 *   BlogPosting on posts.
 * - Robots and sitemap: WordPress's own /wp-sitemap.xml without user pages or
 *   page-builder templates; search results, 404s and attachment pages are noindex.
 *
 * @package isprithul-core
 */

defined( 'ABSPATH' ) || exit;

/** Pages get an Excerpt box: it becomes their description in search results. */
add_action( 'init', fn() => add_post_type_support( 'page', 'excerpt' ) );

/* ---------- titles ---------- */

add_filter(
	'pre_get_document_title',
	function ( $title ) {
		if ( is_front_page() && ! is_paged() ) {
			$home = trim( (string) isp_get( 'seo.title', '' ) );
			return esc_html( '' !== $home ? $home : isp_seo_name() );
		}
		return $title;
	},
	20
);

add_filter(
	'document_title_parts',
	function ( $parts ) {
		$parts['site'] = isp_seo_name();
		unset( $parts['tagline'] );
		return $parts;
	},
	20
);

function isp_seo_name(): string {
	$name = trim( (string) isp_get( 'site.name', '' ) );
	return '' !== $name ? $name : (string) get_bloginfo( 'name' );
}

/* ---------- what describes this page ---------- */

function isp_seo_clean( string $text, int $max = 160 ): string {
	// A space where each tag was, so "list.</li><li>Next" doesn't become "list.Next".
	$text = trim( (string) preg_replace( '/\s+/u', ' ', wp_strip_all_tags( str_replace( '<', ' <', strip_shortcodes( $text ) ) ) ) );
	$text = (string) preg_replace( '/\s+([,.;:!?])/u', '$1', $text );
	if ( mb_strlen( $text ) > $max ) {
		$cut  = mb_substr( $text, 0, $max - 1 );
		$text = rtrim( (string) preg_replace( '/\s+\S*$/u', '', $cut ), ' ,;:–-' ) . '…';
	}
	return $text;
}

function isp_seo_description(): string {
	$text = '';
	if ( is_front_page() ) {
		$text = (string) isp_get( 'seo.description', '' );
	} elseif ( is_singular() ) {
		$post = get_queried_object();
		if ( $post instanceof WP_Post && ! post_password_required( $post ) ) {
			$text = has_excerpt( $post ) ? $post->post_excerpt : $post->post_content;
		}
	} elseif ( is_category() || is_tag() || is_tax() ) {
		$text = (string) term_description();
	}
	$text = isp_seo_clean( $text );
	return '' !== $text ? $text : isp_seo_clean( (string) isp_get( 'seo.description', '' ) );
}

function isp_seo_url(): string {
	if ( is_front_page() ) {
		return home_url( '/' );
	}
	if ( is_singular() ) {
		return (string) wp_get_canonical_url();
	}
	global $wp;
	return home_url( user_trailingslashit( (string) $wp->request ) );
}

/** The share picture: the post's featured image, the one chosen under Site content, or the built-in one. */
function isp_seo_image(): array {
	$id = 0;
	if ( is_singular() && ! is_front_page() && has_post_thumbnail() ) {
		$id = (int) get_post_thumbnail_id();
	}
	if ( ! $id ) {
		$id = (int) isp_raw()['seo.image'];
	}
	if ( $id && ( $src = wp_get_attachment_image_src( $id, 'large' ) ) ) {
		return array(
			'url' => $src[0],
			'w'   => (int) $src[1],
			'h'   => (int) $src[2],
			'alt' => (string) get_post_meta( $id, '_wp_attachment_image_alt', true ),
		);
	}
	return array(
		'url' => ISP_CORE_URL . 'assets/share.jpg',
		'w'   => 1200,
		'h'   => 630,
		'alt' => isp_seo_name() . ', hardware engineer',
	);
}

/* ---------- tags in <head> ---------- */

add_action(
	'wp_head',
	function () {
		if ( is_404() || is_search() ) {
			return;
		}
		$desc  = isp_seo_description();
		$title = wp_get_document_title();
		$img   = isp_seo_image();
		$type  = is_singular( 'post' ) ? 'article' : 'website';
		$tags  = array(
			array( 'name', 'description', $desc ),
			array( 'property', 'og:type', $type ),
			array( 'property', 'og:site_name', isp_seo_name() ),
			array( 'property', 'og:title', html_entity_decode( $title, ENT_QUOTES, 'UTF-8' ) ),
			array( 'property', 'og:description', $desc ),
			array( 'property', 'og:url', isp_seo_url() ),
			array( 'property', 'og:locale', str_replace( '-', '_', get_bloginfo( 'language' ) ) ),
			array( 'property', 'og:image', $img['url'] ),
			array( 'property', 'og:image:width', (string) $img['w'] ),
			array( 'property', 'og:image:height', (string) $img['h'] ),
			array( 'property', 'og:image:alt', $img['alt'] ),
			array( 'name', 'twitter:card', 'summary_large_image' ),
		);
		if ( 'article' === $type ) {
			$tags[] = array( 'property', 'article:published_time', get_the_date( 'c' ) );
			$tags[] = array( 'property', 'article:modified_time', get_the_modified_date( 'c' ) );
		}
		echo "\n<!-- isprithul core: search and sharing -->\n";
		foreach ( $tags as $tag ) {
			if ( '' !== $tag[2] ) {
				printf( '<meta %s="%s" content="%s">' . "\n", $tag[0], esc_attr( $tag[1] ), esc_attr( $tag[2] ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- attribute name is one of two literals.
			}
		}
		$graph = isp_seo_graph();
		if ( $graph ) {
			echo '<script type="application/ld+json">' . wp_json_encode( array( '@context' => 'https://schema.org', '@graph' => $graph ), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG ) . "</script>\n";
		}
	},
	1
);

/** Structured data: who runs the site (homepage), plus the article on posts. */
function isp_seo_graph(): array {
	$home   = home_url( '/' );
	$person = array(
		'@type'    => 'Person',
		'@id'      => $home . '#person',
		'name'     => isp_seo_name(),
		'url'      => $home,
		'jobTitle' => (string) isp_get( 'seo.jobTitle', '' ),
		'address'  => array(
			'@type'           => 'PostalAddress',
			'addressLocality' => 'Budapest',
			'addressCountry'  => 'HU',
		),
		'sameAs'   => array_values( array_filter( array_map( fn( $s ) => esc_url_raw( $s['href'] ), (array) isp_get( 'site.socials', array() ) ) ) ),
	);
	$photo = isp_get( 'about.photo', null );
	if ( is_array( $photo ) && ! empty( $photo['url'] ) ) {
		$person['image'] = $photo['url'];
	}
	if ( is_front_page() ) {
		return array(
			array(
				'@type'     => 'WebSite',
				'@id'       => $home . '#website',
				'url'       => $home,
				'name'      => isp_seo_name(),
				'publisher' => array( '@id' => $home . '#person' ),
			),
			$person,
		);
	}
	if ( is_singular( 'post' ) ) {
		$img = isp_seo_image();
		return array(
			array(
				'@type'         => 'BlogPosting',
				'headline'      => html_entity_decode( get_the_title(), ENT_QUOTES, 'UTF-8' ),
				'description'   => isp_seo_description(),
				'datePublished' => get_the_date( 'c' ),
				'dateModified'  => get_the_modified_date( 'c' ),
				'url'           => (string) get_permalink(),
				'image'         => $img['url'],
				'author'        => array( '@id' => $home . '#person' ),
				'publisher'     => array( '@id' => $home . '#person' ),
			),
			$person,
		);
	}
	return array();
}

/* ---------- robots and sitemap ---------- */

add_filter(
	'wp_robots',
	function ( $robots ) {
		if ( is_404() || is_search() || is_attachment() ) {
			$robots['noindex'] = true;
			$robots['follow']  = true;
		}
		return $robots;
	}
);

/* WordPress's own sitemap (/wp-sitemap.xml), minus user pages and page-builder templates. */
add_filter( 'wp_sitemaps_enabled', '__return_true' );
add_filter(
	'wp_sitemaps_add_provider',
	fn( $provider, $name ) => 'users' === $name ? false : $provider,
	10,
	2
);
add_filter(
	'wp_sitemaps_post_types',
	function ( $types ) {
		foreach ( array( 'elementor_library', 'e-landing-page', 'e-floating-buttons', 'elementor-hf' ) as $type ) {
			unset( $types[ $type ] );
		}
		return $types;
	}
);

/* No "generator: WordPress 7.x" tag telling everyone which version runs here. */
remove_action( 'wp_head', 'wp_generator' );
add_filter( 'the_generator', '__return_empty_string' );
