<?php
/**
 * Questionnaire DB — schema creation, CRUD, and migration for wp_az_case_questionnaire.
 *
 * Table version history:
 *   1 — initial schema (all fields)
 *
 * @package Case_Engine
 */

defined( 'ABSPATH' ) || exit;

class Case_Engine_Questionnaire_DB {

	const TABLE_VERSION_OPTION = 'case_engine_questionnaire_db_version';
	const TABLE_VERSION        = 1;

	/**
	 * Run dbDelta to create / upgrade the table.
	 * Safe to call on every activation and on init version check.
	 */
	public static function install() {
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		global $wpdb;
		$table = $wpdb->prefix . 'az_case_questionnaire';
		$ch    = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE {$table} (
			id                    bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			user_id               bigint(20) unsigned NOT NULL DEFAULT 0,
			case_id               bigint(20) unsigned NOT NULL DEFAULT 0,

			-- Step 1: Petitioner
			petitioner_first_name varchar(100)  DEFAULT '',
			petitioner_last_name  varchar(100)  DEFAULT '',
			petitioner_address    varchar(255)  DEFAULT '',
			petitioner_city       varchar(100)  DEFAULT '',
			petitioner_state      varchar(50)   DEFAULT '',
			petitioner_zip        varchar(20)   DEFAULT '',
			petitioner_phone      varchar(50)   DEFAULT '',
			petitioner_email      varchar(255)  DEFAULT '',

			-- Step 2: Respondent
			respondent_first_name varchar(100)  DEFAULT '',
			respondent_last_name  varchar(100)  DEFAULT '',
			respondent_address    varchar(255)  DEFAULT '',
			respondent_city       varchar(100)  DEFAULT '',
			respondent_state      varchar(50)   DEFAULT '',
			respondent_zip        varchar(20)   DEFAULT '',

			-- Step 3: Marriage
			marriage_date         date          DEFAULT NULL,
			marriage_city         varchar(100)  DEFAULT '',
			marriage_state        varchar(50)   DEFAULT '',
			separation_date       date          DEFAULT NULL,

			-- Step 4: Filing Details
			county_filing         varchar(100)  DEFAULT '',
			covenant_marriage     varchar(5)    DEFAULT 'no',
			pregnancy_status      varchar(5)    DEFAULT 'no',

			-- Step 5: Property & Debt (JSON arrays)
			property_division     longtext,
			debt_division         longtext,

			-- Step 6: Name Change
			restore_former_name   varchar(5)    DEFAULT 'no',
			former_name           varchar(255)  DEFAULT '',

			-- Step 7: Service of Process
			service_method        varchar(100)  DEFAULT '',
			acceptance_of_service varchar(5)    DEFAULT 'no',
			date_of_service       date          DEFAULT NULL,

			-- Completion tracking
			current_step          tinyint(3) unsigned NOT NULL DEFAULT 1,
			completed_steps       varchar(30)   DEFAULT '',
			is_complete           tinyint(1)    NOT NULL DEFAULT 0,

			created_at            datetime      NOT NULL DEFAULT CURRENT_TIMESTAMP,
			updated_at            datetime      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

			PRIMARY KEY  (id),
			UNIQUE KEY   user_case (user_id, case_id),
			KEY          user_id (user_id),
			KEY          case_id (case_id),
			KEY          is_complete (is_complete)
		) {$ch};";

		dbDelta( $sql );
		update_option( self::TABLE_VERSION_OPTION, self::TABLE_VERSION );
	}

	/**
	 * Run install if table version is behind.
	 */
	public static function maybe_upgrade() {
		if ( (int) get_option( self::TABLE_VERSION_OPTION, 0 ) < self::TABLE_VERSION ) {
			self::install();
		}
	}

	/**
	 * Get the table name.
	 *
	 * @return string
	 */
	public static function table() {
		global $wpdb;
		return $wpdb->prefix . 'az_case_questionnaire';
	}

	/**
	 * Fetch a questionnaire row by user_id + case_id.
	 *
	 * @param int $user_id
	 * @param int $case_id
	 * @return array|null
	 */
	public static function get( $user_id, $case_id ) {
		global $wpdb;
		$row = $wpdb->get_row( $wpdb->prepare(
			'SELECT * FROM ' . self::table() . ' WHERE user_id = %d AND case_id = %d LIMIT 1',
			(int) $user_id,
			(int) $case_id
		), ARRAY_A );
		return $row ?: null;
	}

	/**
	 * Fetch a questionnaire row by its primary key.
	 *
	 * @param int $id
	 * @return array|null
	 */
	public static function get_by_id( $id ) {
		global $wpdb;
		$row = $wpdb->get_row( $wpdb->prepare(
			'SELECT * FROM ' . self::table() . ' WHERE id = %d LIMIT 1',
			(int) $id
		), ARRAY_A );
		return $row ?: null;
	}

	/**
	 * Insert or update questionnaire data for a given user+case.
	 * Returns the record id on success, false on failure.
	 *
	 * @param int   $user_id
	 * @param int   $case_id
	 * @param array $data    Column => value pairs (sanitized by caller).
	 * @return int|false
	 */
	public static function upsert( $user_id, $case_id, array $data ) {
		global $wpdb;
		$user_id = (int) $user_id;
		$case_id = (int) $case_id;
		$table   = self::table();

		$existing = self::get( $user_id, $case_id );

		// Protect these — never overwrite from caller-supplied data directly.
		unset( $data['id'], $data['user_id'], $data['case_id'], $data['created_at'] );

		$data['updated_at'] = current_time( 'mysql' );

		if ( $existing ) {
			$result = $wpdb->update(
				$table,
				$data,
				array( 'user_id' => $user_id, 'case_id' => $case_id ),
				null,    // formats derived automatically
				array( '%d', '%d' )
			);
			return ( $result !== false ) ? (int) $existing['id'] : false;
		}

		$data['user_id']    = $user_id;
		$data['case_id']    = $case_id;
		$data['created_at'] = current_time( 'mysql' );
		$result = $wpdb->insert( $table, $data );
		return ( $result !== false ) ? (int) $wpdb->insert_id : false;
	}

	/**
	 * Return all scalar columns allowed for mass-assignment (excludes JSON columns — handled separately).
	 *
	 * @return string[]
	 */
	public static function scalar_columns() {
		return array(
			'petitioner_first_name',
			'petitioner_last_name',
			'petitioner_address',
			'petitioner_city',
			'petitioner_state',
			'petitioner_zip',
			'petitioner_phone',
			'petitioner_email',
			'respondent_first_name',
			'respondent_last_name',
			'respondent_address',
			'respondent_city',
			'respondent_state',
			'respondent_zip',
			'marriage_city',
			'marriage_state',
			'county_filing',
			'covenant_marriage',
			'pregnancy_status',
			'restore_former_name',
			'former_name',
			'service_method',
			'acceptance_of_service',
			'current_step',
			'completed_steps',
			'is_complete',
		);
	}

	/**
	 * Date columns (stored as DATE, accept Y-m-d strings).
	 *
	 * @return string[]
	 */
	public static function date_columns() {
		return array( 'marriage_date', 'separation_date', 'date_of_service' );
	}

	/**
	 * JSON array columns.
	 *
	 * @return string[]
	 */
	public static function json_columns() {
		return array( 'property_division', 'debt_division' );
	}
}
