<?php
/**
 * Template Pages Configuration for Gutenberg
 * 
 * This file contains all template configurations with license control
 * 
 * @package WPKIN_THANK_YOU_PAGE\Templates\Gutenberg
 * @since 2.0.0
 */

namespace WPKIN_THANK_YOU_PAGE\Templates\Gutenberg;

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
                "file"      => "templates/thank-page.html",
                "preview_url" => "https://wpkindemos.com/thankredirect/elementor/basic-thank-you-page/"
            ),
            array(
                "id" => "modern-thank-you-page",
                "name" => "Modern Thank You Page",
                "type" => $type,
                "category" => "landing",
                "thumbnail" => $base_url . "Images/modern-thank-you-page.jpg",
                "file" => "templates/thank-page.html",
                "preview_url" => "https://wpkindemos.com/thankredirect/elementor/modern-thank-you-page"
            ),
            array(
                "id" => "clean-thank-you-page",
                "name" => "Clean Thank You Page",
                "type" => $type,
                "category" => "business",
                "thumbnail" => $base_url . "Images/clean-thank-you-page.jpg",
                "file" => "templates/thank-page.html",
                "preview_url" => "https://wpkindemos.com/thankredirect/elementor/clean-thank-you-page",
                "pro_url" => "https://wpkin.com/thank-redirect/pricing/"
            ),
            array(
                "id" => "elegant-thank-you-page",
                "name" => "Elegant Thank You Page",
                "type" => $type,
                "category" => "portfolio",
                "thumbnail" => $base_url . "Images/elegant-thank-you-page.jpg",
                "file" => "templates/thank-page.html",
                "preview_url" => "https://wpkindemos.com/thankredirect/elementor/elegant-thank-you-page",
                "pro_url" => "https://wpkin.com/thank-redirect/pricing/"
            )
        );
        
        return $all_templates;
    }
    
    /**
     * Get template by ID
     * 
     * @param string $template_id Template ID
     * @return array|bool Template array or false
     */
    public static function get_template_by_id( $template_id ) {
        $templates = self::get_templates();
        
        foreach ( $templates as $template ) {
            if ( $template['id'] === $template_id ) {
                return $template;
            }
        }
        
        return false;
    }
}