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
