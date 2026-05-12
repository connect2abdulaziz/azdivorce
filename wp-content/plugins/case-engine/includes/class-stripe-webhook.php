<?php
/**
 * Stripe Webhook — idempotent handling, payment → case state, audit trail.
 *
 * Add to wp-config.php:
 *   define( 'STRIPE_WEBHOOK_SECRET', 'whsec_...' );
 *
 * Configure in Stripe Dashboard: Webhooks → Add endpoint → URL = https://yoursite.com/?case_engine_stripe_webhook=1
 * Events to send: checkout.session.completed
 *
 * @package Case_Engine
 */

defined( 'ABSPATH' ) || exit;

class Case_Engine_Stripe_Webhook {

	/**
	 * Register REST route and query-var fallback for webhook.
	 */
	public static function register() {
		add_filter( 'query_vars', array( __CLASS__, 'add_query_vars' ) );
		add_action( 'rest_api_init', array( __CLASS__, 'register_rest_route' ) );
		add_action( 'parse_request', array( __CLASS__, 'maybe_handle_webhook' ), 1 );
	}

	public static function add_query_vars( $vars ) {
		$vars[] = 'case_engine_stripe_webhook';
		return $vars;
	}

	/**
	 * REST route for webhook (alternative to query var).
	 */
	public static function register_rest_route() {
		register_rest_route( 'case-engine/v1', '/stripe-webhook', array(
			'methods'             => 'POST',
			'callback'            => array( __CLASS__, 'handle_webhook' ),
			'permission_callback' => '__return_true',
		) );
	}

	/**
	 * Handle webhook via query var (no perm check; Stripe signature is the auth).
	 *
	 * @param WP $wp WordPress request object.
	 */
	public static function maybe_handle_webhook( $wp ) {
		if ( ! isset( $wp->query_vars['case_engine_stripe_webhook'] ) || $wp->query_vars['case_engine_stripe_webhook'] === '' ) {
			return;
		}
		self::handle_webhook();
		exit;
	}

	/**
	 * Main webhook handler: verify signature, idempotency, process checkout.session.completed.
	 */
	public static function handle_webhook() {
		$payload = null;
		if ( isset( $GLOBALS['HTTP_RAW_POST_DATA'] ) && $GLOBALS['HTTP_RAW_POST_DATA'] !== '' ) {
			$payload = $GLOBALS['HTTP_RAW_POST_DATA'];
		}
		if ( empty( $payload ) ) {
			$payload = file_get_contents( 'php://input' );
		}
		if ( empty( $payload ) ) {
			self::send_response( 400, 'Empty body' );
		}

		$sig = isset( $_SERVER['HTTP_STRIPE_SIGNATURE'] ) ? $_SERVER['HTTP_STRIPE_SIGNATURE'] : '';
		if ( ! $sig ) {
			self::send_response( 400, 'Missing signature' );
		}

		$secret = defined( 'STRIPE_WEBHOOK_SECRET' ) ? STRIPE_WEBHOOK_SECRET : '';
		if ( ! $secret ) {
			self::log_webhook( null, 'no_secret', 0, 'Webhook secret not configured' );
			self::send_response( 500, 'Webhook not configured' );
		}

		$autoload = CASE_ENGINE_PLUGIN_DIR . 'vendor/autoload.php';
		if ( ! file_exists( $autoload ) ) {
			self::log_webhook( null, 'no_sdk', 0, 'Stripe SDK missing' );
			self::send_response( 500, 'Server error' );
		}
		require_once $autoload;
		\Stripe\Stripe::setApiKey( defined( 'STRIPE_SECRET_KEY' ) ? STRIPE_SECRET_KEY : '' );

		try {
			$event = \Stripe\Webhook::constructEvent( $payload, $sig, $secret );
		} catch ( \UnexpectedValueException $e ) {
			self::log_webhook( null, 'invalid_payload', 0, $e->getMessage() );
			self::send_response( 400, 'Invalid payload' );
		} catch ( \Stripe\Exception\SignatureVerificationException $e ) {
			self::log_webhook( null, 'invalid_signature', 0, $e->getMessage() );
			self::send_response( 400, 'Invalid signature' );
		}

		$event_id   = $event->id;
		$event_type = $event->type;

		// Idempotency: already processed?
		if ( self::event_processed( $event_id ) ) {
			self::log_webhook( $event_id, $event_type, 0, 'duplicate' );
			self::send_response( 200, 'OK' );
		}

		// Insert idempotency row and process
		self::insert_event_row( $event_id, $event_type );

		if ( $event_type === 'checkout.session.completed' ) {
			$result = self::process_checkout_completed( $event );
			self::update_event_row( $event_id, $result['case_id'], $result['result'] );
			self::audit_log( $event_id, $event_type, $result['case_id'], $result['result'] );
		} else {
			self::update_event_row( $event_id, 0, 'ignored' );
			self::log_webhook( $event_id, $event_type, 0, 'ignored' );
		}

		self::send_response( 200, 'OK' );
	}

