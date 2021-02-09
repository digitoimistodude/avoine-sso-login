<?php
/**
 * Plugin Name: Avoine SSO Login
 * Description: Support SSO login from Avoine Sense
 * Plugin URI: http://dude.fi
 * Author: Timi Wahalahti, Digitoimisto Dude Oy
 * Author URI: http://dude.fi
 * Version: 1.0.0
 * License: GPLv3
 *
 * @Author:             Timi Wahalahti, Digitoimisto Dude Oy (https://dude.fi)
 * @Date:               2019-09-24 10:21:21
 * @Last Modified by:   Timi Wahalahti
 * @Last Modified time: 2021-02-09 14:55:14
 *
 * @package avoine-sso
 */

if ( ! defined( 'ABSPATH' ) ) {
  exit;
}

/**
 * Hello.
 */
class Avoine_SSO_Login {

  /**
   *  Instance of this class.
   *
   *  @var null
   */
  private static $instance = null;

  /**
   *  Add hooks.
   *
   *  @since 0.1.0
   */
  private function __construct() {
    add_action( 'init', array( $this, 'capture_login_redirect' ) );
    add_action( 'init', array( $this, 'capture_sso_logout' ) );
    add_filter( 'logout_url', array( $this, 'maybe_modify_sso_user_logout_url' ), 20, 2 );
  } // end __construct

  /**
   *  Get instance and init the plugin.
   *
   *  @since  0.1.0
   */
  public static function get_instance() {
    if ( null === self::$instance ) {
      self::$instance = new self();
    }

    return self::$instance;
  } // end get_instance

  /**
   *  Get login url to use for SSO logins.
   *
   *  @since  0.1.0
   *  @param  string $return_url where to return after succesfull login, defaults to home url.
   *  @return string             url to use for login.
   */
  public static function get_sso_login_url( $return_url = null ) {
    // default to home url for return.
    if ( empty( $return_url ) ) {
      $return_url = home_url();
    }

    // get service ID for Avoine
    $service = apply_filters( 'avoine_sso_service_id', getenv( 'AVOINE_SSO_SERVICE_ID' ) );

    // filter return url
    $return_url = apply_filters( 'avoine_sso_login_return_url', $return_url );

    // bail if no service ID
    if ( empty( $service ) ) {
      return;
    }

    return "https://tunnistus.avoine.fi/sso-login/?service={$service}&return={$return_url}";
  } // end get_sso_login_url

  /**
   *  Check if user is logged in from sso.
   *
   *  @since  0.1.0
   *  @param  int $user_id user id to check, defaulst to current user.
   *  @return boolean           true if user is logged in from sso, otherwise false.
   */
  public static function is_sso_user( $user_id = null ) {
    // default to current user
    if ( empty( $user_id ) ) {
      $user_id = get_current_user_id();
    }

    // every sso uset should have idp stored, so try to get it
    $sso_idp = get_user_meta( $user_id, 'avoine_sso_idp', true );

    // no idp stored means that user is not logged in from sso
    if ( empty( $sso_idp ) ) {
      return false;
    }

    return true;
  } // end is_sso_user

