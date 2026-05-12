<?php

namespace WPKIN_THANK_YOU_PAGE;

/**
 * The Admin Class Handler
 */
class Admin {

	/**
	 * Admin constructor.
	 */
	public function __construct() {

		new Admin\Menu();
		new Admin\Notice();
	}
}