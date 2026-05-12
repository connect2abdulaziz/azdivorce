<?php
namespace WPKIN_THANK_YOU_PAGE\Templates\Elementor\Includes;
/**
 * TemplateX Library Manager
 * 
 * Manages template data, loading, and API endpoints
 * 
 * @package TemplateX
 * @since 1.0.0
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class TemplateX_Library {

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
        // Register REST API endpoints
        add_action( 'rest_api_init', array( $this, 'register_rest_routes' ) );
 
    }
    /**
     * Register REST API routes
     */
    public function register_rest_routes() {
        // Get all templates
        register_rest_route( 'templatex/v1', '/templates', array(
            'methods'  => 'GET',
            'callback' => array( $this, 'get_templates' ),
            'permission_callback' => array( $this, 'check_permissions' ),
        ) );

        // Get single template data
        register_rest_route( 'templatex/v1', '/templates/(?P<id>[a-zA-Z0-9-]+)', array(
            'methods'  => 'GET',
            'callback' => array( $this, 'get_template_data' ),
            'permission_callback' => array( $this, 'check_permissions' ),
        ) );
    }

    /**
     * Check permissions for REST API
     * 
     * @return bool
     */
    public function check_permissions() {
        // Allow public access to templates for frontend display
        // This is needed for the thank you page to work properly
        return true;
        
        // Alternative: Check if user is logged in OR if it's a frontend request
        // return is_user_logged_in() || !is_admin();
    }

    /**
     * Get all templates
     * 
     * @param WP_REST_Request $request
     * @return WP_REST_Response
     */
    public function get_templates( $request ) {
        // Use the new Pages class for template management
        $templates = \WPKIN_THANK_YOU_PAGE\Templates\Elementor\Pages::get_templates();
        
        // Apply filters for license-based modifications
        $templates = apply_filters( 'templatex_get_templates', $templates );

        return new \WP_REST_Response( array(
            'success' => true,
            'data' => $templates
        ), 200 );
    }

    /**
     * Get single template data
     * 
     * @param WP_REST_Request $request
     * @return WP_REST_Response
     */
    public function get_template_data( $request ) {
        $template_id = $request->get_param( 'id' );
        
        if ( empty( $template_id ) ) {
            return new \WP_REST_Response( array(
                'success' => false,
                'message' => __( 'Template ID is required.', 'templatex' )
            ), 400 );
        }
        
        // Get specific template data using Pages class
        $template = \WPKIN_THANK_YOU_PAGE\Templates\Elementor\Pages::get_template_by_id( $template_id );
        
        if ( ! $template ) {
            return new \WP_REST_Response( array(
                'success' => false,
                'message' => __( 'Template not found.', 'templatex' )
            ), 404 );
        }
        
        // Check if template is accessible (license check for pro templates)
        if ( ! \WPKIN_THANK_YOU_PAGE\Templates\Elementor\Pages::is_template_accessible( $template_id ) ) {
            return new \WP_REST_Response( array(
                'success' => false,
                'message' => __( 'This is a Pro template. Please upgrade to access it.', 'templatex' ),
                'upgrade_url' => 'https://wpkin.com/thank-redirect/pricing/'
            ), 403 );
        }

        // Load the template JSON file
        $template_json_file = WPKIN_THANKYOU_PATH . 'includes/Templates/Elementor/' . $template['json_file'];

        if ( ! file_exists( $template_json_file ) ) {
            return new \WP_REST_Response( array(
                'success' => false,
                'message' => __( 'Template data file not found.', 'templatex' )
            ), 404 );
        }

        $template_json = file_get_contents( $template_json_file );
        $template_data = json_decode( $template_json, true );

        if ( json_last_error() !== JSON_ERROR_NONE ) {
            return new \WP_REST_Response( array(
                'success' => false,
                'message' => __( 'Error parsing template data.', 'templatex' )
            ), 500 );
        }
        
        // Apply filters for license-based modifications
        $template_data = apply_filters( 'templatex_template_data', $template_data, $template_id );

        return new \WP_REST_Response( array(
            'success' => true,
            'data' => $template_data
        ), 200 );
    }
}

