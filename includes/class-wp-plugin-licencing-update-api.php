<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * WP_Plugin_Licencing_Update_API
 */
class WP_Plugin_Licencing_Update_API {

	/**
	 * Constructor
	 */
	public function __construct( $request ) {
		global $wpdb;

		$wpdb->hide_errors();
		nocache_headers();

		$user_agent = $_SERVER['HTTP_USER_AGENT'];

		if ( stristr( $user_agent, 'WordPress' ) === false ) {
			die();
		}

		if ( isset( $request['request'] ) ) {
			$this->request = array_map( 'sanitize_text_field', $request );
		} else {
			die();
		}

		switch ( $this->request['request'] ) {
			case 'pluginupdatecheck' :
				$this->plugin_update_check();
			break;
			case 'plugininformation' :
				$this->plugin_information();
			break;
			default :
				die();
			break;
		}
	}

	/**
	 * Trigger error message
	 * @param  int $code
	 * @param  string $message
	 */
	private function trigger_error( $code, $message ) {
		$response = new stdClass();

		switch ( $this->request['request'] ) {
			case 'pluginupdatecheck' :
				$response->slug        = '';
				$response->new_version = '';
				$response->url         = '';
				$response->package     = '';
			break;
			case 'plugininformation' :
				$response->name          = '';
				$response->slug          = '';
				$response->version       = '';
				$response->last_updated  = '';
				$response->download_link = '';
				$response->author        = '';
				$response->requires      = '';
				$response->tested        = '';
				$response->homepage      = '';
				$response->sections      = '';
			break;
		}

		$response->errors = array( $code => $message );
		die( serialize( $response ) );
	}

	/**
	 * Send response
	 */
	private function send_response( $data ) {
		die( serialize( $data ) );
	}

	/**
	 * Check access to plugin update API
	 */
	public function check_access() {
		// Check data 
		if ( empty( $this->request['licence_key'] ) ) {
			$this->trigger_error( 'no_key', 'no_key' );
		}
		if ( empty( $this->request['email'] ) || empty( $this->request['api_product_id'] ) || empty( $this->request['instance'] ) || empty( $this->request['version'] ) || empty( $this->request['plugin_name'] ) ) {
			$this->trigger_error( 'invalid_request', 'invalid_request' );
		}

		// Check licence
		$licence             = wppl_get_licence_from_key( $this->request['licence_key'] );
		$api_product_post_id = wppl_get_api_product_post_id( $this->request['api_product_id'] );

		if ( ! $api_product_post_id ) {
			$this->trigger_error( 'invalid_request', 'invalid_request' );
		}
		if ( ! $licence || ! is_email( $this->request['email'] ) || $this->request['email'] != $licence->activation_email || ! in_array( $api_product_post_id, wppl_get_licence_api_product_permissions( $licence->product_id ) ) ) {

			$this->trigger_error( 'invalid_key', sprintf( __( 'The licence for <code>%s</code> is invalid or has expired. You can reactivate or purchase a licence key from your <a href="%s" target="_blank">account dashboard</a>.', 'wp-plugin-licencing' ), $this->request['api_product_id'], get_permalink( wc_get_page_id( 'myaccount' ) ) ) );
		}
		if ( ! wppl_is_licence_activated( $this->request['licence_key'], $this->request['api_product_id'], $this->request['instance'] ) ) {

			$this->trigger_error( 'no_activation', sprintf( __( 'The licence is no longer activated on this site. Reactivate the licence to receive updates for <code>%s</code>.', 'wp-plugin-licencing' ), $this->request['api_product_id'] ) );
		}
	}

	/**
	 * Plugin update check
	 */
	public function plugin_update_check() {
		$this->check_access();

		$licence             = wppl_get_licence_from_key( $this->request['licence_key'] );
		$api_product_post_id = wppl_get_api_product_post_id( $this->request['api_product_id'] );
		$data                = new stdClass();
		$data->slug          = $this->request['plugin_name'];
		$data->new_version   = get_post_meta( $api_product_post_id, '_version', true );
		$data->url           = get_post_meta( $api_product_post_id, '_plugin_uri', true );
		$data->package       = wppl_get_package_download_url( $api_product_post_id, $this->request['licence_key'], $this->request['email'] );

		$this->send_response( $data );
	}

	/**
	 * Get plugin information
	 */
	public function plugin_information() {
		$this->check_access();

		$api_product_post_id = wppl_get_api_product_post_id( $this->request['api_product_id'] );
		$plugin_version      = get_post_meta( $api_product_post_id, '_version', true );
		$transient_name      = 'plugininfo_' . md5( $this->request['api_product_id'] . $plugin_version );

		if ( false === ( $data = get_transient( $transient_name ) ) ) {

			$api_product_post    = get_post( $api_product_post_id );
			$data                = new stdClass();
			$data->name          = $api_product_post->post_title;
			$data->slug          = $this->request['plugin_name'];
			$data->version       = $plugin_version;
			$data->last_updated  = get_post_meta( $api_product_post_id, '_last_updated', true );
			$data->download_link = wppl_get_package_download_url( $api_product_post_id, $this->request['licence_key'], $this->request['email'] );
				
			if ( $author_uri = get_post_meta( $api_product_post_id, '_author_uri', true ) ) {
				$data->author = '<a href="' . $author_uri . '">' . get_post_meta( $api_product_post_id, '_author', true ) . '</a>';
			} else {
				$data->author = get_post_meta( $api_product_post_id, '_author', true );
			}
			
			$data->requires      = get_post_meta( $api_product_post_id, '_requires_wp_version', true );
			$data->tested        = get_post_meta( $api_product_post_id, '_tested_wp_version', true );
			$data->homepage      = get_post_meta( $api_product_post_id, '_plugin_uri', true );
			$data->sections      = array(
				'description' => wpautop( $api_product_post->post_content ),
				'changelog'   => get_post_meta( $api_product_post_id, '_changelog', true )
			);

			if ( ! function_exists( 'Markdown' ) ) {
				include_once( 'markdown.php' );
			}

			foreach ( $data->sections as $key => $section ) {
				$data->sections[ $key ] = str_replace( array( "\r\n", "\r"), "\n", $data->sections[ $key ] );
				$data->sections[ $key ] = trim( $data->sections[ $key ] );
				if ( 0 === strpos( $data->sections[ $key ], "\xEF\xBB\xBF" ) ) {
					$data->sections[ $key ] = substr( $data->sections[ $key ], 3 );
				}
				// Markdown transformations
				$data->sections[ $key ] = preg_replace('/^[\s]*=[\s]+(.+?)[\s]+=/m', '<h4>$1</h4>', $data->sections[ $key ] );
				$data->sections[ $key ] = Markdown( $data->sections[ $key ] );
			}	

			set_transient( $transient_name, $data, YEAR_IN_SECONDS );
		}
		$this->send_response( $data );
	}
}