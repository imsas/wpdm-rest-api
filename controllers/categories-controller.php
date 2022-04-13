<?php
/**
 * Created by PhpStorm.
 * User: shahriar
 * Date: 2/19/19
 * Time: 12:02 PM
 */

class WPDM_REST_Categories_Controller {

    protected $namespace;
    protected $rest_base;

    public function __construct() {
        // REST Route: http://localhost/wpdm/wp-json/wpdm/v1/categories
        $this->namespace    = '/wpdm/v1';
        $this->rest_base    = 'categories';
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

        if ( ! current_user_can( 'manage_categories' ) ) {
            return new WP_Error( 'rest_forbidden', esc_html__( 'You cannot create post.' ), array( 'status' => $this->authorization_status_code() ) );
        }
        return true;
    }

    public function create_item( $request ) {
	    //wpdmdd($request->get_body());
        if ( empty( $request['name'] ) ) {
            return new WP_Error( 'rest_empty_category_term', __( 'Must provide term name.' ), array( 'status' => 400 ) );
        }

        $args       = array();
        $taxonomy   = 'wpdmcategory';
        $term = $request['name'];
        if ( isset($request['slug']) ) {
            $args['slug'] = $request['slug'];
        }
        if ( isset($request['description']) ) {
            $args['description'] = wp_slash ( $request['description'] );
        }
        if ( isset($request['parent']) ) {
            $args['parent'] = ( int ) $request['parent'];
        }

        /**
         * Note: wp_insert_term returns this >> array('term_id'=>12,'term_taxonomy_id'=>34)
         */
        $term_tax_array = wp_insert_term( $term, $taxonomy, $args );

        if ( is_wp_error( $term_tax_array ) ) {

            if ( 'db_insert_error' === $term_tax_array->get_error_code() ) {
                $term_tax_array->add_data( array( 'status' => 500 ) );
            } else {
                $term_tax_array->add_data( array( 'status' => 400 ) );
            }

            return $term_tax_array;
        }

        $category_info = get_term( $term_tax_array['term_id'], $taxonomy, 'ARRAY_A' );

        $response = $this->prepare_item_for_response( $category_info, $request );
        $response = rest_ensure_response( $response );

        $response->set_status( 201 );
        $response->header( 'Location', rest_url( sprintf( '%s/%s/%d', $this->namespace, $this->rest_base, $term_tax_array['term_id'] ) ) );

        return $response;
    }

    public function get_items_permissions_check( $request ) {

        if ( ! current_user_can( 'read' ) ) {
            return new WP_Error( 'rest_forbidden', esc_html__( 'You cannot view categories.' ), array( 'status' => $this->authorization_status_code() ) );
        }
        return true;
    }

    public function get_items( $request ) {

        $args = array(
            'taxonomy'      => 'wpdmcategory',
            'orderby'       => isset( $request['orderby'] ) ? $request['orderby'] : 'name',
            'order'         => isset( $request['order'] ) ? $request['order'] : 'ASC',
            'hide_empty'    => isset( $request['hide_empty'] ) ? $request['hide_empty'] : false,
            'number'        => isset( $request['number'] ) ? $request['number'] : 0,
            'offset'        => isset( $request['offset'] ) ? $request['offset'] : 0,
            'parent'        => isset( $request['parent'] ) ? $request['parent'] : 0,
            'include'       => isset( $request['include'] ) ? $request['include'] : array(),
            'exclude'       => isset( $request['exclude'] ) ? $request['exclude'] : array(),
        );

        $terms       = array();
        $the_query  = new WP_Term_Query($args);
        foreach( $the_query->get_terms() as $term ){
            $response   = $this->get_item( array('id' => $term->term_id ) );
            $terms[]     = $this->prepare_response_for_collection( $response );
        }

        return rest_ensure_response( $terms );
    }

    public function get_item_permissions_check( $request ) {
        if ( ! current_user_can( 'read' ) ) {
            return new WP_Error( 'rest_forbidden', esc_html__( 'You cannot view this category.' ), array( 'status' => $this->authorization_status_code() ) );
        }
        return true;
    }

    public function get_item( $request ) {
        $term_id                        = (int) $request['id'];
        $category_info                  = get_term( $term_id, 'wpdmcategory', 'ARRAY_A' );
        $category_info['icon']          = get_term_meta($term_id,'__wpdm_icon', true );
        $category_info['role_access']   = get_term_meta($term_id,'__wpdm_access', true );
        $category_info['user_access']   = get_term_meta($term_id,'__wpdm_user_access', true );

        if ( empty( $category_info ) ) {
            return rest_ensure_response( array() );
        }

        $response = $this->prepare_item_for_response( $category_info, $request );

        return $response;
    }

    public function update_item_permissions_check( $request ) {

        if ( ! current_user_can( 'manage_categories' ) ) {
            return new WP_Error( 'rest_forbidden', esc_html__( 'You cannot edit the category.' ), array( 'status' => $this->authorization_status_code() ) );
        }
        return true;
    }

    public function update_item( $request ) {

        $id = (int) $request['id'];

        $args = array();

        if ( isset($request['name']) ) {
            $args['name'] = $request['name'];
        }
        if ( isset($request['slug']) ) {
            $args['slug'] = $request['slug'];
        }
        if ( isset($request['description']) ) {
            $args['description'] = $request['description'];
        }
        if ( isset($request['parent']) ) {
            $args['parent'] = $request['parent'];
        }

        $term_tax_array = wp_update_term( $id, 'wpdmcategory', $args );

        if ( is_wp_error( $term_tax_array ) ) {

            if ( 'db_insert_error' === $term_tax_array->get_error_code() ) {
                $term_tax_array->add_data( array( 'status' => 500 ) );
            } else {
                $term_tax_array->add_data( array( 'status' => 400 ) );
            }

            return $term_tax_array;
        }

        if ( isset($request['icon']) ) {
            update_term_meta( $id, '__wpdm_icon', $request['icon'] );
        }
        if ( isset($request['role_access']) ) {
            update_term_meta( $id, '__wpdm_access', $request['role_access'] );
        }
        if ( isset($request['user_access']) ) {
            update_term_meta( $id, '__wpdm_user_access', $request['user_access'] );
        }

        $response   = $this->get_item( array('id' => $id ) );

        return $response;
    }

    public function delete_item_permissions_check( $request ) {

        if ( ! current_user_can( 'manage_categories' ) ) {
            return new WP_Error( 'rest_forbidden', esc_html__( 'You cannot delete the category.' ), array( 'status' => $this->authorization_status_code() ) );
        }
        return true;
    }

    public function delete_item( $request ) {

        $id = (int) $request['id'];

        $term = $this->get_item( array('id' => $id ) );

        $term_deleted = wp_delete_term( $id,'wpdmcategory' );

        if ( is_wp_error( $term_deleted ) ) {

            if ( 'db_insert_error' === $term_deleted->get_error_code() ) {
                $term_deleted->add_data( array( 'status' => 500 ) );
            } else {
                $term_deleted->add_data( array( 'status' => 400 ) );
            }

            return $term_deleted;
        }

        return rest_ensure_response( $term );
    }

    public function prepare_item_for_response( $category_info, $request ) {

        $category_info = apply_filters( 'wpdm_rest_api_term_data_response', $category_info );

        return rest_ensure_response( $category_info );
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