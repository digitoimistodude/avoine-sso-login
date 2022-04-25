<?php
/**
 * @Author: Timi Wahalahti
 * @Date:   2022-04-22 13:53:26
 * @Last Modified by:   Timi Wahalahti
 * @Last Modified time: 2022-04-25 15:20:22
 *
 * @package avoine-sso-login
 */

namespace Avoine_SSO_Login;

if ( ! defined( 'ABSPATH' ) ) {
  exit;
}

/**
 *  Check if request is redirect from SSO login and log our
 *  WP user in if coming from succesfull SSO login.
 *
 *  @since  2.0.0
 */
function capture_login_redirect() {
  // bail if not coming from SSI login
  if ( ! isset( $_POST['ssoid'] ) ) { //phpcs:ignore WordPress.Security.NonceVerification.Missing
    return;
  }

  // validate that the ssoid returned really exists
  $sso_user = validate_ssoid( sanitize_text_field( $_POST['ssoid'] ) ); //phpcs:ignore WordPress.Security.NonceVerification.Missing

  // bail if ssoid validation failed
  if ( ! $sso_user ) {
    return;
  }

  // check that user is active in SSO
  $sso_user_active = login_check_is_sso_user_active( $sso_user );

  // bail if user is not active in SSO
  if ( ! $sso_user_active ) {
    handle_failed_login();
  }

  // get wp user attached to this SSO user, function tries to create a one if not existing
  $wp_user = get_wp_user( $sso_user );

  // bail if no attached user
  if ( ! is_a( $wp_user, 'WP_User' ) ) {
    handle_failed_login();
  }

  // update user details
  $userdata = get_user_data_for_wp( $sso_user );
  $userdata['ID'] = $wp_user->ID;

  $wp_user_updated = wp_update_user( $userdata );
  if ( is_wp_error( $wp_user_updated ) ) {
    handle_failed_login();
  }

  /**
   * Thank you wp_update_user for giving us ID instead of WP_User object.
   * Replace the new userdata in object.
   */
  foreach ( $userdata as $userdata_key => $userdata_value ) {
    $wp_user->{ $userdata_key } = $userdata_value;
  }

  // update WP user SSO idp for later activity checks
  update_user_meta( $wp_user->ID, 'avoine_sso_idp', $sso_user->idp );

  // update WP user SSO ID
  update_user_meta( $wp_user->ID, 'avoine_sso_' . $sso_user->idp . '_ssoid', $sso_user->id );

  // do our custom action allowing developers to hook
  do_action( 'avoine_sso_login\succes\auth\before', $wp_user, $sso_user );
  do_action( 'avoine_sso_before_sso_login', $wp_user, $sso_user ); // legacy support

  // log our WP user in
  wp_set_current_user( $wp_user->ID, $wp_user->user_login );
  wp_set_auth_cookie( $wp_user->ID, true );

  // do login actions
  do_action( 'wp_login', $wp_user->user_login, $wp_user );
  do_action( 'avoine_sso_login\succes\auth\after', $wp_user, $sso_user );
  do_action( 'avoine_sso_login', $wp_user->user_login, $wp_user ); // legacy support
} // end capture_login_redirect

/**
 *  Check if request is to logout sso user out. Request comes from
 *  Avoine logout page in hidden iframe.
 *
 *  @since  2.0.0
 */
function capture_sso_logout() {
  // if user is not logged in, there's no need to try to logout
  if ( ! is_user_logged_in() ) {
    return;
  }

  if ( ! isset( $_SERVER['REQUEST_URI']['path'] ) ) {
    return;
  }

  // bail if request is not coming to sso logout url
  if ( 'sso-logout' !== ltrim( untrailingslashit( wp_parse_url( $_SERVER['REQUEST_URI'] )['path'] ), '/' ) ) {  //phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
    return;
  }

  if ( ! is_sso_user() ) {
    return;
  }

  // return 200 for Avoine logout page iframe to work correctly
  status_header( 200 );

  // do the logout
  wp_logout();
  wp_set_current_user( 0 );

  // run our action allowing developers to hook
  do_action( 'avoine_sso_login\logout\after' );
  do_action( 'avoine_sso_after_logout' ); // legacy support

  // show logout message in case user sees the sso logput page and stop further execution
  $logout_message = 'You have been logged out. <a href="' . home_url() . '">Back to the site.</a>';
  $logout_message = apply_filters( 'avoine_sso_login\logout\message', $logout_message );
  $logout_message = apply_filters( 'avoine_sso_logout_message', $logout_message ); // legacy support

  echo esc_html( $logout_message );
  exit;
} // end capture_sso_logout

/**
 * If logged out user was from SSO, send further down the line
 * to perform also SSO logout.
 *
 * @since 2.0.0
 */
function maybe_redirect_sso_user_to_sso_logout( $user_id ) {
  if ( ! is_sso_user( $user_id ) ) {
    return;
  }

  if ( wp_safe_redirect( get_sso_logout_url() ) ) {
    exit;
  }
} // end maybe_redirect_sso_user_to_sso_logout

/**
 * If SSO login is caputured but for some reason WP shadow user
 * login fails, do something smart.
 *
 * @since 2.0.0
 */
function handle_failed_login() {
  // logout in case WP user login was done
  do_action( 'wp_login_failed', '' );
  wp_logout();

  // do our custom action allowing developers to hook
  do_action( 'avoine_sso_login\failed' );
  do_action( 'avoine_sso_login_failed' ); // legacy support

  // redirect to better place, defauls to login url
  wp_safe_redirect( get_sso_login_failed_redirect_url() );
  exit;
} // end handle_failed_login
