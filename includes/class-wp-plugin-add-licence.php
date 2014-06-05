<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * WP_Plugin_Licencing_Add_Licence class.
 */
class WP_Plugin_Licencing_Add_Licence {
	   
	/**
	 * Constructor
	 */
	public function __construct(){
   		if ( ! empty( $_POST['add_licence'] ) && ! empty( $_POST['wp_plugin_licencing_nonce'] ) && wp_verify_nonce( $_POST['wp_plugin_licencing_nonce'], 'add_licence' ) ) {
   			$this->save();
   		}
	}

	/**
	 * Output the form
	 */
	public function form() {
		?>
		<p><?php _e( 'Create a licence manually using the form below. The customer will be emailed their key and the download link.', 'wp-plugin-licencing' ); ?></p>
		<table class="form-table">
			<tr>
				<th>
					<label for="licence_key"><?php _e( 'Licence key', 'wp-plugin-licencing' ); ?></label>
				</th>
				<td>
					<input type="text" name="licence_key" id="licence_key" class="input-text regular-text" value="<?php echo esc_attr( WP_Plugin_Licencing_Orders::generate_licence_key() ); ?>" />
				</td>
			</tr>
			<tr>
				<th>
					<label for="activation_email"><?php _e( 'Activation email', 'wp-plugin-licencing' ); ?></label>
				</th>
				<td>
					<input type="email" name="activation_email" id="activation_email" class="input-text regular-text" placeholder="<?php _e( 'Use registered customer email', 'wp-plugin-licencing' ); ?>" />
				</td>
			</tr>
			<tr>
				<th>
					<label for="product_id"><?php _e( 'Product', 'wp-plugin-licencing' ); ?></label>
				</th>
				<td>
					<select name="product_id" class="chosen_select" data-placeholder="<?php _e( 'Choose a product&hellip;', 'wp-plugin-licencing' ) ?>" style="width:25em">
						<?php
							echo '<option value=""></option>';

							$args = array(
								'post_type'      => 'product',
								'posts_per_page' => -1,
								'post_status'    => 'publish',
								'order'          => 'ASC',
								'orderby'        => 'title',
								'meta_query'     => array(
									array(
										'key'   => '_is_api_product_licence',
										'value' => 'yes'
									)
								)
							);

							$products = get_posts( $args );

							if ( $products ) {
								foreach ( $products as $product ) {
									$args_get_children = array(
										'post_type'      => array( 'product_variation', 'product' ),
										'posts_per_page' => -1,
										'order'          => 'ASC',
										'orderby'        => 'title',
										'post_parent'    => $product->ID
									);

									if ( $children_products = get_children( $args_get_children ) ) {
										echo '<optgroup label="' . esc_attr( $product->post_title ) . '">';
										foreach ( $children_products as $child ) {
											$child_product = get_product( $child );
											$attributes    = $child_product->get_variation_attributes();
											$extra_data    = ' &ndash; ' . implode( ', ', $attributes ) . ' &ndash; ' . wc_price( $child_product->get_price() );
											echo '<option value="' . absint( $child->ID ) . '">&nbsp;&nbsp;&mdash;&nbsp;' . $child_product->get_title() . $extra_data . '</option>';
										}
										echo '</optgroup>';
									} else {
										echo '<option value="' . $product->ID . '">' . $product->post_title . '</option>';
									}
								}
							}
						?>
					</select>
				</td>
			</tr>
			<tr>
				<th>
					<label for="user_id"><?php _e( 'Customer', 'wp-plugin-licencing' ); ?></label>
				</th>
				<td>
					<select id="user_id" name="user_id" class="ajax_chosen_select_customer" style="width:25em">
						<option value=""><?php _e( 'Guest', 'wp-plugin-licencing' ) ?></option>
					</select>
				</td>
			</tr>
		</table>

		<p class="submit">
			<input type="submit" class="button button-primary" name="add_licence" value="<?php _e( 'Add Licence', 'wp-plugin-licencing' ); ?>" />
		</p>
		<?php
		// Ajax Chosen Customer Selectors JS
		wc_enqueue_js( "
			jQuery('select.chosen_select').chosen();
			jQuery('select.ajax_chosen_select_customer').ajaxChosen({
			    method: 		'GET',
			    url: 			'" . admin_url( 'admin-ajax.php' ) . "',
			    dataType: 		'json',
			    afterTypeDelay: 100,
			    minTermLength: 	1,
			    data:		{
			    	action: 	'woocommerce_json_search_customers',
					security: 	'" . wp_create_nonce( "search-customers" ) . "'
			    }
			}, function (data) {

				var terms = {};

			    $.each(data, function (i, val) {
			        terms[i] = val;
			    });

			    return terms;
			});
		" );		
	}

	/**
	 * Save the new key
	 */
	public function save() {
		$licence_key      = wc_clean( $_POST['licence_key'] );
		$activation_email = wc_clean( $_POST['activation_email'] );
		$product_id       = absint( $_POST['product_id'] );
		$user_id          = absint( $_POST['user_id'] );

		try {
			// Validate
			if ( empty( $licence_key ) ) {
				throw new Exception( __( 'Licence key is a required field', 'wp-plugin-licencing' ) );
			}
			if ( empty( $product_id ) ) {
				throw new Exception( __( 'You must choose a product for this licence', 'wp-plugin-licencing' ) );
			}
			if ( empty( $activation_email ) && empty( $user_id ) ) {
				throw new Exception( __( 'Either an activation email or user ID is required', 'wp-plugin-licencing' ) );
			}

			$product = get_product( $product_id );

			if ( 'yes' !== get_post_meta( $product->id, '_is_api_product_licence', true ) ) {
				throw new Exception( __( 'Invalid product', 'wp-plugin-licencing' ) );
			}

			if ( ! $activation_email && $user_id ) {
				$user             = get_user_by( 'id', $user_id );
				$activation_email = $user->user_email;
			}

			if ( empty( $activation_email ) || ! is_email( $activation_email ) ) {
				throw new Exception( __( 'A valid activation email is required', 'wp-plugin-licencing' ) );
			}

			if ( ! $product->variation_id || ( ! $activation_limit = get_post_meta( $product->variation_id, '_licence_activation_limit', true ) ) ) {
				$activation_limit    = get_post_meta( $product->id, '_licence_activation_limit', true );
			}
			if ( ! $product->variation_id || ( ! $licence_expiry_days = get_post_meta( $product->variation_id, '_licence_expiry_days', true ) ) ) {
				$licence_expiry_days = get_post_meta( $product->id, '_licence_expiry_days', true );
			}

			$data = array(
				'order_id'         => 0,
				'licence_key'      => $licence_key,
				'activation_email' => $activation_email,
				'user_id'          => $user_id,
				'product_id'       => $product->variation_id ? $product->variation_id : $product->id,
				'activation_limit' => $activation_limit,
				'date_expires'     => ! empty( $licence_expiry_days ) ? date( "Y-m-d H:i:s", strtotime( "+{$licence_expiry_days} days", current_time( 'timestamp' ) ) ) : ''
			);

			if ( WP_Plugin_Licencing_Orders::save_licence_key( $data ) ) {
				ob_start();

				// Try to get a user name
				if ( ! empty( $user ) && ! empty( $user->first_name ) ) {
					$user_first_name = $user->first_name;
				} else {
					$user_first_name = false;
				}

				wc_get_template( 'new-licence-email.php', array( 'key' => wppl_get_licence_from_key( $licence_key ), 'user_first_name' => $user_first_name ), 'wp-plugin-licencing', WP_PLUGIN_LICENCING_PLUGIN_DIR . '/templates/' );

				// Get contents
				$message = ob_get_clean();

				wp_mail( $activation_email, __( 'Your licence keys for "WP Job Manager"', 'wp-plugin-licencing' ), $message );
				
				$admin_message = sprintf( __( 'Licence has been emailed to %s.', 'wp-plugin-licencing' ), $activation_email );
				echo sprintf( '<div class="updated"><p>%s</p></div>', $admin_message );
			} else {
				throw new Exception( __( 'Could not create the licence', 'wp-plugin-licencing' ) );
			}

		} catch ( Exception $e ) {
			echo sprintf( '<div class="error"><p>%s</p></div>', $e->getMessage() );
		}
	}
}