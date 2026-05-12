<?php
/**
 * Questionnaire AJAX API — handles autosave and step-save requests.
 *
 * Actions registered:
 *   wp_ajax_az_questionnaire_save   — save step data (logged-in users only)
 *   wp_ajax_az_questionnaire_load   — load existing questionnaire data
 *
 * @package Case_Engine
 */

defined( 'ABSPATH' ) || exit;

class Case_Engine_Questionnaire_API {

	public static function register() {
		add_action( 'wp_ajax_az_questionnaire_save', array( __CLASS__, 'handle_save' ) );
		add_action( 'wp_ajax_az_questionnaire_load', array( __CLASS__, 'handle_load' ) );
	}

	// ── Save handler ────────────────────────────────────────────────────────

	/**
	 * AJAX: save questionnaire step.
	 * POST params: nonce, case_id, step (1–7), field data, property_items[], debt_items[]
	 */
	public static function handle_save() {
		if ( ! is_user_logged_in() ) {
			wp_send_json_error( array( 'message' => __( 'You must be logged in.', 'case-engine' ) ), 401 );
		}

		if ( ! check_ajax_referer( 'az_questionnaire_nonce', 'nonce', false ) ) {
			wp_send_json_error( array( 'message' => __( 'Security check failed.', 'case-engine' ) ), 403 );
		}

		$user_id = get_current_user_id();
		$case_id = isset( $_POST['case_id'] ) ? (int) $_POST['case_id'] : 0;
		$step    = isset( $_POST['step'] ) ? (int) $_POST['step'] : 0;

		if ( ! $case_id || $step < 1 || $step > 7 ) {
			wp_send_json_error( array( 'message' => __( 'Invalid case or step.', 'case-engine' ) ) );
		}

		// Verify case belongs to this user.
		if ( ! self::user_owns_case( $user_id, $case_id ) ) {
			wp_send_json_error( array( 'message' => __( 'Case not found.', 'case-engine' ) ), 403 );
		}

		$data = self::sanitize_step_data( $step, $_POST );

		// Track which steps have been completed.
		$existing = Case_Engine_Questionnaire_DB::get( $user_id, $case_id );
		$completed = $existing ? self::parse_completed_steps( $existing['completed_steps'] ) : array();
		$completed[] = $step;
		$completed   = array_unique( $completed );
		sort( $completed );
		$data['completed_steps'] = implode( ',', $completed );

		// Advance current_step pointer.
		$data['current_step'] = max( $step + 1, (int) ( $existing['current_step'] ?? 1 ) );

		// Mark complete when all 7 steps have been saved.
		$all_complete = ( count( $completed ) >= 7 );
		if ( $all_complete ) {
			$data['is_complete']  = 1;
			$data['current_step'] = 7;
		}

		$record_id = Case_Engine_Questionnaire_DB::upsert( $user_id, $case_id, $data );

		if ( ! $record_id ) {
			wp_send_json_error( array( 'message' => __( 'Failed to save. Please try again.', 'case-engine' ) ) );
		}

		// Sync questionnaire_status to az_cases so dashboard rules are always consistent.
		if ( $all_complete ) {
			Case_Engine_Case_Factory::mark_questionnaire_complete( $case_id );
		}

		// Audit trail.
		Case_Engine_Case_Factory::audit_log(
			'questionnaire_step_saved',
			'questionnaire',
			$record_id,
			$user_id,
			array( 'case_id' => $case_id, 'step' => $step )
		);

		wp_send_json_success( array(
			'message'         => __( 'Saved.', 'case-engine' ),
			'record_id'       => $record_id,
			'completed_steps' => $completed,
			'is_complete'     => $all_complete,
			'next_step'       => min( $step + 1, 7 ),
		) );
	}

	// ── Load handler ────────────────────────────────────────────────────────

