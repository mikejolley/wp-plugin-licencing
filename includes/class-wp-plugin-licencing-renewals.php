<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * WP_Plugin_Licencing_Renewals
 */
class WP_Plugin_Licencing_Renewals {

	/**
	 * Constructor
	 */
	public function __construct() {
		add_action( 'wp', array( $this, 'renew_handler' ) );
		add_filter( 'woocommerce_add_cart_item', array( $this, 'add_cart_item' ), 10, 1 );
		add_filter( 'woocommerce_get_cart_item_from_session', array( $this, 'get_cart_item_from_session' ), 10, 2 );
		add_action( 'woocommerce_add_order_item_meta', array( $this, 'order_item_meta' ), 10, 2 );
	}

	/**
	 * Handle a renewal url
	 */
	public function renew_handler() {
		if ( ! empty( $_GET['renew_licence'] ) && is_user_logged_in() ) {
			global $wpdb;

			$licence_key = sanitize_text_field( $_GET['renew_licence'] );
			$licence     = $wpdb->get_row( $wpdb->prepare( "
				SELECT * FROM {$wpdb->prefix}wp_plugin_licencing_licences 
				WHERE licence_key = %s
				AND user_id = %d OR user_id = 0
			", $licence_key, get_current_user_id() ) );

			// Renewable?
			if ( ! $licence ) {
				wc_add_notice( __( 'Invalid licence', 'wp-plugin-licencing' ), 'error' );
				return;
			}
			if ( ! $licence->date_expires || strtotime( $licence->date_expires ) > current_time( 'timestamp' ) ) {
				wc_add_notice( __( 'This licence does not need to be renewed yet', 'wp-plugin-licencing' ), 'notice' );
				return;
			}

			// Purchasable?
			$product = get_product( $licence->product_id );

			if ( ! $product->is_purchasable() ) {
				wc_add_notice( __( 'This product can no longer be purchased', 'wp-plugin-licencing' ), 'error' );
				return;
			}

			// Add to cart
			WC()->cart->empty_cart();
			WC()->cart->add_to_cart( $licence->product_id, 1, '', '', array(
				'renewing_key' => $licence_key
			) );

			// Message
			wc_add_notice( sprintf( __( 'The product has been added to your cart with a %d%% discount.', 'wp-plugin-licencing' ), apply_filters( 'wp_plugin_licencing_renewal_discount_percent', 30 ) ), 'success' );

			// Redirect to checkout
			wp_redirect( get_permalink( wc_get_page_id( 'checkout' ) ) );
			exit;
		}
	}

	/**
	 * Change price in cart to discount the upgrade
	 */
	public function add_cart_item( $cart_item ) {
		if ( isset( $cart_item['renewing_key'] ) ) {
			$price            = $cart_item['data']->get_price();
			$discount         = ( $price / 100 ) * apply_filters( 'wp_plugin_licencing_renewal_discount_percent', 30 );
			$discounted_price = $price - $discount;
			
			$cart_item['data']->set_price( $discounted_price );
			$cart_item['data']->get_post_data();
			$cart_item['data']->post->post_title .= ' (' . __( 'Renewal', 'wp-plugin-licencing' ) . ')';
		}
		return $cart_item;
	}

	/**
	 * get_cart_item_from_session function.
	 */
	public function get_cart_item_from_session( $cart_item, $values ) {
		if ( isset( $values['renewing_key'] ) ) {
			$price            = $cart_item['data']->get_price();
			$discount         = ( $price / 100 ) * apply_filters( 'wp_plugin_licencing_renewal_discount_percent', 30 );
			$discounted_price = $price - $discount;
			
			$cart_item['data']->set_price( $discounted_price );
			$cart_item['data']->get_post_data();
			$cart_item['data']->post->post_title .= ' (' . __( 'Renewal', 'wp-plugin-licencing' ) . ')';

			$cart_item['renewing_key'] = $values['renewing_key'];
		}
		return $cart_item;
	}

	/**
	 * order_item_meta function for storing the meta in the order line items
	 */
	public function order_item_meta( $item_id, $values ) {
		if ( isset( $values['renewing_key'] ) ) {
			wc_add_order_item_meta( $item_id, __('_renewing_key', 'wp-plugin-licencing' ), $values['renewing_key'] );
		}
	}	
}

new WP_Plugin_Licencing_Renewals();