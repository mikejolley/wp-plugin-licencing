<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * WP_Plugin_Licencing_Shortcodes
 */
class WP_Plugin_Licencing_Shortcodes {

	/**
	 * __construct function.
	 *
	 * @access public
	 * @return void
	 */
	public function __construct() {
		add_action( 'wp', array( $this, 'shortcode_action_handler' ) );
		add_shortcode( 'lost_licence_key_form', array( $this, 'lost_licence_key_form' ) );
	}

	/**
	 * Handle actions which need to be run before the shortcode e.g. post actions
	 */
	public function shortcode_action_handler() {
		global $post;

		if ( is_page() && strstr( $post->post_content, '[lost_licence_key_form' ) ) {
			$this->lost_licence_key_form_handler();
		} elseif ( ! empty( $_GET['deactivate_licence'] ) ) {
			$this->deactivate_licence();
		}
	}

	/**
	 * Deactivate a licence from the my account page
	 */
	public function deactivate_licence() {
		global $wpdb;

		if ( is_user_logged_in() && ! empty( $_GET['deactivate_licence'] ) ) {
			$activation_id    = sanitize_text_field( $_GET['deactivate_licence'] );
			$licence_key      = sanitize_text_field( $_GET['licence_key'] );
			$activation_email = sanitize_text_field( $_GET['activation_email'] );
			$licence          = wppl_get_licence_from_key( $licence_key );

			// Validation
			if ( ! $licence ) {
				wp_die( __( 'Invalid or expired licence key.', 'wp-plugin-licencing' ) );
			}
			if ( $licence->user_id && $licence->user_id != get_current_user_id() ) {
				wp_die( __( 'This licence does not appear to be yours.', 'wp-plugin-licencing' ) );
			}
			if ( ! is_email( $activation_email ) || $activation_email != $licence->activation_email ) {
				wp_die( __( 'Invalid activation email address.', 'wp-plugin-licencing' ) );
			}

			if ( $wpdb->update( "{$wpdb->prefix}wp_plugin_licencing_activations", array( 'activation_active' => 0 ), array( 
				'activation_id'    => $activation_id,
				'licence_key'      => $licence_key
			) ) ) {
				wc_add_notice( __( 'Licence successfully deactivated.' ), 'success' );
			} else {
				wc_add_notice( __( 'The licence could not be deactivated.' ), 'error' );
			}
		}
	}

	/**
	 * Handles actions on candidate dashboard
	 */
	public function lost_licence_key_form_handler() {
		if ( ! empty( $_REQUEST['submit_lost_licence_form'] ) ) {
			$activation_email = sanitize_text_field( $_REQUEST['activation_email'] );

			if ( ! is_email( $activation_email ) ) {
				wc_add_notice( __( 'Invalid email address.' ), 'error' );
				return;
			}

			$keys             = wppl_get_licences_from_activation_email( $activation_email );

			if ( ! $keys ) {
				wc_add_notice( __( 'No licences found.' ), 'error' );
			} else {
				ob_start();

				wc_get_template( 'lost-licence-email.php', array( 'keys' => $keys ), 'wp-plugin-licencing', WP_PLUGIN_LICENCING_PLUGIN_DIR . '/templates/' );

				// Get contents
				$message = ob_get_clean();

				wp_mail( $activation_email, __( 'Your product licences', 'wp-plugin-licencing' ), $message );
				wc_add_notice( sprintf( __( 'Your licences have been emailed to %s.' ), $activation_email ), 'success' );
			}
		}
	}

	/**
	 * Shows the lost licence key form
	 */
	public function lost_licence_key_form( $atts ) {
		ob_start();

		wc_get_template( 'lost-licence-form.php', array(), 'wp-plugin-licencing', WP_PLUGIN_LICENCING_PLUGIN_DIR . '/templates/' );

		return ob_get_clean();
	}
}

new WP_Plugin_Licencing_Shortcodes();