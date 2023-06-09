<?php
/**
 * Created by PhpStorm.
 * User: shahriar
 * Date: 2/19/19
 * Time: 12:02 PM
 */

class WPDM_REST_Packages_Controller {

    protected $namespace;
    protected $rest_base;

    public function __construct() {
        // REST Route: http://localhost/wpdm/wp-json/wpdm/v1/packages
        $this->namespace    = '/wpdm/v1';
        $this->rest_base    = 'packages';
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

	    register_rest_route( $this->namespace, '/' . $this->rest_base . '/(?P<id>[\d]+)/(?P<meta>[\w]+)', array(
		    array(
			    'methods'               => 'GET',
			    'callback'              => array( $this, 'get_meta' ),
			    'permission_callback'   => array( $this, 'get_item_permissions_check' ),
		    ),
		    array(
			    'methods'               => 'PUT',
			    'callback'              => array( $this, 'update_meta' ),
			    'permission_callback'   => array( $this, 'update_item_permissions_check' ),
		    ),
		    array(
			    'methods'               => 'DELETE',
			    'callback'              => array( $this, 'delete_meta' ),
			    'permission_callback'   => array( $this, 'delete_item_permissions_check' ),
		    ),
		    'schema' => null,
	    ) );
    }

    public function create_item_permissions_check( $request ) {

        if ( ! current_user_can( 'edit_posts' ) ) {
            return new WP_Error( 'rest_forbidden', esc_html__( 'You cannot create post.' ), array( 'status' => $this->authorization_status_code() ) );
        }
        return true;
    }

    public function uploadFile($fileinfo){

        if(!current_user_can('upload_files')) die('-2');

        $name = $fileinfo['name'];

        $ext = explode('.', $name);
        $ext = end($ext);
        $ext = strtolower($ext);
        if(in_array($ext, array('php', 'js', 'html', 'py', 'pl', 'htaccess'))) die('-3');

        do_action("wpdm_restapi_before_upload_file", $fileinfo );

        @set_time_limit(0);

        if(file_exists(UPLOAD_DIR.$name) && get_option('__wpdm_overwrrite_file',0) == 1){
            @unlink(UPLOAD_DIR.$name);
        }

        $filename = $name;

        if(get_option('__wpdm_sanitize_filename', 0) == 1)
            $filename = sanitize_file_name($filename);

        move_uploaded_file($fileinfo['tmp_name'], UPLOAD_DIR . $filename);
        do_action("wpdm_restapi_after_upload_file", UPLOAD_DIR . $filename);

        return $filename;

        //echo "|||".$filename."|||";
        //exit;
    }

    // TODO: Thumbnails upload from client & remote URL

