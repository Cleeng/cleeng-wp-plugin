/**
* jQuery Cookie plugin
*
* Copyright (c) 2010 Klaus Hartl (stilbuero.de)
* Dual licensed under the MIT and GPL licenses:
* http://www.opensource.org/licenses/mit-license.php
* http://www.gnu.org/licenses/gpl.html
*
*/
jQuery.cookie = function (key, value, options) {

    // key and value given, set cookie...
    if (arguments.length > 1 && (value === null || typeof value !== "object")) {
        options = jQuery.extend({}, options);

        if (value === null) {
            options.expires = -1;
        }

        if (typeof options.expires === 'number') {
            var days = options.expires, t = options.expires = new Date();
            t.setDate(t.getDate() + days);
        }

        return (document.cookie = [
            encodeURIComponent(key), '=',
            options.raw ? String(value) : encodeURIComponent(String(value)),
            options.expires ? '; expires=' + options.expires.toUTCString() : '', // use expires attribute, max-age is not supported by IE
            options.path ? '; path=' + options.path : '',
            options.domain ? '; domain=' + options.domain : '',
            options.secure ? '; secure' : ''
        ].join(''));
    }

    // key and possibly options given, get cookie...
    options = value || {};
    var result, decode = options.raw ? function (s) {return s;} : decodeURIComponent;
    return (result = new RegExp('(?:^|; )' + encodeURIComponent(key) + '=([^;]*)').exec(document.cookie)) ? decode(result[1]) : null;
};

/**
 * Cleeng For WordPress
 *
 * LICENSE
 *
 * Following code is subject to the new BSD license that is bundled
 * with this package in the file license.txt.
 * It is also available through the world-wide-web at this URL:
 * http://cleeng.com/license/new-bsd.txt
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to theteam@cleeng.com so we can send you a copy immediately.
 *
 * Frontend JS library
 */