	/**
	 * Process checkout.session.completed: mark case paid, insert payment, update session.
	 *
	 * @param \Stripe\Event $event Stripe event.
	 * @return array{ case_id: int, result: string }
	 */
	private static function process_checkout_completed( $event ) {
		$session = $event->data->object;
		$case_id = isset( $session->metadata->case_id ) ? (int) $session->metadata->case_id : 0;
		$intake_session_id = isset( $session->metadata->intake_session_id ) ? (int) $session->metadata->intake_session_id : 0;

		if ( ! $case_id ) {
			return array( 'case_id' => 0, 'result' => 'no_case_id' );
		}

		global $wpdb;
		$cases_table = $wpdb->prefix . 'az_cases';
		$current = $wpdb->get_var( $wpdb->prepare(
			"SELECT status FROM {$cases_table} WHERE id = %d",
			$case_id
		) );
		if ( $current === 'paid' ) {
			return array( 'case_id' => $case_id, 'result' => 'already_paid' );
		}

		$amount_total = isset( $session->amount_total ) ? ( $session->amount_total / 100 ) : 0.00;
		$currency     = isset( $session->currency ) ? strtoupper( $session->currency ) : 'USD';
		$stripe_id    = isset( $session->id ) ? $session->id : '';

		Case_Engine_Case_Factory::mark_case_paid_from_stripe(
			$case_id,
			$intake_session_id,
			$amount_total,
			$currency,
			$stripe_id
		);

		return array( 'case_id' => $case_id, 'result' => 'marked_paid' );
	}

	/**
	 * Check if event was already processed (idempotency).
	 *
	 * @param string $event_id Stripe event id.
	 * @return bool
	 */
	private static function event_processed( $event_id ) {
		global $wpdb;
		$table = $wpdb->prefix . 'az_stripe_events';
		$id = $wpdb->get_var( $wpdb->prepare(
			"SELECT id FROM {$table} WHERE stripe_event_id = %s",
			$event_id
		) );
		return (int) $id > 0;
	}

	/**
	 * Insert event row (for idempotency; processed=0 initially).
	 *
	 * @param string $event_id   Stripe event id.
	 * @param string $event_type Event type.
	 */
	private static function insert_event_row( $event_id, $event_type ) {
		global $wpdb;
		$table = $wpdb->prefix . 'az_stripe_events';
		$wpdb->insert(
			$table,
			array(
				'stripe_event_id' => $event_id,
				'event_type'      => $event_type,
				'processed'       => 0,
				'result'          => 'pending',
			),
			array( '%s', '%s', '%d', '%s' )
		);
	}

	/**
	 * Update event row after processing.
	 *
	 * @param string $event_id   Stripe event id.
	 * @param int    $case_id    Case ID.
	 * @param string $result     Result (marked_paid, already_paid, no_case_id, ignored).
	 */
	private static function update_event_row( $event_id, $case_id, $result ) {
		global $wpdb;
		$table = $wpdb->prefix . 'az_stripe_events';
		$wpdb->update(
			$table,
			array( 'processed' => 1, 'case_id' => $case_id, 'result' => $result ),
			array( 'stripe_event_id' => $event_id ),
			array( '%d', '%d', '%s' ),
			array( '%s' )
		);
	}

	/**
	 * Log to audit_logs for webhook trail.
	 */
	private static function audit_log( $event_id, $event_type, $case_id, $result ) {
		global $wpdb;
		$table = $wpdb->prefix . 'az_audit_logs';
		$wpdb->insert(
			$table,
			array(
				'action'      => 'stripe_webhook',
				'entity_type' => 'stripe_webhook',
				'entity_id'   => 0,
				'user_id'     => 0,
				'details'     => wp_json_encode( array(
					'stripe_event_id' => $event_id,
					'event_type'     => $event_type,
					'case_id'        => $case_id,
					'result'         => $result,
				) ),
			),
			array( '%s', '%s', '%d', '%d', '%s' )
		);
	}

	/**
	 * Fallback log when we can't use event id (e.g. before parsing).
	 *
	 * @param string|null $event_id   Event id if available.
	 * @param string      $event_type Type or reason.
	 * @param int         $case_id    Case id.
	 * @param string      $message    Message.
	 */
	private static function log_webhook( $event_id, $event_type, $case_id, $message ) {
		global $wpdb;
		$table = $wpdb->prefix . 'az_audit_logs';
		$wpdb->insert(
			$table,
			array(
				'action'      => 'stripe_webhook',
				'entity_type' => 'stripe_webhook',
				'entity_id'   => 0,
				'user_id'     => 0,
				'details'     => wp_json_encode( array(
					'stripe_event_id' => $event_id,
					'event_type'     => $event_type,
					'case_id'        => $case_id,
					'message'        => $message,
				) ),
			),
			array( '%s', '%s', '%d', '%d', '%s' )
		);
	}

	/**
	 * Send HTTP response and exit.
	 *
	 * @param int    $code    HTTP code.
	 * @param string $body    Body text.
	 */
	private static function send_response( $code, $body ) {
		status_header( $code );
		echo $body;
		exit;
	}
}
