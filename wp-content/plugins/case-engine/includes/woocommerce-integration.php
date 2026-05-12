<?php
/**
 * WooCommerce Integration — links completed orders to Case Engine cases.
 *
 * @package Case_Engine
 */

defined( 'ABSPATH' ) || exit;

class Case_Engine_WooCommerce_Integration {

    /**
     * Register hooks.
     */
    public static function register() {
		// Capture az_session_key and az_case_id from the intake redirect URL into the WooCommerce session.
		add_action( 'template_redirect', array( __CLASS__, 'capture_intake_params' ) );

		// Output hidden fields on checkout so case_id/session_key are always submitted (survives Stripe redirect).
		add_action( 'woocommerce_after_order_notes', array( __CLASS__, 'checkout_hidden_case_fields' ) );

		// Store session_key and case_id in order meta when checkout is submitted
		add_action( 'woocommerce_checkout_create_order', array( __CLASS__, 'save_case_meta_to_order' ), 10, 2 );

		// When order is paid/completed, mark the case as paid (multiple hooks for different gateways).
		add_action( 'woocommerce_payment_complete', array( __CLASS__, 'handle_order_paid' ) );
		add_action( 'woocommerce_order_status_processing', array( __CLASS__, 'handle_order_paid' ) );
		add_action( 'woocommerce_order_status_completed', array( __CLASS__, 'handle_order_paid' ) );

		// After successful payment, redirect to client dashboard.
		add_filter( 'woocommerce_get_return_url', array( __CLASS__, 'redirect_to_dashboard' ), 999, 2 );
		add_action( 'woocommerce_thankyou', array( __CLASS__, 'redirect_on_thankyou' ), 5, 1 );

		// Hard redirect from the order-received page to the dashboard (fallback for gateways that bypass filters).
		add_action( 'template_redirect', array( __CLASS__, 'redirect_order_received_page' ), 5 );
		// On payment-success return, ensure case status syncs even if gateway hooks fired out of order.
		add_action( 'template_redirect', array( __CLASS__, 'maybe_sync_paid_case_from_return' ), 4 );

		// Keep intake users on the payment path after login/registration from My Account.
		add_filter( 'woocommerce_login_redirect', array( __CLASS__, 'maybe_redirect_after_auth' ), 10, 2 );
		add_filter( 'woocommerce_registration_redirect', array( __CLASS__, 'maybe_redirect_after_registration' ), 10, 1 );
    }

	/**
	 * When arriving from the intake payment step, store az_session_key and az_case_id in WC session.
	 */
	public static function capture_intake_params() {
		if ( ! function_exists( 'WC' ) || ! WC()->session ) {
			return;
		}

		if ( empty( $_GET['az_session_key'] ) && empty( $_GET['az_case_id'] ) ) {
			return;
		}

		$session = WC()->session;

		if ( ! empty( $_GET['az_session_key'] ) ) {
			$session->set( 'az_session_key', sanitize_text_field( wp_unslash( $_GET['az_session_key'] ) ) );
		}

		if ( ! empty( $_GET['az_case_id'] ) ) {
			$session->set( 'az_case_id', (int) $_GET['az_case_id'] );
		}

		// If user is already authenticated, immediately link this intake flow ownership.
		if ( is_user_logged_in() ) {
			$user_id     = get_current_user_id();
			$session_key = ! empty( $_GET['az_session_key'] ) ? sanitize_text_field( wp_unslash( $_GET['az_session_key'] ) ) : '';
			$case_id     = ! empty( $_GET['az_case_id'] ) ? (int) $_GET['az_case_id'] : 0;
			self::link_case_and_session_to_user( $case_id, $session_key, $user_id );
		}
	}

	/**
	 * Output hidden fields on checkout so case_id and session_key are in POST when order is created (survives Stripe redirect).
	 */
	public static function checkout_hidden_case_fields() {
		if ( ! function_exists( 'WC' ) || ! WC()->session ) {
			return;
		}
		$session_key = WC()->session->get( 'az_session_key', '' );
		$case_id     = (int) WC()->session->get( 'az_case_id', 0 );
		if ( ! $session_key && ! $case_id ) {
			return;
		}
		if ( $session_key ) {
			echo '<input type="hidden" name="az_session_key" value="' . esc_attr( $session_key ) . '" />';
		}
		if ( $case_id ) {
			echo '<input type="hidden" name="az_case_id" value="' . (int) $case_id . '" />';
		}
	}