var CleengWidget = {

    contentIds: [],
    popupWindow: null,

    init: function() {
        jQuery(document).ajaxError(function(e, xhr, settings, exception) {
            if (typeof(console) !== 'undefined') {
                console.log(e);
                console.log(xhr);
                console.log(settings);
                console.log(exception);
            }
        });        

        jQuery('.cleeng-login').click(function() {CleengWidget.logIn();return false;});
        jQuery('.cleeng-logout').click(function() {CleengWidget.logOut();return false;});
        
        CleengWidget.contentIds = [];

        jQuery('.cleeng-layer').each(function() {
            var contentId = jQuery(this).attr('id').split('-')[2];
            CleengWidget.contentIds.push(contentId);
            jQuery('#cleeng-layer-' + contentId + ' .cleeng-buy')
                .click(function() {
                    CleengWidget.purchaseContent(contentId);
                    return false;
                });
            jQuery('#cleeng-nolayer-' + contentId + ' .cleeng-vote-liked').click(function() {
                jQuery.post(
                    Cleeng_PluginPath + 'ajax.php?cleengMode=vote&liked=1&contentId=' + contentId,
                    function() {
                        CleengWidget.getContentInfo();
                    }
                );
                return false;
            });
            jQuery('#cleeng-nolayer-' + contentId + ' .cleeng-vote-didnt-like').click(function() {
                jQuery.post(
                    Cleeng_PluginPath + 'ajax.php?cleengMode=vote&liked=0&contentId=' + contentId,
                    function() {
                        CleengWidget.getContentInfo();
                    }
                );
                return false;
            });
        });

        jQuery('.cleeng-rating a').click(function() {
            return false;
        });        

        jQuery('.cleeng-share a').click(function() {
            window.open(jQuery(this).attr('href'), 'shareWindow', 'menubar=no,width=607,height=600,toolbar=no,resizable=yes');
            return false;
        });

        CleengWidget.authUser(true);

        jQuery('.cleeng-it').click(function() {
            layer = jQuery(this).parents('.cleeng-nolayer');
            layer.find('.cleeng-share').show();

            contentId = layer.attr('id').split('-')[2];

            referredIds = jQuery.cookie('cleeng_referred_ids')
            if (referredIds) {
                referredIds = referredIds.split(',');
            } else {
                referredIds = [];
            }
            referredIds.push(contentId);
            
            jQuery.cookie('cleeng_referred_ids', referredIds.join(','), {expires: 30, path: '/'});
            if (parseInt(contentId)) {
                jQuery.post(
                    Cleeng_PluginPath + 'ajax.php?cleengMode=refer&contentId=' + contentId,
                    function(res) {                        
                        CleengWidget.getContentInfo();
                    }
                );
            }
            jQuery(this).hide();
            return false;
        });

        CleengWidget.fixShadows();

        // autologin
        if (typeof CleengAutologin !== 'undefined') {
            if (CleengAutologin.available) {
                jQuery.getJSON(
                    Cleeng_PluginPath+'ajax.php?cleengMode=autologin&id=' + CleengAutologin.id
                        + '&key=' + CleengAutologin.key,
                    function(resp) {
                        if (resp && resp.success) {
                            CleengWidget.authUser(false);
                        }
                    }
                );
            }
        }
    },
    authUser: function(dontFetchContentInfo) {
        jQuery.getJSON(
            Cleeng_PluginPath+'ajax.php?cleengMode=getUserInfo',
            function(resp) {
                CleengWidget.userInfo = resp;
                if (!resp || !resp.name) {
                    jQuery('.cleeng-auth').hide();
                    jQuery('.cleeng-noauth').show();
                } else {
                    jQuery('.cleeng-username').html(resp.name);                    
                    jQuery('.cleeng-auth').show();
                    jQuery('.cleeng-noauth').hide();
                    if (parseInt(resp.freeContentViews)) {
                        jQuery('.cleeng-free-content-views span').text(resp.freeContentViews);
                    } else {
                        jQuery('.cleeng-free-content-views').hide();
                    }
                }
                if (!dontFetchContentInfo) {
                    CleengWidget.getContentInfo();
                }
                CleengWidget.fixShadows();
            }
        );
    },
    logIn: function() {
        if (this.popupWindow) {
            this.popupWindow.close();
        }
        this.popupWindow = window.open(Cleeng_PluginPath + 'ajax.php?cleengMode=auth&cleengPopup=1','CleengConfirmationPopUp', 
                    'menubar=no,width=607,height=600,toolbar=no,resizable=yes');
    },
    logOut: function() {
        jQuery.post(
            Cleeng_PluginPath + 'ajax.php?cleengMode=logout',
            function(resp) {
                CleengWidget.authUser();
            }
        );
    },
    getContentInfo: function() {
            var content = [];
            var i = 0;
            jQuery('.cleeng-layer').each(function() {
                var id = jQuery(this).attr('id');
                if (id && typeof id.split('-')[2] !== 'undefined') {
                    id = id.split('-')[2];
                    content.push('content[' + i + '][id]=' + id
                                + '&content[' + i + '][postId]=' + jQuery(this).data('postId'))
                    i++;
                }
            });
            jQuery.post(
                Cleeng_PluginPath + 'ajax.php?cleengMode=getContentInfo',
                content.join('&'),
                function(resp) {                                        
                    CleengWidget.contentInfo = resp;

                    if (CleengWidget.contentInfo) {
                        jQuery.each(resp, function(k, v){
                            var layerId = '#cleeng-layer-' + k;
                            var noLayerId = '#cleeng-nolayer-' + k;
                            jQuery(layerId + ' .cleeng-price').html(v.currencySymbol + '' + v.price.toFixed(2));
                            jQuery('.cleeng-stars', layerId).attr('class', 'cleeng-stars').addClass('cleeng-stars-' + Math.round(v.averageRating));
                            jQuery('.cleeng-stars', noLayerId).attr('class', 'cleeng-stars').addClass('cleeng-stars-' + Math.round(v.averageRating));
                            if (v.purchased == true && v.content) {
                                if (v.referralProgramEnabled) {
                                    jQuery('.cleeng-referral-url', noLayerId).text(v.referralUrl).parent().hide();
                                    jQuery('a.cleeng-facebook').attr('href',
                                        'http://www.facebook.com/sharer.php?u='
                                            + encodeURI(v.referralUrl) + '&t='
                                            + encodeURI(v.shortDescription)
                                    );
                                    jQuery('a.cleeng-twitter').attr('href',
                                        'http://twitter.com/?status='
                                            + encodeURI(v.shortDescription) + ' '
                                            + encodeURI(v.referralUrl)
                                    );
                                } else {
                                    jQuery('.cleeng-referral-url', noLayerId).text(v.shortUrl);
                                    jQuery('a.cleeng-facebook').attr('href',
                                        'http://www.facebook.com/sharer.php?u='
                                            + encodeURI(v.shortUrl) + '&t='
                                            + encodeURI(v.shortDescription)

                                    );
                                    jQuery('a.cleeng-twitter').attr('href',
                                        'http://twitter.com/?status='
                                            + encodeURI(v.shortDescription) + ' '
                                            + encodeURI(v.shortUrl)
                                    );
                                }
                                if (v.canVote) {
                                    jQuery('.cleeng-vote-liked', noLayerId).show();
                                    jQuery('.cleeng-vote-didnt-like', noLayerId).show();
                                } else {
                                    jQuery('.cleeng-vote-liked', noLayerId).hide();
                                    jQuery('.cleeng-vote-didnt-like', noLayerId).hide();
                                }

                                if (v.referralProgramEnabled) {
                                    jQuery('.cleeng-referral-rate', noLayerId).show()
                                        .find('span').text(Math.round(v.referralRate*100)+'%');
                                } else {
                                    jQuery('.cleeng-referral-rate', noLayerId).hide();
                                }

                                referredIds = jQuery.cookie('cleeng_referred_ids');
                                if (referredIds) {
                                    referredIds = referredIds.split(',');
                                    if (jQuery.inArray(v.contentId, referredIds) != -1) {
                                        jQuery('.cleeng-share', noLayerId).show();
                                        jQuery('.cleeng-it', noLayerId).hide();
                                    }
                                }

                                jQuery(layerId).hide();
                                jQuery('.cleeng-content', noLayerId).html(v.content);
                                jQuery(noLayerId).show();
                                jQuery('.cleeng-shadow').hide();
                            } else {
                                jQuery(noLayerId).hide();
                                jQuery(layerId).show();
                                jQuery('.cleeng-shadow').hide();
                            }
                        });
                    }
                    CleengWidget.fixShadows();
                },
            "json"
        );
    },
    purchaseContent: function(contentId) {
        if (this.popupWindow) {
            this.popupWindow.close();
        }
        this.popupWindow = window.open(Cleeng_PluginPath + 'ajax.php?cleengMode=purchase&contentId=' + contentId + '&cleengPopup=1','CleengConfirmationPopUp', 
            'menubar=no,width=607,height=600,toolbar=no,resizable=yes');
    },
    fixShadows: function() {
        if (!jQuery.browser.msie) return;
        jQuery('.cleeng-layer:visible').each(function() {

            blurRadius = 25;
            shadowColor = '#888';

            jQuery(this).css({
                position:	"relative",
                zoom: 		1,
                zIndex:		"2"
            });

            if (jQuery(this).prev('.cleeng-shadow').length) {
                div = jQuery(this).prev('.cleeng-shadow');
            } else {
                var div=document.createElement("div");
                jQuery(div).insertBefore(jQuery(this));
            }

            jQuery(div).css("filter", "progid:DXImageTransform.Microsoft.Blur(pixelRadius="+blurRadius+", enabled='true')");

            jQuery(div).css({
                width:		jQuery(this).outerWidth(),
                height:		jQuery(this).outerHeight(),
                background:	shadowColor,
                position:	"absolute",
                zIndex:		1,
                'margin-top':   -blurRadius-1,
                'margin-left': -blurRadius-1
            }).addClass('cleeng-shadow');

        });
        
    }
}

jQuery(CleengWidget.init);