<?php
/**
 * @Author: Timi Wahalahti
 * @Date:   2022-04-22 13:52:03
 * @Last Modified by:   Timi Wahalahti
 * @Last Modified time: 2022-04-25 15:20:50
 *
 * @package avoine-sso-login
 */

namespace Avoine_SSO_Login;

if ( ! defined( 'ABSPATH' ) ) {
  exit;
}

/**
 *  Validate that sso ID given actually exists in Avoine sso.
 *
 *  @since  2.0.0
 *  @param  string $ssoid  SSO ID.
 *  @return mixed          boolean false if not vaid sso id, sso user object if valid
 */
function validate_ssoid( $ssoid = null ) {
  if ( empty( $ssoid ) ) {
    return false;
  }

  return call_api( $ssoid );
} // end validate_ssoid

/**
 *  During the login process, check that SSO user is active. Defaults always to
 *  active (boolean true), developrs can add  their own checks with filter.
 *
 *  @since  2.0.0
 *  @param  object $sso_user SSO user object.
 *  @return boolean          True if SSO user is active, false otherwise
 */
function login_check_is_sso_user_active( $sso_user = null ) {
  // bail if no SSO user
  if ( empty( $sso_user ) ) {
    return false;
  }

  // get all information about the SSO user
  $sso_user_info = get_sso_user_information( $sso_user->id );
  if ( empty( $sso_user_info ) ) {
    return false;
  }

  /**
   *  Allow developers to filter the active status.
   *  $sso_user is SSO user object
   *  $sso_user_info is object containing all user information, which do alter based on which SSO service we are using
   */
  $active = apply_filters( 'avoine_sso_login\login\user_is_active', true, $sso_user, $sso_user_info );
  $active = apply_filters( 'avoine_sso_login_check_is_user_active', $active, $sso_user, $sso_user_info ); // legacy support

  // allow developers to hook after login activity check
  do_action( 'avoine_sso_login\login\user_is_active\after', $sso_user, $sso_user_info );
  do_action( 'avoine_after_login_check_sso_is_user_active', $sso_user, $sso_user_info ); // legacy support

  return $active;
} // end login_check_is_sso_user_active
