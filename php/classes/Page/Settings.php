<?php

class Cleeng_Page_Settings
{
    function settings_environment_render() {
        $options = Cleeng_Core::get_config();
        if ($options['platformUrl'] == 'cleeng.com') {
            $ch1 = ' checked="checked"';
            $ch2 = '';
        } else {
            $ch1 = '';
            $ch2 = ' checked="checked"';
        }
        echo '
           <label for="cleeng_environment_live">
               <input type="radio" name="cleeng_options[platformUrl]"
                    id="cleeng_environment_live" ' . $ch1 . ' value="cleeng.com"/>'
               . __('LIVE (real transactions!)', 'cleeng') .
           '</label>
           <label for="cleeng_environment_sandbox">
               <input type="radio" name="cleeng_options[platformUrl]"
                    id="cleeng_environment_sandbox" ' . $ch2 . ' value="sandbox.cleeng.com" />'
               . __('SANDBOX (test transactions)', 'cleeng') .
           '</label>';

    }

    function settings_show_prompt_render() {
        $options = Cleeng_Core::get_config();
        if ($options['show_prompt']) {
            $ch = ' checked="checked"';
        } else {
            $ch = '';
        }
        echo '
           <label for="cleeng_show_prompt">
               <input type="hidden" name="cleeng_options[show_prompt]" value="0" />
               <input type="checkbox" name="cleeng_options[show_prompt]"
                    value="1" id="cleeng_show_prompt" ' . $ch . ' />'
               . __('Enable text above layer.', 'cleeng') .
           '</label>';

    }

