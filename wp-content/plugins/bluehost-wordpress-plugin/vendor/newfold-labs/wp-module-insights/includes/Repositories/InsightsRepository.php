<?php

namespace NewfoldLabs\WP\Module\Insights\Repositories;

/**
 * Class InsightsRepository
 *
 * Handles data persistence for Insights module.
 */
class InsightsRepository {

	/**
	 * Option key for storing the scans results.
	 *
	 * @var string
	 */
	const SCANS_OPTION = 'nfd_insights_scans_results';

	/**
	 * Option key for storing the recurring scans status.
	 *
	 * @var string
	 */
	const RECURRING_SCANS_OPTION = 'nfd_insights_recurring_scans_status';

	/**
	 * Transient key for scan lock.
	 *
	 * @var string
	 */
	const SCAN_LOCK_TRANSIENT = 'nfd_insights_scan_pending';

	/**
	 * Transient key for caching API results.
	 *
	 * @var string
	 */
	const CACHE_TRANSIENT = 'nfd_insights_scan_results';

	/**
	 * Option key for storing the site secret.
	 *
	 * @var string
	 */
	const SITE_SECRET_OPTION = 'nfd_insights_site_secret_key';

	/**
	 * Maximum number of scans to store.
	 *
	 * @var int
	 */
	const MAX_SCANS_STORED = 30;

	/**
	 * Get stored scans.
	 *
	 * @return array
	 */
	public function get_scans() {
		return get_option( self::SCANS_OPTION, array() );
	}

	/**
	 * Update stored scans.
	 *
	 * @param array $scans Scans list.
	 * @return bool
	 */
	public function update_scans( $scans ) {
		return update_option( self::SCANS_OPTION, $scans );
	}

	/**
	 * Get recurring scans status.
	 *
	 * @return bool
	 */
	public function get_recurring_scans_status() {
		return (bool) get_option( self::RECURRING_SCANS_OPTION, false );
	}

	/**
	 * Update recurring scans status.
	 *
	 * @param bool $status Status.
	 * @return bool
	 */
	public function update_recurring_scans_status( $status ) {
		return update_option( self::RECURRING_SCANS_OPTION, $status );
	}

	/**
	 * Check if scan is locked (in progress).
	 *
	 * @return bool
	 */
	public function is_scan_locked() {
		return get_transient( self::SCAN_LOCK_TRANSIENT ) !== false;
	}

	/**
	 * Lock scan (mark as in progress).
	 *
	 * @param int $duration Seconds.
	 * @return bool
	 */
	public function lock_scan( $duration ) {
		return set_transient( self::SCAN_LOCK_TRANSIENT, true, $duration );
	}

	/**
	 * Unlock scan.
	 *
	 * @return bool
	 */
	public function unlock_scan() {
		return delete_transient( self::SCAN_LOCK_TRANSIENT );
	}

	/**
	 * Get cached API results.
	 *
	 * @return mixed|false
	 */
	public function get_cached_results() {
		return get_transient( self::CACHE_TRANSIENT );
	}

	/**
	 * Set cached API results.
	 *
	 * @param mixed $data       Data to cache.
	 * @param int   $expiration Expiration time in seconds.
	 * @return bool
	 */
	public function set_cached_results( $data, $expiration ) {
		return set_transient( self::CACHE_TRANSIENT, $data, $expiration );
	}

	/**
	 * Get site secret.
	 *
	 * @return string
	 */
	public function get_site_secret() {
		return get_option( self::SITE_SECRET_OPTION, '' );
	}

	/**
	 * Update site secret.
	 *
	 * @param string $secret Site secret key.
	 * @return bool
	 */
	public function update_site_secret( $secret ) {
		return update_option( self::SITE_SECRET_OPTION, $secret );
	}
}
