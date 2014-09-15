<?php
/*
	Plugin Name: WP Plugin Licencing for WooCommerce
	Plugin URI: http://wpjobmanager.com
	Description: A simple solution to plugin licencing. Define API Products separately, then sell licences as products in WooCommerce which grant access to api products.
	Version: 1.0.0
	Author: Mike Jolley
	Author URI: http://mikejolley.com
*/

/**
 * WP_Plugin_Licencing main class
 */
class WP_Plugin_Licencing {

	/**
	 * Constructor
	 */
	public function __construct() {
		// Define constants
		define( 'WP_PLUGIN_LICENCING_VERSION', '1.0.0' );
		define( 'WP_PLUGIN_LICENCING_PLUGIN_DIR', untrailingslashit( plugin_dir_path( __FILE__ ) ) );
		define( 'WP_PLUGIN_LICENCING_PLUGIN_URL', untrailingslashit( plugins_url( basename( plugin_dir_path( __FILE__ ) ), basename( __FILE__ ) ) ) );

		// Includes
		include_once( 'includes/wp-plugin-licencing-functions.php' );
		include_once( 'includes/class-wp-plugin-licencing-post-types.php' );
		include_once( 'includes/class-wp-plugin-licencing-orders.php' );
		include_once( 'includes/class-wp-plugin-licencing-download-handler.php' );
		include_once( 'includes/class-wp-plugin-licencing-shortcodes.php' );
		include_once( 'includes/class-wp-plugin-licencing-renewals.php' );

		if ( is_admin() ) {
			include_once( 'includes/class-wp-plugin-licencing-products.php' );
			include_once( 'includes/class-wp-plugin-licencing-menus.php' );
		}

		// Activation hooks
		register_activation_hook( __FILE__, array( $this, 'activation' ) );

		// Hooks
		add_action( 'plugins_loaded', array( $this, 'load_plugin_textdomain' ) );
		add_action( 'woocommerce_api_wp_plugin_licencing_activation_api', array( $this, 'handle_activation_api_request' ) );
		add_action( 'woocommerce_api_wp_plugin_licencing_update_api', array( $this, 'handle_update_api_request' ) );
	}

	/**
	 * Localisation
	 */
	public function load_plugin_textdomain() {
		load_plugin_textdomain( 'wp-plugin-licencing', false, dirname( plugin_basename( __FILE__ ) ) . '/languages/' );
	}

	/**
	 * runs various functions when the plugin first activates
	 */
	public function activation() {
		global $wpdb;

		$wpdb->hide_errors();

		$collate = '';

		if ( $wpdb->has_cap( 'collation' ) ) {
			if ( ! empty($wpdb->charset ) ) {
				$collate .= "DEFAULT CHARACTER SET $wpdb->charset";
			}
			if ( ! empty($wpdb->collate ) ) {
				$collate .= " COLLATE $wpdb->collate";
			}
		}

		require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );

	    // Table for storing licence keys for purchases
	    $sql = "
CREATE TABLE ". $wpdb->prefix . "wp_plugin_licencing_licences (
licence_key varchar(200) NOT NULL,
order_id bigint(20) NOT NULL DEFAULT 0,
user_id bigint(20) NOT NULL DEFAULT 0,
activation_email varchar(200) NOT NULL,
product_id int(20) NOT NULL,
activation_limit int(20) NOT NULL DEFAULT 0,
date_created datetime DEFAULT '0000-00-00 00:00:00' NOT NULL,
date_expires datetime NULL,
PRIMARY KEY  (licence_key)
) $collate;
CREATE TABLE ". $wpdb->prefix . "wp_plugin_licencing_activations (
activation_id bigint(20) NOT NULL auto_increment,
licence_key varchar(200) NOT NULL,
api_product_id varchar(200) NOT NULL,
instance varchar(200) NOT NULL,
activation_date datetime DEFAULT '0000-00-00 00:00:00' NOT NULL,
activation_active int(1) NOT NULL DEFAULT 1,
PRIMARY KEY  (activation_id)
) $collate;
CREATE TABLE ". $wpdb->prefix . "wp_plugin_licencing_download_log (
log_id bigint(20) NOT NULL auto_increment,
date_downloaded datetime DEFAULT '0000-00-00 00:00:00' NOT NULL,
licence_key varchar(200) NOT NULL,
activation_email varchar(200) NOT NULL,
api_product_id varchar(200) NOT NULL,
user_ip_address varchar(200) NOT NULL,
PRIMARY KEY  (log_id)
) $collate;
		";

		dbDelta( $sql );
	}

	/**
	 * Activation
	 */
	public function handle_activation_api_request() {
		include_once( 'includes/class-wp-plugin-licencing-activation-api.php' );
		new WP_Plugin_Licencing_Activation_API( $_REQUEST );
	}

	/**
	 * Plugin updates
	 */
	public function handle_update_api_request() {
		include_once( 'includes/class-wp-plugin-licencing-update-api.php' );
		new WP_Plugin_Licencing_Update_API( $_REQUEST );
	}
}

new WP_Plugin_Licencing();