	/**
	 * Save az_session_key and az_case_id from POST/session into order meta.
     */
    public static function save_case_meta_to_order( $order, $data ) {
        // Try from POST first (checkout form), then from cookie/session
        $session_key = '';
        $case_id     = 0;

        if ( ! empty( $_POST['az_session_key'] ) ) {
            $session_key = sanitize_text_field( wp_unslash( $_POST['az_session_key'] ) );
        } elseif ( WC()->session ) {
            $session_key = WC()->session->get( 'az_session_key', '' );
        }

        if ( ! empty( $_POST['az_case_id'] ) ) {
            $case_id = (int) $_POST['az_case_id'];
        } elseif ( WC()->session ) {
            $case_id = (int) WC()->session->get( 'az_case_id', 0 );
        }

        if ( $session_key ) {
            $order->update_meta_data( '_az_session_key', $session_key );
        }
        if ( $case_id ) {
            $order->update_meta_data( '_az_case_id', $case_id );
        }
    }

    /**
     * When order is paid, mark case as paid.
     *
     * @param int $order_id WooCommerce order ID.
     */
    public static function handle_order_paid( $order_id ) {
        $order = wc_get_order( $order_id );
        if ( ! $order ) {
            return;
        }

        $case_id     = (int) $order->get_meta( '_az_case_id' );
        $session_key = (string) $order->get_meta( '_az_session_key' );

        if ( ! $case_id && $session_key ) {
            // Try to find case by session key
            global $wpdb;
            $sessions_table = $wpdb->prefix . 'az_intake_sessions';
            $row = $wpdb->get_row( $wpdb->prepare(
                "SELECT case_id FROM {$sessions_table} WHERE session_key = %s",
                $session_key
            ), ARRAY_A );
            if ( $row && ! empty( $row['case_id'] ) ) {
                $case_id = (int) $row['case_id'];
            }
        }

        if ( ! $case_id ) {
            return;
        }

		$order_user_id = self::resolve_order_user_id( $order );

        // Even if payment was already marked earlier, still ensure ownership is linked.
        if ( $order->get_meta( '_az_case_marked_paid' ) ) {
			if ( $order_user_id > 0 ) {
				self::link_case_and_session_to_user( $case_id, $session_key, $order_user_id );
			}
			// Reconcile: if flag exists but case is still pending_payment, force status to paid.
			global $wpdb;
			$cases_table = $wpdb->prefix . 'az_cases';
			$case_row    = $wpdb->get_row( $wpdb->prepare(
				"SELECT status, intake_session_id FROM {$cases_table} WHERE id = %d LIMIT 1",
				$case_id
			), ARRAY_A );
			if ( $case_row && isset( $case_row['status'] ) && $case_row['status'] === 'pending_payment' ) {
				$amount         = (float) $order->get_total();
				$currency       = strtoupper( $order->get_currency() );
				$transaction_id = $order->get_transaction_id() ?: ( 'wc_order_' . $order_id );
				Case_Engine_Case_Factory::mark_case_paid_from_stripe(
					$case_id,
					(int) ( $case_row['intake_session_id'] ?? 0 ),
					$amount,
					$currency,
					$transaction_id
				);
			}
			self::clear_frontend_intake_cookies();
            return;
        }

        // Get the intake session ID for the case
        global $wpdb;
        $cases_table = $wpdb->prefix . 'az_cases';
        $case = $wpdb->get_row( $wpdb->prepare(
            "SELECT id, intake_session_id FROM {$cases_table} WHERE id = %d",
            $case_id
        ), ARRAY_A );

        if ( ! $case ) {
            return;
        }

        $intake_session_id = (int) $case['intake_session_id'];

        $amount            = (float) $order->get_total();
        $currency          = strtoupper( $order->get_currency() );
        $transaction_id    = $order->get_transaction_id() ?: ( 'wc_order_' . $order_id );

        // Mark case as paid, storing payment details on both az_cases and az_payments.
        Case_Engine_Case_Factory::mark_case_paid_from_stripe(
            $case_id,
            $intake_session_id,
            $amount,
            $currency,
            $transaction_id
        );

		if ( $order_user_id > 0 ) {
			self::link_case_and_session_to_user( $case_id, $session_key, $order_user_id );
		}

        // Mark order so we don't double-process
        $order->update_meta_data( '_az_case_marked_paid', '1' );
        $order->save();
		self::clear_frontend_intake_cookies();
    }

