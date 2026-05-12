<?php

namespace WPKIN_THANK_YOU_PAGE\Admin;

/**
 * Menu Handler Class
 */
class Menu {

	/**
	 * Admin Menu Constructor
	 */
	public function __construct() {

		add_action( 'admin_menu', [ $this, 'admin_menu' ] );

	}

		/**
		 * Register Admin Menu
		 *
		 * @return void
		 */
		public function admin_menu() {
			add_menu_page(
				__( 'ThankRedirect', 'wc-thank-you-page' ),
				__( 'ThankRedirect', 'wc-thank-you-page' ),
				'manage_options',
				'wc-thank-you-page',
				[ $this, 'wc_thank_you_page' ],
				WPKIN_THANKYOU_URL . '/images/dash-icon-24x24.svg',
				56
			);

			// "Settings" submenu (loads the same page for now)
			add_submenu_page(
				'wc-thank-you-page', // parent slug
				__( 'Settings', 'wc-thank-you-page' ),
				__( 'Settings', 'wc-thank-you-page' ),
				'manage_options',
				'wc-thank-you-page', // unique slug for settings
				[ $this, 'wc_thank_you_page' ] // callback for settings
			);

			// "Getting Started" submenu
			add_submenu_page(
				'wc-thank-you-page',
				__( 'Getting Started', 'wc-thank-you-page' ),
				__( 'Getting Started', 'wc-thank-you-page' ),
				'manage_options',
				'wc-thank-you-page-getting-started',
				[ $this, 'getting_started_page' ]
			);

	}

	/**
	 * WC Thank You Page Handler
	 *
	 * @return void
	 */
	public function wc_thank_you_page() {
		echo "<div id='wpte-wc-thank-you-page'></div>";
	}

	/**
	 * Getting Started Page Handler
	 *
	 * @return void
	 */
	public function getting_started_page() {
		echo "<div id='wpkin-wc-thank-you-getting-started'></div>";
	}



}