    function settings_payment_method_render() {
        $options = Cleeng_Core::get_config();
        if ($options['payment_method'] == 'cleeng-only') {
            $ch1 = ' checked="checked"';
            $ch2 = '';
        } else {
            $ch1 = '';
            $ch2 = ' checked="checked"';

        }
        echo '
           <label for="cleeng_payment_method_cleeng_only">
               <input type="radio" name="cleeng_options[payment_method]"
                    id="cleeng_payment_method_cleeng_only" ' . $ch1 . ' value="cleeng-only" />'
               . __('<strong>Standard</strong>: Multiple payment options including PayPal, using credits stored on the Cleeng account', 'cleeng') .
           '</label>
           <br />
           <label for="cleeng_payment_method_paypal_only">
               <input type="radio" name="cleeng_options[payment_method]"
                    id="cleeng_payment_method_paypal_only" ' . $ch2 . ' value="paypal-only" />'
               . __('<strong>PayPal only</strong>: direct PayPal Digital Goods payment from the layer<br />
    Note: PayPal only payments are applicable for payments above 0.49 cents. Does not work in combination with subscriptions. All other functionalities (user library, social commission) remain identical.', 'cleeng') .
           '</label>';

    }

    function settings_environment_description() {
        _e('<p>Here you can select if you want to enable real transactions and earn money (LIVE) or just experiment and test with the Cleeng service using the sandbox environment (SANDBOX). In case you have selected "SANDBOX", please avoid covering content on your public website as your visitors might be very confused. Also note that your settings, content references and accounts are NOT copied in between SANDBOX servers and the LIVE servers. So only use SANDBOX if you want to test on a non-public website.</p>', 'cleeng');
    }

    function settings_prompt_description() {
        _e('<p>With protected content, Cleeng would automatically add a short text above the layer. This text will increase the likelyhood to buy for consumers. If you prefer to write this text yourself just, just un-tick the box below.</p>', 'cleeng');
    }

    function settings_payment_method_description() {
        _e('<p>You may choose between 2 different payment options on the Cleeng layers.</p>', 'cleeng');
    }


    public function render()
    {
        $cleeng = Cleeng_Core::load('Cleeng_WpClient');

        add_settings_section('cleeng_payment_method', __('Payment activation mechanism', 'cleeng'),
                             'cleeng_settings_payment_method_description', 'cleeng');
        add_settings_section('cleeng_prompt', __('Text above layer', 'cleeng'),
                             'cleeng_settings_prompt_description', 'cleeng');
        add_settings_section('cleeng_environment', __('Choose LIVE or SANDBOX', 'cleeng'),
                             'cleeng_settings_environment_description', 'cleeng');
        add_settings_field('environment', '',
                           array($this, 'settings_environment_render'), 'cleeng', 'cleeng_environment');
        add_settings_field('show_prompt', '',
                           array($this, 'settings_show_prompt_render'), 'cleeng', 'cleeng_prompt');
        add_settings_field('payment_method', '',
                           array($this, 'settings_payment_method_render'), 'cleeng', 'cleeng_payment_method');

        add_settings_section('cleeng_payment_method', __('Payment activation mechanism', 'cleeng'),
                             array($this, 'settings_payment_method_description'), 'cleeng');
        add_settings_section('cleeng_prompt', __('Text above layer', 'cleeng'),
                             array($this, 'settings_prompt_description'), 'cleeng');
        add_settings_section('cleeng_environment', __('Choose LIVE or SANDBOX', 'cleeng'),
                             array($this, 'settings_environment_description'), 'cleeng');

     ?>
        <div id="cleeng">
        <div id="poststuff" class="wrap">

            <?php if (isset($_GET['settings-updated']) && $_GET['settings-updated']==='true'): ?>
            <div id="setting-error-settings_updated" class="updated settings-error">
                <p><strong>Settings saved.</strong></p></div>
            <?php endif; ?>
            <div class="right">
                <?php
                Cleeng_Core::load('Cleeng_Page_Sidebar')->render();
                ?>
            </div>
            <div class="left">


            <h2><div class="cleeng-icon"></div><?php _e('Cleeng for WordPress / Settings','cleeng'); ?></h2>

            <div id="namediv" class="stuffbox">
            <h3><label><?php _e('Settings to manage','cleeng'); ?></label></h3>
            <div class="inside">
            <?php _e('To fully leverage Cleeng you need to understand the different "settings":','cleeng') ?>     <br/><br/>

            <?php _e('<strong>Publisher settings - </strong>
Your personal profile, as well as your publisher profile, including your currency and pay-out details. You need to <a class="publisher-account" href="'.$cleeng->getUrl().'/publisher-registration/popup/1">activate a publisher account</a> with Cleeng. Once you have activated an account you can manage all publisher details in <a href="'.$cleeng->getUrl().'/my-account/settings">Settings</a> on the Cleeng Platform.                

            ','cleeng') ?> <br/><br/>

            <?php _e('<strong>Protect & sell single items - </strong>
Within the WordPress editor, select the piece of content you want to protect and once you click on the button (right-hand side) you can set the price, define a description, etc. To see how it works, watch <a href="">this video</a> for details.
            ','cleeng') ?>  <br/><br/>

            
            <?php _e('<strong>Daily pass & Subscriptions - </strong>
Once you have protected multiple items, you can consider to set up subscriptions. You can manage them in your <a href="'.$cleeng->getUrl().'/my-account/settings/edit-publisher-plan/1#edit-publisher-plan">subscription settings</a> on the Cleeng platform. A subscriber gets access to all your publications for the time specified.
            ','cleeng') ?>  <br/><br/>
            
                        
            <?php _e('<strong>Bulk protection for single items - </strong>
To quickly setup multiple items for sale, ensure you have <a href="'.$cleeng->getUrl().'/my-account/settings/single-item-sales/1#edit-single-item-sales">default conditions</a> set-up. With a single click you can now - within the post/page overview - set-up items to sell.
            ','cleeng') ?>  <br/><br/>            
            
           

            <?php _e('<strong>Cleeng settings for WordPress - </strong>a few additional settings that are specific to the plugin you are using. You can find them
                        <a href="#" id="cleeng_advanced2">below</a>.
            ','cleeng') ?>
             </div> </div>
            <a href="#below" id="cleeng_advanced"><?php _e('Cleeng settings for WordPress','cleeng'); ?></a> <span id="arrow" style="color:#21759B;"></span>
            <br/><br/>
        <?php



            global $wp_settings_sections, $wp_settings_fields;


            ?>
            <form method="post"  action="options.php">
                <?php settings_fields('cleeng'); ?>
                <?php

                foreach ( (array) $wp_settings_sections['cleeng'] as $section ) {

                    echo "<div class='cleeng_advanced stuffbox'><h3>{$section['title']}</h3><div class='inside'>\n";

                    call_user_func($section['callback'], $section);

                    if ( !isset($wp_settings_fields)
                        || !isset($wp_settings_fields['cleeng'])
                        || !isset($wp_settings_fields['cleeng'][$section['id']]) ) {
                        continue;
                    }
                    echo '<table class="form-table" style="clear:left;">';
                    do_settings_fields('cleeng', $section['id']);
                    echo '</table>';

                    echo '</div></div>';
                }

                ?>
                <p id="below" class="submit cleeng_advanced">
                    <input style="width:100px;margin:0 10px;" type="submit" name="Submit" class="button-primary" value="<?php esc_attr_e('Save Changes','cleeng') ?>" />
                </p>
            </form>


            </div>

        </div>
        </div>
    <?php
    }
}