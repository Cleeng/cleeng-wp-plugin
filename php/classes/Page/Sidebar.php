<?php

class Cleeng_Page_Sidebar
{
    public function render()
    {
        
        $cleeng = Cleeng_Core::load('Cleeng_Client');
?>

<div  class="stuffbox" >
<?php
    $noCookie = (isset($_COOKIE['cleeng_user_auth']))?false:true;
    $auth = false;
    $userName = '';

    try {
        if ( $cleeng->isUserAuthenticated() ) {
            $info = $cleeng->getUserInfo();
            $userName = $info['name'];
            $auth = true;
        }
    } catch (Exception $e) {
    }
    ?>

<div class="cleeng-noauth" <?php if ($auth) { echo 'style="display:none"'; } ?>>
    <h3><strong><?php _e('Register with Cleeng','cleeng') ?></strong></h3>
    <p class="cleeng-firsttime" <?php if (!$noCookie) { echo 'style="display:none"'; } ?>><?php _e('Sign up with Cleeng to protect your content.', 'cleeng') ?></p>
    <p class="cleeng-nofirsttime" <?php if ($noCookie) { echo 'style="display:none"'; } ?>><?php _e('Welcome, you need to log-in to protect your content.', 'cleeng') ?></p>

    <a class="button-secondary" id="cleeng-login" href="<?php echo $cleeng->getUrl() ?>/login">Log-in</a>
    <a class="button-secondary" id="cleeng-register-publisher" href="<?php echo $cleeng->getUrl() ?>/publisher-registration"><?php _e('Register as publisher', 'cleeng') ?></a>
</div>

    <div class="cleeng-auth" <?php if (!$auth) { echo 'style="display:none"'; } ?>>
        <h3><strong><?php echo sprintf(__('Welcome, <span id="cleeng-username">%s</span>', 'cleeng'), $userName); ?></strong></h3>
    <div class="inside">
        <div id="cleeng-auth-options">
            <ul class="likes">
                <li>
                     <a target="_blank"  href="<?php echo $cleeng->getUrl() ?>/my-account/sales-report"><?php _e('Sales report', 'cleeng') ?></a>
                </li>
                <li>
                     <a target="_blank" href="<?php echo $cleeng->getUrl() ?>/my-account/settings"><?php _e('Your settings', 'cleeng') ?></a>
                </li>
                <li>
                     <a id="cleeng-logout" href="#"><?php _e('Logout from Cleeng', 'cleeng') ?></a>
                </li>
            </ul>
        </div>
        <div id="cleeng-notPublisher" style="display:none;height:115px;">
            <?php _e('You need to have a Publisher account before using this widget. Please upgrade your account:', 'cleeng') ?>
            <a class="button-secondary become-publisher publisher-account"  href="<?php echo $cleeng->getUrl() ?>/publisher-registration/popup/1" ><?php _e('Become publisher', 'cleeng') ?></a>
            <a class="button-secondary become-publisher-logout" id="cleeng-logout2" href=""><?php _e('Logout', 'cleeng') ?></a>

        </div>
    </div>

</div>
</div>
<div class="stuffbox" >
<h3><?php _e('Like this plugin?','cleeng'); ?></h3>
<div class="inside">
    <?php _e('Why not do any or all of the following','cleeng'); ?>
    <ul class="likes">
        <?php $title = urlencode('Check out @Cleeng A simple, awesome WordPress plugin that truly helps me generate profits out of my creativity. http://cleeng.it/oqDkeE'); ?>
        <?php $url = urlencode('http://cleeng.com'); ?>

        <li><a class="cleeng-facebook" title="share on facebook" href="http://www.facebook.com/sharer.php?t=<?php echo $title ?>&u=<?php echo $url ?>"><?php _e('Share your story on Facebook','cleeng'); ?></a></li>

        <li><a target="_BLANK" href="http://wordpress.org/extend/plugins/cleeng/"><?php _e('Give it a 5 star rating on WordPress.org','cleeng'); ?></a></li>
        <li><a class="cleeng-twitter"  href="http://twitter.com?status=Check out @Cleeng A simple, awesome WordPress plugin that truly helps me generate profits out of my creativity. http://cleeng.it/oqDkeE" title=""><?php _e('Tell about this free plugin on Twitter','cleeng'); ?></a></li>
    </ul>
</div>
</div>
<div  class="stuffbox" >
<h3><?php _e('Need support?','cleeng'); ?></h3>
<div class="inside">
    <?php _e('If you have problems with this
              plugin or good ideas for improvements
              or new features, please talk about
              them in the <a href="https://support.cleeng.com/home">Support forums</a>.','cleeng'); ?>

</div>
</div>

<div  class="stuffbox" >
<h3><strong><?php _e('Latest news from Cleeng','cleeng'); ?></strong></h3>
<div class="inside">
 <ul class="rss">
    <?php

     $options = get_option('cleeng_rss');
     $use_cache = false;
     if (isset($options['rss_cache']) && isset($options['rss_cached_at'])) {
         if ($options['rss_cached_at'] + 3600 > time()) {
             $use_cache = true;
             $items = $options['rss_cache'];
         }
     }

     if (!$use_cache) {
        $rss =  Cleeng_Core::load('Cleeng_Rss'); //new rss_php;
        $rss->load('http://cleeng.com/blog/us/feed');

         $channel = $rss->getItems();
         $items = array();
         foreach ($channel as $item) {
             $items[] = array('title' => $item['title'], 'link' => $item['link']);
         }
         $options['rss_cached_at'] = time();
         $options['rss_cache'] = $items;
         update_option('cleeng_rss', $options);
     }

     foreach($items as $item) : ?>
         <li>
             <a href="<?php echo $item['link'] ?>"><?php echo $item['title'] ?></a>
         </li>
     <?php endforeach;


     ?>
  </ul>
</div>
</div>


<?php

    }
}