    /**
     * Redirect to client dashboard after payment instead of WooCommerce order-received page.
     *
     * @param string   $return_url Default return URL.
     * @param WC_Order $order      Order object.
     * @return string
     */
	public static function redirect_to_dashboard( $return_url, $order ) {
		if ( ! $order ) {
			return $return_url;
		}
		$case_id = (int) $order->get_meta( '_az_case_id' );
		if ( $case_id ) {
			$args = array(
				'payment'    => 'success',
				'case_id'    => $case_id,
				'order'      => $order->get_id(),
				'az_case_id' => $case_id,
			);
			$session_key = $order->get_meta( '_az_session_key' );
			if ( $session_key ) {
				$args['az_session_key'] = $session_key;
			}
			return add_query_arg( $args, home_url( '/client-dashboard/' ) );
		}
		return $return_url;
	}

	/**
	 * Fallback: if some gateway overrides the return URL, force redirect from thank-you page.
	 *
	 * @param int $order_id WooCommerce order ID.
	 */
	public static function redirect_on_thankyou( $order_id ) {
		$order = wc_get_order( $order_id );
		if ( ! $order ) {
			return;
		}
		$case_id = (int) $order->get_meta( '_az_case_id' );
		if ( ! $case_id ) {
			return;
		}
		$args = array(
			'payment'    => 'success',
			'case_id'    => $case_id,
			'order'      => $order->get_id(),
			'az_case_id' => $case_id,
		);
		$session_key = $order->get_meta( '_az_session_key' );
		if ( $session_key ) {
			$args['az_session_key'] = $session_key;
		}
		wp_safe_redirect( add_query_arg( $args, home_url( '/client-dashboard/' ) ) );
		exit;
	}

	/**
	 * Final fallback: on order-received page, redirect to client dashboard with order_id, key, and (from cookie if needed) az_case_id and az_session_key.
	 */
	public static function redirect_order_received_page() {
		if ( ! function_exists( 'is_order_received_page' ) || ! is_order_received_page() ) {
			return;
		}

		$order_id = isset( $_GET['order-received'] ) ? absint( $_GET['order-received'] ) : 0;
		if ( ! $order_id ) {
			global $wp;
			if ( isset( $wp->query_vars['order-received'] ) ) {
				$order_id = absint( $wp->query_vars['order-received'] );
			}
		}

		if ( ! $order_id ) {
			return;
		}

		$order = wc_get_order( $order_id );
		if ( ! $order ) {
			return;
		}

		$args = array(
			'order_id' => $order->get_id(),
			'key'      => $order->get_order_key(),
			'payment'  => 'success',
		);

		$case_id = (int) $order->get_meta( '_az_case_id' );
		if ( $case_id ) {
			$args['az_case_id'] = $case_id;
			$session_key = $order->get_meta( '_az_session_key' );
			if ( $session_key ) {
				$args['az_session_key'] = $session_key;
			}
		} else {
			if ( ! empty( $_COOKIE['az_pending_case_id'] ) ) {
				$args['az_case_id'] = absint( $_COOKIE['az_pending_case_id'] );
			}
			if ( ! empty( $_COOKIE['az_pending_session_key'] ) ) {
				$args['az_session_key'] = sanitize_text_field( wp_unslash( $_COOKIE['az_pending_session_key'] ) );
			}
		}

		wp_safe_redirect( add_query_arg( $args, home_url( '/client-dashboard/' ) ) );
		exit;
	}

