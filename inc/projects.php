<?php
/**
 * Projects: one post per project card, edited under Projects in the admin.
 *
 * The order of the list (drag the rows) is the order on the homepage.
 * Only published projects are shown; set one to Draft to hide it.
 *
 * @package isprithul-core
 */

defined( 'ABSPATH' ) || exit;

const ISP_PROJECT = 'isp_project';

/** Meta fields, all plain strings so they are easy to edit through the REST API too. */
function isp_project_meta(): array {
	return array(
		'isp_tagline'    => 'isp_clean_line',
		'isp_type'       => 'isp_clean_line',
		'isp_year'       => 'isp_clean_line',
		'isp_badge'      => 'isp_clean_line',
		'isp_group'      => 'isp_sanitize_group',
		'isp_services'   => 'isp_sanitize_services',
		'isp_summary'    => 'isp_clean_multiline',
		'isp_facts'      => 'isp_clean_multiline',
		'isp_quote_text' => 'isp_clean_multiline',
		'isp_quote_who'  => 'isp_clean_line',
		'isp_links'      => 'isp_clean_multiline',
		'isp_gallery'    => 'isp_sanitize_gallery',
	);
}

function isp_sanitize_group( $value ): string {
	$value = sanitize_key( (string) $value );
	return in_array( $value, array( 'client', 'venture', 'personal' ), true ) ? $value : 'personal';
}

/** "u3, u1" → "u3,u1" (first one = pre-selected in "Start a similar project"). */
function isp_parse_services( string $value ): array {
	$ids = array_map( 'trim', explode( ',', strtolower( $value ) ) );
	return array_values( array_unique( array_intersect( $ids, array( 'u1', 'u2', 'u3' ) ) ) );
}
function isp_sanitize_services( $value ): string {
	return implode( ',', isp_parse_services( is_array( $value ) ? implode( ',', $value ) : (string) $value ) );
}

/** "12, 34, 56" → attachment IDs that are images. */
function isp_parse_ids( string $value ): array {
	$ids = array_filter( array_map( 'absint', explode( ',', $value ) ) );
	return array_values( array_unique( $ids ) );
}
function isp_sanitize_gallery( $value ): string {
	$ids = isp_parse_ids( is_array( $value ) ? implode( ',', $value ) : (string) $value );
	return implode( ',', array_filter( $ids, 'wp_attachment_is_image' ) );
}

/**
 * Published projects in homepage order, shaped like the mockup's content.js.
 */
function isp_projects(): array {
	$posts = get_posts(
		array(
			'post_type'        => ISP_PROJECT,
			'post_status'      => 'publish',
			'numberposts'      => -1,
			'orderby'          => array(
				'menu_order' => 'ASC',
				'title'      => 'ASC',
			),
			'suppress_filters' => false,
		)
	);
	// Load every photo's post and metadata in two queries instead of a few per photo.
	$photo_ids = array();
	foreach ( $posts as $post ) {
		$photo_ids = array_merge( $photo_ids, isp_parse_ids( (string) get_post_meta( $post->ID, 'isp_gallery', true ) ) );
	}
	if ( $photo_ids ) {
		_prime_post_caches( array_unique( $photo_ids ), false, true );
	}
	$out = array();
	foreach ( $posts as $post ) {
		$m     = fn( $key ) => trim( (string) get_post_meta( $post->ID, $key, true ) );
		$facts = array();
		foreach ( preg_split( '/\R/u', $m( 'isp_facts' ) ) as $line ) {
			$line = trim( $line );
			if ( '' === $line ) {
				continue;
			}
			$parts   = array_map( 'trim', explode( '|', $line, 2 ) );
			$facts[] = array( isp_smart( $parts[0] ), isp_smart( $parts[1] ?? '' ) );
		}
		$quote = $m( 'isp_quote_text' );
		$links = array();
		foreach ( isp_parse_lines( $m( 'isp_links' ), array( 'label' => '', 'href' => '' ) ) as $row ) {
			$href = esc_url_raw( $row['href'], array( 'http', 'https' ) );
			if ( '' !== $href ) {
				$links[] = array(
					'label' => '' !== $row['label'] ? $row['label'] : (string) wp_parse_url( $href, PHP_URL_HOST ),
					'href'  => $href,
				);
			}
		}
		$out[] = array(
			'id'       => $post->post_name ? $post->post_name : 'project-' . $post->ID,
			'group'    => isp_sanitize_group( $m( 'isp_group' ) ),
			'title'    => isp_smart( html_entity_decode( $post->post_title, ENT_QUOTES, 'UTF-8' ) ),
			'tagline'  => isp_smart( $m( 'isp_tagline' ) ),
			'type'     => isp_smart( $m( 'isp_type' ) ),
			'year'     => $m( 'isp_year' ),
			'badge'    => isp_smart( $m( 'isp_badge' ) ),
			'services' => isp_parse_services( $m( 'isp_services' ) ),
			'photos'   => array_values( array_filter( array_map( 'isp_photo', isp_parse_ids( $m( 'isp_gallery' ) ) ) ) ),
			'summary'  => isp_smart( $m( 'isp_summary' ) ),
			'facts'    => $facts,
			'links'    => $links,
			'quote'    => '' === $quote ? null : array(
				'text' => isp_smart( $quote ),
				'who'  => isp_smart( $m( 'isp_quote_who' ) ),
			),
		);
	}
	return $out;
}

