<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * WP_Plugin_Licencing_Orders
 */
class WP_Plugin_Licencing_Orders {

	/**
	 * Constructor
	 */
	public function __construct() {
		add_action( 'woocommerce_order_actions_end', array( $this, 'keys_link' ) );
		add_action( 'woocommerce_order_status_completed', array( $this, 'order_completed' ) );
		add_action( 'woocommerce_order_status_cancelled', array( $this, 'order_cancelled' ) );
		add_action( 'delete_post', array( $this, 'delete_post' ) );
		add_action( 'woocommerce_email_before_order_table', array( $this, 'email_keys' ) );
		add_action( 'woocommerce_thankyou', array( $this, 'email_keys' ), 5 );
		add_action( 'woocommerce_before_my_account', array( $this, 'my_keys' ) );
	}

	/**
	 * Link to keys on order edit page
	 */
	public function keys_link( $order_id ) {
		if ( get_post_meta( $order_id, 'has_api_product_licence_keys', true ) ) {
			?>
			<li class="wide">
				<a href="<?php echo admin_url( 'admin.php?page=wp_plugin_licencing_licences&order_id=' . $order_id ); ?>"><?php _e( 'View licence keys &rarr;', 'wp-plugin-licencing' ); ?></a>
			</li>
			<?php 
		}
	}

	/**
	 * save_licence_key function.
	 *
	 * @return int
	 */
	public function save_licence_key( $data ) {
		global $wpdb;

		$defaults = array(
			'order_id'         => '',
			'activation_email' => '',
			'user_id'          => '',
			'licence_key'      => $this->generate_licence_key(),
			'product_id'       => '',
			'activation_limit' => '',
			'date_expires'     => '',
			'date_created'     => current_time( 'mysql' )
		);

		$data = wp_parse_args( $data, $defaults  );

		$insert = array(
			'order_id'         => $data['order_id'],
			'activation_email' => $data['activation_email'],
			'licence_key'      => $data['licence_key'],
			'product_id'       => $data['product_id'],
			'user_id'          => $data['user_id'],
			'activation_limit' => $data['activation_limit'],
			'date_expires'     => $data['date_expires'],
			'date_created'     => $data['date_created']
        );

        $wpdb->insert( $wpdb->prefix . 'wp_plugin_licencing_licences', $insert );

		return $data['licence_key'];
	}

	/**
	 * generates a unique id that is used as the license code
	 *
	 * @since 1.0
	 * @return string the unique ID
	 */
	public function generate_licence_key() {
		return apply_filters( 'wp_plugin_licencing_generate_licence_key', strtoupper( sprintf(
			'%04x-%04x-%04x-%04x',
			mt_rand( 0, 0xffff ), mt_rand( 0, 0xffff ), mt_rand( 0, 0xffff ),
			mt_rand( 0, 0x0fff ) | 0x4000,
			mt_rand( 0, 0x3fff ) | 0x8000,
			mt_rand( 0, 0xffff ), mt_rand( 0, 0xffff ), mt_rand( 0, 0xffff )
		) ) );
	}

