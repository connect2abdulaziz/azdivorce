<?php

namespace WPKIN_THANK_YOU_PAGE;

/**
 * Plugin Helper Class
 *
 * @since 2.0.0
 */
class Helper {

	public static function wpkin_tr_plugins_version_control() {
		if ( function_exists( 'wpkin_tyr_v' ) ) {
			return wpkin_tyr_v()->can_use_premium_code();
		}
		return false;
	}
}
