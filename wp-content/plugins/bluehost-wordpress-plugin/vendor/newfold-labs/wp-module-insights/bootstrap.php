<?php

use NewfoldLabs\WP\ModuleLoader\Container;
use NewfoldLabs\WP\Module\Insights\Insights;

use function NewfoldLabs\WP\ModuleLoader\register;

if ( function_exists( 'add_action' ) ) {
	add_action(
		'plugins_loaded',
		function () {
			register(
				array(
					'name'     => 'wp-module-insights',
					'label'    => __( 'Insights', 'wp-module-insights' ),
					'callback' => function ( Container $container ) {
						new Insights( $container );
						define( 'NFD_INSIGHTS_DIR', __DIR__ );
						define( 'NFD_INSIGHTS_BUILD_DIR', __DIR__ . '/build/' );
						define( 'NFD_INSIGHTS_PLUGIN_URL', $container->plugin()->url );
						define( 'NFD_INSIGHTS_PLUGIN_DIRNAME', dirname( $container->plugin()->basename ) );
					},
					'isActive' => true,
					'isHidden' => true,
				)
			);
		}
	);

}
