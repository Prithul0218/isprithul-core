<?php
/**
 * Plugin Name:       isprithul core
 * Plugin URI:        https://github.com/Prithul0218/isprithul-core
 * Description:       Projects, the editable homepage texts (Site content), the "Start a project" form and a one-time starter-content import for isprithul.me. Pairs with the isprithul theme.
 * Version:           1.1.0
 * Requires at least: 6.5
 * Requires PHP:      8.0
 * Author:            Iqbal Samin Prithul
 * Author URI:        https://isprithul.me
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       isprithul-core
 * GitHub Plugin URI: https://github.com/Prithul0218/isprithul-core
 * Primary Branch:    main
 */

defined( 'ABSPATH' ) || exit;

define( 'ISP_CORE_VERSION', '1.1.0' );
define( 'ISP_CORE_FILE', __FILE__ );
define( 'ISP_CORE_DIR', plugin_dir_path( __FILE__ ) );
define( 'ISP_CORE_URL', plugin_dir_url( __FILE__ ) );

require ISP_CORE_DIR . 'inc/content.php';
require ISP_CORE_DIR . 'inc/projects.php';
require ISP_CORE_DIR . 'inc/settings-page.php';
require ISP_CORE_DIR . 'inc/brief.php';
require ISP_CORE_DIR . 'inc/importer.php';
require ISP_CORE_DIR . 'inc/seo.php';
require ISP_CORE_DIR . 'inc/security.php';
require ISP_CORE_DIR . 'inc/media.php';

/**
 * Empty the LiteSpeed page cache so visitors see a change right away. Every page
 * carries the header, footer and form texts, and the homepage the projects.
 * ('*' empties the page cache only: no object cache, OPcache or admin notice.)
 */
function isp_purge_cache() {
	do_action( 'litespeed_purge', '*' );
}

/**
 * Once per new version: refresh the URL rules (so /wp-sitemap.xml works) and
 * add the 1.1 project links where a project has none yet.
 */
add_action(
	'init',
	function () {
		if ( get_option( 'isp_core_version' ) === ISP_CORE_VERSION ) {
			return;
		}
		$links = array(
			'rc-glider' => 'Build guide on Instructables | https://www.instructables.com/Design-and-Build-Your-Own-3D-Printed-RC-Plane/',
			'wattmeter' => "Build guide on Instructables | https://www.instructables.com/Designing-an-ESP32-Based-Wattmeter-a-Beginners-Gui/\nSource on GitHub | https://github.com/Prithul0218/Precision-Wattmeter-ESP32-S3",
		);
		foreach ( $links as $slug => $value ) {
			$found = get_posts( array( 'post_type' => ISP_PROJECT, 'name' => $slug, 'post_status' => 'any', 'numberposts' => 1, 'fields' => 'ids' ) );
			if ( $found && '' === (string) get_post_meta( $found[0], 'isp_links', true ) ) {
				update_post_meta( $found[0], 'isp_links', $value );
			}
		}
		flush_rewrite_rules( false );
		update_option( 'isp_core_version', ISP_CORE_VERSION );
		isp_purge_cache();
	},
	99
);

register_activation_hook(
	__FILE__,
	function () {
		isp_register_project_type();
		isp_register_brief_type();
	}
);

/** "Settings" style shortcuts on the Plugins screen. */
add_filter(
	'plugin_action_links_' . plugin_basename( __FILE__ ),
	function ( $links ) {
		array_unshift(
			$links,
			'<a href="' . esc_url( admin_url( 'admin.php?page=isp-content' ) ) . '">Site content</a>',
			'<a href="' . esc_url( admin_url( 'admin.php?page=isp-import' ) ) . '">Starter content</a>'
		);
		return $links;
	}
);
