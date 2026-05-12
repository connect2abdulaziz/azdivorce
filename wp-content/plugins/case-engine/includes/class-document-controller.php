<?php
/**
 * Document Controller — WordPress hooks, AJAX handlers, and dashboard integration
 * for the PDF automation engine.
 *
 * AJAX actions registered:
 *   wp_ajax_az_generate_step       — Generate one step packet.
 *   wp_ajax_az_generate_full       — Generate the full packet.
 *   wp_ajax_az_doc_download        — Securely serve a single PDF.
 *   wp_ajax_az_doc_download_zip    — Build and serve ZIP.
 *   wp_ajax_az_doc_list            — Return JSON list of generated docs.
 *
 * Dashboard integration:
 *   - Injects a "Your Divorce Documents" card into the case detail view via
 *     the `az_client_dashboard_case_detail_after` filter (added by class-client-dashboard.php).
 *   - Enqueues CSS/JS assets.
 *
 * @package Case_Engine
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Case_Engine_Document_Controller {

    public static function register(): void {
        // AJAX handlers (logged-in users only)
        add_action( 'wp_ajax_az_generate_step',       [ __CLASS__, 'handle_generate_step' ] );
        add_action( 'wp_ajax_az_generate_full',       [ __CLASS__, 'handle_generate_full' ] );
        add_action( 'wp_ajax_az_doc_download',        [ __CLASS__, 'handle_doc_download' ] );
        add_action( 'wp_ajax_az_doc_download_zip',    [ __CLASS__, 'handle_download_zip' ] );
        add_action( 'wp_ajax_az_doc_list',            [ __CLASS__, 'handle_doc_list' ] );

        // Dashboard injection
        add_filter( 'az_client_dashboard_case_detail_after', [ __CLASS__, 'inject_documents_card' ], 20, 2 );

        // Assets
        add_action( 'wp_enqueue_scripts', [ __CLASS__, 'enqueue_assets' ] );
    }

    // -------------------------------------------------------------------------
    // Asset loading
    // -------------------------------------------------------------------------

    public static function enqueue_assets(): void {
        // Load on the client dashboard page (same condition as the questionnaire controller)
        // and on any page explicitly containing our shortcode.
        $on_dashboard = is_page( 'client-dashboard' );
        global $post;
        $has_shortcode = $post && has_shortcode( $post->post_content, 'az_divorce_questionnaire' );

        if ( ! $on_dashboard && ! $has_shortcode ) {
            return;
        }

        wp_enqueue_style(
            'az-documents',
            CASE_ENGINE_PLUGIN_URL . 'assets/documents.css',
            [],
            CASE_ENGINE_VERSION
        );

        wp_enqueue_script(
            'az-documents',
            CASE_ENGINE_PLUGIN_URL . 'assets/documents.js',
            [ 'jquery' ],
            CASE_ENGINE_VERSION,
            true
        );

        wp_localize_script( 'az-documents', 'azDocs', [
            'ajax_url' => admin_url( 'admin-ajax.php' ),
            'nonce'    => wp_create_nonce( 'az_documents' ),
        ] );
    }

    // -------------------------------------------------------------------------
    // AJAX: Generate a single step
    // -------------------------------------------------------------------------

    public static function handle_generate_step(): void {
        check_ajax_referer( 'az_documents', '_wpnonce' );

        $user_id = get_current_user_id();
        if ( ! $user_id ) {
            wp_send_json_error( [ 'message' => 'You must be logged in.' ], 401 );
        }

        $case_id = (int) ( $_POST['case_id'] ?? 0 );
        $step    = (int) ( $_POST['step']    ?? 0 );

        if ( ! $case_id || $step < 1 || $step > 4 ) {
            wp_send_json_error( [ 'message' => 'Invalid case or step.' ] );
        }

        if ( ! self::user_can_access_case( $user_id, $case_id ) ) {
            wp_send_json_error( [ 'message' => 'Access denied.' ], 403 );
        }

        try {
            $result = Case_Engine_Packet_Generator::generate_step( $case_id, $step );
            self::send_generation_response( $result, $case_id );
        } catch ( \Throwable $e ) {
            wp_send_json_error( [ 'message' => 'Server error: ' . $e->getMessage() ] );
        }
    }

    // -------------------------------------------------------------------------
    // AJAX: Generate full packet
    // -------------------------------------------------------------------------

    public static function handle_generate_full(): void {
        check_ajax_referer( 'az_documents', '_wpnonce' );

        $user_id = get_current_user_id();
        if ( ! $user_id ) {
            wp_send_json_error( [ 'message' => 'You must be logged in.' ], 401 );
        }

        $case_id = (int) ( $_POST['case_id'] ?? 0 );
        if ( ! $case_id ) {
            wp_send_json_error( [ 'message' => 'Invalid case.' ] );
        }

        if ( ! self::user_can_access_case( $user_id, $case_id ) ) {
            wp_send_json_error( [ 'message' => 'Access denied.' ], 403 );
        }

        try {
            $result = Case_Engine_Packet_Generator::generate_full_packet( $case_id );
            self::send_generation_response( $result, $case_id );
        } catch ( \Throwable $e ) {
            wp_send_json_error( [ 'message' => 'Server error: ' . $e->getMessage() ] );
        }
    }

    // -------------------------------------------------------------------------
    // AJAX: List documents
    // -------------------------------------------------------------------------

    public static function handle_doc_list(): void {
        check_ajax_referer( 'az_documents', '_wpnonce' );

        $user_id = get_current_user_id();
        $case_id = (int) ( $_GET['case_id'] ?? $_POST['case_id'] ?? 0 );

        if ( ! $user_id || ! $case_id ) {
            wp_send_json_error( [ 'message' => 'Invalid request.' ] );
        }

        if ( ! self::user_can_access_case( $user_id, $case_id ) ) {
            wp_send_json_error( [ 'message' => 'Access denied.' ], 403 );
        }

        $files = Case_Engine_Packet_Generator::list_documents( $case_id );
        wp_send_json_success( [
            'files' => $files,
            'count' => count( $files ),
        ] );
    }

    // -------------------------------------------------------------------------
    // AJAX: Download a single PDF
    // -------------------------------------------------------------------------

    public static function handle_doc_download(): void {
        $case_id = (int) ( $_GET['case_id'] ?? 0 );
        // Use wp_basename only — sanitize_file_name() alters the filename and
        // breaks the match against the file on disk.
        $filename = wp_basename( wp_unslash( $_GET['file'] ?? '' ) );
        $user_id  = get_current_user_id();

        if ( ! check_ajax_referer( 'az_doc_download_' . $case_id, '_wpnonce', false ) ) {
            wp_die( 'Security check failed.', 403 );
        }

        if ( ! $user_id || ! $case_id || ! $filename ) {
            wp_die( 'Invalid request.', 400 );
        }

        Case_Engine_PDF_Engine::serve_download( $case_id, $user_id, $filename );
    }

    // -------------------------------------------------------------------------
    // AJAX: Download ZIP
    // -------------------------------------------------------------------------

    public static function handle_download_zip(): void {
        $case_id = (int) ( $_GET['case_id'] ?? $_POST['case_id'] ?? 0 );
        $user_id = get_current_user_id();

        if ( ! check_ajax_referer( 'az_documents', '_wpnonce', false ) ) {
            wp_die( 'Security check failed.', 403 );
        }

        if ( ! $user_id || ! $case_id ) {
            wp_die( 'Invalid request.', 400 );
        }

        Case_Engine_Packet_Generator::download_zip( $case_id, $user_id );
    }

    // -------------------------------------------------------------------------
    // Dashboard injection: "Your Divorce Documents" card
    // -------------------------------------------------------------------------

    public static function inject_documents_card( string $html, array $context ): string {
        $case_id = (int) ( $context['case_id'] ?? 0 );

        if ( ! $case_id ) {
            return $html;
        }

        // Hide entirely for unpaid cases (payment gate).
        $case = $context['case'] ?? array();
        if ( ! empty( $case ) && ! Case_Engine_Client_Dashboard::case_can_access_documents( $case ) ) {
            return $html;
        }

        // Determine packet type label.
        $case_row = self::get_case_row( $case_id );
        if ( ! $case_row ) {
            return $html;
        }

        // Also enforce payment gate via DB value (defensive; context case may be stale).
        if ( ! Case_Engine_Client_Dashboard::case_can_access_documents( $case_row ) ) {
            return $html;
        }

        $packet_type  = Case_Engine_Packet_Generator::determine_packet_type( $case_row );
        $packet_label = 'wc' === $packet_type ? 'With Children' : 'Without Children';

        // Check questionnaire completion — prefer context value passed by the dashboard.
        $q_status = $context['questionnaire_status'] ?? null;
        if ( $q_status === null ) {
            $q_complete = self::is_questionnaire_complete( $case_id );
        } else {
            $q_complete = ( $q_status === 'completed' );
        }

        // List existing generated files.
        $files = Case_Engine_Packet_Generator::list_documents( $case_id );

        ob_start();
        require CASE_ENGINE_PLUGIN_DIR . 'public/documents-card.php';
        $card_html = ob_get_clean();

        return $html . $card_html;
    }

    // -------------------------------------------------------------------------
    // Private helpers
    // -------------------------------------------------------------------------

    private static function send_generation_response( array $result, int $case_id ): void {
        $files    = Case_Engine_Packet_Generator::list_documents( $case_id );
        $zip_url  = add_query_arg(
            [
                'action'   => 'az_doc_download_zip',
                'case_id'  => $case_id,
                '_wpnonce' => wp_create_nonce( 'az_documents' ),
            ],
            admin_url( 'admin-ajax.php' )
        );

        if ( empty( $result['errors'] ) ) {
            wp_send_json_success( [
                'message'   => sprintf( '%d document(s) generated successfully.', count( $result['generated'] ) ),
                'generated' => $result['generated'],
                'files'     => $files,
                'zip_url'   => $zip_url,
            ] );
        } else {
            // Partial success
            $message = sprintf(
                '%d generated, %d error(s): %s',
                count( $result['generated'] ),
                count( $result['errors'] ),
                implode( '; ', $result['errors'] )
            );
            if ( ! empty( $result['generated'] ) ) {
                wp_send_json_success( [
                    'message'   => $message,
                    'generated' => $result['generated'],
                    'files'     => $files,
                    'zip_url'   => $zip_url,
                    'errors'    => $result['errors'],
                ] );
            } else {
                wp_send_json_error( [ 'message' => $message, 'errors' => $result['errors'] ] );
            }
        }
    }

    private static function user_can_access_case( int $user_id, int $case_id ): bool {
        if ( current_user_can( 'manage_options' ) ) {
            return true;
        }
        // Delegate to the shared helper which checks both c.user_id and session user_id.
        $case = Case_Engine_Client_Dashboard::get_case_for_user( $case_id, $user_id );
        return ! empty( $case );
    }

    private static function get_case_row( int $case_id ): ?array {
        global $wpdb;
        $row = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT c.*,
                        COALESCE(NULLIF(c.user_id, 0), s.user_id) AS resolved_user_id
                 FROM {$wpdb->prefix}az_cases c
                 LEFT JOIN {$wpdb->prefix}az_intake_sessions s ON s.id = c.intake_session_id
                 WHERE c.id = %d LIMIT 1",
                $case_id
            ),
            ARRAY_A
        );
        return $row ?: null;
    }

    private static function is_questionnaire_complete( int $case_id ): bool {
        global $wpdb;
        $val = $wpdb->get_var( $wpdb->prepare(
            "SELECT is_complete FROM {$wpdb->prefix}az_case_questionnaire WHERE case_id = %d LIMIT 1",
            $case_id
        ) );
        return '1' === (string) $val;
    }
}