    public function create_item( $request ) {
        if ( ! empty( $request['id'] ) ) {
            return new WP_Error( 'rest_post_exists', __( 'Cannot create existing post.' ), array( 'status' => 400 ) );
        }

        //https://developer.wordpress.org/reference/classes/wp_rest_request/get_file_params/
        $files_params = $request->get_file_params();
        //wpdmprecho($files_params);die();

        // File upload to /download-manager-files dir
        if( isset( $files_params['files'] ) ):
            $files = $files_params['files'];
            $prepare_files = array();

            // Multiple files
            if( is_array( $files['name'] ) ):
                foreach ($files['name'] as $indx => $file ):
                    $fileinfo = array();
                    $fileinfo['name']       = $file;
                    $fileinfo['type']       = $files['type'][$indx];
                    $fileinfo['tmp_name']   = $files['tmp_name'][$indx];
                    $fileinfo['error']      = $files['error'][$indx];
                    $fileinfo['size']       = $files['size'][$indx];

                    //wpdmprecho($fileinfo);
                    $res = $this->uploadFile($fileinfo);
                    array_push($prepare_files, $res );
                endforeach;
            endif;

            // Single file
            if( ! is_array( $files['name'] ) ):
                $res = $this->uploadFile($files);
                array_push($prepare_files, $res );
            endif;

            //wpdmprecho($prepare_files);

            $request['files'] = $prepare_files;
        endif;

        //die();

        $wpdm_meta              = array();
        $package                = array();
        $package['post_type']   = 'wpdmpro';
        $package['tax_input'] = [];
        if ( isset($request['title']) )         $package['post_title']      = $request['title'];
        if ( isset($request['slug']) )          $package['post_name']       = $request['slug'];
        if ( isset($request['description']) )   $package['post_content']    = $request['description'];
        if ( isset($request['excerpt']) )       $package['post_excerpt']    = $request['excerpt'];
        if ( isset($request['author']) )        $package['post_author']     = $request['author'];
        if ( isset($request['post_author']) )   $package['post_author']     = $request['post_author'];
        if ( isset($request['status']) )        $package['post_status']     = $request['status'];
        if ( isset($request['parent']) )        $package['post_parent']     = $request['parent'];
        if ( isset($request['categories']) )    $package['tax_input']['wpdmcategory'] = $request['categories'];
        if ( isset($request['tags']) )          $package['tax_input']['wpdmtag']      = $request['tags'];

        if( isset( $request['additional_previews'] ) )      $wpdm_meta['__wpdm_additional_previews'] = $request['additional_previews'];
        if( isset( $request['version'] ) )                  $wpdm_meta['__wpdm_version'] = $request['version'];
        if( isset( $request['link_label'] ) )               $wpdm_meta['__wpdm_link_label'] = $request['link_label'];
        if( isset( $request['quota'] ) )                    $wpdm_meta['__wpdm_quota'] = (int) $request['quota'];
        if( isset( $request['download_limit_per_user'] ) )  $wpdm_meta['__wpdm_download_limit_per_user'] = $request['download_limit_per_user'];
        if( isset( $request['view_count'] ) )               $wpdm_meta['__wpdm_view_count'] = (int) $request['view_count'];
        if( isset( $request['download_count'] ) )           $wpdm_meta['__wpdm_download_count'] = (int) $request['download_count'];
        if( isset( $request['package_size'] ) )             $wpdm_meta['__wpdm_package_size'] = $request['package_size'];
        if( isset( $request['access'] ) )                   $wpdm_meta['__wpdm_access'] = $request['access'];
        if( isset( $request['user_access'] ) )              $wpdm_meta['__wpdm_user_access'] = $request['user_access'];
        if( isset( $request['individual_file_download'] ) ) $wpdm_meta['__wpdm_individual_file_download'] = $request['individual_file_download'];
        if( isset( $request['cache_zip'] ) )                $wpdm_meta['__wpdm_cache_zip'] = $request['cache_zip'];
        if( isset( $request['template'] ) )                 $wpdm_meta['__wpdm_template'] = $request['template'];
        if( isset( $request['page_template'] ) )            $wpdm_meta['__wpdm_page_template'] = $request['page_template'];
        if( isset( $request['files'] ) )                    $wpdm_meta['__wpdm_files'] = $request['files'];
        if( isset( $request['fileinfo'] ) )                 $wpdm_meta['__wpdm_fileinfo'] = $request['fileinfo'];
        if( isset( $request['package_dir'] ) )              $wpdm_meta['__wpdm_package_dir'] = $request['package_dir'];
        if( isset( $request['publish_date'] ) )             $wpdm_meta['__wpdm_publish_date'] = $request['publish_date'];
        if( isset( $request['expire_date'] ) )              $wpdm_meta['__wpdm_expire_date'] = $request['expire_date'];
        if( isset( $request['terms_lock'] ) )               $wpdm_meta['__wpdm_terms_lock'] = $request['terms_lock'];
        if( isset( $request['terms_title'] ) )              $wpdm_meta['__wpdm_terms_title'] = $request['terms_title'];
        if( isset( $request['terms_conditions'] ) )         $wpdm_meta['__wpdm_terms_conditions'] = $request['terms_conditions'];
        if( isset( $request['terms_check_label'] ) )        $wpdm_meta['__wpdm_terms_check_label'] = $request['terms_check_label'];
        if( isset( $request['password_lock'] ) )            $wpdm_meta['__wpdm_password_lock'] = $request['password_lock'];
        if( isset( $request['password'] ) )                 $wpdm_meta['__wpdm_password'] = $request['password'];
        if( isset( $request['password_usage_limit'] ) )     $wpdm_meta['__wpdm_password_usage_limit'] = $request['password_usage_limit'];
        if( isset( $request['linkedin_lock'] ) )            $wpdm_meta['__wpdm_linkedin_lock'] = $request['linkedin_lock'];
        if( isset( $request['linkedin_message'] ) )         $wpdm_meta['__wpdm_linkedin_message'] = $request['linkedin_message'];
        if( isset( $request['linkedin_url'] ) )             $wpdm_meta['__wpdm_linkedin_url'] = $request['linkedin_url'];
        if( isset( $request['tweet_lock'] ) )               $wpdm_meta['__wpdm_tweet_lock'] = $request['tweet_lock'];
        if( isset( $request['tweet_message'] ) )            $wpdm_meta['__wpdm_tweet_message'] = $request['tweet_message'];
        if( isset( $request['twitterfollow_lock'] ) )       $wpdm_meta['__wpdm_twitterfollow_lock'] = $request['twitterfollow_lock'];
        if( isset( $request['twitter_handle'] ) )           $wpdm_meta['__wpdm_twitter_handle'] = $request['twitter_handle'];
        if( isset( $request['facebooklike_lock'] ) )        $wpdm_meta['__wpdm_facebooklike_lock'] = $request['facebooklike_lock'];
        if( isset( $request['facebook_like'] ) )            $wpdm_meta['__wpdm_facebook_like'] = $request['facebook_like'];
        if( isset( $request['email_lock'] ) )               $wpdm_meta['__wpdm_email_lock'] = $request['email_lock'];
        if( isset( $request['email_lock_title'] ) )         $wpdm_meta['__wpdm_email_lock_title'] = $request['email_lock_title'];
        if( isset( $request['email_lock_msg'] ) )           $wpdm_meta['__wpdm_email_lock_msg'] = $request['email_lock_msg'];
        if( isset( $request['email_lock_idl'] ) )           $wpdm_meta['__wpdm_email_lock_idl'] = $request['email_lock_idl'];
        if( isset( $request['icon'] ) )                     $wpdm_meta['__wpdm_icon'] = $request['icon'];

        if( isset( $request['base_price'] ) )               $wpdm_meta['__wpdm_base_price'] = (float) $request['base_price'];
        if( isset( $request['sales_price'] ) )              $wpdm_meta['__wpdm_sales_price'] = (float) $request['sales_price'];
        if( isset( $request['sales_price_expire'] ) )       $wpdm_meta['__wpdm_sales_price_expire'] = $request['sales_price_expire'];
        if( isset( $request['pay_as_you_want'] ) )          $wpdm_meta['__wpdm_pay_as_you_want'] = $request['pay_as_you_want'];
        if( isset( $request['license'] ) )                  $wpdm_meta['__wpdm_license'] = $request['license'];
        if( isset( $request['discount'] ) )                 $wpdm_meta['__wpdm_discount'] = $request['discount'];
        if( isset( $request['enable_license'] ) )           $wpdm_meta['__wpdm_enable_license'] = $request['enable_license'];
        if( isset( $request['enable_license_key'] ) )       $wpdm_meta['__wpdm_enable_license_key'] = $request['enable_license_key'];
        if( isset( $request['free_downloads'] ) )           $wpdm_meta['__wpdm_free_downloads'] = $request['free_downloads'];

        $request['meta_input']  = isset( $request['meta_input'] ) ? $request['meta_input'] : array();
        $package['meta_input']  = $request['meta_input'] + $wpdm_meta;

        // https://developer.wordpress.org/reference/functions/wp_insert_post/
        $post_id = wp_insert_post( $package, true );

        if ( is_wp_error( $post_id ) ) {
            if ( 'db_insert_error' === $post_id->get_error_code() ) {
                $post_id->add_data( array( 'status' => 500 ) );
            } else {
                $post_id->add_data( array( 'status' => 400 ) );
            }
            return $post_id;
        }

        // Setting package thumbnail
        if( isset( $request['thumbnail'] ) ){
            require_once( ABSPATH . 'wp-admin/includes/image.php' );
            $mime_type = '';
            $wp_filetype = wp_check_filetype( basename( $request['thumbnail'] ), null );
            if ( isset( $wp_filetype['type'] ) && $wp_filetype['type'] )
                $mime_type = $wp_filetype['type'];
            unset($wp_filetype);
            $attachment = array(
                'post_mime_type'    => $mime_type,
                'post_parent'       => $post_id,
                'post_title'        => basename( $request['thumbnail'] ),
                'post_status'       => 'inherit'
            );
            $attachment_id = wp_insert_attachment($attachment, $request['thumbnail'], $post_id);
            unset($attachment);

            if ( ! is_wp_error( $attachment_id ) ) {
                $attachment_data = wp_generate_attachment_metadata($attachment_id, $request['thumbnail'] );
                wp_update_attachment_metadata($attachment_id, $attachment_data);
                unset($attachment_data);
                set_post_thumbnail($post_id, $attachment_id);
            }
        }

        $post       = get_post( $post_id );
        $response   = $this->prepare_item_for_response( $post, $request );
        $response   = rest_ensure_response( $response );

        $response->set_status( 201 ); //201 Created
        $response->header( 'Location', rest_url( sprintf( '%s/%s/%d', $this->namespace, $this->rest_base, $post_id ) ) );

        return $response;
    }

