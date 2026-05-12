<?php

namespace NewfoldLabs\WP\Module\Insights;

use NewfoldLabs\Container\NotFoundException;
use NewfoldLabs\WP\ModuleLoader\Container;
use NewfoldLabs\WP\Module\Insights\Admin\Admin;
use NewfoldLabs\WP\Module\Insights\Controllers\RestController;

/**
 * Manages all the functionalities for the module.
 */
class Insights {
	/**
	 * Dependency injection container.
	 *
	 * @var Container
	 */
	protected $container;

	/**
	 * Constructor for the Insights class.
	 *
	 * @param Container $container The module container.
	 */
	public function __construct( Container $container ) {
		$this->container = $container;

		if ( $this->can_view_insights() ) {
			$admin = new Admin();
			$admin->register_hooks();

			\add_action( 'rest_api_init', array( $this, 'init_rest_api' ) );
		}
	}

	/**
	 * Check if the current user can view insights.
	 *
	 * @return bool
	 * @throws NotFoundException If capability is not found.
	 */
	public function can_view_insights() {
		$capabilities = $this->container->get( 'capabilities' )->all();

		return ! empty( $capabilities['canScanPerformance'] );
	}

	/**
	 * Initialize REST API.
	 */
	public function init_rest_api() {
		$api = new RestController();
		$api->register_routes();
	}
}
