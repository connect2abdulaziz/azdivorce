<?php
/**
 * Intake AJAX: save progress, evaluate gates, create case shell.
 *
 * @package Case_Engine
 */

defined( 'ABSPATH' ) || exit;

class Case_Engine_Intake_Handler {

	/**
	 * Register AJAX actions.
	 */
	public static function register() {
		add_action( 'wp_ajax_az_intake_nonce', array( __CLASS__, 'ajax_nonce' ) );
		add_action( 'wp_ajax_nopriv_az_intake_nonce', array( __CLASS__, 'ajax_nonce' ) );
		add_action( 'wp_ajax_az_intake_save', array( __CLASS__, 'ajax_save' ) );
		add_action( 'wp_ajax_nopriv_az_intake_save', array( __CLASS__, 'ajax_save' ) );
		add_action( 'wp_ajax_az_intake_restore', array( __CLASS__, 'ajax_restore' ) );
		add_action( 'wp_ajax_nopriv_az_intake_restore', array( __CLASS__, 'ajax_restore' ) );
		add_action( 'wp_ajax_az_intake_gate', array( __CLASS__, 'ajax_gate' ) );
		add_action( 'wp_ajax_nopriv_az_intake_gate', array( __CLASS__, 'ajax_gate' ) );
		add_action( 'wp_ajax_az_intake_payment', array( __CLASS__, 'ajax_payment' ) );
		add_action( 'wp_ajax_nopriv_az_intake_payment', array( __CLASS__, 'ajax_payment' ) );
		add_action( 'wp_ajax_az_intake_complete', array( __CLASS__, 'ajax_complete' ) );
		add_action( 'wp_ajax_nopriv_az_intake_complete', array( __CLASS__, 'ajax_complete' ) );
	}

	/**
	 * AJAX: return a fresh nonce (uncached). Used when a cached page has a stale nonce.
	 */
	public static function ajax_nonce() {
		wp_send_json_success(
			array(
				'nonce'   => wp_create_nonce( 'az_intake' ),
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
			)
		);
	}

	/**
	 * Get or create session key (cookie or transient).
	 */
	private static function get_session_key() {
		if ( isset( $_COOKIE['az_intake_session'] ) && preg_match( '/^[a-z0-9]{32}$/', $_COOKIE['az_intake_session'] ) ) {
			return sanitize_text_field( $_COOKIE['az_intake_session'] );
		}
		return wp_generate_password( 32, false );
	}

	/**
	 * Get intake session row.
	 */
	private static function get_session( $session_key ) {
		global $wpdb;
		$table = $wpdb->prefix . 'az_intake_sessions';
		return $wpdb->get_row( $wpdb->prepare(
			"SELECT * FROM $table WHERE session_key = %s",
			$session_key
		), ARRAY_A );
	}

	/**
	 * Save or update session.
	 */
	private static function save_session( $session_key, $data ) {
		global $wpdb;
		$table = $wpdb->prefix . 'az_intake_sessions';
		$existing = self::get_session( $session_key );
		$answers = isset( $data['answers'] ) ? $data['answers'] : array();
		$answers_json = wp_json_encode( $answers );
		$user_id = get_current_user_id();
		$status = isset( $data['status'] ) ? $data['status'] : 'in_progress';
		$current_screen = isset( $data['current_screen'] ) ? (int) $data['current_screen'] : 1;

		if ( $existing ) {
			$wpdb->update(
				$table,
				array(
					'user_id'        => $user_id,
					'status'         => $status,
					'current_screen' => $current_screen,
					'answers'        => $answers_json,
					'updated_at'     => current_time( 'mysql' ),
				),
				array( 'session_key' => $session_key ),
				array( '%d', '%s', '%d', '%s', '%s' ),
				array( '%s' )
			);
		} else {
			$wpdb->insert(
				$table,
				array(
					'session_key'    => $session_key,
					'user_id'        => $user_id,
					'status'         => $status,
					'current_screen' => $current_screen,
					'answers'        => $answers_json,
				),
				array( '%s', '%d', '%s', '%d', '%s' )
			);
		}
	}

