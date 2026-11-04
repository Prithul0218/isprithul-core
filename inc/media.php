<?php
/**
 * Every photo uploaded to the Media Library loses its camera data (EXIF), GPS
 * location, XMP and IPTC before WordPress stores it. JPEGs are cleaned without
 * re-compressing them; colour profiles stay. A photo the camera saved sideways
 * is turned upright first, so it still shows the right way round.
 *
 * @package isprithul-core
 */

defined( 'ABSPATH' ) || exit;

add_filter(
	'wp_handle_upload',
	function ( $upload ) {
		if ( empty( $upload['error'] ) && ! empty( $upload['file'] ) && is_file( $upload['file'] ) ) {
			$type = (string) ( $upload['type'] ?? '' );
			if ( 'image/jpeg' === $type ) {
				isp_strip_jpeg( $upload['file'] );
			} elseif ( 'image/png' === $type ) {
				isp_strip_png( $upload['file'] );
			}
		}
		return $upload;
	}
);

/** EXIF orientation (1–8) of a JPEG, 1 when unknown. */
function isp_jpeg_orientation( string $data ): int {
	$pos = strpos( $data, "Exif\0\0" );
	if ( false === $pos || $pos > 65536 ) {
		return 1;
	}
	$tiff = substr( $data, $pos + 6, 65536 );
	$le   = 'II' === substr( $tiff, 0, 2 );
	$u16  = fn( $o ) => strlen( $tiff ) >= $o + 2 ? unpack( $le ? 'v' : 'n', substr( $tiff, $o, 2 ) )[1] : 0;
	$u32  = fn( $o ) => strlen( $tiff ) >= $o + 4 ? unpack( $le ? 'V' : 'N', substr( $tiff, $o, 4 ) )[1] : 0;
	$ifd  = $u32( 4 );
	$n    = $u16( $ifd );
	for ( $i = 0; $i < $n && $i < 200; $i++ ) {
		$entry = $ifd + 2 + $i * 12;
		if ( 0x0112 === $u16( $entry ) ) {
			$value = $u16( $entry + 8 );
			return $value >= 1 && $value <= 8 ? $value : 1;
		}
	}
	return 1;
}

function isp_strip_jpeg( string $file ): bool {
	$data = file_get_contents( $file ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
	if ( ! is_string( $data ) || "\xFF\xD8" !== substr( $data, 0, 2 ) ) {
		return false;
	}
	if ( isp_jpeg_orientation( $data ) > 1 ) {
		// Turn it upright while the rotation is still known; if that can't be done, keep the file as it is.
		$editor = wp_get_image_editor( $file );
		if ( is_wp_error( $editor ) || true !== $editor->maybe_exif_rotate() || is_wp_error( $editor->save( $file, 'image/jpeg' ) ) ) {
			return false;
		}
		$data = file_get_contents( $file ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
		if ( ! is_string( $data ) ) {
			return false;
		}
	}
	$out = "\xFF\xD8";
	$i   = 2;
	$len = strlen( $data );
	while ( $i + 4 <= $len ) {
		if ( "\xFF" !== $data[ $i ] ) {
			return false; // not a marker where one should be: leave the file alone
		}
		$marker = ord( $data[ $i + 1 ] );
		if ( 0xFF === $marker ) {
			++$i; // fill byte
			continue;
		}
		if ( 0xDA === $marker ) {
			$out .= substr( $data, $i ); // start of the image data: copy the rest as it is
			return false !== file_put_contents( $file, $out ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
		}
		if ( 0x01 === $marker || ( $marker >= 0xD0 && $marker <= 0xD7 ) ) {
			$out .= substr( $data, $i, 2 );
			$i   += 2;
			continue;
		}
		$size = unpack( 'n', substr( $data, $i + 2, 2 ) )[1];
		// Drop APP1 (EXIF with GPS, XMP), APP13 (IPTC) and comments; keep the rest (ICC colour profile, tables).
		if ( ! in_array( $marker, array( 0xE1, 0xED, 0xFE ), true ) ) {
			$out .= substr( $data, $i, $size + 2 );
		}
		$i += $size + 2;
	}
	return false;
}

function isp_strip_png( string $file ): bool {
	$data = file_get_contents( $file ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
	if ( ! is_string( $data ) || "\x89PNG\r\n\x1a\n" !== substr( $data, 0, 8 ) ) {
		return false;
	}
	$out = substr( $data, 0, 8 );
	$i   = 8;
	$len = strlen( $data );
	while ( $i + 12 <= $len ) {
		$size = unpack( 'N', substr( $data, $i, 4 ) )[1];
		$type = substr( $data, $i + 4, 4 );
		if ( $i + 12 + $size > $len ) {
			return false;
		}
		if ( ! in_array( $type, array( 'eXIf', 'tEXt', 'zTXt', 'iTXt', 'tIME' ), true ) ) {
			$out .= substr( $data, $i, 12 + $size );
		}
		$i += 12 + $size;
		if ( 'IEND' === $type ) {
			return false !== file_put_contents( $file, $out ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
		}
	}
	return false;
}
