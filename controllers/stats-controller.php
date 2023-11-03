<?php
/**
 * Created by PhpStorm.
 * User: shahriar
 * Date: 2/19/19
 * Time: 12:02 PM
 */

class WPDM_REST_Stats_Controller {

    protected $namespace;
    protected $rest_base;

    public function __construct() {
        // REST Route: http://localhost/wpdm/wp-json/wpdm/v1/stats
        $this->namespace    = '/wpdm/v1';
        $this->rest_base    = 'stats';
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
            return new WP_Error( 'rest_forbidden', esc_html__( 'You cannot create new record.' ), array( 'status' => $this->authorization_status_code() ) );
        }
        return true;
    }

    public function create_item( $request ) {
        if ( empty( $request['package_id'] ) ) {
            return new WP_Error( 'rest_empty_package_id', __( 'Must provide package ID.' ), array( 'status' => 400 ) );
        }
        global $wpdb;

        $res = $wpdb->insert("{$wpdb->prefix}ahm_download_stats",
            array('pid'     => (int) $request['package_id'],
                'uid'       => (int) $request['user_id'],
                'oid'       => $request['order_id'],
                'year'      => date("Y"),
                'month'     => date("m"),
                'day'       => date("d"),
                'timestamp' => time(),
                'ip'        => "{$request['ip']}"
            )
        );
        $lastid = $wpdb->insert_id;

        if ( ! $res ) {
            return array('message' => "DB Error");
        }

        $response = $this->get_item( array('id' => $lastid ) );

        return $response;
    }

    public function get_items_permissions_check( $request ) {

        if ( ! current_user_can( 'read' ) ) {
            return new WP_Error( 'rest_forbidden', esc_html__( 'You cannot view stats.' ), array( 'status' => $this->authorization_status_code() ) );
        }
        return true;
    }

    public function get_items( $request ) {

        global $wpdb;
        $items_per_page = isset( $request['items_per_page'] ) ? $request['items_per_page'] : 10;
        $start          = isset( $request['page'] ) ? ( $request['page'] - 1 ) * $items_per_page : 0;

        $start_date     = isset( $request['start_date'] ) ? strtotime( $request['start_date'] ) : '';
        $end_date       = isset( $request['end_date'] ) ? strtotime( $request['end_date'] ) : '';

        $time_range     = '';
        $time_range     .= $start_date != '' ? "and s.timestamp >={$start_date}" : '';
        $time_range     .= $end_date != '' ? " and s.timestamp <={$end_date}" : '';

		if(!current_user_can("manage_options")) $request['user_id'] = get_current_user_id();

        $package_query  = isset( $request['package_id'] ) ? "and s.pid = ".(int) $request['package_id'] : '';
        $user_query     = isset( $request['user_id'] ) ? "and s.uid = ".(int) $request['user_id'] : '';


        $sql = "SELECT p.post_title, s.* 
                FROM {$wpdb->prefix}posts p, {$wpdb->prefix}ahm_download_stats s 
                WHERE s.pid = p.ID {$time_range} {$package_query} {$user_query} 
                ORDER BY `timestamp` 
                DESC LIMIT $start, $items_per_page";
        $res = $wpdb->get_results( $sql );

        return rest_ensure_response( $res );

        /*$stats       = array();
        foreach($res as $stat){
            $stats[]     = $this->prepare_response_for_collection( $stat );
        }
        return rest_ensure_response( $stats );*/
    }

    public function get_item_permissions_check( $request ) {
        if ( ! current_user_can( 'manage_options' ) ) {
            return new WP_Error( 'rest_forbidden', esc_html__( 'You cannot view stats.' ), array( 'status' => $this->authorization_status_code() ) );
        }
        return true;
    }

    public function get_item( $request ) {
        global $wpdb;
        $stat_id        = (int) $request['id'];
        $stat_query     = isset( $request['id'] ) ? "and s.id = ".$stat_id : '';

        $sql = "SELECT p.post_title, s.* 
                FROM {$wpdb->prefix}posts p, {$wpdb->prefix}ahm_download_stats s 
                WHERE s.pid = p.ID {$stat_query} 
                ORDER BY `timestamp` 
                DESC";
        $res = $wpdb->get_results( $sql );

        foreach($res as $stat){
            return rest_ensure_response( $stat );
        }
    }

    public function update_item_permissions_check( $request ) {

        if ( ! current_user_can( 'manage_options' ) ) {
            return new WP_Error( 'rest_forbidden', esc_html__( 'You cannot edit stats.' ), array( 'status' => $this->authorization_status_code() ) );
        }
        return true;
    }

    public function update_item( $request ) {

        $id = (int) $request['id'];

        $response['message'] = 'No update option available';

        return $response;
    }

    public function delete_item_permissions_check( $request ) {

        if ( ! current_user_can( 'manage_options' ) ) {
            return new WP_Error( 'rest_forbidden', esc_html__( 'You cannot delete stats.' ), array( 'status' => $this->authorization_status_code() ) );
        }
        return true;
    }

    public function delete_item( $request ) {

        global $wpdb;
        $stat_id        = (int) $request['id'];
        $stat_query     = isset( $request['id'] ) ? "and s.id = ".$stat_id : '';

        $sql = "SELECT p.post_title, s.* 
                FROM {$wpdb->prefix}posts p, {$wpdb->prefix}ahm_download_stats s 
                WHERE s.pid = p.ID {$stat_query} 
                ORDER BY `timestamp` 
                DESC";
        $res = $wpdb->get_results( $sql );

        $response = array();
        foreach($res as $stat){
            $response = $stat;
        }

        $table  = "{$wpdb->prefix}ahm_download_stats";
        $wpdb->delete( $table, array( 'id' => $stat_id ) );

        return rest_ensure_response( $response );
    }

    public function prepare_item_for_response( $stat_info, $request ) {

        $stat_info = apply_filters( 'wpdm_rest_api_term_data_response', $stat_info );

        return rest_ensure_response( $stat_info );
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