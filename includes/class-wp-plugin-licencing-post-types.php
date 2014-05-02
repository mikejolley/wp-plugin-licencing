<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * WP_Plugin_Licencing_Post_Types
 */
class WP_Plugin_Licencing_Post_Types {

	/**
	 * Constructor
	 */
	public function __construct() {
		add_filter( 'enter_title_here', array( $this, 'enter_title_here' ), 1, 2 );
		add_filter( 'manage_edit-api_product_columns', array( $this, 'columns' ) );
		add_action( 'manage_api_product_posts_custom_column', array( $this, 'custom_columns' ), 2 );
		add_action( 'admin_enqueue_scripts', array( $this, 'admin_enqueue_scripts' ) );
		add_action( 'init', array( $this, 'register_post_types' ) );
		add_action( 'add_meta_boxes', array( $this, 'add_meta_boxes' ) );
		add_action( 'save_post', array( $this, 'save_post' ), 1, 2 );
		add_action( 'wp_plugin_licencing_save_api_product', array( $this, 'save_api_product_data' ), 20, 2 );
		add_action( 'admin_menu', array( $this, 'remove_meta_boxes' ) );
	}

	/**
	 * Remove meta boxes
	 */
	public function remove_meta_boxes() {
		remove_meta_box( 'slugdiv', 'api_product', 'normal' );
	}

	/**
	 * enter_title_here function.
	 *
	 * @access public
	 * @return void
	 */
	public function enter_title_here( $text, $post ) {
		if ( $post->post_type === 'api_product' ) {
			return __( 'Plugin name', 'wp-plugin-licencing' );
		}
		return $text;
	}

	/**
	 * columns function.
	 *
	 * @access public
	 * @param mixed $columns
	 * @return void
	 */
	public function columns( $columns ) {
		if ( ! is_array( $columns ) ) {
			$columns = array();
		}

		unset( $columns['date'] );

		$columns["api_product"]  = __( "API Product ID", 'wp-plugin-licencing' );
		$columns["version"]      = __( "Version", 'wp-plugin-licencing' );
		$columns["last_updated"] = __( "Last updated", 'wp-plugin-licencing' );
		$columns["package"]      = __( "Package name", 'wp-plugin-licencing' );
		
		return $columns;
	}

	/**
	 * custom_columns function.
	 *
	 * @access public
	 * @param mixed $column
	 * @return void
	 */
	public function custom_columns( $column ) {
		global $post, $job_manager;

		switch ( $column ) {
			case "api_product" :
				echo '<code>' . $post->post_name . '</code>';
			break;
			case "version" :
			case "last_updated" :
				$data = get_post_meta( $post->ID, '_' . $column, true );
				echo esc_html( $data );
			break;
			case "package" :
				$data = get_post_meta( $post->ID, '_package', true );
				echo '<code>' . esc_html( basename( $data ) ) . '</code>';
			break;
		}
	}

	/**
	 * Scripts
	 */
	public function admin_enqueue_scripts() {
		wp_enqueue_style( 'wp_plugin_licencing_admin_css', WP_PLUGIN_LICENCING_PLUGIN_URL . '/assets/css/admin.css' );
		wp_enqueue_style( 'wp_plugin_licencing_menu_css', WP_PLUGIN_LICENCING_PLUGIN_URL . '/assets/css/menu.css' );
		wp_enqueue_media();
	}

