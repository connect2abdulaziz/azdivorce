<?php

namespace NewfoldLabs\WP\Module\Insights\Services;

use NewfoldLabs\WP\Module\Insights\Repositories\InsightsRepository;
use WP_Error;

/**
 * Class InsightsService
 *
 * Handles business logic for Insights.
 */
class InsightsService {

	/**
	 * Hiive Service.
	 *
	 * @var HiiveService
	 */
	protected $hiive_service;

	/**
	 * Insights Repository.
	 *
	 * @var InsightsRepository
	 */
	protected $repository;

	/**
	 * Constructor.
	 *
	 * @param HiiveService       $hiive_service Hiive Service.
	 * @param InsightsRepository $repository    Insights Repository.
	 */
	public function __construct( HiiveService $hiive_service = null, InsightsRepository $repository = null ) {
		$this->hiive_service = $hiive_service ? $hiive_service : new HiiveService();
		$this->repository    = $repository ? $repository : new InsightsRepository();
	}

	/**
	 * Get scan results (from DB or API).
	 *
	 * @param bool $force_refresh Force refresh from API.
	 * @return array|WP_Error
	 */
	public function get_results( $force_refresh = false ) {
		$scans = $this->repository->get_scans();

		if ( ! empty( $scans ) && ! $force_refresh ) {
			return $scans;
		}

		$cached = $this->repository->get_cached_results();
		if ( false !== $cached && ! $force_refresh ) {
			return $this->format_and_store_scans( $cached );
		}

		$api_data = $this->hiive_service->get_scans();

		if ( is_wp_error( $api_data ) ) {
			return $api_data;
		}

		$this->repository->set_cached_results( $api_data, MINUTE_IN_SECONDS * 30 );

		return $this->format_and_store_scans( $api_data );
	}

	/**
	 * Trigger a new scan.
	 *
	 * @return array|WP_Error
	 */
	public function trigger_scan() {
		return $this->hiive_service->trigger_scan();
	}

	/**
	 * Toggle recurring scans.
	 *
	 * @param bool $status Status.
	 * @return array|WP_Error
	 */
	public function toggle_recurring( $status ) {
		return $this->hiive_service->toggle_recurring( $status );
	}

	/**
	 * Validate webhook signature.
	 *
	 * @param string $validation_key Header X-Validation-Key
	 * @param string $job_id Body jobId
	 * @return bool
	 */
	public function validate_webhook_signature( $validation_key, $job_id ) {
		$secret = $this->hiive_service->get_site_secret();
		if ( empty( $secret ) ) {
			return false;
		}

		$expected = hash_hmac( 'sha256', $job_id, $secret );

		return hash_equals( $expected, $validation_key );
	}

	/**
	 * Format and store scans.
	 *
	 * @param array $scans Raw scans.
	 * @return array Formatted scans.
	 */
	public function format_and_store_scans( $scans ) {
		$formatted = $this->format_scans( $scans );
		$this->repository->update_scans( $formatted );
		return $formatted;
	}

	/**
	 * Add a new scan to the list and store.
	 *
	 * @param array $new_scan New scan data.
	 */
	public function add_scan( $new_scan ) {
		$scans = $this->repository->get_scans();
		if ( ! is_array( $scans ) ) {
			$scans = array();
		}

		$created_at = $new_scan['createdAt'] ?? null;
		if ( $created_at ) {
			foreach ( $scans as $scan ) {
				if ( isset( $scan['createdAt'] ) && $scan['createdAt'] === $created_at ) {
					return;
				}
			}
		}

		$scans[] = $new_scan;
		$this->format_and_store_scans( $scans );
	}

	/**
	 * Keep only the most recent scan per day, then keep latest 30 days.
	 * Optimized version.
	 *
	 * @param array $scans The scans to format.
	 * @return array
	 */
	public function format_scans( $scans ) {
		if ( ! is_array( $scans ) ) {
			return array();
		}

		$by_day = array();

		foreach ( $scans as $scan ) {
			if ( ! is_array( $scan ) || empty( $scan['updatedAt'] ) ) {
				continue;
			}

			$date_str = $scan['updatedAt'];
			$day_key  = substr( $date_str, 0, 10 );

			if ( ! preg_match( '/^\d{4}-\d{2}-\d{2}/', $day_key ) ) {
				$ts = strtotime( $date_str );
				if ( ! $ts ) { continue;
				}
				$day_key = gmdate( 'Y-m-d', $ts );
			}

			if ( ! isset( $by_day[ $day_key ] ) ) {
				$by_day[ $day_key ] = $scan;
				continue;
			}

			if ( strcmp( $scan['updatedAt'], $by_day[ $day_key ]['updatedAt'] ) > 0 ) {
				$by_day[ $day_key ] = $scan;
			}
		}

		krsort( $by_day );

		$filtered = array_values( $by_day );

		return array_slice( $filtered, 0, InsightsRepository::MAX_SCANS_STORED );
	}
}
