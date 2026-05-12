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
				 SET status = 'completed', current_screen = 12, updated_at = %s
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
		$context_user_id = self::get_dashboard_context_user_id();
		if ( ! $context_user_id ) {
			return self::render_login_required();
		}
		if ( ! is_user_logged_in() ) {
			// Context-only access for immediate post-payment dashboard view.
			wp_set_current_user( $context_user_id );
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
				return self::render_case_detail( $case, $parties );
			}
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
		$back_url = remove_query_arg( array( 'view_case', 'case_id' ), get_permalink() );
		$case_id  = (int) $case['id'];

		$output = '<div class="az-client-dashboard">';
		$output .= '<p><a href="' . esc_url( $back_url ) . '" class="az-intake-btn az-intake-btn-secondary">← ' . esc_html__( 'Back to your cases', 'case-engine' ) . '</a></p>';
		$output .= self::render_contested_case_notice();

		// Case info card
		$output .= '<div class="az-client-dashboard__card">';
		$output .= '<span class="az-client-dashboard__subtitle">' . esc_html__( 'Case', 'case-engine' ) . ' #' . $case_id . '</span>';
		$output .= '<h2 class="az-client-dashboard__title">' . esc_html__( 'Case details', 'case-engine' ) . '</h2>';
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
		$output .= '<h2 class="az-client-dashboard__title">' . esc_html__( 'Parties', 'case-engine' ) . '</h2>';

		if ( empty( $parties ) ) {
			$output .= '<p>' . esc_html__( 'No party information on file.', 'case-engine' ) . '</p>';
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
			 WHERE c.id = %d AND (c.user_id = %d OR s.user_id = %d)",
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
			 WHERE c.user_id = %d OR s.user_id = %d
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
