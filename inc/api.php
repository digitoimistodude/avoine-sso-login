<?php
/**
 * @Author: Timi Wahalahti
 * @Date:   2022-04-22 13:34:53
 * @Last Modified by:   Timi Wahalahti
 * @Last Modified time: 2022-04-25 14:49:05
 *
 * @package avoine-sso-login
 */

namespace Avoine_SSO_Login;

if ( ! defined( 'ABSPATH' ) ) {
  exit;
}

/**
 * Call the SSO service API.
 * @param  string $sso_user_id SSO user to check things againts.
 * @param  string $method      SSO API call method.
 * @return boolean/json        Boolean false if API call fails, json data if succesful.
 */
function call_api( $sso_user_id = null, $method = 'GetUser' ) {
  $api_key = get_api_key();
  $api_url = get_api_url();
  if ( empty( $api_key ) || empty( $api_url ) ) {
    return false;
  }

  $request = wp_remote_post( $api_url, [
    'body'  => wp_json_encode( [
      'id'      => wp_generate_uuid4(),
      'method'  => $method,
      'params'  => [
        $api_key,
        $sso_user_id,
      ],
      'jsonrpc' => '2.0',
    ] ),
  ] );

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
} // end call_api
