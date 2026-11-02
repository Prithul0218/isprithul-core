<?php
/**
 * Site content: every word on the homepage outside the projects.
 *
 * Stored in one option (isp_content) as the raw text of each field on the
 * Site content screen, keyed by where it shows on the page ("hero.title").
 * isp_content() turns that into the nested structure the theme renders.
 *
 * @package isprithul-core
 */

defined( 'ABSPATH' ) || exit;

const ISP_OPTION = 'isp_content';

/** Default texts (generated from the homepage mockup, see tools/build-data.js). */
function isp_defaults(): array {
	static $defaults = null;
	if ( null === $defaults ) {
		$json     = file_get_contents( ISP_CORE_DIR . 'data/defaults.json' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
		$defaults = json_decode( (string) $json, true );
		if ( ! is_array( $defaults ) ) {
			$defaults = array();
		}
	}
	return $defaults;
}

/**
 * The Site content screen, section by section.
 * Field types: text, textarea, lines (one item per line, columns split by |),
 * paragraphs (blank line between), checkbox, email, url, image, page.
 */
function isp_schema(): array {
	$t = fn( $label, $extra = array() ) => array_merge( array( 'type' => 'text', 'label' => $label ), $extra );
	return array(
		'basics'      => array(
			'title'  => 'Basics',
			'fields' => array(
				'site.available'    => array(
					'type'  => 'checkbox',
					'label' => 'Availability',
					'check' => 'Taking new projects (the D1 LED is lit). Untick it when you are booked.',
				),
				'site.statusOn'     => $t( 'Status line: taking projects' ),
				'site.statusOff'    => $t( 'Status line: booked' ),
				'site.name'         => $t( 'Your name' ),
				'site.email'        => array(
					'type'  => 'email',
					'label' => 'Contact email',
					'help'  => 'Shown in the Contact section. The Copy button copies it.',
				),
				'site.headerButton' => $t( 'Header button' ),
				'site.nav'          => array(
					'type'  => 'lines',
					'label' => 'Menu links',
					'cols'  => array(
						'label' => 'Label',
						'href'  => 'Link',
					),
					'help'  => '#projects, #services, #about and #contact jump to sections of the homepage. Any other link works too (for example /blog/).',
				),
				'site.socials'      => array(
					'type'  => 'lines',
					'label' => 'Footer links: elsewhere',
					'cols'  => array(
						'label' => 'Label',
						'href'  => 'Address',
					),
				),
			),
		),
		'hero'        => array(
			'title'  => 'Top of the page',
			'fields' => array(
				'hero.questionStart' => $t( 'Question start', array( 'help' => 'The fixed first words of the rotating question.' ) ),
				'hero.questions'     => array(
					'type'  => 'lines',
					'label' => 'Rotating questions',
					'cols'  => array(
						'q'      => 'Question',
						'a'      => 'Answer',
						'target' => 'Target',
						'booked' => 'Answer when booked',
					),
					'rows'  => 6,
					'help'  => 'Target: a project ID (opens that project), u1, u2 or u3 (filters the projects by that service), or brief (opens the Start a project form; the fourth column is shown instead while you are booked).',
				),
				'hero.title'         => $t( 'Headline', array( 'type' => 'textarea', 'rows' => 2 ) ),
				'hero.intro'         => $t( 'Intro', array( 'type' => 'textarea', 'rows' => 3 ) ),
				'hero.button'        => $t( 'Main button' ),
				'hero.secondButton'  => $t( 'Second button' ),
			),
		),
		'probe'       => array(
			'title'  => 'Schematic',
			'intro'  => 'The interactive schematic next to the headline. Its three services (U1–U3) take their names and texts from the Services section.',
			'fields' => array(
				'probe.j1Title'    => $t( 'J1 (ISP header): title' ),
				'probe.j1Text'     => $t( 'J1: text' ),
				'probe.j1Note'     => $t( 'J1: note on the drawing' ),
				'probe.d1TitleOn'  => $t( 'D1 lit: title' ),
				'probe.d1TextOn'   => $t( 'D1 lit: text' ),
				'probe.d1NoteOn'   => $t( 'D1 lit: note on the drawing' ),
				'probe.d1TitleOff' => $t( 'D1 dark: title' ),
				'probe.d1TextOff'  => $t( 'D1 dark: text' ),
				'probe.d1NoteOff'  => $t( 'D1 dark: note on the drawing' ),
				'probe.button'     => $t( 'Probe card button' ),
				'probe.seeAll'     => $t( '"See all" button', array( 'help' => 'The number of matching projects is added after it.' ) ),
			),
		),
		'projects'    => array(
			'title'  => 'Projects section',
			'intro'  => 'The projects themselves are edited under Projects in the left menu.',
			'fields' => array(
				'projectsSection.title'           => $t( 'Section title' ),
				'projectsSection.all'             => $t( 'Filter: everything' ),
				'projectsSection.groups.client'   => $t( 'Filter: client work' ),
				'projectsSection.groups.venture'  => $t( 'Filter: ventures and competitions' ),
				'projectsSection.groups.personal' => $t( 'Filter: personal' ),
				'projectsSection.surprise'        => $t( 'Random project button' ),
				'projectsSection.similarButton'   => $t( 'Photo viewer: button' ),
				'projectsSection.prev'            => $t( 'Photo viewer: previous' ),
				'projectsSection.next'            => $t( 'Photo viewer: next' ),
			),
		),
		'services'    => array(
			'title'  => 'Services',
			'fields' => array(
				'services.title'        => $t( 'Section title' ),
				'services.u1.name'      => $t( 'U1 name' ),
				'services.u1.text'      => $t( 'U1 text', array( 'type' => 'textarea', 'rows' => 2 ) ),
				'services.u1.link'      => $t( 'U1 link' ),
				'services.u2.name'      => $t( 'U2 name' ),
				'services.u2.text'      => $t( 'U2 text', array( 'type' => 'textarea', 'rows' => 2 ) ),
				'services.u2.link'      => $t( 'U2 link' ),
				'services.u3.name'      => $t( 'U3 name' ),
				'services.u3.text'      => $t( 'U3 text', array( 'type' => 'textarea', 'rows' => 2 ) ),
				'services.u3.link'      => $t( 'U3 link' ),
				'services.processTitle' => $t( 'Process title' ),
				'services.process'      => array(
					'type'  => 'lines',
					'label' => 'Process steps',
					'cols'  => array(
						'step'  => 'Number',
						'title' => 'Title',
						'text'  => 'Text',
					),
				),
			),
		),
		'clients'     => array(
			'title'  => 'Clients',
			'fields' => array(
				'clients.title'           => $t( 'Section title' ),
				'clients.link.label'      => $t( 'Link next to the title' ),
				'clients.link.href'       => $t( 'Link address', array( 'type' => 'url' ) ),
				'clients.stats'           => array(
					'type'  => 'lines',
					'label' => 'Numbers',
					'cols'  => array(
						'n'     => 'Number',
						'label' => 'Label',
					),
				),
				'clients.featured.text'   => $t( 'Featured quote', array( 'type' => 'textarea', 'rows' => 3 ) ),
				'clients.featured.name'   => $t( 'Featured quote: name' ),
				'clients.featured.role'   => $t( 'Featured quote: role' ),
				'clients.featured.source' => $t( 'Featured quote: source' ),
				'clients.quotes'          => array(
					'type'  => 'lines',
					'label' => 'Short quotes',
					'cols'  => array(
						'text' => 'Quote',
						'who'  => 'Who',
					),
				),
			),
		),
		'experience'  => array(
			'title'  => 'Experience',
			'fields' => array(
				'experience.title' => $t( 'Section title' ),
				'experience.list'  => array(
					'type'  => 'lines',
					'label' => 'Timeline',
					'cols'  => array(
						'when'  => 'When',
						'title' => 'Role',
						'where' => 'Where',
					),
					'rows'  => 7,
				),
			),
		),
		'about'       => array(
			'title'  => 'About',
			'fields' => array(
				'about.title' => $t( 'Title' ),
				'about.photo' => array(
					'type'  => 'image',
					'label' => 'Portrait',
					'help'  => 'The photo’s alt text in the Media Library is used for screen readers.',
				),
				'about.text'  => array(
					'type'  => 'paragraphs',
					'label' => 'Text',
					'rows'  => 5,
				),
				'about.facts' => array(
					'type'  => 'lines',
					'label' => 'Facts under the text',
				),
			),
		),
		'contact'     => array(
			'title'  => 'Contact',
			'fields' => array(
				'contact.title'        => $t( 'Title' ),
				'contact.button'       => $t( 'Button' ),
				'contact.emailLabel'   => $t( 'Email label' ),
				'contact.copy'         => $t( 'Copy button' ),
				'contact.copied'       => $t( 'Message after copying' ),
				'contact.copyFallback' => $t( 'Message when copying is blocked' ),
				'contact.basedLabel'   => $t( 'Location label' ),
				'contact.based'        => $t( 'Location' ),
				'contact.replyLabel'   => $t( 'Third cell: label' ),
				'contact.reply'        => $t( 'Third cell: text' ),
			),
		),
		'footer'      => array(
			'title'  => 'Footer',
			'fields' => array(
				'footer.copyright' => $t( 'Copyright line', array( 'help' => '{year} becomes the current year.' ) ),
				'footer.legal'     => $t( 'Legal link label' ),
				'footer.legalPage' => array(
					'type'  => 'page',
					'label' => 'Legal page',
					'help'  => 'Leave on “none” to link to /legal/.',
				),
				'footer.rev'       => $t( 'Revision mark' ),
			),
		),
		'brief'       => array(
			'title'  => 'Start a project form',
			'intro'  => 'Each brief is saved under Briefs and emailed to you, with Reply-To set to the sender.',
			'fields' => array(
				'brief.notify'          => array(
					'type'  => 'email',
					'label' => 'Send briefs to',
					'help'  => 'Leave empty to use the contact email.',
				),
				'brief.title'           => $t( 'Title' ),
				'brief.intro'           => $t( 'Intro' ),
				'brief.name'            => $t( 'Name field' ),
				'brief.email'           => $t( 'Email field' ),
				'brief.what'            => $t( 'Project field' ),
				'brief.type'            => $t( 'Service field', array( 'help' => 'Its options are the three service names plus the next field.' ) ),
				'brief.typeOther'       => $t( 'Service field: other' ),
				'brief.timeline'        => $t( 'Timeline field' ),
				'brief.timelineOptions' => array(
					'type'  => 'lines',
					'label' => 'Timeline options',
				),
				'brief.budget'          => $t( 'Budget field' ),
				'brief.send'            => $t( 'Send button' ),
				'brief.errorName'       => $t( 'Error: no name' ),
				'brief.errorEmail'      => $t( 'Error: bad email' ),
				'brief.errorWhat'       => $t( 'Error: no project' ),
				'brief.errorSend'       => $t( 'Error: sending failed', array( 'help' => 'Your contact email is added after it.' ) ),
				'brief.doneTitle'       => $t( 'After sending: title' ),
				'brief.doneText'        => $t( 'After sending: text', array( 'type' => 'textarea', 'rows' => 2 ) ),
				'brief.close'           => $t( 'Close button' ),
			),
		),
	);
}

/** All fields, keyed by their place on the page. */
function isp_fields(): array {
	static $fields = null;
	if ( null === $fields ) {
		$fields = array();
		foreach ( isp_schema() as $section ) {
			$fields += $section['fields'];
		}
	}
	return $fields;
}

/** The raw text of every field: what was saved, or the default. */
function isp_raw(): array {
	$saved = get_option( ISP_OPTION, array() );
	if ( ! is_array( $saved ) ) {
		$saved = array();
	}
	$defaults = isp_defaults();
	$raw      = array();
	foreach ( isp_fields() as $key => $field ) {
		$raw[ $key ] = array_key_exists( $key, $saved ) ? (string) $saved[ $key ] : (string) ( $defaults[ $key ] ?? '' );
	}
	return $raw;
}

/** Clean what the Site content form (or the REST route) sends. Always receives every field. */
function isp_sanitize_content( $input ): array {
	$input = is_array( $input ) ? $input : array();
	$old   = get_option( ISP_OPTION, array() );
	$old   = is_array( $old ) ? $old : array();
	$out   = array();
	foreach ( isp_fields() as $key => $field ) {
		if ( 'checkbox' === $field['type'] ) {
			$out[ $key ] = empty( $input[ $key ] ) ? '0' : '1';
			continue;
		}
		if ( ! array_key_exists( $key, $input ) ) {
			if ( array_key_exists( $key, $old ) ) {
				$out[ $key ] = (string) $old[ $key ];
			}
			continue;
		}
		$value = is_scalar( $input[ $key ] ) ? (string) $input[ $key ] : '';
		switch ( $field['type'] ) {
			case 'textarea':
			case 'lines':
			case 'paragraphs':
				$out[ $key ] = isp_clean_multiline( $value );
				break;
			case 'email':
				$out[ $key ] = sanitize_email( $value );
				break;
			case 'url':
				$out[ $key ] = esc_url_raw( trim( $value ) );
				break;
			case 'image':
			case 'page':
				$out[ $key ] = (string) absint( $value );
				break;
			default:
				$out[ $key ] = isp_clean_line( $value );
		}
	}
	return $out;
}

/*
 * Plain-text cleaning that keeps what WordPress's sanitize_text_field() would eat:
 * percent-encoded links (linkedin.com/in/j%C3%B6rg) and backslashes. Tags go;
 * everything is escaped again when it is shown.
 */
function isp_clean_line( $value ): string {
	$value = wp_check_invalid_utf8( (string) $value, true );
	$value = wp_strip_all_tags( $value, true );
	return trim( (string) preg_replace( '/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $value ) );
}
function isp_clean_multiline( $value ): string {
	$value = wp_check_invalid_utf8( str_replace( array( "\r\n", "\r" ), "\n", (string) $value ), true );
	$value = wp_strip_all_tags( $value );
	return trim( (string) preg_replace( '/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $value ) );
}

/** Save some fields, keeping the rest. */
function isp_update_content( array $values ): void {
	$raw = array_merge( isp_raw(), array_intersect_key( $values, isp_fields() ) );
	update_option( ISP_OPTION, $raw );
}

add_action(
	'init',
	function () {
		register_setting(
			'isp_content_group',
			ISP_OPTION,
			array(
				'type'              => 'object',
				'sanitize_callback' => 'isp_sanitize_content',
				'default'           => array(),
				'show_in_rest'      => false,
			)
		);
	}
);
add_action( 'add_option_' . ISP_OPTION, 'isp_purge_cache' );
add_action( 'update_option_' . ISP_OPTION, 'isp_purge_cache' );

/* ---------- turning raw text into page data ---------- */

/** A straight ' typed in the admin shows as a curly ’ on the page. */
function isp_smart( string $text ): string {
	return str_replace( "'", "\u{2019}", $text );
}

function isp_set_path( array &$target, string $path, $value ): void {
	$ref = &$target;
	foreach ( explode( '.', $path ) as $segment ) {
		if ( ! isset( $ref[ $segment ] ) || ! is_array( $ref[ $segment ] ) ) {
			$ref[ $segment ] = array();
		}
		$ref = &$ref[ $segment ];
	}
	$ref = $value;
}

/** Parse "a | b | c" lines into rows keyed by the field's columns. */
function isp_parse_lines( string $text, array $cols = array() ): array {
	$rows = array();
	foreach ( preg_split( '/\R/u', $text ) as $line ) {
		$line = trim( $line );
		if ( '' === $line ) {
			continue;
		}
		if ( ! $cols ) {
			$rows[] = isp_smart( $line );
			continue;
		}
		$parts = array_map( 'trim', explode( '|', $line, count( $cols ) ) );
		$row   = array();
		foreach ( array_keys( $cols ) as $i => $col ) {
			$value       = $parts[ $i ] ?? '';
			$row[ $col ] = in_array( $col, array( 'href', 'target' ), true ) ? $value : isp_smart( $value );
		}
		$rows[] = $row;
	}
	return $rows;
}

function isp_parse_field( array $field, string $value ) {
	switch ( $field['type'] ) {
		case 'checkbox':
			return '1' === $value;
		case 'image':
		case 'page':
			return absint( $value );
		case 'email':
		case 'url':
			return trim( $value );
		case 'lines':
			return isp_parse_lines( $value, $field['cols'] ?? array() );
		case 'paragraphs':
			$paragraphs = array_map( 'trim', preg_split( '/\R\s*\R/u', trim( $value ) ) );
			return array_values( array_map( 'isp_smart', array_filter( $paragraphs, 'strlen' ) ) );
		default:
			return isp_smart( trim( $value ) );
	}
}

/** One "Is Prithul ___?" line: where its answer leads. */
function isp_question( array $row ): ?array {
	if ( '' === $row['q'] ) {
		return null;
	}
	$question = array(
		'q' => $row['q'],
		'a' => $row['a'],
	);
	$target   = strtolower( trim( $row['target'] ) );
	if ( '' === $target || 'brief' === $target ) {
		$question['brief'] = true;
		if ( '' !== $row['booked'] ) {
			$question['aBooked'] = $row['booked'];
		}
	} elseif ( in_array( $target, array( 'u1', 'u2', 'u3' ), true ) ) {
		$question['filter'] = $target;
	} else {
		$question['open'] = sanitize_title( $target );
	}
	return $question;
}

/**
 * Everything the homepage shows, in the same shape as the mockup's content.js.
 */
function isp_content(): array {
	static $cache = null;
	if ( null !== $cache ) {
		return $cache;
	}
	$raw     = isp_raw();
	$content = array();
	foreach ( isp_fields() as $key => $field ) {
		isp_set_path( $content, $key, isp_parse_field( $field, $raw[ $key ] ) );
	}

	$content['hero']['questions'] = array_values( array_filter( array_map( 'isp_question', $content['hero']['questions'] ) ) );

	$services = array();
	foreach ( array( 'u1', 'u2', 'u3' ) as $id ) {
		$services[] = array( 'id' => $id ) + $content['services'][ $id ];
		unset( $content['services'][ $id ] );
	}
	$content['services']['list'] = $services;

	$groups = array();
	foreach ( $content['projectsSection']['groups'] as $id => $label ) {
		$groups[] = array(
			'id'    => $id,
			'label' => $label,
		);
	}
	$content['projectsSection']['groups'] = $groups;

	$content['footer']['copyright'] = str_replace( '{year}', wp_date( 'Y' ), $content['footer']['copyright'] );
	$content['about']['photo']      = isp_photo( $content['about']['photo'] );
	$content['projects']            = isp_projects();

	$cache = $content;
	return $content;
}

/** One value by its place on the page, e.g. isp_get( 'hero.title' ). */
function isp_get( string $path, $fallback = '' ) {
	$cur = isp_content();
	foreach ( explode( '.', $path ) as $segment ) {
		if ( ! is_array( $cur ) || ! array_key_exists( $segment, $cur ) ) {
			return $fallback;
		}
		$cur = $cur[ $segment ];
	}
	return $cur;
}

/**
 * A photo as the theme scripts expect it: { url, card, thumb, w, h, alt }.
 * url is at most 1536 px (the viewer), card 768 px (cards), thumb the small square.
 */
function isp_photo( int $id ): ?array {
	if ( ! $id || ! wp_attachment_is_image( $id ) ) {
		return null;
	}
	$full = wp_get_attachment_image_src( $id, 'full' );
	if ( ! $full ) {
		return null;
	}
	$big   = wp_get_attachment_image_src( $id, '1536x1536' );
	$big   = $big ? $big : $full;
	$card  = wp_get_attachment_image_src( $id, 'medium_large' );
	$card  = $card ? $card : $big;
	$thumb = wp_get_attachment_image_src( $id, 'thumbnail' );
	$thumb = $thumb ? $thumb : $card;
	return array(
		'id'    => $id,
		'url'   => $big[0],
		'card'  => $card[0],
		'thumb' => $thumb[0],
		'w'     => (int) $full[1],
		'h'     => (int) $full[2],
		'alt'   => trim( (string) get_post_meta( $id, '_wp_attachment_image_alt', true ) ),
	);
}

/**
 * The data the theme's scripts need, for the page's JSON block.
 * The homepage gets everything; other pages only what the header, menu and form use.
 */
function isp_content_for_script( bool $home ): array {
	$c = isp_content();
	unset( $c['brief']['notify'] );
	if ( ! $home ) {
		return array(
			'site'     => $c['site'],
			'contact'  => $c['contact'],
			'brief'    => $c['brief'],
			'services' => array( 'list' => $c['services']['list'] ),
		);
	}
	foreach ( $c['projects'] as &$project ) {
		foreach ( $project['photos'] as &$photo ) {
			unset( $photo['id'] );
		}
		unset( $photo );
	}
	unset( $project );
	unset( $c['clients'], $c['experience'], $c['about'], $c['footer'] );
	return $c;
}

/* ---------- REST: read and change the texts (administrators only) ---------- */

add_action(
	'rest_api_init',
	function () {
		register_rest_route(
			'isprithul/v1',
			'/content',
			array(
				array(
					'methods'             => 'GET',
					'permission_callback' => fn() => current_user_can( 'manage_options' ),
					'callback'            => fn() => rest_ensure_response( array( 'values' => isp_raw() ) ),
				),
				array(
					'methods'             => 'POST',
					'permission_callback' => fn() => current_user_can( 'manage_options' ),
					'args'                => array(
						'values' => array(
							'type'     => 'object',
							'required' => true,
						),
					),
					'callback'            => function ( WP_REST_Request $request ) {
						$values  = (array) $request->get_param( 'values' );
						$unknown = array_diff( array_keys( $values ), array_keys( isp_fields() ) );
						if ( $unknown ) {
							return new WP_Error( 'isp_unknown_field', 'Unknown field: ' . implode( ', ', $unknown ), array( 'status' => 400 ) );
						}
						isp_update_content( $values );
						return rest_ensure_response( array( 'values' => isp_raw() ) );
					},
				),
			)
		);
	}
);

/* ---------- admin bar: D1 switch + shortcuts ---------- */

add_action(
	'admin_bar_menu',
	function ( WP_Admin_Bar $bar ) {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$on = '1' === isp_raw()['site.available'];
		$bar->add_node(
			array(
				'id'    => 'isp-d1',
				'title' => $on ? '● Taking projects' : '○ Booked',
				'href'  => wp_nonce_url( admin_url( 'admin-post.php?action=isp_toggle_available' ), 'isp_toggle_available' ),
				'meta'  => array( 'title' => $on ? 'Click to show “booked” on the site' : 'Click to show “taking new projects” on the site' ),
			)
		);
		if ( ! is_admin() ) {
			$bar->add_node(
				array(
					'id'    => 'isp-content',
					'title' => 'Site content',
					'href'  => admin_url( 'admin.php?page=isp-content' ),
				)
			);
			$bar->add_node(
				array(
					'parent' => 'isp-content',
					'id'     => 'isp-content-projects',
					'title'  => 'Projects',
					'href'   => admin_url( 'edit.php?post_type=isp_project' ),
				)
			);
			$bar->add_node(
				array(
					'parent' => 'isp-content',
					'id'     => 'isp-content-briefs',
					'title'  => 'Briefs',
					'href'   => admin_url( 'edit.php?post_type=isp_brief' ),
				)
			);
		}
	},
	80
);

add_action(
	'admin_post_isp_toggle_available',
	function () {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( 'Not allowed.', 403 );
		}
		check_admin_referer( 'isp_toggle_available' );
		$on = '1' === isp_raw()['site.available'];
		isp_update_content( array( 'site.available' => $on ? '0' : '1' ) );
		wp_safe_redirect( wp_get_referer() ? wp_get_referer() : admin_url() );
		exit;
	}
);
