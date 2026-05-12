<?php

namespace NewfoldLabs\WP\Module\Insights\Admin;

use NewfoldLabs\WP\Module\Insights\Repositories\InsightsRepository;

/**
 * Class Admin
 *
 * Handles Admin UI registration and assets.
 */
class Admin {

	/**
	 * Insights Repository.
	 *
	 * @var InsightsRepository
	 */
	protected $repository;

	/**
	 * Constructor.
	 *
	 * @param InsightsRepository|null $repository Insights Repository.
	 */
	public function __construct( InsightsRepository $repository = null ) {
		$this->repository = $repository ? $repository : new InsightsRepository();
	}

	/**
	 * Register hooks.
	 */
	public function register_hooks() {
		\add_action( 'admin_menu', array( $this, 'add_insights_menu_link' ) );
		\add_action( 'admin_enqueue_scripts', array( $this, 'insights_page_assets' ) );
	}

	/**
	 * Add "Insights" sub-link to admin tools menu.
	 */
	public function add_insights_menu_link() {
		\add_submenu_page(
			'tools.php',
			__( 'Insights', 'wp-module-insights' ),
			__( 'Insights', 'wp-module-insights' ),
			'manage_options',
			'nfd-insights',
			array( $this, 'render_insights_page' )
		);
	}

	/**
	 * Render "Insights" page root
	 */
	public function render_insights_page() {
		echo '<div id="nfd-insights-app"></div>';
	}

	/**
	 * Enqueue assets and set locals.
	 */
	public function insights_page_assets() {
		$asset_file = NFD_INSIGHTS_DIR . '/build/insights-page/bundle.asset.php';
		if ( is_readable( $asset_file ) ) {
			$asset = include $asset_file;
		} else {
			return;
		}

		\wp_register_script(
			'insights-page',
			NFD_INSIGHTS_PLUGIN_URL . 'vendor/newfold-labs/wp-module-insights/build/insights-page/bundle.js',
			array_merge(
				$asset['dependencies'],
				array( 'wp-element' ),
			),
			$asset['version'],
			true
		);

		\wp_register_style(
			'insights-page',
			NFD_INSIGHTS_PLUGIN_URL . 'vendor/newfold-labs/wp-module-insights/build/insights-page/insights-page.css',
			null,
			$asset['version']
		);

		$screen = \get_current_screen();
		if ( isset( $screen->id ) && ( false !== strpos( $screen->id, 'nfd-insights' ) ) ) {
			\wp_enqueue_script( 'insights-page' );
			\wp_enqueue_style( 'insights-page' );

			\wp_localize_script(
				'insights-page',
				'NFD_INSIGHTS_DATA',
				array(
					'isRunningScan'           => $this->repository->is_scan_locked(),
					'isRecurringScansEnabled' => $this->repository->get_recurring_scans_status(),
				)
			);
		}
	}
}
