<?php
/**
 * Created by PhpStorm.
 * User: shahriar
 * Date: 2/19/19
 * Time: 12:02 PM
 */

class WPDM_REST_Orders_Controller {

    protected $namespace;
    protected $rest_base;

    public function __construct() {
        // REST Route: http://localhost/wpdm/wp-json/wpdm/v1/orders
        $this->namespace    = '/wpdm/v1';
        $this->rest_base    = 'orders';
    }

    // Register our routes.
    public function register_routes() {

	    register_rest_route( $this->namespace, '/' . $this->rest_base . '/daily_total', array(
		    array(
			    'methods'               => 'GET',
			    'callback'              => array( $this, 'daily_total' ),
			    'permission_callback'   => array( $this, 'get_items_permissions_check' ),
		    ),
		    'schema' => null,
	    ) );

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
            return new WP_Error( 'rest_forbidden', esc_html__( 'You cannot create new record.' ), array( 'status' => $this->authorization_status_code() ) );
        }
        return true;
    }

    public function create_item( $request ) {
        global $wpdb;
        $order_id = uniqid();
        $res = $wpdb->insert("{$wpdb->prefix}ahm_orders", array(
                'order_id'          => $order_id,
                'trans_id'          => $request['trans_id'],
                'title'             => $request['title'],
                'date'              => isset( $request['date']  ) ? strtotime( $request['date'] ) : time(),
                'items'             => isset( $request['items']  ) ? serialize( $request['items'] ) : serialize( array()),
                'cart_data'         => isset( $request['cart_data']  ) ? serialize( $request['cart_data'] ) : serialize( array()),
                'total'             => isset( $request['total']  ) ? $request['total'] : 0,
                'order_status'      => isset( $request['order_status']  ) ? $request['order_status'] : "Processing",
                'payment_status'    => isset( $request['payment_status']  ) ? $request['payment_status'] : "Processing",
                'uid'               => isset( $request['uid']  ) ? $request['uid'] : 0,
                'order_notes'       => isset( $request['order_notes']  ) ? serialize( $request['order_notes'] ) : "",
                'payment_method'    => isset( $request['payment_method']  ) ? $request['payment_method'] : "TestPay",
                'tax'               => isset( $request['tax']  ) ? $request['tax'] : 0,
                'cart_discount'     => isset( $request['cart_discount']  ) ? $request['cart_discount'] : 0,
                'discount'          => isset( $request['discount']  ) ? $request['discount'] : 0,
                'coupon_discount'   => isset( $request['coupon_discount']  ) ? $request['coupon_discount'] : 0,
                'currency'          => isset( $request['currency']  ) ? serialize( $request['currency'] ) : "",
                'download'          => isset( $request['download']  ) ? $request['download'] : 0,
                'IP'                => isset( $request['IP']  ) ? $request['IP'] : "",
                'ipn'               => isset( $request['ipn']  ) ? $request['ipn'] : "",
                'unit_prices'       => isset( $request['unit_prices']  ) ? serialize( $request['unit_prices'] ) : "",
                'billing_info'      => isset( $request['billing_info']  ) ? serialize( $request['billing_info'] ) : "",
                'expire_date'       => isset( $request['expire_date']  ) ? strtotime( $request['expire_date'] ) : time() + ( get_wpdmpp_option('order_validity_period', 0 ) * 86400 ),
                'auto_renew'        => isset( $request['auto_renew']  ) ? $request['auto_renew'] : 0,
                'coupon_code'       => isset( $request['coupon_code']  ) ? $request['coupon_code'] : "",
                'subtotal'          => isset( $request['subtotal']  ) ? $request['subtotal'] : 0
            )
        );

        if ( ! $res ) {
            return array('message' => "DB Error");
        }

        $response = $this->get_item( array('id' => $order_id ) );

        return $response;
    }

    public function get_items_permissions_check( $request ) {

        if ( ! current_user_can( 'manage_options' ) ) {
            return new WP_Error( 'rest_forbidden', esc_html__( 'You cannot view orders.' ), array( 'status' => $this->authorization_status_code() ) );
        }
        return true;
    }

    public function get_items( $request ) {

        global $wpdb;
        $items_per_page = isset( $request['items_per_page'] ) ? $request['items_per_page'] : 10;
        $start          = isset( $request['page'] ) ? ( $request['page'] - 1 ) * $items_per_page : 0;

        $start_date     = isset( $request['start_date'] ) ? strtotime( $request['start_date'] ) : strtotime("2000-12-30 12:00 am");
        $end_date       = isset( $request['end_date'] ) ? strtotime( $request['end_date'] ) : time();

        $time_range     = '';
        $time_range     .= $start_date != '' ? "date >={$start_date}" : '';
        $time_range     .= $end_date != '' ? " and date <={$end_date}" : '';

        $order_status       = isset( $request['order_status'] ) ? "and order_status = '".$request['order_status']."'" : '';
        $payment_status     = isset( $request['payment_status'] ) ? "and payment_status = '".$request['payment_status']."'" : '';
        $payment_method     = isset( $request['payment_method'] ) ? "and payment_method = '".$request['payment_method']."'" : '';
        $user_query         = isset( $request['uid'] ) ? "and uid = ".(int) $request['uid'] : '';
        $download_status    = isset( $request['download'] ) ? "and download = ".(int) $request['download'] : '';

        $sql = "SELECT *
                FROM {$wpdb->prefix}ahm_orders
                WHERE {$time_range} {$order_status} {$payment_status} {$user_query} {$payment_method} {$download_status}
                ORDER BY `date` DESC
                LIMIT $start, $items_per_page";
        $res = $wpdb->get_results( $sql, ARRAY_A );

        $orders       = array();
        foreach( $res as $order ){
            if( isset( $order['cart_data'] ) ) $order['cart_data'] = maybe_unserialize( $order['cart_data'] );
            if( isset( $order['currency'] ) ) $order['currency'] = maybe_unserialize( $order['currency'] );
            if( isset( $order['billing_info'] ) ) $order['billing_info'] = maybe_unserialize( $order['billing_info'] );
            if( isset( $order['order_notes'] ) ) $order['order_notes'] = maybe_unserialize( $order['order_notes'] );
            if( isset( $order['unit_prices'] ) ) $order['unit_prices'] = maybe_unserialize( $order['unit_prices'] );
            if( isset( $order['items'] ) ) $order['items'] = maybe_unserialize( $order['items'] );
            if( isset( $order['date'] ) ) $order['date'] = date( "F j, Y", $order['date'] );
            if( isset( $order['expire_date'] ) ) $order['expire_date'] = date( "F j, Y", $order['expire_date'] );
            $orders[] = $this->prepare_response_for_collection( $order );
        }
        return rest_ensure_response( $orders );
    }

    public function get_item_permissions_check( $request ) {
        if ( ! current_user_can( 'manage_options' ) ) {
            return new WP_Error( 'rest_forbidden', esc_html__( 'You cannot view order.' ), array( 'status' => $this->authorization_status_code() ) );
        }
        return true;
    }

    public function get_item( $request ) {
        global $wpdb;
        $order_id        = $request['id'];
        $order_query     = isset( $request['id'] ) ? "order_id = '".$order_id."'" : '';

        $sql = "SELECT *
                FROM {$wpdb->prefix}ahm_orders
                WHERE {$order_query}
                ORDER BY `date` DESC";
        $res = $wpdb->get_results( $sql, ARRAY_A );

        foreach( $res as $order ){
            if( isset( $order['cart_data'] ) ) $order['cart_data'] = maybe_unserialize( $order['cart_data'] );
            if( isset( $order['currency'] ) ) $order['currency'] = maybe_unserialize( $order['currency'] );
            if( isset( $order['billing_info'] ) ) $order['billing_info'] = maybe_unserialize( $order['billing_info'] );
            if( isset( $order['items'] ) ) $order['items'] = maybe_unserialize( $order['items'] );
            if( isset( $order['order_notes'] ) ) $order['order_notes'] = maybe_unserialize( $order['order_notes'] ); //return $order['order_notes']['messages']['1']['note'];
            if( isset( $order['date'] ) ) $order['date'] = date( "F j, Y", $order['date'] );
            if( isset( $order['expire_date'] ) ) $order['expire_date'] = date( "F j, Y", $order['expire_date'] );

            return rest_ensure_response( $order );
        }
    }

    public function update_item_permissions_check( $request ) {

        if ( ! current_user_can( 'manage_options' ) ) {
            return new WP_Error( 'rest_forbidden', esc_html__( 'You cannot edit order.' ), array( 'status' => $this->authorization_status_code() ) );
        }
        return true;
    }

    public function update_item( $request ) {
        global $wpdb;
        $order_id        = $request['id'];

        $order = array();

        if( isset( $request['order_status'] ) )     $order['order_status']      = $request['order_status'];
        if( isset( $request['payment_status'] ) )   $order['payment_status']    = $request['payment_status'];
        if( isset( $request['payment_method'] ) )   $order['payment_method']    = $request['payment_method'];
        if( isset( $request['download'] ) )         $order['download']          = $request['download'];
        if( isset( $request['uid'] ) )              $order['uid']               = $request['uid'];
        if( isset( $request['auto_renew'] ) )       $order['auto_renew']        = $request['auto_renew'];
        if( isset( $request['coupon_code'] ) )      $order['coupon_code']       = $request['coupon_code'];

        if( isset( $request['date'] ) )             $order['date']              = strtotime( $request['date'] );
        if( isset( $request['expire_date'] ) )      $order['expire_date']       = strtotime( $request['expire_date'] );

        if( isset( $request['order_notes'] ) )      $order['order_notes']       = serialize( $request['order_notes'] );
        if( isset( $request['billing_info'] ) )     $order['billing_info']      = serialize( $request['billing_info'] );

        $res = $wpdb->update( "{$wpdb->prefix}ahm_orders", $order, array('order_id' => $order_id ) );

        return $this->get_item( array('id' => $order_id ) );
    }

    public function delete_item_permissions_check( $request ) {

        if ( ! current_user_can( 'manage_options' ) ) {
            return new WP_Error( 'rest_forbidden', esc_html__( 'You cannot delete order.' ), array( 'status' => $this->authorization_status_code() ) );
        }
        return true;
    }

    public function delete_item( $request ) {

        global $wpdb;
        $order_id   = $request['id'];
        $response   = $this->get_item( array('id' => $order_id ) );
        $table      = "{$wpdb->prefix}ahm_orders";
        $wpdb->delete( $table, array( 'order_id' => $order_id ) );

        return rest_ensure_response( $response );
    }

	function daily_total() {
		$date = wpdm_query_var('date');
		$date_from = wpdm_query_var('date_from');
		$date_to = wpdm_query_var('date_to');
		if($date) {
			$date_from = $date_to = $date;
		}
		if(!$date_from || !$date_to) {
			$date_from = $date_to = date("Y-m-d");
		}
		$result = wpdmpp_daily_sales('', '', $date_from, $date_to);
		return $result;
	}

    public function prepare_item_for_response( $order_info, $request ) {

        $order_info = apply_filters( 'wpdm_rest_api_term_data_response', $order_info );

        return rest_ensure_response( $order_info );
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