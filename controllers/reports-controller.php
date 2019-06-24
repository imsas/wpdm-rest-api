<?php
/**
 * Created by PhpStorm.
 * User: shahriar
 * Date: 2/19/19
 * Time: 12:02 PM
 */

//TODO

class WPDM_REST_Reports_Controller {

    protected $namespace;
    protected $rest_base;

    public function __construct() {
        $this->namespace    = '/wpdm/v1';
        $this->rest_base    = 'reports';
    }

    public function register_routes() {
        register_rest_route( $this->namespace, '/' . $this->rest_base . '/sales', array(
            array(
                'methods'               => 'GET',
                'callback'              => array( $this, 'get_sales' ),
                'permission_callback'   => array( $this, 'get_items_permissions_check' ),
            ),
            'schema' => null,
        ) );
    }

    public function get_items_permissions_check( $request ) {
        return true;
    }

    public function get_items( $request ) {

        $rest_info = array();

        $rest_info['hi'] = 'Testing';

        return rest_ensure_response( $rest_info );
    }

}