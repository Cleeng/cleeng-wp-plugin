<?php
class Cleeng_Dashboard
{

    public function setup()
    {
        wp_add_dashboard_widget( 'my_wp_dashboard_test', __( 'Cleeng - Sell your content <span id="cleeng-dashboard-logo"></span>' ), array($this,'content') );
        
        wp_enqueue_script( 'jquery-tmpl', CLEENG_PLUGIN_URL . 'js/jquery.tmpl.min.js');

        $admin = Cleeng_Core::load('Cleeng_Admin');
        add_action( "admin_head-index.php", array($admin, 'render_javascript') );
    }

    /**
     * Content of Dashboard-Widget
     */
    function content()
    {
        $cleeng = Cleeng_Core::load('Cleeng_WpClient');
        ?>
            
        <div  id="cleeng-dashboard-content"></div>
        <script id="cleeng-dashboard-content-template" type="text/x-jquery-tmpl">
           <div class="cleeng-last-7-days"><?php _e('Last 30 days: ','cleeng') ?></div> 
            
            
            <table class="cleeng-dashboad-table">
                <tr>
                    <th><?php _e('Earnings','cleeng') ?></th>
                    <th><?php _e('Purchases','cleeng') ?></th>
                    <th><?php _e('Impressions','cleeng') ?></th>
                    <th><?php _e('Conversion','cleeng') ?></th>
                </tr>
                <tr>
                    <td>${earnings}</td>
                    <td>${purchases}</td>
                    <td>${impressions}</td>
                    <td>${conversions}</td>
                </tr>
            </table>
           
           <div class="cleeng-see-details">
            <?php _e('See details in the <a target="_BLANK" href="'. $cleeng->getUrl().'/my-account/sales-report">sales report</a>.','cleeng') ?>
           </div>
           <br/><br/>
        </script>
        <div id="cleeng-dashboard-login" style="display:none">
            <a class="CleengWidget-auth-link" id="cleeng-login" href="#"><?php _e('Log in','cleeng') ?></a>
            <?php _e('or','cleeng') ?>
            <a class="publisher-account" href="<?php echo $cleeng->getUrl() ?>/publisher-registration/popup/1"><?php _e('register','cleeng') ?></a>
            <?php _e('in order to sell content directly from your website.','cleeng') ?>
        </div>
        
        <div id="cleeng-connecting">Connecting to Cleeng Platform..</div>

        <?php
    }
    
}