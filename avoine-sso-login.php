<?php
/**
 * Plugin Name: Avoine SSO Login
 * Description: Integrate login to Avoine SSO.
 * Plugin URI: http://dude.fi
 * Author: Digitoimisto Dude Oy
 * Author URI: http://dude.fi
 * Version: 2.0.2
 * License: GPLv3
 *
 * @Author:             Digitoimisto Dude Oy (https://dude.fi)
 * @Date:               2019-09-24 10:21:21
 * @Last Modified by:   Timi Wahalahti
 * @Last Modified time: 2022-04-25 15:20:19
 *
 * @package avoine-sso-login
 */

namespace Avoine_SSO_Login;

if ( ! defined( 'ABSPATH' ) ) {
  exit;
}

// External functions for themes and other plugins
require plugin_dir_path( __FILE__ ) . 'inc/functions-public.php';

// Internal functions
require plugin_dir_path( __FILE__ ) . 'inc/api.php';
require plugin_dir_path( __FILE__ ) . 'inc/login.php';
require plugin_dir_path( __FILE__ ) . 'inc/user.php';

// Miscellaneous hooks
require plugin_dir_path( __FILE__ ) . 'hooks/misc.php';
add_filter( 'allowed_redirect_hosts', __NAMESPACE__ . '\add_sso_service_domain_to_allowed_hosts' );
add_filter( 'check_password',         __NAMESPACE__ . '\prevent_sso_user_login_with_wp_password', 999, 4 );
add_filter( 'lostpassword_user_data', __NAMESPACE__ . '\prevent_sso_user_wp_password_reset', 10, 2 );
add_filter( 'password_change_email',  __NAMESPACE__ . '\prevent_sso_user_wp_password_reset_email', 10, 2 );

// Authentication flow hooks
require plugin_dir_path( __FILE__ ) . 'hooks/auth-flow.php';
add_action( 'init',       __NAMESPACE__ . '\capture_login_redirect' );
add_action( 'init',       __NAMESPACE__ . '\capture_sso_logout' );
add_action( 'wp_logout',  __NAMESPACE__ . '\maybe_redirect_sso_user_to_sso_logout' );

/**
 * Get SSO service ID.
 */
function get_sso_service_id() {
  $service_id = apply_filters( 'avoine_sso_login\service\id', getenv( 'AVOINE_SSO_SERVICE_ID' ) );
  $service_id = apply_filters( 'avoine_sso_service_id', $service_id ); // legacy support
  return $service_id;
} // end get_api_key

/**
 * Get SSO service domain.
 */
function get_sso_service_domain() {
  return apply_filters( 'avoine_sso_login\service\domain', 'tunnistus.avoine.fi' );
} // end get_sso_service_domain

/**
 * Get SSO service communications key-
 */
function get_api_key() {
  $api_key = apply_filters( 'avoine_sso_login\api\key', getenv( 'AVOINE_SSO_KEY' ) );
  $api_key = apply_filters( 'avoine_sso_communications_key', $api_key ); // legacy support
  return $api_key;
} // end get_api_key

/**
 * Get full url for SSO API.
 */
function get_api_url() {
  $sso_service_domain = get_sso_service_domain();
  return apply_filters( 'avoine_sso_login\api\url', "https://{$sso_service_domain}/mmserver" );
} // end get_api_url

/**
 * Get full login url for SSO.
 * @param  string $return_url URL where to redirect user after login, defaults to home.
 * @return string             Full SSO login url with redirect.
 */
function get_sso_login_url( $return_url = null ) {
  $sso_service_domain = get_sso_service_domain();
  $sso_service_id = get_sso_service_id();
  if ( empty( $sso_service_domain ) || empty( $sso_service_id ) ) {
    return false;
  }

  $return_url = ( ! empty( $return_url ) ) ? $return_url : home_url();
  $return_url = apply_filters( 'avoine_sso_login\login\return_url', $return_url );
  $return_url = apply_filters( 'avoine_sso_login_return_url', $return_url ); // legacy support
  $return_url = urlencode( $return_url );

  return "https://{$sso_service_domain}/sso-login/?service={$sso_service_id}&return={$return_url}";
} // end get_sso_login_url

/**
 * Get full logout url for SSO.
 */
function get_sso_logout_url() {
  $sso_service_domain = get_sso_service_domain();
  if ( empty( $sso_service_domain ) ) {
    return false;
  }

  $url = apply_filters( 'avoine_sso_login\logout\url', "https://{$sso_service_domain}/sso-logout/" );
  $url = apply_filters( 'avoine_sso_logout_url', $url ); // legacy support
  return $url;
} // end get_sso_logout_url

/**
 * Get redirect url for failed sso logins.
 */
function get_sso_login_failed_redirect_url() {
  $url = apply_filters( 'avoine_sso_login\failed\redirect_url', wp_login_url() );
  $url = apply_filters( 'avoine_sso_login_redirect_failed', $url ); // legacy support
  return $url;
} // end get_sso_login_failed_redirect_url
