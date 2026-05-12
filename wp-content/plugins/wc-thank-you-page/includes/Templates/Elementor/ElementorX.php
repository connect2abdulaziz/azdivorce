<?php
namespace WPKIN_THANK_YOU_PAGE\Templates\Elementor;

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Main ElementorX Plugin Class
 * 
 * @since 1.0.0
 */
 class ElementorX {

    /**
     * Constructor
     */
    function __construct() {
        $this->init_hooks();
        $this->includes();
    }

    /**
     * Initialize hooks
     */
    function init_hooks() {
        add_action( 'plugins_loaded', array( $this, 'on_plugins_loaded' ) );
    }

    /**
     * Include required files
     */
    function includes() {
		new Includes\TemplateX_Library();
		new Includes\TemplateX_Elementor();
    }

    /**
     * Initialize after all plugins are loaded
     */
    public function on_plugins_loaded() {
        // Check if Elementor is installed and activated
        if ( did_action( 'elementor/loaded' ) ) {
            // Initialize Elementor integration
            \TemplateX_Elementor::instance();
        }
    }

}

