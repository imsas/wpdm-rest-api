<?php
$api_key = get_option('_wpdm_api_key');
if($api_key == ''){
    $api_key = uniqid();
    update_option('_wpdm_api_key', $api_key);
}
?>
<div class="panel panel-default">
    <div class="panel-heading">API Settings</div>
    <div class="panel-body">
        <input type="hidden" name="_wpdm_save_apis" value="1">
        <div class="form-group">
            <label for="wpdm_api_key"><?php _e('API Key', 'downloadmanager'); ?></label>
            <div class="input-group">
                <input class="form-control" type="text" name="_wpdm_api_key" id="wpdm_api_key" value="<?php echo $api_key; ?>"/>
                <span class="input-group-btn">
                    &nbsp;<button type="button" id="generate_wpdm_api_key" class="btn btn-default">Regenerate</button>
                </span>
            </div>
        </div>

        <!--<div class="form-group">
            <label><input type="checkbox" name="_wpdm_pn_ondownload" value="1" <?php /*checked(get_option('_wpdm_pn_ondownload', 0), 1) */?>> Send Push Notification when someone downloads</label>
        </div>
        <div class="form-group">
            <label><input type="checkbox" name="_wpdm_pn_onsale" value="1" <?php /*checked(get_option('_wpdm_pn_onsale', 0), 1) */?> > Send Push Notification when someone purchase from your store</label>
        </div>
        <fieldset>
            <div class="media">
                <div class="pull-right">
                    <img id="qrcode" src="https://chart.googleapis.com/chart?cht=qr&chs=256x256&chl=wpdmapi|<?php /*echo home_url('/'); */?>|<?php /*echo $api_key; */?>" />
                </div>
                <div class="media-body" style="padding-left: 30px">
                    <br/><br/>
                    <h3 style="font-weight: 700;font-size: 13pt;margin-bottom: 15px"><?php /*_e('QR Code', 'wpdm-api'); */?></h3>
                    Use API key or scan the QR code from your app to connect instantly.<br/>
                    If you did not install the App yet:<br/><br/>
                    <div class="row">
                        <div class="col-md-6">
                            <a href="https://itunes.apple.com/us/app/wp-download-manager/id949343686?ls=1&mt=8" target="_blank" class="btn btn-default btn-sm btn-block">
                                <div class="media">
                                    <div class="pull-left" style="padding-right: 0"><i class="fab fa-apple fa-3x"></i></div>
                                    <div class="media-body"><small>Download On</small><h3>App Store</h3></div>
                                </div>
                            </a>
                        </div>
                        <div class="col-md-6">
                            <a href="https://play.google.com/store/apps/details?id=com.w3eden.wpdownloadmanager" target="_blank" class="btn btn-success btn-sm btn-block">
                                <div class="media">
                                    <div class="pull-left" style="padding-right: 0"><i class="fa fa-play fa-3x"></i></div>
                                    <div class="media-body"><small>Download On</small><h3>Play Store</h3></div>
                                </div>
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </fieldset>-->

    </div>
</div>
<script type="text/javascript">
    jQuery(function ($) {
        $('#generate_wpdm_api_key').click(function () {
            var nonce = '<?php echo wp_create_nonce( "ajax_nonce" ); ?>';
            jQuery.ajax({
                type: "post",
                dataType: "json",
                url: "<?php echo admin_url( 'admin-ajax.php' ); ?>",
                data: {action: "wpdm_change_api_key", nonce: nonce},
                success: function (response) {
                    //console.log(response);
                    if (response.type === "success") {
                        if (response.key) {
                            $('#wpdm_api_key').val(response.key);
                            $('#qrcode').attr('src', 'https://chart.googleapis.com/chart?cht=qr&chs=256x256&chl=wpdmapi|<?php echo home_url('/'); ?>|'+response.key);
                        }
                    }
                }
            });
            return false;
        });
    });
</script>