    public function get_items_permissions_check( $request ) {

        if ( ! current_user_can( 'read' ) ) {
            return new WP_Error( 'rest_forbidden', esc_html__( 'You cannot view the post resource.' ), array( 'status' => $this->authorization_status_code() ) );
        }
        return true;
    }

    public function get_items( $request ) {

        $args = array(
            'post_type'         => 'wpdmpro',
            'posts_per_page'    => isset( $request['number_of_posts'] ) ? $request['number_of_posts'] : 10,
            'orderby'           => isset( $request['orderby'] ) ? $request['orderby'] : 'date',
            'order'             => isset( $request['order'] ) ? $request['order'] : 'DESC',
            'offset'            => isset( $request['offset'] ) ? $request['offset'] : 0,
        );

        //	Send comma separated ids, not an array
        if( isset($request['author']) ) $args['author'] = $request['author'];

        if( isset($request['search']) ) $args['s'] = $request['search'];

        $args['tax_query'] = [];
        // Send array of category terms
        if( isset($request['categories']) ) {
            $args['tax_query'][] = array(
                    'taxonomy' => 'wpdmcategory',
                    'field' => 'slug',
                    'terms' => explode(",", $request['categories'])
            );
        }

        if( isset($request['tag']) ) {
            $args['tax_query'][] = array(
                    'taxonomy' => 'wpdmtag',
                    'field' => 'slug',
                    'terms' => explode(",", $request['tag'])
            );
        }

        $the_query = new WP_Query( $args );
        $data = array();
        if ( $the_query->have_posts() ) {
            while ( $the_query->have_posts() ) {
                $the_query->the_post();
                $response = $this->prepare_item_for_response( get_post(), $request );
                $data[] = $this->prepare_response_for_collection( $response );
            }
            wp_reset_postdata();
        } else {
            return rest_ensure_response( $data );
        }

        return rest_ensure_response( $data );
    }

