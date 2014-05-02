<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * WP_Plugin_Licencing_Menus class.
 */
class WP_Plugin_Licencing_Menus {

	/**
	 * Hook in tabs.
	 */
	public function __construct() {
		// Add menus
		add_action( 'admin_menu', array( $this, 'admin_menu' ), 9 );
		add_filter( 'menu_order', array( $this, 'menu_order' ) );
	}

	/**
	 * Add menu items
	 */
	public function admin_menu() {
	    $main_page = add_menu_page( __( 'Licences', 'wp-plugin-licencing' ), __( 'Licences', 'wp-plugin-licencing' ), 'manage_options', 'wp_plugin_licencing_licences' , array( $this, 'licences_page' ), null, '55.8' );

	    add_submenu_page( 'wp_plugin_licencing_licences', __( 'Activations', 'wp-plugin-licencing' ),  __( 'Activations', 'wp-plugin-licencing' ) , 'manage_options', 'wp_plugin_licencing_activations', array( $this, 'activations_page' ) );
	}

	/**
	 * Manage licences
	 */
	public function licences_page() {
		include_once( 'class-wp-plugin-licencing-licences.php' );

		$licence_table = new WP_Plugin_Licencing_Licences();
		$licence_table->prepare_items();
		?>
		<div class="wrap">
			<h2><?php _e( 'Licences', 'wp-plugin-licencing' ); ?></h2>
			<form id="licence-management" method="post">
				<input type="hidden" name="page" value="wp_plugin_licencing_licences" />
				<?php $licence_table->display() ?>
				<?php wp_nonce_field( 'save', 'wp-plugin-licencing' ); ?>
			</form>
		</div>
		<?php
	}

	/**
	 * Manage activations
	 */
	public function activations_page() {
		include_once( 'class-wp-plugin-licencing-activations.php' );

		$activations_table = new WP_Plugin_Licencing_Activations();
		$activations_table->prepare_items();
		?>
		<div class="wrap">
			<h2><?php _e( 'Activations', 'wp-plugin-licencing' ); ?></h2>
			<form id="activation-management" method="post">
				<input type="hidden" name="page" value="wp_plugin_licencing_activations" />
				<?php $activations_table->display() ?>
				<?php wp_nonce_field( 'save', 'wp-plugin-licencing' ); ?>
			</form>
		</div>
		<?php
	}

	/**
	 * Reorder the menu items in admin.
	 *
	 * @param mixed $menu_order
	 * @return array
	 */
	public function menu_order( $menu_order ) {
		// Initialize our custom order array
		$modified_menu_order = array();

		// Get index of product menu
		$api_products = array_search( 'edit.php?post_type=api_product', $menu_order );

		// Loop through menu order and do some rearranging
		foreach ( $menu_order as $index => $item ) {

			if ( ( ( 'wp_plugin_licencing_licences' ) == $item ) ) {
				$modified_menu_order[] = 'edit.php?post_type=api_product';
				$modified_menu_order[] = $item;
				unset( $menu_order[ $api_products ] );
			} else {
				$modified_menu_order[] = $item;
			}
		}

		return $modified_menu_order;
	}	
}

new WP_Plugin_Licencing_Menus();