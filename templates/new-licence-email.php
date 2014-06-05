<?php
/**
 * New licence email
 */
if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

if ( $user_first_name ) {
	echo sprintf( __( "Hello %s,", 'wp-plugin-licencing' ), $user_first_name ) . "\n\n";
} else {
	echo __( "Hi there,", 'wp-plugin-licencing' ) . "\n\n";
}
_e( "A licence key has just been generated for you. The details are as follows:", 'wp-plugin-licencing' );
echo "\n";

if ( $api_product_permissions = wppl_get_licence_api_product_permissions( $key->product_id ) ) {
	foreach ( $api_product_permissions as $api_product_permission ) {
		echo "\n====================\n";
		echo esc_html( get_the_title( $api_product_permission ) ) . ': ' . wppl_get_package_download_url( $api_product_permission, $key->licence_key, $key->activation_email ) . "\n";
		echo $key->licence_key . "";
		echo "\n====================\n\n";
	}
}

_e( "You can input this licence on the plugins page within your WordPress dashboard.", 'wp-plugin-licencing' );
echo "\n";
echo "\n";

// Footer
echo '--' . "\n";
echo apply_filters( 'woocommerce_email_footer_text', get_option( 'woocommerce_email_footer_text' ) );