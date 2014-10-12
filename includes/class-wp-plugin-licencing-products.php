<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * WP_Plugin_Licencing_Products class.
 */
class WP_Plugin_Licencing_Products {
		
	/**
	 * Constructor
	 */
	public function __construct() {
		add_filter( 'product_type_options', array( $this, 'product_type_options' ) );
		
		add_action( 'woocommerce_product_options_general_product_data', array( $this, 'licence_data' ) );
		add_filter( 'woocommerce_process_product_meta', array( $this, 'save_licence_data' ) );
		
		add_action( 'woocommerce_product_after_variable_attributes', array( $this, 'variable_licence_data' ), 10, 3 );
		add_action( 'woocommerce_save_product_variation', array( $this, 'save_variable_licence_data' ), 10, 2 );
	}

	/**
	 * Product type options
	 */
    public function product_type_options( $options ) {
	    $options['is_api_product_licence'] = array( 
			'id'            => '_is_api_product_licence', 
			'wrapper_class' => 'show_if_simple show_if_variable', 
			'label'         => __( 'API Product Licence', 'wp-plugin-licencing' ), 
			'description'   => __( 'Enable this option if this is a licence for an API Product', 'wp-plugin-licencing' ) 
		);
		return $options;
    }

	/**
	 * adds the panel to the product interface
	 */
	public function licence_data() {
		global $post;
		$post_id              = $post->ID;
		$current_api_products = (array) json_decode( get_post_meta( $post->ID, '_api_product_permissions', true ) );
		$api_products         = get_posts( array(
			'numberposts' => -1,
			'orderby'     => 'title',
			'post_type'   => 'api_product',
			'post_status' => array( 'publish' ),
		) );
		include( 'views/html-licence-data.php' );	
	}

	/**
	 * add the panel for variations
	 */
	public function variable_licence_data( $loop, $variation_data, $variation ) {
		global $post, $thepostid;
		include( 'views/html-variation-licence-data.php' );	
	}

	/**
	 * Save data
	 */
	public function save_licence_data() {
		global $post;

		if ( ! empty( $_POST['_is_api_product_licence'] ) ) {
			update_post_meta( $post->ID, '_is_api_product_licence', 'yes' );
		} else {
			update_post_meta( $post->ID, '_is_api_product_licence', 'no' );
		}

		update_post_meta( $post->ID, '_api_product_permissions', json_encode( array_map( 'absint', (array) ( isset( $_POST['api_product_permissions'] ) ? $_POST['api_product_permissions'] : array() ) ) ) );
		update_post_meta( $post->ID, '_licence_activation_limit', sanitize_text_field( $_POST['_licence_activation_limit'] ) );
		update_post_meta( $post->ID, '_licence_expiry_days', sanitize_text_field( $_POST['_licence_expiry_days'] ) );
	}

	/**
	 * Save variation data
	 */
	public function save_variable_licence_data( $variation_id, $i ) {
		$variation_licence_activation_limit = $_POST['_variation_licence_activation_limit'];
		$variation_licence_expiry_days      = $_POST['_variation_licence_expiry_days'];

		update_post_meta( $variation_id, '_licence_activation_limit', sanitize_text_field( $variation_licence_activation_limit[ $i ] ) );
		update_post_meta( $variation_id, '_licence_expiry_days', sanitize_text_field( $variation_licence_expiry_days[ $i ] ) );
	}

}

new WP_Plugin_Licencing_Products();