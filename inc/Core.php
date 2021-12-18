<?php

namespace Woo_Downloadable_Access;

class Core {

	/**
	 * @var string
	 */
	private $cookie_key;
	private $transient_key;

	/**
	 * @var string
	 */
	private $htaccess_dir;
	private $htaccess_file;

	/**
	 * @var array
	 */
	private $notices;

	public function __construct() {

		$this->cookie_key    = 'woo_downloadable_access';
		$this->transient_key = 'woo_downloadable_access';

		$upload_dir = wp_get_upload_dir();

		$this->htaccess_dir  = $upload_dir['basedir'] . '/woocommerce_uploads';
		$this->htaccess_file = $upload_dir['basedir'] . '/woocommerce_uploads/.htaccess';

		$this->notices = [
			100  => esc_html__( 'WooCommerce is not installed or activated.',                                   'woo-downloadable-access' ),
			1000 => esc_html__( 'the directory "wp-content/uploads/woocommerce_uploads" does not exist.',       'woo-downloadable-access' ),
			1001 => esc_html__( 'the directory "wp-content/uploads/woocommerce_uploads" is not writable.',      'woo-downloadable-access' ),
			2001 => esc_html__( 'the file "wp-content/uploads/woocommerce_uploads/.htaccess" is not writable.', 'woo-downloadable-access' ),
		];

	}

	public function init(): void {

		add_action( 'init',       [ $this, 'load_translations' ] );
		add_action( 'admin_init', [ $this, 'setup' ] );
		add_action( 'wp_logout',  [ $this, 'clear_cookie' ] );

	}

	public function load_translations(): void {

		load_plugin_textdomain( 'woo-downloadable-access', false, dirname( PLUGIN_BASE ) . '/languages' );

	}

	/**
	 * Sets the transient and the cookie and modifies the .htaccess rules
	 */
	public function setup(): void {

		if ( ! $this->is_compatible() ) {
			return;
		}

		$user = wp_get_current_user();

		if ( ! in_array( 'administrator', $user->roles ) && ! in_array( 'shop_manager', $user->roles ) ) {

			$this->clear_cookie();
			return;

		}

		// Gets the transient timeout before a new one is set
		$transient_timeout = get_option( '_transient_timeout_' . $this->transient_key );

		// Sets the transient
		if ( false === ( $random_string = get_transient( $this->transient_key ) ) ) {

			$random_string = $this->generate_random_string( 60 );

			set_transient( $this->transient_key, $random_string, DAY_IN_SECONDS );

		}

		// If the cookie is not set or the value is not equal to the transient value
		if ( ! isset( $_COOKIE[ $this->cookie_key ] ) || $_COOKIE[ $this->cookie_key ] !== $random_string ) {

			// Sets the cookie
			setcookie( $this->cookie_key, $random_string, 0, '/', '', isset( $_SERVER['HTTPS'] ), true );

		}

		if ( $transient_timeout ) {

			// If the transient is not expired there is no need to update the .htaccess file
			if ( (int) $transient_timeout > time() ) {
				return;
			}

		}

		// .htaccess rules
		$rules = <<<STRING
RewriteCond %{REQUEST_URI} ^/ [NC]
RewriteCond %{HTTP_COOKIE} !$this->cookie_key=$random_string;? [NC]
RewriteRule .* - [L,F]
STRING;

		// Writes to .htaccess
		file_put_contents( $this->htaccess_file, $rules );

	}

	/**
	 * Generates a random string
	 *
	 * @param int $length
	 *
	 * @return string
	 */
	private function generate_random_string( int $length ): string {

		$characters        = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
		$characters_length = strlen( $characters );
		$random_string     = '';

		for ( $i = 0; $i < $length; $i++ ) {

			$random_string .= $characters[ rand( 0, $characters_length - 1 ) ];

		}

		return $random_string;

	}

	/**
	 * Checks dependencies
	 *
	 * @return bool
	 */
	private function is_compatible(): bool {

		$notices = [];

		if ( ! class_exists( 'WooCommerce' ) ) {

			$notices[] = 100;

			$this->display_admin_notices( $notices );

			if ( isset( $_GET['activate'] ) ) {
				unset( $_GET['activate'] );
			}

			deactivate_plugins( PLUGIN_BASE );

			return empty( $notices );

		}

		if ( ! file_exists( $this->htaccess_dir ) ) {
			$notices[] = 1000;
		}

		if ( ! is_writable( $this->htaccess_dir ) ) {
			$notices[] = 1001;
		}

		if ( ! is_writable( $this->htaccess_file ) ) {
			$notices[] = 2001;
		}

		$this->display_admin_notices( $notices );

		return empty( $notices );

	}

	/**
	 * Displays admin notices
	 *
	 * @param $notices
	 */
	private function display_admin_notices( $notices ): void {

		if ( ! empty( $notices ) ) {

			foreach ( $notices as $key ) {

				add_action( 'admin_notices', function() use ( $key ) {

					echo '<div class="notice notice-error is-dismissible"><p><strong>' . PLUGIN_NAME . ':</strong> ' . $this->notices[ $key ] . '</p></div>';

				} );

			}

		}

	}

	/**
	 * Clears the cookie
	 */
	public function clear_cookie(): void {

		if ( isset( $_COOKIE[ $this->cookie_key ] ) ) {

			setcookie( $this->cookie_key, 0, 0, '/', '', isset( $_SERVER['HTTPS'] ), true );

		}

	}

	/**
	 * Backs up the .htaccess file on plugin activation and saves the path in the database
	 */
	public function activate(): void {

		if ( ! class_exists( 'WooCommerce' ) || ! file_exists( $this->htaccess_dir ) || ! is_writable( $this->htaccess_dir ) ) {
			return;
		}

		$htaccess_backup = $this->htaccess_file . '-backup-' . time();

		$result = copy( $this->htaccess_file, $htaccess_backup );

		if ( $result ) {

			update_option( PLUGIN_SLUG . '_htaccess_backup', $htaccess_backup, false );

		}

	}

	/**
	 * Runs cleanup tasks on plugin deactivation
	 * Deletes transient, clears the cookie and restores the default .htaccess
	 */
	public function deactivate(): void {

		delete_transient( $this->transient_key );

		$this->clear_cookie();

		if ( ! file_exists( $this->htaccess_file ) || ! is_writable( $this->htaccess_file ) ) {
			return;
		}

		unlink( $this->htaccess_file );

		$htaccess_backup = get_option( PLUGIN_SLUG . '_htaccess_backup' );

		if ( $htaccess_backup && file_exists( $htaccess_backup ) && is_writable( $htaccess_backup ) ) {

			rename( $htaccess_backup, $this->htaccess_file );

		} else {

			file_put_contents( $this->htaccess_file, 'deny from all' );

		}

	}

}
