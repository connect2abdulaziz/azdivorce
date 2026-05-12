<?php
namespace WPKIN_THANK_YOU_PAGE\Templates\Gutenberg\Includes;
/**
 * TemplateX Library Manager for Gutenberg
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
        register_rest_route( 'templatex/v1', '/gutenberg-templates', array(
            'methods'  => 'GET',
            'callback' => array( $this, 'get_templates' ),
            'permission_callback' => array( $this, 'check_permissions' ),
        ) );

        // Get single template data
        register_rest_route( 'templatex/v1', '/gutenberg-templates/(?P<id>[a-zA-Z0-9-]+)', array(
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
    }
    
    /**
     * Get all templates
     * 
     * @return WP_REST_Response
     */
    public function get_templates() {
        $templates = \WPKIN_THANK_YOU_PAGE\Templates\Gutenberg\Pages::get_templates();
        
        return rest_ensure_response( $templates );
    }
    
    /**
     * Get template data
     * 
     * @param WP_REST_Request $request
     * @return WP_REST_Response
     */
    public function get_template_data( $request ) {
        $template_id = $request->get_param( 'id' );
        
        $template = \WPKIN_THANK_YOU_PAGE\Templates\Gutenberg\Pages::get_template_by_id( $template_id );
        
        if ( ! $template ) {
            return new \WP_Error( 'template_not_found', __( 'Template not found', 'wc-thank-you-page' ), array( 'status' => 404 ) );
        }
        
        // Get template content
        $template_content = $this->get_template_content( $template );
        
        if ( is_wp_error( $template_content ) ) {
            return $template_content;
        }
        
        $template['content'] = $template_content;
        
        return rest_ensure_response( $template );
    }
    
    /**
     * Get template content
     * 
     * @param array $template
     * @return array|WP_Error
     */
    private function get_template_content( $template ) {
        // Only support HTML files for Gutenberg blocks
        $file_path = WPKIN_THANKYOU_PATH . 'includes/Templates/Gutenberg/' . $template['file'];
        
        if ( ! file_exists( $file_path ) ) {
            return new \WP_Error( 'template_file_not_found', __( 'Template file not found', 'wc-thank-you-page' ), array( 'status' => 404 ) );
        }
        
        $file_content = file_get_contents( $file_path );
        
        // Return content directly as Gutenberg blocks
        return $file_content;
    }

}