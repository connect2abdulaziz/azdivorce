<?php

namespace WPKIN_THANK_YOU_PAGE\Frontend;

/**
 * Handle the thank you page redirection
 */
class WPKIN_Thank_You_Redirect {

	public $version;
    /**
     * Constructor
     */
    public function __construct() {
		$this->version = \WPKIN_THANK_YOU_PAGE\Helper::wpkin_tr_plugins_version_control();
        add_action('woocommerce_thankyou', array($this, 'wpkin_redirect_thank_you_page'), 1, 1);
		if ( $this->version ) {
			add_action('wp_head', array($this, 'wpkin_add_analytics_scripts'), 10);
		}
    }

	/**
	 * Add analytics scripts to the destination page
	 */
	public function wpkin_add_analytics_scripts() {
		// Only run on thank you pages with order data
		if ( ! is_page() || ! isset( $_GET['order_id'] ) || ! isset( $_GET['key'] ) ) {
			return;
		}
		
		$order_id = absint( wp_unslash( $_GET['order_id'] ) );
		$order_key = sanitize_text_field( wp_unslash( $_GET['key'] ) );
		
		// Verify order
		$order = wc_get_order( $order_id );
		if ( ! $order || $order->get_order_key() !== $order_key ) {
			return;
		}
		
		$settings = get_option( 'wpkin_thankyou_settings', array() );

		// Process analytics if enabled
		if ( isset( $settings['analytics']['enable_datalayer'] ) && $settings['analytics']['enable_datalayer'] ) {
			$this->add_datalayer_script( $order, $settings );
		}

		if ( isset( $settings['analytics']['enable_facebook_pixel'] ) && $settings['analytics']['enable_facebook_pixel'] ) {
			$this->add_facebook_pixel_script( $order, $settings );
		}
		
		// Process custom analytics script if enabled
		if ( isset( $settings['analytics']['enable_custom_script'] ) && $settings['analytics']['enable_custom_script'] ) {
			if ( ! empty( $settings['analytics']['custom_script'] ) ) {
				$script = $this->process_analytics_shortcodes( $settings['analytics']['custom_script'], $order );
				// Output sanitized script
				echo '<script type="text/javascript">' . wp_kses( $script, array() ) . '</script>';
			}
		}

		// Allow developers to add custom analytics code
		do_action( 'wpkin_before_thank_you_redirect', $order, $settings );
	}