	/**
	 * Recursively sanitize answers so nested arrays (e.g. children) are preserved.
	 */
	private static function sanitize_answers_deep( $arr ) {
		$out = array();
		foreach ( $arr as $k => $v ) {
			$key = is_numeric( $k ) ? $k : sanitize_key( $k );
			if ( is_array( $v ) ) {
				$out[ $key ] = self::sanitize_answers_deep( $v );
			} else {
				$out[ $key ] = sanitize_text_field( (string) $v );
			}
		}
		return $out;
	}

	/**
	 * AJAX: save current screen answers and optionally advance.
	 */
	public static function ajax_save() {
		check_ajax_referer( 'az_intake', 'nonce' );
		$session_key = isset( $_POST['session_key'] ) ? sanitize_text_field( wp_unslash( $_POST['session_key'] ) ) : '';
		// Empty or invalid key = new session (e.g. user reloaded intake page). Generate so we always create a new row.
		if ( ! $session_key || ! preg_match( '/^[a-zA-Z0-9]{32}$/', $session_key ) ) {
			$session_key = wp_generate_password( 32, false );
		}
		$current = isset( $_POST['current_screen'] ) ? (int) $_POST['current_screen'] : 1;
		$answers_raw = isset( $_POST['answers'] ) && is_array( $_POST['answers'] ) ? wp_unslash( $_POST['answers'] ) : array();
		$answers = self::sanitize_answers_deep( $answers_raw );

		$existing        = self::get_session( $session_key );
		$current_user_id = get_current_user_id();

		// If session belongs to a different user, start a fresh one (privacy — never overwrite another user's data).
		if ( $existing && (int) $existing['user_id'] !== $current_user_id ) {
			$session_key = wp_generate_password( 32, false );
			$existing    = null;
		}

		// If the existing session is in a terminal state (completed / stopped), the user is starting a
		// brand-new intake. Generate a fresh session key so a new case can be created later.
		// A stopped session CAN be restarted IF it has no case yet (user hit a gate and is retrying).
		if ( $existing ) {
			$existing_status  = $existing['status'] ?? '';
			$existing_case_id = ! empty( $existing['case_id'] ) ? (int) $existing['case_id'] : 0;

			$is_completed = ( $existing_status === 'completed' );
			// A stopped session that already produced a case should also start fresh.
			$is_stale_stopped = ( $existing_status === 'stopped' && $existing_case_id > 0 );

			if ( $is_completed || $is_stale_stopped ) {
				$session_key = wp_generate_password( 32, false );
				$existing    = null;
			}
		}
		$existing_answers = array();
		if ( $existing && ! empty( $existing['answers'] ) ) {
			$existing_answers = json_decode( $existing['answers'], true );
			if ( ! is_array( $existing_answers ) ) {
				$existing_answers = array();
			}
		}
		$merged = array_merge( $existing_answers, $answers );

		// Screen 5: don't use stale children_agreement from a previous attempt when the current form didn't send it (e.g. no children or user didn't select).
		if ( $current === 5 && ! array_key_exists( 'children_agreement', $answers ) ) {
			unset( $merged['children_agreement'] );
		}

		// If gate fails, mark session as stopped and do not advance.
		$gate_result = Case_Engine_Intake_Flow::evaluate_gate( $current, $merged );
		if ( ! empty( $gate_result['can_proceed'] ) === false ) {
			self::save_session( $session_key, array(
				'answers'        => $merged,
				'status'         => 'stopped',
				'current_screen' => $current,
			) );
			wp_send_json_success( array(
				'can_proceed'  => false,
				'stop_message' => $gate_result['stop_message'] ?? __( 'This service only supports uncontested divorces.', 'case-engine' ),
				'session_key'  => $session_key,
			) );
		}

		$next = $current + 1;
		self::save_session( $session_key, array(
			'answers'        => $merged,
			'status'         => 'in_progress',
			'current_screen' => $next,
		) );

		// When user completes Screen 10 (Review & Confirmation), create case in structured tables immediately.
		// Data is stored in cases, parties, intake_answers; case status = pending_payment. Payment step then marks it paid.
		if ( $current === 10 ) {
			Case_Engine_Case_Factory::create_from_session( $session_key, array( 'case_status' => 'pending_payment' ) );
		}

		wp_send_json_success( array(
			'can_proceed' => true,
			'next_screen' => $next,
			'session_key' => $session_key,
		) );
	}