	/**
	 * register_post_types function.
	 *
	 * @access public
	 * @return void
	 */
	public function register_post_types() {
		if ( post_type_exists( "api_product" ) ) {
			return;
		}

	    /**
		 * Post types
		 */
		$singular = __( 'API Product', 'wp-plugin-licencing' );
		$plural   = __( 'API Products', 'wp-plugin-licencing' );

		register_post_type( "api_product",
			apply_filters( "register_post_type_api_product", array(
				'labels' => array(
					'name'               => $plural,
					'singular_name'      => $singular,
					'menu_name'          => $plural,
					'all_items'          => sprintf( __( 'All %s', 'wp-plugin-licencing' ), $plural ),
					'add_new'            => __( 'Add New', 'wp-plugin-licencing' ),
					'add_new_item'       => sprintf( __( 'Add %s', 'wp-plugin-licencing' ), $singular ),
					'edit'               => __( 'Edit', 'wp-plugin-licencing' ),
					'edit_item'          => sprintf( __( 'Edit %s', 'wp-plugin-licencing' ), $singular ),
					'new_item'           => sprintf( __( 'New %s', 'wp-plugin-licencing' ), $singular ),
					'view'               => sprintf( __( 'View %s', 'wp-plugin-licencing' ), $singular ),
					'view_item'          => sprintf( __( 'View %s', 'wp-plugin-licencing' ), $singular ),
					'search_items'       => sprintf( __( 'Search %s', 'wp-plugin-licencing' ), $plural ),
					'not_found'          => sprintf( __( 'No %s found', 'wp-plugin-licencing' ), $plural ),
					'not_found_in_trash' => sprintf( __( 'No %s found in trash', 'wp-plugin-licencing' ), $plural ),
					'parent'             => sprintf( __( 'Parent %s', 'wp-plugin-licencing' ), $singular )
				),
				'description'         => __( 'This is where you can create and manage api products.', 'wp-plugin-licencing' ),
				'public'              => false,
				'show_ui'             => true,
				'capability_type'     => 'post',
				'publicly_queryable'  => false,
				'exclude_from_search' => true,
				'hierarchical'        => false,
				'rewrite'             => false,
				'query_var'           => false,
				'supports'            => array( 'title' ),
				'has_archive'         => false,
				'show_in_nav_menus'   => false
			) )
		);
	}

	/**
	 * readme_fields
	 *
	 * @access public
	 * @return array
	 */
	public function readme_fields() {
		global $post;

		return apply_filters( 'wp_plugin_licencing_readme_fields', array(
			'post_name' => array(
				'label'       => __( 'API Product ID', 'wp-plugin-licencing' ),
				'placeholder' => __( 'your-plugin-name', 'wp-plugin-licencing' ),
				'description' => __( 'A unique identifier for the API Product. Stored as the post_name.', 'wp-plugin-licencing' ),
				'type'        => 'text',
				'value'       => $post->post_name
			),
			'_version' => array(
				'label'       => __( 'Version', 'wp-plugin-licencing' ),
				'placeholder' => __( 'x.x.x', 'wp-plugin-licencing' ),
				'description' => __( 'The current version number of the plugin.', 'wp-plugin-licencing' )
			),
			'_last_updated' => array(
				'label'       => __( 'Date', 'wp-plugin-licencing' ),
				'placeholder' => __( 'yyyy-mm-dd', 'wp-plugin-licencing' ),
				'description' => __( 'The date of the last update.', 'wp-plugin-licencing' )
			),
			'_package' => array(
				'label'       => __( 'Package', 'wp-plugin-licencing' ),
				'type'        => 'file',
				'description' => __( 'The plugin package zip file.', 'wp-plugin-licencing' )
			),
			'_plugin_uri' => array(
				'label' => __( 'Plugin URI', 'wp-plugin-licencing' )
			),
			'_author' => array(
				'label'       => __( 'Author', 'wp-plugin-licencing' ),
				'placeholder' => ''
			),
			'_author_uri' => array(
				'label'       => __( 'Author URI', 'wp-plugin-licencing' ),
				'placeholder' => ''
			),
			'_requires_wp_version' => array(
				'label'       => __( 'Requries at least', 'wp-plugin-licencing' ),
				'placeholder' => __( 'e.g. 3.8', 'wp-plugin-licencing' )
			),
			'_tested_wp_version' => array(
				'label'       => __( 'Tested up to', 'wp-plugin-licencing' ),
				'placeholder' => __( 'e.g. 3.9', 'wp-plugin-licencing' )
			),
			'content' => array(
				'label'       => __( 'Description', 'wp-plugin-licencing' ),
				'placeholder' => __( 'Content describing the plugin', 'wp-plugin-licencing' ),
				'type'        => 'textarea',
				'value'       => $post->post_content
			),
			'_changelog' => array(
				'label' => __( 'Changelog', 'wp-plugin-licencing' ),
				'type'  => 'textarea'
			)
		) );
	}

