<?php
/**
 * Creates Case, Parties, Intake answers, Payment, and Audit records from an intake session.
 *
 * @package Case_Engine
 */

defined( 'ABSPATH' ) || exit;

class Case_Engine_Case_Factory {

	/**
	 * Create a case (and related records) from an intake session.
	 *
	 * @param string|int $session_key_or_id Intake session key (string) or session ID (int) for backfill.
	 * @param array      $options           Optional. 'case_status' => 'pending_payment' (create on Review) or 'paid' (create on Payment).
	 * @return int|false Case ID on success, false on failure.
	 */
	public static function create_from_session( $session_key_or_id, $options = array() ) {
		global $wpdb;
		$sessions_table = $wpdb->prefix . 'az_intake_sessions';

		if ( is_numeric( $session_key_or_id ) ) {
			$session = $wpdb->get_row( $wpdb->prepare(
				"SELECT * FROM $sessions_table WHERE id = %d",
				(int) $session_key_or_id
			), ARRAY_A );
		} else {
			$session = $wpdb->get_row( $wpdb->prepare(
				"SELECT * FROM $sessions_table WHERE session_key = %s",
				$session_key_or_id
			), ARRAY_A );
		}

		if ( ! $session || empty( $session['answers'] ) ) {
			return false;
		}

		$answers = json_decode( $session['answers'], true );
		if ( ! is_array( $answers ) ) {
			return false;
		}

		$case_status = isset( $options['case_status'] ) ? $options['case_status'] : 'paid';
		$is_pending  = ( $case_status === 'pending_payment' );

		// If we already have a case for this session, return it (payment step may just need to mark it paid).
		if ( ! empty( $session['case_id'] ) ) {
			return (int) $session['case_id'];
		}

		$session_id = (int) $session['id'];
		$user_id    = (int) ( $session['user_id'] ?? get_current_user_id() );

		$wpdb->query( 'START TRANSACTION' );

		try {
			// 1. Insert case
			$cases_table = $wpdb->prefix . 'az_cases';
			$wpdb->insert(
				$cases_table,
				array(
					'intake_session_id' => $session_id,
					'county'           => self::get_answer( $answers, 'county', 'Maricopa' ),
					'has_children'     => self::get_answer( $answers, 'has_children', 'no' ),
					'filing_date'      => self::get_answer( $answers, 'filing_date', '' ) ?: null,
					'role'             => self::get_answer( $answers, 'role', 'petitioner' ),
					'status'           => $case_status,
				),
				array( '%d', '%s', '%s', '%s', '%s', '%s' )
			);
			$case_id = (int) $wpdb->insert_id;
			if ( ! $case_id ) {
				throw new Exception( 'Case insert failed' );
			}

			// 2. Insert parties (petitioner, respondent, children)
			$parties_table = $wpdb->prefix . 'az_parties';
			$sort = 0;

			// Petitioner
			$wpdb->insert(
				$parties_table,
				array(
					'case_id'     => $case_id,
					'party_type'  => 'petitioner',
					'full_name'   => self::get_answer( $answers, 'petitioner_full_name', '' ),
					'address'     => self::get_answer( $answers, 'petitioner_address', '' ),
					'phone'       => self::get_answer( $answers, 'petitioner_phone', '' ),
					'email'       => self::get_answer( $answers, 'petitioner_email', '' ),
					'dob'         => self::get_answer( $answers, 'petitioner_dob', null ) ?: null,
					'sort_order'  => $sort++,
				),
				array( '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%d' )
			);

			// Respondent
			$wpdb->insert(
				$parties_table,
				array(
					'case_id'     => $case_id,
					'party_type'  => 'respondent',
					'full_name'   => self::get_answer( $answers, 'respondent_full_name', '' ),
					'address'     => self::get_answer( $answers, 'respondent_last_known_address', '' ),
					'phone'       => self::get_answer( $answers, 'respondent_phone', '' ),
					'email'       => self::get_answer( $answers, 'respondent_email', '' ),
					'sort_order'  => $sort++,
				),
				array( '%d', '%s', '%s', '%s', '%s', '%s', '%d' )
			);

			// Children (from children[0], children[1], ...)
			$children = isset( $answers['children'] ) && is_array( $answers['children'] ) ? $answers['children'] : array();
			foreach ( $children as $i => $child ) {
				if ( ! is_array( $child ) ) {
					continue;
				}
				$name = isset( $child['full_name'] ) ? sanitize_text_field( $child['full_name'] ) : '';
				if ( $name === '' ) {
					continue;
				}
				$wpdb->insert(
					$parties_table,
					array(
						'case_id'      => $case_id,
						'party_type'   => 'child',
						'full_name'    => $name,
						'dob'          => ! empty( $child['dob'] ) ? $child['dob'] : null,
						'relationship'  => isset( $child['relationship'] ) ? sanitize_text_field( $child['relationship'] ) : '',
						'sort_order'    => $sort++,
					),
					array( '%d', '%s', '%s', '%s', '%s', '%d' )
				);
			}

			// 3. Insert intake_answers (structured key/value per case)
			$answers_table = $wpdb->prefix . 'az_intake_answers';
			foreach ( $answers as $key => $value ) {
				if ( is_array( $value ) ) {
					$value = wp_json_encode( $value );
				}
				$key = sanitize_key( $key );
				if ( $key === '' ) {
					continue;
				}
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

			// 4. Insert payment row only when status is 'paid' (on Payment step); skip for pending_payment
			if ( ! $is_pending ) {
				$payments_table = $wpdb->prefix . 'az_payments';
				$wpdb->insert(
					$payments_table,
					array(
						'case_id'           => $case_id,
						'amount'            => 0.00,
						'currency'          => 'USD',
						'status'            => 'completed',
						'stripe_payment_id' => '',
						'paid_at'           => current_time( 'mysql' ),
					),
					array( '%d', '%f', '%s', '%s', '%s', '%s' )
				);
			}

			// 5. Update session with case_id (by primary key)
			$wpdb->update(
				$sessions_table,
				array(
					'case_id'       => $case_id,
					'status'        => $is_pending ? 'pending_payment' : 'completed',
					'current_screen' => $is_pending ? 11 : 12,
					'updated_at'    => current_time( 'mysql' ),
				),
				array( 'id' => $session_id ),
				array( '%d', '%s', '%d', '%s' ),
				array( '%d' )
			);

			// 6. Audit log
			self::audit_log( 'case_created', 'case', $case_id, $user_id, array( 'intake_session_id' => $session_id, 'status' => $case_status ) );
			if ( ! $is_pending ) {
				self::audit_log( 'payment_completed', 'payment', $case_id, $user_id, array( 'case_id' => $case_id ) );
			}

			$wpdb->query( 'COMMIT' );
			return $case_id;

		} catch ( Exception $e ) {
			$wpdb->query( 'ROLLBACK' );
			return false;
		}
	}

	/**
	 * Mark an existing case as paid and add payment record (when user completes Payment step).
	 *
	 * @param int $case_id Case ID.
	 * @param int $session_id Optional. Session ID for updating session row.
	 * @return bool True on success.
	 */
	public static function mark_case_paid( $case_id, $session_id = 0 ) {
		global $wpdb;
		$case_id    = (int) $case_id;
		$session_id = (int) $session_id;
		$user_id    = get_current_user_id();

		$cases_table = $wpdb->prefix . 'az_cases';
		$wpdb->update(
			$cases_table,
			array( 'status' => 'paid', 'updated_at' => current_time( 'mysql' ) ),
			array( 'id' => $case_id ),
			array( '%s', '%s' ),
			array( '%d' )
		);

		$payments_table = $wpdb->prefix . 'az_payments';
		$wpdb->insert(
			$payments_table,
			array(
				'case_id'           => $case_id,
				'amount'            => 0.00,
				'currency'          => 'USD',
				'status'            => 'completed',
				'stripe_payment_id' => '',
				'paid_at'           => current_time( 'mysql' ),
			),
			array( '%d', '%f', '%s', '%s', '%s', '%s' )
		);

		if ( $session_id ) {
			$sessions_table = $wpdb->prefix . 'az_intake_sessions';
			$wpdb->update(
				$sessions_table,
				array( 'status' => 'completed', 'current_screen' => 12, 'updated_at' => current_time( 'mysql' ) ),
				array( 'id' => $session_id ),
				array( '%s', '%d', '%s' ),
				array( '%d' )
			);
		}

		self::audit_log( 'payment_completed', 'payment', $case_id, $user_id, array( 'case_id' => $case_id ) );
		return true;
	}

	/**
	 * Mark case paid from Stripe webhook (idempotent caller); store amount and Stripe session id.
	 *
	 * @param int    $case_id           Case ID.
	 * @param int    $session_id        Intake session ID (to mark session completed).
	 * @param float  $amount            Amount paid (e.g. 99.99).
	 * @param string $currency          Currency code (e.g. USD).
	 * @param string $stripe_payment_id Stripe Checkout Session ID or payment intent id.
	 * @return bool True on success.
	 */
	public static function mark_case_paid_from_stripe( $case_id, $session_id = 0, $amount = 0.00, $currency = 'USD', $stripe_payment_id = '' ) {
		global $wpdb;
		$case_id    = (int) $case_id;
		$session_id = (int) $session_id;
		$amount     = (float) $amount;
		$currency   = sanitize_text_field( $currency ) ?: 'USD';
		$stripe_payment_id = sanitize_text_field( $stripe_payment_id );

		$cases_table = $wpdb->prefix . 'az_cases';

		// Build update data; conditionally add new columns only when they exist on the table.
		$cols        = $wpdb->get_col( "SHOW COLUMNS FROM `{$cases_table}`", 0 );
		$update_data = array( 'status' => 'paid', 'updated_at' => current_time( 'mysql' ) );
		$update_fmt  = array( '%s', '%s' );

		if ( in_array( 'stripe_session_id', $cols, true ) ) {
			$update_data['stripe_session_id'] = $stripe_payment_id;
			$update_fmt[] = '%s';
		}
		if ( in_array( 'payment_date', $cols, true ) ) {
			$update_data['payment_date'] = current_time( 'mysql' );
			$update_fmt[] = '%s';
		}
		if ( in_array( 'payment_amount', $cols, true ) ) {
			$update_data['payment_amount'] = $amount;
			$update_fmt[] = '%f';
		}

		$wpdb->update( $cases_table, $update_data, array( 'id' => $case_id ), $update_fmt, array( '%d' ) );

		$payments_table = $wpdb->prefix . 'az_payments';
		$wpdb->insert(
			$payments_table,
			array(
				'case_id'           => $case_id,
				'amount'            => $amount,
				'currency'          => $currency,
				'status'            => 'completed',
				'stripe_payment_id' => $stripe_payment_id,
				'paid_at'           => current_time( 'mysql' ),
			),
			array( '%d', '%f', '%s', '%s', '%s', '%s' )
		);

		if ( $session_id ) {
			$sessions_table = $wpdb->prefix . 'az_intake_sessions';
			$wpdb->update(
				$sessions_table,
				array( 'status' => 'completed', 'current_screen' => 12, 'updated_at' => current_time( 'mysql' ) ),
				array( 'id' => $session_id ),
				array( '%s', '%d', '%s' ),
				array( '%d' )
			);
		}

		self::audit_log( 'payment_completed', 'payment', $case_id, 0, array(
			'case_id' => $case_id,
			'source'  => 'stripe_webhook',
			'stripe_id' => $stripe_payment_id,
		) );
		return true;
	}

	/**
	 * Mark a case's questionnaire_status as 'completed' on az_cases.
	 * Called when the user submits and finalises the questionnaire.
	 *
	 * @param int $case_id Case ID.
	 * @return bool
	 */
	public static function mark_questionnaire_complete( $case_id ) {
		global $wpdb;
		$case_id    = (int) $case_id;
		$cases_table = $wpdb->prefix . 'az_cases';

		// Only update the column if it exists (safe for installs that haven't run v6 migration yet).
		$cols = $wpdb->get_col( "SHOW COLUMNS FROM `{$cases_table}`", 0 );
		if ( ! in_array( 'questionnaire_status', $cols, true ) ) {
			return false;
		}

		$result = $wpdb->update(
			$cases_table,
			array( 'questionnaire_status' => 'completed', 'updated_at' => current_time( 'mysql' ) ),
			array( 'id' => $case_id ),
			array( '%s', '%s' ),
			array( '%d' )
		);
		return $result !== false;
	}

	/**
	 * Get a scalar answer from the answers array.
	 *
	 * @param array  $answers Answers array.
	 * @param string $key     Key.
	 * @param mixed  $default Default value.
	 * @return mixed
	 */
	private static function get_answer( $answers, $key, $default = '' ) {
		if ( ! isset( $answers[ $key ] ) ) {
			return $default;
		}
		$v = $answers[ $key ];
		return is_scalar( $v ) ? $v : $default;
	}

	/**
	 * Write an audit log entry.
	 *
	 * @param string $action     Action name.
	 * @param string $entity_type Entity type (case, payment, etc.).
	 * @param int    $entity_id  Entity ID.
	 * @param int    $user_id    User ID.
	 * @param array  $details    Optional details (stored as JSON).
	 */
	public static function audit_log( $action, $entity_type, $entity_id, $user_id = 0, $details = array() ) {
		global $wpdb;
		$table = $wpdb->prefix . 'az_audit_logs';
		$wpdb->insert(
			$table,
			array(
				'action'      => $action,
				'entity_type' => $entity_type,
				'entity_id'   => $entity_id,
				'user_id'     => $user_id,
				'details'     => wp_json_encode( $details ),
			),
			array( '%s', '%s', '%d', '%d', '%s' )
		);
	}
}
