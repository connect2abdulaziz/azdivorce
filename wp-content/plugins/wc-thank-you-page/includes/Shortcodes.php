<?php

namespace WPKIN_THANK_YOU_PAGE;

/**
 * Thank you page Shortcodes class
 *
 * @since 1.0.2
 */
class Shortcodes {

	public $version;

	public function __construct() {
		$this->version = \WPKIN_THANK_YOU_PAGE\Helper::wpkin_tr_plugins_version_control();

		// Define free shortcodes that are available to all users
		$free_shortcodes = [
			'wpkin_order_number',
			'wpkin_transaction_id',
			'wpkin_order_status',
			'wpkin_order_created_date',
			'wpkin_customer_name',
			'wpkin_order_total',
		];

		// Define all shortcodes
		$all_shortcodes = [
			// 🎉 General Order Info
			'wpkin_order_number',
			'wpkin_transaction_id',
			'wpkin_order_status',
			'wpkin_order_created_date',

			// 💰 Pricing & Financial
			'wpkin_order_total',
			'wpkin_tax',
			'wpkin_shipping',
			'wpkin_currency',
			'wpkin_coupon',

			// 🧍 Customer Details
			'wpkin_customer_name',
			'wpkin_first_name',
			'wpkin_customer_email',
			'wpkin_billing_phone',

			// 🧾 Address Info
			'wpkin_billing_address',
			'wpkin_shipping_address',

			// 📦 Order Items
			'wpkin_items',
			'wpkin_item_count',

			// 🧾 Downloadable Content
			'wpkin_downloads',

			// 💳 Payment & Delivery
			'wpkin_payment_method',
			'wpkin_shipping_method',
		];

		// Determine which shortcodes to register based on version
		$shortcodes_to_register = $this->version ? $all_shortcodes : $free_shortcodes;

		// Register the appropriate shortcodes
		foreach ($shortcodes_to_register as $shortcode) {
			if (method_exists($this, $shortcode)) {
				add_shortcode($shortcode, [$this, $shortcode]);
			}
		}
		
		// For non-pro users, register dummy shortcodes that show a pro message
		if (!$this->version) {
			$pro_shortcodes = array_diff($all_shortcodes, $free_shortcodes);
			
			foreach ($pro_shortcodes as $shortcode) {
				add_shortcode($shortcode, [$this, 'pro_shortcode_message']);
			}
		}
	}

	private function get_order() {
		$order_id = isset( $_GET['order_id'] ) ? absint( wp_unslash( $_GET['order_id'] ) ) : 0;
		$order_key = isset( $_GET['key'] ) ? sanitize_text_field( wp_unslash( $_GET['key'] ) ) : '';
		
		if ( ! $order_id || ! $order_key ) {
			return false;
		}
		
		$order = wc_get_order( $order_id );
		
		// Verify order key for security
		if ( ! $order || $order->get_order_key() !== $order_key ) {
			return false;
		}
		
		return $order;
	}

	public function wpkin_order_number() {
		$order = $this->get_order();
		return $order ? $order->get_order_number() : '1245';
	}

	public function wpkin_first_name() {
		$order = $this->get_order();
		return $order ? $order->get_billing_first_name() : 'John';
	}

	public function wpkin_billing_address() {
		$order = $this->get_order();
		if ( $order ) {
			return nl2br( $order->get_formatted_billing_address() );
		}
		return "John Doe<br>123 Demo St<br>City, Country";
	}

	public function wpkin_shipping_address() {
		$order = $this->get_order();
		if ( $order ) {
			return nl2br( $order->get_formatted_shipping_address() );
		}
		return "John Doe<br>123 Demo St<br>City, Country";
	}

	public function wpkin_billing_phone() {
		$order = $this->get_order();
		return $order ? $order->get_billing_phone() : '+1 555-123-4567';
	}

	public function wpkin_downloads() {
		$order = $this->get_order();

		ob_start();

		if ( $order && $order->has_downloadable_item() && $order->is_download_permitted() ) {
			$downloads = $order->get_downloadable_items();

			if ( $downloads ) {
				echo '<ul>';
				foreach ( $downloads as $download ) {
					printf(
						'<li><a href="%s" target="_blank">%s</a></li>',
						esc_url( $download['download_url'] ),
						esc_html( $download['name'] )
					);
				}
				echo '</ul>';
			}
		} else {
			// Demo content
			echo '<ul>
				<li><a href="#">Sample Product File 1</a></li>
				<li><a href="#">Sample Product File 2</a></li>
			</ul>';
		}

		return ob_get_clean();
	}

	public function wpkin_order_status() {
		$order = $this->get_order();
		return $order ? ucfirst( $order->get_status() ) : 'Processing';
	}

