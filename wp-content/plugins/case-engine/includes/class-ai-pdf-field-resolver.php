<?php
/**
 * AI PDF Field Resolver — GPT-4o powered AcroForm brain.
 *
 * Sends every unfilled, non-protected PDF field to GPT-4o with full
 * case context. AI derives, formats, and combines values intelligently,
 * handling all naming variations found in Arizona divorce court forms.
 *
 * Manual mapping (field-mapping.php) is the trusted seed — never overwritten.
 * AI fills every remaining field it can reason about with high accuracy.
 *
 * @package Case_Engine
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Case_Engine_AI_PDF_Field_Resolver {

	const OPENAI_CHAT_COMPLETIONS_URL = 'https://api.openai.com/v1/chat/completions';
	const DEFAULT_MODEL               = 'gpt-4o';
	const DEFAULT_CONFIDENCE          = 0.60;

	// Known Arizona court form label → semantic category mapping for field hints.
	// Used to pre-annotate ambiguous field names before sending to AI.
	const FIELD_HINTS = [
		'person filing'          => 'petitioner_full_name',
		'petitioner'             => 'petitioner_full_name',
		'party a'                => 'petitioner_full_name',
		'respondent'             => 'respondent_full_name',
		'party b'                => 'respondent_full_name',
		'address if not'         => 'petitioner_address',
		'mailing address'        => 'petitioner_address',
		'city state zip'         => 'petitioner_city_state_zip',
		'city, state, zip'       => 'petitioner_city_state_zip',
		'telephone'              => 'petitioner_phone',
		'phone'                  => 'petitioner_phone',
		'email address'          => 'petitioner_email',
		'case number'            => 'case_number',
		'case no'                => 'case_number',
		'date of birth'          => 'petitioner_dob_us',
		'birthdate'              => 'petitioner_dob_us',
		'dissolution'            => 'case_type_dissolution_check',
		'self without a lawyer'  => 'self_represented_check',
		'without a lawyer'       => 'self_represented_check',
		'atlas'                  => '',
		'bar number'             => '',
		'lawyer'                 => '',
		'social security'        => '',
		'ssn'                    => '',
	];

	// -------------------------------------------------------------------------
	// Public API
	// -------------------------------------------------------------------------

	/**
	 * Main entry point. Resolves all unfilled template fields using AI.
	 *
	 * @param array $args {
	 *   case_id         int
	 *   user_id         int
	 *   form_key        string
	 *   packet_type     string  'wc' or 'woc'
	 *   template_fields array   All AcroForm fields extracted from the PDF.
	 *   existing_fields array   Fields already filled by manual mapping.
	 *   computed_values array   Full flat dictionary of known case/user values.
	 * }
	 * @return array { fields, filled, skipped, error, enabled, model }
	 */
	public static function resolve( array $args ): array {
		$result            = self::empty_result();
		$result['enabled'] = self::is_enabled();
		$result['model']   = self::model();

		if ( ! $result['enabled'] ) {
			$result['error'] = 'AI PDF filling is disabled or no OpenAI API key is configured.';
			return $result;
		}

		$template_fields = is_array( $args['template_fields'] ?? null ) ? $args['template_fields'] : [];
		$existing        = is_array( $args['existing_fields'] ?? null ) ? $args['existing_fields'] : [];
		$computed        = is_array( $args['computed_values'] ?? null ) ? $args['computed_values'] : [];

		$all_fields   = self::all_fillable_fields( $template_fields, $existing );
		$clean_values = self::clean_computed_values( $computed );

		if ( empty( $all_fields ) || empty( $clean_values ) ) {
			return $result;
		}

		// Annotate fields with semantic hints for the AI.
		$annotated_fields = self::annotate_fields( $all_fields, $clean_values );

		$context = [
			'form_key'       => (string) ( $args['form_key'] ?? '' ),
			'form_label'     => self::form_label( (string) ( $args['form_key'] ?? '' ) ),
			'packet_type'    => (string) ( $args['packet_type'] ?? '' ),
			'case_data'      => self::categorize_values( $clean_values ),
			'already_filled' => self::filled_field_summary( $existing ),
			'fields_to_fill' => array_values( $annotated_fields ),
			'fill_count'     => count( $annotated_fields ),
		];

		$response = self::call_openai( $context );

		if ( is_wp_error( $response ) ) {
			$result['error'] = $response->get_error_message();
			return $result;
		}

		$parsed = self::parse_response( $response );

		if ( is_wp_error( $parsed ) ) {
			$result['error'] = $parsed->get_error_message();
			return $result;
		}

		$validated = self::validate_and_merge( $parsed, $all_fields, $existing );
		return array_merge( $result, $validated );
	}

	// -------------------------------------------------------------------------
	// Configuration
	// -------------------------------------------------------------------------

	public static function is_enabled(): bool {
		if ( defined( 'CASE_ENGINE_AI_PDF_ENABLED' ) ) {
			return self::truthy( CASE_ENGINE_AI_PDF_ENABLED );
		}

		$opt = get_option( 'case_engine_ai_pdf_enabled', '' );
		if ( '' !== $opt ) {
			return self::truthy( $opt );
		}

		return '' !== self::api_key();
	}

	public static function model(): string {
		if ( defined( 'CASE_ENGINE_OPENAI_MODEL' ) && CASE_ENGINE_OPENAI_MODEL ) {
			return trim( (string) CASE_ENGINE_OPENAI_MODEL );
		}

		$m = trim( (string) get_option( 'case_engine_openai_model', self::DEFAULT_MODEL ) );
		return '' !== $m ? $m : self::DEFAULT_MODEL;
	}

	public static function confidence_threshold(): float {
		if ( defined( 'CASE_ENGINE_AI_PDF_CONFIDENCE_THRESHOLD' ) ) {
			return max( 0.0, min( 1.0, (float) CASE_ENGINE_AI_PDF_CONFIDENCE_THRESHOLD ) );
		}

		return max( 0.0, min( 1.0, (float) get_option( 'case_engine_ai_pdf_confidence_threshold', self::DEFAULT_CONFIDENCE ) ) );
	}

	// -------------------------------------------------------------------------
	// Audit logging
	// -------------------------------------------------------------------------

	public static function audit_log( int $case_id, int $user_id, string $form_key, array $result ): void {
		if ( ! method_exists( 'Case_Engine_Case_Factory', 'audit_log' ) ) {
			return;
		}

		if ( empty( $result['enabled'] ) && empty( $result['fields'] ) && empty( $result['filled'] ) && empty( $result['skipped'] ) ) {
			return;
		}

		Case_Engine_Case_Factory::audit_log(
			'ai_pdf_fields_resolved',
			'document',
			$case_id,
			$user_id,
			[
				'form_key'      => $form_key,
				'enabled'       => (bool) ( $result['enabled'] ?? false ),
				'model'         => (string) ( $result['model'] ?? '' ),
				'filled_count'  => count( $result['fields'] ?? [] ),
				'skipped_count' => count( $result['skipped'] ?? [] ),
				'error'         => (string) ( $result['error'] ?? '' ),
				'filled'        => $result['filled'] ?? [],
				'skipped'       => $result['skipped'] ?? [],
			]
		);
	}

	// -------------------------------------------------------------------------
	// Context preparation
	// -------------------------------------------------------------------------

	private static function empty_result(): array {
		return [
			'fields'  => [],
			'filled'  => [],
			'skipped' => [],
			'error'   => '',
			'enabled' => false,
			'model'   => '',
		];
	}

	/**
	 * Return all template fields not already filled and not protected.
	 */
	private static function all_fillable_fields( array $template_fields, array $existing ): array {
		$out = [];

		foreach ( $template_fields as $name => $meta ) {
			if ( ! is_string( $name ) || '' === $name ) {
				continue;
			}

			if ( self::is_protected_field( $name ) ) {
				continue;
			}

			if ( isset( $existing[ $name ] ) && '' !== trim( (string) $existing[ $name ] ) ) {
				continue;
			}

			$out[ $name ] = [
				'name'    => $name,
				'alt'     => (string) ( $meta['alt'] ?? '' ),
				'type'    => (string) ( $meta['type'] ?? 'Text' ),
				'options' => isset( $meta['options'] ) && is_array( $meta['options'] ) ? array_values( $meta['options'] ) : [],
			];
		}

		return $out;
	}

	/**
	 * Pre-annotate fields with semantic hints based on field name and alt label.
	 * This drastically improves AI accuracy on ambiguous field names like
	 * "1 DRSDS10f_An" or "Birthdate_2".
	 */
	private static function annotate_fields( array $fields, array $values ): array {
		$annotated = [];

		foreach ( $fields as $name => $meta ) {
			$entry = $meta;
			$hint  = self::hint_for_field( $name, $meta['alt'] ?? '' );

			if ( '' !== $hint ) {
				$entry['hint'] = $hint;

				// Pre-fill suggested_value when we know exactly what it should be.
				if ( isset( $values[ $hint ] ) ) {
					$entry['suggested_value'] = $values[ $hint ];
				}
			}

			// For checkboxes, clarify which option is "checked".
			if ( 'Button' === ( $meta['type'] ?? '' ) ) {
				$options     = $meta['options'] ?? [];
				$non_off     = array_values( array_filter( $options, fn( $o ) => ! in_array( strtolower( $o ), [ 'off' ], true ) ) );
				$entry['checked_value'] = $non_off[0] ?? 'On';
			}

			$annotated[ $name ] = $entry;
		}

		return $annotated;
	}

	/**
	 * Match a field to a computed_values key using keyword heuristics.
	 */
	private static function hint_for_field( string $name, string $alt ): string {
		$search = strtolower( $name . ' ' . $alt );

		// Skip fields that should never be touched.
		foreach ( [ 'ssn', 'social security', 'bar number', 'atlas', 'notary', 'signature', 'judge' ] as $skip ) {
			if ( false !== strpos( $search, $skip ) ) {
				return '';
			}
		}

		foreach ( self::FIELD_HINTS as $keyword => $key ) {
			if ( false !== strpos( $search, $keyword ) ) {
				return $key;
			}
		}

		// Numbered suffix patterns: "Birthdate_2" or "Date of Birth_2" → respondent
		if ( preg_match( '/_2\b|[\[\(]2[\]\)]/', $search ) ) {
			if ( false !== strpos( $search, 'birth' ) || false !== strpos( $search, 'dob' ) ) {
				return 'respondent_dob_us';
			}
			if ( false !== strpos( $search, 'name' ) ) {
				return 'respondent_full_name';
			}
			if ( false !== strpos( $search, 'address' ) ) {
				return 'respondent_address';
			}
		}

		return '';
	}

	/**
	 * Organize computed values into named categories for clearer AI reasoning.
	 */
	private static function categorize_values( array $values ): array {
		$petitioner = [];
		$respondent = [];
		$case       = [];
		$children   = [];
		$other      = [];

		foreach ( $values as $key => $val ) {
			if ( str_starts_with( $key, 'petitioner_' ) || str_starts_with( $key, 'married_name' ) || str_starts_with( $key, 'restore_name' ) ) {
				$petitioner[ $key ] = $val;
			} elseif ( str_starts_with( $key, 'respondent_' ) ) {
				$respondent[ $key ] = $val;
			} elseif ( str_starts_with( $key, 'child_' ) ) {
				$children[ $key ] = $val;
			} elseif ( in_array( $key, [ 'case_number', 'county_filing', 'marriage_date', 'marriage_city_state', 'separation_date', 'date_of_service', 'case_type_dissolution', 'self_represented', 'has_pregnancy', 'is_covenant_marriage', 'is_restore_name' ], true ) ) {
				$case[ $key ] = $val;
			} else {
				$other[ $key ] = $val;
			}
		}

		return array_filter( [
			'petitioner' => $petitioner,
			'respondent' => $respondent,
			'case_info'  => $case,
			'children'   => $children,
			'other'      => $other,
		] );
	}

	/**
	 * Summary of already-filled fields for AI context (field → value pairs).
	 */
	private static function filled_field_summary( array $existing ): array {
		$out = [];
		foreach ( $existing as $name => $value ) {
			if ( '' !== trim( (string) $value ) ) {
				$out[ $name ] = (string) $value;
			}
		}

		return $out;
	}

	/**
	 * Clean computed values: remove empty/invalid, render booleans naturally.
	 */
	private static function clean_computed_values( array $values ): array {
		$out = [];

		foreach ( $values as $key => $val ) {
			if ( is_bool( $val ) ) {
				$out[ $key ] = $val ? 'yes' : 'no';
				continue;
			}

			if ( is_scalar( $val ) ) {
				$s = trim( (string) $val );
				if ( '' !== $s && '0000-00-00' !== $s ) {
					$out[ $key ] = $s;
				}
			}
		}

		return $out;
	}

	/**
	 * Human-readable label for a form key (included in the AI prompt so it
	 * understands what document it is filling).
	 */
	private static function form_label( string $form_key ): string {
		$labels = [
			'woc_petition'               => 'Petition for Dissolution of Non-Covenant Marriage Without Minor Children',
			'woc_summons'                => 'Summons (Divorce Without Children)',
			'woc_sensitive_data'         => 'Family Department Sensitive Data Cover Sheet (Without Children)',
			'woc_preliminary_injunction' => 'Preliminary Injunction (Divorce Without Children)',
			'woc_divorce_decree'         => 'Divorce Decree — No Minor Children',
			'woc_consent_decree'         => 'Consent Decree (Without Children)',
			'woc_default_application'    => 'Application and Affidavit for Entry of Default',
			'woc_motion_default_decree'  => 'Motion and Affidavit for Default Decree Without Hearing',
			'wc_petition'                => 'Petition for Dissolution of Non-Covenant Marriage WITH Minor Children',
			'wc_summons'                 => 'Summons (Divorce With Children)',
			'wc_sensitive_data'          => 'Family Department Sensitive Data Cover Sheet (With Children)',
			'wc_preliminary_injunction'  => 'Preliminary Injunction (Divorce With Children)',
			'wc_health_insurance_notice' => 'Notice of Your Rights About Health Insurance Coverage',
			'wc_affidavit_minor_children'=> 'Affidavit Regarding Minor Children',
			'wc_parent_info_program'     => 'Order and Notice to Attend Parenting Information Program Class',
			'wc_parenting_plan'          => 'Parenting Plan',
			'wc_child_support_worksheet' => 'Child Support Worksheet',
			'wc_default_application'     => 'Application and Affidavit for Entry of Default (With Children)',
			'wc_decree_minor_children'   => 'Decree of Dissolution With Minor Children',
			'wc_consent_decree'          => 'Consent Decree (With Children)',
			'notice_regarding_creditors' => 'Notice Regarding Creditors',
		];

		return $labels[ $form_key ] ?? $form_key;
	}

	private static function api_key(): string {
		if ( defined( 'CASE_ENGINE_OPENAI_API_KEY' ) && CASE_ENGINE_OPENAI_API_KEY ) {
			return trim( (string) CASE_ENGINE_OPENAI_API_KEY );
		}

		if ( defined( 'OPENAI_API_KEY' ) && OPENAI_API_KEY ) {
			return trim( (string) OPENAI_API_KEY );
		}

		$env = getenv( 'OPENAI_API_KEY' );
		if ( $env ) {
			return trim( $env );
		}

		return trim( (string) get_option( 'case_engine_openai_api_key', '' ) );
	}

	// -------------------------------------------------------------------------
	// OpenAI request
	// -------------------------------------------------------------------------

	private static function call_openai( array $context ) {
		$key = self::api_key();
		if ( '' === $key ) {
			return new \WP_Error( 'openai_key_missing', 'OpenAI API key is not configured.' );
		}

		$payload = [
			'model'           => self::model(),
			'temperature'     => 0,
			'response_format' => self::response_format(),
			'messages'        => [
				[
					'role'    => 'system',
					'content' => self::system_prompt(),
				],
				[
					'role'    => 'user',
					'content' => wp_json_encode( $context, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT ),
				],
			],
		];

		$http = wp_remote_post(
			self::OPENAI_CHAT_COMPLETIONS_URL,
			[
				'timeout' => 90,
				'headers' => [
					'Authorization' => 'Bearer ' . $key,
					'Content-Type'  => 'application/json',
				],
				'body' => wp_json_encode( $payload ),
			]
		);

		if ( is_wp_error( $http ) ) {
			return $http;
		}

		$code = (int) wp_remote_retrieve_response_code( $http );
		$body = (string) wp_remote_retrieve_body( $http );

		if ( $code < 200 || $code >= 300 ) {
			return new \WP_Error( 'openai_http_error', "OpenAI API error ({$code}): {$body}" );
		}

		return $body;
	}

	private static function parse_response( string $body ) {
		$decoded = json_decode( $body, true );
		if ( ! is_array( $decoded ) ) {
			return new \WP_Error( 'openai_invalid_json', 'OpenAI response was not valid JSON.' );
		}

		$content = trim( (string) ( $decoded['choices'][0]['message']['content'] ?? '' ) );
		if ( '' === $content ) {
			return new \WP_Error( 'openai_empty_content', 'OpenAI returned an empty message.' );
		}

		$parsed = json_decode( $content, true );
		if ( ! is_array( $parsed ) ) {
			return new \WP_Error( 'openai_invalid_content', 'OpenAI content was not valid JSON.' );
		}

		return $parsed;
	}

	// -------------------------------------------------------------------------
	// Validation
	// -------------------------------------------------------------------------

	/**
	 * Validate AI fills and build the final field array.
	 *
	 * Safety rules (in priority order):
	 * 1. Never fill protected fields (signature/notary/judge/SSN).
	 * 2. Never overwrite existing manual mapping values.
	 * 3. Discard empty or suspiciously long values.
	 * 4. For checkboxes (Button), value must be one of the listed non-Off options.
	 * 5. Reject fills below the confidence threshold.
	 * 6. must declare should_fill = true.
	 */
	private static function validate_and_merge( array $parsed, array $fillable, array $existing ): array {
		$result    = self::empty_result();
		$threshold = self::confidence_threshold();

		$fills = is_array( $parsed['fills'] ?? null ) ? $parsed['fills'] : [];

		foreach ( $fills as $fill ) {
			if ( ! is_array( $fill ) ) {
				continue;
			}

			$field_name  = trim( (string) ( $fill['field_name'] ?? '' ) );
			$value       = (string) ( $fill['value'] ?? '' );
			$confidence  = (float) ( $fill['confidence'] ?? 0 );
			$key_used    = (string) ( $fill['computed_key_used'] ?? '' );
			$reason      = (string) ( $fill['reason'] ?? '' );
			$should_fill = self::truthy( $fill['should_fill'] ?? false );

			if ( ! $should_fill || '' === $field_name || '' === trim( $value ) ) {
				continue;
			}

			if ( self::is_protected_field( $field_name ) ) {
				$result['skipped'][] = [ 'field_name' => $field_name, 'reason' => 'protected_field' ];
				continue;
			}

			if ( isset( $existing[ $field_name ] ) && '' !== trim( (string) $existing[ $field_name ] ) ) {
				continue;
			}

			if ( $confidence < $threshold ) {
				$result['skipped'][] = [
					'field_name' => $field_name,
					'reason'     => 'low_confidence:' . $confidence,
				];
				continue;
			}

			$field_meta = $fillable[ $field_name ] ?? null;

			if ( null !== $field_meta && 'Button' === ( $field_meta['type'] ?? '' ) ) {
				$options   = $field_meta['options'] ?? [];
				$non_off   = array_values( array_filter( $options, fn( $o ) => ! in_array( strtolower( $o ), [ 'off' ], true ) ) );

				if ( ! empty( $non_off ) && ! in_array( $value, $non_off, true ) ) {
					// Try auto-correcting case — AI sometimes guesses "On" when PDF uses "on".
					$lower_value   = strtolower( $value );
					$matched_option = null;
					foreach ( $non_off as $opt ) {
						if ( strtolower( $opt ) === $lower_value ) {
							$matched_option = $opt;
							break;
						}
					}

					if ( null !== $matched_option ) {
						$value = $matched_option;
					} else {
						$result['skipped'][] = [
							'field_name' => $field_name,
							'reason'     => 'invalid_checkbox_option:' . $value . ' not in [' . implode( ',', $non_off ) . ']',
						];
						continue;
					}
				}
			}

			if ( strlen( $value ) > 500 ) {
				$result['skipped'][] = [ 'field_name' => $field_name, 'reason' => 'value_too_long' ];
				continue;
			}

			$result['fields'][ $field_name ] = $value;
			$result['filled'][]              = compact( 'field_name', 'key_used', 'value', 'confidence', 'reason' );
		}

		$skipped = is_array( $parsed['skipped'] ?? null ) ? $parsed['skipped'] : [];
		foreach ( $skipped as $skip ) {
			if ( is_array( $skip ) && isset( $skip['field_name'] ) ) {
				$result['skipped'][] = [
					'field_name' => (string) $skip['field_name'],
					'reason'     => (string) ( $skip['reason'] ?? '' ),
				];
			}
		}

		return $result;
	}

	// -------------------------------------------------------------------------
	// Protection rules
	// -------------------------------------------------------------------------

	private static function is_protected_field( string $name ): bool {
		foreach ( Case_Engine_PDF_Engine::SKIP_FIELDS as $skip ) {
			if ( 0 === strcasecmp( $name, $skip ) ) {
				return true;
			}
		}

		$lower    = strtolower( $name );
		$patterns = [
			'signature', 'notary', 'judge', 'date signed',
			'commission expires', 'bar number', 'lawyers bar',
			'social security', ' ssn', 'clerk', 'court use',
		];
		foreach ( $patterns as $p ) {
			if ( false !== strpos( $lower, $p ) ) {
				return true;
			}
		}

		return false;
	}

	private static function truthy( $v ): bool {
		if ( is_bool( $v ) ) {
			return $v;
		}

		return in_array( strtolower( trim( (string) $v ) ), [ '1', 'true', 'yes', 'on', 'enabled' ], true );
	}

	// -------------------------------------------------------------------------
	// System prompt — fine-tuned Arizona court form expert
	// -------------------------------------------------------------------------

	private static function system_prompt(): string {
		return <<<'PROMPT'
You are an expert legal document processor specializing in Arizona Superior Court AcroForm PDF forms for divorce and family law cases. Your job is to intelligently fill PDF form fields using verified case data.

## CONTEXT YOU RECEIVE
- `form_label`: Human-readable name of the form you are filling
- `case_data`: Verified facts organized by category (petitioner, respondent, case_info, children)
- `already_filled`: Fields already populated by the deterministic mapper — do NOT touch these
- `fields_to_fill`: Every remaining unfilled field with:
  - `name`: Internal AcroForm field name (may be cryptic)
  - `alt`: Human-readable alternate label (often more informative)
  - `type`: "Text" or "Button" (checkbox/radio)
  - `options`: For Button fields, the valid state values
  - `hint`: (when present) The semantic category this field belongs to
  - `suggested_value`: (when present) The pre-computed value for this field
  - `checked_value`: (when present) The exact string that checks a checkbox

## YOUR DECISION RULES

### Text fields
1. Use `suggested_value` directly when provided — it is pre-verified.
2. Otherwise use both `name` and `alt` to identify what the field is asking for.
3. Match field intent to `case_data` values. You may:
   - Format dates: "1990-01-15" → "01/15/1990"
   - Combine fields: first + last → full name
   - Extract parts: "John Smith" → "John" for first-name-only fields
   - Format city/state/zip: combine components into "Phoenix, AZ 85001"
   - Derive county: "Maricopa County" from county = "Maricopa"
4. If you cannot confidently map a field to known data, set should_fill=false.

### Button/Checkbox fields
1. Use `checked_value` when provided — it is the exact string that checks the box.
2. The value MUST be exactly one of the listed `options` (case-sensitive).
3. Arizona court PDF checkboxes use either "No"/"Off" or "On"/"Off" as their states — always use the non-"Off" option to check.
4. Only check a box when the case data clearly supports it (e.g., check "Self without a Lawyer" if self_represented=yes).
5. Never check both competing checkboxes in the same group.

### Arizona-specific field patterns
- "Person Filing" = petitioner full name
- "Petitioner/Party A", "PetitionerParty A" = petitioner full name
- "Respondent/Party B", "RespondentParty B" = respondent full name
- "Address if not protected" = petitioner street address
- Fields with "[1]" suffix = petitioner's version; "[2]" = respondent's version
- "Birthdate", "Date of Birth" fields: use MM/DD/YYYY format
- "Age" fields: calculate from date of birth if DOB is known
- "Case Number" / "Case No" / "CNbr" = the case number
- "ATLAS Number" = leave blank (court-assigned, not known at filing)
- "County" fields = use county_filing from case_info
- Child fields (Name, Birthdate, Age) numbered 1-N correspond to children array in order

### NEVER fill these regardless of what data you have
- Signature fields
- Notary fields  
- Judge / court official fields
- Date Signed fields
- Social Security Number fields
- Attorney Bar Number / Lawyers Bar Number
- Commission Expires fields
- Any field marked "For Clerk's Use Only" or "Court Use Only"

## CONFIDENCE SCORING
- 0.95–1.0: `suggested_value` was provided or field+value match is unambiguous
- 0.80–0.94: Strong semantic match between field label and known data
- 0.65–0.79: Reasonable inference with some uncertainty
- Below 0.60: Do not fill — put in skipped with reason

## OUTPUT FORMAT
Return JSON with exactly two keys: `fills` (array) and `skipped` (array).
Each fill must include all required fields. Process EVERY field in fields_to_fill — either fill it or explain why it was skipped.
PROMPT;
	}

	// -------------------------------------------------------------------------
	// Response format schema
	// -------------------------------------------------------------------------

	private static function response_format(): array {
		return [
			'type'        => 'json_schema',
			'json_schema' => [
				'name'   => 'acroform_fill_result',
				'strict' => true,
				'schema' => [
					'type'                 => 'object',
					'additionalProperties' => false,
					'required'             => [ 'fills', 'skipped' ],
					'properties'           => [
						'fills'   => [
							'type'  => 'array',
							'items' => [
								'type'                 => 'object',
								'additionalProperties' => false,
								'required'             => [ 'field_name', 'computed_key_used', 'value', 'confidence', 'reason', 'should_fill' ],
								'properties'           => [
									'field_name'        => [ 'type' => 'string' ],
									'computed_key_used' => [ 'type' => 'string' ],
									'value'             => [ 'type' => 'string' ],
									'confidence'        => [ 'type' => 'number', 'minimum' => 0, 'maximum' => 1 ],
									'reason'            => [ 'type' => 'string' ],
									'should_fill'       => [ 'type' => 'boolean' ],
								],
							],
						],
						'skipped' => [
							'type'  => 'array',
							'items' => [
								'type'                 => 'object',
								'additionalProperties' => false,
								'required'             => [ 'field_name', 'reason' ],
								'properties'           => [
									'field_name' => [ 'type' => 'string' ],
									'reason'     => [ 'type' => 'string' ],
								],
							],
						],
					],
				],
			],
		];
	}
}
