<?php 
global $wpdb;

if ( sizeof( $keys ) > 0 ) : ?> 

	<h2><?php _e( 'Licences', 'wp-plugin-licencing' ); ?></h2>
	<table class="shop_table my_account_orders my_account_api_licence_keys">
		<thead>
			<tr>
				<th><?php _e( 'Product name', 'wp-plugin-licencing' ); ?></th> 
				<th><?php _e( 'Licence key', 'wp-plugin-licencing' ); ?></th>
				<th><?php _e( 'Activation limit', 'wp-plugin-licencing' ); ?></th>
				<th><?php _e( 'Download/Renew', 'wp-plugin-licencing' ); ?></th>
			</tr>
		</thead>
		<tbody>
			<?php foreach ( $keys as $key ) :

				$product     = wppl_get_licence_product( $key->product_id );
				$activations = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}wp_plugin_licencing_activations WHERE activation_active = 1 AND licence_key=%s;", $key->licence_key ) );

				// The expire time stamp, will be false if no expire date is set
				$expire_ts = strtotime( $key->date_expires );
				?>
				<tr>
					<td rowspan="<?php echo sizeof( $activations ) + 1; ?>"><?php echo esc_html( $product->post_title ); ?></td> 
					<td>
						<code style="display:block;"><?php echo $key->licence_key; ?></code>
						<small>
							<?php printf( __( 'Activation email: %s.', 'wp-plugin-licencing' ), $key->activation_email ); ?>
							<?php if ( false !== $expire_ts && $expire_ts > 0 ) : ?>
								<?php
									printf( __( 'Expiry date: %s.', 'wp-plugin-licencing' ), date_i18n( get_option( 'date_format' ), $expire_ts ) );
								?>
							<?php endif; ?>
						</small>
					</td>
					<td><?php echo $key->activation_limit ? sprintf( __( '%d per product', 'wp-plugin-licencing' ), absint( $key->activation_limit ) ) : __( 'Unlimited', 'wp-plugin-licencing' ); ?></td>
					<td><?php
						if ( $key->date_expires && false !== $expire_ts && $expire_ts > 0 && $expire_ts < current_time( 'timestamp' ) ) {
							echo '<a class="button" href="' . wppl_get_licence_renewal_url( $key->licence_key, $key->activation_email ) . '">' . __( 'Renew licence', 'wp-plugin-licencing' ) . '</a>';
						} else {
							if ( $api_product_permissions = wppl_get_licence_api_product_permissions( $key->product_id ) ) {
								echo '<ul class="digital-downloads">';
								foreach ( $api_product_permissions as $api_product_permission ) {
									echo '<li><a class="download-button" href="' . wppl_get_package_download_url( $api_product_permission, $key->licence_key, $key->activation_email ) . '">' . get_the_title( $api_product_permission ) . '</a></li>';
								}
								echo '</ul>';
							}
						}
					?></td>
				</tr>
				<?php foreach ( $activations as $activation ) : ?>
					<tr>
						<td colspan="3"><?php echo get_the_title( wppl_get_api_product_post_id( $activation->api_product_id ) ); ?> &mdash; <a href="<?php echo esc_attr( $activation->instance ); ?>" target="_blank"><?php echo esc_html( $activation->instance ); ?></a> <a class="button" style="float:right" href="<?php echo wppl_get_licence_deactivation_url( $activation->activation_id, $key->licence_key, $key->activation_email ); ?>"><?php _e( 'Deactivate', 'wp-plugin-licencing' ); ?></a></td>
					</tr>
				<?php endforeach; ?>
			<?php endforeach; ?>
		</tbody>
	</table>
	
<?php endif; ?>