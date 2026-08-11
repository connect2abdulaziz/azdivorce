<?php
/**
 * Client Dashboard — shortcode and "own cases" listing (RBAC: case_engine_view_own_cases).
 *
 * @package Case_Engine
 */

defined( 'ABSPATH' ) || exit;

class Case_Engine_Client_Dashboard {
	const DASHBOARD_SLUG = 'client-dashboard';
	const DASH_ACCESS_COOKIE = 'az_dash_uid';

	/**
	 * Register shortcode [az_client_dashboard].
	 */
	public static function register() {
		add_shortcode( 'az_client_dashboard', array( __CLASS__, 'render' ) );
		// If payment return contains a valid order key, auto-authenticate the linked user.
		add_action( 'template_redirect', array( __CLASS__, 'maybe_auto_login_from_payment_return' ), 0 );
		// Recover case_id from cookie when user lands on dashboard after payment (order meta may not have been saved).
		add_action( 'template_redirect', array( __CLASS__, 'recover_case_from_payment_cookie' ), 1 );
		// Handle direct Stripe Checkout success redirect (?payment=success&session_id=cs_...&case_id=X).
		add_action( 'template_redirect', array( __CLASS__, 'handle_stripe_success_redirect' ), 2 );
		// Client corrections to intake/case party info.
		add_action( 'wp_ajax_az_client_save_intake', array( __CLASS__, 'ajax_save_intake' ) );
	}

	/**
	 * Auto-login user on valid Woo payment return URL.
	 * This avoids showing "Log in / Sign up" immediately after successful payment.
	 *
	 * @return void
	 */
	public static function maybe_auto_login_from_payment_return() {
		if ( is_user_logged_in() || ! function_exists( 'wc_get_order' ) ) {
			return;
		}
		if ( ! is_page( 'client-dashboard' ) ) {
			return;
		}
		if ( empty( $_GET['payment'] ) || 'success' !== sanitize_text_field( wp_unslash( $_GET['payment'] ) ) ) {
			return;
		}

		$order_id = 0;
		if ( ! empty( $_GET['order_id'] ) ) {
			$order_id = absint( $_GET['order_id'] );
		} elseif ( ! empty( $_GET['order'] ) ) {
			$order_id = absint( $_GET['order'] );
		}
		if ( ! $order_id ) {
			return;
		}

		$order = wc_get_order( $order_id );
		if ( ! $order ) {
			return;
		}

		// Verify order key when provided in URL.
		if ( ! empty( $_GET['key'] ) ) {
			$key = sanitize_text_field( wp_unslash( $_GET['key'] ) );
			if ( $key !== $order->get_order_key() ) {
				return;
			}
		}

		// Ensure paid/link logic runs first (can auto-create user by billing email).
		if ( class_exists( 'Case_Engine_WooCommerce_Integration' ) ) {
			Case_Engine_WooCommerce_Integration::handle_order_paid( $order_id );
		}
		// Reload order to pick up any customer_id/meta updates done by handler.
		$order = wc_get_order( $order_id );
		if ( ! $order ) {
			return;
		}

		$user_id = (int) $order->get_user_id();
		if ( $user_id <= 0 ) {
			$billing_email = sanitize_email( $order->get_billing_email() );
			if ( $billing_email ) {
				$user_by_email = get_user_by( 'email', $billing_email );
				if ( $user_by_email ) {
					$user_id = (int) $user_by_email->ID;
				}
			}
		}
		if ( $user_id <= 0 ) {
			return;
		}

		$user = get_user_by( 'id', $user_id );
		if ( ! $user ) {
			return;
		}

		wp_set_current_user( $user_id );
		wp_set_auth_cookie( $user_id, true );
		do_action( 'wp_login', $user->user_login, $user );
		nocache_headers();
		$redirect_args = array( 'payment' => 'success' );
		$case_id       = (int) $order->get_meta( '_az_case_id' );
		if ( $case_id > 0 ) {
			$redirect_args['case_id'] = $case_id;
		}
		wp_safe_redirect( add_query_arg( $redirect_args, home_url( '/client-dashboard/' ) ) );
		exit;
	}

	/**
	 * When user lands on dashboard after payment, recover case_id from cookie, URL params, or order meta and mark case paid.
	 * Supports: order_id + key (WooCommerce style), or order + az_case_id (our return URL style).
	 */
	public static function recover_case_from_payment_cookie() {
		if ( ! is_user_logged_in() || ! function_exists( 'wc_get_order' ) ) {
			return;
		}
		$dashboard_slug = 'client-dashboard';
		if ( ! is_page( $dashboard_slug ) ) {
			return;
		}

		$order_id = isset( $_GET['order_id'] ) ? absint( $_GET['order_id'] ) : 0;
		$order_alt = isset( $_GET['order'] ) ? absint( $_GET['order'] ) : 0;
		$key      = isset( $_GET['key'] ) ? sanitize_text_field( wp_unslash( $_GET['key'] ) ) : '';
		$case_id_from_url  = isset( $_GET['az_case_id'] ) ? absint( $_GET['az_case_id'] ) : 0;
		$session_key_from_url = isset( $_GET['az_session_key'] ) ? sanitize_text_field( wp_unslash( $_GET['az_session_key'] ) ) : '';

		// Resolve order: order_id (with key), order param, or paid-order fallback by case/session cookies.
		if ( $order_id && $key ) {
			$order = wc_get_order( $order_id );
			if ( ! $order || $order->get_order_key() !== $key ) {
				return;
			}
		} elseif ( $order_alt ) {
			$order = wc_get_order( $order_alt );
			if ( ! $order || (int) $order->get_user_id() !== get_current_user_id() ) {
				return;
			}
			$order_id = $order_alt;
		} else {
			$cookie_case_id     = isset( $_COOKIE['az_pending_case_id'] ) ? absint( $_COOKIE['az_pending_case_id'] ) : 0;
			$cookie_session_key = isset( $_COOKIE['az_pending_session_key'] ) ? sanitize_text_field( wp_unslash( $_COOKIE['az_pending_session_key'] ) ) : '';
			if ( ! $cookie_case_id && ! $cookie_session_key ) {
				return;
			}
			$order = self::find_paid_order_for_user_case( get_current_user_id(), $cookie_case_id, $cookie_session_key );
			if ( ! $order ) {
				return;
			}
			$order_id = (int) $order->get_id();
		}

		// Already linked and marked
		if ( (int) $order->get_meta( '_az_case_id' ) > 0 && $order->get_meta( '_az_case_marked_paid' ) ) {
			self::mark_intake_session_completed_for_case( (int) $order->get_meta( '_az_case_id' ) );
			self::clear_intake_progress_cookies();
			wp_safe_redirect( add_query_arg( 'payment', 'success', home_url( '/client-dashboard/' ) ) );
			exit;
		}

		// Order must be paid before we link or mark anything
		if ( ! $order->is_paid() ) {
			return;
		}

		$user_id = get_current_user_id();

		// Resolve case_id: order meta → URL params → cookie → fallback: most recent pending_payment case for this user
		$case_id = (int) $order->get_meta( '_az_case_id' );
		if ( ! $case_id ) {
			$case_id = $case_id_from_url;
		}
		if ( ! $case_id ) {
			$case_id = isset( $_COOKIE['az_pending_case_id'] ) ? absint( $_COOKIE['az_pending_case_id'] ) : 0;
		}
		if ( ! $case_id ) {
			$case_id = self::get_most_recent_pending_case_for_user( $user_id );
		}
		if ( ! $case_id ) {
			return;
		}

		$case = self::get_case_for_user( $case_id, $user_id );
		if ( ! $case ) {
			return;
		}

		// Save to order meta so future hooks see it, then mark case paid
		$order->update_meta_data( '_az_case_id', $case_id );
		$session_key = $session_key_from_url ? $session_key_from_url : ( isset( $_COOKIE['az_pending_session_key'] ) ? sanitize_text_field( wp_unslash( $_COOKIE['az_pending_session_key'] ) ) : '' );
		if ( $session_key ) {
			$order->update_meta_data( '_az_session_key', $session_key );
		}
		$order->save();

		if ( class_exists( 'Case_Engine_WooCommerce_Integration' ) && method_exists( 'Case_Engine_WooCommerce_Integration', 'handle_order_paid' ) ) {
			Case_Engine_WooCommerce_Integration::handle_order_paid( $order_id );
		} else {
			global $wpdb;
			$cases_table = $wpdb->prefix . 'az_cases';
			$case_row    = $wpdb->get_row( $wpdb->prepare( "SELECT id, intake_session_id FROM {$cases_table} WHERE id = %d", $case_id ), ARRAY_A );
			if ( $case_row ) {
				Case_Engine_Case_Factory::mark_case_paid( $case_id, (int) $case_row['intake_session_id'] );
			}
		}

		self::mark_intake_session_completed_for_case( $case_id );
		self::clear_intake_progress_cookies();

		wp_safe_redirect( add_query_arg( 'payment', 'success', home_url( '/client-dashboard/' ) ) );
		exit;
	}

