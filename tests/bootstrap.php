<?php
/**
 * Test bootstrap.
 *
 * Stands in for the handful of WordPress functions this package uses.
 * They are the real implementations' behaviour, not no-ops: the point of most
 * of these tests is that output is escaped, and a stub that returned its input
 * unchanged would make every one of them pass regardless.
 *
 * @package SugarCommerce\Breadcrumbs
 */

declare( strict_types=1 );

define( 'ABSPATH', '/srv/www/' );
define( 'WP_CONTENT_DIR', '/srv/www/wp-content' );

// Styles registered and enqueued are recorded rather than performed, so a
// test can ask what the trail asked WordPress for.
$GLOBALS['bc_registered'] = [];
$GLOBALS['bc_enqueued']   = [];

function wp_register_style( $handle, $src, $deps = [], $ver = false, $media = 'all' ) {
	$GLOBALS['bc_registered'][ $handle ] = [
		'src'  => $src,
		'deps' => $deps,
		'ver'  => $ver,
	];

	return true;
}

function wp_style_is( $handle, $status = 'enqueued' ) {
	return 'registered' === $status ? isset( $GLOBALS['bc_registered'][ $handle ] ) : in_array( $handle, $GLOBALS['bc_enqueued'], true );
}

function wp_enqueue_style( $handle, ...$args ) {
	$GLOBALS['bc_enqueued'][] = $handle;
}

function wp_normalize_path( $path ) {
	return (string) preg_replace( '#/+#', '/', str_replace( '\\', '/', (string) $path ) );
}

function site_url( $path = '' ) {
	return 'https://example.test' . $path;
}

function content_url( $path = '' ) {
	return 'https://example.test/wp-content' . $path;
}

require_once __DIR__ . '/../vendor/autoload.php';

if ( ! function_exists( 'esc_attr' ) ) {
	function esc_attr( $text ): string {
		return htmlspecialchars( (string) $text, ENT_QUOTES, 'UTF-8' );
	}
}

if ( ! function_exists( 'esc_html' ) ) {
	function esc_html( $text ): string {
		return htmlspecialchars( (string) $text, ENT_QUOTES, 'UTF-8' );
	}
}

if ( ! function_exists( 'esc_url' ) ) {
	function esc_url( $url ): string {
		$url = trim( (string) $url );

		// WordPress drops anything whose scheme is not allowed, which is what
		// stops javascript: and data: URLs reaching an href.
		if ( preg_match( '#^\s*(javascript|data|vbscript)\s*:#i', $url ) ) {
			return '';
		}

		return htmlspecialchars( $url, ENT_QUOTES, 'UTF-8' );
	}
}