	/**
	 * add_meta_boxes function.
	 *
	 * @access public
	 * @return void
	 */
	public function add_meta_boxes() {
		add_meta_box( 'api_product_data', __( 'API Product Data', 'wp-plugin-licencing' ), array( $this, 'api_product_data' ), 'api_product', 'normal', 'high' );
	}

	/**
	 * input_text function.
	 *
	 * @param mixed $key
	 * @param mixed $field
	 */
	public function input_file( $key, $field ) {
		global $thepostid;

		if ( empty( $field['value'] ) )
			$field['value'] = get_post_meta( $thepostid, $key, true );
		?>
		<p class="form-field">
			<label for="<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $field['label'] ) ; ?>:</label>
			<input type="text" class="file_url" name="<?php echo esc_attr( $key ); ?>" id="<?php echo esc_attr( $key ); ?>" placeholder="<?php echo esc_attr( $field['placeholder'] ); ?>" value="<?php echo esc_attr( $field['value'] ); ?>" />
			<button class="button upload_image_button" data-uploader_button_text="<?php _e( 'Use file', 'wp-plugin-licencing' ); ?>"><?php _e( 'Upload', 'wp-plugin-licencing' ); ?></button> <?php if ( ! empty( $field['description'] ) ) : ?><span class="description"><?php echo $field['description']; ?></span><?php endif; ?>
		</p>
		<script type="text/javascript">
			// Uploading files
			var file_frame;
			var file_target_input;

			jQuery('.upload_image_button').live('click', function( event ){

			    event.preventDefault();

			    file_target_input = jQuery( this ).closest('.form-field').find('.file_url');

			    // If the media frame already exists, reopen it.
			    if ( file_frame ) {
					file_frame.open();
					return;
			    }

			    // Create the media frame.
			    file_frame = wp.media.frames.file_frame = wp.media({
					title: jQuery( this ).data( 'uploader_title' ),
					button: {
						text: jQuery( this ).data( 'uploader_button_text' ),
					},
					multiple: false  // Set to true to allow multiple files to be selected
			    });

			    // When an image is selected, run a callback.
			    file_frame.on( 'select', function() {
					// We set multiple to false so only get one image from the uploader
					attachment = file_frame.state().get('selection').first().toJSON();

					jQuery( file_target_input ).val( attachment.url );
			    });

			    // Finally, open the modal
			    file_frame.open();
			});
		</script>
		<?php
	}

	/**
	 * input_text function.
	 *
	 * @param mixed $key
	 * @param mixed $field
	 */
	public function input_text( $key, $field ) {
		global $thepostid;

		if ( empty( $field['value'] ) )
			$field['value'] = get_post_meta( $thepostid, $key, true );
		?>
		<p class="form-field">
			<label for="<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $field['label'] ) ; ?>:</label>
			<input type="text" name="<?php echo esc_attr( $key ); ?>" id="<?php echo esc_attr( $key ); ?>" placeholder="<?php echo esc_attr( $field['placeholder'] ); ?>" value="<?php echo esc_attr( $field['value'] ); ?>" />
			<?php if ( ! empty( $field['description'] ) ) : ?><span class="description"><?php echo $field['description']; ?></span><?php endif; ?>
		</p>
		<?php
	}

	/**
	 * input_text function.
	 *
	 * @param mixed $key
	 * @param mixed $field
	 */
	public function input_textarea( $key, $field ) {
		global $thepostid;

		if ( empty( $field['value'] ) ) {
			$field['value'] = get_post_meta( $thepostid, $key, true );
		}
		?>
		<p class="form-field">
			<label for="<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $field['label'] ) ; ?>:</label>
			<textarea name="<?php echo esc_attr( $key ); ?>" id="<?php echo esc_attr( $key ); ?>" placeholder="<?php echo esc_attr( $field['placeholder'] ); ?>" cols="25" rows="3"><?php echo esc_html( $field['value'] ); ?></textarea>
			<?php if ( ! empty( $field['description'] ) ) : ?><span class="description"><?php echo $field['description']; ?></span><?php endif; ?>
		</p>
		<?php
	}
	