	/**
	 * Reconcile paid status on return to dashboard.
	 * Handles cases where gateway redirects before our order->case linking finalized.
	 */
	public static function maybe_sync_paid_case_from_return() {
		if ( empty( $_GET['payment'] ) || 'success' !== sanitize_text_field( wp_unslash( $_GET['payment'] ) ) ) {
			return;
		}

		$order_id = 0;
		if ( ! empty( $_GET['order'] ) ) {
			$order_id = absint( $_GET['order'] );
		} elseif ( ! empty( $_GET['order_id'] ) ) {
			$order_id = absint( $_GET['order_id'] );
		}
		if ( ! $order_id ) {
			return;
		}

		$order = wc_get_order( $order_id );
		if ( ! $order || ! $order->is_paid() ) {
			return;
		}

		$case_id     = (int) $order->get_meta( '_az_case_id' );
		$session_key = (string) $order->get_meta( '_az_session_key' );

		if ( ! $case_id && ! empty( $_GET['az_case_id'] ) ) {
			$case_id = absint( $_GET['az_case_id'] );
		}
		if ( ! $session_key && ! empty( $_GET['az_session_key'] ) ) {
			$session_key = sanitize_text_field( wp_unslash( $_GET['az_session_key'] ) );
		}
		if ( ! $session_key && ! empty( $_COOKIE['az_pending_session_key'] ) ) {
			$session_key = sanitize_text_field( wp_unslash( $_COOKIE['az_pending_session_key'] ) );
		}

		if ( $case_id && ! $order->get_meta( '_az_case_id' ) ) {
			$order->update_meta_data( '_az_case_id', $case_id );
		}
		if ( $session_key && ! $order->get_meta( '_az_session_key' ) ) {
			$order->update_meta_data( '_az_session_key', $session_key );
		}
		if ( $order->get_changes() ) {
			$order->save();
		}

		$current_uid = get_current_user_id();
		if ( $current_uid > 0 ) {
			self::link_case_and_session_to_user( $case_id, $session_key, $current_uid );
		} else {
			$order_uid = self::resolve_order_user_id( $order );
			if ( $order_uid > 0 ) {
				self::link_case_and_session_to_user( $case_id, $session_key, $order_uid );
			}
		}

		self::handle_order_paid( $order_id );
		self::clear_frontend_intake_cookies();
	}

	/**
	 * Resolve user id for an order.
	 * Priority: order customer_id -> existing user by billing email -> auto-create user with temp password.
	 *
	 * @param WC_Order $order Order object.
	 * @return int
	 */
	private static function resolve_order_user_id( $order ) {
		$order_user_id = (int) $order->get_user_id();
		if ( $order_user_id > 0 ) {
			return $order_user_id;
		}
		$billing_email = sanitize_email( $order->get_billing_email() );
		if ( ! $billing_email ) {
			return 0;
		}
		$user_by_email = get_user_by( 'email', $billing_email );
		if ( $user_by_email ) {
			return (int) $user_by_email->ID;
		}

		// Auto-create a user account with a generated temporary password.
		$username_base = sanitize_user( current( explode( '@', $billing_email ) ), true );
		if ( '' === $username_base ) {
			$username_base = 'client';
		}
		$username = $username_base;
		$i        = 1;
		while ( username_exists( $username ) ) {
			$username = $username_base . $i;
			$i++;
		}

		$temp_password = wp_generate_password( 16, true, true );
		$new_user_id   = wp_create_user( $username, $temp_password, $billing_email );
		if ( is_wp_error( $new_user_id ) || ! $new_user_id ) {
			return 0;
		}

		$new_user = get_user_by( 'id', (int) $new_user_id );
		if ( $new_user && ! in_array( 'customer', (array) $new_user->roles, true ) ) {
			$new_user->set_role( 'customer' );
		}

		// Attach the newly created user to the Woo order.
		$order->set_customer_id( (int) $new_user_id );
		$order->save();

		return (int) $new_user_id;
	}