function isp_register_project_type(): void {
	register_post_type(
		ISP_PROJECT,
		array(
			'labels'              => array(
				'name'               => 'Projects',
				'singular_name'      => 'Project',
				'add_new'            => 'Add project',
				'add_new_item'       => 'Add project',
				'edit_item'          => 'Edit project',
				'new_item'           => 'New project',
				'view_item'          => 'View project',
				'search_items'       => 'Search projects',
				'not_found'          => 'No projects yet. Add one, or import the starter content under Site content.',
				'not_found_in_trash' => 'No projects in the trash.',
				'all_items'          => 'All projects',
				'menu_name'          => 'Projects',
			),
			'description'         => 'Project cards on the homepage.',
			'public'              => false,
			'show_ui'             => true,
			'show_in_menu'        => true,
			'menu_position'       => 3,
			'menu_icon'           => 'dashicons-portfolio',
			'show_in_admin_bar'   => false,
			'show_in_nav_menus'   => false,
			'show_in_rest'        => true,
			'rest_base'           => 'isp_project',
			'exclude_from_search' => true,
			'publicly_queryable'  => false,
			'has_archive'         => false,
			'rewrite'             => false,
			'query_var'           => false,
			'hierarchical'        => false,
			'supports'            => array( 'title', 'page-attributes', 'custom-fields' ),
			'map_meta_cap'        => true,
		)
	);
	foreach ( isp_project_meta() as $key => $sanitize ) {
		register_post_meta(
			ISP_PROJECT,
			$key,
			array(
				'type'              => 'string',
				'single'            => true,
				'default'           => '',
				'show_in_rest'      => true,
				'sanitize_callback' => $sanitize,
				'auth_callback'     => fn() => current_user_can( 'edit_posts' ),
			)
		);
	}
}
add_action( 'init', 'isp_register_project_type' );

/** Classic edit screen (one simple form) instead of the block editor. */
add_filter(
	'use_block_editor_for_post_type',
	fn( $use, $type ) => ISP_PROJECT === $type ? false : $use,
	10,
	2
);

/* ---------- edit screen ---------- */

add_action(
	'add_meta_boxes_' . ISP_PROJECT,
	function () {
		remove_meta_box( 'slugdiv', ISP_PROJECT, 'normal' );
		remove_meta_box( 'postcustom', ISP_PROJECT, 'normal' );
		remove_meta_box( 'pageparentdiv', ISP_PROJECT, 'side' );
		add_meta_box( 'isp-project', 'Project card', 'isp_project_box', ISP_PROJECT, 'normal', 'high' );
		add_meta_box( 'isp-project-photos', 'Photos', 'isp_project_photos_box', ISP_PROJECT, 'normal', 'high' );
	}
);

/** Group labels and service names as set under Site content. */
function isp_group_labels(): array {
	$raw = isp_raw();
	return array(
		'client'   => $raw['projectsSection.groups.client'],
		'venture'  => $raw['projectsSection.groups.venture'],
		'personal' => $raw['projectsSection.groups.personal'],
	);
}
function isp_service_names(): array {
	$raw = isp_raw();
	return array(
		'u1' => $raw['services.u1.name'],
		'u2' => $raw['services.u2.name'],
		'u3' => $raw['services.u3.name'],
	);
}