	/**
	 * Generate codes
	 */
	public function order_completed( $order_id ) {
		global $wpdb;

		if ( get_post_meta( $order_id, 'has_api_product_licence_keys', true ) ) {
			return; // Only do this once
		}

		$order   = new WC_Order( $order_id );
		$has_key = false;

		if ( sizeof( $order->get_items() ) > 0 ) {
			foreach ( $order->get_items() as $item ) {
				$product = $order->get_product_from_item( $item );

				if ( 'yes' === get_post_meta( $product->id, '_is_api_product_licence', true ) ) {

					if ( ! $product->variation_id || ( ! $activation_limit = get_post_meta( $product->variation_id, '_licence_activation_limit', true ) ) ) {
						$activation_limit    = get_post_meta( $product->id, '_licence_activation_limit', true );
					}
					if ( ! $product->variation_id || ( ! $licence_expiry_days = get_post_meta( $product->variation_id, '_licence_expiry_days', true ) ) ) {
						$licence_expiry_days = get_post_meta( $product->id, '_licence_expiry_days', true );
					}

					// Renewal?
					$_renewing_key = false;

					foreach ( $item['item_meta'] as $meta_key => $meta_value ) {
						if ( $meta_key == '_renewing_key' ) {
			            	$_renewing_key = $meta_value[0];
						}
					}
					
					if ( $_renewing_key ) {
						// Update old key
						$wpdb->update( "{$wpdb->prefix}wp_plugin_licencing_licences", 
							array( 
								'order_id'         => $order_id,
								'activation_limit' => $activation_limit,
								'activation_email' => $order->billing_email,
								'user_id'          => $order->customer_user,
								'date_expires'     => ! empty( $licence_expiry_days ) ? date( "Y-m-d H:i:s", strtotime( "+{$licence_expiry_days} days", current_time( 'timestamp' ) ) ) : '',
							), 
							array( 
								'licence_key' => $_renewing_key
							)
						);
					} else {
						// Generate new keys
						for ( $i = 0; $i < absint( $item['qty'] ); $i ++ ) {
							// Generate a licence key
							$data = array(
								'order_id'         => $order_id,
								'activation_email' => $order->billing_email,
								'user_id'          => $order->customer_user,
								'product_id'       => $product->variation_id ? $product->variation_id : $product->id,
								'activation_limit' => $activation_limit,
								'date_expires'     => ! empty( $licence_expiry_days ) ? date( "Y-m-d H:i:s", strtotime( "+{$licence_expiry_days} days", current_time( 'timestamp' ) ) ) : '',
					        );

							$licence_id = $this->save_licence_key( $data );
						}
					}

					$has_key = true;
				}
			}
		}
		if ( $has_key ) {
			update_post_meta( $order_id,  'has_api_product_licence_keys', 1 );
		}
	}

	/**
	 * On order cancellation
	 */
	public function order_cancelled( $order_id ) {
		if ( $order_id > 0 ) {
			delete_post_meta( $order_id,  'has_api_product_licence_keys' );
			$wpdb->delete( "{$wpdb->prefix}wp_plugin_licencing_licences", array( 'order_id' => $order_id ) );
			$wpdb->delete( "{$wpdb->prefix}wp_plugin_licencing_activations", array( 'order_id' => $order_id ) );
		}
	}

	/**
	 * On delete post
	 */
	public function delete_post( $id ) {
		if ( ! current_user_can( 'delete_posts' ) ) {
			return;
		}

		if ( $id > 0 ) {
			$post_type = get_post_type( $id );
			if ( 'shop_order' === $post_type ) {
				$wpdb->delete( "{$wpdb->prefix}wp_plugin_licencing_licences", array( 'order_id' => $id ) );
				$wpdb->delete( "{$wpdb->prefix}wp_plugin_licencing_activations", array( 'order_id' => $id ) );
			}
		}
	}

	/**
	 * email_keys function.
	 *
	 * @access public
	 * @return void
	 */
	public function email_keys( $order ) {
		global $wpdb;

		if ( ! is_object( $order ) ) {
			$order = new WC_Order( $order );
		}

		$licence_keys = $wpdb->get_results( $wpdb->prepare( "
			SELECT * FROM {$wpdb->prefix}wp_plugin_licencing_licences
			WHERE order_id = %d
		", $order->id ) );

		if ( $licence_keys ) {
			woocommerce_get_template( 'email-keys.php', array( 'keys' => $licence_keys ), 'wp-plugin-licencing', WP_PLUGIN_LICENCING_PLUGIN_DIR . '/templates/' );
		}
	}

	/**
	 * Output keys on the my account page
	 */
	public function my_keys() {
		global $wpdb;

		$current_user = wp_get_current_user();

		$licence_keys = $wpdb->get_results( $wpdb->prepare( "
			SELECT * FROM {$wpdb->prefix}wp_plugin_licencing_licences
			WHERE activation_email = %s OR user_id = %d
		", $current_user->user_email, get_current_user_id() ) );

		wc_get_template( 'my-licences.php', array( 'keys' => $licence_keys ), 'wp-plugin-licencing', WP_PLUGIN_LICENCING_PLUGIN_DIR . '/templates/' );
	}	
}

new WP_Plugin_Licencing_Orders();