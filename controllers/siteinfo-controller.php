<?php
/**
 *
 */

class WPDM_REST_Siteinfo_Controller {

    protected $namespace;
    protected $rest_base;

    public function __construct() {
        // REST Route: http://localhost/wpdm/wp-json/wpdm/v1/siteinfo
        $this->namespace    = '/wpdm/v1';
        $this->rest_base    = 'siteinfo';
    }

    // Register our routes.
    public function register_routes() {
        register_rest_route( $this->namespace, '/' . $this->rest_base, array(
            array(
                'methods'               => 'GET',
                'callback'              => array( $this, 'siteinfo' ),
                'permission_callback'   => array( $this, 'get_permissions_check' ),
            ),
            'schema' => null,
        ) );

        register_rest_route( $this->namespace, '/' . $this->rest_base . '/overview', array(
            array(
                'methods'               => 'GET',
                'callback'              => array( $this, 'overview' ),
                'permission_callback'   => array( $this, 'admin_permissions_check' ),
            ),
            'schema' => null,
        ) );

    }

	function siteinfo()
	{
		$icon = get_site_icon_url();
		$icon = $icon ?: WPDM_BASE_URL . 'assets/images/wpdm-logo.png';
		$siteinfo = [ 'name' => get_bloginfo('name'), 'desc' => get_bloginfo('description'),  'version' => get_bloginfo('version'), 'url' => get_bloginfo('url'), 'icon' => $icon];
		return rest_ensure_response($siteinfo);
	}

    function overview( )
    {
        global $wpdb;

        if(!\WPDM\__\Session::get( 'daily_sales' )) {
            $daily_sales = wpdmpp_daily_sales('', '', date("Y-m-d", strtotime("-6 Days")), date("Y-m-d", strtotime("Tomorrow")));
            \WPDM\__\Session::set('daily_sales', $daily_sales);

        } else
            $daily_sales = \WPDM\__\Session::get( 'daily_sales' );


        $date = new DateTime();
        $date->modify('this week -6 days');
        $fdolw =  $date->format('Y-m-d');

        $date = new DateTime();
        $date->modify('this week');
        $ldolw =  $date->format('Y-m-d');

        $date = new DateTime();
        $date->modify('first day of last month');
        $fdolm = $date->format('Y-m-d');

        $date = new DateTime();
        $date->modify('first day of this month');
        $ldolm = $date->format('Y-m-d');

        $dn = 0;

        $last_year = date("Y")-1;

        $this_Week = wpdmpp_total_sales('', '', $ldolw, date("Y-m-d", strtotime("Tomorrow")));

        $daily_sales_dataset = [['Date', '$', '#']];
        $day_count = 0;
        foreach ($daily_sales['sales'] as $date => $sale) {
         $daily_sales_dataset[] = [date("D", strtotime($date)), $sale, $daily_sales['quantities'][$date]];
         if($day_count++ > 6) break;
        }
        $sales['daily7'] = $daily_sales_dataset;
        $c1 = $daily_sales['sales'][date("Y-m-d")];
        $sales['today'] = wpdmpp_currency_sign().number_format($c1,2);
        $c2 = $daily_sales['sales'][date("Y-m-d", strtotime("Yesterday"))];
        $sales['yesterday'] = wpdmpp_currency_sign().number_format($c2,2);
        $sales_day_move = number_format(($c2 - $c1) / 100, 0);
        $sales['daymove'] = $sales_day_move;
        $sales['thisweek'] = wpdmpp_currency_sign().wpdmpp_total_sales('', '', $ldolw, date("Y-m-d", strtotime("Tomorrow")));
        $sales['lastweek'] = wpdmpp_currency_sign().wpdmpp_total_sales('', '', $fdolw, $ldolw);
        $sales['thismonth'] = wpdmpp_currency_sign().wpdmpp_total_sales('', '', date("Y-m-01"), date("Y-m-d", strtotime("Tomorrow")));
        $sales['lastmonth'] = wpdmpp_currency_sign().wpdmpp_total_sales('', '', $fdolm, $ldolm);
        $sales['thisyear'] = wpdmpp_currency_sign().number_format(wpdmpp_total_sales('', '', date("Y-01-01"), date("Y-m-d", strtotime("Tomorrow"))),2,'.',',');
        $sales['lastyear'] = wpdmpp_currency_sign().number_format(wpdmpp_total_sales('', '', "$last_year-01-01", date("Y-01-01")),2,'.',',');
        $sales['total'] = wpdmpp_currency_sign().number_format(wpdmpp_total_sales('', '', "1990-01-01", date("Y-m-d", strtotime('Tomorrow'))),2,'.',',');
        $stats['sales'] = $sales;
        $users = count_users();
        $stats['totalusers'] = $users['total_users'];
        $stats['customers'] = wpdm_valueof($users, 'avail_roles/wpdmpp_customer');

        $y = date("Y");
        $m = date("m");
        $d = date("d");
        $yd = date("d", strtotime("yesterday"));
        $stats['downloads'] = [
            'total' => wpdm_total_downloads(),
            'today' => (int)$wpdb->get_var("select count(id) from {$wpdb->prefix}ahm_download_stats where `year`='{$y}' and `month` = '{$m}' and `day` = '{$d}'"),
            'yesterday' => (int)$wpdb->get_var("select count(id) from {$wpdb->prefix}ahm_download_stats where `year`='{$y}' and `month` = '{$m}' and `day` = '{$yd}'")
        ];
        $stats['downloads']['daymove'] = (int)(($stats['downloads']['today'] - $stats['downloads']['yesterday']) / 100);

        $today = date("Y-m-d");
        $stats['signuptoday'] = (int)$wpdb->get_var("select count(ID) from {$wpdb->prefix}users where user_registered like '%{$today}%'");
        $yesterday = date("Y-m-d", strtotime("Yesterday"));
        $stats['signupyesterday'] = (int)$wpdb->get_var("select count(ID) from {$wpdb->prefix}users where user_registered like '%{$yesterday}%'");
        $stats['signupmove'] = (int)(($stats['signuptoday'] - $stats['signupyesterday']) / 100);

        return rest_ensure_response($stats);
    }

    public function get_permissions_check( $request ) {

        if ( ! current_user_can( 'read' ) ) {
            return new WP_Error( 'rest_forbidden', esc_html__( 'You cannot view siteinfo.' ), array( 'status' => $this->authorization_status_code() ) );
        }
        return true;
    }

    public function admin_permissions_check( $request ) {

        if ( ! current_user_can( 'manage_options' ) ) {
            return new WP_Error( 'rest_forbidden', esc_html__( 'You cannot view siteinfo.' ), array( 'status' => $this->authorization_status_code() ) );
        }
        return true;
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
