<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function wenetwork_social_log( $context, $message ) {
	if ( ! defined( 'WP_DEBUG' ) || ! WP_DEBUG ) {
		return;
	}
	error_log( sprintf( '[wenetwork-social][%s] %s', $context, is_string( $message ) ? $message : wp_json_encode( $message ) ) );
}