	/**
	 * Link case + intake session ownership to a user.
	 *
	 * @param int    $case_id     Case ID.
	 * @param string $session_key Intake session key.
	 * @param int    $user_id     WordPress user ID.
	 * @return void
	 */
	private static function link_case_and_session_to_user( $case_id, $session_key, $user_id ) {
		$case_id     = (int) $case_id;
		$session_key = (string) $session_key;
		$user_id     = (int) $user_id;
		if ( $user_id <= 0 ) {
			return;
		}

		global $wpdb;
		$cases_table    = $wpdb->prefix . 'az_cases';
		$sessions_table = $wpdb->prefix . 'az_intake_sessions';

		$session_id = 0;
		if ( $session_key ) {
			$session_id = (int) $wpdb->get_var( $wpdb->prepare(
				"SELECT id FROM {$sessions_table} WHERE session_key = %s LIMIT 1",
				$session_key
			) );
		}
		if ( $session_id > 0 && $case_id <= 0 ) {
			$case_id = (int) $wpdb->get_var( $wpdb->prepare(
				"SELECT case_id FROM {$sessions_table} WHERE id = %d LIMIT 1",
				$session_id
			) );
		}
		if ( $case_id > 0 && $session_id <= 0 ) {
			$session_id = (int) $wpdb->get_var( $wpdb->prepare(
				"SELECT intake_session_id FROM {$cases_table} WHERE id = %d LIMIT 1",
				$case_id
			) );
		}

		if ( $case_id > 0 ) {
			$current_uid = (int) $wpdb->get_var( $wpdb->prepare(
				"SELECT user_id FROM {$cases_table} WHERE id = %d LIMIT 1",
				$case_id
			) );
			if ( $current_uid === 0 || $current_uid === $user_id ) {
				$wpdb->update(
					$cases_table,
					array( 'user_id' => $user_id, 'updated_at' => current_time( 'mysql' ) ),
					array( 'id' => $case_id ),
					array( '%d', '%s' ),
					array( '%d' )
				);
			}
		}

		if ( $session_id > 0 ) {
			$current_session_uid = (int) $wpdb->get_var( $wpdb->prepare(
				"SELECT user_id FROM {$sessions_table} WHERE id = %d LIMIT 1",
				$session_id
			) );
			if ( $current_session_uid === 0 || $current_session_uid === $user_id ) {
				$wpdb->update(
					$sessions_table,
					array( 'user_id' => $user_id, 'updated_at' => current_time( 'mysql' ) ),
					array( 'id' => $session_id ),
					array( '%d', '%s' ),
					array( '%d' )
				);
			}
		}

		// If case is still pending, try to reconcile from the latest paid order for this user.
		if ( $case_id > 0 ) {
			self::maybe_mark_case_paid_for_user( $case_id, $user_id, $session_key );
		}
	}

