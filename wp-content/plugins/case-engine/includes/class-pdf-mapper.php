<?php
/**
 * PDF Mapper — resolves questionnaire database rows into AcroForm field arrays.
 *
 * Takes a raw DB row from wp_az_case_questionnaire plus a case row from
 * wp_az_cases and produces an associative array keyed by the exact AcroForm
 * field name, ready to pass to Case_Engine_PDF_Engine::fill_pdf().
 *
 * @package Case_Engine
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Case_Engine_PDF_Mapper {

    /**
     * Load the field-mapping definition file (cached after first load).
     *
     * @return array
     */
    private static function mapping_def(): array {
        static $def = null;
        if ( null === $def ) {
            $def = require CASE_ENGINE_PLUGIN_DIR . 'data/field-mapping.php';
        }
        return $def;
    }

    /**
     * Build the full set of computed values from a questionnaire row + case row.
     *
     * @param array $q   Questionnaire row (from wp_az_case_questionnaire).
     * @param array $c   Case row (from wp_az_cases / wp_az_intake_sessions).
     * @return array     Flat associative array of computed key => value.
     */
    public static function compute_values( array $q, array $c ): array {
        $v = [];

        // --- Basic scalars from questionnaire ---------------------------------
        $v['petitioner_first_name']   = $q['petitioner_first_name'] ?? '';
        $v['petitioner_last_name']    = $q['petitioner_last_name']  ?? '';
        $v['petitioner_address']      = $q['petitioner_address']    ?? '';
        $v['petitioner_city']         = $q['petitioner_city']       ?? '';
        $v['petitioner_state']        = $q['petitioner_state']      ?? '';
        $v['petitioner_zip']          = $q['petitioner_zip']        ?? '';
        $v['petitioner_phone']        = $q['petitioner_phone']      ?? '';
        $v['petitioner_email']        = $q['petitioner_email']      ?? '';

        $v['respondent_first_name']   = $q['respondent_first_name'] ?? '';
        $v['respondent_last_name']    = $q['respondent_last_name']  ?? '';
        $v['respondent_address']      = $q['respondent_address']    ?? '';
        $v['respondent_city']         = $q['respondent_city']       ?? '';
        $v['respondent_state']        = $q['respondent_state']      ?? '';
        $v['respondent_zip']          = $q['respondent_zip']        ?? '';

        $v['marriage_date']           = $q['marriage_date']         ?? '';
        $v['marriage_city']           = $q['marriage_city']         ?? '';
        $v['marriage_state']          = $q['marriage_state']        ?? '';
        $v['separation_date']         = $q['separation_date']       ?? '';
        $v['county_filing']           = $q['county_filing']         ?? '';
        $v['former_name']             = $q['former_name']           ?? '';
        $v['date_of_service']         = $q['date_of_service']       ?? '';
        $v['case_number']             = $c['case_number']           ?? ( $c['id'] ? 'Case #' . $c['id'] : '' );

        // --- Computed / derived values ----------------------------------------

        $v['petitioner_full_name']    = trim( $v['petitioner_first_name'] . ' ' . $v['petitioner_last_name'] );
        $v['respondent_full_name']    = trim( $v['respondent_first_name'] . ' ' . $v['respondent_last_name'] );

        $v['petitioner_city_state_zip'] = self::city_state_zip(
            $v['petitioner_city'], $v['petitioner_state'], $v['petitioner_zip']
        );
        $v['respondent_city_state_zip'] = self::city_state_zip(
            $v['respondent_city'], $v['respondent_state'], $v['respondent_zip']
        );
        $v['marriage_city_state'] = self::city_state(
            $v['marriage_city'], $v['marriage_state']
        );

        // Current (married) name parts for name-change section
        $v['married_name_first']  = $v['petitioner_first_name'];
        $v['married_name_middle'] = '';
        $v['married_name_last']   = $v['petitioner_last_name'];

        // Restore-to name parts (parse from former_name field)
        $name_parts               = self::split_name( $v['former_name'] );
        $v['restore_name_first']  = $name_parts[0];
        $v['restore_name_middle'] = $name_parts[1];
        $v['restore_name_last']   = $name_parts[2];

        // --- Repeatable: property items ---------------------------------------
        $property_items = [];
        if ( ! empty( $q['property_division'] ) ) {
            $decoded = json_decode( $q['property_division'], true );
            if ( is_array( $decoded ) ) {
                $property_items = $decoded;
            }
        }
        for ( $i = 1; $i <= 10; $i++ ) {
            $item = $property_items[ $i - 1 ] ?? [];
            $desc = sanitize_text_field( $item['description'] ?? '' );
            $val  = sanitize_text_field( $item['value']       ?? '' );
            $awarded = strtolower( $item['awarded_to'] ?? '' );

            $v[ "asset_description_{$i}" ] = $desc ? "$desc (Value: $val)" : '';
            $v[ "asset_value_{$i}" ]        = $val;
            $v[ "asset_awarded_a_{$i}" ]    = ( 'petitioner' === $awarded || 'party a' === $awarded ) ? 'Yes' : '';
            $v[ "asset_awarded_b_{$i}" ]    = ( 'respondent' === $awarded || 'party b' === $awarded ) ? 'Yes' : '';
        }

        // --- Repeatable: debt items ------------------------------------------
        $debt_items = [];
        if ( ! empty( $q['debt_division'] ) ) {
            $decoded = json_decode( $q['debt_division'], true );
            if ( is_array( $decoded ) ) {
                $debt_items = $decoded;
            }
        }
        for ( $i = 1; $i <= 10; $i++ ) {
            $item      = $debt_items[ $i - 1 ] ?? [];
            $creditor  = sanitize_text_field( $item['creditor']  ?? '' );
            $balance   = sanitize_text_field( $item['balance']   ?? '' );
            $party     = strtolower( $item['responsible_party'] ?? '' );

            $v[ "debt_description_{$i}" ]   = $creditor ? "$creditor ($balance)" : '';
            $v[ "debt_creditor_{$i}" ]       = $creditor;
            $v[ "debt_balance_{$i}" ]        = $balance;
            $v[ "debt_responsible_a_{$i}" ]  = ( 'petitioner' === $party || 'party a' === $party ) ? 'Yes' : '';
            $v[ "debt_responsible_b_{$i}" ]  = ( 'respondent' === $party || 'party b' === $party ) ? 'Yes' : '';
        }

        // --- Boolean / radio helpers ------------------------------------------
        $v['is_restore_name']        = ! empty( $q['restore_former_name'] ) && 'yes' === strtolower( $q['restore_former_name'] );
        $v['has_pregnancy']          = ! empty( $q['pregnancy_status'] )    && 'yes' === strtolower( $q['pregnancy_status'] );
        $v['is_covenant_marriage']   = ! empty( $q['covenant_marriage'] )   && 'yes' === strtolower( $q['covenant_marriage'] );

        return $v;
    }

    /**
     * Resolve computed values into an AcroForm-ready field array for a given
     * form key (one of the keys in data/field-mapping.php).
     *
     * @param string $form_key   Key matching an entry in field-mapping.php.
     * @param array  $q          Questionnaire row.
     * @param array  $c          Case row.
     * @return array             [ 'AcroForm Field Name' => 'value', ... ]
     */
    public static function resolve( string $form_key, array $q, array $c ): array {
        $def    = self::mapping_def();
        $map    = $def[ $form_key ] ?? [];
        $values = self::compute_values( $q, $c );
        $output = [];

        foreach ( $map as $computed_key => $acroform_fields ) {
            if ( ! isset( $values[ $computed_key ] ) ) {
                continue;
            }
            $new = (string) $values[ $computed_key ];

            // A mapping value can be a STRING (single field) or an ARRAY (same
            // data fills multiple AcroForm fields within the same PDF — e.g.,
            // petitioner name in the header block, the caption, and the body).
            $fields = is_array( $acroform_fields ) ? $acroform_fields : [ $acroform_fields ];

            foreach ( $fields as $field_name ) {
                $cur = $output[ $field_name ] ?? '';
                // Only write a non-empty value; never overwrite a filled slot with ''
                if ( '' === $cur || '' !== $new ) {
                    $output[ $field_name ] = $new;
                }
            }
        }

        // Remove empty entries — pdftk treats missing keys as blank fields
        foreach ( $output as $k => $v ) {
            if ( '' === $v ) {
                unset( $output[ $k ] );
            }
        }

        return $output;
    }

    /**
     * Return ALL form keys that belong to a given packet type and step.
     *
     * @param string   $packet_type  'wc' or 'woc'.
     * @param int|null $step         1–4, or null for all steps.
     * @return array                  List of form keys.
     */
    public static function forms_for_packet( string $packet_type, ?int $step = null ): array {
        $type = strtolower( $packet_type );

        $forms = array(
            'woc' => array(
                1 => array( 'woc_petition', 'woc_summons', 'woc_sensitive_data', 'notice_regarding_creditors', 'wc_preliminary_injunction' ),
                2 => array( 'woc_acceptance_of_service', 'woc_affidavit_service_signature', 'woc_affidavit_service_alt' ),
                3 => array( 'woc_default_application', 'woc_default_spousal_maintenance' ),
                4 => array( 'woc_divorce_decree', 'woc_motion_default_decree', 'woc_consent_decree' ),
            ),
            'wc'  => array(
                1 => array( 'wc_petition', 'wc_summons', 'wc_sensitive_data', 'notice_regarding_creditors', 'wc_preliminary_injunction' ),
                2 => array( 'woc_acceptance_of_service', 'woc_affidavit_service_signature', 'woc_affidavit_service_alt' ),
                3 => array( 'wc_default_application', 'woc_default_spousal_maintenance' ),
                4 => array( 'woc_divorce_decree', 'woc_motion_default_decree', 'woc_consent_decree' ),
            ),
        );

        if ( ! isset( $forms[ $type ] ) ) {
            return [];
        }

        if ( null === $step ) {
            // Flatten all steps
            $all = [];
            foreach ( $forms[ $type ] as $step_forms ) {
                $all = array_merge( $all, $step_forms );
            }
            return $all;
        }

        return $forms[ $type ][ $step ] ?? [];
    }

    // -------------------------------------------------------------------------
    // Private helpers
    // -------------------------------------------------------------------------

    private static function city_state_zip( string $city, string $state, string $zip ): string {
        $parts = array_filter( [ $city, $state, $zip ] );
        if ( empty( $parts ) ) {
            return '';
        }
        return $city . ( $state ? ', ' . $state : '' ) . ( $zip ? ' ' . $zip : '' );
    }

    private static function city_state( string $city, string $state ): string {
        $parts = array_filter( [ $city, $state ] );
        return implode( ', ', $parts );
    }

    /**
     * Split a full name string into [first, middle, last].
     * Handles: "First Last", "First Middle Last".
     */
    private static function split_name( string $name ): array {
        $name  = trim( $name );
        $words = preg_split( '/\s+/', $name, -1, PREG_SPLIT_NO_EMPTY );
        $count = count( $words );
        if ( 0 === $count ) {
            return [ '', '', '' ];
        }
        if ( 1 === $count ) {
            return [ $words[0], '', '' ];
        }
        if ( 2 === $count ) {
            return [ $words[0], '', $words[1] ];
        }
        // 3+ words: first, everything in middle, last
        $first  = array_shift( $words );
        $last   = array_pop( $words );
        $middle = implode( ' ', $words );
        return [ $first, $middle, $last ];
    }
}
