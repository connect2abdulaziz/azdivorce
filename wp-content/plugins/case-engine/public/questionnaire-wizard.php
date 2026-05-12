<?php
/**
 * Questionnaire Wizard — multi-step HTML template.
 *
 * Variables injected by Case_Engine_Questionnaire_Controller::render_wizard():
 *   int        $case_id
 *   array      $case             Case row (status, county, …)
 *   array|null $existing         Existing questionnaire DB row
 *   array      $prefill          $existing with JSON columns decoded to arrays
 *   array      $completed_steps  Ints of completed step numbers
 *   int        $current_step
 *   string     $back_url
 *   string     $dashboard_url
 *
 * @package Case_Engine
 */

defined( 'ABSPATH' ) || exit;

$pf          = is_array( $prefill ) ? $prefill : array();
$total_steps = 7;

$az_counties = array(
	'Apache','Cochise','Coconino','Gila','Graham','Greenlee',
	'La Paz','Maricopa','Mohave','Navajo','Pima','Pinal',
	'Santa Cruz','Yavapai','Yuma',
);
$us_states = array(
	'AL'=>'Alabama','AK'=>'Alaska','AZ'=>'Arizona','AR'=>'Arkansas',
	'CA'=>'California','CO'=>'Colorado','CT'=>'Connecticut','DE'=>'Delaware',
	'FL'=>'Florida','GA'=>'Georgia','HI'=>'Hawaii','ID'=>'Idaho',
	'IL'=>'Illinois','IN'=>'Indiana','IA'=>'Iowa','KS'=>'Kansas',
	'KY'=>'Kentucky','LA'=>'Louisiana','ME'=>'Maine','MD'=>'Maryland',
	'MA'=>'Massachusetts','MI'=>'Michigan','MN'=>'Minnesota','MS'=>'Mississippi',
	'MO'=>'Missouri','MT'=>'Montana','NE'=>'Nebraska','NV'=>'Nevada',
	'NH'=>'New Hampshire','NJ'=>'New Jersey','NM'=>'New Mexico','NY'=>'New York',
	'NC'=>'North Carolina','ND'=>'North Dakota','OH'=>'Ohio','OK'=>'Oklahoma',
	'OR'=>'Oregon','PA'=>'Pennsylvania','RI'=>'Rhode Island','SC'=>'South Carolina',
	'SD'=>'South Dakota','TN'=>'Tennessee','TX'=>'Texas','UT'=>'Utah',
	'VT'=>'Vermont','VA'=>'Virginia','WA'=>'Washington','WV'=>'West Virginia',
	'WI'=>'Wisconsin','WY'=>'Wyoming',
);

$step_labels = Case_Engine_Questionnaire_Controller::get_step_labels();
$is_complete = ! empty( $existing ) && (int) ( $existing['is_complete'] ?? 0 ) === 1;

// Inline helpers to keep the template DRY.
$v = function( $key ) use ( $pf ) {
	return esc_attr( $pf[ $key ] ?? '' );
};
$sv = function( $key, $opt ) use ( $pf ) {
	return ( ( $pf[ $key ] ?? '' ) === $opt ) ? 'selected' : '';
};
$rv = function( $key, $opt ) use ( $pf ) {
	return ( ( $pf[ $key ] ?? 'no' ) === $opt ) ? 'checked' : '';
};

/**
 * Echo the Previous / Save & Continue button row.
 *
 * @param int $step  Current step (1–7).
 * @param int $total Total steps.
 */
