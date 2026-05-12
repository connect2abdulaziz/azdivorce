<?php
/**
 * Questionnaire Controller — registers assets, shortcode, and hooks into the
 * client dashboard to inject the "Start / Continue Questionnaire" CTA.
 *
 * Shortcode: [az_divorce_questionnaire case_id="123"]
 * Also auto-injects into [az_client_dashboard] via a filter on the rendered HTML.
 *
 * @package Case_Engine
 */

defined( 'ABSPATH' ) || exit;

class Case_Engine_Questionnaire_Controller {

	public static function register() {
		add_shortcode( 'az_divorce_questionnaire', array( __CLASS__, 'render_shortcode' ) );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'enqueue_assets' ) );
		// Inject questionnaire CTA into the dashboard case detail view.
		add_filter( 'az_client_dashboard_case_detail_after', array( __CLASS__, 'inject_questionnaire_cta' ), 10, 2 );
	}

	// ── Asset enqueuing ──────────────────────────────────────────────────────

	public static function enqueue_assets() {
		if ( ! self::is_questionnaire_page() ) {
			return;
		}

		wp_enqueue_style(
			'az-questionnaire',
			CASE_ENGINE_PLUGIN_URL . 'assets/questionnaire.css',
			array(),
			CASE_ENGINE_VERSION
		);

		wp_enqueue_script(
			'az-questionnaire',
			CASE_ENGINE_PLUGIN_URL . 'assets/questionnaire.js',
			array( 'jquery' ),
			CASE_ENGINE_VERSION,
			true
		);

		wp_localize_script( 'az-questionnaire', 'azQuestionnaire', array(
			'ajaxUrl'  => admin_url( 'admin-ajax.php' ),
			'nonce'    => wp_create_nonce( 'az_questionnaire_nonce' ),
			'i18n'     => array(
				'saving'      => __( 'Saving…', 'case-engine' ),
				'saved'       => __( 'Saved', 'case-engine' ),
				'saveError'   => __( 'Save failed. Please try again.', 'case-engine' ),
				'required'    => __( 'This field is required.', 'case-engine' ),
				'invalidDate' => __( 'Please enter a valid date (YYYY-MM-DD).', 'case-engine' ),
				'invalidEmail'=> __( 'Please enter a valid email address.', 'case-engine' ),
			),
			'steps'    => self::get_step_labels(),
			'totalSteps' => 7,
		) );
	}

	// ── Shortcode ────────────────────────────────────────────────────────────

	/**
	 * [az_divorce_questionnaire case_id="123"]
	 *
	 * @param array $atts Shortcode attributes.
	 * @return string HTML output.
	 */
	public static function render_shortcode( $atts ) {
		$atts = shortcode_atts( array( 'case_id' => 0 ), $atts, 'az_divorce_questionnaire' );
		$case_id = (int) $atts['case_id'];

		if ( ! is_user_logged_in() ) {
			return '<div class="az-q-notice az-q-notice--warning"><p>' .
				esc_html__( 'Please log in to access your questionnaire.', 'case-engine' ) .
				'</p></div>';
		}

		if ( ! $case_id ) {
			$case_id = isset( $_GET['case_id'] ) ? (int) $_GET['case_id'] : 0;
		}

		if ( ! $case_id ) {
			return '<div class="az-q-notice az-q-notice--warning"><p>' .
				esc_html__( 'No case specified.', 'case-engine' ) .
				'</p></div>';
		}

		$user_id = get_current_user_id();

		// Ownership check.
		$case = Case_Engine_Client_Dashboard::get_case_for_user( $case_id, $user_id );
		if ( ! $case ) {
			return '<div class="az-q-notice az-q-notice--error"><p>' .
				esc_html__( 'Case not found or access denied.', 'case-engine' ) .
				'</p></div>';
		}

		// Case must be paid to access questionnaire.
		if ( ! Case_Engine_Client_Dashboard::case_can_access_documents( $case ) ) {
			return '<div class="az-q-notice az-q-notice--warning"><p>' .
				esc_html__( 'Your questionnaire will be available after payment is confirmed.', 'case-engine' ) .
				'</p></div>';
		}

		// Load existing data.
		$existing = Case_Engine_Questionnaire_DB::get( $user_id, $case_id );

		return self::render_wizard( $case_id, $case, $existing );
	}

	// ── Dashboard CTA injection ──────────────────────────────────────────────

	/**
	 * Appended to client dashboard case detail via filter.
	 * Renders a CTA card linking to or embedding the questionnaire.
	 *
	 * @param string $html    Existing case detail HTML.
	 * @param array  $context Array with 'case' and 'case_id' keys.
	 * @return string
	 */
	public static function inject_questionnaire_cta( $html, $context ) {
		$case    = $context['case'] ?? array();
		$case_id = (int) ( $context['case_id'] ?? 0 );

		// Only show questionnaire CTA when the case is paid and questionnaire is not yet complete.
		if ( ! $case_id || ! Case_Engine_Client_Dashboard::case_can_access_documents( $case ) ) {
			return $html;
		}

		// If questionnaire is already marked complete in the context, skip the CTA card.
		$q_status = $context['questionnaire_status'] ?? null;
		if ( $q_status === 'completed' ) {
			return $html;
		}

		$user_id  = get_current_user_id();
		$existing = Case_Engine_Questionnaire_DB::get( $user_id, $case_id );
		$q_url    = add_query_arg( array( 'case_id' => $case_id ), get_permalink() );

		ob_start();
		?>
		<div class="az-client-dashboard__card az-q-cta-card">
			<h2 class="az-client-dashboard__title">
				<?php esc_html_e( 'Divorce Intake Questionnaire', 'case-engine' ); ?>
			</h2>
			<?php if ( $existing && (int) $existing['is_complete'] === 1 ) : ?>
				<p class="az-q-cta-status az-q-cta-status--complete">
					&#10003; <?php esc_html_e( 'Questionnaire complete.', 'case-engine' ); ?>
				</p>
				<a href="<?php echo esc_url( $q_url ); ?>" class="az-intake-btn az-intake-btn-secondary">
					<?php esc_html_e( 'Review / Edit Answers', 'case-engine' ); ?>
				</a>
			<?php elseif ( $existing ) : ?>
				<?php
				$completed = array_filter( explode( ',', $existing['completed_steps'] ?? '' ) );
				$pct = min( 100, round( ( count( $completed ) / 7 ) * 100 ) );
				?>
				<p><?php esc_html_e( 'You have a questionnaire in progress.', 'case-engine' ); ?></p>
				<div class="az-q-mini-progress">
					<div class="az-q-mini-progress__bar" style="width:<?php echo (int) $pct; ?>%"></div>
				</div>
				<p class="az-q-mini-progress__label">
					<?php printf( esc_html__( '%d of 7 steps completed', 'case-engine' ), count( $completed ) ); ?>
				</p>
				<a href="<?php echo esc_url( $q_url ); ?>" class="az-intake-btn az-intake-btn-primary">
					<?php esc_html_e( 'Continue Questionnaire', 'case-engine' ); ?>
				</a>
			<?php else : ?>
				<p><?php esc_html_e( 'Complete your divorce intake questionnaire to prepare your documents.', 'case-engine' ); ?></p>
				<a href="<?php echo esc_url( $q_url ); ?>" class="az-intake-btn az-intake-btn-primary">
					<?php esc_html_e( 'Start Questionnaire', 'case-engine' ); ?>
				</a>
			<?php endif; ?>
		</div>
		<?php
		return $html . ob_get_clean();
	}

	// ── Wizard renderer ──────────────────────────────────────────────────────

	/**
	 * Render the full multi-step wizard HTML.
	 *
	 * @param int        $case_id
	 * @param array      $case     Case row.
	 * @param array|null $existing Existing questionnaire row (or null).
	 * @return string
	 */
	private static function render_wizard( $case_id, $case, $existing ) {
		$completed_steps = array();
		$current_step    = 1;
		if ( $existing ) {
			$completed_steps = array_filter( explode( ',', $existing['completed_steps'] ?? '' ) );
			$completed_steps = array_map( 'intval', $completed_steps );
			$current_step    = max( 1, min( 7, (int) ( $existing['current_step'] ?? 1 ) ) );
		}

		// Decode JSON fields so the JS can pre-populate them.
		$prefill = array();
		if ( $existing ) {
			$prefill = $existing;
			foreach ( Case_Engine_Questionnaire_DB::json_columns() as $col ) {
				if ( isset( $prefill[ $col ] ) && is_string( $prefill[ $col ] ) ) {
					$decoded = json_decode( $prefill[ $col ], true );
					$prefill[ $col ] = is_array( $decoded ) ? $decoded : array();
				}
			}
		}

		$dashboard_url = remove_query_arg( array( 'case_id', 'view_case' ), get_permalink() );
		$back_url      = add_query_arg( 'view_case', $case_id, $dashboard_url );

		ob_start();
		require CASE_ENGINE_PLUGIN_DIR . 'public/questionnaire-wizard.php';
		return ob_get_clean();
	}

	// ── Helpers ──────────────────────────────────────────────────────────────

	/**
	 * Determine if we should enqueue assets on the current page.
	 *
	 * @return bool
	 */
	private static function is_questionnaire_page() {
		// Dashboard page (shortcode lives there).
		if ( is_page( 'client-dashboard' ) ) {
			return true;
		}
		// Any page containing our shortcode.
		global $post;
		if ( $post && has_shortcode( $post->post_content, 'az_divorce_questionnaire' ) ) {
			return true;
		}
		return false;
	}

	/**
	 * Step labels for progress stepper.
	 *
	 * @return array<int,string>
	 */
	public static function get_step_labels() {
		return array(
			1 => __( 'Your Info', 'case-engine' ),
			2 => __( 'Spouse Info', 'case-engine' ),
			3 => __( 'Marriage', 'case-engine' ),
			4 => __( 'Filing', 'case-engine' ),
			5 => __( 'Property & Debt', 'case-engine' ),
			6 => __( 'Name Change', 'case-engine' ),
			7 => __( 'Service', 'case-engine' ),
		);
	}
}
