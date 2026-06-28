<?php
/**
 * Plugin Name: Case Engine (AZ Divorce)
 * Description: Intake flow, case lifecycle, questionnaire, and Arizona divorce document automation for Legal Divorce Docs.
 * Version: 1.3.2
 * Author: Legal Divorce Docs
 * Text Domain: case-engine
 * Domain Path: /languages
 */

defined( 'ABSPATH' ) || exit;

define( 'CASE_ENGINE_VERSION', '1.3.2' );
define( 'CASE_ENGINE_DB_VERSION', 6 ); // v6: stripe_session_id + payment_date on az_cases; questionnaire_status on az_case_questionnaire.
define( 'CASE_ENGINE_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'CASE_ENGINE_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

/**
 * Create all Case Engine tables on activation.
 */
if ( class_exists( 'WooCommerce' ) ) {
    require_once CASE_ENGINE_PLUGIN_DIR . 'includes/woocommerce-integration.php';
    Case_Engine_WooCommerce_Integration::register();
}
function case_engine_activate() {
	require_once ABSPATH . 'wp-admin/includes/upgrade.php';
	global $wpdb;
	$p   = $wpdb->prefix;
	$ch  = $wpdb->get_charset_collate();

	$tables = array(
		'az_intake_sessions' => "CREATE TABLE {$p}az_intake_sessions (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			session_key varchar(64) NOT NULL,
			user_id bigint(20) unsigned DEFAULT 0,
			status varchar(20) NOT NULL DEFAULT 'in_progress',
			current_screen int(11) NOT NULL DEFAULT 1,
			answers longtext,
			case_id bigint(20) unsigned DEFAULT 0,
			created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			UNIQUE KEY session_key (session_key),
			KEY status (status),
			KEY user_id (user_id),
			KEY case_id (case_id)
		) $ch;",
		'az_cases' => "CREATE TABLE {$p}az_cases (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			intake_session_id bigint(20) unsigned DEFAULT 0,
			user_id bigint(20) unsigned DEFAULT 0,
			county varchar(100) DEFAULT '',
			has_children varchar(10) DEFAULT 'no',
			filing_date date DEFAULT NULL,
			role varchar(50) DEFAULT 'petitioner',
			status varchar(30) NOT NULL DEFAULT 'paid',
			stripe_session_id varchar(255) DEFAULT '',
			payment_date datetime DEFAULT NULL,
			payment_amount decimal(10,2) DEFAULT 0.00,
			questionnaire_status varchar(20) NOT NULL DEFAULT 'pending',
			created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			KEY intake_session_id (intake_session_id),
			KEY user_id (user_id),
			KEY status (status),
			KEY questionnaire_status (questionnaire_status)
		) $ch;",
		'az_parties' => "CREATE TABLE {$p}az_parties (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			case_id bigint(20) unsigned NOT NULL,
			party_type varchar(20) NOT NULL DEFAULT 'petitioner',
			full_name varchar(255) DEFAULT '',
			address text,
			phone varchar(50) DEFAULT '',
			email varchar(255) DEFAULT '',
			dob date DEFAULT NULL,
			relationship varchar(100) DEFAULT '',
			sort_order int(11) NOT NULL DEFAULT 0,
			created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			KEY case_id (case_id)
		) $ch;",
		'az_intake_answers' => "CREATE TABLE {$p}az_intake_answers (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			case_id bigint(20) unsigned NOT NULL,
			session_id bigint(20) unsigned NOT NULL DEFAULT 0,
			question_key varchar(100) NOT NULL,
			answer_value text,
			created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			KEY case_id (case_id),
			KEY session_id (session_id),
			KEY question_key (question_key)
		) $ch;",
		'az_document_states' => "CREATE TABLE {$p}az_document_states (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			case_id bigint(20) unsigned NOT NULL,
			document_type varchar(80) NOT NULL DEFAULT '',
			file_path varchar(500) DEFAULT '',
			file_hash varchar(64) DEFAULT '',
			version int(11) NOT NULL DEFAULT 1,
			status varchar(30) DEFAULT 'pending',
			created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			KEY case_id (case_id),
			KEY document_type (document_type)
		) $ch;",
		'az_payments' => "CREATE TABLE {$p}az_payments (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			case_id bigint(20) unsigned NOT NULL,
			amount decimal(10,2) NOT NULL DEFAULT 0.00,
			currency varchar(3) DEFAULT 'USD',
			status varchar(30) NOT NULL DEFAULT 'completed',
			stripe_payment_id varchar(255) DEFAULT '',
			paid_at datetime DEFAULT NULL,
			created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			KEY case_id (case_id),
			KEY status (status)
		) $ch;",
		'az_audit_logs' => "CREATE TABLE {$p}az_audit_logs (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			action varchar(80) NOT NULL,
			entity_type varchar(30) DEFAULT '',
			entity_id bigint(20) unsigned DEFAULT 0,
			user_id bigint(20) unsigned DEFAULT 0,
			details text,
			created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			KEY action (action),
			KEY entity_type (entity_type),
			KEY entity_id (entity_id)
		) $ch;",
		'az_stripe_events' => "CREATE TABLE {$p}az_stripe_events (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			stripe_event_id varchar(255) NOT NULL,
			event_type varchar(80) NOT NULL DEFAULT '',
			processed tinyint(1) NOT NULL DEFAULT 0,
			case_id bigint(20) unsigned DEFAULT 0,
			result varchar(100) DEFAULT '',
			created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			UNIQUE KEY stripe_event_id (stripe_event_id),
			KEY event_type (event_type),
			KEY case_id (case_id)
		) $ch;",
		'az_case_questionnaire' => "CREATE TABLE {$p}az_case_questionnaire (
			id                    bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			user_id               bigint(20) unsigned NOT NULL DEFAULT 0,
			case_id               bigint(20) unsigned NOT NULL DEFAULT 0,
			petitioner_first_name varchar(100)  DEFAULT '',
			petitioner_last_name  varchar(100)  DEFAULT '',
			petitioner_address    varchar(255)  DEFAULT '',
			petitioner_city       varchar(100)  DEFAULT '',
			petitioner_state      varchar(50)   DEFAULT '',
			petitioner_zip        varchar(20)   DEFAULT '',
			petitioner_phone      varchar(50)   DEFAULT '',
			petitioner_email      varchar(255)  DEFAULT '',
			respondent_first_name varchar(100)  DEFAULT '',
			respondent_last_name  varchar(100)  DEFAULT '',
			respondent_address    varchar(255)  DEFAULT '',
			respondent_city       varchar(100)  DEFAULT '',
			respondent_state      varchar(50)   DEFAULT '',
			respondent_zip        varchar(20)   DEFAULT '',
			marriage_date         date          DEFAULT NULL,
			marriage_city         varchar(100)  DEFAULT '',
			marriage_state        varchar(50)   DEFAULT '',
			separation_date       date          DEFAULT NULL,
			county_filing         varchar(100)  DEFAULT '',
			covenant_marriage     varchar(5)    DEFAULT 'no',
			pregnancy_status      varchar(5)    DEFAULT 'no',
			property_division     longtext,
			debt_division         longtext,
			restore_former_name   varchar(5)    DEFAULT 'no',
			former_name           varchar(255)  DEFAULT '',
			service_method        varchar(100)  DEFAULT '',
			acceptance_of_service varchar(5)    DEFAULT 'no',
			date_of_service       date          DEFAULT NULL,
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
		) $ch;",
	);

	foreach ( $tables as $name => $sql ) {
		dbDelta( $sql );
	}

	// Ensure user_id exists on az_cases (v3: link user to case without session).
	if ( get_option( 'case_engine_db_version', 0 ) < 3 ) {
		$col = $wpdb->get_results( $wpdb->prepare( "SHOW COLUMNS FROM {$p}az_cases LIKE %s", 'user_id' ) );
		if ( empty( $col ) ) {
			$wpdb->query( "ALTER TABLE {$p}az_cases ADD COLUMN user_id bigint(20) unsigned DEFAULT 0 AFTER intake_session_id, ADD KEY user_id (user_id)" );
		}
	}
	// v4: az_stripe_events created above via dbDelta.
	// v5: az_case_questionnaire created above via dbDelta.
	// v6: stripe_session_id, payment_date, payment_amount, questionnaire_status on az_cases.
	case_engine_run_v6_migration( $p );

	case_engine_ensure_client_dashboard_page();
	update_option( 'case_engine_db_version', CASE_ENGINE_DB_VERSION );

	// RBAC: roles and capabilities.
	require_once CASE_ENGINE_PLUGIN_DIR . 'includes/class-case-engine-rbac.php';
	Case_Engine_RBAC::install_roles_caps();
}
register_activation_hook( __FILE__, 'case_engine_activate' );

