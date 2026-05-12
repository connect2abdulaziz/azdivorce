<?php
/**
 * Questionnaire → PDF AcroForm field mapping.
 *
 * Each entry maps a database column name to the corresponding AcroForm field
 * name used in the Arizona Petition for Dissolution and Default Decree PDFs.
 * Add new PDF forms by extending $form_maps with a new key.
 *
 * Usage:
 *   $map  = Case_Engine_Questionnaire_Field_Map::get_map();
 *   $data = Case_Engine_Questionnaire_DB::get( $user_id, $case_id );
 *   $pdf_fields = Case_Engine_Questionnaire_Field_Map::resolve( $data );
 *
 * @package Case_Engine
 */

defined( 'ABSPATH' ) || exit;

class Case_Engine_Questionnaire_Field_Map {

	/**
	 * Master mapping: db_column => AcroForm field name.
	 * Shared across all supported forms unless overridden in $form_overrides.
	 *
	 * @var array<string,string>
	 */
	private static $map = array(

		// ── Petitioner ───────────────────────────────────────────────
		'petitioner_first_name' => 'PetitionerFirstName',
		'petitioner_last_name'  => 'PetitionerLastName',
		'petitioner_address'    => 'PetitionerAddress',
		'petitioner_city'       => 'PetitionerCity',
		'petitioner_state'      => 'PetitionerState',
		'petitioner_zip'        => 'PetitionerZip',
		'petitioner_phone'      => 'PetitionerPhone',
		'petitioner_email'      => 'PetitionerEmail',

		// ── Respondent ───────────────────────────────────────────────
		'respondent_first_name' => 'RespondentFirstName',
		'respondent_last_name'  => 'RespondentLastName',
		'respondent_address'    => 'RespondentAddress',
		'respondent_city'       => 'RespondentCity',
		'respondent_state'      => 'RespondentState',
		'respondent_zip'        => 'RespondentZip',

		// ── Marriage ─────────────────────────────────────────────────
		'marriage_date'         => 'MarriageDate',
		'marriage_city'         => 'MarriageCity',
		'marriage_state'        => 'MarriageState',
		'separation_date'       => 'SeparationDate',

		// ── Filing Details ───────────────────────────────────────────
		'county_filing'         => 'CountyFiling',
		'covenant_marriage'     => 'CovenantMarriage',
		'pregnancy_status'      => 'PregnancyStatus',

		// ── Name Change ──────────────────────────────────────────────
		'restore_former_name'   => 'RestoreFormerName',
		'former_name'           => 'FormerName',

		// ── Service of Process ───────────────────────────────────────
		'service_method'        => 'ServiceMethod',
		'acceptance_of_service' => 'AcceptanceOfService',
		'date_of_service'       => 'DateOfService',

		// ── Derived / computed fields (not columns; resolved in resolve()) ──
		// 'petitioner_full_name'  => 'PetitionerFullName',
		// 'respondent_full_name'  => 'RespondentFullName',
	);

	/**
	 * Per-form overrides.  Use when a PDF uses a different field name for the
	 * same data point.  Only entries that differ from $map need to be listed.
	 *
	 * @var array<string, array<string,string>>
	 */
	private static $form_overrides = array(
		'petition_for_dissolution' => array(),   // uses master map as-is
		'default_decree'           => array(
			'county_filing' => 'CountyFilingDecree',
		),
	);

	/**
	 * Return the master db→PDF field map.
	 *
	 * @return array<string,string>
	 */
	public static function get_map() {
		return self::$map;
	}

	/**
	 * Return the map for a specific form, merging overrides.
	 *
	 * @param string $form_key One of the keys in $form_overrides.
	 * @return array<string,string>
	 */
	public static function get_form_map( $form_key ) {
		$base      = self::$map;
		$overrides = isset( self::$form_overrides[ $form_key ] ) ? self::$form_overrides[ $form_key ] : array();
		return array_merge( $base, $overrides );
	}

	/**
	 * Resolve a questionnaire DB row into an array of PDF field name => value
	 * pairs ready to pass to an AcroForm filler.
	 *
	 * @param array  $row      Row from Case_Engine_Questionnaire_DB::get().
	 * @param string $form_key Optional form key to use form-specific overrides.
	 * @return array<string,string>  pdf_field_name => value
	 */
	public static function resolve( array $row, $form_key = 'petition_for_dissolution' ) {
		$map    = self::get_form_map( $form_key );
		$result = array();

		foreach ( $map as $db_col => $pdf_field ) {
			$result[ $pdf_field ] = isset( $row[ $db_col ] ) ? (string) $row[ $db_col ] : '';
		}

		// Derived: full names (concatenated for forms that use a single field).
		$result['PetitionerFullName'] = trim(
			( $row['petitioner_first_name'] ?? '' ) . ' ' . ( $row['petitioner_last_name'] ?? '' )
		);
		$result['RespondentFullName'] = trim(
			( $row['respondent_first_name'] ?? '' ) . ' ' . ( $row['respondent_last_name'] ?? '' )
		);

		// Derived: petitioner full address block.
		$result['PetitionerFullAddress'] = self::format_address(
			$row['petitioner_address']  ?? '',
			$row['petitioner_city']     ?? '',
			$row['petitioner_state']    ?? '',
			$row['petitioner_zip']      ?? ''
		);
		$result['RespondentFullAddress'] = self::format_address(
			$row['respondent_address']  ?? '',
			$row['respondent_city']     ?? '',
			$row['respondent_state']    ?? '',
			$row['respondent_zip']      ?? ''
		);

		return $result;
	}

	/**
	 * Format a mailing address into a single line.
	 *
	 * @param string $street
	 * @param string $city
	 * @param string $state
	 * @param string $zip
	 * @return string
	 */
	private static function format_address( $street, $city, $state, $zip ) {
		$parts = array_filter( array( $street ) );
		$csz   = trim( $city . ', ' . $state . ' ' . $zip, ', ' );
		if ( $csz ) {
			$parts[] = $csz;
		}
		return implode( ', ', $parts );
	}
}
