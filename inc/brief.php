<?php
/**
 * The "Start a project" form: saves each brief and emails it.
 *
 * POST /wp-json/isprithul/v1/brief  { name, email, what, type, when, budget, website, elapsed }
 * No nonce on purpose: the homepage is served from the page cache, where a nonce
 * would go stale. Spam is held off instead by a hidden field, a minimum fill-in
 * time the theme's script always reports, 5 briefs an hour per connection (per /64
 * for IPv6) and 30 an hour for the whole site.
 *
 * @package isprithul-core
 */

defined( 'ABSPATH' ) || exit;

const ISP_BRIEF = 'isp_brief';

function isp_register_brief_type(): void {
	register_post_type(
		ISP_BRIEF,
		array(
			'labels'              => array(
				'name'          => 'Briefs',
				'singular_name' => 'Brief',
				'edit_item'     => 'Brief',
				'search_items'  => 'Search briefs',
				'not_found'     => 'No briefs yet. They arrive from the “Start a project” form on the homepage.',
				'all_items'     => 'Briefs',
				'menu_name'     => 'Briefs',
			),
			'public'              => false,
			'show_ui'             => true,
			'show_in_menu'        => false,
			'show_in_rest'        => false,
			'exclude_from_search' => true,
			'publicly_queryable'  => false,
			'rewrite'             => false,
			'query_var'           => false,
			'supports'            => array( 'title' ),
			'capability_type'     => 'post',
			'capabilities'        => array( 'create_posts' => 'do_not_allow' ),
			'map_meta_cap'        => true,
		)
	);
}
add_action( 'init', 'isp_register_brief_type' );

add_action(
	'admin_menu',
	function () {
		add_menu_page( 'Briefs', 'Briefs', 'edit_others_posts', 'edit.php?post_type=' . ISP_BRIEF, '', 'dashicons-email-alt', 3 );
	}
);

add_action(
	'rest_api_init',
	function () {
		register_rest_route(
			'isprithul/v1',
			'/brief',
			array(
				'methods'             => 'POST',
				'permission_callback' => '__return_true',
				'callback'            => 'isp_receive_brief',
			)
		);
		// The latest briefs and whether their email went out (administrators only), for checks.
		register_rest_route(
			'isprithul/v1',
			'/briefs',
			array(
				'methods'             => 'GET',
				'permission_callback' => fn() => current_user_can( 'manage_options' ),
				'callback'            => function () {
					$out = array();
					foreach ( get_posts( array( 'post_type' => ISP_BRIEF, 'post_status' => 'private', 'numberposts' => 10 ) ) as $brief ) {
						$out[] = array(
							'id'       => $brief->ID,
							'received' => get_post_time( 'c', true, $brief ),
							'title'    => $brief->post_title,
							'email'    => (string) get_post_meta( $brief->ID, 'isp_email', true ),
							'mailed'   => '1' === get_post_meta( $brief->ID, 'isp_mailed', true ),
						);
					}
					return rest_ensure_response( $out );
				},
			)
		);
	}
);

/** Who is sending: the IP address, or its /64 network for IPv6 (one connection gets a whole /64). */
function isp_brief_client_key(): string {
	$ip = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';
	if ( filter_var( $ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6 ) ) {
		$packed = inet_pton( $ip );
		if ( false !== $packed ) {
			return 'v6:' . bin2hex( substr( $packed, 0, 8 ) );
		}
	}
	return $ip;
}