    public function get_item_permissions_check( $request ) {
        if ( ! current_user_can( 'read' ) ) {
            return new WP_Error( 'rest_forbidden', esc_html__( 'You cannot view the post.' ), array( 'status' => $this->authorization_status_code() ) );
        }
        return true;
    }

    public function get_item( $request ) {

        $id = (int) $request['id'];
        $post = get_post( $id );

        if ( empty( $post ) ) {
            return new WP_Error("wpdm_rest_invalid_post_id", __( 'Invalid Package ID.', 'download-manager' ), array( 'status' => 404 ) );
            //return rest_ensure_response( array() );
        }

        $response = $this->prepare_item_for_response( $post, $request );

        return $response;
    }

    public function update_item_permissions_check( $request ) {

        if ( ! current_user_can( 'edit_others_posts' ) ) {
            return new WP_Error( 'rest_forbidden', esc_html__( 'You cannot edit the post.' ), array( 'status' => $this->authorization_status_code() ) );
        }
        return true;
    }

    public function update_item( $request ) {

        $id             = (int) $request['id'];
        $wpdm_meta      = array();
        $package['ID']  = $id;

        if ( isset($request['title']) )         $package['post_title']      = $request['title'];
        if ( isset($request['slug']) )          $package['post_name']       = $request['slug'];
        if ( isset($request['description']) )   $package['post_content']    = $request['description'];
        if ( isset($request['excerpt']) )       $package['post_excerpt']    = $request['excerpt'];
        if ( isset($request['author']) )        $package['author']          = $request['author'];
        if ( isset($request['status']) )        $package['post_status']     = $request['status'];
        if ( isset($request['parent']) )        $package['post_parent']     = $request['parent'];
        if ( isset($request['categories']) )    wp_set_post_terms( $id, $request['categories'],'wpdmcategory' );
        if ( isset($request['tags']) )          $package['tags_input']      = $request['tags'];

        if( isset( $request['additional_previews'] ) )      $wpdm_meta['__wpdm_additional_previews'] = $request['additional_previews'];
        if( isset( $request['version'] ) )                  $wpdm_meta['__wpdm_version'] = $request['version'];
        if( isset( $request['link_label'] ) )               $wpdm_meta['__wpdm_link_label'] = $request['link_label'];
        if( isset( $request['quota'] ) )                    $wpdm_meta['__wpdm_quota'] = (int) $request['quota'];
        if( isset( $request['download_limit_per_user'] ) )  $wpdm_meta['__wpdm_download_limit_per_user'] = $request['download_limit_per_user'];
        if( isset( $request['view_count'] ) )               $wpdm_meta['__wpdm_view_count'] = (int) $request['view_count'];
        if( isset( $request['download_count'] ) )           $wpdm_meta['__wpdm_download_count'] = (int) $request['download_count'];
        if( isset( $request['package_size'] ) )             $wpdm_meta['__wpdm_package_size'] = $request['package_size'];
        if( isset( $request['access'] ) )                   $wpdm_meta['__wpdm_access'] = $request['access'];
        if( isset( $request['user_access'] ) )              $wpdm_meta['__wpdm_user_access'] = $request['user_access'];
        if( isset( $request['individual_file_download'] ) ) $wpdm_meta['__wpdm_individual_file_download'] = $request['individual_file_download'];
        if( isset( $request['cache_zip'] ) )                $wpdm_meta['__wpdm_cache_zip'] = $request['cache_zip'];
        if( isset( $request['template'] ) )                 $wpdm_meta['__wpdm_template'] = $request['template'];
        if( isset( $request['page_template'] ) )            $wpdm_meta['__wpdm_page_template'] = $request['page_template'];
        if( isset( $request['files'] ) )                    $wpdm_meta['__wpdm_files'] = $request['files'];
        if( isset( $request['fileinfo'] ) )                 $wpdm_meta['__wpdm_fileinfo'] = $request['fileinfo'];
        if( isset( $request['package_dir'] ) )              $wpdm_meta['__wpdm_package_dir'] = $request['package_dir'];
        if( isset( $request['publish_date'] ) )             $wpdm_meta['__wpdm_publish_date'] = $request['publish_date'];
        if( isset( $request['expire_date'] ) )              $wpdm_meta['__wpdm_expire_date'] = $request['expire_date'];
        if( isset( $request['terms_lock'] ) )               $wpdm_meta['__wpdm_terms_lock'] = $request['terms_lock'];
        if( isset( $request['terms_title'] ) )              $wpdm_meta['__wpdm_terms_title'] = $request['terms_title'];
        if( isset( $request['terms_conditions'] ) )         $wpdm_meta['__wpdm_terms_conditions'] = $request['terms_conditions'];
        if( isset( $request['terms_check_label'] ) )        $wpdm_meta['__wpdm_terms_check_label'] = $request['terms_check_label'];
        if( isset( $request['password_lock'] ) )            $wpdm_meta['__wpdm_password_lock'] = $request['password_lock'];
        if( isset( $request['password'] ) )                 $wpdm_meta['__wpdm_password'] = $request['password'];
        if( isset( $request['password_usage_limit'] ) )     $wpdm_meta['__wpdm_password_usage_limit'] = $request['password_usage_limit'];
        if( isset( $request['linkedin_lock'] ) )            $wpdm_meta['__wpdm_linkedin_lock'] = $request['linkedin_lock'];
        if( isset( $request['linkedin_message'] ) )         $wpdm_meta['__wpdm_linkedin_message'] = $request['linkedin_message'];
        if( isset( $request['linkedin_url'] ) )             $wpdm_meta['__wpdm_linkedin_url'] = $request['linkedin_url'];
        if( isset( $request['tweet_lock'] ) )               $wpdm_meta['__wpdm_tweet_lock'] = $request['tweet_lock'];
        if( isset( $request['tweet_message'] ) )            $wpdm_meta['__wpdm_tweet_message'] = $request['tweet_message'];
        if( isset( $request['twitterfollow_lock'] ) )       $wpdm_meta['__wpdm_twitterfollow_lock'] = $request['twitterfollow_lock'];
        if( isset( $request['twitter_handle'] ) )           $wpdm_meta['__wpdm_twitter_handle'] = $request['twitter_handle'];
        if( isset( $request['facebooklike_lock'] ) )        $wpdm_meta['__wpdm_facebooklike_lock'] = $request['facebooklike_lock'];
        if( isset( $request['facebook_like'] ) )            $wpdm_meta['__wpdm_facebook_like'] = $request['facebook_like'];
        if( isset( $request['email_lock'] ) )               $wpdm_meta['__wpdm_email_lock'] = $request['email_lock'];
        if( isset( $request['email_lock_title'] ) )         $wpdm_meta['__wpdm_email_lock_title'] = $request['email_lock_title'];
        if( isset( $request['email_lock_msg'] ) )           $wpdm_meta['__wpdm_email_lock_msg'] = $request['email_lock_msg'];
        if( isset( $request['email_lock_idl'] ) )           $wpdm_meta['__wpdm_email_lock_idl'] = $request['email_lock_idl'];
        if( isset( $request['icon'] ) )                     $wpdm_meta['__wpdm_icon'] = $request['icon'];

        if( isset( $request['base_price'] ) )               $wpdm_meta['__wpdm_base_price'] = (float) $request['base_price'];
        if( isset( $request['sales_price'] ) )              $wpdm_meta['__wpdm_sales_price'] = (float) $request['sales_price'];
        if( isset( $request['sales_price_expire'] ) )       $wpdm_meta['__wpdm_sales_price_expire'] = $request['sales_price_expire'];
        if( isset( $request['pay_as_you_want'] ) )          $wpdm_meta['__wpdm_pay_as_you_want'] = $request['pay_as_you_want'];
        if( isset( $request['license'] ) )                  $wpdm_meta['__wpdm_license'] = $request['license'];
        if( isset( $request['discount'] ) )                 $wpdm_meta['__wpdm_discount'] = $request['discount'];
        if( isset( $request['enable_license'] ) )           $wpdm_meta['__wpdm_enable_license'] = $request['enable_license'];
        if( isset( $request['enable_license_key'] ) )       $wpdm_meta['__wpdm_enable_license_key'] = $request['enable_license_key'];
        if( isset( $request['free_downloads'] ) )           $wpdm_meta['__wpdm_free_downloads'] = $request['free_downloads'];

        $request['meta_input']  = isset( $request['meta_input'] ) ? $request['meta_input'] : array();
        $package['meta_input']  = $request['meta_input'] + $wpdm_meta;

        //print_r($package  );

        if( isset( $request['thumbnail'] ) ){
            require_once( ABSPATH . 'wp-admin/includes/image.php' );
            $mime_type = '';
            $wp_filetype = wp_check_filetype( basename( $request['thumbnail'] ), null );
            if ( isset( $wp_filetype['type'] ) && $wp_filetype['type'] )
                $mime_type = $wp_filetype['type'];
            unset($wp_filetype);
            $attachment = array(
                'post_mime_type'    => $mime_type,
                'post_parent'       => $id,
                'post_title'        => basename( $request['thumbnail'] ),
                'post_status'       => 'inherit'
            );
            $attachment_id = wp_insert_attachment($attachment, $request['thumbnail'], $id);
            unset($attachment);

            if ( ! is_wp_error( $attachment_id ) ) {
                $attachment_data = wp_generate_attachment_metadata($attachment_id, $request['thumbnail'] );
                wp_update_attachment_metadata($attachment_id, $attachment_data);
                unset($attachment_data);
                set_post_thumbnail($id, $attachment_id);
            }
        }

        // https://developer.wordpress.org/reference/functions/wp_update_post/
        $post_id = wp_update_post( $package );

        if ( is_wp_error( $post_id ) ) {
            if ( 'db_insert_error' === $post_id->get_error_code() ) {
                $post_id->add_data( array( 'status' => 500 ) );
            } else {
                $post_id->add_data( array( 'status' => 400 ) );
            }
            return $post_id;
        }

        $response = $this->get_item(array('id' => $post_id ) );
        return rest_ensure_response( $response );
    }