	public function wpkin_order_created_date() {
		$order = $this->get_order();
		if ( $order ) {
			$date = $order->get_date_created();
			return gmdate( 'd, M Y', strtotime( $date ) );
		}
		return '26, Mar 2021';
	}

	// -------------------------
	// New wpkin_* shortcodes
	// -------------------------

	public function wpkin_transaction_id() {
		$order = $this->get_order();
		if ( ! $order ) {
			return 'txn_1QGvA2JieEoYUZGbCpzKvYX4';
		}
		$transaction_id = $order->get_transaction_id();
		return $transaction_id ? $transaction_id : 'txn_1QGvA2JieEoYUZGbCpzKvYX4';
	}

	public function wpkin_order_total() {
		$order = $this->get_order();
		return $order ? wc_price( $order->get_total() ) : wc_price( 99.99 );
	}

	public function wpkin_tax() {
		$order = $this->get_order();
		return $order ? wc_price( $order->get_total_tax() ) : wc_price( 5.00 );
	}

	public function wpkin_shipping() {
		$order = $this->get_order();
		return $order ? wc_price( $order->get_shipping_total() ) : wc_price( 10.00 );
	}

	public function wpkin_currency() {
		$order = $this->get_order();
		return $order ? $order->get_currency() : 'USD';
	}

	public function wpkin_coupon() {
		$order = $this->get_order();
		if ( ! $order ) {
			return 'WELCOME10';
		}
		$coupons = $order->get_coupon_codes();
		return implode( ', ', $coupons );
	}
	
	/**
	 * Pro shortcode message for free users
	 */
	public function pro_shortcode_message() {
		return '<span style="color: #999; font-style: italic;">' . esc_html__( '[PRO Feature - Upgrade to unlock]', 'wc-thank-you-page' ) . '</span>';
	}

	public function wpkin_items() {
		$order = $this->get_order();

		ob_start();
		?>
		<table style="width:100%; border-collapse: collapse;">
			<thead>
				<tr>
					<th style="border: 1px solid #ddd; padding: 8px;"><?php echo esc_html__('Product', 'wc-thank-you-page'); ?></th>
					<th style="border: 1px solid #ddd; padding: 8px;"><?php echo esc_html__('Quantity', 'wc-thank-you-page'); ?></th>
					<th style="border: 1px solid #ddd; padding: 8px;"><?php echo esc_html__('Price', 'wc-thank-you-page'); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php if ( $order ) : ?>
					<?php foreach ( $order->get_items() as $item ) : ?>
						<tr>
							<td style="border: 1px solid #ddd; padding: 8px;"><?php echo esc_html( $item->get_name() ); ?></td>
							<td style="border: 1px solid #ddd; padding: 8px;"><?php echo esc_html( $item->get_quantity() ); ?></td>
							<td style="border: 1px solid #ddd; padding: 8px;"><?php echo $order->get_formatted_line_subtotal( $item ); ?></td>
						</tr>
					<?php endforeach; ?>
				<?php else : ?>
					<tr>
						<td style="border: 1px solid #ddd; padding: 8px;"><?php echo esc_html__('Demo Product', 'wc-thank-you-page'); ?></td>
						<td style="border: 1px solid #ddd; padding: 8px;"><?php echo esc_html__('2', 'wc-thank-you-page'); ?></td>
						<td style="border: 1px solid #ddd; padding: 8px;"><?php echo wc_price( 49.99 ); ?></td>
					</tr>
					<tr>
						<td style="border: 1px solid #ddd; padding: 8px;"><?php echo esc_html__('Another Product', 'wc-thank-you-page'); ?></td>
						<td style="border: 1px solid #ddd; padding: 8px;"><?php echo esc_html__('1', 'wc-thank-you-page'); ?></td>
						<td style="border: 1px solid #ddd; padding: 8px;"><?php echo wc_price( 29.99 ); ?></td>
					</tr>
				<?php endif; ?>
			</tbody>
		</table>
		<?php
		return ob_get_clean();
	}

	public function wpkin_item_count() {
		$order = $this->get_order();
		return $order ? $order->get_item_count() : 3;
	}

	public function wpkin_customer_email() {
		$order = $this->get_order();
		return $order ? $order->get_billing_email() : 'demo@example.com';
	}

	public function wpkin_customer_name() {
		$order = $this->get_order();
		return $order ? $order->get_formatted_billing_full_name() : 'John Doe';
	}

	public function wpkin_payment_method() {
		$order = $this->get_order();
		return $order ? $order->get_payment_method_title() : 'Credit Card';
	}

	public function wpkin_shipping_method() {
		$order = $this->get_order();
		return $order ? $order->get_shipping_method() : 'Free Shipping';
	}
}
