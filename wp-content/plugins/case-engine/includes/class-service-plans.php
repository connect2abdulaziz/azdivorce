<?php
/**
 * Service plans (DIY / Fully Guided), pricing shortcode, and marketing pages/nav.
 *
 * @package Case_Engine
 */

defined( 'ABSPATH' ) || exit;

class Case_Engine_Service_Plans {

	const PLAN_DIY     = 'diy';
	const PLAN_GUIDED  = 'guided';
	const DIY_PRICE    = '450';
	const GUIDED_PRICE = '799';
	const DIY_SLUG     = 'diy-divorce';
	const GUIDED_SLUG  = 'fully-guided-divorce';

	/**
	 * Register hooks.
	 */
	public static function register() {
		add_shortcode( 'az_pricing', array( __CLASS__, 'render_pricing' ) );
		add_shortcode( 'az_services', array( __CLASS__, 'render_services' ) );
		add_action( 'init', array( __CLASS__, 'maybe_ensure_store_and_pages' ), 20 );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'enqueue_assets' ) );
	}

	/**
	 * Front styles for pricing / services pages.
	 */
	public static function enqueue_assets() {
		if ( ! is_singular() ) {
			return;
		}
		$post = get_post();
		if ( ! $post ) {
			return;
		}
		$content = (string) $post->post_content;
		$needs   = has_shortcode( $content, 'az_pricing' )
			|| has_shortcode( $content, 'az_services' )
			|| in_array( $post->post_name, array( 'pricing', 'services' ), true );
		if ( ! $needs ) {
			return;
		}
		wp_enqueue_style(
			'case-engine-intake',
			CASE_ENGINE_PLUGIN_URL . 'assets/intake.css',
			array(),
			CASE_ENGINE_VERSION
		);
		wp_enqueue_style(
			'case-engine-pricing',
			CASE_ENGINE_PLUGIN_URL . 'assets/pricing.css',
			array( 'case-engine-intake' ),
			CASE_ENGINE_VERSION
		);
	}

	/**
	 * Plan catalog for UI / checkout.
	 *
	 * @return array
	 */
	public static function get_plans() {
		return array(
			self::PLAN_DIY => array(
				'slug'        => self::PLAN_DIY,
				'name'        => __( 'DIY Divorce', 'case-engine' ),
				'price'       => self::DIY_PRICE,
				'tagline'     => __( 'Generate your personal divorce documents', 'case-engine' ),
				'features'    => array(
					__( 'Generate personal divorce documents', 'case-engine' ),
					__( 'Unlimited editing', 'case-engine' ),
					__( 'Customer support', 'case-engine' ),
				),
				'product_option' => 'case_engine_product_id_diy',
				'product_slug'   => self::DIY_SLUG,
			),
			self::PLAN_GUIDED => array(
				'slug'        => self::PLAN_GUIDED,
				'name'        => __( 'Fully Guided', 'case-engine' ),
				'price'       => self::GUIDED_PRICE,
				'tagline'     => __( 'We help file, manage, and serve', 'case-engine' ),
				'features'    => array(
					__( 'Generate personal divorce documents', 'case-engine' ),
					__( 'We file your documents', 'case-engine' ),
					__( 'We manage your case', 'case-engine' ),
					__( 'We help you serving your spouse', 'case-engine' ),
				),
				'product_option' => 'case_engine_product_id_guided',
				'product_slug'   => self::GUIDED_SLUG,
				'popular'        => true,
			),
		);
	}

	/**
	 * Normalize plan slug.
	 *
	 * @param string $plan Plan key.
	 * @return string diy|guided
	 */
	public static function normalize_plan( $plan ) {
		$plan = strtolower( trim( (string) $plan ) );
		return ( self::PLAN_GUIDED === $plan || 'fully_guided' === $plan || 'full' === $plan )
			? self::PLAN_GUIDED
			: self::PLAN_DIY;
	}

	/**
	 * Ensure USD store currency, Woo products, marketing pages, and header nav.
	 */
	public static function maybe_ensure_store_and_pages() {
		if ( get_option( 'case_engine_plans_setup_version' ) === CASE_ENGINE_VERSION ) {
			return;
		}
		if ( class_exists( 'WooCommerce' ) ) {
			self::ensure_usd_currency();
			self::ensure_plan_products();
		}
		self::ensure_marketing_pages();
		self::ensure_primary_navigation();
		update_option( 'case_engine_plans_setup_version', CASE_ENGINE_VERSION );
	}

	/**
	 * Force store currency to USD for US pricing.
	 */
	public static function ensure_usd_currency() {
		if ( 'USD' !== get_option( 'woocommerce_currency' ) ) {
			update_option( 'woocommerce_currency', 'USD' );
		}
		// Prefer US-style formatting: $450.00
		update_option( 'woocommerce_currency_pos', 'left' );
		update_option( 'woocommerce_price_thousand_sep', ',' );
		update_option( 'woocommerce_price_decimal_sep', '.' );
		update_option( 'woocommerce_price_num_decimals', '2' );
		if ( 'UA' === substr( (string) get_option( 'woocommerce_default_country' ), 0, 2 ) ) {
			update_option( 'woocommerce_default_country', 'US:AZ' );
		}
	}

	/**
	 * Create or update DIY / Fully Guided WooCommerce products.
	 */
	public static function ensure_plan_products() {
		if ( ! function_exists( 'wc_get_product_id_by_sku' ) && ! function_exists( 'wc_get_products' ) ) {
			return;
		}
		foreach ( self::get_plans() as $plan_key => $plan ) {
			$product_id = (int) get_option( $plan['product_option'], 0 );
			if ( $product_id > 0 && function_exists( 'wc_get_product' ) ) {
				$existing = wc_get_product( $product_id );
				if ( $existing ) {
					$existing->set_regular_price( $plan['price'] );
					$existing->set_price( $plan['price'] );
					$existing->set_name( $plan['name'] );
					$existing->set_status( 'publish' );
					$existing->set_catalog_visibility( 'hidden' );
					$existing->set_virtual( true );
					$existing->save();
					continue;
				}
			}

			// Find by slug.
			$found_id = 0;
			if ( function_exists( 'wc_get_products' ) ) {
				$found = wc_get_products( array(
					'limit'  => 1,
					'status' => array( 'publish', 'draft', 'private' ),
					'slug'   => $plan['product_slug'],
					'return' => 'ids',
				) );
				if ( ! empty( $found[0] ) ) {
					$found_id = (int) $found[0];
				}
			}

			if ( $found_id > 0 && function_exists( 'wc_get_product' ) ) {
				$product = wc_get_product( $found_id );
			} else {
				$product = new WC_Product_Simple();
				$product->set_slug( $plan['product_slug'] );
			}

			$product->set_name( $plan['name'] );
			$product->set_regular_price( $plan['price'] );
			$product->set_price( $plan['price'] );
			$product->set_status( 'publish' );
			$product->set_catalog_visibility( 'hidden' );
			$product->set_virtual( true );
			$product->set_sold_individually( true );
			$product->set_short_description( $plan['tagline'] );
			$product->set_description( implode( "\n", $plan['features'] ) );
			$new_id = $product->save();
			if ( $new_id ) {
				update_option( $plan['product_option'], (int) $new_id );
			}
		}
	}

	/**
	 * Resolve Woo product ID for a service plan.
	 *
	 * @param string $plan diy|guided
	 * @return int
	 */
	public static function get_product_id_for_plan( $plan ) {
		$plan = self::normalize_plan( $plan );
		$plans = self::get_plans();
		$meta  = $plans[ $plan ];
		$product_id = (int) get_option( $meta['product_option'], 0 );

		if ( $product_id <= 0 && function_exists( 'wc_get_products' ) ) {
			$found = wc_get_products( array(
				'limit'  => 1,
				'status' => 'publish',
				'slug'   => $meta['product_slug'],
				'return' => 'ids',
			) );
			if ( ! empty( $found[0] ) ) {
				$product_id = (int) $found[0];
				update_option( $meta['product_option'], $product_id );
			}
		}

		return (int) apply_filters( 'case_engine_plan_product_id', $product_id, $plan );
	}

	/**
	 * Display info for a plan (for intake JS).
	 *
	 * @param string $plan diy|guided
	 * @return array
	 */
	public static function get_plan_price_info( $plan ) {
		$plan  = self::normalize_plan( $plan );
		$meta  = self::get_plans()[ $plan ];
		$id    = self::get_product_id_for_plan( $plan );
		$info  = array(
			'id'         => $id,
			'slug'       => $plan,
			'name'       => $meta['name'],
			'price'      => $meta['price'],
			'price_html' => '$' . number_format( (float) $meta['price'], 2 ),
			'features'   => $meta['features'],
			'tagline'    => $meta['tagline'],
			'popular'    => ! empty( $meta['popular'] ),
		);

		if ( $id > 0 && function_exists( 'wc_get_product' ) ) {
			$product = wc_get_product( $id );
			if ( $product ) {
				$info['name']  = $product->get_name();
				$info['price'] = (string) $product->get_price();
				$raw           = function_exists( 'wc_price' ) ? wc_price( $product->get_price() ) : ( '$' . $info['price'] );
				$info['price_html'] = html_entity_decode(
					wp_strip_all_tags( (string) $raw ),
					ENT_QUOTES | ENT_HTML5,
					'UTF-8'
				);
			}
		}

		return $info;
	}

	/**
	 * Pricing payload for intake JS.
	 *
	 * @return array
	 */
	public static function get_plans_pricing_for_js() {
		return array(
			'diy'    => self::get_plan_price_info( self::PLAN_DIY ),
			'guided' => self::get_plan_price_info( self::PLAN_GUIDED ),
		);
	}

	/**
	 * Create Services / Pricing / States pages if missing.
	 */
	public static function ensure_marketing_pages() {
		$pages = array(
			'services' => array(
				'title'   => 'Services',
				'content' => "<!-- wp:shortcode -->\n[az_services]\n<!-- /wp:shortcode -->",
			),
			'pricing'  => array(
				'title'   => 'Pricing',
				'content' => "<!-- wp:shortcode -->\n[az_pricing]\n<!-- /wp:shortcode -->",
			),
			'states'   => array(
				'title'   => 'States',
				'content' => "<h2>Arizona divorce documents</h2>\n<p>Legal Divorce Docs currently supports uncontested divorce paperwork for Arizona counties. Start your divorce to begin your Arizona case.</p>\n<p><a class=\"az-intake-btn az-intake-btn-primary\" href=\"" . esc_url( home_url( '/start-your-divorce/' ) ) . "\">Get started</a></p>",
			),
		);

		foreach ( $pages as $slug => $cfg ) {
			$existing = get_page_by_path( $slug );
			if ( $existing ) {
				continue;
			}
			wp_insert_post( array(
				'post_title'   => $cfg['title'],
				'post_name'    => $slug,
				'post_status'  => 'publish',
				'post_type'    => 'page',
				'post_content' => $cfg['content'],
			) );
		}
	}

	/**
	 * Update block theme navigation (wp_navigation) to divorce.com-style items.
	 */
	public static function ensure_primary_navigation() {
		$nav_posts = get_posts( array(
			'post_type'      => 'wp_navigation',
			'post_status'    => 'publish',
			'posts_per_page' => 5,
			'orderby'        => 'ID',
			'order'          => 'ASC',
		) );
		if ( empty( $nav_posts ) ) {
			return;
		}

		$services = get_page_by_path( 'services' );
		$pricing  = get_page_by_path( 'pricing' );
		$states   = get_page_by_path( 'states' );
		$start    = get_page_by_path( 'start-your-divorce' );
		$account  = function_exists( 'wc_get_page_id' ) ? get_post( wc_get_page_id( 'myaccount' ) ) : get_page_by_path( 'my-account' );

		$items = array();
		if ( $services ) {
			$items[] = self::nav_link_block( 'Services', get_permalink( $services ) );
		}
		if ( $pricing ) {
			$items[] = self::nav_link_block( 'Pricing', get_permalink( $pricing ) );
		}
		if ( $states ) {
			$items[] = self::nav_link_block( 'States', get_permalink( $states ) );
		}
		if ( $account ) {
			$items[] = self::nav_link_block( 'Log in', get_permalink( $account ) );
		}
		if ( $start ) {
			$items[] = '<!-- wp:navigation-link {"label":"Get started","type":"page","id":' . (int) $start->ID . ',"url":"' . esc_url( get_permalink( $start ) ) . '","kind":"post-type","className":"az-nav-cta"} /-->';
		}

		$content = implode( "\n", $items );
		// Update the lowest-ID navigation (primary header ref) once unless manually locked.
		$nav = $nav_posts[0];
		if ( get_post_meta( $nav->ID, '_case_engine_nav_locked', true ) ) {
			return;
		}
		wp_update_post( array(
			'ID'           => $nav->ID,
			'post_content' => $content,
		) );
		update_post_meta( $nav->ID, '_case_engine_nav_managed', 1 );
	}

	/**
	 * @param string $label Label.
	 * @param string $url URL.
	 * @return string
	 */
	private static function nav_link_block( $label, $url ) {
		return sprintf(
			'<!-- wp:navigation-link {"label":%s,"url":%s,"kind":"custom"} /-->',
			wp_json_encode( $label ),
			wp_json_encode( esc_url_raw( $url ) )
		);
	}

	/**
	 * [az_pricing] shortcode.
	 *
	 * @return string
	 */
	public static function render_pricing() {
		$plans = self::get_plans();
		$start = home_url( '/start-your-divorce/' );
		ob_start();
		?>
		<div class="az-pricing">
			<header class="az-pricing__intro">
				<h2 class="az-pricing__title"><?php esc_html_e( 'Choose a divorce package', 'case-engine' ); ?></h2>
				<p class="az-pricing__subtitle"><?php esc_html_e( 'Transparent flat fees. Court filing fees are separate.', 'case-engine' ); ?></p>
			</header>
			<div class="az-pricing__grid">
				<?php foreach ( $plans as $key => $plan ) : ?>
					<?php $info = self::get_plan_price_info( $key ); ?>
					<article class="az-pricing__card<?php echo ! empty( $plan['popular'] ) ? ' is-popular' : ''; ?>">
						<?php if ( ! empty( $plan['popular'] ) ) : ?>
							<span class="az-pricing__badge"><?php esc_html_e( 'Most support', 'case-engine' ); ?></span>
						<?php endif; ?>
						<h3 class="az-pricing__name"><?php echo esc_html( $info['name'] ); ?></h3>
						<p class="az-pricing__price"><?php echo esc_html( $info['price_html'] ); ?></p>
						<p class="az-pricing__tagline"><?php echo esc_html( $plan['tagline'] ); ?></p>
						<ul class="az-pricing__features">
							<?php foreach ( $plan['features'] as $feature ) : ?>
								<li><?php echo esc_html( $feature ); ?></li>
							<?php endforeach; ?>
						</ul>
						<p><a class="az-intake-btn az-intake-btn-primary" href="<?php echo esc_url( add_query_arg( 'plan', $key, $start ) ); ?>"><?php esc_html_e( 'Get started', 'case-engine' ); ?></a></p>
					</article>
				<?php endforeach; ?>
			</div>
		</div>
		<?php
		return ob_get_clean();
	}

	/**
	 * [az_services] shortcode.
	 *
	 * @return string
	 */
	public static function render_services() {
		ob_start();
		?>
		<div class="az-pricing az-services">
			<header class="az-pricing__intro">
				<h2 class="az-pricing__title"><?php esc_html_e( 'Our services', 'case-engine' ); ?></h2>
				<p class="az-pricing__subtitle"><?php esc_html_e( 'Uncontested Arizona divorce paperwork and guided filing support.', 'case-engine' ); ?></p>
			</header>
			<?php echo self::render_pricing(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
		</div>
		<?php
		return ob_get_clean();
	}
}
