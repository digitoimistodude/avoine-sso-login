# Avoine SSO  Login

Plugin integrates Avoine SSO to WordPress and creates an shadow user in WordPress for users that log in via SSO.

## Setup

Plugin uses few environment variables for configuration.

`AVOINE_SSO_SERVICE_ID` is the ID for SSO
`AVOINE_SSO_KEY` is the communications key for SSO

## Auth flow

1. User is sent to log in url get with `avoine_sso_get_login_url` function
2. If user exists, shadow WP user details are updated
3. If user does not exist, new shadow WP user is created
2. Do logout calling domain.fi/sso-logout

## Functions

`avoine_sso_get_login_url` returns login url for SSO service. Accepts one parameter for redirect url, to which user will be redirected after succesfull login.

`avoine_sso_get_logout_url` returns logout url for SSO service.

`avoine_is_sso_user` returns boolean based on if user loggedin from SSO. Accepts one parameter for WP user ID, defaults to current user if not given.

`avoine_is_sso_user_active` returns boolean based on if user is still active based on SSO data. Accepts one parameter for WP user ID, defaults to current user if not given. Caches the status in object cache (redis, memcached or similar).

## Hooks

### Setup
`avoine-sso-login\service\id` defaults to AVOINE_SSO_SERVICE_ID environment variable
`avoine-sso-login\api\key` defaults to AVOINE_SSO_KEY environment variable
`avoine-sso-login\service\domain` defaults to tunnistus.avoine.fi
`avoine-sso-login\login\return_url` defaults to home (`home_url`) and overrides the value given for login url function
`avoine-sso-login\logout\url` defaults to https://{$sso_service_domain}/sso-logout/
`avoine-sso-login\failed\redirect_url` defaults to WP login url

### Auth flow
`avoine-sso-login\logout\message` message shown in case SSO logout becomes visible for the user.
`avoine-sso-login\login\user-is-active` when SSO user activity is checked during the lofgin, defaults to true. Gives activity status, SSO user and SSO user full data as parameters.

### User creation
`avoine-sso-login\user\create\user_login` allows filtering the user login for shadow WP user. Defaults to combination of unixtime and SSO user id. Gives default login, SSO user and SSO user full data as parameters.
`avoine-sso-login\user\create` allows filtering all the data given for wp_insert_user function when creating shadow WP user. Gives SSO user and SSO user full data as parameters.

### User data
`avoine-sso-login\user\create\user_email\use_original` boolean setting if real user email from SSO data should be used also for WP shadow user. Defaults to false.
`avoine-sso-login\user\data` array given to wp_insert_user and wp_update_user functions.
`avoine-sso-login\user\mapping_id` allows chaning the unique identifier for SSO user againts which WP shadow user will be checked. Defults to $sso_user->idp. Gives the mappinf id, SSO user and SSO user full data as parameters.

### User activity
`avoine-sso-login\user\is-active` when avoine_is_sso_user_active function is called and activity status is not cached. Gives activity status, WP_User object, SSO user and SSO user full data as parameters.
`avoine-sso-login\user\is-active\expiration` cache lifetime for user activity check. Stored in object cache. Defaults to two days or value of WP native filter auth_cookie_expiration.

## Actions

### Auth flow
`avoine-sso-login\succes\auth\before` when SSO user has been rediceted back and their activity validated but WP user is not logged in. Get's WP_User object and SSO user data given by the redirect.
`avoine-sso-login\succes\auth\after` when SSO user has been rediceted back and their activity validated. Get's WP_User object and SSO user data given by the redirect.
`avoine-sso-login\logout\after` after SSO service has called logout url and WP user logout has been done.
`avoine-sso-login\failed` when SSO user login fails for some reason after caputing valid redirect from SSO login.
`avoine-sso-login\login\user-is-active\after` after SSO user activity check has been done durign the login. Gives SSO user and SSO user full data as parameters.

### User creation
`avoine-sso-login\user\create\before` before new WP shadow user is created after succesfull SSO login. Gives SSO user and SSO user full data as parameters.
`avoine-sso-login\user\create\after` after new WP shadow user is created. Gives new WP user ID, SSO user and SSO user full data as parameters.

### User activity
`avoine-sso-login\user\is-active\after` when avoine_is_sso_user_active function is called and activity status is not cached. Gives WP_User object and activity status as parameters.

### User action preventions
`avoine-sso-login\user\prevented-wp-login` when SSO user normal WP login is prevented
`avoine-sso-login\user\prevented-password-reset` when SSO user WP password reset is prevented
`avoine-sso-login\user\prevented-password-reset\email` when SSO uset WP password reset email is prevented