	/**
	 * AJAX: restore session on page load (current screen + answers) so reload keeps progress.
	 */
	public static function ajax_restore() {
		check_ajax_referer( 'az_intake', 'nonce' );
		$session_key = isset( $_POST['session_key'] ) ? sanitize_text_field( wp_unslash( $_POST['session_key'] ) ) : '';
		if ( ! $session_key || ! preg_match( '/^[a-zA-Z0-9]{32}$/', $session_key ) ) {
			wp_send_json_success( array( 'restored' => false ) );
		}
		$session = self::get_session( $session_key );

		// Session not found.
		if ( ! $session ) {
			wp_send_json_success( array( 'restored' => false, 'clear_cookie' => true ) );
		}

		$s_status  = $session['status'] ?? '';
		$s_case_id = ! empty( $session['case_id'] ) ? (int) $session['case_id'] : 0;

		// Completed sessions always start fresh — clear cookie so next save generates a new key.
		if ( $s_status === 'completed' ) {
			wp_send_json_success( array( 'restored' => false, 'clear_cookie' => true ) );
		}

		// A stopped session that already has a case attached is stale — also start fresh.
		if ( $s_status === 'stopped' && $s_case_id > 0 ) {
			wp_send_json_success( array( 'restored' => false, 'clear_cookie' => true ) );
		}

		// A fresh stopped session (no case yet) can be resumed so the user can fix their answers.
		if ( $s_status === 'stopped' ) {
			wp_send_json_success( array( 'restored' => false ) );
		}
		$session_user_id = isset( $session['user_id'] ) ? (int) $session['user_id'] : 0;
		$current_user_id = get_current_user_id();

		// If the session is currently unowned (guest) and a user is now logged in, link them
		// — handles the case where the wp_login hook fired before cookies were available.
		if ( $session_user_id === 0 && $current_user_id > 0 ) {
			global $wpdb;
			$wpdb->update(
				$wpdb->prefix . 'az_intake_sessions',
				array( 'user_id' => $current_user_id, 'updated_at' => current_time( 'mysql' ) ),
				array( 'session_key' => $session_key ),
				array( '%d', '%s' ),
				array( '%s' )
			);
			// Also link az_cases if a case was created for this session.
			if ( ! empty( $session['case_id'] ) ) {
				$case_id     = (int) $session['case_id'];
				$cases_table = $wpdb->prefix . 'az_cases';
				$existing_uid = (int) $wpdb->get_var( $wpdb->prepare(
					"SELECT user_id FROM {$cases_table} WHERE id = %d LIMIT 1", $case_id
				) );
				if ( $existing_uid === 0 ) {
					$wpdb->update(
						$cases_table,
						array( 'user_id' => $current_user_id, 'updated_at' => current_time( 'mysql' ) ),
						array( 'id' => $case_id ),
						array( '%d', '%s' ),
						array( '%d' )
					);
				}
			}
			$session_user_id = $current_user_id;
		}

		// Privacy: only restore if session belongs to the current user (same user_id).
		if ( $session_user_id !== $current_user_id ) {
			wp_send_json_success( array( 'restored' => false, 'clear_cookie' => true ) );
		}
		$current_screen = isset( $session['current_screen'] ) ? (int) $session['current_screen'] : 1;
		$answers = array();
		if ( ! empty( $session['answers'] ) ) {
			$decoded = json_decode( $session['answers'], true );
			if ( is_array( $decoded ) ) {
				$answers = $decoded;
			}
		}
		wp_send_json_success( array(
			'restored'       => true,
			'current_screen' => max( 1, min( $current_screen, 12 ) ),
			'answers'        => $answers,
			'session_key'    => $session_key,
		) );
	}

