<?php
/**
 * Packet Generator — orchestrates multi-document generation for a divorce case.
 *
 * Decides between WC (With Children) and WOC (Without Children) packet sets,
 * maps each form key to the correct PDF template in the /documents/ folder,
 * invokes the PDF Engine for each document, and stores the results.
 *
 * @package Case_Engine
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Case_Engine_Packet_Generator {

    /**
     * Maps form keys to their PDF template path relative to CASE_ENGINE_PLUGIN_DIR . 'documents/'.
     *
     * Key   = form key used in Case_Engine_PDF_Mapper and data/field-mapping.php.
     * Value = [ packet_type => relative_path_from_documents_dir ]
     */
    const TEMPLATE_MAP = array(
        // ---- WOC templates --------------------------------------------------
        'woc_petition' => array(
            'woc' => 'Divorce WOC/1 Petition Divorce WO 1st step/Petition for Dissolution of Non-Covenant Marriage without Minor Children  drda10fz.pdf',
        ),
        'woc_summons' => array(
            'woc' => 'Divorce WOC/1 Petition Divorce WO 1st step/Summons dr11fz.pdf',
        ),
        'woc_sensitive_data' => array(
            'woc' => 'Divorce WOC/1 Petition Divorce WO 1st step/Family Department Sensitive Data Cover Sheet  drsds10f-annz.pdf',
        ),
        'notice_regarding_creditors' => array(
            'woc' => 'Divorce WOC/1 Petition Divorce WO 1st step/Notice Regarding Creditors dr16fz.pdf',
            'wc'  => 'Divorce WC/1 Divorce WC 1st Step/Notice Regarding Creditors dr16fz.pdf',
        ),
        'woc_preliminary_injunction' => array(
            'woc' => 'Divorce WOC/1 Petition Divorce WO 1st step/Preliminary Injunction dr14fz.pdf',
            'wc'  => 'Divorce WC/1 Divorce WC 1st Step/Preliminary Injunction dr14fz.pdf',
        ),
        'woc_acceptance_of_service' => array(
            'woc' => 'Divorce WOC/2 Serving The Spouse Step 2/Acceptance of service  dr22fz.pdf',
            'wc'  => 'Divorce WC/2 Serving The Spouse Step 2/Acceptance of service  dr22fz.pdf',
        ),
        'woc_affidavit_service_signature' => array(
            'woc' => 'Divorce WOC/2 Serving The Spouse Step 2/Affidavit of service with signature confirmation dr24fz.pdf',
            'wc'  => 'Divorce WC/2 Serving The Spouse Step 2/Affidavit of service with signature confirmation dr24fz.pdf',
        ),
        'woc_affidavit_service_alt' => array(
            'woc' => 'Divorce WOC/2 Serving The Spouse Step 2/Affidavit of Service by Alternative Means dr31fz.pdf',
            'wc'  => 'Divorce WC/2 Serving The Spouse Step 2/Affidavit of Service by Alternative Means dr31fz.pdf',
        ),
        'woc_default_application' => array(
            'woc' => 'Divorce WOC/3 Default Application Step 3/Application and Affidavit for Entry of Default drd61fz.pdf',
            'wc'  => 'Divorce WC/3 Default Application Step 3/Application and Affidavit for Entry of Default drd61fz.pdf',
        ),
        'woc_default_spousal_maintenance' => array(
            'woc' => 'Divorce WOC/3 Default Application Step 3/Default Information for Spousal Maintenance  drd62fz.pdf',
            'wc'  => 'Divorce WC/3 Default Application Step 3/Default Information for Spousal Maintenance  drd62fz.pdf',
        ),
        'woc_divorce_decree' => array(
            'woc' => 'Divorce WOC/4 Default Hearing Step 4/Divorce Decree for Non-Covenant Marriage - No Minor Children  drda81fz.pdf',
        ),
        'woc_motion_default_decree' => array(
            'woc' => 'Divorce WOC/4 Default Hearing Step 4/Motion and Affidavit for Default Decree without Hearing  drd68fz.pdf',
            'wc'  => 'Divorce WC/4a Default Hearing 4th Step/Motion and Affidavit for Default Decree without Hearing drd68fz.pdf',
        ),
        'woc_consent_decree' => array(
            'woc' => 'Divorce WOC/4b Consent Decree Step 4 Optional/Consent Decree dra71fz.pdf',
        ),
        // ---- WC-specific templates ------------------------------------------
        'wc_petition' => array(
            'wc' => 'Divorce WC/1 Divorce WC 1st Step/PEtition Divorce WC drdc15fz.pdf',
        ),
        'wc_summons' => array(
            'wc' => 'Divorce WC/1 Divorce WC 1st Step/Summons dr11fz.pdf',
        ),
        'wc_sensitive_data' => array(
            'wc' => 'Divorce WC/1 Divorce WC 1st Step/Sensitive Data Cover Sheet WC drsds10f-cz.pdf',
        ),
        'wc_preliminary_injunction' => array(
            'wc' => 'Divorce WC/1 Divorce WC 1st Step/Preliminary Injunction dr14fz.pdf',
        ),
        'wc_default_application' => array(
            'wc' => 'Divorce WC/3 Default Application Step 3/Application and Affidavit for Entry of Default drd61fz.pdf',
        ),
    );

    /**
     * Step → form keys. Used to generate individual step packets.
     */
    const STEP_FORMS = array(
        'woc' => array(
            1 => array( 'woc_petition', 'woc_summons', 'woc_sensitive_data', 'notice_regarding_creditors', 'woc_preliminary_injunction' ),
            2 => array( 'woc_acceptance_of_service', 'woc_affidavit_service_signature', 'woc_affidavit_service_alt' ),
            3 => array( 'woc_default_application', 'woc_default_spousal_maintenance' ),
            4 => array( 'woc_divorce_decree', 'woc_motion_default_decree', 'woc_consent_decree' ),
        ),
        'wc'  => array(
            1 => array( 'wc_petition', 'wc_summons', 'wc_sensitive_data', 'notice_regarding_creditors', 'wc_preliminary_injunction' ),
            2 => array( 'woc_acceptance_of_service', 'woc_affidavit_service_signature', 'woc_affidavit_service_alt' ),
            3 => array( 'wc_default_application', 'woc_default_spousal_maintenance' ),
            4 => array( 'woc_motion_default_decree' ),
        ),
    );

    /**
     * Human-readable step labels.
     */
    const STEP_LABELS = array(
        1 => 'Initial Filing',
        2 => 'Serving the Spouse',
        3 => 'Default Application',
        4 => 'Default Hearing / Decree',
    );

    // -------------------------------------------------------------------------
    // Public API
    // -------------------------------------------------------------------------

    /**
     * Generate documents for a single step.
     *
     * @param int $case_id   Case ID.
     * @param int $step      1–4.
     * @return array         Result: [ 'generated' => [...], 'errors' => [...] ]
     */
    public static function generate_step( int $case_id, int $step ): array {
        $data = self::load_case_data( $case_id );
        if ( is_wp_error( $data ) ) {
            return [ 'generated' => [], 'errors' => [ $data->get_error_message() ] ];
        }

        [ $case_row, $q_row, $packet_type ] = $data;
        $form_keys = self::STEP_FORMS[ $packet_type ][ $step ] ?? [];

        if ( empty( $form_keys ) ) {
            return [
                'generated' => [],
                'errors'    => [ "No forms defined for step $step in $packet_type packet." ],
            ];
        }

        return self::run_generation( $case_id, $packet_type, $form_keys, $step, $case_row, $q_row );
    }

    /**
     * Generate the complete packet (all steps).
     *
     * @param int $case_id
     * @return array  Result: [ 'generated' => [...], 'errors' => [...] ]
     */
    public static function generate_full_packet( int $case_id ): array {
        $data = self::load_case_data( $case_id );
        if ( is_wp_error( $data ) ) {
            return [ 'generated' => [], 'errors' => [ $data->get_error_message() ] ];
        }

        [ $case_row, $q_row, $packet_type ] = $data;
        $all_generated = [];
        $all_errors    = [];

        foreach ( self::STEP_FORMS[ $packet_type ] as $step => $form_keys ) {
            $result        = self::run_generation( $case_id, $packet_type, $form_keys, $step, $case_row, $q_row );
            $all_generated = array_merge( $all_generated, $result['generated'] );
            $all_errors    = array_merge( $all_errors, $result['errors'] );
        }

        return [ 'generated' => $all_generated, 'errors' => $all_errors ];
    }

    /**
     * Build and serve a ZIP download of all generated PDFs for a case.
     *
     * @param int $case_id
     * @param int $user_id
     */
    public static function download_zip( int $case_id, int $user_id ): void {
        // Validate ownership
        if ( ! self::user_owns_case( $user_id, $case_id ) && ! current_user_can( 'manage_options' ) ) {
            wp_die( 'Access denied.', 403 );
        }

        $zip = Case_Engine_PDF_Engine::create_zip( $case_id );
        if ( is_wp_error( $zip ) ) {
            wp_die( esc_html( $zip->get_error_message() ) );
        }

        $filename = 'divorce_packet_case_' . $case_id . '.zip';
        header( 'Content-Type: application/zip' );
        header( 'Content-Disposition: attachment; filename="' . $filename . '"' );
        header( 'Content-Length: ' . filesize( $zip ) );
        header( 'Cache-Control: private' );
        readfile( $zip );
        exit;
    }

    /**
     * Determine packet type (wc/woc) from a case row.
     *
     * @param array $case_row
     * @return string  'wc' or 'woc'
     */
    public static function determine_packet_type( array $case_row ): string {
        $has_children = strtolower( trim( $case_row['has_children'] ?? 'no' ) );
        return ( 'yes' === $has_children || '1' === $has_children ) ? 'wc' : 'woc';
    }

    /**
     * Return the list of files already generated for a case.
     *
     * @param int $case_id
     * @return array
     */
    public static function list_documents( int $case_id ): array {
        return Case_Engine_PDF_Engine::list_generated_files( $case_id );
    }

    // -------------------------------------------------------------------------
    // Internal helpers
    // -------------------------------------------------------------------------

    /**
     * Load and validate all data needed for generation.
     *
     * @param  int   $case_id
     * @return array|\WP_Error  [ $case_row, $q_row, $packet_type ]
     */
    private static function load_case_data( int $case_id ) {
        global $wpdb;

        if ( ! Case_Engine_PDF_Engine::pdftk_available() ) {
            return new \WP_Error( 'pdftk_missing', 'pdftk is not installed or not accessible. Please install pdftk to generate documents.' );
        }

        // Load case row
        $case_row = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT c.*, s.user_id
                 FROM {$wpdb->prefix}az_cases c
                 LEFT JOIN {$wpdb->prefix}az_intake_sessions s ON s.id = c.intake_session_id
                 WHERE c.id = %d LIMIT 1",
                $case_id
            ),
            ARRAY_A
        );

        if ( ! $case_row ) {
            return new \WP_Error( 'case_not_found', "Case #$case_id not found." );
        }

        // Load questionnaire row
        $q_row = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}az_case_questionnaire WHERE case_id = %d LIMIT 1",
                $case_id
            ),
            ARRAY_A
        );

        if ( ! $q_row ) {
            return new \WP_Error( 'questionnaire_not_found', "No questionnaire data found for case #$case_id. Please complete the questionnaire first." );
        }

        $packet_type = self::determine_packet_type( $case_row );
        return [ $case_row, $q_row, $packet_type ];
    }

    /**
     * Run the generation loop for a set of form keys.
     *
     * @param int    $case_id
     * @param string $packet_type 'wc' or 'woc'
     * @param array  $form_keys
     * @param int    $step
     * @param array  $case_row
     * @param array  $q_row
     * @return array [ 'generated' => [...], 'errors' => [...] ]
     */
    private static function run_generation(
        int    $case_id,
        string $packet_type,
        array  $form_keys,
        int    $step,
        array  $case_row,
        array  $q_row
    ): array {
        $generated = [];
        $errors    = [];
        $output_dir = Case_Engine_PDF_Engine::case_output_dir( $case_id );
        $docs_dir   = trailingslashit( CASE_ENGINE_PLUGIN_DIR . 'documents' );

        foreach ( $form_keys as $form_key ) {
            // Find template path
            $template_rel = self::resolve_template_path( $form_key, $packet_type );
            if ( null === $template_rel ) {
                // Template not applicable to this packet type
                continue;
            }

            $template_path = $docs_dir . $template_rel;
            if ( ! file_exists( $template_path ) ) {
                $errors[] = "Template not found: $template_rel";
                continue;
            }

            // Resolve AcroForm field values
            $fields = Case_Engine_PDF_Mapper::resolve( $form_key, $q_row, $case_row );

            // Build safe output filename
            $safe_name   = sanitize_file_name( preg_replace( '/[^a-zA-Z0-9 _\-]/', '', basename( $template_path, '.pdf' ) ) );
            $output_name = "step{$step}_" . str_replace( ' ', '_', $safe_name ) . '.pdf';
            $output_path = $output_dir . $output_name;

            $result = Case_Engine_PDF_Engine::fill_pdf( $template_path, $fields, $output_path );

            if ( is_wp_error( $result ) ) {
                $errors[] = sprintf( '%s: %s', basename( $template_path ), $result->get_error_message() );
            } else {
                $generated[] = array(
                    'form_key'    => $form_key,
                    'filename'    => $output_name,
                    'step'        => $step,
                    'template'    => basename( $template_path ),
                    'output_path' => $output_path,
                );
            }
        }

        // Log the generation event
        if ( method_exists( 'Case_Engine_Case_Factory', 'audit_log' ) ) {
            Case_Engine_Case_Factory::audit_log(
                $case_row['user_id'] ?? 0,
                $case_id,
                'documents_generated',
                sprintf( 'Step %d: %d files generated, %d errors.', $step, count( $generated ), count( $errors ) )
            );
        }

        return compact( 'generated', 'errors' );
    }

    /**
     * Resolve the relative template path for a form_key + packet_type.
     *
     * Returns null if the form does not apply to this packet type.
     */
    private static function resolve_template_path( string $form_key, string $packet_type ): ?string {
        $map = self::TEMPLATE_MAP[ $form_key ] ?? [];
        // Try exact packet type first, then fall back to opposite for shared forms
        if ( isset( $map[ $packet_type ] ) ) {
            return $map[ $packet_type ];
        }
        // If a form has only one packet entry and we asked for the other, skip it
        return null;
    }

    /**
     * Check whether the given user owns the given case.
     */
    private static function user_owns_case( int $user_id, int $case_id ): bool {
        global $wpdb;
        $uid = $wpdb->get_var( $wpdb->prepare(
            "SELECT s.user_id
             FROM {$wpdb->prefix}az_cases c
             LEFT JOIN {$wpdb->prefix}az_intake_sessions s ON s.id = c.intake_session_id
             WHERE c.id = %d LIMIT 1",
            $case_id
        ) );
        return (int) $uid === $user_id;
    }
}
