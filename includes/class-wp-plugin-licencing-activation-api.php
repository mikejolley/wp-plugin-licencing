<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * WP_Plugin_Licencing_Activation_API
 */
class WP_Plugin_Licencing_Activation_API {

	/**
	 * Constructor
	 */
	public function __construct( $request ) {
		global $wpdb;

		$wpdb->hide_errors();
		nocache_headers();

		if ( isset( $request['request'] ) ) {
			$this->request = array_map( 'sanitize_text_field', $request );
		} else {
			$this->trigger_error( '100', __( 'Invalid API Request', 'wp-plugin-licencing' ) );
		}

		switch ( $this->request['request'] ) {
			case 'activate' :
				$this->activate();
			break;
			case 'deactivate' :
				$this->deactivate();
			break;
			default :
				$this->trigger_error( '100', __( 'Invalid API Request', 'wp-plugin-licencing' ) );
			break;
		}
	}

	/**
	 * Check if required API fields are set
	 * @param  array $required_fields
	 */
	private function check_required( $required_fields ) {
		$missing = array();

		foreach ( $required_fields as $required_field ) {
			if ( empty( $this->request[ $required_field ] ) ) {
				$missing[] = $required_field;
			}
		}

		if ( $missing ) {
			$this->trigger_error( '100', __( 'The following required information is missing', 'wp-plugin-licencing' ) . ': ' . implode( ', ', $missing ) );
		}
	}

	/**
	 * Trigger error message
	 * @param  int $code
	 * @param  string $message
	 */
	private function trigger_error( $code, $message ) {
		header( 'Content-Type: application/json' );
		$error = array( 'error_code' => $code, 'error' => $message );
		die( json_encode( $error ) );
	}

	/**
	 * Send JSON response
	 */
	private function send_response( $data ) {
		header( 'Content-Type: application/json' );
		die( json_encode( $data ) );
	}

	/**
	 * Activate a licence key
	 */
	public function activate() {
		global $wpdb;

		$this->check_required( array( 'email', 'licence_key', 'api_product_id', 'instance' ) );

		$licence             = wppl_get_licence_from_key( $this->request['licence_key'] );
		$api_product_post_id = wppl_get_api_product_post_id( $this->request['api_product_id'] );

		if ( ! $licence ) {
			$this->trigger_error( '101', __( 'Activation error: The provided licence is invalid or has expired.', 'wp-plugin-licencing' ) );
		}
		if ( ! $api_product_post_id ) {
			$this->trigger_error( '102', __( 'Activation error: Invalid API Product ID.', 'wp-plugin-licencing' ) );
		}
		if ( ! is_email( $this->request['email'] ) || strtolower( $this->request['email'] ) != strtolower( $licence->activation_email ) ) {
			$this->trigger_error( '103', sprintf( __( 'Activation error: The email provided (%s) is invalid.', 'wp-plugin-licencing' ), $this->request['email'] ) );
		}
		if ( ! in_array( $api_product_post_id, wppl_get_licence_api_product_permissions( $licence->product_id ) ) ) {
			$this->trigger_error( '104', __( 'Activation error: Licence is not for this product.', 'wp-plugin-licencing' ) );
		}

		$active_instances = wppl_get_licence_activations( $this->request['licence_key'], $this->request['api_product_id'], 1 );

		// Check if activation limit is reached
		if ( $licence->activation_limit && sizeof( $active_instances ) >= $licence->activation_limit ) {
			// lets allow reactvation for guests, but registered users need to de-activate first
			if ( ! $licence->user_id ) {
				foreach( $active_instances as $activation ) {
					if ( $activation->instance == $this->request['instance'] ) {
						// Reactivate the key
						$activation_result = $wpdb->update(
							"{$wpdb->prefix}wp_plugin_licencing_activations",
							array(
								'activation_active' => 1,
								'activation_date'   => current_time( 'mysql' )
							),
							array(
								'instance'       => $this->request['instance'],
								'api_product_id' => $this->request['api_product_id'],
								'licence_key'    => $this->request['licence_key']
							)
						);
						if ( ! $activation_result ) {
							$this->trigger_error( '106', __( 'Activation error: Could not reactivate licence key.', 'wp-plugin-licencing' ) );
						} else {
							$response              = array( 'activated' => true );
							$activations_remaining = absint( $licence->activation_limit - sizeof( $active_instances ) );
							$response['remaining'] = sprintf( __( '%s out of %s activations remaining', 'wp-plugin-licencing' ), $activations_remaining, $licence->activation_limit );
							$this->send_response( $response );
						}
					}
				}
			}
			$this->trigger_error( '105', __( 'Activation error: Licence key activation limit reached. Deactivate an install first.', 'wp-plugin-licencing' ) );
		}

		$instance_exists = false;
		$instances       = wppl_get_licence_activations( $this->request['licence_key'], $this->request['api_product_id'] );

		// Check for reactivation
		if ( $instances ) {
			foreach( $instances as $activation ) {
				if ( $activation->instance == $this->request['instance'] ) {
					$instance_exists = true;
				}
			}
		}

		if ( $instance_exists ) {
			$activation_result = $wpdb->update(
				"{$wpdb->prefix}wp_plugin_licencing_activations",
				array(
					'activation_active' => 1,
					'activation_date'   => current_time( 'mysql' )
				),
				array(
					'instance'       => $this->request['instance'],
					'api_product_id' => $this->request['api_product_id'],
					'licence_key'    => $this->request['licence_key']
				)
			);
		} else {
			$activation_result = $wpdb->insert(
				"{$wpdb->prefix}wp_plugin_licencing_activations",
				array(
					'activation_active' => 1,
					'activation_date'   => current_time( 'mysql' ),
					'instance'          => $this->request['instance'],
					'api_product_id'    => $this->request['api_product_id'],
					'licence_key'       => $this->request['licence_key']
				)
			);
		}

		if ( ! $activation_result ) {
			$this->trigger_error( '107', __( 'Activation error: Could not activate licence key.', 'wp-plugin-licencing' ) );
		}

		$activations = wppl_get_licence_activations( $this->request['licence_key'], $this->request['api_product_id'] );
		$response    = array( 'activated' => true );

		if ( $licence->activation_limit ) {
			$activations_remaining = absint( $licence->activation_limit - sizeof( $activations ) );
			$response['remaining'] = sprintf( __( '%s out of %s activations remaining', 'wp-plugin-licencing' ), $activations_remaining, $licence->activation_limit );
		}

		$this->send_response( $response );
	}

	/**
	 * Dectivate a licence key
	 */
	public function deactivate() {
		global $wpdb;

		$this->check_required( array( 'licence_key', 'api_product_id', 'instance' ) );

		$deactivation_result = $wpdb->update(
			"{$wpdb->prefix}wp_plugin_licencing_activations",
			array(
				'activation_active' => 0
			),
			array(
				'instance'       => $this->request['instance'],
				'api_product_id' => $this->request['api_product_id'],
				'licence_key'    => $this->request['licence_key']
			)
		);

		$this->send_response( $deactivation_result ? true : false );
	}
}