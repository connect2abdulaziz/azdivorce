<?php
/**
 * Documents Card — rendered inside the case-detail view via
 * the az_client_dashboard_case_detail_after filter.
 *
 * Variables injected by Case_Engine_Document_Controller::inject_documents_card():
 *   $case_id      (int)
 *   $packet_type  ('wc' | 'woc')
 *   $packet_label (string)
 *   $q_complete   (bool)
 *   $files        (array)
 *
 * @package Case_Engine
 */

defined( 'ABSPATH' ) || exit;

$step_labels = array(
    1 => __( 'Step 1 — Initial Filing', 'case-engine' ),
    2 => __( 'Step 2 — Serving the Spouse', 'case-engine' ),
    3 => __( 'Step 3 — Default Application', 'case-engine' ),
    4 => __( 'Step 4 — Default Hearing / Decree', 'case-engine' ),
);

// Group existing files by step
$files_by_step = [];
foreach ( $files as $file ) {
    $s = $file['step'] ?? 0;
    $files_by_step[ $s ][] = $file;
}

$zip_url = add_query_arg(
    [
        'action'   => 'az_doc_download_zip',
        'case_id'  => $case_id,
        '_wpnonce' => wp_create_nonce( 'az_documents' ),
    ],
    admin_url( 'admin-ajax.php' )
);

$pdftk_ok = class_exists( 'Case_Engine_PDF_Engine' ) && Case_Engine_PDF_Engine::pdftk_available();

// Detect why pdftk is unavailable (for a better admin message)
$pdftk_reason = '';
if ( ! $pdftk_ok && class_exists( 'Case_Engine_PDF_Engine' ) ) {
    if ( ! function_exists( 'exec' ) ) {
        $pdftk_reason = 'exec';
    } elseif ( ini_get( 'disable_functions' ) && in_array( 'exec', array_map( 'trim', explode( ',', ini_get( 'disable_functions' ) ) ), true ) ) {
        $pdftk_reason = 'exec';
    } else {
        $pdftk_reason = 'pdftk';
    }
}
?>