/**
 * Ensure DB tables exist (e.g. after update without reactivation).
 */
function case_engine_maybe_upgrade_db() {
	if ( get_option( 'case_engine_db_version', 0 ) >= CASE_ENGINE_DB_VERSION ) {
		case_engine_ensure_user_id_column();
		return;
	}
	case_engine_activate();
}
add_action( 'init', 'case_engine_maybe_upgrade_db', 5 );

/**
 * Ensure az_cases has user_id column (v3). Run on load so existing installs get the column even if upgrade path was skipped.
 */
function case_engine_ensure_user_id_column() {
	global $wpdb;
	$p = $wpdb->prefix;
	$table = $p . 'az_cases';
	$col = $wpdb->get_results( $wpdb->prepare( "SHOW COLUMNS FROM `{$table}` LIKE %s", 'user_id' ) );
	if ( ! empty( $col ) ) {
		return;
	}
	$wpdb->query( "ALTER TABLE `{$table}` ADD COLUMN user_id bigint(20) unsigned DEFAULT 0 AFTER intake_session_id, ADD KEY user_id (user_id)" );
}

/**
 * v6 migration: add stripe_session_id, payment_date, payment_amount, questionnaire_status to az_cases.
 *
 * @param string $p Table prefix.
 */
function case_engine_run_v6_migration( $p ) {
	global $wpdb;
	$table = $p . 'az_cases';
	$cols  = $wpdb->get_col( "SHOW COLUMNS FROM `{$table}`", 0 );

	if ( ! in_array( 'stripe_session_id', $cols, true ) ) {
		$wpdb->query( "ALTER TABLE `{$table}` ADD COLUMN stripe_session_id varchar(255) DEFAULT '' AFTER status" );
	}
	if ( ! in_array( 'payment_date', $cols, true ) ) {
		$wpdb->query( "ALTER TABLE `{$table}` ADD COLUMN payment_date datetime DEFAULT NULL AFTER stripe_session_id" );
	}
	if ( ! in_array( 'payment_amount', $cols, true ) ) {
		$wpdb->query( "ALTER TABLE `{$table}` ADD COLUMN payment_amount decimal(10,2) DEFAULT 0.00 AFTER payment_date" );
	}
	if ( ! in_array( 'questionnaire_status', $cols, true ) ) {
		$wpdb->query( "ALTER TABLE `{$table}` ADD COLUMN questionnaire_status varchar(20) NOT NULL DEFAULT 'pending' AFTER payment_amount, ADD KEY questionnaire_status (questionnaire_status)" );
	}
}

/**
 * Link guest intake session/case to a user id using intake cookies.
 *
 * @param int $user_id WordPress user id.
 * @return void
 */
function case_engine_link_guest_session_to_user( $user_id ) {
	$user_id = (int) $user_id;
	if ( $user_id <= 0 ) {
		return;
	}

	$session_key = '';

	// Priority 1: cookie set just before login redirect (set by ajax_payment for guests).
	if ( isset( $_COOKIE['az_intake_pending_sk'] ) ) {
		$sk = sanitize_text_field( wp_unslash( $_COOKIE['az_intake_pending_sk'] ) );
		if ( preg_match( '/^[a-zA-Z0-9]{32}$/', $sk ) ) {
			$session_key = $sk;
		}
	}

	// Priority 2: standard intake session cookie (guest used the form without being interrupted).
	if ( ! $session_key && isset( $_COOKIE['az_intake_session'] ) ) {
		$sk = sanitize_text_field( wp_unslash( $_COOKIE['az_intake_session'] ) );
		if ( preg_match( '/^[a-zA-Z0-9]{32}$/', $sk ) ) {
			$session_key = $sk;
		}
	}

	if ( ! $session_key ) {
		return;
	}

	global $wpdb;
	$sessions_table  = $wpdb->prefix . 'az_intake_sessions';
	$cases_table     = $wpdb->prefix . 'az_cases';

	$session = $wpdb->get_row( $wpdb->prepare(
		"SELECT id, user_id, case_id FROM {$sessions_table} WHERE session_key = %s LIMIT 1",
		$session_key
	), ARRAY_A );

	if ( ! $session ) {
		return;
	}

	// Only link if currently unowned (user_id = 0).
	if ( (int) $session['user_id'] !== 0 ) {
		return;
	}

	// Update session user_id.
	$wpdb->update(
		$sessions_table,
		array( 'user_id' => $user_id, 'updated_at' => current_time( 'mysql' ) ),
		array( 'id' => (int) $session['id'] ),
		array( '%d', '%s' ),
		array( '%d' )
	);

	// Update case user_id if a case was created from this session.
	if ( ! empty( $session['case_id'] ) ) {
		$case_id = (int) $session['case_id'];
		$current_case_uid = (int) $wpdb->get_var( $wpdb->prepare(
			"SELECT user_id FROM {$cases_table} WHERE id = %d LIMIT 1",
			$case_id
		) );
		if ( $current_case_uid === 0 ) {
			$wpdb->update(
				$cases_table,
				array( 'user_id' => $user_id, 'updated_at' => current_time( 'mysql' ) ),
				array( 'id' => $case_id ),
				array( '%d', '%s' ),
				array( '%d' )
			);
		}
	}

	// Clear intake-link cookies once ownership has been attached.
	if ( PHP_VERSION_ID >= 70300 ) {
		@setcookie( 'az_intake_pending_sk', '', array( 'expires' => time() - 3600, 'path' => '/', 'samesite' => 'Lax' ) );
		@setcookie( 'az_intake_session', '', array( 'expires' => time() - 3600, 'path' => '/', 'samesite' => 'Lax' ) );
	} else {
		@setcookie( 'az_intake_pending_sk', '', time() - 3600, '/; samesite=Lax' );
		@setcookie( 'az_intake_session', '', time() - 3600, '/; samesite=Lax' );
	}
}

