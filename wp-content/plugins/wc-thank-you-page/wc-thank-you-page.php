<?php

/**
 * Plugin Name:       ThankRedirect
 * Plugin URI:        https://wpking.com
 * Description:       Take control of your WooCommerce Thank You page by redirecting customers to a custom URL after purchase. Boost engagement and conversions post-checkout.
 * Version:           2.0.1
 * Author:            WPKIN
 * Author URI:        https://wpking.com
 * Text Domain:       wc-thank-you-page
 * License:           GPLv2 or later
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
 */
if ( !defined( 'ABSPATH' ) ) {
    wp_die( esc_html__( 'You can\'t access this page', ' wc-thank-you-page' ) );
}

if ( function_exists( 'wpkin_tyr_v' ) ) {
    wpkin_tyr_v()->set_basename( false, __FILE__ );
} else {
    /**
     * Included Autoload File
     */
    require_once __DIR__ . '/vendor/autoload.php';
    if ( ! function_exists( 'wpkin_tyr_v' ) ) {
		// Create a helper function for easy SDK access.
		function wpkin_tyr_v() {
			global $wpkin_tyr_v;

			if ( ! isset( $wpkin_tyr_v ) ) {
				// Include Freemius SDK.
				// SDK is auto-loaded through composer
				$wpkin_tyr_v = fs_dynamic_init( array(
					'id'                  => '19790',
					'slug'                => 'wc-thank-you-page',
					'premium_slug'        => 'wc-thank-you-page-pro',
					'type'                => 'plugin',
					'public_key'          => 'pk_210b92fd8cf84fcf0b3285f2cf0e4',
					'is_premium'          => false,
					'has_premium_version' => false,
					'has_addons'          => false,
					'has_paid_plans'      => true,
					'trial'               => array(
						'days'               => 7,
						'is_require_payment' => true,
					),
					'menu' => array(
						'slug'           => 'wc-thank-you-page',
						'first-path'     => 'admin.php?page=wc-thank-you-page-getting-started',
						'contact'        => false,
						'support'        => false,
					),
				) );
			}

			return $wpkin_tyr_v;
		}

		// Init Freemius.
		wpkin_tyr_v();
		// Signal that SDK was initiated.
		do_action( 'wpkin_tyr_v_loaded' );
	}

    /** If class `WPKIN_ThankYou_Page` doesn't exists yet. */
    if ( !class_exists( 'WPKIN_ThankYou_Page' ) ) {
        /**
         * Sets up and initializes the plugin.
         * Main initiation class
         *
         * @since 1.0.0
         */
        final class WPKIN_ThankYou_Page {
            /**
             * Plugin Version
             */
            const VERSION = '2.0.1';

            /**
             * Php Version
             */
            const MIN_PHP_VERSION = '7.3';

            /**
             * WC Version
             */
            const MIN_WC_VERSION = '7.9.0';

            /**
             * WordPress Version
             */
            const MIN_WP_VERSION = '6.2';

            /**
             * Class Constructor
             */
            private function __construct() {
                $this->define_constants();
                register_activation_hook( __FILE__, [$this, 'activate'] );
                add_action( 'plugins_loaded', [$this, 'init_plugin'] );
                add_action( 'admin_init', [$this, 'activation_redirect'] );
                add_filter( 'plugin_action_links_' . plugin_basename( WPKIN_THANKYOU_FILE ), [__CLASS__, 'wpkin_thankyou_page_action_links'] );
            }

            /**
             * Initialize a singleton instance
             *
             * @return WPKIN_ThankYou_Page
             */
            public static function init() {
                static $instance = false;
                if ( !$instance ) {
                    $instance = new self();
                }
                return $instance;
            }

            /**
             * Method define_constants.
             * Plugin Constants.
             */
            public function define_constants() {
                define( 'WPKIN_THANKYOU_VERSION', self::VERSION );
                define( 'WPKIN_THANKYOU_FILE', __FILE__ );
                define( 'WPKIN_THANKYOU_PATH', plugin_dir_path( __FILE__ ) );
                define( 'WPKIN_THANKYOU_URL', plugins_url( '', WPKIN_THANKYOU_FILE ) );
                define( 'WPKIN_THANKYOU_ASSETS', WPKIN_THANKYOU_URL . '/assets' );
                define( 'WPKIN_THANKYOU_MINIMUM_PHP_VERSION', self::MIN_PHP_VERSION );
                define( 'WPKIN_THANKYOU_MINIMUM_WC_VERSION', self::MIN_WC_VERSION );
                define( 'WPKIN_THANKYOU_MINIMUM_WP_VERSION', self::MIN_WP_VERSION );
            }

            /**
             * After activate Plugin
             */
            public function activate() {
                $installed = get_option( 'WPKIN_thankyou_installed' );
                if ( !$installed ) {
                    update_option( 'WPKIN_thankyou_installed', time() );
                }
                update_option( 'WPKIN_thankyou_version', WPKIN_THANKYOU_VERSION );
                add_option( 'WPKIN_thankyou_page_activation_redirect', true );
            }

            /**
             * Plugins Loaded
             */
            public function init_plugin() {
                new WPKIN_THANK_YOU_PAGE\Assets();
                new WPKIN_THANK_YOU_PAGE\Api();
                new WPKIN_THANK_YOU_PAGE\Shortcodes();
                new WPKIN_THANK_YOU_PAGE\Templates();
                if ( is_admin() ) {
                    new WPKIN_THANK_YOU_PAGE\Admin();
                } else {
                    new WPKIN_THANK_YOU_PAGE\Frontend();
                }
            }

            /**
             * Redirect to settings page after activation the plugin
             */
            public function activation_redirect() {
				if ( get_option( 'WPKIN_thankyou_page_activation_redirect', false ) ) {
					delete_option('WPKIN_thankyou_page_activation_redirect');

					// Only redirect if Freemius is not in activation mode
					if ( function_exists( 'wpkin_tyr_v' ) && ! wpkin_tyr_v()->is_activation_mode() ) {
						wp_safe_redirect(admin_url('admin.php?page=wc-thank-you-page-getting-started'));
						exit;
					}
				}
			}

            /**
             * Plugin Page Settings menu.
             *
             * @param mixed $links .
             */
            public static function wpkin_thankyou_page_action_links( $links ) {
                if ( !current_user_can( 'manage_options' ) ) {
                    return $links;
                }
                $links = array_merge( [sprintf( '<a href="%s">%s</a>', admin_url( 'admin.php?page=wc-thank-you-page' ), esc_html__( 'Settings', 'wc-thank-you-page' ) )], $links );
                return $links;
            }

        }

    }

    /**
     * Initialize the main plugin
     *
     * @return WPKIN_ThankYou_Page|null
     */
    function wpkin_thankyou() {
        if ( class_exists( 'WPKIN_ThankYou_Page' ) ) {
            return WPKIN_ThankYou_Page::init();
        }
    }

    /**
     * Kick-off the plugin
     */
    wpkin_thankyou();
}