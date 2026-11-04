<?php
/**
 * Lockdown: closes the doors this site doesn't use.
 *
 * - XML-RPC is off (pingbacks, the old remote-publishing API and a favourite
 *   target for password guessing). The MCP connector and Git Updater use the
 *   REST API instead.
 * - Your login name stays private: the user list in the REST API is for logged-in
 *   editors only, ?author=1 and /author/... go to the homepage, oEmbed and the
 *   sitemap no longer name the author.
 * - Login: one vague error message for every failure, and 5 wrong passwords from
 *   one connection lock it out for 15 minutes.
 * - No version numbers: the WordPress version is gone from the page head and feeds,
 *   and every ?ver= on styles and scripts is replaced by a short code that still
 *   changes when the file does.
 * - Comments are off everywhere (the site has none, and the form only drew spam).
 * - Browser safety headers on every page, and the code editors in wp-admin are off.
 * - Server rules (.htaccess): readme and changelog files that list versions, and
 *   xmlrpc.php, answer 403; folders never list their files. The rules are tested
 *   right after they are written and taken out again if the site stops answering.
 *
 * @package isprithul-core
 */

defined( 'ABSPATH' ) || exit;

/* ---------- XML-RPC ---------- */

if ( defined( 'XMLRPC_REQUEST' ) && XMLRPC_REQUEST ) {
	add_action(
		'plugins_loaded',
		function () {
			status_header( 403 );
			header( 'Content-Type: text/plain; charset=utf-8' );
			echo 'XML-RPC is turned off on this site.';
			exit;
		},
		0
	);
}
add_filter( 'xmlrpc_enabled', '__return_false' );
add_filter( 'xmlrpc_methods', fn() => array() );
add_filter( 'pings_open', '__return_false' );
add_filter(
	'wp_headers',
	function ( $headers ) {
		unset( $headers['X-Pingback'] );
		return $headers;
	}
);
remove_action( 'wp_head', 'rsd_link' );

/* ---------- user names ---------- */

add_action(
	'template_redirect',
	function () {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only check.
		if ( ! is_user_logged_in() && ( is_author() || isset( $_GET['author'] ) ) ) {
			wp_safe_redirect( home_url( '/' ), 301 );
			exit;
		}
	},
	1
);

add_filter(
	'rest_pre_dispatch',
	function ( $result, $server, WP_REST_Request $request ) {
		if ( str_starts_with( $request->get_route(), '/wp/v2/users' ) && ! current_user_can( 'edit_posts' ) ) {
			return new WP_Error( 'rest_forbidden', 'Sorry, you are not allowed to list users.', array( 'status' => rest_authorization_required_code() ) );
		}
		return $result;
	},
	10,
	3
);

add_filter(
	'oembed_response_data',
	function ( $data ) {
		unset( $data['author_name'], $data['author_url'] );
		return $data;
	}
);

/* ---------- login ---------- */

function isp_login_key(): string {
	return 'isp_login_' . md5( isp_brief_client_key() . wp_salt( 'auth' ) );
}

add_filter(
	'authenticate',
	function ( $user, $username, $password ) {
		if ( '' === (string) $username && '' === (string) $password ) {
			return $user;
		}
		if ( (int) get_transient( isp_login_key() ) >= 5 ) {
			return new WP_Error( 'isp_locked', '<strong>Too many failed logins.</strong> Please try again in 15 minutes.' );
		}
		return $user;
	},
	99,
	3
);

add_action(
	'wp_login_failed',
	function () {
		$key = isp_login_key();
		set_transient( $key, (int) get_transient( $key ) + 1, 15 * MINUTE_IN_SECONDS );
	}
);

add_action(
	'wp_login',
	function () {
		delete_transient( isp_login_key() );
	}
);

