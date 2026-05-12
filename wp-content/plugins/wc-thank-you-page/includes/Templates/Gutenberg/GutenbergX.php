<?php
namespace WPKIN_THANK_YOU_PAGE\Templates\Gutenberg;

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Main GutenbergX Plugin Class
 * 
 * @since 1.0.0
 */
 class GutenbergX {

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
		new Includes\TemplateX_Gutenberg();
    }

    /**
     * Initialize after all plugins are loaded
     */
    public function on_plugins_loaded() {
        // Check if Gutenberg is available
        if ( function_exists( 'register_block_type' ) ) {
            // Initialize Gutenberg integration
            \WPKIN_THANK_YOU_PAGE\Templates\Gutenberg\Includes\TemplateX_Gutenberg::instance();
        }
    }

}