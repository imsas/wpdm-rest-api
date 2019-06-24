<?php
/**
 * Created by PhpStorm.
 * User: shahriar
 * Date: 2/19/19
 * Time: 12:02 PM
 */

class WPDM_REST_Coupons_Controller {

    protected $namespace;
    protected $rest_base;

    public function __construct() {
        // REST Route: http://localhost/wpdm/wp-json/wpdm/v1/coupons
        $this->namespace    = '/wpdm/v1';
        $this->rest_base    = 'coupons';
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

        register_rest_route( $this->namespace, '/' . $this->rest_base . '/(?P<id>[\d\w]+)', array(
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
            return new WP_Error( 'rest_forbidden', esc_html__( 'You cannot create new coupon.' ), array( 'status' => $this->authorization_status_code() ) );
        }
        return true;
    }

    public function create_item( $request ) {

        if ( ! isset ( $request['code'] ) || trim( $request['code'] ) == "" ) {
            return new WP_Error( 'rest_empty_coupon', __( 'Coupon code can not be empty.' ), array( 'status' => 400 ) );
        }

        global $wpdb;

        $res = $wpdb->insert("{$wpdb->prefix}ahm_coupons", array(
                'code'              => trim( $request['code'] ),
                'description'       => $request['description'],
                'type'              => isset( $request['type']  ) ? $request['type'] : 'percent',
                'discount'          => isset( $request['discount']  ) ? $request['discount'] : 0,
                'min_order_amount'  => isset( $request['min_order_amount']  ) ? $request['min_order_amount'] : 0,
                'max_order_amount'  => isset( $request['max_order_amount']  ) ? $request['max_order_amount'] : 0,
                'allowed_emails'    => isset( $request['allowed_emails']  ) ? $request['allowed_emails'] : 0,
                'product'           => isset( $request['product']  ) ? $request['product'] : 0,
                'expire_date'       => isset( $request['expire_date']  ) ? strtotime( $request['expire_date'] ) : 0,
                'usage_limit'       => isset( $request['usage_limit']  ) ? $request['usage_limit'] : 0,
                'used'              => isset( $request['used']  ) ? $request['used'] : ""
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
            return new WP_Error( 'rest_forbidden', esc_html__( 'You cannot view coupons.' ), array( 'status' => $this->authorization_status_code() ) );
        }
        return true;
    }

    public function get_items( $request ) {

        global $wpdb;
        $items_per_page = isset( $request['items_per_page'] ) ? $request['items_per_page'] : 10;
        $start          = isset( $request['page'] ) ? ( $request['page'] - 1 ) * $items_per_page : 0;

        $product_query     = isset( $request['product'] ) ? "product = ".(int) $request['product'] : '1=1';
        $code_query         = isset( $request['code'] ) ? "and code = '".$request['code']."'" : '';
        $type_query    = isset( $request['type'] ) ? "and type = '".$request['type']."'" : '';

        $sql = "SELECT *
                FROM {$wpdb->prefix}ahm_coupons
                WHERE {$product_query} {$code_query} {$type_query}
                ORDER BY `ID` DESC
                LIMIT $start, $items_per_page";
        $res = $wpdb->get_results( $sql, ARRAY_A );

        $coupons       = array();
        foreach( $res as $coupon ){
            if( isset( $coupon['expire_date'] ) ) $coupon['expire_date'] = date( "F j, Y", $coupon['expire_date'] );
            $coupons[] = $this->prepare_response_for_collection( $coupon );
        }
        return rest_ensure_response( $coupons );
    }

    public function get_item_permissions_check( $request ) {
        if ( ! current_user_can( 'manage_options' ) ) {
            return new WP_Error( 'rest_forbidden', esc_html__( 'You cannot view coupon.' ), array( 'status' => $this->authorization_status_code() ) );
        }
        return true;
    }

    public function get_item( $request ) {
        global $wpdb;
        $coupon_id      = $request['id'];
        $coupon_query   = isset( $request['id'] ) ? "ID = '".$coupon_id."'" : '';

        $sql = "SELECT *
                FROM {$wpdb->prefix}ahm_coupons
                WHERE {$coupon_query}
                ORDER BY `ID` DESC";
        $res = $wpdb->get_results( $sql, ARRAY_A );

        foreach( $res as $coupon ){
            if( isset( $coupon['expire_date'] ) ) $coupon['expire_date'] = date( "F j, Y", $coupon['expire_date'] );
            return rest_ensure_response( $coupon );
        }
    }

    public function update_item_permissions_check( $request ) {

        if ( ! current_user_can( 'manage_options' ) ) {
            return new WP_Error( 'rest_forbidden', esc_html__( 'You cannot edit coupon.' ), array( 'status' => $this->authorization_status_code() ) );
        }
        return true;
    }

    public function update_item( $request ) {
        global $wpdb;
        $coupon_id  = $request['id'];
        $coupon     = array();
        if( isset( $request['code'] ) )                 $coupon['code']             = $request['code'];
        if( isset( $request['description'] ) )          $coupon['description']      = $request['description'];
        if( isset( $request['type'] ) )                 $coupon['type']             = $request['type'];
        if( isset( $request['discount'] ) )             $coupon['discount']         = $request['discount'];
        if( isset( $request['min_order_amount'] ) )     $coupon['min_order_amount'] = $request['min_order_amount'];
        if( isset( $request['max_order_amount'] ) )     $coupon['max_order_amount'] = $request['max_order_amount'];
        if( isset( $request['product'] ) )              $coupon['product']          = $request['product'];
        if( isset( $request['allowed_emails'] ) )       $coupon['allowed_emails']   = $request['allowed_emails'];
        if( isset( $request['expire_date'] ) )          $coupon['expire_date']      = strtotime( $request['expire_date'] );
        if( isset( $request['usage_limit'] ) )          $coupon['usage_limit']      = $request['usage_limit'];
        if( isset( $request['used'] ) )                 $coupon['used']             = $request['used'];

        $res = $wpdb->update( "{$wpdb->prefix}ahm_coupons", $coupon, array('ID' => $coupon_id ) );
        return $this->get_item( array('id' => $coupon_id ) );
    }

    public function delete_item_permissions_check( $request ) {

        if ( ! current_user_can( 'manage_options' ) ) {
            return new WP_Error( 'rest_forbidden', esc_html__( 'You cannot delete coupon.' ), array( 'status' => $this->authorization_status_code() ) );
        }
        return true;
    }

    public function delete_item( $request ) {

        global $wpdb;
        $coupon_id  = $request['id'];
        $response   = $this->get_item( array('id' => $coupon_id ) );
        $table      = "{$wpdb->prefix}ahm_coupons";
        $wpdb->delete( $table, array( 'ID' => $coupon_id ) );

        return rest_ensure_response( $response );
    }

    public function prepare_item_for_response( $coupon_info, $request ) {

        $coupon_info = apply_filters( 'wpdm_rest_api_coupon_response', $coupon_info );

        return rest_ensure_response( $coupon_info );
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