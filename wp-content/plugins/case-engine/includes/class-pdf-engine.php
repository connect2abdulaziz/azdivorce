<?php
/**
 * PDF Engine — fills AcroForm PDFs using pdftk and stores files securely.
 *
 * Uses pdftk (must be installed) to:
 *   1. Generate an FDF (Forms Data Format) file from the field map.
 *   2. Call pdftk to stamp the FDF into the template PDF.
 *   3. Flatten the output so the form is read-only.
 *   4. Save the result to the private uploads directory.
 *
 * Signature, notary, and date-signed fields are deliberately left blank.
 *
 * @package Case_Engine
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Case_Engine_PDF_Engine {

    /**
     * Fields to SKIP regardless of mapping (signature / notary sections).
     */
    const SKIP_FIELDS = [
        'PetitionerSignature',
        'RespondentSignature',
        'NotarySignature',
        'NotarySection',
        'JudgeSignature',
        'Signature',
        'Signature_2',
        'Signature_3',
        'Date Signed',
        'Date Signed_2',
        'Notary Public',
        'Commission Expires',
    ];

    /**
     * Absolute path to pdftk binary.
     *
     * Can be overridden by defining CASE_ENGINE_PDFTK_PATH in wp-config.php.
     */
    private static function pdftk_bin(): string {
        if ( defined( 'CASE_ENGINE_PDFTK_PATH' ) ) {
            return CASE_ENGINE_PDFTK_PATH;
        }

        if ( PHP_OS_FAMILY === 'Windows' ) {
            $win = 'C:\\Program Files (x86)\\PDFtk Server\\bin\\pdftk.exe';
            if ( file_exists( $win ) ) {
                return $win;
            }
        }

        // Linux / Mac: pdftk is on PATH
        return 'pdftk';
    }

    /**
     * Check if pdftk is available and executable.
     */
    public static function pdftk_available(): bool {
        // exec() must not be disabled
        if ( ! function_exists( 'exec' ) || self::exec_disabled() ) {
            return false;
        }

        $bin    = self::pdftk_bin();

        // Verify the binary path exists when an absolute path is used
        if ( DIRECTORY_SEPARATOR === '\\' || strpos( $bin, DIRECTORY_SEPARATOR ) !== false ) {
            if ( ! file_exists( $bin ) ) {
                return false;
            }
        }

        $output = [];
        $code   = 1;
        // Use escapeshellarg (not escapeshellcmd) so paths with spaces are properly quoted
        @exec( escapeshellarg( $bin ) . ' --version 2>&1', $output, $code );
        return 0 === $code || ( isset( $output[0] ) && false !== stripos( $output[0], 'pdftk' ) );
    }

    /**
     * Detect whether exec() is listed in disable_functions.
     */
    private static function exec_disabled(): bool {
        $disabled = ini_get( 'disable_functions' );
        if ( ! $disabled ) {
            return false;
        }
        foreach ( explode( ',', $disabled ) as $fn ) {
            if ( trim( $fn ) === 'exec' ) {
                return true;
            }
        }
        return false;
    }

    /**
     * Get (or create) the private output directory for a case.
     *
     * Path: wp-content/uploads/divorce_cases/{case_id}/
     *
     * The directory is protected with .htaccess and index.php to prevent
     * direct web access. Actual downloads go through a WordPress AJAX handler.
     *
     * @param int $case_id
     * @return string Absolute filesystem path (with trailing slash).
     */
    public static function case_output_dir( int $case_id ): string {
        $upload   = wp_upload_dir();
        $base_dir = trailingslashit( $upload['basedir'] ) . 'divorce_cases';
        $case_dir = $base_dir . DIRECTORY_SEPARATOR . $case_id;

        if ( ! is_dir( $case_dir ) ) {
            wp_mkdir_p( $case_dir );
            // Protect directory from direct browser access
            file_put_contents( $base_dir . DIRECTORY_SEPARATOR . '.htaccess', "deny from all\n" );
            file_put_contents( $base_dir . DIRECTORY_SEPARATOR . 'index.php', "<?php // Silence is golden\n" );
            file_put_contents( $case_dir . DIRECTORY_SEPARATOR . 'index.php', "<?php // Silence is golden\n" );
        }

        return trailingslashit( $case_dir );
    }

    /**
     * Generate a single filled PDF.
     *
     * @param string $template_path  Absolute path to source AcroForm PDF.
     * @param array  $fields         [ 'AcroForm Field Name' => 'value', ... ]
     * @param string $output_path    Absolute path for the filled output PDF.
     * @param bool   $flatten        Whether to flatten (make read-only) after fill.
     * @return true|\WP_Error
     */
    public static function fill_pdf(
        string $template_path,
        array  $fields,
        string $output_path,
        bool   $flatten = true
    ) {
        if ( ! file_exists( $template_path ) ) {
            return new \WP_Error( 'template_not_found', "PDF template not found: $template_path" );
        }

        // Remove skip fields
        foreach ( self::SKIP_FIELDS as $skip ) {
            unset( $fields[ $skip ] );
        }

        // Generate FDF
        $fdf_content = self::generate_fdf( $fields );
        $fdf_path    = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'ce_fdf_' . uniqid() . '.fdf';

        if ( false === file_put_contents( $fdf_path, $fdf_content ) ) {
            return new \WP_Error( 'fdf_write_failed', 'Could not write temporary FDF file.' );
        }

        $bin     = self::pdftk_bin();
        $flatten_flag = $flatten ? 'flatten' : '';

        // Build pdftk command — escapeshellarg wraps the binary path in quotes,
        // which is required on Windows where the path contains spaces.
        $cmd = sprintf(
            '%s %s fill_form %s output %s %s 2>&1',
            escapeshellarg( $bin ),
            escapeshellarg( $template_path ),
            escapeshellarg( $fdf_path ),
            escapeshellarg( $output_path ),
            $flatten_flag
        );

        $output_lines = [];
        $exit_code    = 0;
        exec( $cmd, $output_lines, $exit_code );

        // Clean up temp FDF
        @unlink( $fdf_path );

        if ( 0 !== $exit_code || ! file_exists( $output_path ) ) {
            $error_msg = implode( "\n", $output_lines );
            return new \WP_Error(
                'pdftk_failed',
                sprintf( 'pdftk failed (exit %d): %s', $exit_code, $error_msg )
            );
        }

        return true;
    }

    /**
     * List filled PDF files for a case.
     *
     * @param int $case_id
     * @return array  [ ['name' => '...', 'size' => N, 'url' => '...', 'step' => N], ... ]
     */
    public static function list_generated_files( int $case_id ): array {
        $dir    = self::case_output_dir( $case_id );
        $files  = glob( $dir . '*.pdf' );
        $result = [];

        if ( ! $files ) {
            return $result;
        }

        foreach ( $files as $path ) {
            $name = basename( $path );
            // Derive step from filename pattern: step1_Summons.pdf
            preg_match( '/^step(\d+)_/', $name, $m );
            $result[] = [
                'path'     => $path,
                'name'     => $name,
                'size'     => filesize( $path ),
                'modified' => filemtime( $path ),
                'step'     => isset( $m[1] ) ? (int) $m[1] : 0,
                // Actual download URL goes through AJAX for security
                'download_url' => add_query_arg(
                    [
                        'action'   => 'az_doc_download',
                        'case_id'  => $case_id,
                        'file'     => $name,
                        '_wpnonce' => wp_create_nonce( 'az_doc_download_' . $case_id ),
                    ],
                    admin_url( 'admin-ajax.php' )
                ),
            ];
        }

        // Sort by step then name
        usort( $result, function ( $a, $b ) {
            if ( $a['step'] !== $b['step'] ) {
                return $a['step'] <=> $b['step'];
            }
            return strcmp( $a['name'], $b['name'] );
        } );

        return $result;
    }

    /**
     * Serve a generated file as a download (validates ownership first).
     *
     * Call from an AJAX handler. Dies after sending.
     *
     * @param int    $case_id
     * @param int    $user_id
     * @param string $filename  Basename only (no path traversal).
     */
    public static function serve_download( int $case_id, int $user_id, string $filename ): void {
        // Sanitize filename — strip any path components
        $filename = wp_basename( $filename );
        if ( ! preg_match( '/^[a-zA-Z0-9 _\-\.]+\.pdf$/i', $filename ) ) {
            wp_die( 'Invalid filename.', 403 );
        }

        // Verify case ownership
        $owner = self::get_case_owner( $case_id );
        if ( $owner !== $user_id && ! current_user_can( 'manage_options' ) ) {
            wp_die( 'Access denied.', 403 );
        }

        $path = self::case_output_dir( $case_id ) . $filename;
        if ( ! file_exists( $path ) ) {
            wp_die( 'File not found.', 404 );
        }

        // Discard any buffered output so headers go out clean
        if ( ob_get_level() ) {
            ob_end_clean();
        }

        header( 'Content-Type: application/pdf' );
        header( 'Content-Disposition: attachment; filename="' . rawurlencode( $filename ) . '"' );
        header( 'Content-Length: ' . filesize( $path ) );
        header( 'Cache-Control: private, no-store' );
        header( 'Pragma: private' );
        readfile( $path );
        exit;
    }

    /**
     * Create a ZIP archive of all PDFs for a case.
     *
     * @param int $case_id
     * @return string|\WP_Error  Path to the generated zip, or WP_Error.
     */
    public static function create_zip( int $case_id ) {
        if ( ! class_exists( 'ZipArchive' ) ) {
            return new \WP_Error( 'no_zip', 'ZipArchive PHP extension is not available.' );
        }

        $dir      = self::case_output_dir( $case_id );
        $files    = glob( $dir . '*.pdf' );
        if ( empty( $files ) ) {
            return new \WP_Error( 'no_pdfs', 'No PDF files found for this case.' );
        }

        $zip_path = $dir . 'divorce_packet_case_' . $case_id . '.zip';
        $zip      = new ZipArchive();

        if ( $zip->open( $zip_path, ZipArchive::CREATE | ZipArchive::OVERWRITE ) !== true ) {
            return new \WP_Error( 'zip_open_failed', 'Could not create ZIP archive.' );
        }

        foreach ( $files as $file ) {
            $zip->addFile( $file, basename( $file ) );
        }

        $zip->close();
        return $zip_path;
    }

    // -------------------------------------------------------------------------
    // FDF Generation
    // -------------------------------------------------------------------------

    /**
     * Generate an FDF (Forms Data Format) string from an array of field values.
     *
     * FDF is the format pdftk uses to fill AcroForm fields.
     *
     * @param  array  $fields  [ 'FieldName' => 'value', ... ]
     * @return string          FDF file contents (UTF-16 BE encoded for Unicode safety).
     */
    public static function generate_fdf( array $fields ): string {
        $fdf  = "%FDF-1.2\n";
        $fdf .= "1 0 obj\n";
        $fdf .= "<<\n/FDF\n<<\n/Fields [\n";

        foreach ( $fields as $name => $value ) {
            $name  = self::fdf_escape( (string) $name );
            $value = self::fdf_escape( (string) $value );
            $fdf  .= "<</T($name)/V($value)>>\n";
        }

        $fdf .= "]\n>>\n>>\nendobj\ntrailer\n<<\n/Root 1 0 R\n>>\n%%EOF\n";
        return $fdf;
    }

    /**
     * Escape a string for use inside an FDF parentheses-encoded string.
     *
     * Handles backslash, parentheses, and high-byte UTF-8 characters.
     */
    private static function fdf_escape( string $s ): string {
        // Replace backslash first, then parens
        $s = str_replace( '\\', '\\\\', $s );
        $s = str_replace( '(', '\\(', $s );
        $s = str_replace( ')', '\\)', $s );
        return $s;
    }

    // -------------------------------------------------------------------------
    // Internal helpers
    // -------------------------------------------------------------------------

    private static function get_case_owner( int $case_id ): int {
        global $wpdb;

        // Primary: user_id stored directly on the case row (admin-created cases)
        $uid = (int) $wpdb->get_var( $wpdb->prepare(
            "SELECT user_id FROM {$wpdb->prefix}az_cases WHERE id = %d LIMIT 1",
            $case_id
        ) );
        if ( $uid > 0 ) {
            return $uid;
        }

        // Fallback: join through intake_session_id to the sessions table
        $uid = (int) $wpdb->get_var( $wpdb->prepare(
            "SELECT s.user_id
             FROM {$wpdb->prefix}az_cases c
             INNER JOIN {$wpdb->prefix}az_intake_sessions s ON s.id = c.intake_session_id
             WHERE c.id = %d LIMIT 1",
            $case_id
        ) );

        return $uid;
    }
}
