<?php
/**
 * Created by PhpStorm.
 * User: shahriar
 * Date: 2/19/19
 * Time: 12:02 PM
 */

class WPDM_REST_License_Controller {

    protected $namespace;
    protected $rest_base;

    public function __construct() {
        // REST Route: http://localhost/wpdm/wp-json/wpdm/v1/licenses
        $this->namespace    = '/wpdm/v1';
        $this->rest_base    = 'licenses';
    }

    // Register our routes.
    public function register_routes() {
        register_rest_route( $this->namespace, '/' . $this->rest_base, array(
            array(
                'methods'               => 'GET',
                'callback'              => array( $this, 'get_items' ),
                'permission_callback'   => array( $this, 'get_items_permissions_check' ),
            ),
            array(
                'methods'               => 'POST',
                'callback'              => array( $this, 'create_item' ),
                'permission_callback'   => array( $this, 'create_item_permissions_check' ),
                'args'                  => array(),
            ),
            'schema' => null,
        ) );

        register_rest_route( $this->namespace, '/' . $this->rest_base . '/(?P<id>[\d]+)', array(
            array(
                'methods'               => 'GET',
                'callback'              => array( $this, 'get_item' ),
                'permission_callback'   => array( $this, 'get_item_permissions_check' ),
            ),
            array(
                'methods'               => 'PUT',
                'callback'              => array( $this, 'update_item' ),
                'permission_callback'   => array( $this, 'update_item_permissions_check' ),
            ),
            array(
                'methods'               => 'DELETE',
                'callback'              => array( $this, 'delete_item' ),
                'permission_callback'   => array( $this, 'delete_item_permissions_check' ),
            ),
            'schema' => null,
        ) );
    }

    public function create_item_permissions_check( $request ) {

        if ( ! current_user_can( 'manage_options' ) ) {
            return new WP_Error( 'rest_forbidden', esc_html__( 'You cannot create new license.' ), array( 'status' => $this->authorization_status_code() ) );
        }
        return true;
    }

    public function create_item( $request ) {

        if ( ! isset ( $request['licenseno'] ) || trim( $request['licenseno'] ) == "" ) {
            return new WP_Error( 'rest_empty_licenseno', __( 'License no can not be empty.' ), array( 'status' => 400 ) );
        }
        if ( ! isset ( $request['oid'] ) || trim( $request['oid'] ) == "" ) {
            return new WP_Error( 'rest_empty_oid', __( 'Order id can not be empty.' ), array( 'status' => 400 ) );
        }

        global $wpdb;

        $res = $wpdb->insert("{$wpdb->prefix}ahm_licenses", array(
                'domain'            => isset( $request['domain']  ) ? serialize( $request['domain'] ) : '',
                'licenseno'         => trim( $request['licenseno'] ),
                'status'            => isset( $request['status']  ) ? $request['status'] : 0,
                'oid'               => trim( $request['oid'] ),
                'pid'               => isset( $request['pid']  ) ? $request['pid'] : 0,
                'activation_date'   => isset( $request['activation_date']  ) ? strtotime( $request['activation_date'] ) : 0,
                'expire_date'       => isset( $request['expire_date']  ) ? strtotime( $request['expire_date'] ) : 0,
                'domain_limit'      => isset( $request['domain_limit']  ) ? (int) $request['domain_limit'] : 0
            )
        );

        if ( ! $res ) {
            return array('message' => "DB Error");
        }

        $lastid = $wpdb->insert_id;
        $response = $this->get_item( array('id' => $lastid ) );

        return $response;
    }

    public function get_items_permissions_check( $request ) {

        if ( ! current_user_can( 'manage_options' ) ) {
            return new WP_Error( 'rest_forbidden', esc_html__( 'You cannot view licenses.' ), array( 'status' => $this->authorization_status_code() ) );
        }
        return true;
    }

    public function get_items( $request ) {

        global $wpdb;
        $items_per_page = isset( $request['items_per_page'] ) ? $request['items_per_page'] : 10;
        $start          = isset( $request['page'] ) ? ( $request['page'] - 1 ) * $items_per_page : 0;

        $product_query     = isset( $request['pid'] ) ? "pid = ".(int) $request['pid'] : '1=1';
        $license_query         = isset( $request['licenseno'] ) ? "and licenseno = '".$request['licenseno']."'" : '';
        $order_query    = isset( $request['oid'] ) ? "and oid = '".$request['oid']."'" : '';

        $sql = "SELECT *
                FROM {$wpdb->prefix}ahm_licenses
                WHERE {$product_query} {$license_query} {$order_query}
                ORDER BY `ID` DESC
                LIMIT $start, $items_per_page";
        $res = $wpdb->get_results( $sql, ARRAY_A );

        $licenses       = array();
        foreach( $res as $license ){
            if( isset( $license['expire_date'] ) ) $license['expire_date'] = date( "F j, Y", $license['expire_date'] );
            if( isset( $license['activation_date'] ) ) $license['activation_date'] = date( "F j, Y", $license['activation_date'] );
            if( isset( $license['domain'] ) ) $license['domain'] = maybe_unserialize( $license['domain']);

            $licenses[] = $this->prepare_response_for_collection( $license );
        }
        return rest_ensure_response( $licenses );
    }

