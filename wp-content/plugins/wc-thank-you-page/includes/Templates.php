<?php

namespace WPKIN_THANK_YOU_PAGE;

/**
 * The Templates Class Handler
 */
class Templates {

	/**
	 * Templates constructor.
	 */
	public function __construct() {
		// Load Elementor Templates Integration
		new Templates\Elementor\ElementorX();
		// new Templates\Gutenberg\GutenbergX();
	}
}