  /**
   *  Check if request is redirect from sso login and log our
   *  WP user in if user is coming from succesfull sso login.
   *
   *  @since  0.1.0
   */
  public static function capture_login_redirect() {
    // bail if not coming from sso login
    if ( ! isset( $_POST['ssoid'] ) ) {
      return;
    }

    // validate that the ssoid returned really exists
    $sso_user = self::validate_ssoid( sanitize_text_field( $_POST['ssoid'] ) );

    // bail if ssoid validation failed
    if ( ! $sso_user ) {
      return;
    }

    // check that user is active in sso
    $sso_user_active = self::login_check_is_sso_user_active( $sso_user );

    // bail if user is not active in sso
    if ( ! $sso_user_active ) {
      // logout
      do_action( 'wp_login_failed', '' );
      wp_logout();

      // do our custom action allowing developers to hook
      do_action( 'avoine_sso_login_failed' );

      // redirect to better place, defauls to login url
      wp_safe_redirect( apply_filters( 'avoine_sso_login_redirect_failed', wp_login_url() ) );
      return;
    }

    // get wp user attached to this sso user, function tries to create a one if not existing
    $wp_user = self::get_wp_user( $sso_user );

    // bail if no attached user
    if ( ! is_a( $wp_user, 'WP_User' ) ) {
      // logout
      do_action( 'wp_login_failed', '' );
      wp_logout();

      // do our custom action allowing developers to hook
      do_action( 'avoine_sso_login_failed' );

      // redirect to better place, defauls to login url
      wp_safe_redirect( apply_filters( 'avoine_sso_login_redirect_failed', wp_login_url() ) );
      return;
    }

    // update WP user SSO ID
    update_user_meta( $wp_user->ID, 'avoine_sso_' . $sso_user->idp . '_ssoid', $sso_user->id );

    // log our user in
    wp_set_current_user( $wp_user->ID, $wp_user->user_login );
    wp_set_auth_cookie( $wp_user->ID, true );

    // do login actions
    do_action( 'wp_login', $wp_user->user_login, $wp_user );
    do_action( 'avoine_sso_login', $wp_user->user_login, $wp_user );
  } // end capture_login_redirect

  /**
   *  Check if request is to logout sso user out. Request comes from
   *  Avoine logout page in hidden iframe.
   *
   *  @since  0.1.0
   */
  public static function capture_sso_logout() {
    // if user is not logged in, there's no need to try to logout
    if ( ! is_user_logged_in() ) {
      return;
    }

    // bail if request is not coming to sso logout url
    if ( 'sso-logout' !== ltrim( untrailingslashit( wp_parse_url( $_SERVER['REQUEST_URI'] )['path'] ), '/' ) ) {
      return;
    }

    // do logout if current user is sso user
    if ( self::is_sso_user() ) {
      // return 200 for Avoine logout page iframe to work correctly
      status_header( 200 );

      // do the logout
      wp_logout();
      wp_set_current_user( 0 );

      // dun our action allowing developers to hook
      do_action( 'avoine_sso_after_logout' );

      // show logout message in case user sees the sso logput page and stop further execution
      echo apply_filters( 'avoine_sso_logout_message', 'You have been logged out. <a href="' . home_url() . '">Back to the site.</a>' );
      exit;
    }

    // redirect logged in user to home url if not sso user
    wp_safe_redirect( apply_filters( 'avoine_sso_logout_redirect_non_sso_user', home_url() ) );
    exit;
  } // end capture_sso_logout

  /**
   *  Modify return of wp_logout_url if user is sso user.
   *
   *  @since  0.1.0
   *  @param  string $logout_url url where logout happens.
   *  @param  string $redirect   where to redirect after logout, does not apply to sso logout.
   *  @return string             url where logout happens.
   */
  public static function maybe_modify_sso_user_logout_url( $logout_url, $redirect ) {
    // return default logout url if not sso user
    if ( ! self::is_sso_user() ) {
      return $logout_url;
    }

    return apply_filters( 'avoine_sso_logout_url', 'https://tunnistus.avoine.fi/sso-logout/' );
  } // end maybe_modify_sso_user_logout_url

  /**
   *  Do requests to Avoine SSO server.
   *
   *  @since  0.1.0
   *  @return mixed  boolean false if sso call failed, result data id succesfull
   */
  private static function call_sso_service( $sso_user_id = null, $method = 'GetUser' ) {
    // gather data for request
    $body = wp_json_encode( array(
      'id'      => wp_generate_uuid4(),
      'method'  => $method,
      'params'  => array(
        apply_filters( 'avoine_sso_communications_key', getenv( 'AVOINE_SSO_KEY' ) ),
        $sso_user_id,
      ),
      'jsonrpc' => '2.0',
    ) );

    // do the request
    $request = wp_remote_post( 'https://tunnistus.avoine.fi/mmserver', array(
      'body'  => $body,
    ) );

    // bail if request returned something else than 200 OK
    if ( 200 !== wp_remote_retrieve_response_code( $request ) ) {
      return false;
    }

    // get request result body
    $response = wp_remote_retrieve_body( $request );

    // bail if empty response
    if ( empty( $response ) ) {
      return false;
    }

    // response is always json, so decode it
    $response = json_decode( $response );

    // bail if response does not have unique id
    if ( ! isset( $response->id ) ) {
      return false;
    }

    // bail if no result
    if ( ! isset( $response->result ) ) {
      return false;
    }

    return $response->result;
  } // end call_sso_service