/* The same message whether the user name or the password was wrong. */
add_filter(
	'login_errors',
	function ( $message ) {
		global $errors;
		if ( is_wp_error( $errors ) && array( 'isp_locked' ) === $errors->get_error_codes() ) {
			return $message;
		}
		if ( is_wp_error( $errors ) && array_intersect( $errors->get_error_codes(), array( 'invalid_username', 'invalid_email', 'incorrect_password', 'empty_username', 'empty_password' ) ) ) {
			return '<strong>Error:</strong> The login details are not right.';
		}
		return $message;
	}
);

/* ---------- no version numbers ---------- */

/** "?ver=7.1.4" becomes "?ver=3fa9c1d2": still changes on every update, says nothing. */
function isp_hide_ver( $src ) {
	static $salt = null;
	if ( ! is_string( $src ) || false === strpos( $src, 'ver=' ) || is_admin() ) {
		return $src;
	}
	$query = (string) wp_parse_url( $src, PHP_URL_QUERY );
	parse_str( $query, $args );
	if ( empty( $args['ver'] ) || ! is_string( $args['ver'] ) ) {
		return $src;
	}
	$salt = $salt ?? wp_salt( 'nonce' );
	return add_query_arg( 'ver', substr( md5( $args['ver'] . '|' . $salt ), 0, 8 ), $src );
}
add_filter( 'style_loader_src', 'isp_hide_ver', 9999 );
add_filter( 'script_loader_src', 'isp_hide_ver', 9999 );
// The login page bundles its files into one load-styles.php?ver=<WordPress version> link; load them one by one instead.
add_action(
	'login_init',
	function () {
		$GLOBALS['concatenate_scripts'] = false; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
	}
);

/* The theme and plugin code editors in wp-admin: off, so a stolen login can't rewrite PHP. */
if ( ! defined( 'DISALLOW_FILE_EDIT' ) ) {
	define( 'DISALLOW_FILE_EDIT', true );
}

/* ---------- comments: off ---------- */

add_filter( 'comments_open', '__return_false', 20 );
add_filter( 'pings_open', '__return_false', 20 );
add_filter( 'comments_array', '__return_empty_array', 20 );
add_filter( 'feed_links_show_comments_feed', '__return_false' );
add_action(
	'pre_comment_on_post',
	function () {
		wp_die( 'Comments are turned off on this site.', 'Comments are off', array( 'response' => 403 ) );
	}
);
add_action(
	'template_redirect',
	function () {
		if ( is_comment_feed() ) {
			wp_safe_redirect( home_url( '/' ), 301 );
			exit;
		}
	},
	1
);
add_filter(
	'rest_pre_dispatch',
	function ( $result, $server, WP_REST_Request $request ) {
		if ( str_starts_with( $request->get_route(), '/wp/v2/comments' ) && ! current_user_can( 'moderate_comments' ) ) {
			return new WP_Error( 'rest_forbidden', 'Comments are turned off on this site.', array( 'status' => rest_authorization_required_code() ) );
		}
		return $result;
	},
	10,
	3
);
add_action(
	'admin_bar_menu',
	fn( $bar ) => $bar->remove_node( 'comments' ),
	999
);

/* ---------- browser safety headers ---------- */

function isp_security_headers(): void {
	if ( headers_sent() ) {
		return;
	}
	header_remove( 'X-Powered-By' );
	header( 'X-Content-Type-Options: nosniff' );
	header( 'X-Frame-Options: SAMEORIGIN' );
	header( 'Referrer-Policy: strict-origin-when-cross-origin' );
	header( 'Permissions-Policy: camera=(), microphone=(), geolocation=(), payment=(), usb=()' );
	if ( is_ssl() ) {
		header( 'Strict-Transport-Security: max-age=31536000' );
	}
}
add_action( 'send_headers', 'isp_security_headers' );
add_action( 'login_init', 'isp_security_headers' );
add_action( 'admin_init', 'isp_security_headers' );
add_filter(
	'rest_pre_serve_request',
	function ( $served ) {
		isp_security_headers();
		return $served;
	}
);

/* ---------- server rules (.htaccess) ---------- */

const ISP_HTACCESS_MARKER = 'isprithul core: security';

