<?php

namespace NewfoldLabs\WP\Module\Insights\Services;

use NewfoldLabs\WP\Module\Data\HiiveConnection;
use NewfoldLabs\WP\Module\Insights\Repositories\InsightsRepository;
use WP_Error;

/**
 * Class HiiveService
 *
 * Handles direct communication with Hiive API.
 */
class HiiveService {

	/**
	 * Get scan results from Hiive.
	 *
	 * @param array $params Query parameters.
	 * @return array|WP_Error
	 */
	public function get_scans( $params = array() ) {
		$defaults = array(
			'per_page' => InsightsRepository::MAX_SCANS_STORED,
			'group_by' => 'day',
		);

		return $this->request( 'GET', 'sites/v2/performance-scanner/scans', wp_parse_args( $params, $defaults ) );
	}

	/**
	 * Trigger a new scan.
	 *
	 * @return array|WP_Error
	 */
	public function trigger_scan() {
		return $this->request( 'POST', 'sites/v2/performance-scanner/scans/run' );
	}

	/**
	 * Toggle recurring scans.
	 *
	 * @param bool $status The new status.
	 * @return array|WP_Error
	 */
	public function toggle_recurring( $status ) {
		return $this->request(
			'POST',
			'sites/v2/performance-scanner/toggle-recurring',
			array(
				'schedule_status' => $status,
			)
		);
	}

	/**
	 * Make a request to Hiive.
	 *
	 * @param string $method GET, POST, etc.
	 * @param string $endpoint Endpoint path.
	 * @param array  $body Body parameters.
	 * @return array|WP_Error
	 */
	protected function request( $method, $endpoint, $body = null ) {
		if ( ! HiiveConnection::is_connected() ) {
			return new WP_Error(
				'rest_forbidden',
				__( 'Site is not connected to Hiive.', 'wp-module-insights' ),
				array( 'status' => 403 )
			);
		}

		$connection = new HiiveConnection();
		try {

			$site_secret = $this->get_site_secret();
			if ( empty( $site_secret ) ) {
				return new WP_Error( 'rest_api_error', __( 'Invalid site secret.', 'wp-module-insights' ), array( 'status' => 500 ) );
			}

			$headers = array(
				'Content-Type'  => 'application/json',
				'Accept'        => 'application/json',
				'Authorization' => 'Bearer ' . $connection::get_auth_token(),
				'X-Site-Secret' => $site_secret,
			);

			$args = array(
				'method'  => $method,
				'headers' => $headers,
			);

			$response = $connection->hiive_request( $endpoint, $body, $args );

			if ( is_wp_error( $response ) ) {
				return $response;
			}

			$response_body = wp_remote_retrieve_body( $response );
			$data          = json_decode( $response_body, true );

			if ( json_last_error() !== JSON_ERROR_NONE ) {
				return new WP_Error( 'rest_api_error', __( 'Error decoding API response.', 'wp-module-insights' ), array( 'status' => 500 ) );
			}

			return $data;
		} catch ( \Exception $e ) {
			return new WP_Error( 'hiive_request_error', $e->getMessage() );
		}
	}

	/**
	 * Get or generate the site secret.
	 *
	 * @return string|null
	 */
	public function get_site_secret() {

		$site_secret = get_option( 'nfd_insights_site_secret_key', '' );

		if ( empty( $site_secret ) ) {

			try {
				$site_secret = bin2hex( random_bytes( 32 ) );

				if ( ! $this->register_site_secret( $site_secret ) ) {
					return null;
				}

				update_option( 'nfd_insights_site_secret_key', $site_secret );

			} catch ( \Exception $e ) {
				return null;
			}
		}

		return $site_secret;
	}

	/**
	 * Register the secret with Hiive.
	 *
	 * @param string $secret Site secret.
	 * @return string|false
	 */
	protected function register_site_secret( $secret ) {
		if ( ! HiiveConnection::is_connected() ) {
			return false;
		}

		$connection = new HiiveConnection();
		$path       = 'sites/v2/performance-scanner';

		$response = $connection->hiive_request(
			$path,
			null,
			array(
				'headers' => array(
					'Content-Type'  => 'application/json',
					'Accept'        => 'application/json',
					'Authorization' => 'Bearer ' . $connection::get_auth_token(),
					'X-Site-Secret' => $secret,
				),
			)
		);

		if ( is_wp_error( $response ) ) {
			return false;
		}

		return true;
	}
}
