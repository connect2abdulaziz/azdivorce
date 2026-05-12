<?php
/**
 * Template Pages Configuration
 * 
 * This file contains all template configurations with license control
 * 
 * @package WPKIN_THANK_YOU_PAGE\Templates\Elementor
 * @since 2.0.0
 */

namespace WPKIN_THANK_YOU_PAGE\Templates\Elementor;

use WPKIN_THANK_YOU_PAGE\Helper;

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Pages class for template management
 */
class Pages {
    
    /**
     * Get all templates with license control
     * 
     * @return array Templates array
     */
    public static function get_templates() {
        $base_url = WPKIN_THANKYOU_URL . '/includes/Templates/';
        $type     = Helper::wpkin_tr_plugins_version_control() ? "" : "preo";
        
        // All templates configuration
        $all_templates = array(
            array(
                "id" => "basic-thank-you-page",
                "name" => "Basic Thank You Page",
                "type" => "",
                "category" => "special",
                "thumbnail" => $base_url . "Images/basic-thank-you-page.jpg",
                "json_file" => "templates/basic-thank-you-page.json",
                "preview_url" => "https://wpkindemos.com/thankredirect/elementor/basic-thank-you-page/"
            ),
            array(
                "id" => "modern-thank-you-page",
                "name" => "Modern Thank You Page",
                "type" => $type,
                "category" => "landing",
                "thumbnail" => $base_url . "Images/modern-thank-you-page.jpg",
                "json_file" => "templates/mordern-thank-you-page.json",
                "preview_url" => "https://wpkindemos.com/thankredirect/elementor/modern-thank-you-page"
            ),
            array(
                "id" => "clean-thank-you-page",
                "name" => "Clean Thank You Page",
                "type" => $type,
                "category" => "business",
                "thumbnail" => $base_url . "Images/clean-thank-you-page.jpg",
                "json_file" => "templates/clean-thank-you-page.json",
                "preview_url" => "https://wpkindemos.com/thankredirect/elementor/clean-thank-you-page",
                "pro_url" => "https://wpkin.com/thank-redirect/pricing/"
            ),
            array(
                "id" => "elegant-thank-you-page",
                "name" => "Elegant Thank You Page",
                "type" => $type,
                "category" => "portfolio",
                "thumbnail" => $base_url . "Images/elegant-thank-you-page.jpg",
                "json_file" => "templates/elegant-thank-you-page.json",
                "preview_url" => "https://wpkindemos.com/thankredirect/elementor/elegant-thank-you-page",
                "pro_url" => "https://wpkin.com/thank-redirect/pricing/"
            )
        );
        
        return $all_templates;
    }
    
    /**
     * Get templates filtered by license status
     * 
     * @param bool $show_locked Whether to show locked pro templates
     * @return array Filtered templates
     */
    public static function get_templates_filtered( $show_locked = true ) {
        $templates = self::get_templates();
        
        // For now, return all templates
        // You can add filtering logic here later if needed
        return array_values( $templates );
    }
    
    /**
     * Get single template by ID
     * 
     * @param string $template_id Template ID
     * @return array|null Template data or null if not found
     */
    public static function get_template_by_id( $template_id ) {
        $templates = self::get_templates();
        
        foreach ( $templates as $template ) {
            if ( $template['id'] === $template_id ) {
                return $template;
            }
        }
        
        return null;
    }
    
    /**
     * Check if template is accessible
     * 
     * @param string $template_id Template ID
     * @return bool Whether template is accessible
     */
    public static function is_template_accessible( $template_id ) {
        $template = self::get_template_by_id( $template_id );
        
        if ( ! $template ) {
            return false;
        }
        
        // For now, allow access to all templates
        // You can add license checking logic here later if needed
        return true;
    }
}