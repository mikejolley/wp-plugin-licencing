<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'WP_List_Table' ) ) {
	require_once( ABSPATH . 'wp-admin/includes/class-wp-list-table.php' );
}

/**
 * WP_Plugin_Licencing_Licences class.
 * 
 * @extends WP_List_Table
 */
class WP_Plugin_Licencing_Licences extends WP_List_Table {
	   
	/**
	 * Constructor
	 */
	public function __construct(){
		global $status, $page;
		
		//Set parent defaults
		parent::__construct( array(
			'singular' => 'licence key',
			'plural'   => 'licence keys',
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
				return '<code>' . $item->licence_key . '</code>';
			case 'activation_email' :
				return $item->activation_email;
			case 'product_id' :
				$product = wppl_get_licence_product( $item->product_id );
			
				return ( $product ) ? '<a href="' . admin_url( 'post.php?post=' . absint( $product->ID ) . '&action=edit' ) . '">' . esc_html( $product->post_title ) . '</a>' : __( 'n/a', 'wp-plugin-licencing' );
			case 'user_id' :
				$user = get_user_by( 'ID', $item->user_id );
			
				return ( $item->user_id ) ? '<a href="' . admin_url( 'user-edit.php?user_id=' . absint( $item->user_id ) ) . '">#' . esc_html( $item->user_id ) . '&rarr;</a>' : __( 'n/a', 'wp-plugin-licencing' );
			case 'activations' :  
				$count = $wpdb->get_var( $wpdb->prepare( "SELECT COUNT( activation_id ) FROM {$wpdb->prefix}wp_plugin_licencing_activations WHERE activation_active = 1 AND licence_key=%s;", $item->licence_key ) );

				return '<a href="' . admin_url( 'admin.php?page=wp_plugin_licencing_activations&amp;licence_key=' . $item->licence_key ) . '">' . absint( $count ) . ' &rarr;</a>';
			case 'activation_limit' :
				return $item->activation_limit ? sprintf( __( '%d per product', 'wp-plugin-licencing' ), absint( $item->activation_limit ) ) : __( 'n/a', 'wp-plugin-licencing' );
			case 'order_id' :
				return $item->order_id > 0 ? '<a href="' . admin_url( 'post.php?post=' . absint( $item->order_id ) . '&action=edit' ) . '">#' . absint( $item->order_id  ) . ' &rarr;</a>' : __( 'n/a', 'wp-plugin-licencing' );
			case 'date_created' :
				return ( $item->date_created ) ? date_i18n( get_option( 'date_format' ), strtotime( $item->date_created ) ) : __( 'n/a', 'wp-plugin-licencing' );
			case 'date_expires' :
				return ( $item->date_expires ) ? date_i18n( get_option( 'date_format' ), strtotime( $item->date_expires ) ) : __( 'n/a', 'wp-plugin-licencing' );
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
			'licence_key',
			$item->licence_key
		);
	}
	
	/**
	 * get_columns function.
	 * 
	 * @access public
	 */
	public function get_columns(){
		$columns = array(
			'cb'               => '<input type="checkbox" />', 
			'licence_key'      => __( 'Licence key', 'wp-plugin-licencing' ),
			'activation_email' => __( 'Activation email', 'wp-plugin-licencing' ),
			'product_id'       => __( 'Product', 'wp-plugin-licencing' ),
			'order_id'         => __( 'Order ID', 'wp-plugin-licencing' ),
			'user_id'          => __( 'User ID', 'wp-plugin-licencing' ),
			'activation_limit' => __( 'Activation limit', 'wp-plugin-licencing' ),
			'activations'      => __( 'Activations', 'wp-plugin-licencing' ),
			'date_created'     => __( 'Date created', 'wp-plugin-licencing' ),
			'date_expires'     => __( 'Date expires', 'wp-plugin-licencing' )
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
			'date_created'     => array( 'date_created', true ),     //true means its already sorted
			'date_expires'     => array( 'date_expires', false ),
			'order_id'         => array( 'order_id', false ),
			'user_id'          => array( 'user_id', false ),
			'product_id'       => array( 'product_id', false ),
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
		
		if ( ! isset( $_POST['licence_key'] ) ) {
			return;
		}
		
		$items = array_map( 'sanitize_text_field', $_POST['licence_key'] );

		if ( $items ) {
			switch ( $this->current_action() ) {
				case 'deactivate' :
					foreach ( $items as $id ) {
						$wpdb->update( "{$wpdb->prefix}wp_plugin_licencing_activations", array( 'activation_active' => 0 ), array( 'licence_key' => $id ) );
					}
					echo '<div class="updated"><p>' . sprintf( __( '%d keys deactivated', 'wp-plugin-licencing' ), sizeof( $items ) ) . '</p></div>';
				break;
				case 'delete' :
					foreach ( $items as $id ) {
						$wpdb->delete( "{$wpdb->prefix}wp_plugin_licencing_licences", array( 'licence_key' => $id ) );
						$wpdb->delete( "{$wpdb->prefix}wp_plugin_licencing_activations", array( 'licence_key' => $id ) );
					}
					echo '<div class="updated"><p>' . sprintf( __( '%d keys deleted', 'wp-plugin-licencing' ), sizeof( $items ) ) . '</p></div>';
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
		$orderby      = ! empty( $_REQUEST['orderby'] ) ? sanitize_text_field( $_REQUEST['orderby'] ) : 'date_created';
		$order        = empty( $_REQUEST['order'] ) || $_REQUEST['order'] === 'asc' ? 'ASC' : 'DESC';
		$order_id     = ! empty( $_REQUEST['order_id'] ) ? absint( $_REQUEST['order_id'] ) : '';

		/**
		 * Init column headers
		 */
		$this->_column_headers = array( $this->get_columns(), array(), $this->get_sortable_columns() );
		
		/**
		 * Process bulk actions
		 */
		$this->process_bulk_action();

		$where = array( 'WHERE 1=1' );
		if ( $order_id ) {
			$where[] = 'AND order_id=' . $order_id;
		}
		$where = implode( ' ', $where );
		
		/**
		 * Get items
		 */
		$max = $wpdb->get_var( "SELECT COUNT(licence_key) FROM {$wpdb->prefix}wp_plugin_licencing_licences $where;" );
		
		$this->items = $wpdb->get_results( $wpdb->prepare( "
			SELECT * FROM {$wpdb->prefix}wp_plugin_licencing_licences
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