<?php
/**
 * Lost licence email
 */
if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

_e( "Hello there,", 'wp-plugin-licencing' );
echo "\n";
echo "\n";
_e( "Your licence keys are as follows:", 'wp-plugin-licencing' );
echo "\n";

foreach ( $keys as $key ) {
	if ( $api_product_permissions = wppl_get_licence_api_product_permissions( $key->product_id ) ) {
		echo "\n====================\n";
		foreach ( $api_product_permissions as $api_product_permission ) {
			echo esc_html( get_the_title( $api_product_permission ) ) . ': ' . wppl_get_package_download_url( $api_product_permission, $key->licence_key, $key->activation_email ) . "\n";
			echo $key->licence_key . "";
		}
		echo "\n====================\n\n";
	}
}

_e( "You can input these on the plugins page within your WordPress dashboard.", 'wp-plugin-licencing' );
echo "\n";
echo "\n";

// Footer
echo '--' . "\n";
echo apply_filters( 'woocommerce_email_footer_text', get_option( 'woocommerce_email_footer_text' ) );