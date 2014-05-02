<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'WP_List_Table' ) ) {
	require_once( ABSPATH . 'wp-admin/includes/class-wp-list-table.php' );
}

/**
 * WP_Plugin_Licencing_Activations class.
 * 
 * @extends WP_List_Table
 */
class WP_Plugin_Licencing_Activations extends WP_List_Table {
	   
	/**
	 * Constructor
	 */
	public function __construct(){
		global $status, $page;
		
		//Set parent defaults
		parent::__construct( array(
			'singular' => 'activation',
			'plural'   => 'activations',
			'ajax'     => false
		) );    
	}
	
	/**
	 * column_default function.
	 * 
	 * @access public
	 * @param mixed $post
	 * @param mixed $column_name
	 */
	public function column_default( $item, $column_name ) {
		global $wpdb;
		
		switch( $column_name ) {
			case 'licence_key' :
				return '<a href="' . admin_url( 'admin.php?page=wp_plugin_licencing_activations&amp;licence_key=' . $item->licence_key ) . '">' . '<code>' . $item->licence_key . '</code>' . '</a>';
			case 'api_product_id' :
				return esc_html( $item->api_product_id );
			case 'instance' :
				return $item->instance ? esc_html( $item->instance ) : __( 'n/a', 'wp-plugin-licencing' );
			case 'activation_date' :
				return ( $item->activation_date ) ? date_i18n( get_option( 'date_format' ), strtotime( $item->activation_date ) ) : __( 'n/a', 'wp-plugin-licencing' );
			case 'activation_active' :
				return $item->activation_active ? '&#10004;' : '-';
		}
	}
	
	/**
	 * column_cb function.
	 * 
	 * @access public
	 * @param mixed $item
	 */
	public function column_cb( $item ){
		return sprintf(
			'<input type="checkbox" name="%1$s[]" value="%2$s" />',
			'activation_id',
			$item->activation_id
		);
	}
	
	/**
	 * get_columns function.
	 * 
	 * @access public
	 */
	public function get_columns(){
		$columns = array(
			'cb'                => '<input type="checkbox" />', 
			'licence_key'       => __( 'Licence key', 'wp-plugin-licencing' ),
			'api_product_id'    => __( 'API Product ID', 'wp-plugin-licencing' ),
			'activation_date'   => __( 'Activation date', 'wp-plugin-licencing' ),
			'instance'          => __( 'Instance', 'wp-plugin-licencing' ),
			'activation_active' => __( 'Active?', 'wp-plugin-licencing' ),
		);
		return $columns;
	}
	
	/**
	 * get_sortable_columns function.
	 * 
	 * @access public
	 */
	public function get_sortable_columns() {
		$sortable_columns = array(
			'activation_date'  => array( 'activation_date', true ),     //true means its already sorted
			'date_expires'     => array( 'date_expires', false ),
			'order_id'         => array( 'order_id', false ),
			'api_product_id'   => array( 'api_product_id', false ),
			'activation_email' => array( 'activation_email', false ),
		);
		return $sortable_columns;
	}
	
	/**
	 * get_bulk_actions
	 * @return array
	 */
	public function get_bulk_actions() {
		$actions = array(
			'activate'   => __( 'Activate', 'wp-plugin-licencing' ),
			'deactivate' => __( 'Deactivate', 'wp-plugin-licencing' ),
			'delete'     => __( 'Delete', 'wp-plugin-licencing' )
		);
		return $actions;
	}
	
	/** 
	 * Process bulk actions
	 */
	public function process_bulk_action() {
		global $wpdb;
		
		if ( ! isset( $_POST['activation_id'] ) ) {
			return;
		}
		
		$items = array_map( 'absint', $_POST['activation_id'] );

		if ( $items ) {
			switch ( $this->current_action() ) {
				case 'activate' :
					foreach ( $items as $id ) {
						$wpdb->update( "{$wpdb->prefix}wp_plugin_licencing_activations", array( 'activation_active' => 1 ), array( 'activation_id' => $id ) );
					}
					echo '<div class="updated"><p>' . sprintf( __( '%d activations activated', 'wp-plugin-licencing' ), sizeof( $items ) ) . '</p></div>';
				break;
				case 'deactivate' :
					foreach ( $items as $id ) {
						$wpdb->update( "{$wpdb->prefix}wp_plugin_licencing_activations", array( 'activation_active' => 0 ), array( 'activation_id' => $id ) );
					}
					echo '<div class="updated"><p>' . sprintf( __( '%d activations deactivated', 'wp-plugin-licencing' ), sizeof( $items ) ) . '</p></div>';
				break;
				case 'delete' :
					foreach ( $items as $id ) {
						$wpdb->delete( "{$wpdb->prefix}wp_plugin_licencing_activations", array( 'activation_id' => $id ) );
					}
					echo '<div class="updated"><p>' . sprintf( __( '%d activations deleted', 'wp-plugin-licencing' ), sizeof( $items ) ) . '</p></div>';
				break;
			}
		}
	}
	
	/**
	 * prepare_items function.
	 * 
	 * @access public
	 */
	public function prepare_items() {
		global $wpdb;
		
		$current_page = $this->get_pagenum();
		$per_page     = 50;
		$orderby      = ! empty( $_REQUEST['orderby'] ) ? sanitize_text_field( $_REQUEST['orderby'] ) : 'activation_date';
		$order        = empty( $_REQUEST['order'] ) || $_REQUEST['order'] === 'asc' ? 'ASC' : 'DESC';
		$licence_key  = ! empty( $_REQUEST['licence_key'] ) ? sanitize_text_field( $_REQUEST['licence_key'] ) : '';

		/**
		 * Init column headers
		 */
		$this->_column_headers = array( $this->get_columns(), array(), $this->get_sortable_columns() );
		
		/**
		 * Process bulk actions
		 */
		$this->process_bulk_action();
		
		$where = array( 'WHERE 1=1' );
		if ( $licence_key ) {
			$where[] = "AND licence_key='{$licence_key}'";
		}
		$where = implode( ' ', $where );

		/**
		 * Get items
		 */
		$max = $wpdb->get_var( "SELECT COUNT( activation_id ) FROM {$wpdb->prefix}wp_plugin_licencing_activations $where;" );
		
		$this->items = $wpdb->get_results( $wpdb->prepare( "
			SELECT * FROM {$wpdb->prefix}wp_plugin_licencing_activations
			$where
			ORDER BY `{$orderby}` {$order} LIMIT %d, %d
		", ( $current_page - 1 ) * $per_page, $per_page ) );

		/**
		 * Pagination
		 */
		$this->set_pagination_args( array(
			'total_items' => $max, 
			'per_page'    => $per_page,
			'total_pages' => ceil( $max / $per_page )
		) );
	}	
}