<?php
/**
 * Starter content: creates the 13 projects from the homepage mockup and
 * downloads their photos (and the About portrait) into the Media Library.
 *
 * Safe to run more than once: it skips projects and photos that already exist,
 * and never changes a project you have edited. Photos come from the media
 * branch of the plugin's GitHub repository (EXIF/GPS removed, max 1600 px).
 *
 * Runs in short steps (Site content › Starter content, or
 * POST /wp-json/isprithul/v1/import) so slow hosting never times out.
 *
 * @package isprithul-core
 */

defined( 'ABSPATH' ) || exit;

function isp_starter_data(): array {
	$json = file_get_contents( ISP_CORE_DIR . 'data/starter.json' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
	$data = json_decode( (string) $json, true );
	return is_array( $data ) ? $data : array(
		'projects'   => array(),
		'media_base' => '',
	);
}

/** Photos already imported: file name => attachment ID. */
function isp_imported_photos(): array {
	global $wpdb;
	$rows = $wpdb->get_results( "SELECT pm.meta_value AS file, pm.post_id AS id FROM {$wpdb->postmeta} pm JOIN {$wpdb->posts} p ON p.ID = pm.post_id WHERE pm.meta_key = '_isp_import_file' AND p.post_type = 'attachment'" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
	$map  = array();
	foreach ( $rows as $row ) {
		$map[ $row->file ] = (int) $row->id;
	}
	return $map;
}

function isp_find_project( string $slug ): int {
	$ids = get_posts(
		array(
			'post_type'   => ISP_PROJECT,
			'post_status' => array( 'publish', 'draft', 'pending', 'private', 'future' ),
			'numberposts' => 1,
			'fields'      => 'ids',
			'meta_key'    => '_isp_import_id', // phpcs:ignore WordPress.DB.SlowDBQuery
			'meta_value'  => $slug, // phpcs:ignore WordPress.DB.SlowDBQuery
		)
	);
	if ( $ids ) {
		return (int) $ids[0];
	}
	$ids = get_posts(
		array(
			'post_type'   => ISP_PROJECT,
			'post_status' => array( 'publish', 'draft', 'pending', 'private', 'future' ),
			'numberposts' => 1,
			'fields'      => 'ids',
			'name'        => $slug,
		)
	);
	return $ids ? (int) $ids[0] : 0;
}

/** Where the import stands, without changing anything. */
function isp_import_status(): array {
	$data     = isp_starter_data();
	$have     = isp_imported_photos();
	$total    = 0;
	$done     = 0;
	$projects = 0;
	foreach ( $data['projects'] as $p ) {
		$projects += isp_find_project( $p['id'] ) ? 1 : 0;
		foreach ( $p['photos'] as $photo ) {
			++$total;
			$done += isset( $have[ $photo['file'] ] ) ? 1 : 0;
		}
	}
	if ( ! empty( $data['about_photo'] ) ) {
		++$total;
		$done += isset( $have[ $data['about_photo']['file'] ] ) ? 1 : 0;
	}
	return array(
		'projects'       => $projects,
		'projects_total' => count( $data['projects'] ),
		'photos'         => $done,
		'photos_total'   => $total,
		'done'           => $projects === count( $data['projects'] ) && $done === $total && ! isp_pending_galleries(),
	);
}

function isp_pending_galleries(): array {
	return get_posts(
		array(
			'post_type'   => ISP_PROJECT,
			'post_status' => 'any',
			'numberposts' => -1,
			'fields'      => 'ids',
			'meta_key'    => '_isp_import_gallery', // phpcs:ignore WordPress.DB.SlowDBQuery
			'meta_value'  => '1', // phpcs:ignore WordPress.DB.SlowDBQuery
		)
	);
}

function isp_sideload_photo( string $url, string $file, string $alt, int $parent ) {
	require_once ABSPATH . 'wp-admin/includes/file.php';
	require_once ABSPATH . 'wp-admin/includes/media.php';
	require_once ABSPATH . 'wp-admin/includes/image.php';
	$tmp = download_url( $url, 15 );
	if ( is_wp_error( $tmp ) ) {
		return $tmp;
	}
	// The import marker is saved together with the attachment, so a step that is cut
	// off halfway never leaves a photo that the next step would download again.
	$id = media_handle_sideload(
		array(
			'name'     => $file,
			'tmp_name' => $tmp,
		),
		$parent,
		null,
		wp_slash(
			array(
				'post_title'   => $alt,
				'post_excerpt' => '',
				'meta_input'   => array(
					'_isp_import_file'         => $file,
					'_wp_attachment_image_alt' => $alt,
				),
			)
		)
	);
	if ( is_wp_error( $id ) ) {
		wp_delete_file( $tmp );
		return $id;
	}
	return $id;
}

/**
 * One step of the import: create missing projects, then download photos for about
 * 8 seconds (each download may take up to 15 more), so a step stays well inside a
 * 30-second PHP time limit. Call again until "done" is true.
 */
function isp_import_step(): array {
	$start = microtime( true );
	if ( function_exists( 'set_time_limit' ) ) {
		@set_time_limit( 120 ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- some hosts forbid it; the step is short either way.
	}
	$data  = isp_starter_data();
	$log   = array();

	// 1. Projects (only the missing ones; existing ones are never touched).
	foreach ( $data['projects'] as $i => $p ) {
		if ( isp_find_project( $p['id'] ) ) {
			continue;
		}
		$id = wp_insert_post(
			wp_slash( array(
				'post_type'   => ISP_PROJECT,
				'post_status' => 'publish',
				'post_title'  => $p['title'],
				'post_name'   => $p['id'],
				'menu_order'  => $i + 1,
				'meta_input'  => array(
					'isp_tagline'        => $p['tagline'],
					'isp_type'           => $p['type'],
					'isp_year'           => $p['year'],
					'isp_badge'          => $p['badge'],
					'isp_group'          => $p['group'],
					'isp_services'       => $p['services'],
					'isp_summary'        => $p['summary'],
					'isp_facts'          => $p['facts'],
					'isp_quote_text'     => $p['quote_text'],
					'isp_quote_who'      => $p['quote_who'],
					'isp_links'          => $p['links'] ?? '',
					'_isp_import_id'     => $p['id'],
					'_isp_import_gallery' => '1',
				),
			) ),
			true
		);
		$log[] = is_wp_error( $id ) ? 'Could not create ' . $p['title'] . ': ' . $id->get_error_message() : 'Created project: ' . $p['title'];
	}

	// 2. Photos, a few per step.
	$have   = isp_imported_photos();
	$failed = 0;
	$queue  = array();
	foreach ( $data['projects'] as $p ) {
		foreach ( $p['photos'] as $photo ) {
			$queue[] = $photo + array( 'project' => $p['id'] );
		}
	}
	if ( ! empty( $data['about_photo'] ) ) {
		$queue[] = $data['about_photo'] + array( 'project' => '' );
	}
	foreach ( $queue as $photo ) {
		if ( isset( $have[ $photo['file'] ] ) ) {
			continue;
		}
		if ( microtime( true ) - $start > 8 ) {
			break;
		}
		$parent = $photo['project'] ? isp_find_project( $photo['project'] ) : 0;
		$id     = isp_sideload_photo( $data['media_base'] . rawurlencode( $photo['file'] ), $photo['file'], $photo['alt'], $parent );
		if ( is_wp_error( $id ) ) {
			++$failed;
			$log[] = 'Photo ' . $photo['file'] . ' failed: ' . $id->get_error_message();
			continue;
		}
		$have[ $photo['file'] ] = $id;
		$log[]                  = 'Added photo ' . $photo['file'];
	}

	// 2b. Once every photo is in: finish any whose smaller sizes were cut off by a time limit.
	if ( count( $have ) >= count( $queue ) ) {
		require_once ABSPATH . 'wp-admin/includes/image.php';
		update_meta_cache( 'post', array_values( $have ) );
		foreach ( $have as $file => $id ) {
			if ( microtime( true ) - $start > 8 ) {
				break;
			}
			if ( wp_get_missing_image_subsizes( $id ) ) {
				wp_update_image_subsizes( $id );
				$log[] = 'Finished the smaller sizes of ' . $file;
			}
		}
	}

	// 3. Galleries, once all of a project's photos are in.
	foreach ( $data['projects'] as $p ) {
		$post_id = isp_find_project( $p['id'] );
		if ( ! $post_id || '1' !== get_post_meta( $post_id, '_isp_import_gallery', true ) ) {
			continue;
		}
		$ids = array();
		foreach ( $p['photos'] as $photo ) {
			if ( ! isset( $have[ $photo['file'] ] ) ) {
				continue 2;
			}
			$ids[] = $have[ $photo['file'] ];
		}
		update_post_meta( $post_id, 'isp_gallery', implode( ',', $ids ) );
		delete_post_meta( $post_id, '_isp_import_gallery' );
		$log[] = 'Photos set for ' . $p['title'];
	}

	// 4. The About portrait, unless one is already chosen.
	if ( ! empty( $data['about_photo'] ) && isset( $have[ $data['about_photo']['file'] ] ) && ! (int) isp_raw()['about.photo'] ) {
		isp_update_content( array( 'about.photo' => (string) $have[ $data['about_photo']['file'] ] ) );
		$log[] = 'Portrait set under Site content › About';
	}

	// 5. The footer's Legal link, if a page with the slug "legal" exists.
	if ( ! (int) isp_raw()['footer.legalPage'] ) {
		$legal = get_page_by_path( 'legal' );
		if ( $legal ) {
			isp_update_content( array( 'footer.legalPage' => (string) $legal->ID ) );
			$log[] = 'Footer Legal link set to the page “' . $legal->post_title . '”';
		}
	}

	isp_purge_cache();
	return array_merge( isp_import_status(), array( 'failed' => $failed, 'log' => $log ) );
}

add_action(
	'rest_api_init',
	function () {
		register_rest_route(
			'isprithul/v1',
			'/import',
			array(
				array(
					'methods'             => 'GET',
					'permission_callback' => fn() => current_user_can( 'manage_options' ),
					'callback'            => fn() => rest_ensure_response( isp_import_status() ),
				),
				array(
					'methods'             => 'POST',
					'permission_callback' => fn() => current_user_can( 'manage_options' ),
					'callback'            => fn() => rest_ensure_response( isp_import_step() ),
				),
			)
		);
	}
);

add_action(
	'admin_menu',
	function () {
		$hook = add_submenu_page( 'isp-content', 'Starter content', 'Starter content', 'manage_options', 'isp-import', 'isp_render_import_page' );
		add_action(
			'admin_print_scripts-' . $hook,
			function () {
				wp_enqueue_style( 'isp-admin', ISP_CORE_URL . 'assets/admin.css', array(), ISP_CORE_VERSION );
				wp_enqueue_script( 'isp-import', ISP_CORE_URL . 'assets/import.js', array( 'wp-api-fetch' ), ISP_CORE_VERSION, true );
			}
		);
	}
);

function isp_render_import_page(): void {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}
	$s = isp_import_status();
	echo '<div class="wrap isp-admin"><h1>Starter content</h1>';
	echo '<p class="isp-lead">Creates the 13 projects from the homepage design and downloads their photos (and your About portrait) into the Media Library. Afterwards everything is yours to edit under <a href="' . esc_url( admin_url( 'edit.php?post_type=isp_project' ) ) . '">Projects</a>.</p>';
	echo '<ul class="isp-import-facts">';
	printf( '<li>Projects: <strong data-isp-projects>%d</strong> of %d</li>', (int) $s['projects'], (int) $s['projects_total'] );
	printf( '<li>Photos: <strong data-isp-photos>%d</strong> of %d</li>', (int) $s['photos'], (int) $s['photos_total'] );
	echo '</ul>';
	echo '<p>It is safe to run again: anything that already exists is skipped, and projects you have edited are never changed. The photos come from the <code>media</code> branch of the plugin’s GitHub repository, with camera and location data removed.</p>';
	printf(
		'<p><button type="button" class="button button-primary" data-isp-import%s>%s</button></p>',
		$s['done'] ? ' disabled' : '',
		$s['done'] ? 'Everything is imported' : 'Import starter content'
	);
	echo '<progress class="isp-import-progress" max="' . (int) $s['photos_total'] . '" value="' . (int) $s['photos'] . '" data-isp-progress></progress>';
	echo '<pre class="isp-import-log" data-isp-log hidden></pre>';
	echo '</div>';
}
