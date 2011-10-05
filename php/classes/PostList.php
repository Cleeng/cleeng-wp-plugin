<?php

class Cleeng_PostList
{
    protected $content;

    public function setup()
    {
        $cleeng = Cleeng_Core::load('Cleeng_Client');
        add_filter('manage_posts_columns', array($this, 'filter_manage_posts_columns'));
        add_action('manage_posts_custom_column', array($this, 'action_manage_posts_custom_column'), 5, 2);
    }

    public function get_cleeng_contents()
    {
        $cleeng = Cleeng_Core::load('Cleeng_Client');
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
            return;
        }

        if (!isset($this->content[$post_content[0]['contentId']])) {
            return;
        }
        $content = $this->content[$post_content[0]['contentId']];
        if ($content['contentId']){

            $price = $content['price']==0?__('For free!', 'cleeng'):$content['currencySymbol'].$content['price'];

            echo '<a href="/?p='.$id.'"><img style="margin:10px 0;cursor:pointer" title="'.$price."\n ".$content['shortDescription'].'" src="'.CLEENG_PLUGIN_URL.'/img/cleengit.png"></a>';
        }
    }

}