	/**
	 * AJAX: mark intake session as completed when user clicks "Go to Dashboard" on screen 12.
	 * So next visit to intake starts a new session (restore returns false).
	 */
	public static function ajax_complete() {
		check_ajax_referer( 'az_intake', 'nonce' );
		$session_key = isset( $_POST['session_key'] ) ? sanitize_text_field( wp_unslash( $_POST['session_key'] ) ) : '';
		if ( ! $session_key || ! preg_match( '/^[a-zA-Z0-9]{32}$/', $session_key ) ) {
			wp_send_json_success( array( 'done' => true ) );
		}
		$session = self::get_session( $session_key );
		if ( $session && (int) $session['user_id'] === get_current_user_id() ) {
			self::save_session( $session_key, array(
				'answers'        => ! empty( $session['answers'] ) ? json_decode( $session['answers'], true ) : array(),
				'status'         => 'completed',
				'current_screen' => isset( $session['current_screen'] ) ? (int) $session['current_screen'] : 12,
			) );
		}
		wp_send_json_success( array( 'done' => true ) );
	}

	/**
	 * AJAX: evaluate gate for current screen (client can call before attempting next).
	 */
	public static function ajax_gate() {
		check_ajax_referer( 'az_intake', 'nonce' );
		$current = isset( $_POST['current_screen'] ) ? (int) $_POST['current_screen'] : 1;
		$answers_raw = isset( $_POST['answers'] ) && is_array( $_POST['answers'] ) ? wp_unslash( $_POST['answers'] ) : array();
		$answers = array();
		foreach ( $answers_raw as $k => $v ) {
			$answers[ sanitize_key( $k ) ] = is_scalar( $v ) ? sanitize_text_field( $v ) : array_map( 'sanitize_text_field', (array) $v );
		}
		$result = Case_Engine_Intake_Flow::evaluate_gate( $current, $answers );
		wp_send_json_success( $result );
	}

