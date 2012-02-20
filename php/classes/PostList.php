<?php

class Cleeng_PostList
{
    protected $content;
    protected $hasDefaultContentParams;
    protected $userInfo;
    protected $wpClient;

    public function setup()
    {
        // add UI dialog
        wp_enqueue_script( 'jquery-ui-dialog' );

        $cleeng = Cleeng_Core::load('Cleeng_WpClient');
        
        $this->wpClient = $cleeng;
        
        if ($this->wpClient->isUserAuthenticated()){
            $this->userInfo = $cleeng->getUserInfo(); 
        } else {
            $this->userInfo = false;
        }
        
        if (($this->wpClient->isUserAuthenticated() && $this->wpClient->getContentDefaultConditions() != null)) {
            setcookie("hasDefaultSetup", '1');
            $this->hasDefaultContentParams = true;
        } else {
            setcookie("hasDefaultSetup", '0');
            $this->hasDefaultContentParams = false;
        }
        add_filter('manage_pages_columns', array($this, 'filter_manage_posts_columns'));
        add_filter('manage_posts_columns', array($this, 'filter_manage_posts_columns'));

        add_action('manage_pages_custom_column', array($this, 'action_manage_posts_custom_column'), 5, 2);
        add_action('manage_posts_custom_column', array($this, 'action_manage_posts_custom_column'), 5, 2);

        
        wp_enqueue_script( 'jquery-tmpl', CLEENG_PLUGIN_URL . 'js/jquery.tmpl.min.js');

        $admin = Cleeng_Core::load('Cleeng_Admin');
        add_action( "admin_head-edit.php", array($admin, 'render_javascript') );
//        wp_enqueue_script( 'jquery-dialog', CLEENG_PLUGIN_URL . 'js/ui.dialog.min.js');
        wp_enqueue_style('jqueryUi.css', CLEENG_PLUGIN_URL . 'css/south-street/jquery-ui-1.8.2.custom.css');

        //if ($this->wpClient->isUserAuthenticated() && $this->hasDefaultContentParams){
            add_action( "admin_head-edit.php", array($this, 'render_cleeng_options'));
       // }
    }

    public function render_cleeng_options()
    {
        $select = '<div  id="cleeng-options" class="alignleft actions" style="display:none">';
        $select .= '<select>';
        $select .= '<option value="99">'.__('Cleeng options','cleeng').'</option>';
        //if ( $this->hasDefaultContentParams ) {
            $select .= '<option value="add-protection">'.__('Set-up item(s) for sale','cleeng').'</option>';
       // }
        $select .= '<option value="remove-protection">'.__('Remove protection','cleeng').'</option>';
        $select .= '</select>';
        $select .= '<div id="cleeng-option-loader" class="cleeng-loader"></div>';
        $select .= '</div>'; 
        echo $select;
        
        echo '<div id="cleeng-message-no-default-setup" title="<div id=\'cleeng-info-logo\'></div>'.__('Cleeng information','cleeng').'" style="display:none">
                <p>
                    <span class="ui-icon ui-icon-circle-minus" style="float:left; margin:0 7px 50px 0;"></span>
                    You can protect mulitple posts or pages automatically. <br />
                    To do so, please define your default sales settings. 
                </p>
             </div>';
            
        echo '<div id="cleeng-message-no-selected" title="<div id=\'cleeng-info-logo\'></div>'.__('Cleeng information','cleeng').'" style="display:none">
                <p>
                    <span class="ui-icon ui-icon-circle-minus" style="float:left; margin:0 7px 50px 0;"></span>
                    You have to select item(s)
                </p>
            </div>';           
    }
    
