<?php

namespace WPKIN_THANK_YOU_PAGE\API;

if ( ! defined( 'ABSPATH' ) ) {
    wp_die( esc_html__( 'You can\'t access this page', 'wc-thank-you-page' ) );
}

/**
 * Admin Routers
 *
 * @since 1.0.0
 */
class Routers extends Callback {

    /**
     * Routers class constructor
     *
     * @since 1.0.0
     */
    public function __construct() {
        add_action( 'rest_api_init', [ $this, 'wpkin_ty_register_api_init' ] );
    }

    public function wpkin_ty_register_api_init() {
        register_rest_route(
            'wpkin-thankyou-page/v1', '/settings', [
                'methods'             => 'GET',
                'callback'            => [ $this, 'wpkin_get_thankyou_settings' ],
                'permission_callback' => [ $this, 'wpkin_thankyou_check_permissions' ],
            ]
        );

        register_rest_route(
            'wpkin-thankyou-page/v1', '/save-settings', [
                'methods'             => 'POST',
                'callback'            => [ $this, 'wpkin_save_thankyou_settings' ],
                'permission_callback' => [ $this, 'wpkin_thankyou_check_permissions' ],
            ]
        );
    }

    public function wpkin_thankyou_check_permissions( $request ) {
        $nonce = $request->get_header( 'X-WP-Nonce' );
        if ( ! wp_verify_nonce( $nonce, 'wp_rest' ) ) {
            return new \WP_Error( 'rest_forbidden', esc_html__( 'You are not allowed to access this resource.', 'wc-thank-you-page' ), [ 'status' => 403 ] );
        }

        return $this->wpkin_thankyou_user_has_role( [ 'administrator', 'editor', 'shop_manager' ] );
    }

    public function wpkin_thankyou_user_has_role( $roles ) {
        $user = wp_get_current_user();
        if ( empty( $user ) ) {
            return false;
        }

        foreach ( $roles as $role ) {
            if ( in_array( $role, (array) $user->roles, true ) ) {
                return true;
            }
        }
        return false;
    }
}