	/**
	 * Redirect the thank you page based on settings
	 * 
	 * @param int $order_id The order ID
	 */
	public function wpkin_redirect_thank_you_page( $order_id ) {
		// Sanitize order_id
		$order_id = absint( $order_id );
		
		if ( ! $order_id ) {
			return;
		}

		$order = wc_get_order( $order_id );
		if ( ! $order ) {
			return;
		}
		
		// Verify order belongs to current user if logged in
		if ( is_user_logged_in() && $order->get_customer_id() && $order->get_customer_id() !== get_current_user_id() ) {
			// Optional: Only apply this check for logged-in users viewing their own orders
			// return;
		}

		$settings = get_option( 'wpkin_thankyou_settings', array() );

		$redirect_url = $this->get_redirect_url( $order, $settings );

		if ( ! $redirect_url ) {
			return;
		}

		// Process URL personalization if enabled
		if ( $this->version ) {
			if ( isset( $settings['url_personalization']['enabled'] ) && $settings['url_personalization']['enabled'] ) {
				$redirect_url = $this->process_url_personalization( $redirect_url, $order, $settings );
			}
		}

		// Always preserve query parameters
		$current_url = isset( $_SERVER['REQUEST_URI'] ) ? esc_url_raw( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '';
		$query_string = parse_url( $current_url, PHP_URL_QUERY );
		
		if ( $query_string ) {
			parse_str( $query_string, $query_params );
			
			// Remove order-related parameters to avoid duplicates
			unset( $query_params['order_id'] );
			unset( $query_params['key'] );
			
			if ( ! empty( $query_params ) ) {
				$redirect_url = add_query_arg( $query_params, $redirect_url );
			}
		}

		// Get redirect delay if set
		$delay = isset( $settings['additional']['redirect_delay'] ) ? absint( $settings['additional']['redirect_delay'] ) : 0;

		// Add a filter to allow developers to modify the redirect URL
		$redirect_url = apply_filters( 'wpkin_thank_you_redirect_url', $redirect_url, $order, $settings );

		if ( $this->version && $delay > 0 ) {
			// Use JavaScript for delayed redirect
			$delay_ms = absint( $delay ) * 1000;
			wp_enqueue_script( 'wpkin-redirect-delay', '', array(), WPKIN_THANKYOU_VERSION, true );
			wp_add_inline_script( 'wpkin-redirect-delay', 'setTimeout(function() { window.location.href = ' . wp_json_encode( $redirect_url ) . '; }, ' . $delay_ms . ');' );
		} else {
			// Prevent redirect loops
			$redirect_count = isset( $_COOKIE['wpkin_redirect_count'] ) ? intval( $_COOKIE['wpkin_redirect_count'] ) : 0;
			if ( $redirect_count > 5 ) {
				// Too many redirects, stop to prevent loop
				return;
			}
			
			// Set cookie to track redirects with secure flags (PHP 7.3+ syntax)
			if ( PHP_VERSION_ID >= 70300 ) {
				setcookie( 'wpkin_redirect_count', $redirect_count + 1, array(
					'expires' => time() + 30,
					'path' => COOKIEPATH,
					'domain' => COOKIE_DOMAIN,
					'secure' => is_ssl(),
					'httponly' => true,
					'samesite' => 'Lax'
				) );
			} else {
				// PHP < 7.3 compatibility
				setcookie( 'wpkin_redirect_count', $redirect_count + 1, time() + 30, COOKIEPATH, COOKIE_DOMAIN, is_ssl(), true );
			}
			
			// Immediate redirect
			wp_redirect( esc_url_raw( $redirect_url ) );
			exit;
		}
	}

    /**
	 * Process URL personalization if enabled
	 * 
	 * @param string $url The base URL
	 * @param WC_Order $order The order object
	 * @param array $settings Plugin settings
	 * @return string The processed URL
	 */
	private function process_url_personalization($url, $order, $settings) {
		if (!isset($settings['url_personalization']['enabled']) || !$settings['url_personalization']['enabled']) {
			return $url;
		}
		
		// Get the query parameters string
		$query_params = isset($settings['url_personalization']['query_parameters']) 
			? $settings['url_personalization']['query_parameters'] 
			: '';
		
		if (empty($query_params)) {
			return $url;
		}
		
		// Replace placeholders in the query parameters
		$placeholders = [
			'[order_number]' => $order->get_order_number(),
			'[billing_first_name]' => $order->get_billing_first_name(),
			'[billing_last_name]' => $order->get_billing_last_name(),
			'[billing_email]' => $order->get_billing_email(),
			'[order_total]' => $order->get_total(),
			'[payment_method]' => $order->get_payment_method_title(),
			'[shipping_method]' => $order->get_shipping_method(),
		];
		
		// Get product names
		$product_names = [];
		foreach ($order->get_items() as $item) {
			$product_names[] = $item->get_name();
		}
		$placeholders['[product_names]'] = implode(', ', $product_names);
		
		// Allow developers to add custom placeholders
		$placeholders = apply_filters('wpkin_thankyou_url_placeholders', $placeholders, $order);
		
		// Replace placeholders in query parameters
		foreach ($placeholders as $placeholder => $value) {
			$query_params = str_replace($placeholder, urlencode($value), $query_params);
		}
		
		// Append the processed query parameters to the URL
		$url = add_query_arg([], $url); // Normalize the URL
		$separator = (strpos($url, '?') !== false) ? '&' : '?';
		$url .= $separator . $query_params;
		
		return $url;
	}

    /**
	 * Add Google Analytics dataLayer script
	 * 
	 * @param WC_Order $order The order object
	 * @param array $settings Plugin settings
	 */
	private function add_datalayer_script($order, $settings) {
		$event_name = !empty($settings['analytics']['custom_event_name']) ? $settings['analytics']['custom_event_name'] : 'purchase';
		
		$items = [];
		foreach ($order->get_items() as $item) {
			$product = $item->get_product();
			if ($product) {
				$items[] = [
					'item_id' => $product->get_id(),
					'item_name' => $item->get_name(),
					'price' => $order->get_item_total($item),
					'quantity' => $item->get_quantity(),
				];
			}
		}
		
		// Create a single data object with all needed information
		$ecommerce_data = [
			'transaction_id' => $order->get_order_number(),
			'value' => $order->get_total(),
			'tax' => $order->get_total_tax(),
			'shipping' => $order->get_shipping_total(),
			'currency' => $order->get_currency(),
			'coupon' => implode(',', $order->get_coupon_codes()),
			'items' => $items,
		];
		
		// Output the script with a unique ID to prevent duplicates
		echo '<script id="wpkin-datalayer-' . esc_attr($order->get_id()) . '">
			window.dataLayer = window.dataLayer || [];
			
			// Clear previous ecommerce object to prevent conflicts
			dataLayer.push({
				ecommerce: null
			});
			
			// Push purchase event
			dataLayer.push({
				event: "' . esc_js($event_name) . '",
				ecommerce: ' . json_encode($ecommerce_data) . '
			});
		</script>';
	}

    /**
	 * Add Facebook Pixel script
	 * 
	 * @param WC_Order $order The order object
	 * @param array $settings Plugin settings
	 */
	private function add_facebook_pixel_script($order, $settings) {
		$event_name = !empty($settings['analytics']['custom_event_name']) ? $settings['analytics']['custom_event_name'] : 'purchase';
		$contents = [];
		
		foreach ($order->get_items() as $item) {
			$product = $item->get_product();
			if ($product) {
				$contents[] = [
					'id' => $product->get_id(),
					'name' => $item->get_name(),
					'quantity' => $item->get_quantity(),
					'price' => $order->get_item_total($item),
				];
			}
		}
		
		// Output the script with a unique ID to prevent duplicates
		echo '<script id="wpkin-fbpixel-' . esc_attr($order->get_id()) . '">
			(function() {
				if (typeof fbq === "undefined") {
					console.log("WC Thank You Page: Facebook Pixel not found");
					return;
				}
				
				// Track Purchase event
				fbq("track", "' . esc_js($event_name) . '", {
					value: ' . esc_js($order->get_total()) . ',
					currency: "' . esc_js($order->get_currency()) . '",
					content_type: "product",
					contents: ' . json_encode($contents) . ',
					num_items: ' . esc_js(count($contents)) . ',
					order_id: "' . esc_js($order->get_order_number()) . '"
				});
			})();
		</script>';
	}

	/**
	 * Process analytics script shortcodes
	 * 
	 * @param string $script The script with shortcodes
	 * @param WC_Order $order The order object
	 * @return string The processed script
	 */
	private function process_analytics_shortcodes($script, $order) {
		// Prepare items JSON
		$items = [];
		foreach ($order->get_items() as $item) {
			$product = $item->get_product();
			if ($product) {
				$items[] = [
					'item_id' => $product->get_id(),
					'item_name' => $item->get_name(),
					'price' => $order->get_item_total($item),
					'quantity' => $item->get_quantity(),
				];
			}
		}
		
		$items_json = json_encode($items);
		
		// Define shortcodes and their values
		$shortcodes = [
			'[wpkin_transaction_id]' => $order->get_order_number(),
			'[wpkin_order_total]' => $order->get_total(),
			'[wpkin_currency]' => $order->get_currency(),
			'[wpkin_items]' => $items_json,
			'[wpkin_customer_email]' => $order->get_billing_email(),
			'[wpkin_customer_name]' => $order->get_billing_first_name() . ' ' . $order->get_billing_last_name(),
			'[wpkin_payment_method]' => $order->get_payment_method_title(),
			'[wpkin_shipping_method]' => $order->get_shipping_method(),
		];
		
		// Allow developers to add custom shortcodes
		$shortcodes = apply_filters('wpkin_analytics_shortcodes', $shortcodes, $order);
		
		// Replace shortcodes in script
		foreach ($shortcodes as $shortcode => $value) {
			$script = str_replace($shortcode, $value, $script);
		}
		
		return $script;
	}

    /**
	 * Get the redirect URL based on settings and order
	 * 
	 * @param WC_Order $order The order object
	 * @param array $settings The plugin settings
	 * @return string|false The redirect URL or false if no redirect
	 */
	private function get_redirect_url( $order, $settings ) {
		$redirect_url = false;
		
		// Check for product variation-based redirects
		$items = $order->get_items();
		$variation_ids = array();
		$product_ids = array();
		
		foreach ( $items as $item ) {
			$product_ids[] = $item->get_product_id();
			$variation_id = $item->get_variation_id();
			if ( $variation_id ) {
				$variation_ids[] = $variation_id;
			}
		}
		
		// If no redirect URL yet, check for product-specific redirects
		if ( ! $redirect_url ) {
			if ( isset( $settings['product_redirects'] ) && is_array( $settings['product_redirects'] ) ) {
				foreach ( $settings['product_redirects'] as $redirect ) {
					if ( isset( $redirect['product_ids'] ) && is_array( $redirect['product_ids'] ) ) {
						// Check if any of the order products match the redirect products
						$matching_products = array_intersect( $product_ids, $redirect['product_ids'] );
						
						if ( ! empty( $matching_products ) ) {
							if ( isset( $redirect['redirect_type'] ) && $redirect['redirect_type'] === 'custom_url' ) {
								if ( ! empty( $redirect['custom_url'] ) ) {
									$redirect_url = $redirect['custom_url'];
									break;
								}
							} elseif ( isset( $redirect['page_id'] ) && ! empty( $redirect['page_id'] ) ) {
								$redirect_url = get_permalink( $redirect['page_id'] );
								break;
							}
						}
					}
				}
			}
		}

		// If no redirect URL yet, check for category-specific redirects
		if ( ! $redirect_url ) {
			$product_cats = array();
			foreach ( $product_ids as $product_id ) {
				$terms = wp_get_post_terms( $product_id, 'product_cat', array( 'fields' => 'ids' ) );
				if ( ! is_wp_error( $terms ) ) {
					$product_cats = array_merge( $product_cats, $terms );
				}
			}
			$product_cats = array_unique( $product_cats );
			
			if ( isset( $settings['category_redirects'] ) && is_array( $settings['category_redirects'] ) ) {
				foreach ( $settings['category_redirects'] as $redirect ) {
					if ( isset( $redirect['category_ids'] ) && is_array( $redirect['category_ids'] ) ) {
						// Check if any of the order product categories match the redirect categories
						$matching_cats = array_intersect( $product_cats, $redirect['category_ids'] );
						
						if ( ! empty( $matching_cats ) ) {
							if ( isset( $redirect['redirect_type'] ) && $redirect['redirect_type'] === 'custom_url' ) {
								if ( ! empty( $redirect['custom_url'] ) ) {
									$redirect_url = $redirect['custom_url'];
									break;
								}
							} elseif ( isset( $redirect['page_id'] ) && ! empty( $redirect['page_id'] ) ) {
								$redirect_url = get_permalink( $redirect['page_id'] );
								break;
							}
						}
					}
				}
			}
		}
		if ( $this->version ) {
			// Redirect Variation items
			if (!empty($variation_ids) && isset($settings['product_variation_redirects']) && is_array($settings['product_variation_redirects'])) {
				foreach ($settings['product_variation_redirects'] as $redirect) {
					if (isset($redirect['variation_ids']) && is_array($redirect['variation_ids'])) {
						// Check if any of the order variations match the redirect variations
						$matching_variations = array_intersect($variation_ids, $redirect['variation_ids']);
						
						if (!empty($matching_variations)) {
							if (isset($redirect['redirect_type']) && $redirect['redirect_type'] === 'custom_url') {
								if (!empty($redirect['custom_url'])) {
									return $redirect['custom_url'];
								}
							} elseif (isset($redirect['page_id']) && !empty($redirect['page_id'])) {
								return get_permalink($redirect['page_id']);
							}
						}
					}
				}
			}

			// If no redirect URL yet, check for product type-based redirects
			if (!$redirect_url) {
				$product_types = [];
				foreach ($product_ids as $product_id) {
					$product = wc_get_product($product_id);
					if ($product) {
						$product_types[] = $product->get_type();
					}
				}
				$product_types = array_unique($product_types);
				
				if (!empty($product_types) && isset($settings['product_type_redirects']) && is_array($settings['product_type_redirects'])) {
					foreach ($settings['product_type_redirects'] as $redirect) {
						if (isset($redirect['product_types']) && is_array($redirect['product_types'])) {
							// Check if any of the order product types match the redirect types
							$matching_types = array_intersect($product_types, $redirect['product_types']);
							
							if (!empty($matching_types)) {
								if (isset($redirect['redirect_type']) && $redirect['redirect_type'] === 'custom_url') {
									if (!empty($redirect['custom_url'])) {
										$redirect_url = $redirect['custom_url'];
										break;
									}
								} elseif (isset($redirect['page_id']) && !empty($redirect['page_id'])) {
									$redirect_url = get_permalink($redirect['page_id']);
									break;
								}
							}
						}
					}
				}
			}

			// If no redirect URL yet, check for product tag-based redirects
			if (!$redirect_url) {
				$product_tags = [];
				foreach ($product_ids as $product_id) {
					$tags = wp_get_post_terms($product_id, 'product_tag', array('fields' => 'ids'));
					if (!is_wp_error($tags)) {
						$product_tags = array_merge($product_tags, $tags);
					}
				}
				$product_tags = array_unique($product_tags);
				
				if (!empty($product_tags) && isset($settings['product_tag_redirects']) && is_array($settings['product_tag_redirects'])) {
					foreach ($settings['product_tag_redirects'] as $redirect) {
						if (isset($redirect['tag_ids']) && is_array($redirect['tag_ids'])) {
							// Check if any of the order product tags match the redirect tags
							$matching_tags = array_intersect($product_tags, $redirect['tag_ids']);
							
							if (!empty($matching_tags)) {
								if (isset($redirect['redirect_type']) && $redirect['redirect_type'] === 'custom_url') {
									if (!empty($redirect['custom_url'])) {
										$redirect_url = $redirect['custom_url'];
										break;
									}
								} elseif (isset($redirect['page_id']) && !empty($redirect['page_id'])) {
									$redirect_url = get_permalink($redirect['page_id']);
									break;
								}
							}
						}
					}
				}
			}

			// Check for brand-based redirects
			if (!$redirect_url) {
				$product_brands = [];
				foreach ($product_ids as $product_id) {
					// Check for brands from different plugins
					$brand_taxonomies = ['pwb-brand', 'yith_product_brand', 'product_brand', 'brand'];
					
					foreach ($brand_taxonomies as $taxonomy) {
						if (taxonomy_exists($taxonomy)) {
							$terms = wp_get_post_terms($product_id, $taxonomy, array('fields' => 'ids'));
							if (!is_wp_error($terms)) {
								$product_brands = array_merge($product_brands, $terms);
							}
						}
					}
				}
				$product_brands = array_unique($product_brands);

				if (!empty($product_brands) && isset($settings['product_brand_redirects']) && is_array($settings['product_brand_redirects'])) {
					foreach ($settings['product_brand_redirects'] as $redirect) {
						if (isset($redirect['brand_ids']) && is_array($redirect['brand_ids'])) {
							// Check if any of the order product brands match the redirect brands
							$matching_brands = array_intersect($product_brands, $redirect['brand_ids']);
							
							if (!empty($matching_brands)) {
								if (isset($redirect['redirect_type']) && $redirect['redirect_type'] === 'custom_url') {
									if (!empty($redirect['custom_url'])) {
										return $redirect['custom_url'];
									}
								} elseif (isset($redirect['page_id']) && !empty($redirect['page_id'])) {
									return get_permalink($redirect['page_id']);
								}
							}
						}
					}
				}
			}

			// If no redirect URL yet, check for payment method-based redirects
			if (!$redirect_url) {
				$payment_method = $order->get_payment_method();
				if ($payment_method && isset($settings['payment_method_redirects']) && is_array($settings['payment_method_redirects'])) {
					foreach ($settings['payment_method_redirects'] as $redirect) {
						if (isset($redirect['payment_methods']) && is_array($redirect['payment_methods'])) {
							if (in_array($payment_method, $redirect['payment_methods'])) {
								if (isset($redirect['redirect_type']) && $redirect['redirect_type'] === 'custom_url') {
									if (!empty($redirect['custom_url'])) {
										$redirect_url = $redirect['custom_url'];
										break;
									}
								} elseif (isset($redirect['page_id']) && !empty($redirect['page_id'])) {
									$redirect_url = get_permalink($redirect['page_id']);
									break;
								}
							}
						}
					}
				}
			}

			// If no redirect URL yet, check for user role-based redirects
			if (!$redirect_url) {
				$user_id = $order->get_user_id();
				if ($user_id) {
					$user = get_userdata($user_id);
					if ($user && isset($settings['user_role_redirects']) && is_array($settings['user_role_redirects'])) {
						foreach ($settings['user_role_redirects'] as $redirect) {
							if (isset($redirect['roles']) && is_array($redirect['roles'])) {
								// Check if any of the user roles match the redirect roles
								$matching_roles = array_intersect($user->roles, $redirect['roles']);
								
								if (!empty($matching_roles)) {
									if (isset($redirect['redirect_type']) && $redirect['redirect_type'] === 'custom_url') {
										if (!empty($redirect['custom_url'])) {
											$redirect_url = $redirect['custom_url'];
											break;
										}
									} elseif (isset($redirect['page_id']) && !empty($redirect['page_id'])) {
										$redirect_url = get_permalink($redirect['page_id']);
										break;
									}
								}
							}
						}
					}
				}
			}
		}

		// If no redirect URL yet, check global settings
		if ( ! $redirect_url ) {
			if ( isset( $settings['global'] ) ) {
				if ( isset( $settings['global']['redirect_type'] ) && $settings['global']['redirect_type'] === 'custom_url' ) {
					if ( ! empty( $settings['global']['custom_url'] ) ) {
						$redirect_url = $settings['global']['custom_url'];
					}
				} elseif ( isset( $settings['global']['page_id'] ) && ! empty( $settings['global']['page_id'] ) ) {
					$redirect_url = get_permalink( $settings['global']['page_id'] );
				}
			}
		}

		// If we have a redirect URL, always append order data for shortcodes to work
		if ( $redirect_url ) {
			// Always add order data parameters for shortcodes to work
			$redirect_url = add_query_arg( array(
				'order_id' => $order->get_id(),
				'key' => $order->get_order_key(),
			), $redirect_url );
		}

		return $redirect_url;
	}
}