function isp_receive_brief( WP_REST_Request $request ) {
	$p   = $request->get_json_params();
	$p   = is_array( $p ) ? $p : $request->get_body_params();
	$get = fn( $key, $max ) => mb_substr( trim( is_scalar( $p[ $key ] ?? '' ) ? (string) ( $p[ $key ] ?? '' ) : '' ), 0, $max );

	// Bots: the hidden "website" field is filled in, or the form wasn't open for a few
	// seconds (the theme's script always sends how long it was open). They get a calm "ok".
	$elapsed = (int) ( $p['elapsed'] ?? 0 );
	if ( '' !== $get( 'website', 200 ) || $elapsed < 2500 ) {
		return rest_ensure_response( array( 'ok' => true ) );
	}

	$limit = 'isp_brief_' . md5( isp_brief_client_key() . wp_salt( 'nonce' ) );
	$count = (int) get_transient( $limit );
	$total = (int) get_transient( 'isp_brief_all' );
	if ( $count >= 5 || $total >= 30 ) {
		return new WP_Error( 'isp_too_many', 'Too many briefs right now. Please email me instead.', array( 'status' => 429 ) );
	}

	$name   = isp_clean_line( $get( 'name', 120 ) );
	$email  = sanitize_email( $get( 'email', 200 ) );
	$what   = isp_clean_multiline( $get( 'what', 6000 ) );
	$type   = isp_clean_line( $get( 'type', 120 ) );
	$when   = isp_clean_line( $get( 'when', 120 ) );
	$budget = isp_clean_line( $get( 'budget', 200 ) );
	if ( '' === $name || ! is_email( $email ) || '' === $what ) {
		return new WP_Error( 'isp_incomplete', 'Please add your name, a valid email and a few words about the project.', array( 'status' => 400 ) );
	}
	set_transient( $limit, $count + 1, HOUR_IN_SECONDS );
	set_transient( 'isp_brief_all', $total + 1, HOUR_IN_SECONDS );

	// wp_insert_post() expects slashed data (it strips one level), so backslashes survive.
	$post_id = wp_insert_post(
		wp_slash(
			array(
				'post_type'   => ISP_BRIEF,
				'post_status' => 'private',
				'post_title'  => $name . ' · ' . wp_trim_words( $what, 8, '…' ),
				'meta_input'  => array(
					'isp_name'   => $name,
					'isp_email'  => $email,
					'isp_what'   => $what,
					'isp_type'   => $type,
					'isp_when'   => $when,
					'isp_budget' => $budget,
				),
			)
		),
		true
	);
	if ( is_wp_error( $post_id ) ) {
		return new WP_Error( 'isp_save_failed', 'The brief could not be saved.', array( 'status' => 500 ) );
	}

	$raw  = isp_raw();
	$to   = is_email( $raw['brief.notify'] ) ? $raw['brief.notify'] : ( is_email( $raw['site.email'] ) ? $raw['site.email'] : get_option( 'admin_email' ) );
	$safe = trim( str_replace( array( '"', '<', '>', ',', ';', "\r", "\n" ), ' ', $name ) );
	$body = implode(
		"\n",
		array(
			'Name:     ' . $name,
			'Email:    ' . $email,
			'Service:  ' . $type,
			'Timeline: ' . $when,
			'Budget:   ' . ( '' === $budget ? '–' : $budget ),
			'',
			$what,
			'',
			'—',
			'Sent from the Start a project form on ' . wp_parse_url( home_url(), PHP_URL_HOST ) . '. Reply to this email to answer ' . $safe . ' directly.',
			'All briefs: ' . admin_url( 'edit.php?post_type=' . ISP_BRIEF ),
		)
	);
	$sent = wp_mail( $to, 'New project brief from ' . $safe, $body, array( 'Reply-To: ' . $safe . ' <' . $email . '>' ) );
	update_post_meta( $post_id, 'isp_mailed', $sent ? '1' : '0' );

	return rest_ensure_response( array( 'ok' => true ) );
}

/* ---------- reading briefs in the admin ---------- */

add_filter(
	'manage_' . ISP_BRIEF . '_posts_columns',
	fn( $cols ) => array(
		'cb'         => $cols['cb'],
		'title'      => 'Brief',
		'isp_email'  => 'Email',
		'isp_type'   => 'Service',
		'isp_when'   => 'Timeline',
		'isp_mailed' => 'Emailed',
		'date'       => 'Received',
	)
);

add_action(
	'manage_' . ISP_BRIEF . '_posts_custom_column',
	function ( $col, $post_id ) {
		$value = (string) get_post_meta( $post_id, $col, true );
		if ( 'isp_email' === $col ) {
			printf( '<a href="mailto:%1$s">%2$s</a>', esc_attr( $value ), esc_html( $value ) );
		} elseif ( 'isp_mailed' === $col ) {
			echo '1' === $value ? 'Yes' : '<strong>No</strong> (email failed; the brief is saved here)';
		} else {
			echo esc_html( $value );
		}
	},
	10,
	2
);

add_action(
	'add_meta_boxes_' . ISP_BRIEF,
	function () {
		remove_meta_box( 'submitdiv', ISP_BRIEF, 'side' );
		add_meta_box(
			'isp-brief',
			'Brief',
			function ( WP_Post $post ) {
				$m     = fn( $key ) => (string) get_post_meta( $post->ID, $key, true );
				$rows  = array(
					'Name'     => $m( 'isp_name' ),
					'Email'    => $m( 'isp_email' ),
					'Service'  => $m( 'isp_type' ),
					'Timeline' => $m( 'isp_when' ),
					'Budget'   => $m( 'isp_budget' ),
					'Received' => get_the_date( 'j F Y, H:i', $post ),
				);
				echo '<table class="form-table" role="presentation"><tbody>';
				foreach ( $rows as $label => $value ) {
					printf( '<tr><th scope="row">%s</th><td>%s</td></tr>', esc_html( $label ), esc_html( '' === $value ? '–' : $value ) );
				}
				echo '</tbody></table>';
				echo '<div class="isp-brief-text">' . nl2br( esc_html( $m( 'isp_what' ) ) ) . '</div>';
				$subject = 'Re: your project brief';
				printf(
					'<p><a class="button button-primary" href="mailto:%1$s?subject=%2$s">Reply by email</a> <a class="button-link-delete" href="%3$s">Move to trash</a></p>',
					esc_attr( $m( 'isp_email' ) ),
					rawurlencode( $subject ),
					esc_url( get_delete_post_link( $post->ID ) )
				);
			},
			ISP_BRIEF,
			'normal',
			'high'
		);
	}
);

/* Briefs are read-only: hide the title box. */
add_action(
	'admin_head',
	function () {
		$screen = get_current_screen();
		if ( $screen && ISP_BRIEF === $screen->post_type && 'post' === $screen->base ) {
			echo '<style>#titlediv{display:none}.isp-brief-text{white-space:normal;font-size:14px;line-height:1.6;padding:12px 14px;background:#f6f7f7;border-left:4px solid #2271b1;margin:8px 0 16px}</style>';
		}
	}
);
