<?php
/**
 * @Author: Timi Wahalahti
 * @Date:   2022-04-22 14:11:04
 * @Last Modified by:   Timi Wahalahti
 * @Last Modified time: 2022-04-25 15:12:28
 *
 * @package avoine-sso-login
 */

if ( ! defined( 'ABSPATH' ) ) {
  exit;
}

/**
 * @since 2.0.0
 */
function avoine_sso_get_login_url( $return_url = null ) {
  return Avoine_SSO_Login\get_sso_login_url( $return_url );
} // end avoine_sso_get_login_url

/**
 * @since 2.0.0
 */
function avoine_sso_get_logout_url() {
  return Avoine_SSO_Login\get_sso_logout_url();
} // end avoine_sso_get_logout_url

/**
 * @since 2.0.0
 */
function avoine_is_sso_user( $user_id = null ) {
  return Avoine_SSO_Login\is_sso_user( $user_id );
} // end avoine_is_sso_user

/**
 * @since 2.0.0
 */
function avoine_is_sso_user_active( $user_id = null ) {
  return Avoine_SSO_Login\is_sso_user_active( $user_id );
} // end avoine_is_sso_user_active