/**
 * On user login: link guest intake ownership.
 *
 * @param string  $user_login Username.
 * @param WP_User $user       User object.
 */
function case_engine_link_guest_session_on_login( $user_login, $user ) {
	unset( $user_login ); // Hook signature only.
	case_engine_link_guest_session_to_user( (int) $user->ID );
}
add_action( 'wp_login', 'case_engine_link_guest_session_on_login', 10, 2 );

/**
 * On user registration (including Woo create-account): link guest intake ownership.
 *
 * @param int $user_id New user id.
 */
function case_engine_link_guest_session_on_registration( $user_id ) {
	case_engine_link_guest_session_to_user( (int) $user_id );
}
add_action( 'user_register', 'case_engine_link_guest_session_on_registration', 20, 1 );
add_action( 'woocommerce_created_customer', 'case_engine_link_guest_session_on_registration', 20, 1 );

/**
 * Create Client Dashboard page if it doesn't exist (so post-intake redirect doesn't 404).
 */
function case_engine_ensure_client_dashboard_page() {
	$slug = 'client-dashboard';
	$page = get_page_by_path( $slug, OBJECT, 'page' );
	if ( ! $page ) {
		wp_insert_post( array(
			'post_title'   => __( 'Client Dashboard', 'case-engine' ),
			'post_name'    => $slug,
			'post_status'  => 'publish',
			'post_type'    => 'page',
			'post_content' => '<p>' . __( 'View your cases and, in a later release, documents and payment history.', 'case-engine' ) . '</p>' . "\n\n" . '[az_client_dashboard]',
		) );
		return;
	}
	// Existing page created before shortcode: add shortcode so dashboard shows login/cases list.
	if ( strpos( $page->post_content, '[az_client_dashboard]' ) === false ) {
		$new_content = '<p>' . __( 'View your cases and, in a later release, documents and payment history.', 'case-engine' ) . '</p>' . "\n\n" . '[az_client_dashboard]';
		wp_update_post( array(
			'ID'           => $page->ID,
			'post_content' => $new_content,
		) );
	}
}

/**
 * Load RBAC, intake flow, case factory, and questionnaire system.
 */
require_once CASE_ENGINE_PLUGIN_DIR . 'includes/class-case-engine-rbac.php';
require_once CASE_ENGINE_PLUGIN_DIR . 'includes/class-intake-flow.php';
require_once CASE_ENGINE_PLUGIN_DIR . 'includes/class-intake-handler.php';
require_once CASE_ENGINE_PLUGIN_DIR . 'includes/class-case-factory.php';
require_once CASE_ENGINE_PLUGIN_DIR . 'includes/class-client-dashboard.php';
require_once CASE_ENGINE_PLUGIN_DIR . 'includes/woocommerce-integration.php';
require_once CASE_ENGINE_PLUGIN_DIR . 'includes/class-questionnaire-db.php';
require_once CASE_ENGINE_PLUGIN_DIR . 'includes/class-questionnaire-field-map.php';
require_once CASE_ENGINE_PLUGIN_DIR . 'includes/class-questionnaire-api.php';
require_once CASE_ENGINE_PLUGIN_DIR . 'includes/class-questionnaire-controller.php';

// PDF automation engine (v1.2.0)
require_once CASE_ENGINE_PLUGIN_DIR . 'includes/class-pdf-engine.php';
require_once CASE_ENGINE_PLUGIN_DIR . 'includes/class-pdf-mapper.php';
require_once CASE_ENGINE_PLUGIN_DIR . 'includes/class-ai-pdf-field-resolver.php';
require_once CASE_ENGINE_PLUGIN_DIR . 'includes/class-packet-generator.php';
require_once CASE_ENGINE_PLUGIN_DIR . 'includes/class-document-controller.php';

/**
 * Init plugin.
 */
function case_engine_init() {
	Case_Engine_Intake_Flow::register();
	Case_Engine_Intake_Handler::register();
	Case_Engine_Client_Dashboard::register();
	Case_Engine_Questionnaire_DB::maybe_upgrade();
	Case_Engine_Questionnaire_API::register();
	Case_Engine_Questionnaire_Controller::register();
	Case_Engine_Document_Controller::register();
	// Use WooCommerce (and its Stripe gateway) for payments instead of custom Stripe Checkout.
	if ( class_exists( 'WooCommerce' ) ) {
		Case_Engine_WooCommerce_Integration::register();
	}
	// Ensure Client Dashboard page exists (fixes 404 after intake completion).
	if ( get_option( 'case_engine_dashboard_page_checked' ) !== CASE_ENGINE_VERSION ) {
		case_engine_ensure_client_dashboard_page();
		update_option( 'case_engine_dashboard_page_checked', CASE_ENGINE_VERSION );
	}
	// One-time: add [az_client_dashboard] to existing Client Dashboard page if missing.
	if ( get_option( 'case_engine_dashboard_shortcode_added' ) !== CASE_ENGINE_VERSION ) {
		$page = get_page_by_path( 'client-dashboard', OBJECT, 'page' );
		if ( $page && strpos( $page->post_content, '[az_client_dashboard]' ) === false ) {
			$new_content = '<p>' . __( 'View your cases and, in a later release, documents and payment history.', 'case-engine' ) . '</p>' . "\n\n" . '[az_client_dashboard]';
			wp_update_post( array( 'ID' => $page->ID, 'post_content' => $new_content ) );
		}
		update_option( 'case_engine_dashboard_shortcode_added', CASE_ENGINE_VERSION );
	}
	// Ensure roles/caps exist (e.g. after update without reactivation).
	if ( get_option( 'case_engine_rbac_version' ) !== CASE_ENGINE_VERSION ) {
		Case_Engine_RBAC::install_roles_caps();
		update_option( 'case_engine_rbac_version', CASE_ENGINE_VERSION );
	}
}
add_action( 'init', 'case_engine_init' );

/**
 * Enqueue Client Dashboard styles on the dashboard page.
 */
function case_engine_enqueue_dashboard_assets() {
	if ( ! is_page( 'client-dashboard' ) ) {
		return;
	}
	wp_enqueue_style(
		'case-engine-dashboard',
		CASE_ENGINE_PLUGIN_URL . 'assets/dashboard.css',
		array(),
		CASE_ENGINE_VERSION
	);
}
add_action( 'wp_enqueue_scripts', 'case_engine_enqueue_dashboard_assets' );