    public function get_cleeng_contents()
    {
        $cleeng = Cleeng_Core::load('Cleeng_WpClient');
        $editor = Cleeng_Core::load('Cleeng_Editor');
        global $wpdb;
        global $posts;

        $table_name = $wpdb->prefix . "cleeng_content";
        
        $contentIds = array();
        foreach ($posts as $postKey => $postVal) {
            $content = $editor->get_cleeng_content($postVal->post_content);

            if ($content != null) {
                foreach ($content as $c) {

		    if (is_numeric($c['contentId'])) {
        		$contentIds[] = $c['contentId'];
		    }
                }
            }
        }
        if (!count($contentIds)) {
            return array();
        }

        $rows = $wpdb->get_results("SELECT * FROM " . $table_name . ' WHERE content_id IN ("'.implode('","',$contentIds).'")');

        $contents = array();

        foreach ($rows as $cont) {
            $cont = (array)$cont;
            $contents[$cont['content_id']] = array(
                'contentId' => $cont['content_id'],
                'publisherId' => $cont['publisher_id'],
                'pageTitle' => $cont['page_title'],
                'currency' => $cont['currency'],
                'currencySymbol' => $cont['currency_symbol'],
                'shortDescription' => $cont['short_description'],
                'shortUrl' => $cont['short_url'],
                'itemType' => $cont['item_type'],
                'price' => $cont['price'],
                'referralProgramEnabled' => $cont['referral_program_enabled'],
                'referralRate' => $cont['referral_rate'],
                'subscriptionOffer' => $cont['subscription_offer'],
                'subscriptionPrompt' => $cont['subscription_prompt'],
            );
        }

        if (count($contents) != count($contentIds)) {
            $contentsInfo = $cleeng->getContentInfo($contentIds);
            foreach ($contentsInfo as $key => $cont) {
                if( !isset($contents[$key])) {
                    $insert = array(
                        'content_id' => $cont['contentId'],
                        'publisher_id' => $cont['publisherId'],
                        'page_title' => $cont['pageTitle'],
                        'currency' => $cont['currency'],
                        'currency_symbol' => $cont['currencySymbol'],
                        'short_description' => $cont['shortDescription'],
                        'short_url' => $cont['shortUrl'],
                        'item_type' => $cont['itemType'],
                        'price' => $cont['price'],
                        'referral_program_enabled' => $cont['referralProgramEnabled'],
                        'referral_rate' => $cont['referralRate'],
                        'subscription_offer' => $cont['subscriptionOffer'],
                        'subscription_prompt' => $cont['subscriptionPrompt'],
                    );

                    $wpdb->insert( $table_name, $insert);
                    $contents[$cont['contentId']] = $cont;
                }
            }
        }

        return $contents;
    }

    public function filter_manage_posts_columns($posts_columns)
    {
        $new_columns = array();
        foreach($posts_columns as $column => $val) {
            if($column == 'title' ){
                $new_columns[$column] = $val;
                $new_columns['cleeng'] = __('Cleeng', 'cleeng');
            } else {
                $new_columns[$column] = $val;
            }
        }
        $posts_columns = $new_columns;
        return $posts_columns;
    }

    public function action_manage_posts_custom_column($column_name, $id)
    {
        global $post;

        if ($column_name !== 'cleeng') {
            return;
        }

        if (!$this->content) {
            $this->content = $this->get_cleeng_contents();
        }

        $editor = Cleeng_Core::load('Cleeng_Editor');
        $post_content = $editor->get_cleeng_content($post->post_content);

        if (!isset($post_content[0])) {
             echo '<a id="cleeng-post-'.$id.'" class="cleeng-post cleengit cleeng-off" title="Protect it!" ></a>';
             return;
        }
        
        if (!isset($this->content[$post_content[0]['contentId']])) {
             echo '<a id="cleeng-post-'.$id.'" class="cleeng-post cleengit cleeng-off" title="Protect it!" ></a>';
             return;
        }
        
        $content = $this->content[$post_content[0]['contentId']];
        
        if ($content['contentId']) {
         
            $price = $content['price']==0?__('For free!', 'cleeng'):$content['currencySymbol'].$content['price'];
            echo '<input type="hidden" name="'.$id.'" value="'.$content['contentId'].'"/>';
            echo '<a id="cleeng-post-'.$id.'" class="cleeng-post cleengit cleeng-on"  title="'.$price."\n ".$content['shortDescription'].'" ></a>';

        } else {
            echo '<a id="cleeng-post-'.$id.'" class="cleeng-post cleengit cleeng-off" title="Protect it!" ></a>';
        }
        
    }

}