function isp_htaccess_rules(): array {
	return array(
		'# Files that only tell visitors which versions run here, and the old XML-RPC door.',
		'<FilesMatch "^(readme\.html|license\.txt|wp-config-sample\.php|readme\.txt|changelog\.txt|changelog\.md|readme\.md|xmlrpc\.php)$">',
		'Require all denied',
		'</FilesMatch>',
		'# Folders never list their files.',
		'Options -Indexes',
		'<IfModule mod_headers.c>',
		'Header always set X-Content-Type-Options "nosniff"',
		'Header always set X-Frame-Options "SAMEORIGIN"',
		'Header always set Referrer-Policy "strict-origin-when-cross-origin"',
		'Header always set Permissions-Policy "camera=(), microphone=(), geolocation=(), payment=(), usb=()"',
		'Header always set Strict-Transport-Security "max-age=31536000"',
		'Header always unset X-Powered-By',
		'Header unset X-Powered-By',
		'</IfModule>',
	);
}

function isp_htaccess_file(): string {
	require_once ABSPATH . 'wp-admin/includes/file.php';
	return get_home_path() . '.htaccess';
}

function isp_htaccess_write( array $lines ): bool {
	require_once ABSPATH . 'wp-admin/includes/misc.php';
	$file = isp_htaccess_file();
	if ( ! file_exists( $file ) || ! wp_is_writable( $file ) ) {
		return false;
	}
	return (bool) insert_with_markers( $file, ISP_HTACCESS_MARKER, $lines );
}

/**
 * Add the rules, then check the homepage still answers. If it doesn't, the rules come
 * straight out again, so a server that refuses one of them can't take the site down.
 */
function isp_htaccess_apply(): array {
	$status = array( 'time' => time(), 'applied' => false, 'note' => '' );
	if ( ! apply_filters( 'isp_harden_htaccess', true ) ) {
		$status['note'] = 'turned off by the isp_harden_htaccess filter';
	} elseif ( ! isp_htaccess_write( isp_htaccess_rules() ) ) {
		$status['note'] = '.htaccess is missing or not writable';
	} else {
		$home = wp_remote_get( add_query_arg( 'isp-check', time(), home_url( '/' ) ), array( 'timeout' => 15, 'redirection' => 3 ) );
		$code = is_wp_error( $home ) ? 0 : (int) wp_remote_retrieve_response_code( $home );
		if ( 200 !== $code ) {
			isp_htaccess_write( array() );
			$status['note'] = 'homepage answered ' . ( $code ? $code : $home->get_error_message() ) . ' with the rules, so they were taken out again';
		} else {
			$readme          = wp_remote_head( site_url( '/readme.html' ), array( 'timeout' => 10, 'redirection' => 0 ) );
			$status['applied'] = true;
			$status['readme']  = is_wp_error( $readme ) ? 0 : (int) wp_remote_retrieve_response_code( $readme );
		}
	}
	update_option( 'isp_htaccess_status', $status, false );
	return $status;
}

register_deactivation_hook( ISP_CORE_DIR . 'isprithul-core.php', fn() => isp_htaccess_write( array() ) );

/* What's in place, for checks (administrators only). */
add_action(
	'rest_api_init',
	function () {
		register_rest_route(
			'isprithul/v1',
			'/security',
			array(
				array(
					'methods'             => 'GET',
					'permission_callback' => fn() => current_user_can( 'manage_options' ),
					'callback'            => fn() => array(
						'version'      => ISP_CORE_VERSION,
						'htaccess'     => get_option( 'isp_htaccess_status', null ),
						'file_edit'    => defined( 'DISALLOW_FILE_EDIT' ) && DISALLOW_FILE_EDIT,
						'comments'     => (int) wp_count_comments()->total_comments,
						'users'        => count_users()['avail_roles'] ?? array(),
					),
				),
				array(
					'methods'             => 'POST',
					'permission_callback' => fn() => current_user_can( 'manage_options' ),
					'callback'            => fn() => isp_htaccess_apply(),
				),
			)
		);
	}
);
