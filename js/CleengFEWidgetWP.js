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
    popupWindow: false,
    userInfo: {},
    contentInfo: {},
    loaderVisile: false,

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
            jQuery('#cleeng-layer-' + contentId + ' .cleeng-subscribe')
                .click(function() {
                    CleengWidget.subscribe(contentId);
                    return false;
                });
            jQuery('#cleeng-layer-' + contentId + ' .cleeng-button').not('.cleeng-subscribe')
                .click(function() {
                    CleengWidget.purchaseContent(contentId);
                    return false;
                });
            jQuery('#cleeng-nolayer-' + contentId + ' .cleeng-vote-liked').click(function() {
                CleengClient.vote(contentId, 1, function() {
                    CleengWidget.getContentInfo();
                });
                return false;
            });
            jQuery('#cleeng-nolayer-' + contentId + ' .cleeng-vote-didnt-like').click(function() {
                CleengClient.vote(contentId, 0, function() {
                    CleengWidget.getContentInfo();
                });
                return false;
            });
        });

        jQuery('a.cleeng-facebook, a.cleeng-twitter').click(function() {
            if (jQuery(this).hasClass('cleeng-twitter')) {
                width = 1110;
                height = 650;
            } else {
                width = 600;
                height = 400;
            }
            window.open(jQuery(this).attr('href'), 'shareWindow', 'menubar=no,width='
                + width + ',height=' + height + ',toolbar=no,resizable=yes');
            return false;
        });

        jQuery('a.cleeng-pay-with-paypal').each(function() {
            var contentId = jQuery(this).attr('id').split('-')[2];
            jQuery(this).attr('href', Cleeng_PluginPath + 'ajax.php?cleengMode=paypal&cleengPopup=1&content_id=' + contentId);
            dg = new PAYPAL.apps.DGFlow({
                trigger: jQuery(this).attr('id')
            });
        });

        CleengWidget.updateUserInfo();
        if (CleengWidget.contentInfo) {
            for (i in CleengWidget.contentInfo) {
                CleengWidget.updateBottomBar(CleengWidget.contentInfo[i]);
            }
        }

        // clipboard
        ZeroClipboard.setMoviePath(Cleeng_PluginPath + '/js/ZeroClipboard.swf');        
        jQuery('.cleeng-copy').each(function() {            
            clip = new ZeroClipboard.Client();
            clip.setHandCursor(true);
            clip.addEventListener('onMouseOver', function(client) {
                var text = jQuery.trim(jQuery('#' + client.movieId).parent().prev().text());
                client.setText(text);
            });
            clip.addEventListener('onComplete', function(client) {
                jQuery('#' + client.movieId).parent().prev().addClass('cleeng-copied');
            });
            jQuery(this).html(clip.getHTML(23, 22));
        });

        // PayPal
        if (typeof PAYPAL !== 'undefined' && typeof PAYPAL.apps.DGFlow !== 'undefined') {
            
            var oldFunction = PAYPAL.apps.DGFlow.prototype._buildDOM;

            PAYPAL.apps.DGFlow.prototype._buildDOM = function() {                
                oldFunction.apply(this);
                var loader = jQuery('<div>');
                loader.css({
                    width: '42px',
                    height: '42px',
                    display: 'block',
                    position: 'absolute',
                    padding: '15px',
                    borderRadius: '4px',
                    backgroundColor: 'white',
                    top: ( jQuery(window).height() - loader.height() ) / 2+jQuery(window).scrollTop() + "px",
                    left: ( jQuery(window).width() - loader.width() ) / 2+jQuery(window).scrollLeft() + "px",
                    zIndex: 9999
                });
                loader.append(jQuery('<img src="https://www.sandbox.paypal.com/en_US/i/icon/icon_animated_prog_42wx42h.gif" alt=""/>'));
                jQuery('#PPDGFrame').prepend(loader);
                CleengClient.setPopupCallback(function() {
                    CleengWidget.getUserInfo();
                });
                CleengClient.pollPayPalIframe();
            };
        }



        if (!CleengClient.isUserAuthenticated()) {
            CleengClient.autologin(function(resp) {
                if (resp && typeof resp.token !== 'undefined' && resp.token) {
                    CleengWidget.getUserInfo();
                }
            });
        }
        jQuery('.cleeng-whatsCleeng').click(function() {
            CleengClient.showAboutScreen();
            return false;
        });
    },
    /**
     * Fetch information about currently authenticated user
     */
    getUserInfo: function(dontFetchContentInfo) {
        
        CleengWidget.showLoader();
        CleengClient.getUserInfo(function(resp) {
            CleengWidget.userInfo = resp;
            if (!dontFetchContentInfo) {
                CleengWidget.getContentInfo(function() {
                    CleengWidget.updateUserInfo();
                    CleengWidget.hideLoader();
                });
            } else {
                CleengWidget.updateUserInfo();
                CleengWidget.hideLoader();
            }
            jQuery('.cleeng-once').hide();
        });
    },
    hasCookie: function(){
        var split = document.cookie.split(';');

        for(var i = 0; i < split.length; i++) {
            var name_value = split[i].split("=");
            if ( jQuery.trim(name_value[0]) == 'cleeng_user_auth' ) {
                return true;
            }
        }
        return false;        
    },
    /**
     * Update user information
     */
    updateUserInfo: function() {
        var user = CleengWidget.userInfo;

        if (!user || !user.name) {
            jQuery('.cleeng-prompt .cleeng-auth').hide();
            if (CleengWidget.hasCookie()) {
                jQuery('.cleeng-prompt .cleeng-firsttime').hide();
                jQuery('.cleeng-prompt .cleeng-nofirsttime').show();
            } else {
                jQuery('.cleeng-prompt .cleeng-firsttime').show();
                jQuery('.cleeng-prompt .cleeng-nofirsttime').hide();
            }
            jQuery('.cleeng-auth-bar').hide();
            jQuery('.cleeng-noauth-bar').show();          
            var hasCookie = CleengWidget.hasCookie();
        } else {
            jQuery('.cleeng-prompt .cleeng-auth').show();
            jQuery('.cleeng-prompt .cleeng-firsttime').hide();
            jQuery('.cleeng-prompt .cleeng-nofirsttime').hide();
            jQuery('.cleeng-auth-bar').show();
            jQuery('.cleeng-noauth-bar').hide();
            jQuery('.cleeng-username').text(user.name);

            CleengWidget.cookie('cleeng_user_auth', 1, {path: '/'});
            var hasCookie = true;
        }        

        jQuery('.cleeng-once').remove();
        for (var first in CleengWidget.contentInfo) {
            var info = CleengWidget.contentInfo[first];

            if (info.price == 0 || (user && user.name && parseInt(info.freeContentViews.remained) > 0 && parseFloat(info.price) <= parseFloat(info.freeContentViews.maxPrice))) {
                var object = null;
                if (hasCookie) {
                    jQuery('.by-free-'+info.contentId).css('display','none');
                    if (info.itemType == 'article') {
                        object = jQuery('.read-for-free-'+info.contentId);
                    } else if (info.itemType == 'video') {
                        object = jQuery('.watch-for-free-'+info.contentId);
                    } else {
                        object = jQuery('.access-for-free-'+info.contentId);
                    }
                    jQuery('.cleeng-button', '#cleeng-layer-' + info.contentId).not(object).not('.cleeng-subscribe').hide();
                    object.css('display','block');
                } 
            } else if (hasCookie) {
                var object = null;

                if (parseInt(info.freeContentViews.remained) > 0 && parseFloat(info.price) <= parseFloat(info.freeContentViews.maxPrice)) {
                    jQuery('.by-free-'+info.contentId).css('display','none');
                    if (info.itemType == 'article') {
                        object = jQuery('.register-and-read-for-free-'+info.contentId);
                    } else if (info.itemType == 'video') {
                        object = jQuery('.register-and-watch-for-free-'+info.contentId);
                    } else {
                        object = jQuery('.register-and-access-for-free-'+info.contentId);
                    }
                    jQuery('.cleeng-button', '#cleeng-layer-' + info.contentId).not(object).not('.cleeng-subscribe').hide();
                    object.css('display','block');
                    
                } else {
                    if (info.itemType == 'article') {
                        object = jQuery('.buy-this-article-'+info.contentId);
                    } else if (info.itemType == 'video') {
                        object = jQuery('.buy-this-video-'+info.contentId);
                    } else {
                        object = jQuery('.buy-this-item-'+info.contentId);
                    }
                    jQuery('.cleeng-button', '#cleeng-layer-' + info.contentId).not(object).not('.cleeng-subscribe').hide();
                    object.show();
                }

            } 

            if (user && user.name && user.id == info.publisherId) {
                jQuery('<span class="cleeng-once" style="color:red">This article is revealed because you are logged in as its publisher.</span>')
                    .insertBefore('#cleeng-layer-' + info.contentId);
            }
        }
    },
    logIn: function() {
        CleengClient.logIn(function(result) {
            CleengWidget.getUserInfo();
            if (result && typeof result.showMessage !== 'undefined' && result.showMessage) {
                CleengWidget.showMessage(contentId, result.messageHeader, result.messageBody, result.messageImage, result.messageDuration);
            }
        });
    },
    purchaseContent: function(contentId) {
        CleengClient.purchaseContent(contentId, function(result) {
            CleengWidget.getUserInfo();
            if (result && typeof result.showMessage !== 'undefined' && result.showMessage) {
                CleengWidget.showMessage(contentId, result.messageHeader, result.messageBody, result.messageImage, result.messageDuration);
            }
        });
    },
    subscribe: function(contentId) {
        CleengClient.subscribe(CleengWidget.contentInfo[contentId].publisherId, function(result) {
            CleengWidget.getUserInfo();
            if (result && typeof result.showMessage !== 'undefined' && result.showMessage) {
                CleengWidget.showMessage(contentId, result.messageHeader, result.messageBody, result.messageImage, result.messageDuration);
            }
        });
    },
    logOut: function() {
        CleengWidget.showLoader();
        CleengClient.logOut(function() {
            CleengWidget.getUserInfo();
        });
    },
    getContentInfo: function(callbackFunction) {
            var content = [];
            var i = 0;
            jQuery('.cleeng-layer').each(function() {
                var id = jQuery(this).attr('id');
                if (id && typeof id.split('-')[2] !== 'undefined') {
                    id = id.split('-')[2];
                    var postId = jQuery('#cleeng-'+id).attr('rel');
                    content.push('content[' + i + '][id]=' + id
                                + '&content[' + i + '][postId]=' + postId)
                    i++;
                }
            });
            jQuery.post(
                Cleeng_PluginPath + 'ajax.php?cleengMode=getContentInfo',
                content.join('&'),
                function(resp) {
                    CleengWidget.contentInfo = resp;
                    CleengWidget.updateContentInfo();

                    if (typeof callbackFunction !== 'undefined') {
                        callbackFunction();
                    }

                },
            "json"
        );
    },
    updateContentInfo: function() {
        jQuery.each(CleengWidget.contentInfo, function(k, v){
            var layerId = '#cleeng-layer-' + k;
            var noLayerId = '#cleeng-nolayer-' + k;
            jQuery(layerId + ' .cleeng-price').html(v.currencySymbol + '' + v.price.toFixed(2));
            jQuery('.cleeng-stars', jQuery(layerId)).attr('class', 'cleeng-stars').addClass('cleeng-stars-' + Math.round(v.averageRating));
            jQuery('.cleeng-stars', jQuery(noLayerId)).attr('class', 'cleeng-stars').addClass('cleeng-stars-' + Math.round(v.averageRating));
            if (v.purchased == true && v.content) {
                jQuery(layerId).prev('.cleeng-prompt').hide();
                if (v.canVote) {
                    jQuery('.cleeng-rate', noLayerId).show();
                    jQuery('.cleeng-rating', noLayerId).hide();
                } else {
                    jQuery('.cleeng-rate', noLayerId).hide();
                    jQuery('.cleeng-rating', noLayerId).show();
                }

                if (v.referralProgramEnabled) {
                    jQuery('.cleeng-referral-rate', noLayerId).show()
                        .find('span').text(Math.round(v.referralRate*100)+'%');
                } else {
                    jQuery('.cleeng-referral-rate', noLayerId).hide();
                }

                if (v.referralUrl) {
                    var shortUrl = v.referralUrl;
                } else {
                    var shortUrl = v.shortUrl;
                }

                CleengWidget.updateBottomBar(v);

                jQuery('.cleeng-referral-url', noLayerId).text(shortUrl);
                jQuery(layerId).hide();
                jQuery('.cleeng-content', noLayerId).html(v.content);
                jQuery(noLayerId).show();

                if (jQuery('#cleeng_tip_content', layerId).length) {
                    jQuery('#cleeng_tip_content', layerId).appendTo(noLayerId);
                }
            } else {

                if (v.subscriptionOffer) {
                    jQuery('.cleeng-button', jQuery(layerId)).removeClass('cleeng-middle');
                    if (v.subscriptionPrompt) {
                        jQuery('.cleeng-subscribe', jQuery(layerId)).text(v.subscriptionPrompt);
                    }
                    jQuery('.cleeng-subscribe', jQuery(layerId)).show();
                } else {
                    jQuery('.cleeng-button', jQuery(layerId)).addClass('cleeng-middle');
                    jQuery('.cleeng-subscribe', jQuery(layerId)).hide();
                }

                jQuery(layerId).prev('.cleeng-prompt').show();
                jQuery(noLayerId).hide();
                jQuery(layerId).show();

                if (jQuery('#cleeng_tip_content', noLayerId).length) {
                    jQuery('#cleeng_tip_content', noLayerId).appendTo(layerId);
                }
            }
        });
    },
    updateBottomBar: function(content) {
        var layerId = '#cleeng-layer-' + content.contentId;
        var noLayerId = '#cleeng-nolayer-' + content.contentId;

        if (content.referralUrl) {
            var shortUrl = content.referralUrl;
        } else {
            var shortUrl = content.shortUrl;
        }
        var shortDescription = jQuery.trim(jQuery('.cleeng-description', jQuery(layerId)).text()).substring(0, 30);
        var subject = CleengWidget.userInfo.name
                    + ' shares with you ' + shortDescription;
        var bbody = 'Hi,\n\nI wanted to share this ' + content.itemType
                 + ' with you.\n\n'
                 + 'Click here to access it: ' + shortUrl
                 + '\n\nHave a look!\n\n' + CleengWidget.userInfo.name;
        jQuery('.cleeng-email', noLayerId)
            .attr('href', 'mailto:?subject=' + encodeURI(subject) + '&body=' + encodeURI(bbody));
        jQuery('.cleeng-referral-url', jQuery(noLayerId)).text(shortUrl);
        jQuery('a.cleeng-facebook', jQuery(noLayerId)).attr('href',
            'http://www.facebook.com/sharer.php?u='
                + encodeURI(shortUrl) + '&t='
                + encodeURI('Check this ' + content.itemType + '!\n' + content.shortDescription)
        );
        jQuery('a.cleeng-twitter', jQuery(noLayerId)).attr('href',
            'http://twitter.com/?status='
                + content.shortDescription + ' '
                + shortUrl
        );
    },

    showLoader: function() {
        if (CleengWidget.loaderVisile) {
            return;
        }
        CleengWidget.overlay = [];
        jQuery('.cleeng-layer, .cleeng-nolayer').each(function() {
            if (!jQuery(this).is(':visible')) {
                return;
            }
            jQuery('<div/>').addClass('cleeng-overlay')
                .width(jQuery(this).width())
                .height(jQuery(this).height())
                .css('position','absolute')
                .css('background-color', 'white')
                .prependTo(this)
                .css('z-index', 1000).fadeTo(0,0.6);
        });
        jQuery('.cleeng-ajax-loader').show();
        CleengWidget.loaderVisile = true;
    },

    hideLoader: function() {
        jQuery('.cleeng-overlay').remove();
        jQuery('.cleeng-ajax-loader').hide();
        CleengWidget.loaderVisile = false;
    },

    showMessage: function(contentId, header, message, image, duration) {
        if (jQuery('#cleeng-layer-' + contentId).is(':visible')) {
            var element = jQuery('#cleeng-layer-' + contentId);
        } else {
            var element = jQuery('#cleeng-nolayer-' + contentId);
        }

        if (!jQuery('#cleeng_tip_content').length) {
            var overlay = jQuery('<div>');
            overlay.attr('id', 'cleeng_tip_content')
                    .css('display', 'none')
                   .click(function() {jQuery(this).hide();});
        } else {
            var overlay = jQuery('#cleeng_tip_content');
        }

        var html = '<img src="' + image + '" />'
                 + '<div class="cleeng-message-header">' + header + '</div>'
                 + '<div class="cleeng-message-body">' + message + '</div>';

        overlay.html(html).appendTo(element)
            .css({top: 30, left: element.width() / 2 - 450 / 2});
        overlay.fadeIn('fast').delay(parseInt(duration)*1000).fadeOut('slow');
    },

    /**
    * jQuery Cookie plugin (moved to CleengWidget namespace to prevent conflicts)
    *
    * Copyright (c) 2010 Klaus Hartl (stilbuero.de)
    * Dual licensed under the MIT and GPL licenses:
    * http://www.opensource.org/licenses/mit-license.php
    * http://www.gnu.org/licenses/gpl.html
    *
    */
    cookie: function (key, value, options) {

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
    }

}