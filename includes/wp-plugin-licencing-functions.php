<?php

/**
 * Get the product (WC) for a licence. If the licence was purchased for a variation, this will return the parent.
 * @param  int $product_or_variation_id 
 * @return post
 */
function wppl_get_licence_product( $product_or_variation_id ) {
	if ( 'product_variation' === get_post_type( $product_or_variation_id ) ) {
		$variation  = get_post( $product_or_variation_id );
		$product_id = $variation->post_parent;
	} else {
		$product_id = $product_or_variation_id;
	}
	return get_post( $product_id );
}

/**
 * Get the download URL for the package
 * @param  int $api_product_post_id
 * @param  string $licence_key
 * @param  string $activation_email
 * @return string
 */
function wppl_get_package_download_url( $api_product_post_id, $licence_key, $activation_email ) {
	return add_query_arg( array(
		'download_api_product' => $api_product_post_id,
		'licence_key'          => $licence_key,
		'activation_email'     => $activation_email
	), home_url() );
}

/**
 * Get the deactivation url for a licence
 * @param  string $activation_id of the activation
 * @param  string $licence_key
 * @param  string $activation_email
 * @return string
 */
function wppl_get_licence_deactivation_url( $activation_id, $licence_key, $activation_email ) {
	return add_query_arg( array(
		'deactivate_licence' => $activation_id,
		'licence_key'        => $licence_key,
		'activation_email'   => $activation_email
	) );
}

/**
 * Get the renew url for a licence
 * @param  string $licence_key
 * @param  string $activation_email
 * @return string
 */
function wppl_get_licence_renewal_url( $licence_key, $activation_email ) {
	return add_query_arg( array(
		'renew_licence'    => $licence_key,
		'activation_email' => $activation_email
	) );
}

/**
 * Get a licence from its key
 * @param  string $licence_key
 * @return object
 */
function wppl_get_licence_from_key( $licence_key ) {
	global $wpdb;

	return $wpdb->get_row( $wpdb->prepare( "
		SELECT * FROM {$wpdb->prefix}wp_plugin_licencing_licences 
		WHERE licence_key = %s
		AND (
			date_expires IS NULL
			OR date_expires > NOW()
		)
	", $licence_key ) );
}

/**
 * Get a licence from its activation_email
 * @param  string $activation_email
 * @return object
 */
function wppl_get_licences_from_activation_email( $activation_email ) {
	global $wpdb;

	return $wpdb->get_results( $wpdb->prepare( "
		SELECT * FROM {$wpdb->prefix}wp_plugin_licencing_licences 
		WHERE activation_email = %s
		AND (
			date_expires IS NULL
			OR date_expires > NOW()
		)
	", $activation_email ) );
}
	
/**
 * Get activations for a given key
 * @param  string  $licence_key 
 * @param  int  $api_product_id 
 * @param  int|null $active
 * @return object
 */
function wppl_get_licence_activations( $licence_key, $api_product_id, $active = null ) {
	global $wpdb;

	if ( is_null( $active ) ) {
		return $wpdb->get_results( $wpdb->prepare( "
			SELECT * FROM {$wpdb->prefix}wp_plugin_licencing_activations
			WHERE licence_key = %s AND api_product_id = %s
		", $licence_key, $api_product_id ) );
	} else {
		return $wpdb->get_results( $wpdb->prepare( "
			SELECT * FROM {$wpdb->prefix}wp_plugin_licencing_activations
			WHERE licence_key = %s AND api_product_id = %s AND activation_active = %d
		", $licence_key, $api_product_id, $active ) );
	}
}

/**
 * Get activations for a given key
 * @param  string  $licence_key 
 * @param  int  $api_product_id 
 * @param  int|null $active
 * @return object
 */
function wppl_is_licence_activated( $licence_key, $api_product_id, $instance ) {
	global $wpdb;

	return $wpdb->get_var( $wpdb->prepare( "
		SELECT activation_id FROM {$wpdb->prefix}wp_plugin_licencing_activations
		WHERE licence_key = %s 
		AND api_product_id = %s
		AND activation_active = 1
		AND instance = %s
	", $licence_key, $api_product_id, $instance ) ) ? true : false;
}

/**
 * Get file path of the package to download
 * @param  int $api_product_post_id
 * @return string
 */
function wppl_get_package_file_path( $api_product_post_id ) {
	return get_post_meta( $api_product_post_id, '_package', true );
}

/**
 * Get API product permissions which a WC product grants
 * @param  int $product_or_variation_id
 * @return array
 */
function wppl_get_licence_api_product_permissions( $product_or_variation_id ) {
	if ( 'product_variation' === get_post_type( $product_or_variation_id ) ) {
		$variation  = get_post( $product_or_variation_id );
		$product_id = $variation->post_parent;
	} else {
		$product_id = $product_or_variation_id;
	}

	return (array) json_decode( get_post_meta( $product_id, '_api_product_permissions', true ) );
}

/**
 * Covert a post name (api product id) into the actual post ID
 * string $api_product_id
 * @return int
 */
function wppl_get_api_product_post_id( $api_product_id ) {
	$api_product = get_page_by_path( $api_product_id, 'object', 'api_product' );
	return isset( $api_product->ID ) ? $api_product->ID : 0;
}
