<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Download handler
 */
class WP_Plugin_Licencing_Download_Handler {

	/**
	 * Constructor
	 */
	public function __construct() {
		add_action( 'init', array( $this, 'download_api_product' ) );
	}

	/**
	 * Check if we need to download a file and check validity
	 */
	public function download_api_product() {
		if ( isset( $_GET['download_api_product'] ) && isset( $_GET['licence_key'] ) ) {
			$download_api_product = absint( $_GET['download_api_product'] );
			$licence_key          = sanitize_text_field( $_GET['licence_key'] );
			$activation_email     = sanitize_text_field( $_GET['activation_email'] );
			$licence              = wppl_get_licence_from_key( $licence_key );

			// Validation
			if ( ! $licence ) {
				wp_die( __( 'Invalid or expired licence key.', 'wp-plugin-licencing' ) );
			}
			if ( is_user_logged_in() && $licence->user_id && $licence->user_id != get_current_user_id() ) {
				wp_die( __( 'This licence does not appear to be yours.', 'wp-plugin-licencing' ) );
			}
			if ( ! is_email( $activation_email ) || $activation_email != $licence->activation_email ) {
				wp_die( __( 'Invalid activation email address.', 'wp-plugin-licencing' ) );
			}
			if ( ! in_array( $download_api_product, wppl_get_licence_api_product_permissions( $licence->product_id ) ) ) {
				wp_die( __( 'This licence does not allow access to the requested product.', 'wp-plugin-licencing' ) );
			}

			// Get the download URL
			$file_path = wppl_get_package_file_path( $download_api_product );

			// Download it!
			$this->download( $file_path );
		}
	}

