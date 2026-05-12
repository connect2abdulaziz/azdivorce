<?php
/**
 * Case Engine — Roles and capabilities (RBAC).
 *
 * @package Case_Engine
 */

defined( 'ABSPATH' ) || exit;

class Case_Engine_RBAC {

	/** Capability required to see Case Engine admin menu and session list. */
	const VIEW_SESSIONS = 'case_engine_view_sessions';

	/** Capability required to create a case from an intake session. */
	const CREATE_CASE_FROM_SESSION = 'case_engine_create_case_from_session';

	/** Capability required to list/view all cases. */
	const VIEW_CASES = 'case_engine_view_cases';

	/** Capability required to edit case status/details. */
	const EDIT_CASES = 'case_engine_edit_cases';

	/** Capability required to view own cases (client dashboard). */
	const VIEW_OWN_CASES = 'case_engine_view_own_cases';

	/** Capability required to manage Case Engine settings (Admin only). */
	const MANAGE_SETTINGS = 'case_engine_manage_settings';

	/** All Case Engine capabilities. */
	public static function get_all_caps() {
		return array(
			self::VIEW_SESSIONS,
			self::CREATE_CASE_FROM_SESSION,
			self::VIEW_CASES,
			self::EDIT_CASES,
			self::VIEW_OWN_CASES,
			self::MANAGE_SETTINGS,
		);
	}

	/** Caps for Case Manager (no settings, no full admin). */
	public static function get_case_manager_caps() {
		return array(
			self::VIEW_SESSIONS,
			self::CREATE_CASE_FROM_SESSION,
			self::VIEW_CASES,
			self::EDIT_CASES,
			self::VIEW_OWN_CASES,
		);
	}

	/** Caps for Client (dashboard only). */
	public static function get_client_caps() {
		return array( self::VIEW_OWN_CASES );
	}

	/** Role slugs. */
	const ROLE_CASE_MANAGER = 'case_engine_case_manager';
	const ROLE_CLIENT       = 'case_engine_client';

	/**
	 * Install roles and capabilities. Call on plugin activation.
	 */
	public static function install_roles_caps() {
		// Give Administrator all Case Engine caps.
		$admin = get_role( 'administrator' );
		if ( $admin ) {
			foreach ( self::get_all_caps() as $cap ) {
				$admin->add_cap( $cap );
			}
		}

		// Standard WordPress subscribers and WooCommerce customers must be able to see
		// their own dashboard. Grant the single client-facing capability to both roles.
		foreach ( array( 'subscriber', 'customer' ) as $role_slug ) {
			$role = get_role( $role_slug );
			if ( $role ) {
				$role->add_cap( self::VIEW_OWN_CASES );
			}
		}

		// Case Manager: can view sessions, create case from session, view/edit cases, view own.
		$cm_caps = array_fill_keys( self::get_case_manager_caps(), true );
		$cm_caps['read'] = true;
		add_role(
			self::ROLE_CASE_MANAGER,
			__( 'Case Manager', 'case-engine' ),
			$cm_caps
		);

		// Client: can only view own cases (dashboard).
		$client_caps = array_fill_keys( self::get_client_caps(), true );
		$client_caps['read'] = true;
		add_role(
			self::ROLE_CLIENT,
			__( 'Client', 'case-engine' ),
			$client_caps
		);
	}

	/**
	 * Remove Case Engine caps from all roles. Call on plugin deactivation if desired.
	 */
	public static function remove_roles_caps() {
		$all_caps = self::get_all_caps();
		$roles = array( 'administrator', self::ROLE_CASE_MANAGER, self::ROLE_CLIENT );
		foreach ( $roles as $role_slug ) {
			$role = get_role( $role_slug );
			if ( $role ) {
				foreach ( $all_caps as $cap ) {
					$role->remove_cap( $cap );
				}
			}
		}
		remove_role( self::ROLE_CASE_MANAGER );
		remove_role( self::ROLE_CLIENT );
	}
}
