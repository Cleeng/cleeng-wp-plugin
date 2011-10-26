<?php

ob_start();
define('WP_USE_THEMES', false);
require('../../../wp-load.php');
ob_end_clean();

define('WP_ADMIN', true);
if (!session_id()) {
    session_start();
}
$admin = Cleeng_Core::load('Cleeng_Admin');

global $wpdb;
$table_name = $wpdb->prefix . "posts";

$contentId = @$_REQUEST['contentId'];
$contentIds = @$_REQUEST['contentIds'];
$protection = @$_REQUEST['protection'];
$cleeng = Cleeng_Core::load('Cleeng_WpClient');
$default = $cleeng->getContentDefaultConditions();

if (isset($contentIds) && $contentIds != null) {

    $rows = $wpdb->get_results("SELECT * FROM " . $table_name . ' WHERE id IN (' . $contentIds . ')');

    $cleengContentIds = array();
    $i = 0;
    $content = array();

    foreach ($rows as $row) {
        $contentId = $row->ID;
        $postContent = $row->post_content;
        $postTitle = $rows->post_title;
        switch ($protection) {
            case 'remove-protection':
                preg_match_all('/(\[cleeng_content.+\"\])/', $postContent, $matches);

                if ($matches[1][0]) {

                    $postContent = str_replace($matches[1][0], ' ', $postContent);

                    preg_match('/id=\"([0-9]+)\"/', $matches[1][0], $matches);
                    $cleengContentId = $matches[1];

                    preg_match_all('/(\[\/cleeng_content\])/', $postContent, $matches);
                    $postContent = str_replace($matches[1][0], ' ', $postContent);

                    $cleengContentIds[] = $cleengContentId;

                    $wpdb->query(
                        "UPDATE $table_name 
                        SET post_content = '$postContent'
                        WHERE id = $contentId 
                    ");
                }
                break;
            case 'add-protection':
                preg_match_all('/(\[cleeng_content.+\"\])/', $postContent, $matches);

                if ($matches[1][0] == null) {
                    $content[$i]['shortDescription'] = substr($default['itemDescription'], 0, 100) . '...';
                    $content[$i]['price'] = $default['itemPrice'];
                    $content[$i]['itemType'] = 'article';
                    $content[$i]['url'] = 'http://' . $_SERVER['HTTP_HOST'] . '?p=' . $contentId;

                    if ($default['referralProgram'] != 0) {
                        $content[$i]['referralRate'] = $default['referralProgram'] / 100;
                        $content[$i]['referralProgramEnabled'] = 1;
                    }
                    $content[$i]['pageTitle'] = $postTitle . ' | ' . get_bloginfo();
                    $content[$i]['externalId'] = $contentId;
                }

            default:
                break;
        }
        $i++;
    }

    switch ($protection) {
        case 'remove-protection':
            $cleeng->removeContent($cleengContentIds);
            break;
        case 'add-protection':
            if (!$default) {
                $admin->session_error_message(__('You have to set up default content parameters in you Cleeng platform account.'));
                exit;
            }
            $return = $cleeng->createContent($content);

            foreach ($return as $row) {
                if ($row['contentSaved'] === true) {
                    $contentId = $row['externalId'];
                    $wpRow = $wpdb->get_results("SELECT * FROM " . $table_name . ' WHERE id = ' . $contentId . '');
                    $postContent = $wpRow[0]->post_content;
                    $postTitle = $wpRow[0]->post_title;

                    $str1 = '[cleeng_content id="' . $row['contentId'] . '" description="' . $default['itemDescription'] . '" price="' . $default['itemPrice'];
                    if ($default['referralProgram'] != 0) {
                        $str1 .= ' referral="' . $content['referralRate'] . '"';
                    }
                    $str1 .= '"]';
                    $str2 = '[/cleeng_content]';
                    if (strpos($postContent, '<!--more-->')) {
                        preg_match('/\<\!\-\-more\-\-\>(.+)/', $postContent, $matches);
                        $postContent = str_replace($matches[1], '', $postContent);
                        $postContent = $postContent . $str1 . $matches[1] . $str2;

                        $wpdb->update($table_name, array('post_content' => $postContent), array('id' => $contentId));
                    } else {

                        $wpdb->update($table_name, array('post_content' => $str1. $postContent. $str2), array('id' => $contentId));
                    }
                }
            }
            break;
    }
} else {
    $rows = $wpdb->get_results("SELECT * FROM " . $table_name . ' WHERE id = ' . $contentId . '');
    $postContent = $rows[0]->post_content;
    $postTitle = $rows[0]->post_title;

    switch ($protection) {
        case 'remove-protection':

            preg_match_all('/(\[cleeng_content.+\"\])/', $postContent, $matches);

            $postContent = str_replace($matches[1][0], ' ', $postContent);

            preg_match('/id=\"([0-9]+)\"/', $matches[1][0], $matches);
            $cleengContentId = $matches[1];

            preg_match_all('/(\[\/cleeng_content\])/', $postContent, $matches);
            $postContent = str_replace($matches[1][0], ' ', $postContent);

            if ($cleeng->removeContent(array($cleengContentId))) {
                $wpdb->update($table_name, array('post_content' => $postContent), array('id' => $contentId));
                
                $return = array(
                    'protecting' => 'off'
                );
                header('content-type: application/json');
                echo json_encode($return);
            } else {
                header('content-type: application/json');
                echo json_encode('error');
            }
            break;
        case 'add-protection':
            
                if (!$default) {
                    $admin->session_error_message(__('You have to set up default content parameters in you Cleeng platform account.'));
                    exit;
                }
                $default = $cleeng->getContentDefaultConditions();

                $content = array();
                $content['shortDescription'] = substr($default['itemDescription'], 0, 100) . '...';
                $content['price'] = $default['itemPrice'];
                $content['itemType'] = 'article';
                $content['url'] = 'http://' . $_SERVER['HTTP_HOST'] . '?p=' . $contentId;

                if ($default['referralProgram'] != 0) {
                    $content['referralRate'] = $default['referralProgram'] / 100;
                    $content['referralProgramEnabled'] = 1;
                }
                $content['pageTitle'] = $postTitle . ' | ' . get_bloginfo();
                $content['externalId'] = $contentId;
                
                $return = $cleeng->createContent(array($content));
                if ($return[0]['contentSaved'] === true) {
                    
                    $str1 = '[cleeng_content id="' . $return[0]['contentId'] . '" description="' . $default['itemDescription'] . '" price="' . $default['itemPrice'];
                    if ($default['referralProgram'] != 0) {
                        $str1 .= ' referral="' . $content['referralRate'] . '"';
                    }
                    $str1 .= '"]';
                    $str2 = '[/cleeng_content]';
                    if (strpos($postContent, '<!--more-->')) {
                        preg_match('/\<\!\-\-more\-\-\>(.+)/', $postContent, $matches);
                        $postContent = str_replace($matches[1], '', $postContent);
                        $postContent = $postContent . $str1 . $matches[1] . $str2;

                        $wpdb->update($table_name, array('post_content' => $postContent), array('id' => $contentId));

                    } else {
                        $wpdb->update($table_name, array('post_content' => $str1.$postContent.$str2), array('id' => $contentId));
                    }
                }
                
                $user = $cleeng->getUserInfo();
                $content['symbol'] = $user['currencySymbol'];
                $return = array(
                    'protecting' => 'on',
                    'info' => $content
                );
                
                header('content-type: application/json');
                echo json_encode($return);
            
        default:
            break;
    }
}