function isp_project_box( WP_Post $post ): void {
	wp_nonce_field( 'isp_project_save', 'isp_project_nonce' );
	$m        = fn( $key ) => (string) get_post_meta( $post->ID, $key, true );
	$services = isp_parse_services( $m( 'isp_services' ) );
	$group    = $m( 'isp_group' ) ? $m( 'isp_group' ) : 'personal';
	$text     = function ( $key, $label, $help = '' ) use ( $m ) {
		printf(
			'<p class="isp-field"><label for="%1$s">%2$s</label><input type="text" class="widefat" id="%1$s" name="%1$s" value="%3$s">%4$s</p>',
			esc_attr( $key ),
			esc_html( $label ),
			esc_attr( $m( $key ) ),
			$help ? '<span class="description">' . esc_html( $help ) . '</span>' : ''
		);
	};
	$area     = function ( $key, $label, $rows, $help = '' ) use ( $m ) {
		printf(
			'<p class="isp-field"><label for="%1$s">%2$s</label><textarea class="widefat" id="%1$s" name="%1$s" rows="%3$d">%4$s</textarea>%5$s</p>',
			esc_attr( $key ),
			esc_html( $label ),
			(int) $rows,
			esc_textarea( $m( $key ) ),
			$help ? '<span class="description">' . esc_html( $help ) . '</span>' : ''
		);
	};
	echo '<div class="isp-project-fields">';
	$text( 'isp_tagline', 'Tagline', 'One line under the title on the card.' );
	echo '<div class="isp-cols">';
	$text( 'isp_type', 'Type', 'Shown above the title, e.g. Client project.' );
	$text( 'isp_year', 'Year', 'e.g. 2026 or 2022–25.' );
	$text( 'isp_badge', 'Badge', 'Small label on the cover photo. Leave empty for none.' );
	echo '</div>';

	echo '<div class="isp-cols">';
	echo '<p class="isp-field"><label for="isp_group">Filter group</label><select id="isp_group" name="isp_group">';
	foreach ( isp_group_labels() as $id => $label ) {
		printf( '<option value="%s"%s>%s</option>', esc_attr( $id ), selected( $group, $id, false ), esc_html( $label ) );
	}
	echo '</select></p>';

	echo '<fieldset class="isp-field"><legend>Services</legend>';
	foreach ( isp_service_names() as $id => $name ) {
		printf(
			'<label class="isp-inline"><input type="checkbox" name="isp_services_on[]" value="%1$s"%2$s> %3$s <code>%1$s</code></label>',
			esc_attr( $id ),
			checked( in_array( $id, $services, true ), true, false ),
			esc_html( $name )
		);
	}
	echo '</fieldset>';

	echo '<p class="isp-field"><label for="isp_services_main">Main service</label><select id="isp_services_main" name="isp_services_main">';
	foreach ( isp_service_names() as $id => $name ) {
		printf( '<option value="%s"%s>%s</option>', esc_attr( $id ), selected( $services[0] ?? '', $id, false ), esc_html( $name ) );
	}
	echo '</select><span class="description">Pre-selected when someone clicks “Start a similar project”.</span></p>';
	echo '</div>';

	$area( 'isp_summary', 'Summary', 4, 'Shown in the photo viewer.' );
	$area( 'isp_facts', 'Facts', 5, 'One per line: Label | Value, e.g. Client | Private, UAE' );
	$area( 'isp_quote_text', 'Quote (optional)', 3 );
	$text( 'isp_quote_who', 'Quote by', 'e.g. Assaf Pashut, Founder, Chefee Robotics' );
	$area( 'isp_links', 'Links (optional)', 3, 'One per line: Label | Address, e.g. Build guide on Instructables | https://www.instructables.com/… They show in the photo viewer.' );

	printf(
		'<p class="isp-field"><label for="isp_slug">Project ID</label><input type="text" class="widefat code" id="isp_slug" name="post_name" value="%s"><span class="description">Used in links such as <code>#p-%s</code> and in the “Is Prithul ___?” questions. Lowercase letters, numbers and dashes.</span></p>',
		esc_attr( $post->post_name ),
		esc_html( $post->post_name ? $post->post_name : 'project-id' )
	);
	echo '</div>';
}