    public function delete_item_permissions_check( $request ) {

        if ( ! current_user_can( 'delete_others_posts' ) ) {
            return new WP_Error( 'rest_forbidden', esc_html__( 'You cannot delete the post.' ), array( 'status' => $this->authorization_status_code() ) );
        }
        return true;
    }

    public function delete_item( $request ) {

        $id = (int) $request['id'];

        if ( ! is_object( get_post( $id ) ) ) {
            return rest_ensure_response( array() );
        }

        $post = $this->get_item( array('id' => $id ) );

        wp_delete_post($id,false);

        return rest_ensure_response( $post );
    }

	public function get_meta( $request ) {

		$id = (int) $request['id'];
		$meta = sanitize_key($request['meta']);
		$meta_value = get_post_meta( $id, $meta, true );
		$meta_value = maybe_unserialize($meta_value);


		if ( empty( $id ) || empty($meta)) {
			return new WP_Error("wpdm_rest_invalid_request", __( 'Invalid api request.', 'download-manager' ), array( 'status' => 404 ) );
		}

		$response = $this->prepare_item_for_response( $meta_value, $request );

		return $response;
	}

	public function update_meta( $request ) {

		$id = (int) $request['id'];
		$meta = sanitize_key($request['meta']);
		$meta_value = get_post_meta( $id, $meta, true );
		$meta_value = maybe_unserialize($meta_value);
		$response = update_post_meta( $id, $meta, $request['value'] );

		if ( empty( $id ) || empty($meta)) {
			return new WP_Error("wpdm_rest_invalid_request", __( 'Invalid api request.', 'download-manager' ), array( 'status' => 404 ) );
		}

		$response = $this->prepare_item_for_response( $response, $request );

		return $response;
	}

