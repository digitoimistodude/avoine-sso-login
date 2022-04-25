<?php
/**
 * @Author: Timi Wahalahti
 * @Date:   2022-04-22 13:42:20
 * @Last Modified by:   Timi Wahalahti
 * @Last Modified time: 2022-04-25 14:55:30
 *
 * @package avoine-sso-login
 */

namespace Avoine_SSO_Login;

if ( ! defined( 'ABSPATH' ) ) {
  exit;
}

/**
 *  Check if user is logged in from SSO.
 *
 *  @since  0.1.0
 *  @param  int $user_id user ID to check, defaulst to current user.
 *  @return boolean           True if user is logged in from SSO, otherwise false.
 */
function is_sso_user( $user_id = null ) {
  // default to current user
  if ( empty( $user_id ) ) {
    $user_id = get_current_user_id();
  }

  // every SSO user should have idp stored, so try to get it
  $sso_idp = get_user_meta( $user_id, 'avoine_sso_idp', true );

  // no idp stored means that user is not logged in from SSO
  if ( empty( $sso_idp ) ) {
    return false;
  }

  return true;
} // end is_sso_user

/**
 *  Function to check sso user actvity based on wp user id,
 *  defaults to current user. Returns false if user is not
 *  SSO user, otherwise defaults to active (boolean true).
 *  Developers can add their own checks with filter.
 *
 *  @since  0.1.0
 *  @param  integer $wp_user_id ID of WP user which we want to check, defaults to current user.
 *  @return boolean             false if not active or nor SSO user, true otherwise.
 */
function is_sso_user_active( $wp_user_id = null ) {
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

  // get SSO idp for getting the sso id
  $idp = get_user_meta( $wp_user_id, 'avoine_sso_idp', true );

  // bail if no idp
  if ( empty( $idp ) ) {
    return false;
  }

  // get user SSO id
  $ssoid = get_user_meta( $wp_user_id, "avoine_sso_{$idp}_ssoid", true );

  // bail if no SSO id
  if ( empty( $ssoid ) ) {
    return false;
  }

  // get user information from SSO server
  $sso_user_info = get_sso_user_information( $ssoid );

  // bail if no user infomation
  if ( empty( $sso_user_info ) ) {
    return false;
  }

  /**
   *  Allow developers to filter the active status.
   *  $wp_user_id is the wp user id checked
   *  $ssoid is SSO ID for the user
   *  $sso_user_info is object containing all user information, which do alter based on which SSO service we are using
   */
  $active = apply_filters( 'avoine-sso-login\user\is-active', true, $wp_user_id, $ssoid, $sso_user_info );
  $active = apply_filters( 'avoine_sso_is_user_active', $active, $wp_user_id, $ssoid, $sso_user_info ); // legacy support

  // allow developers to hook after acivity check
  do_action( 'avoine-sso-login\user\is-active\after', $wp_user_id, $active );
  do_action( 'avoine_after_sso_is_user_active', $wp_user_id, $active ); // legacy support

  // save to cache
  $save_active = $active ? 'active' : 'not-active';

  $expiration = apply_filters( 'auth_cookie_expiration', DAY_IN_SECONDS * 2, $wp_user_id, false );
  $expiration = apply_filters( 'avoine-sso-login\user\is-active\expiration', $expiration );
  $expiration = apply_filters( 'avoine_sso_is_user_active_expiration', $expiration ); // legacy support

  wp_cache_set( 'user_activity_' . $wp_user_id, $save_active, 'avoine_sso_login', $expiration );

  return $active;
} // is_sso_user_active

/**
 *  Get all sso user infrmation the service has.
 *
 *  @since  0.1.0
 *  @param  string $ssoid SSO ID
 *  @return mixed         boolean false or sso user object
 */
function get_sso_user_information( $ssoid = null ) {
  return call_api( $ssoid, 'GetUserData' );
} // end get_sso_user_information

/**
 *  Get WP user attached to sso user. If user does not exist yet,
 *  try co create a one.
 *
 *  @since  0.1.0
 *  @param  object $sso_user sso user object.
 *  @return mixed            WP_User object if user is attached, false otherwise.
 */