	/**
	 * AJAX: load existing questionnaire data for a case.
	 * POST params: nonce, case_id
	 */
	public static function handle_load() {
		if ( ! is_user_logged_in() ) {
			wp_send_json_error( array( 'message' => __( 'You must be logged in.', 'case-engine' ) ), 401 );
		}

		if ( ! check_ajax_referer( 'az_questionnaire_nonce', 'nonce', false ) ) {
			wp_send_json_error( array( 'message' => __( 'Security check failed.', 'case-engine' ) ), 403 );
		}

		$user_id = get_current_user_id();
		$case_id = isset( $_POST['case_id'] ) ? (int) $_POST['case_id'] : 0;

		if ( ! $case_id ) {
			wp_send_json_error( array( 'message' => __( 'Invalid case.', 'case-engine' ) ) );
		}

		if ( ! self::user_owns_case( $user_id, $case_id ) ) {
			wp_send_json_error( array( 'message' => __( 'Case not found.', 'case-engine' ) ), 403 );
		}

		$row = Case_Engine_Questionnaire_DB::get( $user_id, $case_id );

		if ( ! $row ) {
			wp_send_json_success( array( 'exists' => false, 'data' => array() ) );
		}

		// Decode JSON columns before sending to JS.
		foreach ( Case_Engine_Questionnaire_DB::json_columns() as $col ) {
			if ( isset( $row[ $col ] ) && is_string( $row[ $col ] ) ) {
				$decoded = json_decode( $row[ $col ], true );
				$row[ $col ] = is_array( $decoded ) ? $decoded : array();
			}
		}

		wp_send_json_success( array(
			'exists'          => true,
			'data'            => $row,
			'completed_steps' => self::parse_completed_steps( $row['completed_steps'] ?? '' ),
			'current_step'    => (int) ( $row['current_step'] ?? 1 ),
			'is_complete'     => (bool) ( $row['is_complete'] ?? false ),
		) );
	}

	// ── Private helpers ──────────────────────────────────────────────────────

	/**
	 * Sanitize and extract field data for the given step from raw $_POST.
	 *
	 * @param int   $step 1–7.
	 * @param array $post Raw $_POST (wp_unslash applied inside).
	 * @return array
	 */
	private static function sanitize_step_data( $step, $post ) {
		$data = array();
		$p    = wp_unslash( $post );

		switch ( $step ) {

			case 1: // Petitioner Information
				$data['petitioner_first_name'] = sanitize_text_field( $p['petitioner_first_name'] ?? '' );
				$data['petitioner_last_name']  = sanitize_text_field( $p['petitioner_last_name'] ?? '' );
				$data['petitioner_address']    = sanitize_text_field( $p['petitioner_address'] ?? '' );
				$data['petitioner_city']       = sanitize_text_field( $p['petitioner_city'] ?? '' );
				$data['petitioner_state']      = sanitize_text_field( $p['petitioner_state'] ?? '' );
				$data['petitioner_zip']        = sanitize_text_field( $p['petitioner_zip'] ?? '' );
				$data['petitioner_phone']      = sanitize_text_field( $p['petitioner_phone'] ?? '' );
				$data['petitioner_email']      = sanitize_email( $p['petitioner_email'] ?? '' );
				break;

			case 2: // Respondent Information
				$data['respondent_first_name'] = sanitize_text_field( $p['respondent_first_name'] ?? '' );
				$data['respondent_last_name']  = sanitize_text_field( $p['respondent_last_name'] ?? '' );
				$data['respondent_address']    = sanitize_text_field( $p['respondent_address'] ?? '' );
				$data['respondent_city']       = sanitize_text_field( $p['respondent_city'] ?? '' );
				$data['respondent_state']      = sanitize_text_field( $p['respondent_state'] ?? '' );
				$data['respondent_zip']        = sanitize_text_field( $p['respondent_zip'] ?? '' );
				break;

			case 3: // Marriage Information
				$data['marriage_date']   = self::sanitize_date( $p['marriage_date'] ?? '' );
				$data['marriage_city']   = sanitize_text_field( $p['marriage_city'] ?? '' );
				$data['marriage_state']  = sanitize_text_field( $p['marriage_state'] ?? '' );
				$data['separation_date'] = self::sanitize_date( $p['separation_date'] ?? '' );
				break;

			case 4: // Filing Details
				$data['county_filing']    = sanitize_text_field( $p['county_filing'] ?? '' );
				$data['covenant_marriage'] = self::sanitize_yes_no( $p['covenant_marriage'] ?? 'no' );
				$data['pregnancy_status'] = self::sanitize_yes_no( $p['pregnancy_status'] ?? 'no' );
				break;

			case 5: // Property & Debt
				$data['property_division'] = self::sanitize_property_items( $p['property_items'] ?? array() );
				$data['debt_division']     = self::sanitize_debt_items( $p['debt_items'] ?? array() );
				break;

			case 6: // Name Change
				$data['restore_former_name'] = self::sanitize_yes_no( $p['restore_former_name'] ?? 'no' );
				$data['former_name']         = sanitize_text_field( $p['former_name'] ?? '' );
				break;

			case 7: // Service of Process
				$data['service_method']        = sanitize_text_field( $p['service_method'] ?? '' );
				$data['acceptance_of_service'] = self::sanitize_yes_no( $p['acceptance_of_service'] ?? 'no' );
				$data['date_of_service']       = self::sanitize_date( $p['date_of_service'] ?? '' );
				break;
		}

		return $data;
	}

