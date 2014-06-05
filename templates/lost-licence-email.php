<?php
/**
 * Lost licence email
 */
if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

if ( $user_first_name ) {
	echo sprintf( __( "Hello %s,", 'wp-plugin-licencing' ), $user_first_name ) . "\n\n";
} else {
	echo __( "Hi there,", 'wp-plugin-licencing' ) . "\n\n";
}

echo __( "Your licence keys and product download links are listed below.", 'wp-plugin-licencing' ) . "\n\n";

foreach ( $keys as $key ) {
	if ( $api_product_permissions = wppl_get_licence_api_product_permissions( $key->product_id ) ) {
		echo "===============\n";
		foreach ( $api_product_permissions as $api_product_permission ) {
			echo esc_html( get_the_title( $api_product_permission ) ) . "\n";
			echo sprintf( __( 'Key: %s', 'wp-plugin-licencing' ), $key->licence_key ) . "\n";
			if ( $key->activation_limit ) {
				echo sprintf( __( 'Activation limit: %s', 'wp-plugin-licencing' ), absint( $key->activation_limit ) ) . "\n";
			}
			if ( $key->date_expires ) {
				echo sprintf( __( 'Expiry date: %s', 'wp-plugin-licencing' ), date_i18n( get_option( 'date_format' ), strtotime( $key->date_expires ) ) ) . "\n";
			}
			echo sprintf( __( 'Download link: %s', 'wp-plugin-licencing' ), wppl_get_package_download_url( $api_product_permission, $key->licence_key, $key->activation_email ) ) . "\n";
			echo "===============\n";
		}
		echo "\n";
	}
}

echo sprintf( __( "You can input these keys from your WordPress dashboard in the plugins section. Find the plugin in the list and enter the key and your activation email. The activation email in your case will be %s.", 'wp-plugin-licencing' ), $activation_email ) . "\n\n";

echo sprintf( __( 'Once activated, you will be able to update your plugins normally through the dashboard like any other plugin. If you ever want to de-activate a licence you can de-activate the plugin, or do so from your account page on the %s website (if you have an account).', 'wp-plugin-licencing' ), $blogname ) . "\n\n";

echo __( "Thanks!", 'wp-plugin-licencing' ) . "\n";

echo "--\n";

echo apply_filters( 'woocommerce_email_footer_text', get_option( 'woocommerce_email_footer_text' ) );