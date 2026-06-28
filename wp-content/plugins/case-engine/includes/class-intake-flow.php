<?php
/**
 * Intake flow: 12 screens, gates, and rendering.
 *
 * @package Case_Engine
 */

defined( 'ABSPATH' ) || exit;

class Case_Engine_Intake_Flow {

	const SHORTCODE = 'az_intake';
	const TOTAL_SCREENS = 12;

	/**
	 * Register shortcode and assets.
	 */
	public static function register() {
		add_shortcode( self::SHORTCODE, array( __CLASS__, 'render' ) );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'enqueue_assets' ) );
		add_action( 'send_headers', array( __CLASS__, 'maybe_send_nocache_for_intake_page' ) );
		add_filter( 'epc_exempt_uri_contains', array( __CLASS__, 'exclude_intake_from_page_cache' ) );
		// Login is now required only at the payment step; guests may start and fill the intake form freely.
	}

	/**
	 * Do not cache intake URLs — cached HTML embeds a stale AJAX nonce and saves return -1 / 403.
	 *
	 * @param array $exempt URI fragments that skip Endurance Page Cache.
	 * @return array
	 */
	public static function exclude_intake_from_page_cache( $exempt ) {
		if ( ! is_array( $exempt ) ) {
			$exempt = array();
		}
		$exempt[] = 'start-your-divorce';
		$exempt[] = 'az_sk=';
		return $exempt;
	}

	/**
	 * Whether the post may render the intake shortcode (post content, raw bracket text, or Elementor JSON).
	 *
	 * @param int $post_id Post ID.
	 * @return bool
	 */
	private static function post_may_contain_intake( $post_id ) {
		$post_id = (int) $post_id;
		if ( $post_id <= 0 ) {
			return false;
		}
		$content = (string) get_post_field( 'post_content', $post_id );
		if ( has_shortcode( $content, self::SHORTCODE ) ) {
			return true;
		}
		if ( strpos( $content, '[' . self::SHORTCODE ) !== false ) {
			return true;
		}
		$elementor = get_post_meta( $post_id, '_elementor_data', true );
		if ( is_string( $elementor ) && $elementor !== '' ) {
			if ( strpos( $elementor, self::SHORTCODE ) !== false || strpos( $elementor, '[' . self::SHORTCODE . ']' ) !== false ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Avoid full-page caches serving HTML with another user's (or stale) AJAX nonce — breaks intake saves with 403 / "-1".
	 */
	public static function maybe_send_nocache_for_intake_page() {
		if ( ! is_singular() ) {
			return;
		}
		$post_id = (int) get_queried_object_id();
		if ( ! $post_id || ! self::post_may_contain_intake( $post_id ) ) {
			return;
		}
		if ( ! headers_sent() ) {
			nocache_headers();
			header( 'Vary: Cookie', false );
		}
	}

	/**
	 * Enqueue intake CSS/JS.
	 */
	public static function enqueue_assets() {
		if ( ! is_singular() ) {
			return;
		}
		$post_id = (int) get_queried_object_id();
		if ( ! $post_id || ! self::post_may_contain_intake( $post_id ) ) {
			return;
		}
		wp_enqueue_style(
			'case-engine-intake',
			CASE_ENGINE_PLUGIN_URL . 'assets/intake.css',
			array(),
			CASE_ENGINE_VERSION
		);
		wp_enqueue_script(
			'case-engine-intake',
			CASE_ENGINE_PLUGIN_URL . 'assets/intake.js',
			array( 'jquery' ),
			CASE_ENGINE_VERSION,
			true
		);
		$localize = array(
			'ajaxUrl' => admin_url( 'admin-ajax.php' ),
			'nonce'   => wp_create_nonce( 'az_intake' ),
			'total'   => self::TOTAL_SCREENS,
		);
		wp_localize_script( 'case-engine-intake', 'caseEngineIntake', $localize );
	}

	/**
	 * Get screen definitions (content + gate logic).
	 *
	 * @return array[]
	 */
	public static function get_screens() {
		return array(
			1  => array(
				'title'   => __( 'Welcome & Disclosure', 'case-engine' ),
				'type'    => 'disclosure',
				'gate'    => 'checkbox',
				'message' => __( 'These tools help you complete official Arizona court forms.', 'case-engine' ) . "\n" .
					__( 'We are not attorneys and do not provide legal advice.', 'case-engine' ) . "\n" .
					__( 'If you may need representation or your case could be contested, contact a lawyer.', 'case-engine' ),
				'label'   => __( 'I understand and wish to continue', 'case-engine' ),
			),
			2  => array(
				'title'   => __( 'Eligibility: Agreement Check', 'case-engine' ),
				'type'    => 'radio',
				'key'     => 'agreement_check',
				'gate'    => 'allow_only',
				'allow'   => array( 'yes' ),
				'question' => __( 'Are you and your spouse in agreement on all issues related to your divorce (property, debts, support, and parenting time, if applicable)?', 'case-engine' ),
				'options' => array(
					'yes' => __( 'Yes, we agree on everything', 'case-engine' ),
					'no'  => __( 'No, we disagree', 'case-engine' ),
					'unsure' => __( "I'm not sure", 'case-engine' ),
				),
				'stop_message' => __( 'This service only supports uncontested divorces.', 'case-engine' ),
			),
			3  => array(
				'title'   => __( 'Eligibility: Response Filed', 'case-engine' ),
				'type'    => 'radio',
				'key'     => 'response_filed',
				'gate'    => 'allow_only',
				'allow'   => array( 'no' ),
				'question' => __( 'Has your spouse filed a response or objection with the court?', 'case-engine' ),
				'options' => array(
					'no'  => __( 'No', 'case-engine' ),
					'yes' => __( 'Yes', 'case-engine' ),
					'unknown' => __( "I don't know", 'case-engine' ),
				),
				'stop_message' => __( 'This service only supports uncontested divorces.', 'case-engine' ),
			),
			4  => array(
				'title'   => __( 'Basic Case Info', 'case-engine' ),
				'type'    => 'case_info',
				'keys'    => array( 'county', 'has_children', 'filing_date', 'role' ),
				'county_default' => 'Maricopa',
				'role_options' => array(
					'petitioner' => __( 'Petitioner', 'case-engine' ),
					'joint'      => __( 'Joint filing', 'case-engine' ),
				),
			),
			5  => array(
				'title'   => __( 'Issue-Specific Agreement Checks', 'case-engine' ),
				'type'    => 'issue_checks',
				'keys'    => array( 'property_agreement', 'children_agreement', 'spousal_agreement' ),
				'stop_message' => __( 'This service only supports uncontested divorces.', 'case-engine' ),
			),
			6  => array(
				'title'   => __( 'Future Dispute Acknowledgment', 'case-engine' ),
				'type'    => 'checkbox',
				'key'     => 'future_dispute_ack',
				'gate'    => 'required',
				'message' => __( 'If your spouse later disagrees or files a response, do you understand this service cannot continue to automate your case?', 'case-engine' ),
				'label'   => __( 'Yes, I understand', 'case-engine' ),
			),
			7  => array(
				'title'   => __( 'Party Information (Petitioner)', 'case-engine' ),
				'type'    => 'party',
				'party'   => 'petitioner',
				'fields'  => array( 'full_name', 'address', 'phone', 'email', 'dob' ),
			),
			8  => array(
				'title'   => __( 'Party Information (Respondent)', 'case-engine' ),
				'type'    => 'party',
				'party'   => 'respondent',
				'fields'  => array( 'full_name', 'last_known_address', 'phone', 'email' ),
				'help'    => __( "If you don't know this information, you may leave it blank.", 'case-engine' ),
			),
			9  => array(
				'title'   => __( 'Children Information', 'case-engine' ),
				'type'    => 'children',
				'fields'  => array( 'full_name', 'dob', 'relationship' ),
			),
			10 => array(
				'title'   => __( 'Review & Confirmation', 'case-engine' ),
				'type'    => 'review',
				'label'   => __( 'I confirm the above information is accurate to the best of my knowledge.', 'case-engine' ),
			),
			11 => array(
				'title'   => __( 'Payment', 'case-engine' ),
				'type'    => 'payment',
			),
			12 => array(
				'title'   => __( 'Next Steps', 'case-engine' ),
				'type'    => 'next_steps',
			),
		);
	}

	/**
	 * Check if a screen is a gate that can STOP the flow.
	 *
	 * @param int   $screen_num Screen number.
	 * @param array $answers    Current answers (keyed).
	 * @return array{ 'can_proceed': bool, 'stop_message'?: string }
	 */
	public static function evaluate_gate( $screen_num, $answers ) {
		$screens = self::get_screens();
		if ( ! isset( $screens[ $screen_num ] ) ) {
			return array( 'can_proceed' => true );
		}
		$screen = $screens[ $screen_num ];

		switch ( $screen['type'] ) {
			case 'disclosure':
			case 'checkbox':
				$key = isset( $screen['key'] ) ? $screen['key'] : 'disclosure_ack';
				$val = isset( $answers[ $key ] ) ? $answers[ $key ] : '';
				$ok  = ( $val === '1' || $val === true || $val === 'yes' );
				return array(
					'can_proceed' => $ok,
					'stop_message' => $ok ? null : ( $screen['stop_message'] ?? __( 'You must accept to continue.', 'case-engine' ) ),
				);
			case 'radio':
				// Eligibility answers are collected for the file; we no longer block the flow here.
				return array( 'can_proceed' => true );
			case 'issue_checks':
				// Issue agreement answers are informational; contested cases are addressed on the dashboard.
				return array( 'can_proceed' => true );
			default:
				return array( 'can_proceed' => true );
		}
	}

	/**
	 * Render shortcode: full 12-screen form (one container, steps shown/hidden by JS).
	 */
	public static function render( $atts ) {
		if ( ! headers_sent() ) {
			nocache_headers();
			header( 'Vary: Cookie', false );
		}
		self::enqueue_assets();
		$screens = self::get_screens();
		ob_start();
		?>
		<div id="az-intake" class="az-intake" data-current="1" data-ajax-url="<?php echo esc_url( admin_url( 'admin-ajax.php' ) ); ?>" data-nonce="<?php echo esc_attr( wp_create_nonce( 'az_intake' ) ); ?>" data-total="<?php echo (int) self::TOTAL_SCREENS; ?>">
			<div class="az-intake-card">
				<div class="az-intake-progress" aria-hidden="true"><span class="az-intake-progress-text">Step <span class="az-intake-step-current">1</span> of <?php echo (int) self::TOTAL_SCREENS; ?></span><div class="az-intake-progress-bar"><span class="az-intake-progress-fill" style="width:<?php echo round( 100 / self::TOTAL_SCREENS ); ?>%"></span></div></div>
				<?php foreach ( $screens as $num => $screen ) : ?>
				<div class="az-intake-screen" data-screen="<?php echo (int) $num; ?>" id="az-intake-screen-<?php echo (int) $num; ?>" role="region" aria-labelledby="az-intake-title-<?php echo (int) $num; ?>">
					<p class="az-intake-step-badge">Step <?php echo (int) $num; ?> of <?php echo (int) self::TOTAL_SCREENS; ?></p>
					<h2 id="az-intake-title-<?php echo (int) $num; ?>" class="az-intake-screen-title"><?php echo esc_html( $screen['title'] ); ?></h2>
					<?php self::render_screen_content( $num, $screen ); ?>
					<div class="az-intake-screen-actions">
						<?php if ( $num > 1 && $num < 12 ) : ?>
							<button type="button" class="az-intake-btn az-intake-btn-prev" data-prev="<?php echo (int) $num; ?>"><?php esc_html_e( 'Previous', 'case-engine' ); ?></button>
						<?php endif; ?>
						<?php if ( $num < 12 ) : ?>
							<button type="button" class="az-intake-btn az-intake-btn-next" data-next="<?php echo (int) $num; ?>"><?php esc_html_e( 'Continue', 'case-engine' ); ?></button>
						<?php endif; ?>
					</div>
					<div class="az-intake-stop-message" role="alert" aria-live="polite" hidden></div>
				</div>
				<?php endforeach; ?>
			</div>
		</div>
		<?php
		return ob_get_clean();
	}

	/**
	 * Output HTML for each screen type.
	 */
	private static function render_screen_content( $num, $screen ) {
		$type = $screen['type'];
		?>
		<div class="az-intake-screen-body">
		<?php
		switch ( $type ) {
			case 'disclosure':
				echo '<div class="az-intake-disclosure-box"><p class="az-intake-disclosure">' . esc_html( $screen['message'] ) . '</p></div>';
				echo '<label class="az-intake-checkbox-label"><input type="checkbox" name="disclosure_ack" value="1" required class="az-intake-checkbox" /> <span class="az-intake-checkbox-text">' . esc_html( $screen['label'] ) . '</span></label>';
				break;
			case 'radio':
				echo '<p class="az-intake-question">' . esc_html( $screen['question'] ) . '</p>';
				echo '<div class="az-intake-options" role="radiogroup" aria-label="' . esc_attr( $screen['question'] ) . '">';
				foreach ( $screen['options'] as $value => $label ) {
					echo '<label class="az-intake-option"><input type="radio" name="' . esc_attr( $screen['key'] ) . '" value="' . esc_attr( $value ) . '" required class="az-intake-radio" /> <span class="az-intake-option-text">' . esc_html( $label ) . '</span></label>';
				}
				echo '</div>';
				break;
			case 'case_info':
				?>
				<div class="az-intake-form-fields">
					<div class="az-intake-form-group"><label><span class="az-intake-label-text"><?php esc_html_e( 'County', 'case-engine' ); ?></span> <input type="text" name="county" value="<?php echo esc_attr( $screen['county_default'] ?? '' ); ?>" /></label></div>
					<div class="az-intake-form-group"><label><span class="az-intake-label-text"><?php esc_html_e( 'Are there minor children?', 'case-engine' ); ?></span>
						<select name="has_children">
							<option value="no"><?php esc_html_e( 'No', 'case-engine' ); ?></option>
							<option value="yes"><?php esc_html_e( 'Yes', 'case-engine' ); ?></option>
						</select></label></div>
					<div class="az-intake-form-group"><label><span class="az-intake-label-text"><?php esc_html_e( 'Approximate filing date (optional)', 'case-engine' ); ?></span> <input type="date" name="filing_date" /></label></div>
					<div class="az-intake-form-group"><label><span class="az-intake-label-text"><?php esc_html_e( 'Your role', 'case-engine' ); ?></span>
						<select name="role">
							<?php foreach ( ( $screen['role_options'] ?? array() ) as $val => $lbl ) : ?>
								<option value="<?php echo esc_attr( $val ); ?>"><?php echo esc_html( $lbl ); ?></option>
							<?php endforeach; ?>
						</select></label></div>
				</div>
				<?php
				break;
			case 'issue_checks':
				?>
				<p class="az-intake-question"><?php esc_html_e( 'Property & Debts: Are you and your spouse in agreement on how all property and debts will be divided?', 'case-engine' ); ?></p>
				<div class="az-intake-options" role="radiogroup"><label class="az-intake-option"><input type="radio" name="property_agreement" value="yes" required class="az-intake-radio" /> <span class="az-intake-option-text"><?php esc_html_e( 'Yes', 'case-engine' ); ?></span></label> <label class="az-intake-option"><input type="radio" name="property_agreement" value="no" class="az-intake-radio" /> <span class="az-intake-option-text"><?php esc_html_e( 'No', 'case-engine' ); ?></span></label></div>
				<div class="az-intake-children-agreement" style="display:none;">
					<p class="az-intake-question"><?php esc_html_e( 'Children: Are you and your spouse in agreement on legal decision-making, parenting time, and child support?', 'case-engine' ); ?></p>
					<div class="az-intake-options" role="radiogroup"><label class="az-intake-option"><input type="radio" name="children_agreement" value="yes" class="az-intake-radio" /> <span class="az-intake-option-text"><?php esc_html_e( 'Yes', 'case-engine' ); ?></span></label> <label class="az-intake-option"><input type="radio" name="children_agreement" value="no" class="az-intake-radio" /> <span class="az-intake-option-text"><?php esc_html_e( 'No', 'case-engine' ); ?></span></label></div>
				</div>
				<p class="az-intake-question"><?php esc_html_e( 'Spousal Maintenance: Are you and your spouse in agreement regarding spousal maintenance (alimony)?', 'case-engine' ); ?></p>
				<div class="az-intake-options" role="radiogroup"><label class="az-intake-option"><input type="radio" name="spousal_agreement" value="yes" required class="az-intake-radio" /> <span class="az-intake-option-text"><?php esc_html_e( 'Yes', 'case-engine' ); ?></span></label> <label class="az-intake-option"><input type="radio" name="spousal_agreement" value="no" class="az-intake-radio" /> <span class="az-intake-option-text"><?php esc_html_e( 'No', 'case-engine' ); ?></span></label> <label class="az-intake-option"><input type="radio" name="spousal_agreement" value="na" class="az-intake-radio" /> <span class="az-intake-option-text"><?php esc_html_e( 'Not applicable', 'case-engine' ); ?></span></label></div>
				<?php
				break;
			case 'checkbox':
				echo '<p class="az-intake-question">' . esc_html( $screen['message'] ) . '</p>';
				echo '<label class="az-intake-checkbox-label"><input type="checkbox" name="' . esc_attr( $screen['key'] ) . '" value="1" required class="az-intake-checkbox" /> <span class="az-intake-checkbox-text">' . esc_html( $screen['label'] ) . '</span></label>';
				break;
			case 'party':
				$labels = array(
					'full_name' => __( 'Full legal name', 'case-engine' ),
					'address'   => __( 'Address', 'case-engine' ),
					'last_known_address' => __( 'Last known address', 'case-engine' ),
					'phone'     => __( 'Phone', 'case-engine' ),
					'email'     => __( 'Email', 'case-engine' ),
					'dob'       => __( 'Date of birth', 'case-engine' ),
				);
				$prefix = $screen['party'] . '_';
				echo '<div class="az-intake-form-fields">';
				foreach ( $screen['fields'] as $f ) {
					$name = $prefix . $f;
					$input_type = ( $f === 'email' ) ? 'email' : ( ( $f === 'dob' ) ? 'date' : 'text' );
					echo '<div class="az-intake-form-group"><label><span class="az-intake-label-text">' . esc_html( $labels[ $f ] ?? $f ) . '</span> <input type="' . esc_attr( $input_type ) . '" name="' . esc_attr( $name ) . '" /></label></div>';
				}
				echo '</div>';
				if ( ! empty( $screen['help'] ) ) {
					echo '<p class="az-intake-help">' . esc_html( $screen['help'] ) . '</p>';
				}
				break;
			case 'children':
				?>
				<p class="az-intake-help"><?php esc_html_e( 'Add each minor child. No custody decisions are asked here.', 'case-engine' ); ?></p>
				<div class="az-intake-children-list" data-screen="<?php echo (int) $num; ?>">
					<div class="az-intake-child-row">
						<div class="az-intake-form-group"><label><span class="az-intake-label-text"><?php esc_html_e( 'Full name', 'case-engine' ); ?></span> <input type="text" name="children[0][full_name]" /></label></div>
						<div class="az-intake-form-group"><label><span class="az-intake-label-text"><?php esc_html_e( 'Date of birth', 'case-engine' ); ?></span> <input type="date" name="children[0][dob]" /></label></div>
						<div class="az-intake-form-group"><label><span class="az-intake-label-text"><?php esc_html_e( 'Relationship', 'case-engine' ); ?></span> <input type="text" name="children[0][relationship]" placeholder="<?php esc_attr_e( 'e.g. Son, Daughter', 'case-engine' ); ?>" /></label></div>
					</div>
				</div>
				<p><button type="button" class="az-intake-btn az-intake-add-child"><?php esc_html_e( 'Add another child', 'case-engine' ); ?></button></p>
				<?php
				break;
			case 'review':
				?>
				<div class="az-intake-review-summary"></div>
				<label class="az-intake-checkbox-label"><input type="checkbox" name="review_confirm" value="1" required class="az-intake-checkbox" /> <span class="az-intake-checkbox-text"><?php echo esc_html( $screen['label'] ); ?></span></label>
				<p class="az-intake-help"><em><?php esc_html_e( 'Case type: Uncontested Divorce', 'case-engine' ); ?></em></p>
				<?php
				break;
			case 'payment':
				?>
				<p><?php esc_html_e( 'Complete your payment securely at checkout to finalize your case.', 'case-engine' ); ?></p>
				<p><button type="button" class="az-intake-btn az-intake-btn-payment"><?php esc_html_e( 'Proceed to Payment', 'case-engine' ); ?></button></p>
				<p class="az-intake-payment-note"><?php esc_html_e( 'After payment, your case will be prepared and documents will be available on your dashboard.', 'case-engine' ); ?></p>
				<?php
				break;
			case 'next_steps':
				?>
				<p><?php esc_html_e( 'Your documents are being prepared. You will be notified when they are ready.', 'case-engine' ); ?></p>
				<p><a href="<?php echo esc_url( home_url( '/client-dashboard/' ) ); ?>" class="az-intake-btn"><?php esc_html_e( 'Go to Dashboard', 'case-engine' ); ?></a></p>
				<?php
				break;
			default:
				echo '<p>' . esc_html__( 'Screen content.', 'case-engine' ) . '</p>';
		}
		?>
		</div>
		<?php
	}
}
