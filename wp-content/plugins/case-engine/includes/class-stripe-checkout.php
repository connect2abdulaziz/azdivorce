<?php
/**
 * Stripe Checkout Session — create session for payment (Phase 3).
 *
 * Add to wp-config.php (use test keys for development):
 *   define( 'STRIPE_SECRET_KEY', 'sk_test_...' );
 *   define( 'STRIPE_PUBLISHABLE_KEY', 'pk_test_...' );
 *
 * @package Case_Engine
 */

defined( 'ABSPATH' ) || exit;

class Case_Engine_Stripe_Checkout {

	public static function register() {
		add_action( 'wp_ajax_case_engine_create_checkout_session', array( __CLASS__, 'ajax_create_checkout_session' ) );
	}

	public static function get_secret_key() {
		return defined( 'STRIPE_SECRET_KEY' ) ? STRIPE_SECRET_KEY : '';
	}

	public static function get_publishable_key() {
		return defined( 'STRIPE_PUBLISHABLE_KEY' ) ? STRIPE_PUBLISHABLE_KEY : '';
	}

	public static function is_configured() {
		return self::get_secret_key() !== '' && self::get_publishable_key() !== '';
	}

	public static function ajax_create_checkout_session() {
		check_ajax_referer( 'az_intake', 'nonce' );

		if ( ! is_user_logged_in() ) {
			wp_send_json_error( array( 'message' => __( 'Please log in to continue.', 'case-engine' ) ) );
		}

		$session_key = isset( $_POST['session_key'] ) ? sanitize_text_field( wp_unslash( $_POST['session_key'] ) ) : '';
		if ( ! $session_key ) {
			wp_send_json_error( array( 'message' => __( 'Invalid session.', 'case-engine' ) ) );
		}

		if ( ! self::is_configured() ) {
			wp_send_json_error( array( 'message' => __( 'Payment is not configured. Please try again later.', 'case-engine' ) ) );
		}

		$session = self::get_intake_session( $session_key );
		if ( ! $session ) {
			wp_send_json_error( array( 'message' => __( 'Session not found.', 'case-engine' ) ) );
		}
		if ( (int) $session['user_id'] !== get_current_user_id() ) {
			wp_send_json_error( array( 'message' => __( 'Invalid session.', 'case-engine' ) ) );
		}

		$case_id = ! empty( $session['case_id'] ) ? (int) $session['case_id'] : 0;
		if ( ! $case_id ) {
			wp_send_json_error( array( 'message' => __( 'Case not found. Complete the review step first.', 'case-engine' ) ) );
		}

		global $wpdb;
		$cases_table = $wpdb->prefix . 'az_cases';
		$case_status = $wpdb->get_var( $wpdb->prepare(
			"SELECT status FROM {$cases_table} WHERE id = %d",
			$case_id
		) );
		if ( $case_status === 'paid' ) {
			wp_send_json_error( array( 'message' => __( 'This case is already paid.', 'case-engine' ) ) );
		}

		$session_id_int = (int) $session['id'];
		$dashboard_url = home_url( '/client-dashboard/' );
		$success_url  = add_query_arg(
			array(
				'payment'     => 'success',
				'session_id'  => '{CHECKOUT_SESSION_ID}',
				'case_id'     => $case_id,
			),
			$dashboard_url
		);
		$intake_url   = home_url( '/' );
		$cancel_url   = add_query_arg( array( 'cancelled' => '1' ), $intake_url );

		$amount_cents = apply_filters( 'case_engine_stripe_amount_cents', 9999, $case_id );
		$currency     = apply_filters( 'case_engine_stripe_currency', 'usd', $case_id );
		$description  = apply_filters( 'case_engine_stripe_description', __( 'Uncontested divorce — case packet', 'case-engine' ), $case_id );

		try {
			$stripe = self::get_stripe_client();
			if ( ! $stripe ) {
				wp_send_json_error( array( 'message' => __( 'Payment service unavailable.', 'case-engine' ) ) );
			}

			$checkout_session = \Stripe\Checkout\Session::create( array(
				'mode'                 => 'payment',
				'payment_method_types' => array( 'card' ),
				'line_items'           => array(
					array(
						'price_data' => array(
							'currency'     => $currency,
							'product_data' => array(
								'name'        => $description,
								'description' => __( 'Arizona divorce document packet.', 'case-engine' ),
							),
							'unit_amount'  => $amount_cents,
						),
						'quantity'   => 1,
					),
				),
				'success_url'        => $success_url,
				'cancel_url'          => $cancel_url,
				'client_reference_id' => (string) $session_key,
				'metadata'            => array(
					'intake_session_id' => (string) $session_id_int,
					'case_id'           => (string) $case_id,
				),
			) );

			wp_send_json_success( array( 'sessionId' => $checkout_session->id ) );
		} catch ( \Exception $e ) {
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				wp_send_json_error( array( 'message' => $e->getMessage() ) );
			}
			wp_send_json_error( array( 'message' => __( 'Could not start checkout. Please try again.', 'case-engine' ) ) );
		}
	}

	private static function get_stripe_client() {
		$autoload = CASE_ENGINE_PLUGIN_DIR . 'vendor/autoload.php';
		if ( ! file_exists( $autoload ) ) {
			return false;
		}
		require_once $autoload;
		$key = self::get_secret_key();
		if ( ! $key ) {
			return false;
		}
		\Stripe\Stripe::setApiKey( $key );
		return true;
	}

	private static function get_intake_session( $session_key ) {
		global $wpdb;
		$table = $wpdb->prefix . 'az_intake_sessions';
		return $wpdb->get_row(
			$wpdb->prepare(
				"SELECT id, session_key, user_id, status, case_id FROM {$table} WHERE session_key = %s",
				$session_key
			),
			ARRAY_A
		);
	}
}
