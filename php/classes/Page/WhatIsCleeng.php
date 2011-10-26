<?php

class Cleeng_Page_WhatIsCleeng
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
    <h2><div class="cleeng-icon"></div><?php _e('Cleeng for WordPress / What is Cleeng?', 'cleeng'); ?></h2>

    <div id="namediv" class="stuffbox">

    <h3><?php _e('What is Cleeng?'); ?></h3>

    <div class="genius"></div>


    <div class="banana-desc">

    <ul class="ul-what-is-cleeng">
        <li><?php _e('Generate more revenue, save money & time', 'cleeng'); ?></li>
        <li><?php _e('Monetize all content types: text, video, images', 'cleeng'); ?></li>
        <li><?php _e('From single sales to full site subscriptions', 'cleeng'); ?></li>
        <li><?php _e('Risk free, cost efficient and progressive', 'cleeng'); ?></li>

    </ul>

    </div>

    <object class="banana" type="application/x-shockwave-flash" id="player17094725_259110888" name="player17094725_259110888" class="" data="http://a.vimeocdn.com/p/flash/moogaloop/5.1.28/moogaloop.swf?v=1.0.0" width="100%" height="100%" style="visibility: visible; "><param name="allowscriptaccess" value="always"><param name="allowfullscreen" value="true"><param name="scalemode" value="noscale"><param name="quality" value="high"><param name="wmode" value="opaque"><param name="bgcolor" value="#000000"><param name="flashvars" value="server=vimeo.com&amp;player_server=player.vimeo.com&amp;cdn_server=a.vimeocdn.com&amp;embed_location=http%3A%2F%2Fcleeng.com%2Fus%2Ffeatures%2Fdemos&amp;force_embed=0&amp;force_info=0&amp;moogaloop_type=moogaloop&amp;js_api=1&amp;js_getConfig=player17094725_259110888.getConfig&amp;js_setConfig=player17094725_259110888.setConfig&amp;clip_id=17094725&amp;fullscreen=1&amp;js_onLoad=player17094725_259110888.player.moogaloopLoaded&amp;js_onThumbLoaded=player17094725_259110888.player.moogaloopThumbLoaded"></object>
    <br style="clear:left;">
    </div>


    <div id="namediv" class="stuffbox" style="min-height:550px;">
    <h3><?php _e('Sell your content, your way', 'cleeng'); ?></h3>


    <div class="hends"></div>

    <div class="inside">
    <?php _e('We are all potential artists: musician, blogger, teachers, photographers! Unleash your
                Monetization tips creativity, publish and thanks to Cleeng make money from it! Cleeng makes content
                Demos monetization very easy, whatever you create: text, pictures, videos and all you can
                think of! Just focus on bringing added-value to your readers and Cleeng takes care of
                the rest!
                ','cleeng'); ?>
    </div>

    <br style="clear:left">
    <div  class="inside">
    <?php _e('With Cleeng you can sell any individual piece of content directly from your
                own website. With Cleeng you can offer your digital goods:
        ', 'cleeng'); ?>
    <br/><br/>

    <?php _e('1. Sell <b>single items,</b>', 'cleeng'); ?><br/>
    <?php _e('2. Provide a <b>Daily pass</b>(24h), or', 'cleeng'); ?><br/>
    <?php _e('3. Give access to all your content via <b>subscriptions</b>', 'cleeng'); ?><br/>
    <br/>
    <div style="min-width:600px"><?php _e('Cleeng supports USD, EUR and GBP and you can price single items from 0.14 to 19.99 cents.', 'cleeng'); ?></div>
    <?php _e('With installing this plugin you can enjoy automatically many payment methods,
including:','cleeng')?>


    <div class="payment-methods"></div>

    <a href="http://cleeng.com/us/features" class="learn-about-cleeng button-secondary"><?php _e('Learn about all features on Cleeng.com', 'cleeng'); ?></a>
    </div>

    </div>

    <div id="namediv" class="stuffbox" style="min-height:855px;">
    <h3><?php _e('What else you need to know', 'cleeng'); ?></h3>

    <div class="inside">
    <?php _e('<b>In page monetization for higher conversions.</b>', 'cleeng'); ?><br/>
    <?php _e('Within the editor for pages or posts you can select any piece of content you want to hide, type a teaser/description and
            select the price for the item. You can also define dates to only protect your content a specific period (e.g first 2h after
            publication, or after 2 days of publication). Once the page is published your visitors can get instant access to it.','cleeng'); ?>
    <br/>
    <?php _e('<b>Daily Pass & Subscriptions</b>', 'cleeng'); ?>
    <br style="clear:left;">

     <div style="width:530px;float:left; ">
    <?php _e('You might consider to offer access to your premium content to your loyal visitors
        in a different way as well. Therefore this Cleeng offers a Daily pass and
        subscriptions. When a visitor purchases one of those - they get instant access to
        all the content published under your publisher account. You can define the set-
        up for Daily pass and subscription in your <a  href="'.$cleeng->getUrl().'/my-account/settings">profile settings</a> on Cleeng.com.
    ','cleeng'); ?>
     </div>

    <a href="" class="earth"></a>

    <br style="clear:left;">

    <a href="" class="clock"></a>

    <?php _e('<b>Completely free to try, learn and adapt</b>', 'cleeng'); ?>
    <br />

    <?php _e('Do you know what people are willing to pay for? Creating a content strategy, and
            having a good understanding of what your audience is wiling to pay for is likely
            something you need to learn by trying. With Cleeng you can try, learn and adapt
            without upfront investments. You can experiment for free.
    ','cleeng'); ?>
<br /><br />
    <?php _e('<b>Get paid monthly via PayPal or wire transfer</b>', 'cleeng'); ?>
<br />
    <?php _e('Within your <a  href="'.$cleeng->getUrl().'/my-account/settings">profile settings</a> on Cleeng.com you can set your publisher currency
            (USD, EUR or USD) as well as define on how you want to receive your monthly
            fees. You keep 80% at least, while Cleeng takes care of all customer service,
            payment fees, and more.
    ','cleeng'); ?>
    <br /><br />

    <div style="float:left;width:530px">
    <?php _e('<b>Let your visitors share & earn</b>', 'cleeng'); ?>
    <br />
    <?php _e('Cleeng offers a truly unique feature: a social commission system allowing
        users to be rewarded when sharing content with their friends.

    ','cleeng'); ?><br/><br/>

    <?php _e('You can enable the “cleeng it” program per individual piece of content. People
        can share and recommend the content directly from the layer once they have
        purchased the content or later from the library. If the article is purchased via
        the link they shared they will receive a commission.
    ','cleeng'); ?>
    </div>

    <div class="commission"></div>

    </div>
    </div>
</div>

</div>
</div>
    <?php
    }
}