<div class="az-client-dashboard__card az-docs" id="az-docs-card" data-case-id="<?php echo esc_attr( $case_id ); ?>">

    <div class="az-docs__header">
        <div class="az-docs__header-text">
            <span class="az-client-dashboard__subtitle"><?php esc_html_e( 'Your Divorce Documents', 'case-engine' ); ?></span>
            <h2 class="az-client-dashboard__title"><?php esc_html_e( 'Document Packet', 'case-engine' ); ?></h2>
        </div>
        <div class="az-docs__badge az-docs__badge--<?php echo esc_attr( $packet_type ); ?>">
            <?php echo esc_html( $packet_label ); ?>
        </div>
    </div>

    <?php if ( ! $pdftk_ok ) : ?>
        <div class="az-docs__notice az-docs__notice--warning">
            <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"></path><line x1="12" y1="9" x2="12" y2="13"></line><line x1="12" y1="17" x2="12.01" y2="17"></line></svg>
            <?php if ( 'exec' === $pdftk_reason ) : ?>
                <span><?php esc_html_e( 'PHP\'s exec() function is disabled on this server. To fix: open php.ini, find disable_functions, and remove "exec" from the list, then restart Apache.', 'case-engine' ); ?></span>
            <?php else : ?>
                <span><?php esc_html_e( 'pdftk is not found. It is installed at the expected path — try adding define(\'CASE_ENGINE_PDFTK_PATH\', \'C:\\Program Files (x86)\\PDFtk Server\\bin\\pdftk.exe\') to wp-config.php.', 'case-engine' ); ?></span>
            <?php endif; ?>
        </div>
    <?php elseif ( ! $q_complete ) : ?>
        <div class="az-docs__notice az-docs__notice--info">
            <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="10"></circle><line x1="12" y1="8" x2="12" y2="12"></line><line x1="12" y1="16" x2="12.01" y2="16"></line></svg>
            <span><?php esc_html_e( 'Complete the questionnaire before generating documents.', 'case-engine' ); ?></span>
        </div>
    <?php endif; ?>

    <?php /* ---------- Generation Buttons ---------- */ ?>
    <div class="az-docs__actions <?php echo ( ! $pdftk_ok || ! $q_complete ) ? 'az-docs__actions--disabled' : ''; ?>">

        <div class="az-docs__actions-label"><?php esc_html_e( 'Generate Documents', 'case-engine' ); ?></div>

        <div class="az-docs__btn-grid">
            <?php foreach ( $step_labels as $step_num => $step_label ) : ?>
                <button
                    class="az-docs__step-btn"
                    data-action="generate_step"
                    data-step="<?php echo esc_attr( $step_num ); ?>"
                    data-case-id="<?php echo esc_attr( $case_id ); ?>"
                    <?php echo ( ! $pdftk_ok || ! $q_complete ) ? 'disabled' : ''; ?>
                    aria-label="<?php echo esc_attr( sprintf( __( 'Generate %s', 'case-engine' ), $step_label ) ); ?>"
                >
                    <span class="az-docs__step-btn-icon"><?php echo esc_html( $step_num ); ?></span>
                    <span class="az-docs__step-btn-text"><?php echo esc_html( $step_label ); ?></span>
                    <span class="az-docs__step-btn-spinner" aria-hidden="true" hidden></span>
                </button>
            <?php endforeach; ?>
        </div>

        <button
            class="az-docs__full-btn"
            data-action="generate_full"
            data-case-id="<?php echo esc_attr( $case_id ); ?>"
            <?php echo ( ! $pdftk_ok || ! $q_complete ) ? 'disabled' : ''; ?>
        >
            <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path><polyline points="14 2 14 8 20 8"></polyline><line x1="16" y1="13" x2="8" y2="13"></line><line x1="16" y1="17" x2="8" y2="17"></line><polyline points="10 9 9 9 8 9"></polyline></svg>
            <?php esc_html_e( 'Generate Full Packet', 'case-engine' ); ?>
            <span class="az-docs__full-btn-spinner" aria-hidden="true" hidden></span>
        </button>
    </div>

    <?php /* ---------- Status message area ---------- */ ?>
    <div class="az-docs__status" id="az-docs-status" role="status" aria-live="polite" hidden></div>

    <?php /* ---------- Generated Files List ---------- */ ?>
    <div class="az-docs__files" id="az-docs-files">
        <?php if ( ! empty( $files ) ) : ?>
            <div class="az-docs__files-header">
                <h3 class="az-docs__files-title"><?php esc_html_e( 'Generated Documents', 'case-engine' ); ?></h3>
                <a href="<?php echo esc_url( $zip_url ); ?>" class="az-docs__zip-btn" download>
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path><polyline points="7 10 12 15 17 10"></polyline><line x1="12" y1="15" x2="12" y2="3"></line></svg>
                    <?php esc_html_e( 'Download ZIP', 'case-engine' ); ?>
                </a>
            </div>
            <div class="az-docs__file-list">
                <?php foreach ( $step_labels as $step_num => $step_label ) : ?>
                    <?php if ( ! empty( $files_by_step[ $step_num ] ) ) : ?>
                        <div class="az-docs__step-group">
                            <div class="az-docs__step-group-label"><?php echo esc_html( $step_label ); ?></div>
                            <?php foreach ( $files_by_step[ $step_num ] as $file ) :
                                $display_name = preg_replace( '/^step\d+_/', '', $file['name'] );
                                $display_name = str_replace( '_', ' ', basename( $display_name, '.pdf' ) );
                                $kb = round( $file['size'] / 1024, 1 );
                            ?>
                                <div class="az-docs__file-row">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="az-docs__file-icon" aria-hidden="true"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path><polyline points="14 2 14 8 20 8"></polyline></svg>
                                    <span class="az-docs__file-name"><?php echo esc_html( $display_name ); ?></span>
                                    <span class="az-docs__file-size"><?php echo esc_html( $kb . ' KB' ); ?></span>
                                    <a href="<?php echo esc_url( $file['download_url'] ); ?>" class="az-docs__file-dl" download aria-label="<?php echo esc_attr( sprintf( __( 'Download %s', 'case-engine' ), $display_name ) ); ?>">
                                        <svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path><polyline points="7 10 12 15 17 10"></polyline><line x1="12" y1="15" x2="12" y2="3"></line></svg>
                                    </a>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                <?php endforeach; ?>
                <?php if ( ! empty( $files_by_step[0] ) ) : ?>
                    <div class="az-docs__step-group">
                        <div class="az-docs__step-group-label"><?php esc_html_e( 'Other', 'case-engine' ); ?></div>
                        <?php foreach ( $files_by_step[0] as $file ) :
                            $display_name = str_replace( '_', ' ', basename( $file['name'], '.pdf' ) );
                            $kb = round( $file['size'] / 1024, 1 );
                        ?>
                            <div class="az-docs__file-row">
                                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="az-docs__file-icon" aria-hidden="true"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path><polyline points="14 2 14 8 20 8"></polyline></svg>
                                <span class="az-docs__file-name"><?php echo esc_html( $display_name ); ?></span>
                                <span class="az-docs__file-size"><?php echo esc_html( $kb . ' KB' ); ?></span>
                                <a href="<?php echo esc_url( $file['download_url'] ); ?>" class="az-docs__file-dl" download>
                                    <svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path><polyline points="7 10 12 15 17 10"></polyline><line x1="12" y1="15" x2="12" y2="3"></line></svg>
                                </a>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        <?php else : ?>
            <div class="az-docs__empty" id="az-docs-empty">
                <svg xmlns="http://www.w3.org/2000/svg" width="36" height="36" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path><polyline points="14 2 14 8 20 8"></polyline><line x1="16" y1="13" x2="8" y2="13"></line><line x1="16" y1="17" x2="8" y2="17"></line></svg>
                <p><?php esc_html_e( 'No documents generated yet.', 'case-engine' ); ?></p>
                <p class="az-docs__empty-sub"><?php esc_html_e( 'Use the buttons above to generate your divorce packet.', 'case-engine' ); ?></p>
            </div>
        <?php endif; ?>
    </div>

    <?php /* ---------- Legal disclaimer ---------- */ ?>
    <p class="az-docs__disclaimer">
        <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="10"></circle><line x1="12" y1="8" x2="12" y2="12"></line><line x1="12" y1="16" x2="12.01" y2="16"></line></svg>
        <?php esc_html_e( 'Documents are pre-filled from your questionnaire. Signature fields are left blank for manual signing. Review all documents carefully before filing with the court.', 'case-engine' ); ?>
    </p>

</div>
