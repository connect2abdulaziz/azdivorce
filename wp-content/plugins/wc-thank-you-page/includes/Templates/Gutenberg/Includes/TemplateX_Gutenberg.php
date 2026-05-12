<?php
namespace WPKIN_THANK_YOU_PAGE\Templates\Gutenberg\Includes;
/**
 * TemplateX Gutenberg Integration
 * 
 * Handles all Gutenberg-specific integration
 * 
 * @package TemplateX
 * @since 1.0.0
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class TemplateX_Gutenberg {

    /**
     * Instance
     *
     * @var TemplateX_Gutenberg
     */
    private static $instance = null;

    /**
     * Get instance
     */
    public static function instance() {
        if ( is_null( self::$instance ) ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Constructor
     */
    function __construct() {
        $this->init_hooks();
    }

    /**
     * Initialize hooks
     */
    private function init_hooks() {
        // Enqueue editor scripts and styles
        add_action( 'enqueue_block_editor_assets', array( $this, 'enqueue_editor_scripts' ) );
    }

    /**
     * Enqueue editor scripts
     */
    public function enqueue_editor_scripts() {
        // Enqueue Gutenberg integration script
        wp_enqueue_script(
            'templatex-gutenberg-integration',
            WPKIN_THANKYOU_URL . '/includes/Templates/Gutenberg/assets/js/gutenberg-integration.js',
            array( 'wp-blocks', 'wp-element', 'wp-editor', 'wp-components', 'jquery' ),
            filemtime( WPKIN_THANKYOU_PATH . 'includes/Templates/Gutenberg/assets/js/gutenberg-integration.js' ),
            true
        );

        // Enqueue modal script
        wp_enqueue_script(
            'templatex-modals',
            WPKIN_THANKYOU_URL . '/includes/Templates/Gutenberg/assets/js/modal.js',
            array( 'jquery', 'templatex-gutenberg-integration' ),
            filemtime( WPKIN_THANKYOU_PATH . 'includes/Templates/Gutenberg/assets/js/modal.js' ),
            true
        );

        // Enqueue styles
        wp_enqueue_style(
            'templatex-modal',
            WPKIN_THANKYOU_URL . '/includes/Templates/Gutenberg/assets/css/modal.css',
            array(),
            filemtime( WPKIN_THANKYOU_PATH . 'includes/Templates/Gutenberg/assets/css/modal.css' )
        );

        // Localize script with data
        wp_localize_script( 'templatex-gutenberg-integration', 'templatexData', array(
            'ajaxUrl'     => admin_url( 'admin-ajax.php' ),
            'restUrl'     => rest_url( 'templatex/v1' ),
            'nonce'       => wp_create_nonce( 'wp_rest' ),
            'pluginUrl'   => WPKIN_THANKYOU_URL . '/includes/Templates/Gutenberg/',
            'plugin_logo' => WPKIN_THANKYOU_URL . '/images/dash-icon-24x24.svg',
            'siteUrl'     => home_url(),
            'i18n'        => array(
                'modalTitle'       => __( 'Thank Redirect', 'wc-thank-you-page' ),
                'searchPlaceholder' => __( 'Search', 'wc-thank-you-page' ),
                'preview'          => __( 'Preview', 'wc-thank-you-page' ),
                'insert'           => __( 'Insert', 'wc-thank-you-page' ),
                'free'             => __( 'Free', 'wc-thank-you-page' ),
                'pro'              => __( 'Pro', 'wc-thank-you-page' ),
                'upgrade'          => __( 'Get Pro', 'wc-thank-you-page' ),
                'proMessage'       => __( 'This is a Pro template. Upgrade to unlock!', 'wc-thank-you-page' ),
                'noResults'        => __( 'No templates found.', 'wc-thank-you-page' ),
                'loading'          => __( 'Loading templates...', 'wc-thank-you-page' ),
                'insertSuccess'    => __( 'Template inserted successfully!', 'wc-thank-you-page' ),
                'insertError'      => __( 'Error inserting template. Please try again.', 'wc-thank-you-page' ),
                'desktop'          => __( 'Desktop', 'wc-thank-you-page' ),
                'tablet'           => __( 'Tablet', 'wc-thank-you-page' ),
                'mobile'           => __( 'Mobile', 'wc-thank-you-page' ),
                'close'            => __( 'Close', 'wc-thank-you-page' ),
            )
        ) );
    }
}