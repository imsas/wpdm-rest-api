<?php
/**
 *
 */

class WPDM_REST_Siteinfo_Controller {

    protected $namespace;
    protected $rest_base;

    public function __construct() {
        // REST Route: http://localhost/wpdm/wp-json/wpdm/v1/categories
        $this->namespace    = '/wpdm/v1';
        $this->rest_base    = 'siteinfo';
    }

    // Register our routes.
    public function register_routes() {
        register_rest_route( $this->namespace, '/' . $this->rest_base, array(
            array(
                'methods'               => 'GET',
                'callback'              => array( $this, 'siteinfo' ),
                'permission_callback'   => array( $this, 'get_permissions_check' ),
            ),
            'schema' => null,
        ) );

    }

	function siteinfo()
	{
		$siteinfo = [ 'name' => get_bloginfo('name'), 'desc' => get_bloginfo('description'),  'version' => get_bloginfo('version'), 'url' => get_bloginfo('url')];
		return rest_ensure_response($siteinfo);
	}


    public function get_permissions_check( $request ) {

        if ( ! current_user_can( 'edit_posts' ) ) {
            return new WP_Error( 'rest_forbidden', esc_html__( 'You cannot view categories.' ), array( 'status' => $this->authorization_status_code() ) );
        }
        return true;
    }


    // Sets up the proper HTTP status code for authorization.
    public function authorization_status_code() {

        $status = 401;

        if ( is_user_logged_in() ) {
            $status = 403;
        }

        return $status;
    }
}