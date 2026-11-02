<?php
/**
 * The Site content screen: every homepage text in one long form.
 *
 * @package isprithul-core
 */

defined( 'ABSPATH' ) || exit;

add_action(
	'admin_menu',
	function () {
		add_menu_page( 'Site content', 'Site content', 'manage_options', 'isp-content', 'isp_render_content_page', 'dashicons-edit-page', 3 );
		add_submenu_page( 'isp-content', 'Site content', 'Homepage texts', 'manage_options', 'isp-content', 'isp_render_content_page' );
	}
);

function isp_render_field( string $key, array $field, string $value ): void {
	$id   = 'isp-' . str_replace( '.', '-', $key );
	$name = ISP_OPTION . '[' . $key . ']';
	switch ( $field['type'] ) {
		case 'checkbox':
			printf(
				'<label for="%1$s"><input type="checkbox" id="%1$s" name="%2$s" value="1"%3$s> %4$s</label>',
				esc_attr( $id ),
				esc_attr( $name ),
				checked( '1', $value, false ),
				esc_html( $field['check'] ?? '' )
			);
			break;
		case 'textarea':
		case 'lines':
		case 'paragraphs':
			$lines = 'textarea' === $field['type'] ? 0 : max( 2, substr_count( $value, "\n" ) + 2 );
			$rows  = max( $field['rows'] ?? 3, min( 14, $lines ) );
			printf(
				'<textarea class="large-text%5$s" id="%1$s" name="%2$s" rows="%3$d">%4$s</textarea>',
				esc_attr( $id ),
				esc_attr( $name ),
				(int) $rows,
				esc_textarea( $value ),
				'lines' === $field['type'] ? ' code' : ''
			);
			break;
		case 'image':
			$thumb = (int) $value ? wp_get_attachment_image_src( (int) $value, 'thumbnail' ) : false;
			printf(
				'<div class="isp-image" data-isp-image><input type="hidden" id="%1$s" name="%2$s" value="%3$s"><span class="isp-image__preview">%4$s</span> <button type="button" class="button" data-isp-image-pick>Choose photo</button> <button type="button" class="button-link isp-image__remove" data-isp-image-remove%5$s>Remove</button></div>',
				esc_attr( $id ),
				esc_attr( $name ),
				esc_attr( (string) (int) $value ),
				$thumb ? '<img src="' . esc_url( $thumb[0] ) . '" alt="">' : '',
				$thumb ? '' : ' hidden'
			);
			break;
		case 'page':
			wp_dropdown_pages(
				array(
					'name'              => esc_attr( $name ),
					'id'                => esc_attr( $id ),
					'selected'          => (int) $value,
					'show_option_none'  => '— none —',
					'option_none_value' => '0',
					'post_status'       => array( 'publish', 'draft', 'private' ),
				)
			);
			break;
		default:
			printf(
				'<input type="%5$s" class="large-text" id="%1$s" name="%2$s" value="%3$s"%4$s>',
				esc_attr( $id ),
				esc_attr( $name ),
				esc_attr( $value ),
				'url' === $field['type'] || 'email' === $field['type'] ? ' spellcheck="false"' : '',
				'email' === $field['type'] ? 'email' : ( 'url' === $field['type'] ? 'url' : 'text' )
			);
	}
	if ( 'lines' === $field['type'] ) {
		$format = empty( $field['cols'] ) ? 'One per line.' : 'One per line: ' . implode( ' | ', $field['cols'] );
		echo '<p class="description">' . esc_html( $format ) . ( empty( $field['help'] ) ? '' : '<br>' . esc_html( $field['help'] ) ) . '</p>';
	} elseif ( 'paragraphs' === $field['type'] ) {
		echo '<p class="description">Leave an empty line between paragraphs.</p>';
	} elseif ( ! empty( $field['help'] ) ) {
		echo '<p class="description">' . esc_html( $field['help'] ) . '</p>';
	}
}

function isp_render_content_page(): void {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}
	$raw    = isp_raw();
	$schema = isp_schema();
	echo '<div class="wrap isp-admin">';
	echo '<h1 class="wp-heading-inline">Site content</h1> <a class="page-title-action" href="' . esc_url( home_url( '/' ) ) . '" target="_blank" rel="noopener">View homepage</a>';
	echo '<hr class="wp-header-end">';
	settings_errors();
	echo '<p class="isp-lead">Every word on the homepage, top to bottom. Projects are edited under <a href="' . esc_url( admin_url( 'edit.php?post_type=isp_project' ) ) . '">Projects</a>. A straight apostrophe (\') shows as a curly one (’) on the site.</p>';

	echo '<nav class="isp-jump" aria-label="Sections">';
	foreach ( $schema as $id => $section ) {
		printf( '<a href="#isp-%s">%s</a>', esc_attr( $id ), esc_html( $section['title'] ) );
	}
	echo '</nav>';

	echo '<form method="post" action="options.php" class="isp-form">';
	settings_fields( 'isp_content_group' );
	foreach ( $schema as $id => $section ) {
		printf( '<section class="isp-section" id="isp-%s"><h2>%s</h2>', esc_attr( $id ), esc_html( $section['title'] ) );
		if ( ! empty( $section['intro'] ) ) {
			echo '<p class="isp-section__intro">' . esc_html( $section['intro'] ) . '</p>';
		}
		echo '<table class="form-table" role="presentation"><tbody>';
		foreach ( $section['fields'] as $key => $field ) {
			$id_attr = 'isp-' . str_replace( '.', '-', $key );
			echo '<tr><th scope="row">';
			if ( in_array( $field['type'], array( 'checkbox', 'image' ), true ) ) {
				echo esc_html( $field['label'] );
			} else {
				printf( '<label for="%s">%s</label>', esc_attr( $id_attr ), esc_html( $field['label'] ) );
			}
			echo '</th><td>';
			isp_render_field( $key, $field, $raw[ $key ] );
			echo '</td></tr>';
		}
		echo '</tbody></table></section>';
	}
	echo '<div class="isp-savebar">';
	submit_button( 'Save changes', 'primary', 'submit', false );
	echo ' <span class="description">Saving also empties the page cache, so the change is live right away.</span></div>';
	echo '</form></div>';
}
