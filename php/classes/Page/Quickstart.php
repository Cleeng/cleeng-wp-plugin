<?php

class Cleeng_Page_Quickstart
{
    public function render()
    {
        $cleeng = Cleeng_Core::load('Cleeng_WpClient');
        ?>
        <div id="cleeng">
        <div id="poststuff" class="wrap">
        <div class="right">
<?php Cleeng_Core::load('Cleeng_Page_Sidebar')->render(); ?>
        </div>
            <div class="left">
            <h2><div class="cleeng-icon"></div><?php _e('Cleeng for WordPress / Get started in 3 minutes','cleeng'); ?></h2>


            <div id="namediv" class="stuffbox" style="float:left;">
            <h3>
            <label><?php _e('Register','cleeng'); ?></label>
            </h3>

            <div class="inside">
            <?php echo __('You have to create a publisher account with Cleeng in order to start selling content (hey, we need to know who
                    should get the money!). While you register you can define your currency and other settings, or adjust them later in your
                    profile settings. Cleeng is free to use.
                    ','cleeng') ?>

                <a class="register-with-cleeng publisher-account button-primary" href="<?php echo $cleeng->getUrl()?>/publisher-registration/popup/1" title="<?php echo _e('Activate a publisher account','cleeng') ?>"><?php echo _e('Activate a publisher account','cleeng') ?></a>


            </div>
            </div>
            <br style="clear:left;"/>

            <div id="namediv" class="stuffbox" style="float:left;">
            <h3>
            <label><?php _e('Sell per item','cleeng'); ?></label>
            </h3>
             <div class="inside" >
            <?php echo _e('When you edit posts or pages you\'ll find a Cleeng widget on the right-hand side.','cleeng') ?>
            <br/><br/>
            <div class="login-a"></div>
            <div style="float:left;margin:10px 20px;width:240px;">
            <?php echo _e('<b>A. Log in with Cleeng</b><br/>
                        When you edit posts or
                        pages you\'ll find a Cleeng
                        widget on the right-hand
                        side. Make sure you are
                        logged in with Cleeng
                        ','cleeng') ?>
            </div>

            <div class="login-b"></div>
                <div style="float:left;margin:10px 20px;width:240px;">

                <?php echo _e('<b>B. Select the content to protect</b><br/>
                            Once you are logged with a
                            publisher account you see a big
                            green button on the right-hand
                            side. Just select the piece of
                            content in the editor you want to
                            sell and click the green button. You
                            can select text, images or even
                            objects. In general it is best to give
                            part of your content for free, and
                            only sell the most valuable piece.
                            ','cleeng') ?>
                </div>

                <div style="float:left;width:480px;">
                <?php echo _e('<b>C. Define item price and more <br/> </b>After you have selected
                                the content you want to protect and sell, you can define the
                                settings for this item. First define a good teaser that
                                describes what you are selling. A good description will help
                                conversion. Than define the right price-point. With Cleeng
                                you can sell anything in between 14 cents and 19.99$ (or &euro;/&pound;).
                            ','cleeng') ?>
                    <br/><br/>

                <?php echo _e('You have the option to set dates for the period you want to
                            sell your content. Outside these dates your content will be
                            freely available. So for example you can decide to only sell
                            your content for the first day, or even hour.
                            ','cleeng') ?>
                    <br/><br/>
                <?php echo _e('Last, the referral program. Define the commission that your
                            visitors will earn once they successfully refer your content,
                            and they pay. Last, the social commission program.
                            Define the commission that your visitors earn once they successfully
                            refer your content. With the personal cleeng.it URL the visitors
                            and the purchases are tracked. Credits are automatically
                            calculated and paid to the original referrer.
                            ','cleeng') ?>
                </div>
                <div class="new-account-element"></div>


                 <div style="clear:left;float:left;width:100%;margin-top:30px;">
                <?php echo _e('Now click save, and that\'s it! Two markers appear in your
                    editor indicating the protected part of your content.
                    After you click “publish” the content in between
                    the tags is automatically protected and ready to be sold.
                            ','cleeng') ?>

                 </div>


            <div class="editor"></div>
            </div>
            </div>

            <div id="namediv" class="stuffbox"  style="float:left;">
            <h3>
            <label><?php _e('Set-up daily pass & subscriptions','cleeng'); ?></label>
            </h3>
             <div class="inside" style="width:930px;height:410px;">

            <?php echo _e('
        Once you have protected multiple pages and/or posts, you can offer daily passes or subscriptions. Go to your <a href="'.$cleeng->getUrl().'/my-account/settings">settings page</a> on cleeng.com and enable the subscriptions. You find more instructions there. With a daily pass or subscription you provide access to all your publications for a certain time period.','cleeng') ?>
            <br/><br/>
            <div class="set-up-subscriptions"></div>
            </div>
             </div>

            </div>
                <br style="clear:both;"/>
            </div>

        </div>
<?php
    }
}