	/**
	 * Fallback reconciliation: if case remains pending, map a paid order and mark paid.
	 *
	 * @param int    $case_id     Case ID.
	 * @param int    $user_id     User ID.
	 * @param string $session_key Session key.
	 * @return void
	 */
	private static function maybe_mark_case_paid_for_user( $case_id, $user_id, $session_key = '' ) {
		$case_id     = (int) $case_id;
		$user_id     = (int) $user_id;
		$session_key = (string) $session_key;
		if ( $case_id <= 0 || $user_id <= 0 || ! function_exists( 'wc_get_orders' ) ) {
			return;
		}

		global $wpdb;
		$cases_table = $wpdb->prefix . 'az_cases';
		$case_row    = $wpdb->get_row( $wpdb->prepare(
			"SELECT status, intake_session_id FROM {$cases_table} WHERE id = %d LIMIT 1",
			$case_id
		), ARRAY_A );
		if ( ! $case_row || ( $case_row['status'] ?? '' ) !== 'pending_payment' ) {
			return;
		}

		$order = false;
		$orders = wc_get_orders( array(
			'limit'       => 1,
			'orderby'     => 'date',
			'order'       => 'DESC',
			'customer_id' => $user_id,
			'status'      => array( 'processing', 'completed' ),
			'meta_key'    => '_az_case_id',
			'meta_value'  => $case_id,
		) );
		if ( ! empty( $orders ) ) {
			$order = $orders[0];
		}

		if ( ! $order && $session_key ) {
			$orders = wc_get_orders( array(
				'limit'       => 1,
				'orderby'     => 'date',
				'order'       => 'DESC',
				'customer_id' => $user_id,
				'status'      => array( 'processing', 'completed' ),
				'meta_key'    => '_az_session_key',
				'meta_value'  => $session_key,
			) );
			if ( ! empty( $orders ) ) {
				$order = $orders[0];
			}
		}

		// Last-resort: latest paid order by user for the case product, with no linked case yet.
		if ( ! $order ) {
			$candidate_orders = wc_get_orders( array(
				'limit'       => 5,
				'orderby'     => 'date',
				'order'       => 'DESC',
				'customer_id' => $user_id,
				'status'      => array( 'processing', 'completed' ),
			) );
			$product_id = (int) apply_filters( 'case_engine_wc_product_id', 123 );
			foreach ( $candidate_orders as $candidate ) {
				if ( ! $candidate || ! $candidate->is_paid() ) {
					continue;
				}
				$linked_case = (int) $candidate->get_meta( '_az_case_id' );
				if ( $linked_case > 0 && $linked_case !== $case_id ) {
					continue;
				}
				$has_product = false;
				foreach ( $candidate->get_items() as $item ) {
					if ( (int) $item->get_product_id() === $product_id ) {
						$has_product = true;
						break;
					}
				}
				if ( $has_product ) {
					$order = $candidate;
					break;
				}
			}
		}

		if ( ! $order || ! $order->is_paid() ) {
			return;
		}

		if ( ! (int) $order->get_meta( '_az_case_id' ) ) {
			$order->update_meta_data( '_az_case_id', $case_id );
		}
		if ( $session_key && ! (string) $order->get_meta( '_az_session_key' ) ) {
			$order->update_meta_data( '_az_session_key', $session_key );
		}
		$order->save();

		$amount         = (float) $order->get_total();
		$currency       = strtoupper( $order->get_currency() );
		$transaction_id = $order->get_transaction_id() ?: ( 'wc_order_' . $order->get_id() );
		Case_Engine_Case_Factory::mark_case_paid_from_stripe(
			$case_id,
			(int) ( $case_row['intake_session_id'] ?? 0 ),
			$amount,
			$currency,
			$transaction_id
		);
		$order->update_meta_data( '_az_case_marked_paid', '1' );
		$order->save();
		self::clear_frontend_intake_cookies();
	}

	/**
	 * Clear flow cookies on frontend responses.
	 *
	 * @return void
	 */
	private static function clear_frontend_intake_cookies() {
		if ( is_admin() || headers_sent() ) {
			return;
		}
		$cookies = array( 'az_pending_case_id', 'az_pending_session_key', 'az_intake_session', 'az_intake_pending_sk' );
		if ( PHP_VERSION_ID >= 70300 ) {
			foreach ( $cookies as $cookie_name ) {
				@setcookie( $cookie_name, '', array( 'expires' => time() - 3600, 'path' => '/', 'samesite' => 'Lax' ) );
			}
		} else {
			foreach ( $cookies as $cookie_name ) {
				@setcookie( $cookie_name, '', time() - 3600, '/; samesite=Lax' );
			}
		}
	}

	/**
	 * After Woo login, honor redirect_to so intake guests continue to checkout.
	 *
	 * @param string  $redirect Redirect URL.
	 * @param WP_User $user     Logged-in user.
	 * @return string
	 */
	public static function maybe_redirect_after_auth( $redirect, $user ) {
		unset( $user ); // Not needed; kept for hook signature compatibility.
		if ( empty( $_REQUEST['redirect_to'] ) ) {
			return $redirect;
		}
		$requested = wp_unslash( $_REQUEST['redirect_to'] );
		return wp_validate_redirect( $requested, $redirect );
	}

	/**
	 * After Woo registration, honor redirect_to so new users continue to checkout.
	 *
	 * @param string $redirect Redirect URL.
	 * @return string
	 */
	public static function maybe_redirect_after_registration( $redirect ) {
		if ( empty( $_REQUEST['redirect_to'] ) ) {
			return $redirect;
		}
		$requested = wp_unslash( $_REQUEST['redirect_to'] );
		return wp_validate_redirect( $requested, $redirect );
	}
}