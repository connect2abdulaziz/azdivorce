<?php

namespace WPKIN_THANK_YOU_PAGE\Admin;

/**
 * Admin Notice Handler Class
 */
class Notice {

	/**
	 * Notice Constructor
	 */
	public function __construct() {

		add_action( 'init', [ $this, 'i18n' ] );
		if ( is_admin() ) :
			$this->notice_init();
		endif;

	}


	/**
	 * Load Textdomain
	 *
	 * Load plugin localization files.
	 *
	 * Fired by `init` action hook.
	 *
	 * @since 1.0.0
	 *
	 * @access public
	 */
	public function i18n() {
		load_plugin_textdomain( 'wc-thank-you-page', false, dirname( plugin_basename( WPKIN_THANKYOU_FILE ) ) . '/languages/' );
	}


	/**
	 * Initialize the plugin
	 *
	 * Load the plugin only after WooCommerce (and other plugins) are loaded.
	 * Checks for basic plugin requirements, if one check fail don't continue,
	 * if all check have passed load the files required to run the plugin.
	 *
	 * @since 1.0.0
	 *
	 * @access public
	 */
	public function notice_init() {

		// Check for required PHP version.
		if ( version_compare( PHP_VERSION, WPKIN_THANKYOU_MINIMUM_PHP_VERSION, '<' ) ) {
			add_action( 'admin_notices', [ $this, 'wpte_thankyou_page_minimum_php_version' ] );
			return;
		}

		// Check if WooCommerce installed and activated.
		if ( ! class_exists( 'WooCommerce' ) ) {
			add_action( 'admin_notices', [ $this, 'admin_notice_missing_main_plugin' ] );
			return;
		}

		// Check for required Woocommerce version.
		if ( version_compare( WC_VERSION, WPKIN_THANKYOU_MINIMUM_WC_VERSION, '<' ) ) {
			add_action( 'admin_notices', [ $this, 'wpte_thankyou_page_minimum_wc_version' ] );
			return;
		}

	}

	/**
	 * Admin notice
	 *
	 * Warning when the site doesn't have a minimum required PHP version.
	 *
	 * @since 1.0.0
	 *
	 * @access public
	 */
	public function wpte_thankyou_page_minimum_php_version() {

		$message = sprintf(
			/* translators: 1: Plugin name 2: PHP 3: Required PHP version */
			esc_html__('"%1$s" requires "%2$s" version %3$s or greater.', 'wc-thank-you-page'),
			'<strong>' . esc_html__('WC Thank You Page', 'wc-thank-you-page') . '</strong>',
			'<strong>' . esc_html__('PHP', 'wc-thank-you-page') . '</strong>',
			WPKIN_THANKYOU_MINIMUM_PHP_VERSION
		);

		printf( '<div class="notice notice-warning is-dismissible"><p>%s</p></div>', wp_kses_post( $message ) );
	}

	/**
	 * Admin notice
	 *
	 * Warning when the site doesn't have WooCommerce installed or activated.
	 *
	 * @since 1.0.0
	 *
	 * @access public
	 */
	public function admin_notice_missing_main_plugin() {

		$screen = get_current_screen();
		if ( isset( $screen->parent_file ) && 'plugins.php' === $screen->parent_file && 'update' === $screen->id ) {
			return;
		}

		$plugin            = 'WooCommerce';
		$file_path         = 'woocommerce/woocommerce.php';
		$installed_plugins = get_plugins();

		if ( isset( $installed_plugins[ $file_path ] ) ) { // Check if plugin is installed.
			if ( ! current_user_can( 'activate_plugins') ) {
				return;
			}
			$activation_url = wp_nonce_url(admin_url('plugins.php?action=activate&plugin=' . $file_path), 'activate-plugin_' . $file_path);

			$message = '<p><strong>' . esc_html__('WC Thank You Page', 'wc-thank-you-page') . '</strong>' . __(' not working because you need to activate the WooCommerce plugin.', 'wc-thank-you-page') . '</p>';
			$message .= '<p>' . sprintf('<a href="%s" class="button-primary">%s</a>', $activation_url, esc_html__('Activate WooCommerce Now', 'wc-thank-you-page')) . '</p>';
		} else {
			if ( ! current_user_can( 'install_plugins' ) ) {
				return;
			}
			$install_url = wp_nonce_url( add_query_arg( [ 'action' => 'install-plugin', 'plugin' => $plugin ],
			admin_url( 'update.php' ) ),
			'install-plugin_' . $plugin
			);
			$message     = '<p><strong>' . esc_html__('WC Thank You Page', 'wc-thank-you-page') . '</strong>' . __(' not working because you need to install the WooCommerce plugin', 'wc-thank-you-page') . '</p>';
			$message    .= '<p>' . sprintf('<a href="%s" class="button-primary">%s</a>', $install_url, esc_html__('Install WooCommerce Now', 'wc-thank-you-page')) . '</p>';
		}

		echo '<div class="error"><p>' . $message . '</p></div>';

	}

	/**
	 * Admin notice
	 *
	 * Warning when the site doesn't have a minimum required WooCommerce version.
	 *
	 * @since 1.0.0
	 *
	 * @access public
	 */
	public function wpte_thankyou_page_minimum_wc_version() {

		$message = sprintf(
			/* translators: 1: Plugin name 2: WooCommerce 3: Required WooCommerce version */
			esc_html__('"%1$s" requires "%2$s" version %3$s or greater.', 'wc-thank-you-page'),
			'<strong>' . esc_html__('WC Thank You Page', 'wc-thank-you-page') . '</strong>',
			'<strong>' . esc_html__('WooCommerce', 'wc-thank-you-page') . '</strong>',
			WPKIN_THANKYOU_MINIMUM_WC_VERSION
		);

		printf( '<div class="notice notice-warning is-dismissible"><p>%s</p></div>', wp_kses_post( $message ) );
	}

}