	/**
	 * input_select function.
	 *
	 * @param mixed $key
	 * @param mixed $field
	 */
	public function input_select( $key, $field ) {
		global $thepostid;

		if ( empty( $field['value'] ) )
			$field['value'] = get_post_meta( $thepostid, $key, true );
		?>
		<p class="form-field">
			<label for="<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $field['label'] ) ; ?>:</label>
			<select name="<?php echo esc_attr( $key ); ?>" id="<?php echo esc_attr( $key ); ?>">
				<?php foreach ( $field['options'] as $key => $value ) : ?>
				<option value="<?php echo esc_attr( $key ); ?>" <?php if ( isset( $field['value'] ) ) selected( $field['value'], $key ); ?>><?php echo esc_html( $value ); ?></option>
				<?php endforeach; ?>
			</select>
			<?php if ( ! empty( $field['description'] ) ) : ?><span class="description"><?php echo $field['description']; ?></span><?php endif; ?>
		</p>
		<?php
	}

	/**
	 * api_product_data function.
	 *
	 * @access public
	 * @param mixed $post
	 * @return void
	 */
	public function api_product_data( $post ) {
		global $post, $thepostid;

		$thepostid = $post->ID;

		echo '<div class="wp_plugin_licencing_meta_data">';

		wp_nonce_field( 'save_meta_data', 'wp_plugin_licencing_nonce' );

		do_action( 'wp_plugin_licencing_api_product_data_start', $thepostid );

		foreach ( $this->readme_fields() as $key => $field ) {
			$type = ! empty( $field['type'] ) ? $field['type'] : 'text';

			if ( method_exists( $this, 'input_' . $type ) ) {
				call_user_func( array( $this, 'input_' . $type ), $key, $field );
			} else {
				do_action( 'wp_plugin_licencing_input_' . $type, $key, $field );
			}
		}

		do_action( 'wp_plugin_licencing_api_product_data_end', $thepostid );

		echo '</div>';
	}

	/**
	 * save_post function.
	 *
	 * @access public
	 * @param mixed $post_id
	 * @param mixed $post
	 * @return void
	 */
	public function save_post( $post_id, $post ) {
		if ( empty( $post_id ) || empty( $post ) || empty( $_POST ) ) return;
		if ( defined('DOING_AUTOSAVE') && DOING_AUTOSAVE ) return;
		if ( is_int( wp_is_post_revision( $post ) ) ) return;
		if ( is_int( wp_is_post_autosave( $post ) ) ) return;
		if ( empty($_POST['wp_plugin_licencing_nonce']) || ! wp_verify_nonce( $_POST['wp_plugin_licencing_nonce'], 'save_meta_data' ) ) return;
		if ( ! current_user_can( 'edit_post', $post_id ) ) return;
		if ( $post->post_type != 'api_product' ) return;

		do_action( 'wp_plugin_licencing_save_api_product', $post_id, $post );
	}

	/**
	 * save_api_product_data function.
	 *
	 * @access public
	 * @param mixed $post_id
	 * @param mixed $post
	 * @return void
	 */
	public function save_api_product_data( $post_id, $post ) {
		global $wpdb;

		// Save fields
		foreach ( $this->readme_fields() as $key => $field ) {
			// Expirey date
			if ( '_last_updated' === $key ) {
				if ( ! empty( $_POST[ $key ] ) ) {
					update_post_meta( $post_id, $key, date( 'Y-m-d', strtotime( sanitize_text_field( $_POST[ $key ] ) ) ) );
				} else {
					update_post_meta( $post_id, $key, date( 'Y-m-d' ) );
				}
			}

			elseif ( 'content' === $key ) {
				continue;
			}

			elseif ( 'post_name' === $key ) {
				continue;
			}

			// Everything else
			else {
				$type = ! empty( $field['type'] ) ? $field['type'] : '';

				switch ( $type ) {
					case 'textarea' :
						update_post_meta( $post_id, $key, wp_kses_post( stripslashes( $_POST[ $key ] ) ) );
					break;
					default : 
						if ( is_array( $_POST[ $key ] ) ) {
							update_post_meta( $post_id, $key, array_map( 'sanitize_text_field', $_POST[ $key ] ) );
						} else {
							update_post_meta( $post_id, $key, sanitize_text_field( $_POST[ $key ] ) );
						}
					break;
				}
			}
		}

		delete_transient( 'plugininfo_' . md5( $post->post_name . $plugin_version ) );
	}	
}

new WP_Plugin_Licencing_Post_Types();