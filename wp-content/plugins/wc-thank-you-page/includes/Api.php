<?php

namespace WPKIN_THANK_YOU_PAGE;

if ( ! defined( 'ABSPATH' ) ) {
	wp_die( esc_html__( 'You can\'t access this page', 'wc-thank-you-page' ) );
}

/**
 * Api Handler Class
 *
 * @since 1.0.0
 */
class Api {

	/**
	 * Api class constructor
	 *
	 * @since 1.0.0
	 */
	public function __construct() {
		new API\Routers();
	}
}