	/**
	 * AJAX: Initiate payment. For guests, require login first (storing session_key in a cookie so it
	 * survives the login redirect and can be linked to the user on wp_login).
	 */
	public static function ajax_payment() {
		check_ajax_referer( 'az_intake', 'nonce' );

		$session_key = isset( $_POST['session_key'] ) ? sanitize_text_field( wp_unslash( $_POST['session_key'] ) ) : '';
		if ( ! $session_key || ! preg_match( '/^[a-zA-Z0-9]{32}$/', $session_key ) ) {
			wp_send_json_error( array( 'message' => __( 'Invalid session.', 'case-engine' ) ) );
		}

		$session = self::get_session( $session_key );
		if ( ! $session ) {
			wp_send_json_error( array( 'message' => __( 'Session not found.', 'case-engine' ) ) );
		}

		$current_user_id = get_current_user_id();

		// --- Guest: send straight to WooCommerce checkout (no WordPress login required).
		// Order billing email + optional "create account" at checkout links the case after payment.
		if ( ! $current_user_id ) {
			if ( ! function_exists( 'wc_get_checkout_url' ) ) {
				// No WooCommerce: keep login gate for non-WC installs (Stripe direct flow expects a user).
				$cookie_args = array(
					'expires'  => time() + DAY_IN_SECONDS,
					'path'     => '/',
					'samesite' => 'Lax',
				);
				if ( PHP_VERSION_ID >= 70300 ) {
					setcookie( 'az_intake_pending_sk', $session_key, $cookie_args );
					setcookie( 'az_intake_session', $session_key, $cookie_args );
				} else {
					setcookie( 'az_intake_pending_sk', $session_key, time() + DAY_IN_SECONDS, '/; SameSite=Lax' );
					setcookie( 'az_intake_session', $session_key, time() + DAY_IN_SECONDS, '/; SameSite=Lax' );
				}
				$intake_page = wp_get_referer() ?: home_url( '/' );
				$intake_page = remove_query_arg( 'az_sk', $intake_page );
				$return_url  = add_query_arg( 'az_sk', rawurlencode( $session_key ), $intake_page );
				$login_url   = wp_login_url( $return_url );
				wp_send_json_success( array(
					'login_required' => true,
					'redirect'       => $login_url,
					'session_key'    => $session_key,
					'message'        => __( 'Please log in or create an account to complete your payment.', 'case-engine' ),
				) );
				return;
			}
			// Guest session must be unowned.
			if ( (int) $session['user_id'] !== 0 ) {
				wp_send_json_error( array( 'message' => __( 'Invalid session.', 'case-engine' ) ) );
			}
			// Fall through to shared checkout URL logic below (same as logged-in users).
		}

		// Ownership: logged-in users must own the session; guests handled above.
		$session_uid = (int) $session['user_id'];
		if ( $current_user_id > 0 ) {
			if ( $session_uid !== 0 && $session_uid !== $current_user_id ) {
				wp_send_json_error( array( 'message' => __( 'Invalid session.', 'case-engine' ) ) );
			}
		}

		// If session was guest-created, link it now that the user is logged in.
		if ( $current_user_id > 0 && $session_uid === 0 ) {
			global $wpdb;
			$wpdb->update(
				$wpdb->prefix . 'az_intake_sessions',
				array( 'user_id' => $current_user_id, 'updated_at' => current_time( 'mysql' ) ),
				array( 'session_key' => $session_key ),
				array( '%d', '%s' ),
				array( '%s' )
			);
		}

		// Ensure case exists (created at screen 10).
		$case_id = ! empty( $session['case_id'] ) ? (int) $session['case_id'] : 0;
		if ( ! $case_id ) {
			$case_id = Case_Engine_Case_Factory::create_from_session( $session_key, array( 'case_status' => 'pending_payment' ) );
			if ( ! $case_id ) {
				wp_send_json_error( array( 'message' => __( 'Could not create case. Please try again.', 'case-engine' ) ) );
			}
		}

		// Also ensure the case has the correct user_id.
		global $wpdb;
		$cases_table = $wpdb->prefix . 'az_cases';
		$case_uid    = (int) $wpdb->get_var( $wpdb->prepare(
			"SELECT user_id FROM {$cases_table} WHERE id = %d LIMIT 1",
			$case_id
		) );
		if ( $case_uid === 0 ) {
			$wpdb->update(
				$cases_table,
				array( 'user_id' => $current_user_id, 'updated_at' => current_time( 'mysql' ) ),
				array( 'id' => $case_id ),
				array( '%d', '%s' ),
				array( '%d' )
			);
		}

		// WooCommerce product ID for the divorce package (configure via filter).
		$product_id = (int) apply_filters( 'case_engine_wc_product_id', 123 );

		// Build WooCommerce checkout URL — auto-add item, pass session identifiers.
		$checkout_url = add_query_arg(
			array(
				'add-to-cart'    => $product_id,
				'az_session_key' => $session_key,
				'az_case_id'     => $case_id,
			),
			function_exists( 'wc_get_checkout_url' ) ? wc_get_checkout_url() : home_url( '/checkout/' )
		);

		// If WooCommerce guest checkout is disabled, send guests to My Account (login/register)
		// and return them to checkout after authentication.
		if ( 0 === (int) $current_user_id && 'yes' !== get_option( 'woocommerce_enable_guest_checkout' ) ) {
			$auth_url = function_exists( 'wc_get_page_permalink' ) ? wc_get_page_permalink( 'myaccount' ) : '';
			if ( ! $auth_url ) {
				$auth_url = wp_login_url( $checkout_url );
			} else {
				$auth_url = add_query_arg( 'redirect_to', rawurlencode( $checkout_url ), $auth_url );
			}
			$auth_url = add_query_arg(
				array(
					'az_session_key' => $session_key,
					'az_case_id'     => $case_id,
				),
				$auth_url
			);

			wp_send_json_success( array(
				'login_required' => true,
				'redirect'       => $auth_url,
				'session_key'    => $session_key,
				'case_id'        => $case_id,
				'message'        => __( 'Please log in or create an account to continue to checkout.', 'case-engine' ),
			) );
		}

		wp_send_json_success( array(
			'redirect'        => $checkout_url,
			'case_id'         => $case_id,
			'use_woocommerce' => true,
		) );
	}
}