  /**
   *  Validate that sso ID given actually exists in Avoine sso.
   *
   *  @since  0.1.0
   *  @param  string  $ssoid SSO ID.
   *  @return mixed          boolean false if not vaid sso id, sso user object if valid
   */
  private static function validate_ssoid( $ssoid = null ) {
    if ( empty( $ssoid ) ) {
      return false;
    }

    $result = self::call_sso_service( $ssoid );

    return $result;
  } // end validate_ssoid

  /**
   *  Get all sso user infrmation the service has.
   *
   *  @since  0.1.0
   *  @param  string  $ssoid SSO ID
   *  @return mixed          boolean false or sso user object
   */
  private static function get_sso_user_information( $ssoid = null ) {
    return self::call_sso_service( $ssoid, 'GetUserData' );
  } // end get_sso_user_information

  /**
   *  During the login process, check that sso user is active. Defaults always to
   *  active (boolean true), developrs can add  their own checks with filter.
   *
   *  @since  0.1.0
   *  @param  object  $sso_user sso user object.
   *  @return boolean           true if sso user is active, false otherwise
   */
  private static function login_check_is_sso_user_active( $sso_user = null ) {
    // bail if no sso user
    if ( empty( $sso_user ) ) {
      return false;
    }

    // get all information about the sso user
    $sso_user_info = self::get_sso_user_information( $sso_user->id );

    // bail if no user info
    if ( empty( $sso_user_info ) ) {
      return false;
    }

    /**
     *  Allow developers to filter the active status.
     *  $sso_user is sso user object
     *  $sso_user_info is object containing all user information, which do alter based on which sso service we are using
     */
    $active = apply_filters( 'avoine_sso_login_check_is_user_active', true, $sso_user, $sso_user_info );

    // allow developers to hook after login activity check
    do_action( 'avoine_after_login_check_sso_is_user_active', $sso_user, $sso_user_info );

    return $active;
  } // end login_check_is_sso_user_active

  /**
   *  Function to check sso user actvity based on wp user id,
   *  defaults to current user. Returns false if user is not
   *  sso user, otherwise defaults to active (boolean true).
   *  Developers can add their own checks with filter.
   *
   *  @since  0.1.0
   *  @param  integer $wp_user_id ID of WP user which we want to check, defaults to current user.
   *  @return boolean             false if not active or nor sso user, true otherwise.
   */
  public static function is_sso_user_active( $wp_user_id = null ) {
    // default to current user
    if ( empty( $wp_user_id ) ) {
      $wp_user_id = get_current_user_id();
    }

    // bail if no user
    if ( empty( $wp_user_id ) ) {
      return false;
    }

    // try to get from cache
    $active = wp_cache_get( 'user_activity_' . $wp_user_id,  'avoine_sso_login' );
    if ( 'active' === $active ) {
      return true;
    }

    // get sso idp for getting the sso id
    $idp = get_user_meta( $wp_user_id, 'avoine_sso_idp', true );

    // bail if no idp
    if ( empty( $idp ) ) {
      return false;
    }

    // get user sso id
    $ssoid = get_user_meta( $wp_user_id, "avoine_sso_{$idp}_ssoid", true );

    // bail if no sso id
    if ( empty( $ssoid ) ) {
      return false;
    }

    // get user information from sso server
    $sso_user_info = self::get_sso_user_information( $ssoid );

    // bail if no user infomation
    if ( empty( $sso_user_info ) ) {
      return false;
    }

    /**
     *  Allow developers to filter the active status.
     *  $wp_user_id is the wp user id checked
     *  $ssoid is sso id for the user
     *  $sso_user_info is object containing all user information, which do alter based on which sso service we are using
     */
    $active = apply_filters( 'avoine_sso_is_user_active', true, $wp_user_id, $ssoid, $sso_user_info );

    // allow developers to hook after acivity check
    do_action( 'avoine_after_sso_is_user_active', $wp_user_id, $active );

    // save to cache
    $save_active = 'not-active';
    if ( $active ) {
      $save_active = 'active';
    }

    wp_cache_set( 'user_activity_' . $wp_user_id, $save_active, 'avoine_sso_login', MINUTE_IN_SECONDS * 30 );

    return $active;
  } // is_sso_user_active