	/**
	 * Handle direct Stripe Checkout success URL:
	 *   /client-dashboard/?payment=success&session_id=cs_xxx&case_id=X
	 *
	 * Verifies the Stripe session is paid and marks the case paid via the factory.
	 * Only runs when WooCommerce is NOT handling the payment (i.e. no order_id in URL).
	 */
	public static function handle_stripe_success_redirect() {
		if ( ! is_user_logged_in() ) {
			return;
		}
		if ( ! is_page( 'client-dashboard' ) ) {
			return;
		}
		// Only handle direct Stripe; WooCommerce flow has order_id / order params.
		if ( isset( $_GET['order_id'] ) || isset( $_GET['order'] ) ) {
			return;
		}

		$payment     = isset( $_GET['payment'] ) ? sanitize_text_field( wp_unslash( $_GET['payment'] ) ) : '';
		$session_id  = isset( $_GET['session_id'] ) ? sanitize_text_field( wp_unslash( $_GET['session_id'] ) ) : '';
		$case_id_raw = isset( $_GET['case_id'] ) ? absint( $_GET['case_id'] ) : 0;

		if ( $payment !== 'success' || ! $session_id || ! $case_id_raw ) {
			return;
		}

		// Must start with 'cs_' to be a valid Stripe Checkout Session ID.
		if ( strpos( $session_id, 'cs_' ) !== 0 ) {
			return;
		}

		$user_id = get_current_user_id();
		$case    = self::get_case_for_user( $case_id_raw, $user_id );
		if ( ! $case ) {
			return;
		}

		// Already paid — nothing to do; clean URL and continue.
		if ( in_array( $case['status'], array( 'paid', 'in_progress', 'completed' ), true ) ) {
			return;
		}

		// Verify with Stripe and mark paid.
		$autoload = CASE_ENGINE_PLUGIN_DIR . 'vendor/autoload.php';
		if ( ! file_exists( $autoload ) ) {
			return;
		}
		$secret_key = defined( 'STRIPE_SECRET_KEY' ) ? STRIPE_SECRET_KEY : '';
		if ( ! $secret_key ) {
			return;
		}

		try {
			require_once $autoload;
			\Stripe\Stripe::setApiKey( $secret_key );
			$stripe_session = \Stripe\Checkout\Session::retrieve( $session_id );

			if ( ! $stripe_session || $stripe_session->payment_status !== 'paid' ) {
				return;
			}

			$amount   = isset( $stripe_session->amount_total ) ? (float) ( $stripe_session->amount_total / 100 ) : 0.00;
			$currency = isset( $stripe_session->currency ) ? strtoupper( $stripe_session->currency ) : 'USD';

			global $wpdb;
			$case_row = $wpdb->get_row( $wpdb->prepare(
				"SELECT id, intake_session_id FROM {$wpdb->prefix}az_cases WHERE id = %d LIMIT 1",
				$case_id_raw
			), ARRAY_A );

			if ( $case_row ) {
				Case_Engine_Case_Factory::mark_case_paid_from_stripe(
					$case_id_raw,
					(int) $case_row['intake_session_id'],
					$amount,
					$currency,
					$session_id
				);
				self::mark_intake_session_completed_for_case( $case_id_raw );
				self::clear_intake_progress_cookies();
			}
		} catch ( \Exception $e ) {
			// Log but don't die — let the dashboard render with the current status.
			if ( defined( 'WP_DEBUG_LOG' ) && WP_DEBUG_LOG ) {
				error_log( 'Case Engine: Stripe session verification failed — ' . $e->getMessage() );
			}
		}
	}

	/**
	 * Force intake session completion for a paid case.
	 *
	 * @param int $case_id Case ID.
	 * @return void
	 */
	private static function mark_intake_session_completed_for_case( $case_id ) {
		$case_id = (int) $case_id;
		if ( $case_id <= 0 ) {
			return;
		}
		global $wpdb;
		$sessions_table = $wpdb->prefix . 'az_intake_sessions';
		$wpdb->query(
			$wpdb->prepare(
				"UPDATE {$sessions_table}
				 SET status = 'completed', current_screen = 11, updated_at = %s
				 WHERE case_id = %d",
				current_time( 'mysql' ),
				$case_id
			)
		);
	}

