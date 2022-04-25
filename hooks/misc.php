<?php
/**
 * @Author: Timi Wahalahti
 * @Date:   2022-04-22 14:31:30
 * @Last Modified by:   Timi Wahalahti
 * @Last Modified time: 2022-04-25 15:22:03
 *
 * @package avoine-sso-login
 */

namespace Avoine_SSO_Login;

if ( ! defined( 'ABSPATH' ) ) {
  exit;
}

/**
 * Add SSO service domain to allowed hosts for wp_safe_redirect.
 *
 * @since 2.0.0
 */
function add_sso_service_domain_to_allowed_hosts( $hosts ) {
  $new_hosts = [
    get_sso_service_domain(),
  ];

  return array_merge( $hosts, $new_hosts );
} // end add_sso_service_domain_to_allowed_hosts

/**
 * Prevent CMS user from logging in with WP password.
 *
 * @since 2.0.0
 * @param bool       $check    Whether the passwords match.
 * @param string     $password The plaintext password.
 * @param string     $hash     The hashed password.
 * @param string|int $user_id  User ID. Can be empty.
 */
function prevent_sso_user_login_with_wp_password( $check, $password, $hash, $user_id ) {
  if ( ! is_sso_user( $user_id ) ) {
    return $check;
  }

  do_action( 'avoine_sso_login\user\prevented_wp_login' );

  return false;
} // end prevent_sso_user_login_with_wp_password

/**
 * Prevent CMS user from resetting their WP shadow user password.
 *
 * @since 2.0.0
 * @param WP_User|false $user_data WP_User object if found, false if the user does not exist.
 * @param WP_Error      $errors    A WP_Error object containing any errors generated by using invalid credentials.
 */
function prevent_sso_user_wp_password_reset( $user_data, $errors ) {
  if ( ! is_a( $user_data, 'WP_User' ) ) {
    return $user_data;
  }

  if ( ! is_sso_user( $user_data->ID ) ) {
    return $user_data;
  }

  do_action( 'avoine_sso_login\user\prevented_password_reset' );

  return null;
} // end prevent_sso_user_wp_password_reset

/**
 * Prevent sending password reset emails for CMS shadow users in case
 * someone resets their WP user password.
 *
 * @since 2.0.0
 * @param array $pass_change_email  Used to build wp_mail().
 * @param array $user               The original user array.
 */
function prevent_sso_user_wp_password_reset_email( $pass_change_email, $user ) {
  if ( is_sso_user( $user['ID'] ) ) {
    return false;
  }

  do_action( 'avoine_sso_login\user\prevented_password_reset\email' );

  return $pass_change_email;
} // end prevent_sso_user_wp_password_reset_email