$render_footer = function( $step, $total ) {
	$prev_step = $step - 1;
	?>
	<div class="az-q-step-footer">
		<?php if ( $step > 1 ) : ?>
			<button type="button"
					class="az-intake-btn az-intake-btn-secondary az-q-btn-prev"
					data-prev="<?php echo (int) $prev_step; ?>">
				← <?php esc_html_e( 'Previous', 'case-engine' ); ?>
			</button>
		<?php endif; ?>
		<button type="button"
				class="az-intake-btn az-intake-btn-primary az-q-btn-save"
				data-step="<?php echo (int) $step; ?>">
			<?php if ( $step === $total ) : ?>
				&#10003; <?php esc_html_e( 'Submit Questionnaire', 'case-engine' ); ?>
			<?php else : ?>
				<?php esc_html_e( 'Save & Continue', 'case-engine' ); ?> →
			<?php endif; ?>
		</button>
	</div>
	<?php
};
?>
<div class="az-q-wrapper"
	 id="az-questionnaire"
	 data-case-id="<?php echo (int) $case_id; ?>"
	 data-current-step="<?php echo (int) $current_step; ?>"
	 data-completed-steps="<?php echo esc_attr( implode( ',', $completed_steps ) ); ?>">

	<p class="az-q-back-link">
		<a href="<?php echo esc_url( $back_url ); ?>">← <?php esc_html_e( 'Back to your case', 'case-engine' ); ?></a>
	</p>

	<div class="az-q-header">
		<h1 class="az-q-title"><?php esc_html_e( 'Divorce Intake Questionnaire', 'case-engine' ); ?></h1>
		<p class="az-q-subtitle">
			<?php printf(
				/* translators: 1: case ID, 2: county name */
				esc_html__( 'Case #%1$d — %2$s County', 'case-engine' ),
				(int) $case_id,
				esc_html( $case['county'] ?? '' )
			); ?>
		</p>
	</div>

	<!-- ── Stepper nav ── -->
	<nav class="az-q-stepper" aria-label="<?php esc_attr_e( 'Questionnaire steps', 'case-engine' ); ?>">
		<?php for ( $i = 1; $i <= $total_steps; $i++ ) :
			$done    = in_array( $i, $completed_steps, true );
			$active  = ( $i === $current_step );
			$classes = 'az-q-step';
			if ( $done )   { $classes .= ' az-q-step--done'; }
			if ( $active ) { $classes .= ' az-q-step--active'; }
		?>
		<button type="button"
				class="<?php echo esc_attr( $classes ); ?>"
				data-step="<?php echo (int) $i; ?>"
				aria-label="<?php echo esc_attr( sprintf( __( 'Step %1$d: %2$s', 'case-engine' ), $i, $step_labels[ $i ] ) ); ?>"
				<?php echo ( ! $done && ! $active ) ? 'disabled' : ''; ?>>
			<span class="az-q-step__num">
				<?php if ( $done ) : ?><span class="az-q-step__check" aria-hidden="true">&#10003;</span><?php else : echo (int) $i; endif; ?>
			</span>
			<span class="az-q-step__label"><?php echo esc_html( $step_labels[ $i ] ); ?></span>
		</button>
		<?php if ( $i < $total_steps ) : ?>
			<div class="az-q-step__connector<?php echo $done ? ' az-q-step__connector--done' : ''; ?>" aria-hidden="true"></div>
		<?php endif; ?>
		<?php endfor; ?>
	</nav>

	<!-- ── Progress bar ── -->
	<div class="az-q-progress-wrap" aria-hidden="true">
		<div class="az-q-progress-bar"
			 id="az-q-progress-bar"
			 style="width:<?php echo min( 100, round( ( count( $completed_steps ) / $total_steps ) * 100 ) ); ?>%"></div>
	</div>

	<!-- ── Autosave status ── -->
	<div class="az-q-save-status" id="az-q-save-status" aria-live="polite"></div>

	<?php if ( $is_complete ) : ?>
	<div class="az-q-notice az-q-notice--success">
		<strong><?php esc_html_e( 'Questionnaire complete!', 'case-engine' ); ?></strong>
		<?php esc_html_e( 'Your answers have been saved and will be used to prepare your documents.', 'case-engine' ); ?>
	</div>
	<?php endif; ?>

	<!-- ════════════════════════════════════════════════════
	     STEP 1 — Petitioner Information
	     ════════════════════════════════════════════════════ -->
	<div class="az-q-panel" id="az-q-panel-1" data-step="1">
		<div class="az-q-panel__card">
			<div class="az-q-panel__heading">
				<span class="az-q-panel__step-badge">1 / <?php echo (int) $total_steps; ?></span>
				<h2><?php esc_html_e( 'Your Information (Petitioner)', 'case-engine' ); ?></h2>
				<p class="az-q-panel__desc"><?php esc_html_e( 'The person filing for divorce.', 'case-engine' ); ?></p>
			</div>
			<div class="az-q-fields">
				<div class="az-q-row az-q-row--2col">
					<div class="az-q-field az-q-field--required">
						<label for="petitioner_first_name"><?php esc_html_e( 'First Name', 'case-engine' ); ?></label>
						<input type="text" id="petitioner_first_name" name="petitioner_first_name"
							   value="<?php echo $v( 'petitioner_first_name' ); ?>"
							   placeholder="Jane" autocomplete="given-name" required />
					</div>
					<div class="az-q-field az-q-field--required">
						<label for="petitioner_last_name"><?php esc_html_e( 'Last Name', 'case-engine' ); ?></label>
						<input type="text" id="petitioner_last_name" name="petitioner_last_name"
							   value="<?php echo $v( 'petitioner_last_name' ); ?>"
							   placeholder="Smith" autocomplete="family-name" required />
					</div>
				</div>
				<div class="az-q-field az-q-field--required">
					<label for="petitioner_address"><?php esc_html_e( 'Street Address', 'case-engine' ); ?></label>
					<input type="text" id="petitioner_address" name="petitioner_address"
						   value="<?php echo $v( 'petitioner_address' ); ?>"
						   placeholder="123 Main St" autocomplete="street-address" required />
				</div>
				<div class="az-q-row az-q-row--3col">
					<div class="az-q-field az-q-field--required">
						<label for="petitioner_city"><?php esc_html_e( 'City', 'case-engine' ); ?></label>
						<input type="text" id="petitioner_city" name="petitioner_city"
							   value="<?php echo $v( 'petitioner_city' ); ?>"
							   placeholder="Phoenix" autocomplete="address-level2" required />
					</div>
					<div class="az-q-field az-q-field--required">
						<label for="petitioner_state"><?php esc_html_e( 'State', 'case-engine' ); ?></label>
						<select id="petitioner_state" name="petitioner_state" autocomplete="address-level1" required>
							<option value=""><?php esc_html_e( '— Select —', 'case-engine' ); ?></option>
							<?php foreach ( $us_states as $abbr => $name ) : ?>
							<option value="<?php echo esc_attr( $abbr ); ?>" <?php echo $sv( 'petitioner_state', $abbr ); ?>>
								<?php echo esc_html( $name ); ?>
							</option>
							<?php endforeach; ?>
						</select>
					</div>
					<div class="az-q-field az-q-field--required">
						<label for="petitioner_zip"><?php esc_html_e( 'ZIP Code', 'case-engine' ); ?></label>
						<input type="text" id="petitioner_zip" name="petitioner_zip"
							   value="<?php echo $v( 'petitioner_zip' ); ?>"
							   placeholder="85001" pattern="\d{5}(-\d{4})?" autocomplete="postal-code" required />
					</div>
				</div>
				<div class="az-q-row az-q-row--2col">
					<div class="az-q-field">
						<label for="petitioner_phone"><?php esc_html_e( 'Phone Number', 'case-engine' ); ?></label>
						<input type="tel" id="petitioner_phone" name="petitioner_phone"
							   value="<?php echo $v( 'petitioner_phone' ); ?>"
							   placeholder="(602) 555-1234" autocomplete="tel" />
					</div>
					<div class="az-q-field az-q-field--required">
						<label for="petitioner_email"><?php esc_html_e( 'Email Address', 'case-engine' ); ?></label>
						<input type="email" id="petitioner_email" name="petitioner_email"
							   value="<?php echo $v( 'petitioner_email' ); ?>"
							   placeholder="jane@example.com" autocomplete="email" required />
					</div>
				</div>
			</div>
			<?php $render_footer( 1, $total_steps ); ?>
		</div>
	</div>

	<!-- ════════════════════════════════════════════════════
	     STEP 2 — Respondent Information
	     ════════════════════════════════════════════════════ -->
	<div class="az-q-panel" id="az-q-panel-2" data-step="2" hidden>
		<div class="az-q-panel__card">
			<div class="az-q-panel__heading">
				<span class="az-q-panel__step-badge">2 / <?php echo (int) $total_steps; ?></span>
				<h2><?php esc_html_e( 'Spouse Information (Respondent)', 'case-engine' ); ?></h2>
				<p class="az-q-panel__desc"><?php esc_html_e( 'The person you are divorcing.', 'case-engine' ); ?></p>
			</div>
			<div class="az-q-fields">
				<div class="az-q-row az-q-row--2col">
					<div class="az-q-field az-q-field--required">
						<label for="respondent_first_name"><?php esc_html_e( 'First Name', 'case-engine' ); ?></label>
						<input type="text" id="respondent_first_name" name="respondent_first_name"
							   value="<?php echo $v( 'respondent_first_name' ); ?>" required />
					</div>
					<div class="az-q-field az-q-field--required">
						<label for="respondent_last_name"><?php esc_html_e( 'Last Name', 'case-engine' ); ?></label>
						<input type="text" id="respondent_last_name" name="respondent_last_name"
							   value="<?php echo $v( 'respondent_last_name' ); ?>" required />
					</div>
				</div>
				<div class="az-q-field">
					<label for="respondent_address"><?php esc_html_e( 'Street Address', 'case-engine' ); ?></label>
					<input type="text" id="respondent_address" name="respondent_address"
						   value="<?php echo $v( 'respondent_address' ); ?>"
						   placeholder="Last known address" />
				</div>
				<div class="az-q-row az-q-row--3col">
					<div class="az-q-field">
						<label for="respondent_city"><?php esc_html_e( 'City', 'case-engine' ); ?></label>
						<input type="text" id="respondent_city" name="respondent_city"
							   value="<?php echo $v( 'respondent_city' ); ?>" />
					</div>
					<div class="az-q-field">
						<label for="respondent_state"><?php esc_html_e( 'State', 'case-engine' ); ?></label>
						<select id="respondent_state" name="respondent_state">
							<option value=""><?php esc_html_e( '— Select —', 'case-engine' ); ?></option>
							<?php foreach ( $us_states as $abbr => $name ) : ?>
							<option value="<?php echo esc_attr( $abbr ); ?>" <?php echo $sv( 'respondent_state', $abbr ); ?>>
								<?php echo esc_html( $name ); ?>
							</option>
							<?php endforeach; ?>
						</select>
					</div>
					<div class="az-q-field">
						<label for="respondent_zip"><?php esc_html_e( 'ZIP Code', 'case-engine' ); ?></label>
						<input type="text" id="respondent_zip" name="respondent_zip"
							   value="<?php echo $v( 'respondent_zip' ); ?>" placeholder="85001" />
					</div>
				</div>
			</div>
			<?php $render_footer( 2, $total_steps ); ?>
		</div>
	</div>

	<!-- ════════════════════════════════════════════════════
	     STEP 3 — Marriage Information
	     ════════════════════════════════════════════════════ -->
	<div class="az-q-panel" id="az-q-panel-3" data-step="3" hidden>
		<div class="az-q-panel__card">
			<div class="az-q-panel__heading">
				<span class="az-q-panel__step-badge">3 / <?php echo (int) $total_steps; ?></span>
				<h2><?php esc_html_e( 'Marriage Information', 'case-engine' ); ?></h2>
			</div>
			<div class="az-q-fields">
				<div class="az-q-row az-q-row--2col">
					<div class="az-q-field az-q-field--required">
						<label for="marriage_date"><?php esc_html_e( 'Date of Marriage', 'case-engine' ); ?></label>
						<input type="date" id="marriage_date" name="marriage_date"
							   value="<?php echo $v( 'marriage_date' ); ?>" required />
					</div>
					<div class="az-q-field az-q-field--required">
						<label for="separation_date"><?php esc_html_e( 'Date of Separation', 'case-engine' ); ?></label>
						<input type="date" id="separation_date" name="separation_date"
							   value="<?php echo $v( 'separation_date' ); ?>" required />
					</div>
				</div>
				<div class="az-q-row az-q-row--2col">
					<div class="az-q-field az-q-field--required">
						<label for="marriage_city"><?php esc_html_e( 'City Where Married', 'case-engine' ); ?></label>
						<input type="text" id="marriage_city" name="marriage_city"
							   value="<?php echo $v( 'marriage_city' ); ?>" required />
					</div>
					<div class="az-q-field az-q-field--required">
						<label for="marriage_state"><?php esc_html_e( 'State Where Married', 'case-engine' ); ?></label>
						<select id="marriage_state" name="marriage_state" required>
							<option value=""><?php esc_html_e( '— Select —', 'case-engine' ); ?></option>
							<?php foreach ( $us_states as $abbr => $name ) : ?>
							<option value="<?php echo esc_attr( $abbr ); ?>" <?php echo $sv( 'marriage_state', $abbr ); ?>>
								<?php echo esc_html( $name ); ?>
							</option>
							<?php endforeach; ?>
						</select>
					</div>
				</div>
			</div>
			<?php $render_footer( 3, $total_steps ); ?>
		</div>
	</div>

	<!-- ════════════════════════════════════════════════════
	     STEP 4 — Filing Details
	     ════════════════════════════════════════════════════ -->
	<div class="az-q-panel" id="az-q-panel-4" data-step="4" hidden>
		<div class="az-q-panel__card">
			<div class="az-q-panel__heading">
				<span class="az-q-panel__step-badge">4 / <?php echo (int) $total_steps; ?></span>
				<h2><?php esc_html_e( 'Filing Details', 'case-engine' ); ?></h2>
			</div>
			<div class="az-q-fields">
				<div class="az-q-field az-q-field--required">
					<label for="county_filing"><?php esc_html_e( 'County Where You Are Filing', 'case-engine' ); ?></label>
					<div class="az-q-tooltip-wrap">
						<select id="county_filing" name="county_filing" required>
							<option value=""><?php esc_html_e( '— Select county —', 'case-engine' ); ?></option>
							<?php foreach ( $az_counties as $county ) : ?>
							<option value="<?php echo esc_attr( $county ); ?>" <?php echo $sv( 'county_filing', $county ); ?>>
								<?php echo esc_html( $county ); ?>
							</option>
							<?php endforeach; ?>
						</select>
						<span class="az-q-tooltip" data-tooltip="<?php esc_attr_e( 'File in the county where you or your spouse have lived for at least 90 days.', 'case-engine' ); ?>">?</span>
					</div>
				</div>
				<div class="az-q-field">
					<label class="az-q-label--bold"><?php esc_html_e( 'Is this a Covenant Marriage?', 'case-engine' ); ?></label>
					<div class="az-q-tooltip-wrap">
						<div class="az-q-radio-group">
							<label class="az-q-radio">
								<input type="radio" name="covenant_marriage" value="yes" <?php echo $rv( 'covenant_marriage', 'yes' ); ?> />
								<span><?php esc_html_e( 'Yes', 'case-engine' ); ?></span>
							</label>
							<label class="az-q-radio">
								<input type="radio" name="covenant_marriage" value="no" <?php echo $rv( 'covenant_marriage', 'no' ); ?> />
								<span><?php esc_html_e( 'No', 'case-engine' ); ?></span>
							</label>
						</div>
						<span class="az-q-tooltip" data-tooltip="<?php esc_attr_e( 'Covenant marriages have stricter dissolution requirements under Arizona law.', 'case-engine' ); ?>">?</span>
					</div>
				</div>
				<div class="az-q-field">
					<label class="az-q-label--bold"><?php esc_html_e( 'Is anyone currently pregnant?', 'case-engine' ); ?></label>
					<div class="az-q-tooltip-wrap">
						<div class="az-q-radio-group">
							<label class="az-q-radio">
								<input type="radio" name="pregnancy_status" value="yes" <?php echo $rv( 'pregnancy_status', 'yes' ); ?> />
								<span><?php esc_html_e( 'Yes', 'case-engine' ); ?></span>
							</label>
							<label class="az-q-radio">
								<input type="radio" name="pregnancy_status" value="no" <?php echo $rv( 'pregnancy_status', 'no' ); ?> />
								<span><?php esc_html_e( 'No', 'case-engine' ); ?></span>
							</label>
						</div>
						<span class="az-q-tooltip" data-tooltip="<?php esc_attr_e( 'Courts require disclosure of any pregnancy. If yes, additional forms may be required.', 'case-engine' ); ?>">?</span>
					</div>
				</div>
			</div>
			<?php $render_footer( 4, $total_steps ); ?>
		</div>
	</div>

	<!-- ════════════════════════════════════════════════════
	     STEP 5 — Property & Debt
	     ════════════════════════════════════════════════════ -->
	<div class="az-q-panel" id="az-q-panel-5" data-step="5" hidden>
		<div class="az-q-panel__card">
			<div class="az-q-panel__heading">
				<span class="az-q-panel__step-badge">5 / <?php echo (int) $total_steps; ?></span>
				<h2><?php esc_html_e( 'Property & Debt Division', 'case-engine' ); ?></h2>
				<p class="az-q-panel__desc"><?php esc_html_e( 'List community assets and debts. You can add as many rows as needed.', 'case-engine' ); ?></p>
			</div>

			<h3 class="az-q-section-title"><?php esc_html_e( 'Property / Assets', 'case-engine' ); ?></h3>
			<?php
			$existing_props = $pf['property_division'] ?? array();
			if ( ! is_array( $existing_props ) || empty( $existing_props ) ) {
				$existing_props = array( array( 'description' => '', 'value' => '', 'awarded_to' => 'petitioner' ) );
			}
			?>
			<div class="az-q-repeater" id="az-property-repeater">
				<div class="az-q-repeater__header" aria-hidden="true">
					<span><?php esc_html_e( 'Description', 'case-engine' ); ?></span>
					<span><?php esc_html_e( 'Value', 'case-engine' ); ?></span>
					<span><?php esc_html_e( 'Awarded To', 'case-engine' ); ?></span>
					<span></span>
				</div>
				<div class="az-q-repeater__rows" id="property-rows">
					<?php foreach ( $existing_props as $idx => $prop ) : ?>
					<div class="az-q-repeater__row" data-index="<?php echo (int) $idx; ?>">
						<input type="text"
							   name="property_items[<?php echo (int) $idx; ?>][description]"
							   placeholder="<?php esc_attr_e( 'e.g. Family home', 'case-engine' ); ?>"
							   value="<?php echo esc_attr( $prop['description'] ?? '' ); ?>" />
						<input type="text"
							   name="property_items[<?php echo (int) $idx; ?>][value]"
							   placeholder="<?php esc_attr_e( 'e.g. $250,000', 'case-engine' ); ?>"
							   value="<?php echo esc_attr( $prop['value'] ?? '' ); ?>" />
						<select name="property_items[<?php echo (int) $idx; ?>][awarded_to]">
							<option value="petitioner" <?php echo ( ( $prop['awarded_to'] ?? 'petitioner' ) === 'petitioner' ) ? 'selected' : ''; ?>><?php esc_html_e( 'Petitioner', 'case-engine' ); ?></option>
							<option value="respondent" <?php echo ( ( $prop['awarded_to'] ?? '' ) === 'respondent' ) ? 'selected' : ''; ?>><?php esc_html_e( 'Respondent', 'case-engine' ); ?></option>
						</select>
						<button type="button" class="az-q-repeater__remove" aria-label="<?php esc_attr_e( 'Remove this row', 'case-engine' ); ?>">&#x2715;</button>
					</div>
					<?php endforeach; ?>
				</div>
				<button type="button" class="az-q-repeater__add" id="add-property-row">
					+ <?php esc_html_e( 'Add Property', 'case-engine' ); ?>
				</button>
			</div>

			<h3 class="az-q-section-title"><?php esc_html_e( 'Debts', 'case-engine' ); ?></h3>
			<?php
			$existing_debts = $pf['debt_division'] ?? array();
			if ( ! is_array( $existing_debts ) || empty( $existing_debts ) ) {
				$existing_debts = array( array( 'creditor' => '', 'balance' => '', 'responsible_party' => 'petitioner' ) );
			}
			?>
			<div class="az-q-repeater" id="az-debt-repeater">
				<div class="az-q-repeater__header" aria-hidden="true">
					<span><?php esc_html_e( 'Creditor Name', 'case-engine' ); ?></span>
					<span><?php esc_html_e( 'Balance', 'case-engine' ); ?></span>
					<span><?php esc_html_e( 'Responsible Party', 'case-engine' ); ?></span>
					<span></span>
				</div>
				<div class="az-q-repeater__rows" id="debt-rows">
					<?php foreach ( $existing_debts as $idx => $debt ) : ?>
					<div class="az-q-repeater__row" data-index="<?php echo (int) $idx; ?>">
						<input type="text"
							   name="debt_items[<?php echo (int) $idx; ?>][creditor]"
							   placeholder="<?php esc_attr_e( 'e.g. Bank of America', 'case-engine' ); ?>"
							   value="<?php echo esc_attr( $debt['creditor'] ?? '' ); ?>" />
						<input type="text"
							   name="debt_items[<?php echo (int) $idx; ?>][balance]"
							   placeholder="<?php esc_attr_e( 'e.g. $15,000', 'case-engine' ); ?>"
							   value="<?php echo esc_attr( $debt['balance'] ?? '' ); ?>" />
						<select name="debt_items[<?php echo (int) $idx; ?>][responsible_party]">
							<option value="petitioner" <?php echo ( ( $debt['responsible_party'] ?? 'petitioner' ) === 'petitioner' ) ? 'selected' : ''; ?>><?php esc_html_e( 'Petitioner', 'case-engine' ); ?></option>
							<option value="respondent" <?php echo ( ( $debt['responsible_party'] ?? '' ) === 'respondent' ) ? 'selected' : ''; ?>><?php esc_html_e( 'Respondent', 'case-engine' ); ?></option>
						</select>
						<button type="button" class="az-q-repeater__remove" aria-label="<?php esc_attr_e( 'Remove this row', 'case-engine' ); ?>">&#x2715;</button>
					</div>
					<?php endforeach; ?>
				</div>
				<button type="button" class="az-q-repeater__add" id="add-debt-row">
					+ <?php esc_html_e( 'Add Debt', 'case-engine' ); ?>
				</button>
			</div>

			<?php $render_footer( 5, $total_steps ); ?>
		</div>
	</div>

	<!-- ════════════════════════════════════════════════════
	     STEP 6 — Name Change
	     ════════════════════════════════════════════════════ -->
	<div class="az-q-panel" id="az-q-panel-6" data-step="6" hidden>
		<div class="az-q-panel__card">
			<div class="az-q-panel__heading">
				<span class="az-q-panel__step-badge">6 / <?php echo (int) $total_steps; ?></span>
				<h2><?php esc_html_e( 'Name Change', 'case-engine' ); ?></h2>
			</div>
			<div class="az-q-fields">
				<div class="az-q-field">
					<label class="az-q-label--bold"><?php esc_html_e( 'Do you want to restore a former name?', 'case-engine' ); ?></label>
					<div class="az-q-radio-group">
						<label class="az-q-radio">
							<input type="radio" name="restore_former_name" value="yes" <?php echo $rv( 'restore_former_name', 'yes' ); ?> />
							<span><?php esc_html_e( 'Yes', 'case-engine' ); ?></span>
						</label>
						<label class="az-q-radio">
							<input type="radio" name="restore_former_name" value="no" <?php echo $rv( 'restore_former_name', 'no' ); ?> />
							<span><?php esc_html_e( 'No', 'case-engine' ); ?></span>
						</label>
					</div>
				</div>
				<div class="az-q-field az-q-field--conditional" id="former-name-field">
					<label for="former_name"><?php esc_html_e( 'Former Name to Restore', 'case-engine' ); ?></label>
					<input type="text" id="former_name" name="former_name"
						   value="<?php echo $v( 'former_name' ); ?>"
						   placeholder="<?php esc_attr_e( 'Your name before marriage', 'case-engine' ); ?>" />
				</div>
			</div>
			<?php $render_footer( 6, $total_steps ); ?>
		</div>
	</div>

	<!-- ════════════════════════════════════════════════════
	     STEP 7 — Service of Process
	     ════════════════════════════════════════════════════ -->
	<div class="az-q-panel" id="az-q-panel-7" data-step="7" hidden>
		<div class="az-q-panel__card">
			<div class="az-q-panel__heading">
				<span class="az-q-panel__step-badge">7 / <?php echo (int) $total_steps; ?></span>
				<h2><?php esc_html_e( 'Service of Process', 'case-engine' ); ?></h2>
				<p class="az-q-panel__desc"><?php esc_html_e( 'How will your spouse be served with the divorce papers?', 'case-engine' ); ?></p>
			</div>
			<div class="az-q-fields">
				<div class="az-q-field az-q-field--required">
					<label for="service_method"><?php esc_html_e( 'Method of Service', 'case-engine' ); ?></label>
					<div class="az-q-tooltip-wrap">
						<select id="service_method" name="service_method" required>
							<option value=""><?php esc_html_e( '— Select method —', 'case-engine' ); ?></option>
							<option value="personal" <?php echo $sv( 'service_method', 'personal' ); ?>><?php esc_html_e( 'Personal Service (Process Server)', 'case-engine' ); ?></option>
							<option value="acceptance" <?php echo $sv( 'service_method', 'acceptance' ); ?>><?php esc_html_e( 'Acceptance of Service (Spouse Signs)', 'case-engine' ); ?></option>
							<option value="certified_mail" <?php echo $sv( 'service_method', 'certified_mail' ); ?>><?php esc_html_e( 'Certified Mail', 'case-engine' ); ?></option>
							<option value="publication" <?php echo $sv( 'service_method', 'publication' ); ?>><?php esc_html_e( 'Service by Publication', 'case-engine' ); ?></option>
						</select>
						<span class="az-q-tooltip" data-tooltip="<?php esc_attr_e( 'If your spouse agrees to sign an Acceptance of Service, you may avoid hiring a process server.', 'case-engine' ); ?>">?</span>
					</div>
				</div>
				<div class="az-q-field">
					<label class="az-q-label--bold"><?php esc_html_e( 'Has your spouse signed an Acceptance of Service?', 'case-engine' ); ?></label>
					<div class="az-q-radio-group">
						<label class="az-q-radio">
							<input type="radio" name="acceptance_of_service" value="yes" <?php echo $rv( 'acceptance_of_service', 'yes' ); ?> />
							<span><?php esc_html_e( 'Yes', 'case-engine' ); ?></span>
						</label>
						<label class="az-q-radio">
							<input type="radio" name="acceptance_of_service" value="no" <?php echo $rv( 'acceptance_of_service', 'no' ); ?> />
							<span><?php esc_html_e( 'No / Not yet', 'case-engine' ); ?></span>
						</label>
					</div>
				</div>
				<div class="az-q-field">
					<label for="date_of_service"><?php esc_html_e( 'Date of Service', 'case-engine' ); ?></label>
					<input type="date" id="date_of_service" name="date_of_service"
						   value="<?php echo $v( 'date_of_service' ); ?>" />
				</div>
			</div>
			<?php $render_footer( 7, $total_steps ); ?>
		</div>
	</div>

	<!-- ── Complete screen ── -->
	<div class="az-q-panel az-q-panel--complete" id="az-q-panel-complete" hidden>
		<div class="az-q-panel__card az-q-panel__card--success">
			<div class="az-q-success-icon" aria-hidden="true">&#10003;</div>
			<h2><?php esc_html_e( 'Questionnaire Submitted!', 'case-engine' ); ?></h2>
			<p><?php esc_html_e( 'Your answers have been saved. Our team will prepare your divorce documents shortly.', 'case-engine' ); ?></p>
			<a href="<?php echo esc_url( $back_url ); ?>" class="az-intake-btn az-intake-btn-primary">
				<?php esc_html_e( 'Return to Your Case', 'case-engine' ); ?>
			</a>
		</div>
	</div>

	<!-- Prefill data for JS (safe — JSON-encoded) -->
	<script id="az-q-prefill-data" type="application/json"><?php
		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo wp_json_encode( $pf, JSON_HEX_TAG | JSON_HEX_AMP );
	?></script>

</div><!-- /.az-q-wrapper -->