function isp_project_photos_box( WP_Post $post ): void {
	$ids = isp_parse_ids( (string) get_post_meta( $post->ID, 'isp_gallery', true ) );
	echo '<div class="isp-gallery" data-isp-gallery>';
	printf( '<input type="hidden" name="isp_gallery" value="%s">', esc_attr( implode( ',', $ids ) ) );
	echo '<ul class="isp-gallery__list">';
	foreach ( $ids as $id ) {
		$thumb = wp_get_attachment_image_src( $id, 'thumbnail' );
		if ( ! $thumb ) {
			continue;
		}
		printf(
			'<li data-id="%1$d"><img src="%2$s" alt=""><button type="button" class="isp-gallery__remove" aria-label="Remove this photo from the project">×</button><a class="isp-gallery__edit" href="%3$s" target="_blank" rel="noopener">alt text</a></li>',
			(int) $id,
			esc_url( $thumb[0] ),
			esc_url( get_edit_post_link( $id ) )
		);
	}
	echo '</ul>';
	echo '<p><button type="button" class="button" data-isp-gallery-add>Add photos</button> ';
	echo '<span class="description">Drag to reorder. <strong>The first photo is the cover.</strong> Portrait photos are shown whole on a blurred background. “alt text” opens the photo in the Media Library, where you can describe it for screen readers.</span></p>';
	echo '</div>';
}

add_action(
	'save_post_' . ISP_PROJECT,
	function ( $post_id ) {
		if ( ! isset( $_POST['isp_project_nonce'] ) || ! wp_verify_nonce( sanitize_key( wp_unslash( $_POST['isp_project_nonce'] ) ), 'isp_project_save' ) ) {
			return;
		}
		if ( wp_is_post_autosave( $post_id ) || wp_is_post_revision( $post_id ) || ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}
		// phpcs:disable WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- each value goes through its registered sanitizer below.
		foreach ( isp_project_meta() as $key => $sanitize ) {
			if ( 'isp_services' === $key ) {
				$on   = isset( $_POST['isp_services_on'] ) ? array_map( 'sanitize_key', (array) wp_unslash( $_POST['isp_services_on'] ) ) : array();
				$main = isset( $_POST['isp_services_main'] ) ? sanitize_key( wp_unslash( $_POST['isp_services_main'] ) ) : '';
				$list = $on && in_array( $main, $on, true ) ? array_merge( array( $main ), $on ) : $on;
				update_post_meta( $post_id, $key, isp_sanitize_services( $list ) );
				continue;
			}
			if ( isset( $_POST[ $key ] ) ) {
				// update_post_meta() strips one level of slashes, so hand it a slashed value.
				update_post_meta( $post_id, $key, wp_slash( call_user_func( $sanitize, wp_unslash( $_POST[ $key ] ) ) ) );
			}
		}
		// phpcs:enable
	}
);