    public function get_item_permissions_check( $request ) {
        if ( ! current_user_can( 'manage_options' ) ) {
            return new WP_Error( 'rest_forbidden', esc_html__( 'You cannot view license.' ), array( 'status' => $this->authorization_status_code() ) );
        }
        return true;
    }

    public function get_item( $request ) {
        global $wpdb;
        $license_id      = $request['id'];
        $license_query   = isset( $request['id'] ) ? "id = '".$license_id."'" : '';

        $sql = "SELECT *
                FROM {$wpdb->prefix}ahm_licenses
                WHERE {$license_query}
                ORDER BY `ID` DESC";
        $res = $wpdb->get_results( $sql, ARRAY_A );

        foreach( $res as $license ){
            if( isset( $license['expire_date'] ) ) $license['expire_date'] = date( "F j, Y", $license['expire_date'] );
            if( isset( $license['activation_date'] ) ) $license['activation_date'] = date( "F j, Y", $license['activation_date'] );
            if( isset( $license['domain'] ) ) $license['domain'] = maybe_unserialize( $license['domain']);
            return rest_ensure_response( $license );
        }
    }

    public function update_item_permissions_check( $request ) {

        if ( ! current_user_can( 'manage_options' ) ) {
            return new WP_Error( 'rest_forbidden', esc_html__( 'You cannot edit license.' ), array( 'status' => $this->authorization_status_code() ) );
        }
        return true;
    }

    public function update_item( $request ) {
        global $wpdb;
        $license_id  = $request['id'];
        $license     = array();
        if( isset( $request['domain'] ) )           $license['domain']          = serialize( $request['domain'] );
        if( isset( $request['licenseno'] ) )        $license['licenseno']       = trim( $request['licenseno'] );
        if( isset( $request['status'] ) )           $license['status']          = $request['status'];
        if( isset( $request['oid'] ) )              $license['oid']             = trim( $request['oid'] );
        if( isset( $request['pid'] ) )              $license['pid']             = (int) $request['pid'];
        if( isset( $request['activation_date'] ) )  $license['activation_date'] = strtotime( $request['activation_date'] );
        if( isset( $request['expire_date'] ) )      $license['expire_date']     = strtotime( $request['expire_date'] );
        if( isset( $request['expire_period'] ) )    $license['expire_period']   = $request['expire_period'];
        if( isset( $request['domain_limit'] ) )     $license['domain_limit']    = (int) $request['domain_limit'];

        $res = $wpdb->update( "{$wpdb->prefix}ahm_licenses", $license, array('id' => $license_id ) );
        return $this->get_item( array('id' => $license_id ) );
    }

    public function delete_item_permissions_check( $request ) {

        if ( ! current_user_can( 'manage_options' ) ) {
            return new WP_Error( 'rest_forbidden', esc_html__( 'You cannot delete license.' ), array( 'status' => $this->authorization_status_code() ) );
        }
        return true;
    }

    public function delete_item( $request ) {

        global $wpdb;
        $license_id     = $request['id'];
        $response       = $this->get_item( array('id' => $license_id ) );
        $table          = "{$wpdb->prefix}ahm_licenses";
        $wpdb->delete( $table, array( 'id' => $license_id ) );

        return rest_ensure_response( $response );
    }

    public function prepare_item_for_response( $license_info, $request ) {

        $license_info = apply_filters( 'wpdm_rest_api_coupon_response', $license_info );

        return rest_ensure_response( $license_info );
    }

    public function prepare_response_for_collection( $response ) {
        if ( ! ( $response instanceof WP_REST_Response ) ) {
            return $response;
        }

        $data = (array) $response->get_data();
        $server = rest_get_server();

        if ( method_exists( $server, 'get_compact_response_links' ) ) {
            $links = call_user_func( array( $server, 'get_compact_response_links' ), $response );
        } else {
            $links = call_user_func( array( $server, 'get_response_links' ), $response );
        }

        if ( ! empty( $links ) ) {
            $data['_links'] = $links;
        }

        return $data;
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