	/**
	 * Sanitize and JSON-encode property items array.
	 *
	 * @param mixed $raw Raw input (should be array of assoc arrays).
	 * @return string JSON string.
	 */
	private static function sanitize_property_items( $raw ) {
		if ( ! is_array( $raw ) ) {
			return wp_json_encode( array() );
		}
		$items = array();
		foreach ( $raw as $item ) {
			if ( ! is_array( $item ) ) {
				continue;
			}
			$desc  = sanitize_text_field( $item['description'] ?? '' );
			$value = sanitize_text_field( $item['value'] ?? '' );
			$to    = sanitize_text_field( $item['awarded_to'] ?? '' );
			if ( $desc === '' && $value === '' ) {
				continue;
			}
			$items[] = array(
				'description' => $desc,
				'value'       => $value,
				'awarded_to'  => in_array( $to, array( 'petitioner', 'respondent' ), true ) ? $to : 'petitioner',
			);
		}
		return wp_json_encode( $items );
	}

	/**
	 * Sanitize and JSON-encode debt items array.
	 *
	 * @param mixed $raw
	 * @return string JSON string.
	 */
	private static function sanitize_debt_items( $raw ) {
		if ( ! is_array( $raw ) ) {
			return wp_json_encode( array() );
		}
		$items = array();
		foreach ( $raw as $item ) {
			if ( ! is_array( $item ) ) {
				continue;
			}
			$creditor = sanitize_text_field( $item['creditor'] ?? '' );
			$balance  = sanitize_text_field( $item['balance'] ?? '' );
			$party    = sanitize_text_field( $item['responsible_party'] ?? '' );
			if ( $creditor === '' && $balance === '' ) {
				continue;
			}
			$items[] = array(
				'creditor'          => $creditor,
				'balance'           => $balance,
				'responsible_party' => in_array( $party, array( 'petitioner', 'respondent' ), true ) ? $party : 'petitioner',
			);
		}
		return wp_json_encode( $items );
	}

	/**
	 * Validate a Y-m-d date string; return empty string if invalid.
	 *
	 * @param string $raw
	 * @return string|null
	 */
	private static function sanitize_date( $raw ) {
		$raw = sanitize_text_field( $raw );
		if ( $raw === '' ) {
			return null;
		}
		$d = DateTime::createFromFormat( 'Y-m-d', $raw );
		return ( $d && $d->format( 'Y-m-d' ) === $raw ) ? $raw : null;
	}

	/**
	 * Accept only 'yes' or 'no'.
	 *
	 * @param string $raw
	 * @return string
	 */
	private static function sanitize_yes_no( $raw ) {
		$v = strtolower( sanitize_text_field( $raw ) );
		return in_array( $v, array( 'yes', 'no' ), true ) ? $v : 'no';
	}

	/**
	 * Parse a comma-separated completed_steps string to array of ints.
	 *
	 * @param string $str
	 * @return int[]
	 */
	private static function parse_completed_steps( $str ) {
		if ( ! $str ) {
			return array();
		}
		return array_map( 'intval', array_filter( explode( ',', $str ) ) );
	}

	/**
	 * Check if the current user owns the given case.
	 *
	 * @param int $user_id
	 * @param int $case_id
	 * @return bool
	 */
	private static function user_owns_case( $user_id, $case_id ) {
		// Admins can access any case.
		if ( current_user_can( 'manage_options' ) ) {
			return true;
		}
		$case = Case_Engine_Client_Dashboard::get_case_for_user( $case_id, $user_id );
		return ! empty( $case );
	}
}