	public function delete_meta( $request ) {

		$id = (int) $request['id'];
		$meta = sanitize_key($request['meta']);
		$response = delete_post_meta( $id, $meta );

		if ( empty( $id ) || empty($meta)) {
			return new WP_Error("wpdm_rest_invalid_request", __( 'Invalid api request.', 'download-manager' ), array( 'status' => 404 ) );
		}

		$response = $this->prepare_item_for_response( $response, $request );

		return $response;
	}

    public function package_meta_data( $ID ) {
        $post_meta = get_post_custom( $ID );

        $data = array();
        if( is_array( $post_meta ) ){
            foreach ($post_meta as $key => $value) {
                if( strpos( $key,'__wpdmkey_') !== false ) continue;
                $key = str_replace("__wpdm_", "", $key);
                $data[$key] = maybe_unserialize($value[0]);
            }
        }

        $data['access']         = ( ! isset($data['access']) || ! is_array( $data['access'] ) ) ? array() : $data['access'];
        $data['download_count'] = isset( $data['download_count'] ) ? intval( $data['download_count'] ) : 0;
        $data['view_count']     = isset( $data['view_count'] ) ? intval( $data['view_count'] ) : 0;
        $data['version']        = isset( $data['version'] ) ? $data['version'] : '1.0.0';
        $data['quota']          = isset( $data['quota'] ) && $data['quota'] > 0 ? $data['quota'] : 0;

        return $data;
    }