function get_wp_user( $sso_user = null ) {
  // bail if no sso user
  if ( empty( $sso_user ) ) {
    return false;
  }

  // try to get sso identifying unique id, bail if fails
  $sso_mapping_id = get_sso_user_mapping_id( $sso_user );
  if ( empty( $sso_mapping_id ) ) {
    return false;
  }

  // get wp users attached to sso user
  $users = get_users( [
    'meta_key'    => 'avoine_sso_mapping_id',
    'meta_value'  => $sso_mapping_id,
  ] );

  // user does not exist, create a one
  if ( empty( $users ) ) {
    return create_wp_user( $sso_user );
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
function create_wp_user( $sso_user = null ) {
  // bail if no sso user
  if ( empty( $sso_user ) ) {
    return false;
  }

  // try to get sso identifying unique id, bail if fails
  $sso_mapping_id = get_sso_user_mapping_id( $sso_user );
  if ( empty( $sso_mapping_id ) ) {
    return false;
  }

  $userdata = get_user_data_for_wp( $sso_user );
  if ( ! $userdata ) {
    return false;
  }

  do_action( 'avoine-sso-login\user\create\before', $sso_user, $sso_user_info );

  // make unique identifier for WP user in case sso local ids collide for some reason
  $user_wp_unique = wp_date( 'U' ) . $sso_user->local_id;

  // gather userdata for new user, developers can alter with filter
  $userdata['user_login'] = apply_filters( 'avoine-sso-login\user\create\user_login', $user_wp_unique, $sso_user, $sso_user_info );
  $userdata['user_pass'] = null;

  if ( ! isset( $userdata['user_email'] ) ) {
    $userdata['user_email'] = $user_wp_unique . '@' . wp_parse_url( get_site_url() )['host'];
  }

  $userdata = apply_filters( 'avoine-sso-login\user\create', $userdata, $sso_user, $sso_user_info );
  $userdata = apply_filters( 'avoine_sso_create_userdata', $userdata, $sso_user, $sso_user_info ); // legacy support

  // we want to have user pass as null always
  $userdata['user_pass'] = null;

  // try to create a new user
  $new_user_id = wp_insert_user( $userdata );

  // return false it user creation failed for some reason
  if ( is_wp_error( $new_user_id ) ) {
    return false;
  }

  // save sso details to newly created wp user
  update_user_meta( $new_user_id, 'avoine_sso_mapping_id', $sso_mapping_id );
  update_user_meta( $new_user_id, 'avoine_sso_idp', $sso_user->idp );
  update_user_meta( $new_user_id, 'avoine_sso_' . $sso_user->idp . '_ssoid', $sso_user->id );
  update_user_meta( $new_user_id, 'avoine_sso_' . $sso_user->idp . '_local_id', $sso_user->local_id );

  // allow developers to hook after user is created
  do_action( 'avoine-sso-login\user\create\after', $new_user_id, $sso_user, $sso_user_info );
  do_action( 'avoine_sso_after_create_user', $new_user_id, $sso_user, $sso_user_info ); // legacy support

  return get_userdata( $new_user_id );
} // end create_wp_user

function get_user_data_for_wp( $sso_user ) {
  $userdata = [];

  // get all user information from sso server
  $sso_user_info = get_sso_user_information( $sso_user->id );

  // bail if we cant get all information
  if ( empty( $sso_user_info ) ) {
    return false;
  }

  // try to get sso identifying unique id, bail if fails
  $sso_mapping_id = get_sso_user_mapping_id( $sso_user );
  if ( empty( $sso_mapping_id ) ) {
    return false;
  }

  if ( apply_filters( 'avoine-sso-login\user\create\user_email\use_original', false ) && isset( $sso_user_info->{ $sso_user->idp . '.email_address' } ) && ! empty( $sso_user_info->{ $sso_user->idp . '.email_address' } ) ) {
    $userdata['user_email'] = $sso_user_info->{ $sso_user->idp . '.email_address' };
  }

  if ( isset( $sso_user_info->{ $sso_user->idp . '.firstname' } ) && ! empty( $sso_user_info->{ $sso_user->idp . '.firstname' } ) ) {
    $userdata['first_name'] = $sso_user_info->{ $sso_user->idp . '.firstname' };
  }

  if ( isset( $sso_user_info->{ $sso_user->idp . '.lastname' } ) && ! empty( $sso_user_info->{ $sso_user->idp . '.lastname' } ) ) {
    $userdata['last_name'] = $sso_user_info->{ $sso_user->idp . '.lastname' };
  }

  if ( isset( $userdata['first_name'] ) ) {
    $userdata['user_nicename'] = $userdata['first_name'];
  }

  if ( isset( $userdata['last_name'] ) ) {
    $userdata['user_nicename'] .= ' ' . $userdata['last_name'][0] . '.';
  }

  $userdata = apply_filters( 'avoine-sso-login\user\data', $userdata, $sso_user, $sso_user_info );

  return $userdata;
} // end update_wp_user

function get_sso_user_mapping_id( $sso_user ) {
   // bail if no sso user
  if ( empty( $sso_user ) ) {
    return false;
  }

  // get all user information from sso server
  $sso_user_info = get_sso_user_information( $sso_user->id );

  // bail if we cant get all information
  if ( empty( $sso_user_info ) ) {
    return false;
  }

  $mapping_id = $sso_user->local_id;
  $mapping_id = apply_filters( 'avoine-sso-login\user\mapping_id', $mapping_id, $sso_user, $sso_user_info );
  $mapping_id = apply_filters( 'avoine_sso_user_mapping_id', $mapping_id, $sso_user, $sso_user_info ); // legacy support

  return $mapping_id;
} // end get_sso_user_mapping_id