/** New projects go to the end of the list. */
add_filter(
	'wp_insert_post_data',
	function ( $data, $postarr ) {
		if ( ISP_PROJECT === $data['post_type'] && empty( $postarr['ID'] ) && empty( $postarr['menu_order'] ) ) {
			global $wpdb;
			$max = $wpdb->get_var( $wpdb->prepare( "SELECT MAX(menu_order) FROM {$wpdb->posts} WHERE post_type = %s AND post_status NOT IN ('auto-draft','trash')", ISP_PROJECT ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$data['menu_order'] = null === $max ? 0 : (int) $max + 1;
		}
		return $data;
	},
	10,
	2
);

/* ---------- list screen: cover, group, services, drag to reorder ---------- */

add_filter(
	'manage_' . ISP_PROJECT . '_posts_columns',
	function ( $cols ) {
		return array(
			'cb'           => $cols['cb'],
			'isp_drag'     => '<span class="screen-reader-text">Order</span>',
			'isp_cover'    => '<span class="screen-reader-text">Cover</span>',
			'title'        => 'Project',
			'isp_group'    => 'Filter group',
			'isp_services' => 'Services',
			'isp_photos'   => 'Photos',
		);
	}
);

add_action(
	'manage_' . ISP_PROJECT . '_posts_custom_column',
	function ( $col, $post_id ) {
		switch ( $col ) {
			case 'isp_drag':
				echo '<span class="isp-drag dashicons dashicons-menu" title="Drag to reorder" aria-hidden="true"></span>';
				break;
			case 'isp_cover':
				$ids = isp_parse_ids( (string) get_post_meta( $post_id, 'isp_gallery', true ) );
				if ( $ids ) {
					echo wp_get_attachment_image( $ids[0], array( 60, 45 ), false, array( 'class' => 'isp-cover' ) );
				}
				break;
			case 'isp_group':
				$labels = isp_group_labels();
				$group  = (string) get_post_meta( $post_id, 'isp_group', true );
				echo esc_html( $labels[ $group ] ?? $labels['personal'] );
				break;
			case 'isp_services':
				$names = isp_service_names();
				echo esc_html( implode( ', ', array_map( fn( $id ) => $names[ $id ], isp_parse_services( (string) get_post_meta( $post_id, 'isp_services', true ) ) ) ) );
				break;
			case 'isp_photos':
				echo (int) count( isp_parse_ids( (string) get_post_meta( $post_id, 'isp_gallery', true ) ) );
				break;
		}
	},
	10,
	2
);

/** The list follows the homepage order unless a column header is clicked. */
add_action(
	'pre_get_posts',
	function ( WP_Query $q ) {
		if ( is_admin() && $q->is_main_query() && ISP_PROJECT === $q->get( 'post_type' ) && ! isset( $_GET['orderby'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$q->set(
				'orderby',
				array(
					'menu_order' => 'ASC',
					'title'      => 'ASC',
				)
			);
		}
	}
);

add_action(
	'admin_notices',
	function () {
		$screen = get_current_screen();
		if ( $screen && 'edit-' . ISP_PROJECT === $screen->id ) {
			echo '<div class="notice notice-info isp-order-note"><p>Drag the rows by <span class="dashicons dashicons-menu" aria-hidden="true"></span> to change the order on the homepage. Only published projects are shown there; set one to Draft to hide it.</p></div>';
		}
	}
);

add_action(
	'wp_ajax_isp_reorder',
	function () {
		check_ajax_referer( 'isp_reorder', 'nonce' );
		if ( ! current_user_can( 'edit_others_posts' ) ) {
			wp_send_json_error( 'Not allowed.', 403 );
		}
		$ids = isset( $_POST['ids'] ) ? array_map( 'absint', (array) wp_unslash( $_POST['ids'] ) ) : array();
		$ids = array_values( array_filter( $ids, fn( $id ) => ISP_PROJECT === get_post_type( $id ) ) );
		// Reuse the positions these rows already had, so reordering a filtered list leaves the others alone.
		$slots = array_map( fn( $id ) => (int) get_post_field( 'menu_order', $id ), $ids );
		sort( $slots );
		if ( count( array_unique( $slots ) ) < count( $slots ) ) {
			$start = $slots ? min( $slots ) : 0;
			$slots = range( $start, $start + count( $ids ) - 1 );
		}
		foreach ( $ids as $i => $id ) {
			if ( (int) get_post_field( 'menu_order', $id ) !== $slots[ $i ] ) {
				wp_update_post(
					array(
						'ID'         => $id,
						'menu_order' => $slots[ $i ],
					)
				);
			}
		}
		isp_purge_cache();
		wp_send_json_success();
	}
);

/* ---------- admin assets ---------- */

add_action(
	'admin_enqueue_scripts',
	function ( $hook ) {
		$screen  = get_current_screen();
		$project = $screen && ISP_PROJECT === $screen->post_type;
		$content = 'toplevel_page_isp-content' === $hook;
		if ( ! $project && ! $content ) {
			return;
		}
		wp_enqueue_style( 'isp-admin', ISP_CORE_URL . 'assets/admin.css', array(), ISP_CORE_VERSION );
		if ( $content || ( $project && 'post' === $screen->base ) ) {
			wp_enqueue_media();
		}
		wp_enqueue_script( 'isp-admin', ISP_CORE_URL . 'assets/admin.js', array( 'jquery', 'jquery-ui-sortable' ), ISP_CORE_VERSION, true );
		wp_localize_script( 'isp-admin', 'ISPAdmin', array( 'reorderNonce' => wp_create_nonce( 'isp_reorder' ) ) );
	}
);

/* Any project change shows on the homepage, so empty the page cache. */
add_action( 'save_post_' . ISP_PROJECT, 'isp_purge_cache', 99 );
add_action(
	'deleted_post',
	function ( $id, $post ) {
		if ( $post && ISP_PROJECT === $post->post_type ) {
			isp_purge_cache();
		}
	},
	10,
	2
);
add_action(
	'transition_post_status',
	function ( $new, $old, $post ) {
		if ( ISP_PROJECT === $post->post_type && $new !== $old ) {
			isp_purge_cache();
		}
	},
	10,
	3
);
/* A deleted photo or a new, changed or removed alt text changes the homepage too. */
add_action( 'delete_attachment', 'isp_purge_cache' );
foreach ( array( 'added_post_meta', 'updated_post_meta', 'deleted_post_meta' ) as $isp_meta_hook ) {
	add_action(
		$isp_meta_hook,
		function ( $meta_id, $object_id, $key ) {
			if ( '_wp_attachment_image_alt' === $key ) {
				isp_purge_cache();
			}
		},
		10,
		3
	);
}
unset( $isp_meta_hook );