/**
 * Remind admins to enable WooCommerce guest checkout so intake payment does not require WordPress login.
 */
function case_engine_admin_notice_guest_checkout() {
	if ( ! current_user_can( 'manage_options' ) || ! class_exists( 'WooCommerce' ) ) {
		return;
	}
	if ( 'yes' === get_option( 'woocommerce_enable_guest_checkout' ) ) {
		return;
	}
	echo '<div class="notice notice-warning"><p>';
	echo esc_html__( 'Case Engine: To let customers pay without logging in first, enable ', 'case-engine' );
	echo '<strong>' . esc_html__( 'WooCommerce → Settings → Accounts & Privacy → Allow customers to place orders without an account', 'case-engine' ) . '</strong>. ';
	echo esc_html__( 'Otherwise checkout may require an account.', 'case-engine' );
	echo '</p></div>';
}
add_action( 'admin_notices', 'case_engine_admin_notice_guest_checkout' );

/**
 * Admin menu (visible to users who can view sessions).
 */
function case_engine_admin_menu() {
	add_menu_page(
		__( 'Case Engine', 'case-engine' ),
		__( 'Case Engine', 'case-engine' ),
		Case_Engine_RBAC::VIEW_SESSIONS,
		'case-engine',
		'case_engine_admin_page',
		'dashicons-portfolio',
		30
	);
}
add_action( 'admin_menu', 'case_engine_admin_menu' );

/**
 * Enqueue admin styles for Case Engine (e.g. action bar single row).
 */
