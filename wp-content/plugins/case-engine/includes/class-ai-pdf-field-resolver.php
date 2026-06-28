<?php
/**
 * AI PDF Field Resolver.
 *
 * Acts as the AI brain for AcroForm filling. Given all user/case data and
 * all template fields, it uses OpenAI to intelligently map, derive, and
 * format values — handling every variation of field names (capitalization,
 * abbreviations, label mismatches, checkbox states, etc.).
 *
 * Manual mapping (field-mapping.php) is treated as a trusted seed and is
 * never overwritten. AI fills every remaining field it can reason about.
 *
 * @package Case_Engine
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Case_Engine_AI_PDF_Field_Resolver {

	const OPENAI_CHAT_COMPLETIONS_URL = 'https://api.openai.com/v1/chat/completions';
	const DEFAULT_MODEL               = 'gpt-4o-mini';
	const DEFAULT_CONFIDENCE          = 0.65;

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

		$all_fields    = self::all_fillable_fields( $template_fields, $existing );
		$clean_values  = self::clean_computed_values( $computed );

		if ( empty( $all_fields ) || empty( $clean_values ) ) {
			return $result;
		}

		$context = [
			'form_key'       => (string) ( $args['form_key'] ?? '' ),
			'packet_type'    => (string) ( $args['packet_type'] ?? '' ),
			'case_id'        => (int) ( $args['case_id'] ?? 0 ),
			'case_data'      => $clean_values,
			'already_filled' => self::filled_field_summary( $existing ),
			'fields_to_fill' => array_values( $all_fields ),
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
	// Internal helpers
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
	 * Return all template fields that are NOT already filled by manual mapping
	 * and are NOT protected (signature, notary, etc.).
	 * Keyed by field name for fast lookup.
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
	 * Build a concise summary of what manual mapping already filled
	 * (sent to AI as context so it can cross-reference names etc.).
	 */
	private static function filled_field_summary( array $existing ): array {
		$out = [];
		foreach ( $existing as $name => $value ) {
			if ( '' !== trim( (string) $value ) ) {
				$out[] = [ 'field' => $name, 'value' => (string) $value ];
			}
		}

		return $out;
	}

	/**
	 * Clean computed values: keep only non-empty scalar values.
	 * Booleans are rendered as 'yes'/'no' so AI can reason about them naturally.
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
					'content' => wp_json_encode( $context, JSON_UNESCAPED_UNICODE ),
				],
			],
		];

		$http = wp_remote_post(
			self::OPENAI_CHAT_COMPLETIONS_URL,
			[
				'timeout' => 60,
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
	 * Validate AI fills and build the final result.
	 *
	 * Rules:
	 * 1. Never fill protected fields (signature/notary/judge).
	 * 2. Never overwrite what manual mapping already filled.
	 * 3. Never accept an empty value.
	 * 4. For checkboxes, value must be a valid FieldStateOption.
	 * 5. AI must declare should_fill = true and confidence >= threshold.
	 * 6. AI must not invent data it wasn't given — basic sanity: value length <= 500.
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
				$options = $field_meta['options'] ?? [];
				$off_options = [ 'off', 'Off', 'OFF' ];

				$valid_non_off = array_values( array_filter( $options, function( $o ) use ( $off_options ) {
					return ! in_array( $o, $off_options, true );
				} ) );

				if ( ! empty( $valid_non_off ) && ! in_array( $value, $valid_non_off, true ) ) {
					$result['skipped'][] = [
						'field_name' => $field_name,
						'reason'     => 'invalid_checkbox_option',
					];
					continue;
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

		$lower = strtolower( $name );
		$patterns = [ 'signature', 'notary', 'judge', 'date signed', 'commission expires', 'bar number', 'lawyers bar' ];
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
	// System prompt — AI brain
	// -------------------------------------------------------------------------

	private static function system_prompt(): string {
		return <<<'PROMPT'
You are an expert AI assistant that fills Arizona divorce court AcroForm PDF fields.

You will receive:
- case_data: all known facts about the case (names, addresses, dates, phone numbers, etc.)
- already_filled: fields already filled by a deterministic mapper (do NOT touch these)
- fields_to_fill: every remaining unfilled AcroForm field with its internal name, alt label, type (Text/Button), and checkbox options

Your job:
- For each field in fields_to_fill, decide what value to write using ONLY the facts in case_data.
- Use both the internal field name AND the alt label to understand what the field is asking for.
- Handle every variation: "FullName", "full name", "FULL NAME", "Name", "fullname" — all mean the same thing.
- You may derive, format, and combine values. Examples:
  - "01/15/1990" from a date stored as "1990-01-15"
  - "John" from petitioner_first_name when the field only wants a first name
  - "John Smith" from first+last when the field wants a full name
  - "Maricopa County" from county when the field asks for county
  - "Yes" checkbox when case_data confirms that condition is true
- For Button/checkbox fields, the value MUST be one of the listed FieldStateOptions (never "Off" or "No").
- If the fact needed is simply not in case_data, skip the field with reason "needs_user_answer".
- Never invent Social Security Numbers, employer details, legal conclusions, or any fact not in case_data.
- Never fill attorney bar number or lawyer fields (this is a self-represented filing).
- Never fill signature, notary, judge, or date-signed fields.
- Do not overwrite any field listed in already_filled.

Respond with JSON matching this schema exactly:
{
  "fills": [
    {
      "field_name": "<exact internal field name from fields_to_fill>",
      "computed_key_used": "<which case_data key you used, or 'derived' if you combined multiple>",
      "value": "<the string value to write into the field>",
      "confidence": <0.0 to 1.0>,
      "reason": "<one-line explanation>",
      "should_fill": true
    }
  ],
  "skipped": [
    {
      "field_name": "<exact internal field name>",
      "reason": "<why skipped: needs_user_answer | low_confidence | protected | already_filled>"
    }
  ]
}
PROMPT;
	}

	// -------------------------------------------------------------------------
	// Response format schema (strict JSON)
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
