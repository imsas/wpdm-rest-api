<?php
if (!defined('WPINC')) {
    exit();
}

function wpdm_rest_api_basic_auth_handler( $user ) {
    global $wpdm_rest_basic_auth_error;

	$wpdm_rest_basic_auth_error = null;

    // Don't authenticate twice
    if ( ! empty( $user ) ) {
        return $user;
    }

    // Bearer Token Auth
    $api_key        = get_option('_wpdm_api_key');
	$bearer_token   = wpdm_rest_api_get_bearer_token();
    if( $api_key == $bearer_token && $bearer_token !== null ) {
        $user = wpdm_rest_api_admin_user();
        return $user;
    }

    // Check that we're trying to authenticate
    if ( !isset( $_SERVER['PHP_AUTH_USER'] ) ) {
        return $user;
    }

    $username = $_SERVER['PHP_AUTH_USER'];
    $password = $_SERVER['PHP_AUTH_PW'];

    /**
     * In multi-site, wp_authenticate_spam_check filter is run on authentication. This filter calls
     * get_currentuserinfo which in turn calls the determine_current_user filter. This leads to infinite
     * recursion and a stack overflow unless the current function is removed from the determine_current_user
     * filter during authentication.
     */
    remove_filter( 'determine_current_user', 'wpdm_rest_api_basic_auth_handler', 15 );

    $user = wp_authenticate( $username, $password );

    add_filter( 'determine_current_user', 'wpdm_rest_api_basic_auth_handler', 15 );

    if ( is_wp_error( $user ) ) {
        $wpdm_rest_basic_auth_error = $user;
        return null;
    }

    $wpdm_rest_basic_auth_error = true;

    return $user->ID;
}
add_filter( 'determine_current_user', 'wpdm_rest_api_basic_auth_handler', 15 );

function wpdm_rest_api_basic_auth_error( $error ) {
    // Passthrough other errors
    if ( ! empty( $error ) ) {
        return $error;
    }

    global $wpdm_rest_basic_auth_error;

    return $wpdm_rest_basic_auth_error;
}
add_filter( 'rest_authentication_errors', 'wpdm_rest_api_basic_auth_error' );

function wpdm_rest_api_get_authorization_header(){
    $headers = null;
    if (isset($_SERVER['Authorization'])) {
        $headers = trim($_SERVER["Authorization"]);
    }
    else if (isset($_SERVER['HTTP_AUTHORIZATION'])) { //Nginx or fast CGI
        $headers = trim($_SERVER["HTTP_AUTHORIZATION"]);
    } elseif (function_exists('apache_request_headers')) {
        $requestHeaders = apache_request_headers();
        // Server-side fix for bug in old Android versions (a nice side-effect of this fix means we don't care about capitalization for Authorization)
        $requestHeaders = array_combine(array_map('ucwords', array_keys($requestHeaders)), array_values($requestHeaders));
        //print_r($requestHeaders);
        if (isset($requestHeaders['Authorization'])) {
            $headers = trim($requestHeaders['Authorization']);
        }
    }
    return $headers;
}

function wpdm_rest_api_get_bearer_token() {
    $headers = wpdm_rest_api_get_authorization_header();
    // HEADER: Get the access token from the header
    if ( ! empty( $headers ) ) {
        if (preg_match('/Bearer\s(\S+)/', $headers, $matches)) {
            return $matches[1];
        }
    }
	if(wpdm_query_var('auth_token', 'txt') !== '') {
		return wpdm_query_var('auth_token', 'txt');
	}
    return null;
}

function wpdm_rest_api_admin_user(){
    $rest_admin = get_option('wpdm_rest_admin');

    if( $rest_admin === false || $rest_admin <= 0 ){

        /*$admin_email = get_option('admin_email');
        $admin = get_user_by('email', $admin_email );
        $rest_admin = $admin->ID;
        update_option('wpdm_rest_admin', $rest_admin );
        return $rest_admin;*/

        $admin_users = get_users( array( 'role' => 'administrator' ,'number' => 1 ) );
        foreach ( $admin_users as $admin_user ) {
            $rest_admin = $admin_user->ID;
            update_option('wpdm_rest_admin', $rest_admin );
            return $rest_admin;
        }
    }

    return $rest_admin;
}