<?php
/*
Plugin Name:  WPDM - REST API
Plugin URI: https://www.wpdownloadmanager.com/download/wpdm-api/
Description: WordPress Download Manager REST API
Author: Shahriar
Version: 1.3.3
Author URI: https://wpdownloadmanager.com/
Update URI: wpdm-rest-api
*/
if (!defined('WPINC')) {
    exit();
}

class WPDM_REST_API {

    public function __construct() {
        add_filter( 'add_wpdm_settings_tab', array( $this, 'wpdm_rest_api_settings' ) );
        add_filter( 'register_post_type_args', array( $this, 'wpdm_post_type_args' ), 10, 2 );
        add_filter( 'use_block_editor_for_post_type', array( $this, 'wpdm_disable_gutenberg' ), 10, 2);
        add_action( 'rest_api_init', array( $this, 'wpdm_init_rest_api' ) );
	    add_filter( 'update_plugins_wpdm-rest-api', [ $this, "updatePlugin" ], 10, 4 );

        if (defined('DOING_AJAX')) {
            add_action('wp_ajax_wpdm_change_api_key', array($this, 'generateToken'));
        }
        if ( is_admin() ) {
            add_action('init', array($this, 'save_settings'));
        }

        include_once dirname(__FILE__ ) . '/basic-auth.php';
    }

    public function wpdm_rest_api_settings($tabs){
        $tabs['restapi'] = wpdm_create_settings_tab('restapi', 'REST API', array( $this, 'wpdm_api_settings' ), $icon = 'fas fa-code');
        return $tabs;
    }

    public function wpdm_api_settings(){
        include_once dirname(__FILE__).'/tpls/settings.php';
    }

    public function save_settings(){
        if (isset($_POST['_wpdm_save_apis'])) {
            if ( isset($_POST['_wpdm_api_key'] ) ) update_option('_wpdm_api_key', $_POST['_wpdm_api_key'] );
            if ( ! isset($_POST['_wpdm_pn_ondownload'] ) ) delete_option('_wpdm_pn_ondownload');
            if ( ! isset($_POST['_wpdm_pn_onsale'] ) ) delete_option('_wpdm_pn_onsale');
            die('Settings Saved Successfully.');
        }
    }

    public function generateToken()
    {
        $result['type'] = '';
        if (check_ajax_referer('ajax_nonce', 'nonce', false)) {
            $better_token = "";
            $uid = uniqid("", true);
            $data = "";
            $data .= $_SERVER['REQUEST_TIME'];
            $data .= $_SERVER['HTTP_USER_AGENT'];
            $data .= $_SERVER['LOCAL_ADDR'];
            $data .= $_SERVER['LOCAL_PORT'];
            $data .= $_SERVER['REMOTE_ADDR'];
            $data .= $_SERVER['REMOTE_PORT'];
            $toHash = $uid . md5($data); //unique id

            // Avoid Collision Attacks with different algorithms
            $tempHash = '';
            $algorithms = array('sha512', 'ripemd320', 'whirlpool');
            foreach ($algorithms as $algo) {
                $tempHash .= hash($algo, $toHash);
            }
            //reset hash length
            $toHash = hash('whirlpool', $tempHash);
            $better_token = substr($toHash, 0, 16);

            $result['type'] = "success";
            $result['key'] = "$better_token";
        } else {
            $result['type'] = "Nonce Error";
        }

        if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
            $result = json_encode($result);
            echo $result;
        } else {
            header("Location: " . $_SERVER["HTTP_REFERER"]);
        }
        die();
    }

    // Add REST API support to wpdmpro post type
    public function wpdm_post_type_args( $args, $post_type ) {
        if ( 'wpdmpro' === $post_type ) {
            $args['show_in_rest'] = true;
        }
        return $args;
    }

    // Disable Gutenberg for WPDM post type
    public function wpdm_disable_gutenberg($is_enabled, $post_type) {
        if ($post_type === 'wpdmpro') return (bool)((int)get_option('__wpdm_gutenberg_editor', 0));
        return $is_enabled;
    }

    // Register our REST routes from the controller.
    public function wpdm_init_rest_api() {
        $this->include_rest_api_controllers();

        $controllers = array(
            'WPDM_REST_Packages_Controller',
            'WPDM_REST_Categories_Controller',
            'WPDM_REST_Tags_Controller',
            'WPDM_REST_Stats_Controller',
            'WPDM_REST_Subscribers_Controller',
            'WPDM_REST_Orders_Controller',
            'WPDM_REST_Coupons_Controller',
            'WPDM_REST_License_Controller',
            'WPDM_REST_Customers_Controller',
            'WPDM_REST_Siteinfo_Controller',
        );

        foreach ( $controllers as $controller ) {
            $this->$controller = new $controller();
            $this->$controller->register_routes();
        }
    }

    private function include_rest_api_controllers(){
        include_once dirname(__FILE__ ) . '/controllers/packages-controller.php';
        include_once dirname(__FILE__ ) . '/controllers/categories-controller.php';
        include_once dirname(__FILE__ ) . '/controllers/stats-controller.php';
        include_once dirname(__FILE__ ) . '/controllers/subscribers-controller.php';
        include_once dirname(__FILE__ ) . '/controllers/orders-controller.php';
        include_once dirname(__FILE__ ) . '/controllers/tags-controller.php';
        include_once dirname(__FILE__ ) . '/controllers/coupons-controller.php';
        include_once dirname(__FILE__ ) . '/controllers/licenses-controller.php';
        include_once dirname(__FILE__ ) . '/controllers/customers-controller.php';
        include_once dirname(__FILE__ ) . '/controllers/siteinfo-controller.php';
    }

	function updatePlugin( $update, $plugin_data, $plugin_file, $locales ) {
		$id                = basename( __DIR__ );
		$latest_versions   = WPDM()->updater->getLatestVersions();
		$latest_version    = wpdm_valueof( $latest_versions, $id );
		$access_token      = wpdm_access_token();
		$update            = [];
		$update['id']      = $id;
		$update['slug']    = $id;
		$update['url']     = $plugin_data['PluginURI'];
		$update['tested']  = true;
		$update['version'] = $latest_version;
		$update['package'] = $access_token !== '' ? "https://www.wpdownloadmanager.com/?wpdmpp_file={$id}.zip&access_token={$access_token}" : '';

		return $update;
	}
}

$WPDM_REST_API = new WPDM_REST_API();