	/**
	 * Download a file - hooked into init function.
	 */
	public function download( $file_path ) {
		global $wpdb, $is_IE;

		if ( ! $file_path ) {
			wp_die( __( 'No file defined', 'wp-plugin-licencing' ) . ' <a href="' . esc_url( home_url() ) . '" class="wc-forward">' . __( 'Go to homepage', 'wp-plugin-licencing' ) . '</a>' );
		}

		$remote_file      = true;
		$parsed_file_path = parse_url( $file_path );
		
		$wp_uploads       = wp_upload_dir();
		$wp_uploads_dir   = $wp_uploads['basedir'];
		$wp_uploads_url   = $wp_uploads['baseurl'];

		if ( ( ! isset( $parsed_file_path['scheme'] ) || ! in_array( $parsed_file_path['scheme'], array( 'http', 'https', 'ftp' ) ) ) && isset( $parsed_file_path['path'] ) && file_exists( $parsed_file_path['path'] ) ) {

			/** This is an absolute path */
			$remote_file  = false;

		} elseif( strpos( $file_path, $wp_uploads_url ) !== false ) {

			/** This is a local file given by URL so we need to figure out the path */
			$remote_file  = false;
			$file_path    = str_replace( $wp_uploads_url, $wp_uploads_dir, $file_path );

		} elseif( is_multisite() && ( strpos( $file_path, network_site_url( '/', 'http' ) ) !== false || strpos( $file_path, network_site_url( '/', 'https' ) ) !== false ) ) {

			/** This is a local file outside of wp-content so figure out the path */
			$remote_file = false;
			// Try to replace network url
            $file_path   = str_replace( network_site_url( '/', 'https' ), ABSPATH, $file_path );
            $file_path   = str_replace( network_site_url( '/', 'http' ), ABSPATH, $file_path );
            // Try to replace upload URL
            $file_path   = str_replace( $wp_uploads_url, $wp_uploads_dir, $file_path );

		} elseif( strpos( $file_path, site_url( '/', 'http' ) ) !== false || strpos( $file_path, site_url( '/', 'https' ) ) !== false ) {

			/** This is a local file outside of wp-content so figure out the path */
			$remote_file = false;
			$file_path   = str_replace( site_url( '/', 'https' ), ABSPATH, $file_path );
			$file_path   = str_replace( site_url( '/', 'http' ), ABSPATH, $file_path );

		} elseif ( file_exists( ABSPATH . $file_path ) ) {
			
			/** Path needs an abspath to work */
			$remote_file = false;
			$file_path   = ABSPATH . $file_path;
		}

		if ( ! $remote_file ) {
			// Remove Query String
			if ( strstr( $file_path, '?' ) ) {
				$file_path = current( explode( '?', $file_path ) );
			}

			// Run realpath
			$file_path = realpath( $file_path );
		}

		// Get extension and type
		$file_extension  = strtolower( substr( strrchr( $file_path, "." ), 1 ) );
		$ctype           = "application/force-download";

		foreach ( get_allowed_mime_types() as $mime => $type ) {
			$mimes = explode( '|', $mime );
			if ( in_array( $file_extension, $mimes ) ) {
				$ctype = $type;
				break;
			}
		}

		// Start setting headers
		if ( ! ini_get('safe_mode') ) {
			@set_time_limit(0);
		}

		if ( function_exists( 'get_magic_quotes_runtime' ) && get_magic_quotes_runtime() ) {
			@set_magic_quotes_runtime(0);
		}

		if ( function_exists( 'apache_setenv' ) ) {
			@apache_setenv( 'no-gzip', 1 );
		}

		@session_write_close();
		@ini_set( 'zlib.output_compression', 'Off' );

		/**
		 * Prevents errors, for example: transfer closed with 3 bytes remaining to read
		 */
		@ob_end_clean(); // Clear the output buffer

		if ( ob_get_level() ) {

			$levels = ob_get_level();

			for ( $i = 0; $i < $levels; $i++ ) {
				@ob_end_clean(); // Zip corruption fix
			}

		}

		if ( $is_IE && is_ssl() ) {
			// IE bug prevents download via SSL when Cache Control and Pragma no-cache headers set.
			header( 'Expires: Wed, 11 Jan 1984 05:00:00 GMT' );
			header( 'Cache-Control: private' );
		} else {
			nocache_headers();
		}

		$filename = basename( $file_path );

		if ( strstr( $filename, '?' ) ) {
			$filename = current( explode( '?', $filename ) );
		}

		header( "X-Robots-Tag: noindex, nofollow", true );
		header( "Content-Type: " . $ctype );
		header( "Content-Description: File Transfer" );
		header( "Content-Disposition: attachment; filename=\"" . $filename . "\";" );
		header( "Content-Transfer-Encoding: binary" );

        if ( $size = @filesize( $file_path ) ) {
        	header( "Content-Length: " . $size );
        }

		/*if ( ! $remote_file ) {
			// Path fix - kudos to Jason Judge
         	if ( getcwd() ) {
         		$sendfile_file_path = trim( preg_replace( '`^' . str_replace( '\\', '/', getcwd() ) . '`' , '', $file_path ), '/' );
         	}

            header( "Content-Disposition: attachment; filename=\"" . $filename . "\";" );

            if ( function_exists( 'apache_get_modules' ) && in_array( 'mod_xsendfile', apache_get_modules() ) ) {
            	header("X-Sendfile: $sendfile_file_path");
            	exit;
            } elseif ( stristr( getenv( 'SERVER_SOFTWARE' ), 'lighttpd' ) ) {
            	header( "X-Lighttpd-Sendfile: $sendfile_file_path" );
            	exit;
            } elseif ( stristr( getenv( 'SERVER_SOFTWARE' ), 'nginx' ) || stristr( getenv( 'SERVER_SOFTWARE' ), 'cherokee' ) ) {
            	header( "X-Accel-Redirect: /$sendfile_file_path" );
            	exit;
            }
        }*/

        if ( $remote_file ) {
        	$this->readfile_chunked( $file_path ) or header( 'Location: ' . $file_path );
        } else {
        	$this->readfile_chunked( $file_path ) or wp_die( __( 'File not found', 'wp-plugin-licencing' ) . ' <a href="' . esc_url( home_url() ) . '" class="wc-forward">' . __( 'Go to homepage', 'wp-plugin-licencing' ) . '</a>' );
        }

        exit;
	}

	/**
	 * readfile_chunked
	 * Reads file in chunks so big downloads are possible without changing PHP.INI - http://codeigniter.com/wiki/Download_helper_for_large_files/
	 * @param    string $file
	 * @param    bool   $retbytes return bytes of file
	 * @return bool|int
	 * @todo Meaning of the return value? Last return is status of fclose?
	 */
	public static function readfile_chunked( $file, $retbytes = true ) {
		$chunksize = 1 * ( 1024 * 1024 );
		$buffer = '';
		$cnt = 0;

		$handle = @fopen( $file, 'r' );
		if ( $handle === FALSE ) {
			return FALSE;
		}

		while ( ! feof( $handle ) ) {
			$buffer = fread( $handle, $chunksize );
			echo $buffer;
			@ob_flush();
			@flush();

			if ( $retbytes ) {
				$cnt += strlen( $buffer );
			}
		}

		$status = fclose( $handle );

		if ( $retbytes && $status ) {
			return $cnt;
		}

		return $status;
	}
}

new WP_Plugin_Licencing_Download_Handler();