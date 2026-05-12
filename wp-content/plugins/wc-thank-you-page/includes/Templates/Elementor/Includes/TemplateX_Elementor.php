<?php
namespace WPKIN_THANK_YOU_PAGE\Templates\Elementor\Includes;
/**
 * TemplateX Elementor Integration
 * 
 * Handles all Elementor-specific integration
 * 
 * @package TemplateX
 * @since 1.0.0
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class TemplateX_Elementor {

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
        add_action( 'elementor/editor/before_enqueue_scripts', array( $this, 'enqueue_editor_scripts' ) );
        add_action( 'elementor/editor/after_enqueue_styles', array( $this, 'enqueue_editor_styles' ) );
    }

    /**
     * Enqueue editor scripts
     */
    public function enqueue_editor_scripts() {
        // Enqueue Elementor integration script
        wp_enqueue_script(
            'templatex-elementor-integration',
            WPKIN_THANKYOU_URL . '/includes/Templates/Elementor/assets/js/elementor-integration.js',
            array( 'jquery', 'elementor-editor' ),
            filemtime( WPKIN_THANKYOU_PATH . 'includes/Templates/Elementor/assets/js/elementor-integration.js' ),
            true
        );

        // Enqueue modal script
        wp_enqueue_script(
            'templatex-modals',
            WPKIN_THANKYOU_URL . '/includes/Templates/Elementor/assets/js/modal.js',
            array( 'jquery', 'templatex-elementor-integration' ),
            filemtime( WPKIN_THANKYOU_PATH . 'includes/Templates/Elementor/assets/js/modal.js' ),
            true
        );

        // Localize script with data
        wp_localize_script( 'templatex-elementor-integration', 'templatexData', array(
            'ajaxUrl'     => admin_url( 'admin-ajax.php' ),
            'restUrl'     => rest_url( 'templatex/v1' ),
            'nonce'       => wp_create_nonce( 'wp_rest' ),
            'pluginUrl'   => WPKIN_THANKYOU_URL . '/includes/Templates/Elementor/',
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

    /**
     * Enqueue editor styles
     */
    public function enqueue_editor_styles() {
        wp_enqueue_style(
            'templatex-modal',
            WPKIN_THANKYOU_URL . '/includes/Templates/Elementor/assets/css/modal.css',
            array(),
            filemtime( WPKIN_THANKYOU_PATH . 'includes/Templates/Elementor/assets/css/modal.css' )
        );
    }
}

