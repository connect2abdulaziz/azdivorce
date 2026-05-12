<?php

namespace WPKIN_THANK_YOU_PAGE\API;

if (! defined('ABSPATH')) {
    wp_die(esc_html__('You can\'t access this page', 'wc-thank-you-page'));
}

/**
 * API Callback
 *
 * @since 1.0.0
 */
class Callback {

    public function wpkin_get_thankyou_settings( $request ) {
        // Get saved settings with default values for missing fields
        $default_settings = array(
			'global' => array(
				'redirect_type' => 'page',
				'custom_url' => '',
				'page_id' => '',
			),
			'product_redirects' => array(),
			'category_redirects' => array(),
			'user_role_redirects' => array(),
			'payment_method_redirects' => array(),
			'product_type_redirects' => array(),
			'product_tag_redirects' => array(),
			'product_variation_redirects' => array(),
			'product_brand_redirects' => array(),
			'url_personalization' => array(
				'enabled' => true,
				'query_parameters' => '',
			),
			'analytics' => array(
				'enable_datalayer' => false,
				'enable_facebook_pixel' => false,
				'custom_event_name' => 'purchase',
			),
			'additional' => array(
				'redirect_delay' => 0,
			),
		);
        
        $saved_settings = get_option( 'wpkin_thankyou_settings', array() );
        
        // Merge saved settings with defaults to ensure all expected fields exist
        $settings = array_replace_recursive( $default_settings, $saved_settings );

        // Get all products for dropdown
        $products = array();
        $args = array(
            'post_type' => 'product',
            'post_status' => 'publish',
            'posts_per_page' => 500, // Limit to 500 products for performance
            'orderby' => 'title',
            'order' => 'ASC',
            'fields' => 'ids', // Only get IDs for performance
        );
        
        $product_ids = get_posts( $args );
        foreach ( $product_ids as $product_id ) {
            $products[] = array(
                'value' => $product_id,
                'label' => get_the_title( $product_id ),
            );
        }
        
        // Get all categories
        $categories = array();
        $terms = get_terms( array(
            'taxonomy' => 'product_cat',
            'hide_empty' => false,
            'number' => 500, // Limit to 500 categories
        ) );
        
        if ( ! is_wp_error( $terms ) && ! empty( $terms ) ) {
            foreach ( $terms as $term ) {
                $categories[] = array(
                    'value' => $term->term_id,
                    'label' => $term->name,
                );
            }
        }
        
        // Get all product tags
        $tags = array();
        $tag_terms = get_terms( array(
            'taxonomy' => 'product_tag',
            'hide_empty' => false,
            'number' => 500, // Limit to 500 tags
        ) );
        
        if ( ! is_wp_error( $tag_terms ) && ! empty( $tag_terms ) ) {
            foreach ( $tag_terms as $term ) {
                $tags[] = array(
                    'value' => $term->term_id,
                    'label' => $term->name,
                );
            }
        }
        
        // Get product types
        $product_types = array(
            array( 'value' => 'simple', 'label' => 'Simple Product' ),
            array( 'value' => 'variable', 'label' => 'Variable Product' ),
            array( 'value' => 'grouped', 'label' => 'Grouped Product' ),
            array( 'value' => 'external', 'label' => 'External/Affiliate Product' ),
        );

		// Get product Brand
		$brand_taxonomies = array( 'pwb-brand', 'yith_product_brand', 'product_brand', 'brand' );
		$active_brand_taxonomy = null;

		foreach ( $brand_taxonomies as $taxonomy ) {
			if ( taxonomy_exists( $taxonomy ) ) {
				$active_brand_taxonomy = $taxonomy;
				break;
			}
		}

		// Get product brands
		$brands = array();
		if ( $active_brand_taxonomy ) {
			$brand_terms = get_terms( array(
				'taxonomy' => $active_brand_taxonomy,
				'hide_empty' => false,
				'number' => 500, // Limit to 500 brands
			) );
			
			if ( ! is_wp_error( $brand_terms ) && ! empty( $brand_terms ) ) {
				foreach ( $brand_terms as $term ) {
					$brands[] = array(
						'value' => $term->term_id,
						'label' => $term->name,
					);
				}
			}
		}
        
        // Get product variations (limited for performance)
        $variations = array();
		$args = array(
			'post_type' => 'product_variation',
			'post_status' => 'publish',
			'posts_per_page' => 200, // Limit to 200 variations for performance
			'orderby' => 'parent',
			'order' => 'ASC',
		);

		$variation_query = new \WP_Query( $args );
		if ( $variation_query->have_posts() ) {
			while ( $variation_query->have_posts() ) {
				$variation_query->the_post();
				$variation_id = get_the_ID();
				$variation = wc_get_product( $variation_id );
				
				if ( $variation && $variation->get_type() === 'variation' ) {
					$parent_id = $variation->get_parent_id();
					$parent = wc_get_product( $parent_id );
					$parent_name = $parent ? $parent->get_name() : 'Unknown Product';
					
					// Get variation attributes
					$attributes = $variation->get_variation_attributes();
					$attribute_text = array();
					
					foreach ( $attributes as $attribute => $value ) {
						$taxonomy = str_replace( 'attribute_', '', $attribute );
						$term_name = $value;
						
						// If it's a taxonomy attribute, get the term name
						if ( taxonomy_exists( $taxonomy ) ) {
							$term = get_term_by( 'slug', $value, $taxonomy );
							if ( $term ) {
								$term_name = $term->name;
							}
						}
						
						$attribute_label = wc_attribute_label( $taxonomy );
						$attribute_text[] = $attribute_label . ': ' . $term_name;
					}
					
					$variation_label = $parent_name;
					if ( ! empty( $attribute_text ) ) {
						$variation_label .= ' - ' . implode( ', ', $attribute_text );
					}
					
					$variations[] = array(
						'value' => $variation_id,
						'label' => $variation_label,
					);
				}
			}
			wp_reset_postdata();
		}
        
        // Get all pages
        $pages = array();
        $args = array(
            'post_type' => 'page',
            'post_status' => 'publish',
            'posts_per_page' => 500, // Limit to 500 pages
            'orderby' => 'title',
            'order' => 'ASC',
            'fields' => 'ids', // Only get IDs for performance
        );
        
        $page_ids = get_posts( $args );
        foreach ( $page_ids as $page_id ) {
            $pages[] = array(
                'value' => $page_id,
                'label' => get_the_title( $page_id ),
            );
        }
        
        // Get user roles
        $roles = array();
        $wp_roles = wp_roles();
        if ( ! empty( $wp_roles->roles ) ) {
            foreach ( $wp_roles->roles as $role_id => $role_data ) {
                $roles[] = array(
                    'value' => $role_id,
                    'label' => $role_data['name'],
                );
            }
        }
        
        // Get payment methods
        $payment_methods = array();
        if ( function_exists( 'WC' ) ) {
            $gateways = WC()->payment_gateways->payment_gateways();
            if ( ! empty( $gateways ) ) {
                foreach ( $gateways as $gateway_id => $gateway ) {
                    if ( $gateway->enabled === 'yes' ) {
                        $payment_methods[] = array(
                            'value' => $gateway_id,
                            'label' => $gateway->get_title(),
                        );
                    }
                }
            }
        }
        
        return rest_ensure_response( array(
            'success' => true,
            'settings' => $settings,
            'options' => array(
                'products' => $products,
                'categories' => $categories,
                'tags' => $tags,
                'product_types' => $product_types,
                'variations' => $variations,
                'pages' => $pages,
                'roles' => $roles,
                'brands' => $brands,
                'payment_methods' => $payment_methods,
            ),
        ) );
    }

    public function wpkin_save_thankyou_settings( $request ) {
        $settings = $request->get_param( 'settings' );
        
        if ( ! $settings || ! is_array( $settings ) ) {
            return rest_ensure_response( array(
                'success' => false,
                'message' => 'No settings data provided',
            ) );
        }
        
        // Sanitize settings data before saving
        $sanitized_settings = $this->sanitize_settings( $settings );
        
        $updated = update_option( 'wpkin_thankyou_settings', $sanitized_settings );
        
        if ( ! $updated && get_option( 'wpkin_thankyou_settings' ) !== $sanitized_settings ) {
            return rest_ensure_response( array(
                'success' => false,
                'message' => 'Failed to update settings in database',
            ) );
        }
        
        return rest_ensure_response( array(
            'success' => true,
            'message' => 'Settings saved successfully',
        ) );
    }
    
    /**
     * Sanitize settings before saving
     * 
     * @param array $settings The settings to sanitize
     * @return array The sanitized settings
     */
    private function sanitize_settings( $settings ) {
        // Basic sanitization - recursive array sanitization
        array_walk_recursive( $settings, function( &$value ) {
            if ( is_string( $value ) ) {
                $value = sanitize_text_field( $value );
            }
        } );
        
        return $settings;
    }
}