function case_engine_admin_enqueue( $hook ) {
	if ( $hook !== 'toplevel_page_case-engine' ) {
		return;
	}
	wp_add_inline_style( 'wp-admin', '
		.case-engine-action-bar { display: flex !important; flex-wrap: nowrap !important; align-items: center !important; gap: 8px !important; }
		.case-engine-action-bar form { display: inline-block !important; margin: 0 !important; }
		.case-engine-action-bar form input[type="submit"] { margin: 0 !important; }
		.case-engine-add-case-form { background: #fff; border: 1px solid #c3c4c7; border-radius: 4px; box-shadow: 0 1px 1px rgba(0,0,0,.04); padding: 20px 24px; margin: 20px 0; max-width: 600px; }
		.case-engine-add-case-form h2 { margin-top: 0; padding-bottom: 12px; border-bottom: 1px solid #dcdcde; }
		.case-engine-add-case-form .form-table { margin-top: 0; }
		.case-engine-add-case-form .form-table th { padding: 12px 10px 12px 0; width: 160px; }
		.case-engine-add-case-form .form-table td { padding: 12px 10px; }
		.case-engine-add-case-form .form-table input[type="text"],
		.case-engine-add-case-form .form-table input[type="date"],
		.case-engine-add-case-form .form-table select { width: 100%; max-width: 280px; }
		.case-engine-add-case-form .button-group { margin-top: 16px; padding-top: 16px; border-top: 1px solid #dcdcde; }
	' );
}
add_action( 'admin_enqueue_scripts', 'case_engine_admin_enqueue' );

/**
 * Handle Case Engine admin actions before any output (fixes "headers already sent" on redirect).
 */
function case_engine_admin_load() {
	if ( ! isset( $_GET['page'] ) || $_GET['page'] !== 'case-engine' ) {
		return;
	}
	if ( ! current_user_can( Case_Engine_RBAC::VIEW_SESSIONS ) ) {
		return;
	}

	global $wpdb;
	$p = $wpdb->prefix;
	$sessions_table   = $p . 'az_intake_sessions';
	$cases_table      = $p . 'az_cases';
	$parties_table    = $p . 'az_parties';
	$answers_table    = $p . 'az_intake_answers';
	$documents_table  = $p . 'az_document_states';
	$payments_table   = $p . 'az_payments';
	$audit_table     = $p . 'az_audit_logs';
	$base_url = admin_url( 'admin.php?page=case-engine' );

	// Update case status (RBAC: EDIT_CASES).
	if ( isset( $_POST['case_engine_update_case'] ) && current_user_can( Case_Engine_RBAC::EDIT_CASES ) ) {
		check_admin_referer( 'case_engine_update_case' );
		$case_id = isset( $_POST['case_id'] ) ? (int) $_POST['case_id'] : 0;
		$status  = isset( $_POST['case_status'] ) ? sanitize_text_field( wp_unslash( $_POST['case_status'] ) ) : '';
		if ( $case_id && $status && strlen( $status ) <= 30 ) {
			$wpdb->update( $cases_table, array( 'status' => $status ), array( 'id' => $case_id ), array( '%s' ), array( '%d' ) );
			wp_safe_redirect( add_query_arg( array( 'view_case' => $case_id, 'updated' => 1 ), $base_url ) );
			exit;
		}
	}

	// Edit case and parties (RBAC: EDIT_CASES).
	if ( isset( $_POST['case_engine_edit_case'] ) && current_user_can( Case_Engine_RBAC::EDIT_CASES ) ) {
		check_admin_referer( 'case_engine_edit_case' );
		$case_id = isset( $_POST['case_id'] ) ? (int) $_POST['case_id'] : 0;
		if ( $case_id ) {
			$county      = isset( $_POST['county'] ) ? sanitize_text_field( wp_unslash( $_POST['county'] ) ) : '';
			$has_children = isset( $_POST['has_children'] ) ? sanitize_text_field( wp_unslash( $_POST['has_children'] ) ) : 'no';
			$filing_date = isset( $_POST['filing_date'] ) ? sanitize_text_field( wp_unslash( $_POST['filing_date'] ) ) : null;
			$role        = isset( $_POST['role'] ) ? sanitize_text_field( wp_unslash( $_POST['role'] ) ) : 'petitioner';
			$status      = isset( $_POST['case_status'] ) ? sanitize_text_field( wp_unslash( $_POST['case_status'] ) ) : 'paid';
			if ( $filing_date === '' ) {
				$filing_date = null;
			}
			$wpdb->update(
				$cases_table,
				array( 'county' => $county, 'has_children' => $has_children, 'filing_date' => $filing_date, 'role' => $role, 'status' => $status ),
				array( 'id' => $case_id ),
				array( '%s', '%s', '%s', '%s', '%s' ),
				array( '%d' )
			);
			$parties = isset( $_POST['parties'] ) && is_array( $_POST['parties'] ) ? wp_unslash( $_POST['parties'] ) : array();
			$wpdb->delete( $parties_table, array( 'case_id' => $case_id ), array( '%d' ) );
			$sort = 0;
			foreach ( $parties as $row ) {
				$pt = isset( $row['party_type'] ) ? sanitize_text_field( $row['party_type'] ) : 'petitioner';
				$name = isset( $row['full_name'] ) ? sanitize_text_field( $row['full_name'] ) : '';
				if ( $name === '' && empty( $row['email'] ) && empty( $row['phone'] ) ) {
					continue;
				}
				$wpdb->insert(
					$parties_table,
					array(
						'case_id'      => $case_id,
						'party_type'   => $pt,
						'full_name'    => $name,
						'address'      => isset( $row['address'] ) ? sanitize_textarea_field( $row['address'] ) : '',
						'phone'        => isset( $row['phone'] ) ? sanitize_text_field( $row['phone'] ) : '',
						'email'        => isset( $row['email'] ) ? sanitize_email( $row['email'] ) : '',
						'dob'          => ! empty( $row['dob'] ) ? sanitize_text_field( $row['dob'] ) : null,
						'relationship' => isset( $row['relationship'] ) ? sanitize_text_field( $row['relationship'] ) : '',
						'sort_order'   => $sort++,
					),
					array( '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%d' )
				);
			}
			wp_safe_redirect( add_query_arg( array( 'view_case' => $case_id, 'updated' => 1 ), $base_url ) );
			exit;
		}
	}

	// Delete case and all related data (RBAC: EDIT_CASES).
	if ( isset( $_POST['case_engine_delete_case'] ) && current_user_can( Case_Engine_RBAC::EDIT_CASES ) ) {
		check_admin_referer( 'case_engine_delete_case' );
		$case_id = isset( $_POST['case_id'] ) ? (int) $_POST['case_id'] : 0;
		if ( $case_id ) {
			$wpdb->delete( $parties_table, array( 'case_id' => $case_id ), array( '%d' ) );
			$wpdb->delete( $answers_table, array( 'case_id' => $case_id ), array( '%d' ) );
			$wpdb->delete( $documents_table, array( 'case_id' => $case_id ), array( '%d' ) );
			$wpdb->delete( $payments_table, array( 'case_id' => $case_id ), array( '%d' ) );
			$wpdb->delete( $audit_table, array( 'entity_type' => 'case', 'entity_id' => $case_id ), array( '%s', '%d' ) );
			$wpdb->update( $sessions_table, array( 'case_id' => 0 ), array( 'case_id' => $case_id ), array( '%d' ), array( '%d' ) );
			$wpdb->delete( $cases_table, array( 'id' => $case_id ), array( '%d' ) );
			wp_safe_redirect( add_query_arg( 'deleted', 1, remove_query_arg( array( 'view_case', 'edit' ), $base_url ) ) );
			exit;
		}
	}

	// Add case (admin-created, optional assign user_id on case only — no session). RBAC: EDIT_CASES.
	if ( isset( $_POST['case_engine_add_case'] ) && current_user_can( Case_Engine_RBAC::EDIT_CASES ) ) {
		check_admin_referer( 'case_engine_add_case' );
		$county         = isset( $_POST['county'] ) ? sanitize_text_field( wp_unslash( $_POST['county'] ) ) : 'Maricopa';
		$has_children   = isset( $_POST['has_children'] ) ? sanitize_text_field( wp_unslash( $_POST['has_children'] ) ) : 'no';
		$filing_date    = isset( $_POST['filing_date'] ) ? sanitize_text_field( wp_unslash( $_POST['filing_date'] ) ) : null;
		$role           = isset( $_POST['role'] ) ? sanitize_text_field( wp_unslash( $_POST['role'] ) ) : 'petitioner';
		$status         = isset( $_POST['case_status'] ) ? sanitize_text_field( wp_unslash( $_POST['case_status'] ) ) : 'pending_payment';
		$assign_user_id = isset( $_POST['assign_user_id'] ) ? (int) $_POST['assign_user_id'] : 0;
		if ( $filing_date === '' ) {
			$filing_date = null;
		}
		$wpdb->insert(
			$cases_table,
			array(
				'intake_session_id' => 0,
				'user_id'           => $assign_user_id,
				'county'            => $county,
				'has_children'      => $has_children,
				'filing_date'       => $filing_date,
				'role'              => $role,
				'status'            => $status,
			),
			array( '%d', '%d', '%s', '%s', '%s', '%s', '%s' )
		);
		$new_case_id = (int) $wpdb->insert_id;
		if ( $new_case_id ) {
			wp_safe_redirect( add_query_arg( array( 'view_case' => $new_case_id, 'add_case' => 1 ), $base_url ) );
			exit;
		}
	}

	// Create case from session (RBAC: CREATE_CASE_FROM_SESSION).
	$create_from = isset( $_GET['create_case_from_session'] ) ? (int) $_GET['create_case_from_session'] : 0;
	if ( $create_from && current_user_can( Case_Engine_RBAC::CREATE_CASE_FROM_SESSION ) ) {
		$case_id = Case_Engine_Case_Factory::create_from_session( $create_from );
		if ( $case_id ) {
			wp_safe_redirect( add_query_arg( 'case_created', $case_id, $base_url ) );
			exit;
		}
	}
}
add_action( 'load-toplevel_page_case-engine', 'case_engine_admin_load' );

function case_engine_admin_page() {
	if ( ! current_user_can( Case_Engine_RBAC::VIEW_SESSIONS ) ) {
		wp_die( esc_html__( 'You do not have permission to access this page.', 'case-engine' ), 403 );
	}

	global $wpdb;
	$p = $wpdb->prefix;
	$sessions_table = $p . 'az_intake_sessions';
	$cases_table    = $p . 'az_cases';
	$parties_table  = $p . 'az_parties';

	// Admin view single case (RBAC: VIEW_CASES required).
	$view_case_id = isset( $_GET['view_case'] ) ? (int) $_GET['view_case'] : 0;
	if ( $view_case_id && current_user_can( Case_Engine_RBAC::VIEW_CASES ) ) {
		$case = $wpdb->get_row( $wpdb->prepare(
			"SELECT c.*, s.user_id AS session_user_id FROM {$cases_table} c
			 LEFT JOIN {$sessions_table} s ON c.intake_session_id = s.id
			 WHERE c.id = %d",
			$view_case_id
		), ARRAY_A );
		if ( $case ) {
			$parties = $wpdb->get_results( $wpdb->prepare(
				"SELECT id, party_type, full_name, address, phone, email, dob, relationship FROM {$parties_table} WHERE case_id = %d ORDER BY sort_order ASC, id ASC",
				$view_case_id
			), ARRAY_A );
			$is_edit = isset( $_GET['edit'] ) && (int) $_GET['edit'] === 1 && current_user_can( Case_Engine_RBAC::EDIT_CASES );
			case_engine_render_admin_case_detail( $case, $parties ? $parties : array(), $is_edit );
			return;
		}
	}

	echo '<div class="wrap"><h1>' . esc_html__( 'Case Engine', 'case-engine' ) . '</h1>';
	if ( isset( $_GET['deleted'] ) ) {
		echo '<div class="notice notice-success"><p>' . esc_html__( 'Case deleted.', 'case-engine' ) . '</p></div>';
	}
	if ( isset( $_GET['case_created'] ) ) {
		echo '<div class="notice notice-success"><p>' . esc_html__( 'Case created successfully.', 'case-engine' ) . ' ID: ' . (int) $_GET['case_created'] . '</p></div>';
	}
	if ( isset( $_GET['add_case'] ) && isset( $_GET['view_case'] ) ) {
		echo '<div class="notice notice-success"><p>' . esc_html__( 'Case created. Click "Edit case" to add parties.', 'case-engine' ) . '</p></div>';
	}
	echo '<p>' . esc_html__( 'Cases and intake sessions are listed below.', 'case-engine' ) . '</p>';

	// Add case form (RBAC: EDIT_CASES) — show when ?add_case=1 and not viewing a case.
	$show_add_case = isset( $_GET['add_case'] ) && (int) $_GET['add_case'] === 1 && ! isset( $_GET['view_case'] ) && current_user_can( Case_Engine_RBAC::EDIT_CASES );
	if ( $show_add_case ) {
		case_engine_render_add_case_form();
		echo '<hr style="margin: 20px 0;" />';
	}

	// All Cases list (RBAC: VIEW_CASES — Admin / Case Manager only).
	if ( current_user_can( Case_Engine_RBAC::VIEW_CASES ) ) {
		$all_cases = $wpdb->get_results(
			"SELECT c.id, c.status, c.county, c.has_children, c.created_at, c.intake_session_id, c.user_id, s.user_id AS session_user_id
			 FROM {$cases_table} c
			 LEFT JOIN {$sessions_table} s ON c.intake_session_id = s.id
			 ORDER BY c.id DESC
			 LIMIT 100",
			ARRAY_A
		);
		if ( $all_cases ) {
			echo '<h2>' . esc_html__( 'All cases', 'case-engine' ) . '</h2>';
			if ( current_user_can( Case_Engine_RBAC::EDIT_CASES ) ) {
				$add_case_url = add_query_arg( 'add_case', 1, admin_url( 'admin.php?page=case-engine' ) );
				echo '<p><a href="' . esc_url( $add_case_url ) . '" class="button button-primary">' . esc_html__( 'Add case', 'case-engine' ) . '</a></p>';
			}
			echo '<table class="widefat striped"><thead><tr>';
			echo '<th>' . esc_html__( 'Case ID', 'case-engine' ) . '</th><th>' . esc_html__( 'Status', 'case-engine' ) . '</th><th>' . esc_html__( 'County', 'case-engine' ) . '</th>';
			echo '<th>' . esc_html__( 'Owner', 'case-engine' ) . '</th><th>' . esc_html__( 'Created', 'case-engine' ) . '</th><th>' . esc_html__( 'Actions', 'case-engine' ) . '</th></tr></thead><tbody>';
			foreach ( $all_cases as $c ) {
				$view_url = add_query_arg( 'view_case', (int) $c['id'], admin_url( 'admin.php?page=case-engine' ) );
				$owner_id = (int) ( $c['user_id'] ?? 0 ) ? (int) $c['user_id'] : (int) ( $c['session_user_id'] ?? 0 );
				$owner_name = '—';
				if ( $owner_id ) {
					$owner = get_user_by( 'id', $owner_id );
					$owner_name = $owner ? esc_html( $owner->display_name ? $owner->display_name : $owner->user_login ) : (string) $owner_id;
				}
				echo '<tr>';
				echo '<td>' . (int) $c['id'] . '</td><td>' . esc_html( $c['status'] ) . '</td><td>' . esc_html( $c['county'] ) . '</td>';
				echo '<td>' . $owner_name . '</td>';
				echo '<td>' . esc_html( $c['created_at'] ) . '</td>';
				echo '<td><a href="' . esc_url( $view_url ) . '" style="color:#2b6cb0;">' . esc_html__( 'View / Manage', 'case-engine' ) . '</a></td></tr>';
			}
			echo '</tbody></table>';
		} else {
			if ( current_user_can( Case_Engine_RBAC::EDIT_CASES ) ) {
				$add_case_url = add_query_arg( 'add_case', 1, admin_url( 'admin.php?page=case-engine' ) );
				echo '<p><a href="' . esc_url( $add_case_url ) . '" class="button button-primary">' . esc_html__( 'Add case', 'case-engine' ) . '</a></p>';
			}
			echo '<p>' . esc_html__( 'No cases yet.', 'case-engine' ) . '</p>';
		}
	}

	echo '</div>';
}

/**
 * Render "Add case" form (admin-created case, optional assign user). RBAC: EDIT_CASES.
 */
function case_engine_render_add_case_form() {
	$users = get_users( array( 'orderby' => 'display_name', 'number' => 500 ) );
	echo '<div class="case-engine-add-case-form">';
	echo '<h2>' . esc_html__( 'Add case', 'case-engine' ) . '</h2>';
	echo '<p class="description">' . esc_html__( 'Create a new case manually. After saving, you can add parties by opening the case and clicking "Edit case".', 'case-engine' ) . '</p>';
	echo '<form method="post" action="">';
	wp_nonce_field( 'case_engine_add_case' );
	echo '<input type="hidden" name="case_engine_add_case" value="1" />';
	echo '<table class="form-table" role="presentation"><tbody>';
	echo '<tr><th scope="row"><label for="add_county">' . esc_html__( 'County', 'case-engine' ) . '</label></th><td><input type="text" id="add_county" name="county" value="Maricopa" class="regular-text" /></td></tr>';
	echo '<tr><th scope="row"><label for="add_has_children">' . esc_html__( 'Has children', 'case-engine' ) . '</label></th><td><select id="add_has_children" name="has_children"><option value="no">' . esc_html__( 'No', 'case-engine' ) . '</option><option value="yes">' . esc_html__( 'Yes', 'case-engine' ) . '</option></select></td></tr>';
	echo '<tr><th scope="row"><label for="add_filing_date">' . esc_html__( 'Filing date', 'case-engine' ) . '</label></th><td><input type="date" id="add_filing_date" name="filing_date" value="" /></td></tr>';
	echo '<tr><th scope="row"><label for="add_role">' . esc_html__( 'Role', 'case-engine' ) . '</label></th><td><select id="add_role" name="role"><option value="petitioner">' . esc_html__( 'Petitioner', 'case-engine' ) . '</option><option value="joint">' . esc_html__( 'Joint filing', 'case-engine' ) . '</option></select></td></tr>';
	echo '<tr><th scope="row"><label for="add_case_status">' . esc_html__( 'Status', 'case-engine' ) . '</label></th><td><select id="add_case_status" name="case_status"><option value="pending_payment">pending_payment</option><option value="paid">paid</option><option value="in_progress">in_progress</option><option value="completed">completed</option><option value="cancelled">cancelled</option></select></td></tr>';
	echo '<tr><th scope="row"><label for="add_assign_user">' . esc_html__( 'Assign user to case', 'case-engine' ) . '</label></th><td><select id="add_assign_user" name="assign_user_id">';
	echo '<option value="0">— ' . esc_html__( 'No owner / Unassigned', 'case-engine' ) . '</option>';
	foreach ( $users as $u ) {
		$name = $u->display_name ? $u->display_name . ' (' . $u->user_login . ')' : $u->user_login;
		echo '<option value="' . esc_attr( $u->ID ) . '">' . esc_html( $name ) . '</option>';
	}
	echo '</select></td></tr>';
	echo '</tbody></table>';
	echo '<div class="button-group">';
	submit_button( __( 'Create case', 'case-engine' ), 'primary', 'submit', false );
	echo ' <a href="' . esc_url( admin_url( 'admin.php?page=case-engine' ) ) . '" class="button">' . esc_html__( 'Cancel', 'case-engine' ) . '</a>';
	echo '</div></form>';
	echo '</div>';
}

/**
 * Render admin case detail with View/Edit/Delete (RBAC: VIEW_CASES to view, EDIT_CASES to edit/delete).
 *
 * @param array $case    Case row (with session_user_id).
 * @param array $parties Party rows (id, party_type, full_name, address, phone, email, dob, relationship).
 * @param bool  $is_edit Whether to show edit form.
 */
function case_engine_render_admin_case_detail( $case, $parties, $is_edit = false ) {
	$case_id  = (int) $case['id'];
	$can_edit = current_user_can( Case_Engine_RBAC::EDIT_CASES );
	$back_url = remove_query_arg( array( 'view_case', 'edit' ), admin_url( 'admin.php?page=case-engine' ) );
	$view_url = add_query_arg( 'view_case', $case_id, admin_url( 'admin.php?page=case-engine' ) );
	$edit_url = add_query_arg( array( 'view_case' => $case_id, 'edit' => 1 ), admin_url( 'admin.php?page=case-engine' ) );

	echo '<div class="wrap">';
	echo '<h1>' . esc_html__( 'Case', 'case-engine' ) . ' #' . $case_id . '</h1>';
	if ( isset( $_GET['updated'] ) ) {
		echo '<div class="notice notice-success"><p>' . esc_html__( 'Case updated.', 'case-engine' ) . '</p></div>';
	}
	$delete_confirm = esc_js( __( 'Permanently delete this case and all party data?', 'case-engine' ) );
	echo '<div class="case-engine-action-bar" style="display:flex;flex-wrap:nowrap;align-items:center;gap:8px;margin-bottom:1em;">';
	echo '<a href="' . esc_url( $back_url ) . '" class="button">← ' . esc_html__( 'Back to Case Engine', 'case-engine' ) . '</a>';
	if ( $can_edit && ! $is_edit ) {
		echo '<a href="' . esc_url( $edit_url ) . '" class="button button-primary">' . esc_html__( 'Edit case', 'case-engine' ) . '</a>';
		echo '<form method="post" action="" style="display:inline-block;margin:0;" onsubmit="return confirm(\'' . $delete_confirm . '\');">';
		wp_nonce_field( 'case_engine_delete_case' );
		echo '<input type="hidden" name="case_engine_delete_case" value="1" /><input type="hidden" name="case_id" value="' . esc_attr( $case_id ) . '" />';
		echo '<input type="submit" name="submit" class="button" value="' . esc_attr__( 'Delete case', 'case-engine' ) . '" style="margin:0;" /></form>';
	}
	if ( $can_edit && $is_edit ) {
		echo '<a href="' . esc_url( $view_url ) . '" class="button">' . esc_html__( 'Cancel', 'case-engine' ) . '</a>';
		echo '<form method="post" action="" style="display:inline-block;margin:0;" onsubmit="return confirm(\'' . $delete_confirm . '\');">';
		wp_nonce_field( 'case_engine_delete_case' );
		echo '<input type="hidden" name="case_engine_delete_case" value="1" /><input type="hidden" name="case_id" value="' . esc_attr( $case_id ) . '" />';
		echo '<input type="submit" name="submit" class="button" value="' . esc_attr__( 'Delete case', 'case-engine' ) . '" style="margin:0;" /></form>';
	}
	echo '</div>';

	if ( $is_edit && $can_edit ) {
		// Edit form: case fields + parties.
		echo '<form method="post" action="" id="case-engine-edit-form">';
		wp_nonce_field( 'case_engine_edit_case' );
		echo '<input type="hidden" name="case_engine_edit_case" value="1" /><input type="hidden" name="case_id" value="' . esc_attr( $case_id ) . '" />';

		echo '<h2>' . esc_html__( 'Case details', 'case-engine' ) . '</h2>';
		echo '<table class="form-table"><tbody>';
		echo '<tr><th scope="row"><label for="county">' . esc_html__( 'County', 'case-engine' ) . '</label></th><td><input type="text" id="county" name="county" value="' . esc_attr( $case['county'] ?? '' ) . '" class="regular-text" /></td></tr>';
		echo '<tr><th scope="row"><label for="has_children">' . esc_html__( 'Has children', 'case-engine' ) . '</label></th><td><select id="has_children" name="has_children"><option value="no"' . selected( $case['has_children'] ?? '', 'no', false ) . '>' . esc_html__( 'No', 'case-engine' ) . '</option><option value="yes"' . selected( $case['has_children'] ?? '', 'yes', false ) . '>' . esc_html__( 'Yes', 'case-engine' ) . '</option></select></td></tr>';
		echo '<tr><th scope="row"><label for="filing_date">' . esc_html__( 'Filing date', 'case-engine' ) . '</label></th><td><input type="date" id="filing_date" name="filing_date" value="' . esc_attr( $case['filing_date'] ?? '' ) . '" /></td></tr>';
		echo '<tr><th scope="row"><label for="role">' . esc_html__( 'Role', 'case-engine' ) . '</label></th><td><select id="role" name="role"><option value="petitioner"' . selected( $case['role'] ?? '', 'petitioner', false ) . '>' . esc_html__( 'Petitioner', 'case-engine' ) . '</option><option value="joint"' . selected( $case['role'] ?? '', 'joint', false ) . '>' . esc_html__( 'Joint filing', 'case-engine' ) . '</option></select></td></tr>';
		echo '<tr><th scope="row"><label for="case_status">' . esc_html__( 'Status', 'case-engine' ) . '</label></th><td><select id="case_status" name="case_status">';
		foreach ( array( 'pending_payment', 'paid', 'in_progress', 'completed', 'cancelled' ) as $s ) {
			echo '<option value="' . esc_attr( $s ) . '"' . selected( $case['status'] ?? '', $s, false ) . '>' . esc_html( $s ) . '</option>';
		}
		echo '</select></td></tr>';
		echo '</tbody></table>';

		echo '<h3>' . esc_html__( 'Parties', 'case-engine' ) . '</h3>';
		echo '<table class="widefat striped" id="case-engine-parties-table"><thead><tr>';
		echo '<th>' . esc_html__( 'Type', 'case-engine' ) . '</th><th>' . esc_html__( 'Full name', 'case-engine' ) . '</th><th>' . esc_html__( 'Address', 'case-engine' ) . '</th><th>' . esc_html__( 'Phone', 'case-engine' ) . '</th><th>' . esc_html__( 'Email', 'case-engine' ) . '</th><th>' . esc_html__( 'DOB', 'case-engine' ) . '</th><th>' . esc_html__( 'Relationship', 'case-engine' ) . '</th></tr></thead><tbody>';
		$idx = 0;
		foreach ( $parties as $p ) {
			echo '<tr><td><select name="parties[' . $idx . '][party_type]"><option value="petitioner"' . selected( $p['party_type'] ?? '', 'petitioner', false ) . '>' . esc_html__( 'Petitioner', 'case-engine' ) . '</option><option value="respondent"' . selected( $p['party_type'] ?? '', 'respondent', false ) . '>' . esc_html__( 'Respondent', 'case-engine' ) . '</option><option value="child"' . selected( $p['party_type'] ?? '', 'child', false ) . '>' . esc_html__( 'Child', 'case-engine' ) . '</option></select></td>';
			echo '<td><input type="text" name="parties[' . $idx . '][full_name]" value="' . esc_attr( $p['full_name'] ?? '' ) . '" class="regular-text" /></td>';
			echo '<td><input type="text" name="parties[' . $idx . '][address]" value="' . esc_attr( $p['address'] ?? '' ) . '" class="regular-text" /></td>';
			echo '<td><input type="text" name="parties[' . $idx . '][phone]" value="' . esc_attr( $p['phone'] ?? '' ) . '" /></td>';
			echo '<td><input type="email" name="parties[' . $idx . '][email]" value="' . esc_attr( $p['email'] ?? '' ) . '" class="regular-text" /></td>';
			echo '<td><input type="date" name="parties[' . $idx . '][dob]" value="' . esc_attr( $p['dob'] ?? '' ) . '" /></td>';
			echo '<td><input type="text" name="parties[' . $idx . '][relationship]" value="' . esc_attr( $p['relationship'] ?? '' ) . '" /></td></tr>';
			$idx++;
		}
		echo '</tbody></table>';
		echo '<p><button type="button" class="button" id="case-engine-add-party">' . esc_html__( 'Add party', 'case-engine' ) . '</button></p>';

		submit_button( __( 'Save changes', 'case-engine' ), 'primary', 'submit', false );
		echo '</form>';

		// JS: Add party row.
		echo '<script>document.addEventListener("DOMContentLoaded",function(){var t=document.getElementById("case-engine-parties-table").getElementsByTagName("tbody")[0],b=document.getElementById("case-engine-add-party"),n=t.getElementsByTagName("tr").length;b&&b.addEventListener("click",function(){var r=t.insertRow(-1);r.innerHTML=\'<td><select name="parties[\'+n+\'][party_type]"><option value="petitioner">Petitioner</option><option value="respondent">Respondent</option><option value="child">Child</option></select></td><td><input type="text" name="parties[\'+n+\'][full_name]" class="regular-text" /></td><td><input type="text" name="parties[\'+n+\'][address]" class="regular-text" /></td><td><input type="text" name="parties[\'+n+\'][phone]" /></td><td><input type="email" name="parties[\'+n+\'][email]" class="regular-text" /></td><td><input type="date" name="parties[\'+n+\'][dob]" /></td><td><input type="text" name="parties[\'+n+\'][relationship]" /></td>\';n++;});});</script>';
	} else {
		// Read-only view.
		echo '<h2>' . esc_html__( 'Case details', 'case-engine' ) . '</h2>';
		echo '<table class="widefat striped"><tbody>';
		echo '<tr><th>' . esc_html__( 'Status', 'case-engine' ) . '</th><td>' . esc_html( $case['status'] ) . '</td></tr>';
		echo '<tr><th>' . esc_html__( 'County', 'case-engine' ) . '</th><td>' . esc_html( $case['county'] ) . '</td></tr>';
		echo '<tr><th>' . esc_html__( 'Role', 'case-engine' ) . '</th><td>' . esc_html( $case['role'] ) . '</td></tr>';
		echo '<tr><th>' . esc_html__( 'Has children', 'case-engine' ) . '</th><td>' . esc_html( $case['has_children'] ) . '</td></tr>';
		echo '<tr><th>' . esc_html__( 'Filing date', 'case-engine' ) . '</th><td>' . esc_html( $case['filing_date'] ?? '' ) . '</td></tr>';
		echo '<tr><th>' . esc_html__( 'Intake session', 'case-engine' ) . '</th><td>' . (int) ( $case['intake_session_id'] ?? 0 ) . '</td></tr>';
		$owner_id = (int) ( $case['user_id'] ?? 0 ) ? (int) $case['user_id'] : (int) ( $case['session_user_id'] ?? 0 );
		echo '<tr><th>' . esc_html__( 'Owner (user ID)', 'case-engine' ) . '</th><td>' . ( $owner_id ? $owner_id : '—' ) . '</td></tr>';
		echo '<tr><th>' . esc_html__( 'Created', 'case-engine' ) . '</th><td>' . esc_html( $case['created_at'] ?? '' ) . '</td></tr>';
		echo '</tbody></table>';

		// Quick status update (optional).
		if ( $can_edit ) {
			echo '<h3>' . esc_html__( 'Update status', 'case-engine' ) . '</h3>';
			echo '<form method="post" action="">';
			wp_nonce_field( 'case_engine_update_case' );
			echo '<input type="hidden" name="case_engine_update_case" value="1" /><input type="hidden" name="case_id" value="' . esc_attr( $case_id ) . '" />';
			echo '<select name="case_status">';
			foreach ( array( 'pending_payment', 'paid', 'in_progress', 'completed', 'cancelled' ) as $s ) {
				echo '<option value="' . esc_attr( $s ) . '"' . selected( $case['status'], $s, false ) . '>' . esc_html( $s ) . '</option>';
			}
			echo '</select> ';
			submit_button( __( 'Update status', 'case-engine' ), 'primary', 'submit', false );
			echo '</form>';
		}

		echo '<h3>' . esc_html__( 'Parties', 'case-engine' ) . '</h3>';
		if ( empty( $parties ) ) {
			echo '<p>' . esc_html__( 'No party information.', 'case-engine' ) . '</p>';
			if ( $can_edit ) {
				echo '<p><a href="' . esc_url( $edit_url ) . '" class="button button-primary">' . esc_html__( 'Add parties', 'case-engine' ) . '</a> ';
				echo '<span class="description">' . esc_html__( 'Add petitioner, respondent, and children in the edit form.', 'case-engine' ) . '</span></p>';
			}
		} else {
			echo '<table class="widefat striped"><thead><tr><th>' . esc_html__( 'Type', 'case-engine' ) . '</th><th>' . esc_html__( 'Name', 'case-engine' ) . '</th><th>' . esc_html__( 'Contact', 'case-engine' ) . '</th></tr></thead><tbody>';
			foreach ( $parties as $p ) {
				$contact = implode( ', ', array_filter( array( $p['phone'] ?? '', $p['email'] ?? '' ) ) );
				echo '<tr><td>' . esc_html( $p['party_type'] ?? '' ) . '</td><td>' . esc_html( $p['full_name'] ?? '' ) . '</td><td>' . esc_html( $contact ) . '</td></tr>';
			}
			echo '</tbody></table>';
		}
	}
	echo '</div>';
}