  /**
   *  Get WP user attached to sso user. If user does not exist yet,
   *  try co create a one.
   *
   *  @since  0.1.0
   *  @param  object $sso_user sso user object.
   *  @return mixed            WP_User object if user is attached, false otherwise.
   */
  private static function get_wp_user( $sso_user = null ) {
    // bail if no sso user
    if ( empty( $sso_user ) ) {
      return false;
    }

    // get wp users attached to sso user
    $users = get_users( array(
      'search'  => $sso_user->local_id,
    ) );

    // user does not exist, create a one
    if ( empty( $users ) ) {
      return self::create_wp_user( $sso_user );
    }

    return reset( $users );
  } // end get_wp_user

  /**
   *  Create a WP user from sso user.
   *
   *  @since  0.1.0
   *  @param  object $sso_user sso user object.
   *  @return mixed            WP_User object if user is created, false otherwise.
   */
  private static function create_wp_user( $sso_user = null ) {
    // bail if no sso user
    if ( empty( $sso_user ) ) {
      return false;
    }

    // get all user information from sso server
    $sso_user_info = self::get_sso_user_information( $sso_user->id );

    // bail if we cant get all information
    if ( empty( $sso_user_info ) ) {
      return false;
    }

    // gather userdata for new user, developers can alter with filter
    $userdata = apply_filters( 'avoine_sso_create_userdata', array(
      'user_email' => $sso_user->local_id . '@' . wp_parse_url( get_site_url() )['host'],
      'user_login' => $sso_user->local_id,
      'first_name' => '',
      'last_name'  => '',
      'user_pass'  => null,
    ), $sso_user, $sso_user_info );

    // we want to have user pass as null always
    $userdata['user_pass'] = null;

    // try to create a new user
    $new_user_id = wp_insert_user( $userdata );

    // return false it user creation failed for some reason
    if ( is_wp_error( $new_user_id ) ) {
      return false;
    }

    // save sso details to newly created wp user
    update_user_meta( $new_user_id, 'avoine_sso_idp', $sso_user->idp );
    update_user_meta( $new_user_id, 'avoine_sso_' . $sso_user->idp . '_ssoid', $sso_user->id );
    update_user_meta( $new_user_id, 'avoine_sso_' . $sso_user->idp . '_local_id', $sso_user->local_id );

    // allow developers to hook after user is created
    do_action( 'avoine_sso_after_create_user', $new_user_id, $sso_user, $sso_user_info );

    return get_userdata( $new_user_id );
  } // end create_wp_user
} // end class

add_action( 'plugins_loaded', array( 'Avoine_SSO_Login', 'get_instance' ) );

// check class function for documentation
function avoine_sso_get_login_url( $return_url = null ) {
  return Avoine_SSO_Login::get_sso_login_url( $return_url );
} // end avoine_sso_login_url

// check class function for documentation
function avoine_is_sso_user( $user_id = null ) {
  return Avoine_SSO_Login::is_sso_user( $user_id );
} // end avoine_is_sso_user

// check class function for documentation
function avoine_is_sso_user_active( $user_id = null ) {
  return Avoine_SSO_Login::is_sso_user_active( $user_id );
} // end avoine_is_sso_user_active