	/**
	 * Clear intake/payment continuity cookies after successful payment.
	 *
	 * @return void
	 */
	private static function clear_intake_progress_cookies() {
		$cookies = array(
			'az_pending_case_id',
			'az_pending_session_key',
			'az_intake_session',
			'az_intake_pending_sk',
		);
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
	 * Find most recent paid Woo order for this user and case/session identifiers.
	 *
	 * @param int    $user_id     WordPress user id.
	 * @param int    $case_id     Case id from cookie.
	 * @param string $session_key Session key from cookie.
	 * @return WC_Order|false
	 */
	private static function find_paid_order_for_user_case( $user_id, $case_id, $session_key ) {
		if ( ! function_exists( 'wc_get_orders' ) ) {
			return false;
		}
		$user_id     = (int) $user_id;
		$case_id     = (int) $case_id;
		$session_key = (string) $session_key;
		if ( $user_id <= 0 ) {
			return false;
		}

		$base = array(
			'limit'      => 1,
			'orderby'    => 'date',
			'order'      => 'DESC',
			'status'     => array( 'processing', 'completed' ),
			'customer_id'=> $user_id,
		);

		if ( $case_id > 0 ) {
			$orders = wc_get_orders( array_merge( $base, array(
				'meta_key'   => '_az_case_id',
				'meta_value' => $case_id,
			) ) );
			if ( ! empty( $orders ) ) {
				return $orders[0];
			}
		}

		if ( $session_key ) {
			$orders = wc_get_orders( array_merge( $base, array(
				'meta_key'   => '_az_session_key',
				'meta_value' => $session_key,
			) ) );
			if ( ! empty( $orders ) ) {
				return $orders[0];
			}
		}

		$user = get_user_by( 'id', $user_id );
		if ( ! $user || empty( $user->user_email ) ) {
			return false;
		}
		$base['customer'] = sanitize_email( $user->user_email );
		unset( $base['customer_id'] );

		if ( $case_id > 0 ) {
			$orders = wc_get_orders( array_merge( $base, array(
				'meta_key'   => '_az_case_id',
				'meta_value' => $case_id,
			) ) );
			if ( ! empty( $orders ) ) {
				return $orders[0];
			}
		}

		if ( $session_key ) {
			$orders = wc_get_orders( array_merge( $base, array(
				'meta_key'   => '_az_session_key',
				'meta_value' => $session_key,
			) ) );
			if ( ! empty( $orders ) ) {
				return $orders[0];
			}
		}

		return false;
	}

	/**
	 * Shortcode callback: enforce VIEW_OWN_CASES, then show list, case detail, or questionnaire.
	 *
	 * @return string
	 */
	public static function render() {
		$logged_in_uid   = get_current_user_id();
		$context_user_id = self::get_dashboard_context_user_id();
		if ( ! $context_user_id ) {
			return self::render_login_required();
		}

		// Cookie-only access (not WP-logged-in): never expose the full case list for another profile.
		// Only allow viewing the specific paid case from the return URL / pending cookie.
		$cookie_only = ( $logged_in_uid <= 0 );
		if ( $cookie_only ) {
			wp_set_current_user( $context_user_id );
			$view_case = isset( $_GET['view_case'] ) ? (int) $_GET['view_case'] : 0;
			if ( ! $view_case ) {
				$view_case = isset( $_GET['case_id'] ) ? (int) $_GET['case_id'] : 0;
			}
			if ( ! $view_case ) {
				$view_case = isset( $_COOKIE['az_pending_case_id'] ) ? absint( $_COOKIE['az_pending_case_id'] ) : 0;
			}
			if ( $view_case > 0 ) {
				$case = self::get_case_for_user( $view_case, $context_user_id );
				if ( $case ) {
					$parties = self::get_case_parties( $view_case );
					return self::render_case_detail( $case, $parties );
				}
			}
			return self::render_login_required();
		}

		// Every authenticated user may view their own cases.
		// Subscriber / Customer roles get the cap added on activation, but if somehow
		// it is missing (e.g. existing users before the cap was added), grant it now.
		if ( is_user_logged_in() && ! current_user_can( Case_Engine_RBAC::VIEW_OWN_CASES ) ) {
			$user = wp_get_current_user();
			// Only deny access to explicitly non-client roles (editors, authors, etc. without
			// the cap who are not site admins). Regular subscribers / customers are always allowed.
			$non_client_roles = array( 'editor', 'author', 'contributor' );
			$user_roles       = (array) $user->roles;
			$is_non_client    = ! empty( array_intersect( $user_roles, $non_client_roles ) );
			if ( $is_non_client ) {
				return self::render_no_permission();
			}
			// Grant the missing cap to this user persistently so future requests are fast.
			$user->add_cap( Case_Engine_RBAC::VIEW_OWN_CASES );
		}

		// Questionnaire route: ?case_id=X (no view_case param).
		$q_case_id = isset( $_GET['case_id'] ) && ! isset( $_GET['view_case'] ) ? (int) $_GET['case_id'] : 0;
		if ( $q_case_id > 0 && class_exists( 'Case_Engine_Questionnaire_Controller' ) ) {
			return do_shortcode( '[az_divorce_questionnaire case_id="' . (int) $q_case_id . '"]' );
		}

		$view_case = isset( $_GET['view_case'] ) ? (int) $_GET['view_case'] : 0;
		if ( $view_case > 0 ) {
			$user_id = $context_user_id;
			$case    = self::get_case_for_user( $view_case, $user_id );
			if ( $case ) {
				$parties = self::get_case_parties( $view_case );
				if ( ! empty( $_GET['edit_intake'] ) ) {
					return self::render_edit_intake( $case, $parties );
				}
				return self::render_case_detail( $case, $parties );
			}
			// Foreign case_id in URL — do not fall through to another profile's data.
			return '<div class="az-client-dashboard"><p class="az-client-dashboard__error">' .
				esc_html__( 'Case not found or access denied.', 'case-engine' ) .
				'</p></div>';
		}

		return self::render_own_cases( $context_user_id );
	}

	/**
	 * Resolve dashboard user context:
	 * 1) logged-in user
	 * 2) short-lived signed cookie
	 * 3) valid paid order return URL (order_id + key + payment=success)
	 *
	 * @return int
	 */
	private static function get_dashboard_context_user_id() {
		$current_uid = get_current_user_id();
		if ( $current_uid > 0 ) {
			return (int) $current_uid;
		}

		$cookie_uid = self::read_dashboard_access_cookie();
		if ( $cookie_uid > 0 ) {
			return $cookie_uid;
		}

		if ( empty( $_GET['payment'] ) || 'success' !== sanitize_text_field( wp_unslash( $_GET['payment'] ) ) ) {
			return 0;
		}
		$order_id = isset( $_GET['order_id'] ) ? absint( $_GET['order_id'] ) : 0;
		if ( ! $order_id ) {
			$order_id = isset( $_GET['order'] ) ? absint( $_GET['order'] ) : 0;
		}
		if ( ! $order_id || ! function_exists( 'wc_get_order' ) ) {
			return 0;
		}
		$order = wc_get_order( $order_id );
		if ( ! $order ) {
			return 0;
		}

		if ( ! empty( $_GET['key'] ) ) {
			$key = sanitize_text_field( wp_unslash( $_GET['key'] ) );
			if ( $key !== $order->get_order_key() ) {
				return 0;
			}
		}

		$user_id = (int) $order->get_user_id();
		if ( $user_id <= 0 ) {
			$email = sanitize_email( $order->get_billing_email() );
			if ( $email ) {
				$u = get_user_by( 'email', $email );
				if ( $u ) {
					$user_id = (int) $u->ID;
				}
			}
		}
		if ( $user_id <= 0 ) {
			return 0;
		}

		self::set_dashboard_access_cookie( $user_id );
		return $user_id;
	}

	/**
	 * Set short-lived signed dashboard access cookie.
	 *
	 * @param int $user_id User id.
	 * @return void
	 */
	private static function set_dashboard_access_cookie( $user_id ) {
		$user_id = (int) $user_id;
		if ( $user_id <= 0 || headers_sent() ) {
			return;
		}
		$exp     = time() + ( 20 * MINUTE_IN_SECONDS );
		$payload = $user_id . '|' . $exp;
		$sig     = hash_hmac( 'sha256', $payload, wp_salt( 'auth' ) );
		$value   = $payload . '|' . $sig;
		$secure  = is_ssl();
		if ( PHP_VERSION_ID >= 70300 ) {
			@setcookie( self::DASH_ACCESS_COOKIE, $value, array(
				'expires'  => $exp,
				'path'     => '/',
				'secure'   => $secure,
				'httponly' => true,
				'samesite' => 'Lax',
			) );
		} else {
			@setcookie( self::DASH_ACCESS_COOKIE, $value, $exp, '/; samesite=Lax', '', $secure, true );
		}
	}

	/**
	 * Read and validate dashboard access cookie.
	 *
	 * @return int
	 */
	private static function read_dashboard_access_cookie() {
		if ( empty( $_COOKIE[ self::DASH_ACCESS_COOKIE ] ) ) {
			return 0;
		}
		$raw = (string) wp_unslash( $_COOKIE[ self::DASH_ACCESS_COOKIE ] );
		$parts = explode( '|', $raw );
		if ( count( $parts ) !== 3 ) {
			return 0;
		}
		list( $uid_str, $exp_str, $sig ) = $parts;
		$uid = (int) $uid_str;
		$exp = (int) $exp_str;
		if ( $uid <= 0 || $exp < time() ) {
			return 0;
		}
		$payload = $uid . '|' . $exp;
		$expected = hash_hmac( 'sha256', $payload, wp_salt( 'auth' ) );
		if ( ! hash_equals( $expected, (string) $sig ) ) {
			return 0;
		}
		return $uid;
	}

	/**
	 * Optional notice: contested cases / legal advice — full HTML filterable for site-specific phone.
	 *
	 * @return string
	 */
	private static function render_contested_case_notice(): string {
		$phone = apply_filters( 'case_engine_office_phone', '' );
		if ( $phone ) {
			$inner = sprintf(
				/* translators: %s: office phone number */
				__( 'If your divorce may be contested or you need legal advice, call us at %s for guidance.', 'case-engine' ),
				'<strong>' . esc_html( $phone ) . '</strong>'
			);
			$html = '<div class="az-client-dashboard__notice az-client-dashboard__notice--info"><p>' . wp_kses_post( $inner ) . '</p></div>';
		} else {
			$inner = esc_html__( 'If your divorce may be contested or you need legal advice, please contact our office for guidance.', 'case-engine' );
			$html = '<div class="az-client-dashboard__notice az-client-dashboard__notice--info"><p>' . $inner . '</p></div>';
		}
		/**
		 * Filter the full HTML for the contested-case notice on the dashboard (list view).
		 *
		 * @param string $html Default notice markup.
		 */
		return apply_filters( 'case_engine_dashboard_contested_notice_html', $html );
	}

	/**
	 * Message when user is not logged in.
	 *
	 * @return string
	 */
	private static function render_login_required() {
		$redirect_to = get_permalink();
		$account_url = function_exists( 'wc_get_page_permalink' ) ? wc_get_page_permalink( 'myaccount' ) : '';
		$login_url   = $account_url ? add_query_arg( 'redirect_to', rawurlencode( $redirect_to ), $account_url ) : wp_login_url( $redirect_to );
		$output   = '<div class="az-client-dashboard az-client-dashboard--guest">';
		$output  .= '<div class="az-client-dashboard__card">';
		$output  .= '<p>' . esc_html__( 'Please log in to view your cases and documents.', 'case-engine' ) . '</p>';
		$output  .= '<p><a href="' . esc_url( $login_url ) . '" class="az-intake-btn az-intake-btn-primary">' . esc_html__( 'Log in / Sign up', 'case-engine' ) . '</a></p>';
		$output  .= '</div></div>';
		return $output;
	}

	/**
	 * Message when user lacks VIEW_OWN_CASES.
	 *
	 * @return string
	 */
	private static function render_no_permission() {
		return '<div class="az-client-dashboard az-client-dashboard--forbidden"><div class="az-client-dashboard__card"><p>' .
			esc_html__( 'You do not have permission to view this page.', 'case-engine' ) . '</p></div></div>';
	}

	/**
	 * List cases owned by the current user with "View details" link.
	 *
	 * @return string
	 */
	/**
	 * Whether the case has paid status and documents can be accessed (payment-gated).
	 *
	 * @param array $case Case row with 'status' key.
	 * @return bool
	 */
	public static function case_can_access_documents( $case ) {
		$status = isset( $case['status'] ) ? $case['status'] : '';
		return in_array( $status, array( 'paid', 'in_progress', 'completed' ), true );
	}

	private static function render_own_cases( $user_id = 0 ) {
		$user_id = $user_id ? (int) $user_id : (int) get_current_user_id();
		$cases   = self::get_own_cases( $user_id );
		$base_url = get_permalink();

		$output = '<div class="az-client-dashboard">';
		$output .= '<div class="az-client-dashboard__card">';
		$output .= self::render_contested_case_notice();
		if ( isset( $_GET['payment'] ) && $_GET['payment'] === 'success' ) {
			$output .= '<p class="az-client-dashboard__notice az-client-dashboard__notice--success">' . esc_html__( 'Payment received. Your case is being prepared. Documents will be available here when ready.', 'case-engine' ) . '</p>';
		}
		$output .= '<span class="az-client-dashboard__subtitle">' . esc_html__( 'Dashboard', 'case-engine' ) . '</span>';
		$output .= '<h2>' . esc_html__( 'Your cases', 'case-engine' ) . '</h2>';

		if ( empty( $cases ) ) {
			$output .= '<p>' . esc_html__( 'You have no cases yet. Complete the intake to create a case.', 'case-engine' ) . '</p>';
			$output .= '</div></div>';
			return $output;
		}

		$output .= '<table class="az-client-dashboard__table">';
		$output .= '<thead><tr>';
		$output .= '<th>' . esc_html__( 'Case', 'case-engine' ) . '</th>';
		$output .= '<th>' . esc_html__( 'Status', 'case-engine' ) . '</th>';
		$output .= '<th>' . esc_html__( 'County', 'case-engine' ) . '</th>';
		$output .= '<th>' . esc_html__( 'Created', 'case-engine' ) . '</th>';
		$output .= '<th>' . esc_html__( 'Actions', 'case-engine' ) . '</th>';
		$output .= '</tr></thead><tbody>';

		foreach ( $cases as $row ) {
			$view_url = add_query_arg( 'view_case', (int) $row['id'], $base_url );
			$output .= '<tr>';
			$output .= '<td><span class="az-client-dashboard__case-num">' . (int) $row['id'] . '</span></td>';
			$output .= '<td>' . esc_html( $row['status'] ) . '</td>';
			$output .= '<td>' . esc_html( $row['county'] ) . '</td>';
			$output .= '<td>' . esc_html( $row['created_at'] ) . '</td>';
			$output .= '<td><a href="' . esc_url( $view_url ) . '" class="az-intake-btn az-intake-btn-link">' . esc_html__( 'View details', 'case-engine' ) . '</a></td>';
			$output .= '</tr>';
		}

		$output .= '</tbody></table>';
		$output .= '<p class="az-client-dashboard__footer-note">' . esc_html__( 'Document downloads and payment history will appear here in a later release.', 'case-engine' ) . '</p>';
		$output .= '</div></div>';
		return $output;
	}

	/**
	 * Render case detail: case info + parties + questionnaire CTA (via filter).
	 *
	 * @param array $case   Case row (id, status, county, filing_date, role, created_at, has_children).
	 * @param array $parties Party rows from get_case_parties().
	 * @return string
	 */
	private static function render_case_detail( $case, $parties ) {
		$back_url = remove_query_arg( array( 'view_case', 'case_id', 'edit_intake', 'updated' ), get_permalink() );
		$case_id  = (int) $case['id'];
		$edit_url = add_query_arg( array( 'view_case' => $case_id, 'edit_intake' => 1 ), get_permalink() );

		$output = '<div class="az-client-dashboard">';
		$output .= '<p><a href="' . esc_url( $back_url ) . '" class="az-intake-btn az-intake-btn-secondary">← ' . esc_html__( 'Back to your cases', 'case-engine' ) . '</a></p>';
		$output .= self::render_contested_case_notice();

		if ( ! empty( $_GET['updated'] ) ) {
			$output .= '<div class="az-client-dashboard__notice az-client-dashboard__notice--success">' .
				esc_html__( 'Your intake information was updated successfully. If you already generated documents, regenerate them so the PDFs use the corrected details.', 'case-engine' ) .
				'</div>';
		}

		// Case info card
		$output .= '<div class="az-client-dashboard__card">';
		$output .= '<div class="az-client-dashboard__card-header">';
		$output .= '<div>';
		$output .= '<span class="az-client-dashboard__subtitle">' . esc_html__( 'Case', 'case-engine' ) . ' #' . $case_id . '</span>';
		$output .= '<h2 class="az-client-dashboard__title">' . esc_html__( 'Case details', 'case-engine' ) . '</h2>';
		$output .= '</div>';
		$output .= '<a href="' . esc_url( $edit_url ) . '" class="az-intake-btn az-intake-btn-secondary">' . esc_html__( 'Correct intake info', 'case-engine' ) . '</a>';
		$output .= '</div>';
		$output .= '<div class="az-client-dashboard__info-grid">';
		$output .= '<div class="az-client-dashboard__info-item"><span class="az-client-dashboard__info-label">' . esc_html__( 'Status', 'case-engine' ) . '</span><span class="az-client-dashboard__info-value">' . esc_html( $case['status'] ) . '</span></div>';
		$output .= '<div class="az-client-dashboard__info-item"><span class="az-client-dashboard__info-label">' . esc_html__( 'County', 'case-engine' ) . '</span><span class="az-client-dashboard__info-value">' . esc_html( $case['county'] ) . '</span></div>';
		$output .= '<div class="az-client-dashboard__info-item"><span class="az-client-dashboard__info-label">' . esc_html__( 'Your role', 'case-engine' ) . '</span><span class="az-client-dashboard__info-value">' . esc_html( $case['role'] ) . '</span></div>';
		$output .= '<div class="az-client-dashboard__info-item"><span class="az-client-dashboard__info-label">' . esc_html__( 'Children', 'case-engine' ) . '</span><span class="az-client-dashboard__info-value">' . esc_html( $case['has_children'] ) . '</span></div>';
		if ( ! empty( $case['filing_date'] ) ) {
			$output .= '<div class="az-client-dashboard__info-item"><span class="az-client-dashboard__info-label">' . esc_html__( 'Filing date', 'case-engine' ) . '</span><span class="az-client-dashboard__info-value">' . esc_html( $case['filing_date'] ) . '</span></div>';
		}
		$output .= '<div class="az-client-dashboard__info-item"><span class="az-client-dashboard__info-label">' . esc_html__( 'Created', 'case-engine' ) . '</span><span class="az-client-dashboard__info-value">' . esc_html( $case['created_at'] ) . '</span></div>';
		$output .= '</div></div>';

		// Parties card
		$output .= '<div class="az-client-dashboard__card">';
		$output .= '<div class="az-client-dashboard__card-header">';
		$output .= '<h2 class="az-client-dashboard__title">' . esc_html__( 'Parties', 'case-engine' ) . '</h2>';
		$output .= '<a href="' . esc_url( $edit_url ) . '" class="az-intake-btn az-intake-btn-link">' . esc_html__( 'Edit', 'case-engine' ) . '</a>';
		$output .= '</div>';

		if ( empty( $parties ) ) {
			$output .= '<p>' . esc_html__( 'No party information on file.', 'case-engine' ) . '</p>';
			$output .= '<p><a href="' . esc_url( $edit_url ) . '" class="az-intake-btn az-intake-btn-primary">' . esc_html__( 'Add party information', 'case-engine' ) . '</a></p>';
		} else {
			$type_labels = array(
				'petitioner' => __( 'Petitioner', 'case-engine' ),
				'respondent' => __( 'Respondent', 'case-engine' ),
				'child'      => __( 'Child', 'case-engine' ),
			);
			foreach ( $parties as $p ) {
				$type = isset( $p['party_type'] ) ? $p['party_type'] : '';
				$type_label = isset( $type_labels[ $type ] ) ? $type_labels[ $type ] : ucfirst( $type );
				$output .= '<div class="az-client-dashboard__party-block">';
				$output .= '<div class="az-client-dashboard__party-type">' . esc_html( $type_label ) . '</div>';
				$output .= '<div class="az-client-dashboard__party-name">' . esc_html( $p['full_name'] ) . '</div>';
				if ( ! empty( $p['address'] ) ) {
					$output .= '<div class="az-client-dashboard__party-detail">' . esc_html__( 'Address', 'case-engine' ) . ': ' . esc_html( $p['address'] ) . '</div>';
				}
				if ( ! empty( $p['phone'] ) ) {
					$output .= '<div class="az-client-dashboard__party-detail">' . esc_html__( 'Phone', 'case-engine' ) . ': ' . esc_html( $p['phone'] ) . '</div>';
				}
				if ( ! empty( $p['email'] ) ) {
					$output .= '<div class="az-client-dashboard__party-detail">' . esc_html__( 'Email', 'case-engine' ) . ': ' . esc_html( $p['email'] ) . '</div>';
				}
				if ( ! empty( $p['dob'] ) ) {
					$output .= '<div class="az-client-dashboard__party-detail">' . esc_html__( 'Date of birth', 'case-engine' ) . ': ' . esc_html( $p['dob'] ) . '</div>';
				}
				if ( ! empty( $p['relationship'] ) ) {
					$output .= '<div class="az-client-dashboard__party-detail">' . esc_html__( 'Relationship', 'case-engine' ) . ': ' . esc_html( $p['relationship'] ) . '</div>';
				}
				$output .= '</div>';
			}
		}
		$output .= '</div>';

		// --- Payment status notice (unpaid cases: skip questionnaire + documents entirely) ---
		if ( ! self::case_can_access_documents( $case ) ) {
			$output .= '<div class="az-client-dashboard__card az-client-dashboard__card--payment-pending">';
			$output .= '<h2 class="az-client-dashboard__title">' . esc_html__( 'Payment Required', 'case-engine' ) . '</h2>';
			$output .= '<p>' . esc_html__( 'Your documents and questionnaire will be available after payment is confirmed.', 'case-engine' ) . '</p>';
			$output .= '</div>';
			$output .= '</div>';
			return $output;
		}

		// Resolve questionnaire status once and pass it to all filter callbacks via context,
		// so neither inject_questionnaire_cta() nor inject_documents_card() add a second card.
		$q_status = self::get_questionnaire_status( $case_id );

		// Allow modules (questionnaire CTA, document generator) to append additional cards.
		$output = apply_filters( 'az_client_dashboard_case_detail_after', $output, array(
			'case'                 => $case,
			'case_id'              => $case_id,
			'questionnaire_status' => $q_status,
		) );

		$output .= '</div>';
		return $output;
	}

	/**
	 * Client form to correct intake case + party information.
	 *
	 * @param array $case    Case row.
	 * @param array $parties Party rows.
	 * @return string
	 */
	private static function render_edit_intake( $case, $parties ) {
		$case_id   = (int) $case['id'];
		$cancel    = add_query_arg( 'view_case', $case_id, get_permalink() );
		$by_type   = array(
			'petitioner' => array( 'full_name' => '', 'address' => '', 'phone' => '', 'email' => '', 'dob' => '' ),
			'respondent' => array( 'full_name' => '', 'address' => '', 'phone' => '', 'email' => '', 'dob' => '' ),
		);
		$children = array();
		foreach ( $parties as $p ) {
			$type = $p['party_type'] ?? '';
			if ( 'child' === $type ) {
				$children[] = $p;
			} elseif ( isset( $by_type[ $type ] ) ) {
				$by_type[ $type ] = array_merge( $by_type[ $type ], $p );
			}
		}
		if ( empty( $children ) ) {
			$children[] = array( 'full_name' => '', 'dob' => '', 'relationship' => '' );
		}

		$pet = $by_type['petitioner'];
		$res = $by_type['respondent'];

		ob_start();
		?>
		<div class="az-client-dashboard" id="az-edit-intake"
			 data-case-id="<?php echo (int) $case_id; ?>"
			 data-ajax-url="<?php echo esc_url( admin_url( 'admin-ajax.php' ) ); ?>"
			 data-nonce="<?php echo esc_attr( wp_create_nonce( 'az_client_save_intake' ) ); ?>">
			<p><a href="<?php echo esc_url( $cancel ); ?>" class="az-intake-btn az-intake-btn-secondary">← <?php esc_html_e( 'Cancel', 'case-engine' ); ?></a></p>

			<div class="az-client-dashboard__card">
				<span class="az-client-dashboard__subtitle"><?php esc_html_e( 'Case', 'case-engine' ); ?> #<?php echo (int) $case_id; ?></span>
				<h2 class="az-client-dashboard__title"><?php esc_html_e( 'Correct intake information', 'case-engine' ); ?></h2>
				<p class="az-client-dashboard__help"><?php esc_html_e( 'Update the details you submitted during intake. Changes apply to your case and future document generation.', 'case-engine' ); ?></p>

				<form id="az-edit-intake-form" class="az-edit-intake-form">
					<h3 class="az-edit-intake-form__section"><?php esc_html_e( 'Case details', 'case-engine' ); ?></h3>
					<div class="az-edit-intake-form__grid">
						<label>
							<span><?php esc_html_e( 'County', 'case-engine' ); ?></span>
							<input type="text" name="county" value="<?php echo esc_attr( $case['county'] ?? '' ); ?>" required />
						</label>
						<label>
							<span><?php esc_html_e( 'Are there minor children?', 'case-engine' ); ?></span>
							<select name="has_children">
								<option value="no" <?php selected( $case['has_children'] ?? '', 'no' ); ?>><?php esc_html_e( 'No', 'case-engine' ); ?></option>
								<option value="yes" <?php selected( $case['has_children'] ?? '', 'yes' ); ?>><?php esc_html_e( 'Yes', 'case-engine' ); ?></option>
							</select>
						</label>
						<label>
							<span><?php esc_html_e( 'Your role', 'case-engine' ); ?></span>
							<select name="role">
								<option value="petitioner" <?php selected( $case['role'] ?? '', 'petitioner' ); ?>><?php esc_html_e( 'Petitioner', 'case-engine' ); ?></option>
								<option value="joint" <?php selected( $case['role'] ?? '', 'joint' ); ?>><?php esc_html_e( 'Joint filing', 'case-engine' ); ?></option>
							</select>
						</label>
						<label>
							<span><?php esc_html_e( 'Approximate filing date', 'case-engine' ); ?></span>
							<input type="date" name="filing_date" value="<?php echo esc_attr( $case['filing_date'] ?? '' ); ?>" />
						</label>
					</div>

					<h3 class="az-edit-intake-form__section"><?php esc_html_e( 'Petitioner', 'case-engine' ); ?></h3>
					<div class="az-edit-intake-form__grid">
						<label><span><?php esc_html_e( 'Full legal name', 'case-engine' ); ?></span>
							<input type="text" name="petitioner_full_name" value="<?php echo esc_attr( $pet['full_name'] ); ?>" required /></label>
						<label><span><?php esc_html_e( 'Address', 'case-engine' ); ?></span>
							<input type="text" name="petitioner_address" value="<?php echo esc_attr( $pet['address'] ); ?>" /></label>
						<label><span><?php esc_html_e( 'Phone', 'case-engine' ); ?></span>
							<input type="text" name="petitioner_phone" value="<?php echo esc_attr( $pet['phone'] ); ?>" /></label>
						<label><span><?php esc_html_e( 'Email', 'case-engine' ); ?></span>
							<input type="email" name="petitioner_email" value="<?php echo esc_attr( $pet['email'] ); ?>" /></label>
						<label><span><?php esc_html_e( 'Date of birth', 'case-engine' ); ?></span>
							<input type="date" name="petitioner_dob" value="<?php echo esc_attr( $pet['dob'] ); ?>" /></label>
					</div>

					<h3 class="az-edit-intake-form__section"><?php esc_html_e( 'Respondent', 'case-engine' ); ?></h3>
					<div class="az-edit-intake-form__grid">
						<label><span><?php esc_html_e( 'Full legal name', 'case-engine' ); ?></span>
							<input type="text" name="respondent_full_name" value="<?php echo esc_attr( $res['full_name'] ); ?>" /></label>
						<label><span><?php esc_html_e( 'Last known address', 'case-engine' ); ?></span>
							<input type="text" name="respondent_address" value="<?php echo esc_attr( $res['address'] ); ?>" /></label>
						<label><span><?php esc_html_e( 'Phone', 'case-engine' ); ?></span>
							<input type="text" name="respondent_phone" value="<?php echo esc_attr( $res['phone'] ); ?>" /></label>
						<label><span><?php esc_html_e( 'Email', 'case-engine' ); ?></span>
							<input type="email" name="respondent_email" value="<?php echo esc_attr( $res['email'] ); ?>" /></label>
					</div>

					<div class="az-edit-intake-children" <?php echo ( ( $case['has_children'] ?? '' ) === 'yes' ) ? '' : 'hidden'; ?>>
						<h3 class="az-edit-intake-form__section"><?php esc_html_e( 'Children', 'case-engine' ); ?></h3>
						<div id="az-edit-children-rows">
							<?php foreach ( $children as $i => $child ) : ?>
							<div class="az-edit-intake-form__grid az-edit-child-row">
								<label><span><?php esc_html_e( 'Full name', 'case-engine' ); ?></span>
									<input type="text" name="children[<?php echo (int) $i; ?>][full_name]" value="<?php echo esc_attr( $child['full_name'] ?? '' ); ?>" /></label>
								<label><span><?php esc_html_e( 'Date of birth', 'case-engine' ); ?></span>
									<input type="date" name="children[<?php echo (int) $i; ?>][dob]" value="<?php echo esc_attr( $child['dob'] ?? '' ); ?>" /></label>
								<label><span><?php esc_html_e( 'Relationship', 'case-engine' ); ?></span>
									<input type="text" name="children[<?php echo (int) $i; ?>][relationship]" value="<?php echo esc_attr( $child['relationship'] ?? '' ); ?>" placeholder="<?php esc_attr_e( 'e.g. Son, Daughter', 'case-engine' ); ?>" /></label>
							</div>
							<?php endforeach; ?>
						</div>
						<p><button type="button" class="az-intake-btn az-intake-btn-secondary" id="az-add-child-row">+ <?php esc_html_e( 'Add another child', 'case-engine' ); ?></button></p>
					</div>

					<p class="az-edit-intake-form__status" id="az-edit-intake-status" hidden></p>
					<p class="az-edit-intake-form__actions">
						<button type="submit" class="az-intake-btn az-intake-btn-primary"><?php esc_html_e( 'Save corrections', 'case-engine' ); ?></button>
						<a href="<?php echo esc_url( $cancel ); ?>" class="az-intake-btn az-intake-btn-secondary"><?php esc_html_e( 'Cancel', 'case-engine' ); ?></a>
					</p>
				</form>
			</div>
		</div>
		<script>
		(function () {
			var root = document.getElementById('az-edit-intake');
			if (!root) return;
			var form = document.getElementById('az-edit-intake-form');
			var statusEl = document.getElementById('az-edit-intake-status');
			var kidsWrap = root.querySelector('.az-edit-intake-children');
			var kidsRows = document.getElementById('az-edit-children-rows');

			form.querySelector('select[name="has_children"]').addEventListener('change', function () {
				kidsWrap.hidden = this.value !== 'yes';
			});

			document.getElementById('az-add-child-row').addEventListener('click', function () {
				var i = kidsRows.querySelectorAll('.az-edit-child-row').length;
				var div = document.createElement('div');
				div.className = 'az-edit-intake-form__grid az-edit-child-row';
				div.innerHTML =
					'<label><span>Full name</span><input type="text" name="children[' + i + '][full_name]" /></label>' +
					'<label><span>Date of birth</span><input type="date" name="children[' + i + '][dob]" /></label>' +
					'<label><span>Relationship</span><input type="text" name="children[' + i + '][relationship]" placeholder="e.g. Son, Daughter" /></label>';
				kidsRows.appendChild(div);
			});

			form.addEventListener('submit', function (e) {
				e.preventDefault();
				statusEl.hidden = true;
				var fd = new FormData(form);
				fd.append('action', 'az_client_save_intake');
				fd.append('nonce', root.getAttribute('data-nonce'));
				fd.append('case_id', root.getAttribute('data-case-id'));
				var btn = form.querySelector('button[type="submit"]');
				btn.disabled = true;
				fetch(root.getAttribute('data-ajax-url'), { method: 'POST', body: fd, credentials: 'same-origin' })
					.then(function (r) { return r.json(); })
					.then(function (res) {
						btn.disabled = false;
						if (res && res.success && res.data && res.data.redirect) {
							window.location.href = res.data.redirect;
							return;
						}
						statusEl.hidden = false;
						statusEl.className = 'az-edit-intake-form__status is-error';
						statusEl.textContent = (res && res.data && res.data.message) ? res.data.message : 'Could not save. Please try again.';
					})
					.catch(function () {
						btn.disabled = false;
						statusEl.hidden = false;
						statusEl.className = 'az-edit-intake-form__status is-error';
						statusEl.textContent = 'Could not save. Please try again.';
					});
			});
		})();
		</script>
		<?php
		return ob_get_clean();
	}

	/**
	 * AJAX: save client corrections to intake case + parties.
	 */
	public static function ajax_save_intake() {
		if ( ! check_ajax_referer( 'az_client_save_intake', 'nonce', false ) ) {
			wp_send_json_error( array( 'message' => __( 'Security check failed. Please refresh and try again.', 'case-engine' ) ), 403 );
		}

		$user_id = get_current_user_id();
		$case_id = isset( $_POST['case_id'] ) ? (int) $_POST['case_id'] : 0;
		if ( ! $user_id || ! $case_id ) {
			wp_send_json_error( array( 'message' => __( 'You must be logged in to edit this case.', 'case-engine' ) ), 401 );
		}

		$case = self::get_case_for_user( $case_id, $user_id );
		if ( ! $case ) {
			wp_send_json_error( array( 'message' => __( 'Case not found or access denied.', 'case-engine' ) ), 403 );
		}

		$county       = isset( $_POST['county'] ) ? sanitize_text_field( wp_unslash( $_POST['county'] ) ) : '';
		$has_children = isset( $_POST['has_children'] ) ? sanitize_text_field( wp_unslash( $_POST['has_children'] ) ) : 'no';
		$role         = isset( $_POST['role'] ) ? sanitize_text_field( wp_unslash( $_POST['role'] ) ) : 'petitioner';
		$filing_date  = isset( $_POST['filing_date'] ) ? sanitize_text_field( wp_unslash( $_POST['filing_date'] ) ) : '';
		if ( '' === $filing_date ) {
			$filing_date = null;
		}
		if ( ! in_array( $has_children, array( 'yes', 'no' ), true ) ) {
			$has_children = 'no';
		}
		if ( ! in_array( $role, array( 'petitioner', 'joint' ), true ) ) {
			$role = 'petitioner';
		}
		if ( '' === $county ) {
			wp_send_json_error( array( 'message' => __( 'County is required.', 'case-engine' ) ) );
		}

		$pet_name = isset( $_POST['petitioner_full_name'] ) ? sanitize_text_field( wp_unslash( $_POST['petitioner_full_name'] ) ) : '';
		if ( '' === $pet_name ) {
			wp_send_json_error( array( 'message' => __( 'Petitioner full name is required.', 'case-engine' ) ) );
		}

		$pet = array(
			'full_name' => $pet_name,
			'address'   => isset( $_POST['petitioner_address'] ) ? sanitize_text_field( wp_unslash( $_POST['petitioner_address'] ) ) : '',
			'phone'     => isset( $_POST['petitioner_phone'] ) ? sanitize_text_field( wp_unslash( $_POST['petitioner_phone'] ) ) : '',
			'email'     => isset( $_POST['petitioner_email'] ) ? sanitize_email( wp_unslash( $_POST['petitioner_email'] ) ) : '',
			'dob'       => isset( $_POST['petitioner_dob'] ) ? sanitize_text_field( wp_unslash( $_POST['petitioner_dob'] ) ) : '',
		);
		$res = array(
			'full_name' => isset( $_POST['respondent_full_name'] ) ? sanitize_text_field( wp_unslash( $_POST['respondent_full_name'] ) ) : '',
			'address'   => isset( $_POST['respondent_address'] ) ? sanitize_text_field( wp_unslash( $_POST['respondent_address'] ) ) : '',
			'phone'     => isset( $_POST['respondent_phone'] ) ? sanitize_text_field( wp_unslash( $_POST['respondent_phone'] ) ) : '',
			'email'     => isset( $_POST['respondent_email'] ) ? sanitize_email( wp_unslash( $_POST['respondent_email'] ) ) : '',
		);

		$children_raw = isset( $_POST['children'] ) && is_array( $_POST['children'] ) ? wp_unslash( $_POST['children'] ) : array();
		$children     = array();
		if ( 'yes' === $has_children ) {
			foreach ( $children_raw as $child ) {
				if ( ! is_array( $child ) ) {
					continue;
				}
				$name = sanitize_text_field( $child['full_name'] ?? '' );
				if ( '' === $name ) {
					continue;
				}
				$children[] = array(
					'full_name'    => $name,
					'dob'          => sanitize_text_field( $child['dob'] ?? '' ),
					'relationship' => sanitize_text_field( $child['relationship'] ?? '' ),
				);
			}
		}

		global $wpdb;
		$cases_table   = $wpdb->prefix . 'az_cases';
		$parties_table = $wpdb->prefix . 'az_parties';
		$answers_table = $wpdb->prefix . 'az_intake_answers';

		$wpdb->update(
			$cases_table,
			array(
				'county'       => $county,
				'has_children' => $has_children,
				'role'         => $role,
				'filing_date'  => $filing_date,
				'updated_at'   => current_time( 'mysql' ),
			),
			array( 'id' => $case_id ),
			array( '%s', '%s', '%s', '%s', '%s' ),
			array( '%d' )
		);

		$wpdb->delete( $parties_table, array( 'case_id' => $case_id ), array( '%d' ) );
		$sort = 0;
		$wpdb->insert(
			$parties_table,
			array(
				'case_id'    => $case_id,
				'party_type' => 'petitioner',
				'full_name'  => $pet['full_name'],
				'address'    => $pet['address'],
				'phone'      => $pet['phone'],
				'email'      => $pet['email'],
				'dob'        => $pet['dob'] !== '' ? $pet['dob'] : null,
				'sort_order' => $sort++,
			),
			array( '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%d' )
		);
		$wpdb->insert(
			$parties_table,
			array(
				'case_id'    => $case_id,
				'party_type' => 'respondent',
				'full_name'  => $res['full_name'],
				'address'    => $res['address'],
				'phone'      => $res['phone'],
				'email'      => $res['email'],
				'sort_order' => $sort++,
			),
			array( '%d', '%s', '%s', '%s', '%s', '%s', '%d' )
		);
		foreach ( $children as $child ) {
			$wpdb->insert(
				$parties_table,
				array(
					'case_id'      => $case_id,
					'party_type'   => 'child',
					'full_name'    => $child['full_name'],
					'dob'          => $child['dob'] !== '' ? $child['dob'] : null,
					'relationship' => $child['relationship'],
					'sort_order'   => $sort++,
				),
				array( '%d', '%s', '%s', '%s', '%s', '%d' )
			);
		}

		$session_id = (int) $wpdb->get_var( $wpdb->prepare(
			"SELECT intake_session_id FROM {$cases_table} WHERE id = %d LIMIT 1",
			$case_id
		) );

		$answer_map = array(
			'county'                         => $county,
			'has_children'                   => $has_children,
			'role'                           => $role,
			'filing_date'                    => (string) $filing_date,
			'petitioner_full_name'           => $pet['full_name'],
			'petitioner_address'             => $pet['address'],
			'petitioner_phone'               => $pet['phone'],
			'petitioner_email'               => $pet['email'],
			'petitioner_dob'                 => $pet['dob'],
			'respondent_full_name'           => $res['full_name'],
			'respondent_last_known_address'  => $res['address'],
			'respondent_phone'               => $res['phone'],
			'respondent_email'               => $res['email'],
			'children'                       => wp_json_encode( $children ),
		);
		foreach ( $answer_map as $key => $value ) {
			$existing = $wpdb->get_var( $wpdb->prepare(
				"SELECT id FROM {$answers_table} WHERE case_id = %d AND question_key = %s LIMIT 1",
				$case_id,
				$key
			) );
			if ( $existing ) {
				$wpdb->update(
					$answers_table,
					array( 'answer_value' => (string) $value ),
					array( 'id' => (int) $existing ),
					array( '%s' ),
					array( '%d' )
				);
			} else {
				$wpdb->insert(
					$answers_table,
					array(
						'case_id'      => $case_id,
						'session_id'   => $session_id,
						'question_key' => $key,
						'answer_value' => (string) $value,
					),
					array( '%d', '%d', '%s', '%s' )
				);
			}
		}

		// Keep questionnaire party fields in sync when a questionnaire row already exists.
		self::sync_questionnaire_party_fields( $user_id, $case_id, $pet, $res, $county );

		if ( method_exists( 'Case_Engine_Case_Factory', 'audit_log' ) ) {
			Case_Engine_Case_Factory::audit_log(
				'intake_corrected_by_client',
				'case',
				$case_id,
				$user_id,
				array( 'has_children' => $has_children, 'county' => $county )
			);
		}

		$redirect = add_query_arg(
			array(
				'view_case' => $case_id,
				'updated'   => 1,
			),
			home_url( '/' . self::DASHBOARD_SLUG . '/' )
		);

		wp_send_json_success( array(
			'message'  => __( 'Intake information updated.', 'case-engine' ),
			'redirect' => $redirect,
		) );
	}

	/**
	 * Mirror corrected intake party fields into the questionnaire row (if any).
	 *
	 * @param int   $user_id
	 * @param int   $case_id
	 * @param array $pet
	 * @param array $res
	 * @param string $county
	 */
	private static function sync_questionnaire_party_fields( $user_id, $case_id, array $pet, array $res, $county ) {
		if ( ! class_exists( 'Case_Engine_Questionnaire_DB' ) ) {
			return;
		}
		$existing = Case_Engine_Questionnaire_DB::get( (int) $user_id, (int) $case_id );
		if ( ! $existing ) {
			return;
		}

		$pet_parts = preg_split( '/\s+/', trim( $pet['full_name'] ), 2 );
		$res_parts = preg_split( '/\s+/', trim( $res['full_name'] ), 2 );
		$data      = array(
			'petitioner_first_name' => $pet_parts[0] ?? '',
			'petitioner_last_name'  => $pet_parts[1] ?? '',
			'petitioner_address'    => $pet['address'],
			'petitioner_phone'      => $pet['phone'],
			'petitioner_email'      => $pet['email'],
			'respondent_first_name' => $res_parts[0] ?? '',
			'respondent_last_name'  => $res_parts[1] ?? '',
			'respondent_address'    => $res['address'],
			'county_filing'         => $county,
		);
		Case_Engine_Questionnaire_DB::upsert( (int) $user_id, (int) $case_id, $data );
	}

	/**
	 * Retrieve questionnaire_status for a case from az_cases (v6+).
	 * Falls back to checking az_case_questionnaire.is_complete for installs pre-v6.
	 *
	 * @param int $case_id Case ID.
	 * @return string 'completed' | 'pending'
	 */
	private static function get_questionnaire_status( $case_id ) {
		global $wpdb;
		$case_id = (int) $case_id;

		// Try the denormalised column first (v6+).
		$cols = $wpdb->get_col( "SHOW COLUMNS FROM `{$wpdb->prefix}az_cases`", 0 );
		if ( in_array( 'questionnaire_status', $cols, true ) ) {
			$status = $wpdb->get_var( $wpdb->prepare(
				"SELECT questionnaire_status FROM {$wpdb->prefix}az_cases WHERE id = %d LIMIT 1",
				$case_id
			) );
			if ( $status === 'completed' ) {
				return 'completed';
			}
		}

		// Fallback: check az_case_questionnaire.is_complete.
		$is_complete = $wpdb->get_var( $wpdb->prepare(
			"SELECT is_complete FROM {$wpdb->prefix}az_case_questionnaire WHERE case_id = %d LIMIT 1",
			$case_id
		) );
		return ( '1' === (string) $is_complete ) ? 'completed' : 'pending';
	}

	/**
	 * Get a case by ID only if it belongs to the given user.
	 *
	 * @param int $case_id Case ID.
	 * @param int $user_id WordPress user ID.
	 * @return array|null Case row or null if not found / not owned.
	 */
	public static function get_case_for_user( $case_id, $user_id ) {
		global $wpdb;
		$p = $wpdb->prefix;
		$sessions_table = $p . 'az_intake_sessions';
		$cases_table    = $p . 'az_cases';

		$case_id = (int) $case_id;
		$user_id = (int) $user_id;
		if ( ! $case_id || ! $user_id ) {
			return null;
		}

		$row = $wpdb->get_row( $wpdb->prepare(
			"SELECT c.id, c.status, c.county, c.filing_date, c.role, c.has_children, c.created_at,
			        c.stripe_session_id, c.payment_date, c.payment_amount, c.questionnaire_status
			 FROM {$cases_table} c
			 LEFT JOIN {$sessions_table} s ON c.intake_session_id = s.id
			 WHERE c.id = %d
			   AND (
			     c.user_id = %d
			     OR ( c.user_id = 0 AND s.user_id = %d AND c.status IN ('pending_payment','draft','in_progress') )
			   )",
			$case_id,
			$user_id,
			$user_id
		), ARRAY_A );
		return $row ?: null;
	}

	/**
	 * Get parties for a case (petitioner, respondent, children).
	 *
	 * @param int $case_id Case ID.
	 * @return array List of party rows.
	 */
	public static function get_case_parties( $case_id ) {
		global $wpdb;
		$table = $wpdb->prefix . 'az_parties';
		$case_id = (int) $case_id;
		if ( ! $case_id ) {
			return array();
		}
		return $wpdb->get_results( $wpdb->prepare(
			"SELECT party_type, full_name, address, phone, email, dob, relationship
			 FROM {$table}
			 WHERE case_id = %d
			 ORDER BY sort_order ASC, id ASC",
			$case_id
		), ARRAY_A );
	}

	/**
	 * Get cases whose intake session belongs to the given user.
	 *
	 * @param int $user_id WordPress user ID.
	 * @return array List of case rows (id, status, county, created_at).
	 */
	public static function get_own_cases( $user_id ) {
		global $wpdb;
		$p = $wpdb->prefix;
		$sessions_table = $p . 'az_intake_sessions';
		$cases_table    = $p . 'az_cases';

		$user_id = (int) $user_id;
		if ( ! $user_id ) {
			return array();
		}

		// Safety net: if payment succeeded but status sync was missed, reconcile now.
		self::maybe_reconcile_paid_orders_for_user( $user_id );

		return $wpdb->get_results( $wpdb->prepare(
			"SELECT c.id, c.status, c.county, c.created_at
			 FROM {$cases_table} c
			 LEFT JOIN {$sessions_table} s ON c.intake_session_id = s.id
			 WHERE c.user_id = %d
			    OR ( c.user_id = 0 AND s.user_id = %d AND c.status IN ('pending_payment','draft','in_progress') )
			 ORDER BY c.created_at DESC",
			$user_id,
			$user_id
		), ARRAY_A );
	}

	/**
	 * Reconcile pending_payment cases to paid using customer's paid Woo orders.
	 *
	 * @param int $user_id WordPress user id.
	 * @return void
	 */
	private static function maybe_reconcile_paid_orders_for_user( $user_id ) {
		if ( ! function_exists( 'wc_get_orders' ) ) {
			return;
		}
		$user_id = (int) $user_id;
		if ( $user_id <= 0 ) {
			return;
		}

		global $wpdb;
		$cases_table = $wpdb->prefix . 'az_cases';
		$pending_ids = $wpdb->get_col( $wpdb->prepare(
			"SELECT id FROM {$cases_table} WHERE user_id = %d AND status = 'pending_payment' ORDER BY created_at DESC LIMIT 10",
			$user_id
		) );
		if ( empty( $pending_ids ) ) {
			return;
		}

		foreach ( $pending_ids as $case_id ) {
			$case_id = (int) $case_id;
			if ( $case_id <= 0 ) {
				continue;
			}

			$orders = wc_get_orders( array(
				'limit'       => 1,
				'orderby'     => 'date',
				'order'       => 'DESC',
				'customer_id' => $user_id,
				'status'      => array( 'processing', 'completed' ),
				'meta_key'    => '_az_case_id',
				'meta_value'  => $case_id,
			) );
			if ( empty( $orders ) ) {
				continue;
			}

			$order = $orders[0];
			if ( ! $order || ! $order->is_paid() ) {
				continue;
			}

			$intake_session_id = (int) $wpdb->get_var( $wpdb->prepare(
				"SELECT intake_session_id FROM {$cases_table} WHERE id = %d LIMIT 1",
				$case_id
			) );
			$amount         = (float) $order->get_total();
			$currency       = strtoupper( $order->get_currency() );
			$transaction_id = $order->get_transaction_id() ?: ( 'wc_order_' . $order->get_id() );

			Case_Engine_Case_Factory::mark_case_paid_from_stripe(
				$case_id,
				$intake_session_id,
				$amount,
				$currency,
				$transaction_id
			);

			// Ensure Woo order is marked as synced for idempotency.
			$order->update_meta_data( '_az_case_marked_paid', '1' );
			$order->save();
		}
	}

	/**
	 * Get the most recent case with status pending_payment for the user (for payment recovery when cookie/URL params are missing).
	 *
	 * @param int $user_id WordPress user ID.
	 * @return int Case ID or 0.
	 */
	public static function get_most_recent_pending_case_for_user( $user_id ) {
		global $wpdb;
		$p = $wpdb->prefix;
		$sessions_table = $p . 'az_intake_sessions';
		$cases_table    = $p . 'az_cases';
		$user_id = (int) $user_id;
		if ( ! $user_id ) {
			return 0;
		}
		$id = $wpdb->get_var( $wpdb->prepare(
			"SELECT c.id FROM {$cases_table} c
			 LEFT JOIN {$sessions_table} s ON c.intake_session_id = s.id
			 WHERE ( c.user_id = %d OR s.user_id = %d ) AND c.status = 'pending_payment'
			 ORDER BY c.created_at DESC LIMIT 1",
			$user_id,
			$user_id
		) );
		return $id ? (int) $id : 0;
	}
}
