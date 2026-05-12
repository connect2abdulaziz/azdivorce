<?php

namespace WPKIN_THANK_YOU_PAGE;

/**
 * Assets Handeler class
 */
class Assets {

	/**
	 * Assets constructor.
	 */
	public function __construct() {
		add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_assets' ] );
	}

	/**
	 * Assets enqueue_assets.
	 */
	public function enqueue_assets() {

		$current_screen = get_current_screen()->id;
		$current_page   = isset( $_GET['page'] ) ? sanitize_text_field( wp_unslash( $_GET['page'] ) ) : '';

		if ( 'toplevel_page_wc-thank-you-page' === $current_screen && 'wc-thank-you-page' === $current_page ) {
			//CSS
			wp_enqueue_style( 'wpkin-thank-you-admin-style', WPKIN_THANKYOU_URL . '/build/admin.bundle.css', [], filemtime( WPKIN_THANKYOU_PATH . 'build/admin.bundle.css' ), false );
			//JS
			wp_enqueue_script( 'wpkin-thank-you-admin-js', WPKIN_THANKYOU_URL . '/build/admin.bundle.js', [ 'wp-i18n', 'wp-element', 'jquery', 'wp-api' ], filemtime( WPKIN_THANKYOU_PATH . 'build/admin.bundle.js' ), true );
		
			wp_localize_script(
				'wpkin-thank-you-admin-js',
				'wpkinThankYouPage',
				array(
					'root'           => get_site_url(),
					'nonce'          => wp_create_nonce( 'wp_rest' ),
					'versioncontrol' => Helper::wpkin_tr_plugins_version_control(),
				)
			);
		}
		if ( 'thankredirect_page_wc-thank-you-page-getting-started' === $current_screen && 'wc-thank-you-page-getting-started' === $current_page ) {
			//CSS
			wp_enqueue_style( 'wpkin-thank-you-getting-style', WPKIN_THANKYOU_URL . '/build/gettingstarted.bundle.css', [], filemtime( WPKIN_THANKYOU_PATH . 'build/gettingstarted.bundle.css' ), false );
			//JS
			wp_enqueue_script( 'wpkin-thank-you-getting-js', WPKIN_THANKYOU_URL . '/build/gettingstarted.bundle.js', [ 'wp-i18n', 'wp-element', 'jquery', 'wp-api' ], filemtime( WPKIN_THANKYOU_PATH . 'build/gettingstarted.bundle.js' ), true );

			wp_localize_script(
				'wpkin-thank-you-getting-js',
				'wpkinThankYouPage',
				array(
					'plugin_url' => WPKIN_THANKYOU_URL,
				)
			);
		}

		wp_enqueue_style( 'wpkin-tr-admin-style', WPKIN_THANKYOU_URL . '/assets/css/wpkin-tr-admin.css', [], filemtime( WPKIN_THANKYOU_PATH . 'assets/css/wpkin-tr-admin.css' ), false );
	}

}