<?php
/**
 * Created by PhpStorm.
 * User: shahriar
 * Date: 2/19/19
 * Time: 12:02 PM
 */

class WPDM_REST_Customers_Controller {

    protected $namespace;
    protected $rest_base;

    public function __construct() {
        // REST Route: http://localhost/wpdm/wp-json/wpdm/v1/orders
        $this->namespace    = '/wpdm/v1';
        $this->rest_base    = 'customers';
    }

    // Register our routes.
    public function register_routes() {
        register_rest_route( $this->namespace, '/' . $this->rest_base, array(
            array(
                'methods'               => 'GET',
                'callback'              => array( $this, 'get_items' ),
                'permission_callback'   => array( $this, 'get_items_permissions_check' ),
            ),

            'schema' => null,
        ) );

        register_rest_route( $this->namespace, '/' . $this->rest_base . '/(?P<id>[\d\w]+)', array(
            array(
                'methods'               => 'GET',
                'callback'              => array( $this, 'get_item' ),
                'permission_callback'   => array( $this, 'get_item_permissions_check' ),
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


    public function get_items_permissions_check( $request ) {

        if ( ! current_user_can( 'manage_options' ) ) {
            return new WP_Error( 'rest_forbidden', esc_html__( 'You cannot view orders.' ), array( 'status' => $this->authorization_status_code() ) );
        }
        return true;
    }

    public function get_items( $request ) {

        global $wpdb;
        $limit  = 20;
        $page   = wpdm_query_var('paged');
        $page   = $page > 0 ? $page : 1;
        $start  = ( $page - 1 ) * $limit;
        $cond   = array();


        $cond[] = "o.order_status = 'Completed' or o.order_status = 'Expired'";

        if(wpdm_query_var('product') != '')
            $cond[] = "product = '".wpdm_query_var('product', array('validate' => 'num'))."'";

        $usrc = '';
        if(wpdm_query_var('search') != '') {
            $src = wpdm_query_var('search');
            $usrc = "(u.user_login like '%{$src}%' or u.user_email like '%{$src}%' or u.user_nicename like '%$src%' or u.display_name like '%$src%' or u.ID = '$src') and o.uid = u.ID ";
            if(count($cond) > 0 ) $usrc = " and $usrc";
        }

        if(count($cond) > 0)
            $cond = "where (".implode(" or ", $cond).")"; else $cond = '';

        $sql                = "select o.uid from {$wpdb->prefix}ahm_orders o, {$wpdb->prefix}users u {$cond} {$usrc} GROUP BY o.uid ORDER BY o.uid DESC limit $start, $limit";
        $all_customers      = $wpdb->get_results($sql);
        $total_customers    = $wpdb->get_var("select count(DISTINCT uid) from {$wpdb->prefix}ahm_orders o {$cond}");

        foreach ($all_customers as &$customer) {
            $ID = $customer->uid;
            $customer = get_user_by('id', $ID);
            if(is_object($customer)) {
                $customer->value = wpdmpp_price_format(\WPDMPP\Libs\User::totalSpent($ID));
                $customer->date = wp_date(get_option('date_format')." ".get_option('time_format'), strtotime($customer->user_registered ));
            }
        }

        return rest_ensure_response( ['customers' => $all_customers, 'total' => $total_customers, 'current_page' => $page] );
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