    public function prepare_item_for_response( $post, $request ) {
        $post_data = array();

        $post_data['id']                = (int) $post->ID;
        $post_data['title']             = $post->post_title;
        $post_data['slug']              = $post->post_name;
        $post_data['description']       = $post->post_content;
        $post_data['excerpt']           = $post->post_excerpt;
        $post_data['author']            = (int) $post->post_author;
        $post_data['date_created']      = $post->post_date;
        $post_data['date_created_gmt']  = $post->post_date_gmt;
        $post_data['date_modified']     = $post->post_modified;
        $post_data['date_modified_gmt'] = $post->post_modified_gmt;
        $post_data['status']            = $post->post_status;
        $post_data['parent']            = $post->post_parent;
        $post_data['guid']              = $post->guid;
        $post_data['comment_count']     = $post->comment_count;
        $post_data['permalink']         = get_the_permalink( $post->ID );
        $post_data['tags']              = wp_get_post_terms( $post->ID, 'wpdmtag' );
        $post_data['categories']        = wp_get_post_terms( $post->ID, 'wpdmcategory' );
        $post_data['thumbnail']    = get_the_post_thumbnail_url($post);

        $wpdm_meta = $this->package_meta_data( $post->ID );

        $post_data = $post_data + $wpdm_meta;

        //return rest_ensure_response( array_merge( $post_data, $this->package_meta_data( $post->ID ) ) );

        $post_data = apply_filters( 'wpdm_rest_api_package_data_response', $post_data );

        return rest_ensure_response( $post_data );
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

    public function authorization_status_code() {

        $status = 401; //401 Unauthorized

        if ( is_user_logged_in() ) {
            $status = 403; // 403 Forbidden
        }

        return $status;
    }
}
