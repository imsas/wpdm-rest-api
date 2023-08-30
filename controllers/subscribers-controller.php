<?php
/**
 * Created by PhpStorm.
 * User: shahriar
 * Date: 2/19/19
 * Time: 12:02 PM
 */

class WPDM_REST_Subscribers_Controller {

    protected $namespace;
    protected $rest_base;

    public function __construct() {
        // REST Route: http://localhost/wpdm/wp-json/wpdm/v1/subscribers
        $this->namespace    = '/wpdm/v1';
        $this->rest_base    = 'subscribers';
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
        if ( empty( $request['pid'] ) ) {
            return new WP_Error( 'rest_empty_package_id', __( 'Must provide package ID.' ), array( 'status' => 400 ) );
        }
        if ( empty( $request['email'] ) ) {
            return new WP_Error( 'rest_empty_email', __( 'Must provide email address.' ), array( 'status' => 400 ) );
        }
        global $wpdb;

        $res = $wpdb->insert("{$wpdb->prefix}ahm_emails", array(
                'pid'               => (int) $request['pid'],
                'email'             => $request['email'],
                'date'              => isset( $request['date']  ) ? strtotime( $request['date'] ) : time(),
                'request_status'    => isset( $request['request_status']  ) ? $request['request_status'] : 0,
                'custom_data'       => isset( $request['custom_data']  ) ? serialize( ( array ) $request['custom_data'] ) : serialize( array())
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

        if ( ! current_user_can( 'manage_options' ) ) {
            return new WP_Error( 'rest_forbidden', esc_html__( 'You cannot view subscribers.' ), array( 'status' => $this->authorization_status_code() ) );
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
        $time_range     .= $start_date != '' ? "and e.date >={$start_date}" : '';
        $time_range     .= $end_date != '' ? " and e.date <={$end_date}" : '';

        $package_query  = isset( $request['package_id'] ) ? "and e.pid = ".(int) $request['package_id'] : '';

		$source = isset($request['source']) ? sanitize_text_field($request['source']) : 'emaillock';

		if($source === 'newsletter') {
			$sql = "SELECT *
                FROM {$wpdb->prefix}eden_subscriber                 
                ORDER BY `id` DESC
                LIMIT $start, $items_per_page";
			$res = $wpdb->get_results( $sql, ARRAY_A );
		} else if($source === 'membership') {
			$plans = WPPM()->plan_manager->getPlans();
			foreach ($plans as &$plan) {
				$plan = $plan->role_id;
			}
			//wp_send_json($plans);
			$res = get_users(['role__in' => $plans]);
			foreach ($res as &$r) {
				$r = $r->data;
				$subscription_table = $wpdb->prefix . 'wppm_subscriptions';
				$subscriptions = $wpdb->get_results("SELECT * FROM $subscription_table WHERE `user_id` = {$r->ID} ORDER BY `modified_at` DESC ");
				$subs = [];
				foreach ($subscriptions as $subscription) {
					$subs[] = ['plan_name' => get_the_title($subscription->plan_id), 'plan_id' => $subscription->plan_id, 'subscription_status' => $subscription->subscription_status, 'created_at' => $subscription->created_at, 'expires_at' => $subscription->expires_at, 'payment_system' => $subscription->payment_system_id];
				}
				$download_limit = WPPM()->user_manager->downloadLimit($r->ID);
				$user_download_count = WPPM()->user_manager->downloadCount($r->ID);
				$r = ['email' => $r->user_email, 'name' => $r->display_name, 'subscriptions' => $subs, 'download_limit' => $download_limit, 'download_count' => $user_download_count];
			}
		} else {
			$sql = "SELECT p.post_title, e.*
                FROM {$wpdb->prefix}posts p, {$wpdb->prefix}ahm_emails e
                WHERE e.pid = p.ID {$time_range} {$package_query}
                ORDER BY `id` DESC
                LIMIT $start, $items_per_page";
			$res = $wpdb->get_results( $sql, ARRAY_A );
		}


        $subscribers       = array();
        foreach( $res as $subscriber ){
	        $subscriber = (array)$subscriber;
            if( isset( $subscriber['custom_data'] ) ) $subscriber['custom_data'] = maybe_unserialize( $subscriber['custom_data'] );
            if( isset( $subscriber['date'] ) ) $subscriber['date'] = date( "F j, Y", $subscriber['date'] );
            $subscribers[] = $this->prepare_response_for_collection( $subscriber );
        }
         return rest_ensure_response( $subscribers );
    }

    public function get_item_permissions_check( $request ) {
        if ( ! current_user_can( 'manage_options' ) ) {
            return new WP_Error( 'rest_forbidden', esc_html__( 'You cannot view subscribers.' ), array( 'status' => $this->authorization_status_code() ) );
        }
        return true;
    }

    public function get_item( $request ) {
        global $wpdb;
        $record_id        = (int) $request['id'];
        $record_query     = isset( $request['id'] ) ? "and e.id = ".$record_id : '';

        $sql = "SELECT p.post_title, e.*
                FROM {$wpdb->prefix}posts p, {$wpdb->prefix}ahm_emails e
                WHERE e.pid = p.ID {$record_query}
                ORDER BY `id` DESC";
        $res = $wpdb->get_results( $sql, ARRAY_A );

        foreach( $res as $subscriber ){
            if( isset( $subscriber['custom_data'] ) ) $subscriber['custom_data'] = maybe_unserialize( $subscriber['custom_data'] );
            if( isset( $subscriber['date'] ) ) $subscriber['date'] = date( "F j, Y", $subscriber['date'] );
            return rest_ensure_response( $subscriber );
        }
    }

    public function update_item_permissions_check( $request ) {

        if ( ! current_user_can( 'manage_options' ) ) {
            return new WP_Error( 'rest_forbidden', esc_html__( 'You cannot edit subscriber.' ), array( 'status' => $this->authorization_status_code() ) );
        }
        return true;
    }

    public function update_item( $request ) {

        global $wpdb;
        $record_id        = (int) $request['id'];
        $subscriber = array();

        if( isset( $request['email'] ) ) $subscriber['email'] = $request['email'];
        if( isset( $request['date'] ) ) $subscriber['date'] = strtotime( $request['date'] );
        if( isset( $request['request_status'] ) ) $subscriber['request_status'] = $request['request_status'];
        if( isset( $request['custom_data'] ) ) $subscriber['custom_data'] = serialize( (array) $request['custom_data'] );

        $res = $wpdb->update( "{$wpdb->prefix}ahm_emails", $subscriber, array('id' => $record_id ) );

        return $this->get_item( array('id' => $record_id ) );
    }

    public function delete_item_permissions_check( $request ) {

        if ( ! current_user_can( 'manage_options' ) ) {
            return new WP_Error( 'rest_forbidden', esc_html__( 'You cannot delete subscriber.' ), array( 'status' => $this->authorization_status_code() ) );
        }
        return true;
    }

    public function delete_item( $request ) {

        global $wpdb;
        $record_id  = (int) $request['id'];
        $response   = $this->get_item( array('id' => $record_id ) );
        $table      = "{$wpdb->prefix}ahm_emails";
        $wpdb->delete( $table, array( 'id' => $record_id ) );

        return rest_ensure_response( $response );
    }

    public function prepare_item_for_response( $subscriber_info, $request ) {

        $subscriber_info = apply_filters( 'wpdm_rest_api_term_data_response', $subscriber_info );

        return rest_ensure_response( $subscriber_info );
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