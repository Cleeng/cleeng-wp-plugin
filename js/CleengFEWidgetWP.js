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
            jQuery('#cleeng-layer-' + contentId + ' .cleeng-buy')
                .click(function() {
                    CleengWidget.purchaseContent(contentId);
                    return false;
                });
            jQuery('#cleeng-layer-' + contentId + ' .cleeng-buy-wide')
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

//        jQuery('a.cleeng-pay-with-paypal').click(function() {
//            if (this.popupWindow) {
//                this.popupWindow.close();
//                this.popupWindow = null;
//            }
//
//            var contentId = jQuery(this).parents('.cleeng-layer').attr('id').split('-')[2];
//
//            this.popupWindow = window.open(Cleeng_PluginPath + 'ajax.php?cleengMode=paypal&cleengPopup=1&content_id=' + contentId,'CleengPayPalPopUp',
//                        'menubar=no,width=607,height=600,toolbar=no,resizable=yes');
//            return false;
//        });

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
            };
        }

        // autologin
        if (typeof CleengAutologin !== 'undefined') {
            if (CleengAutologin.available) {
                jQuery.getJSON(
                    Cleeng_PluginPath+'ajax.php?cleengMode=autologin&id=' + CleengAutologin.id
                        + '&key=' + CleengAutologin.key,
                    function(resp) {
                        if (resp && resp.success) {
                            CleengWidget.getUserInfo(false);
                        }
                    }
                );
            }
        }
    },
    /**
     * Fetch information about currently authenticated user
     */
    getUserInfo: function(dontFetchContentInfo) {
        CleengWidget.showLoader();
        jQuery.getJSON(
            Cleeng_PluginPath+'ajax.php?cleengMode=getUserInfo',
            function(resp) {
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
//                jQuery('.cleeng-ajax-loader').hide();
            }
        );
    },
    /**
     * Update user information
     */
    updateUserInfo: function() {
        var user = CleengWidget.userInfo;
        
        if (!user || !user.name) {
            jQuery('.cleeng-auth-bar').hide();
            jQuery('.cleeng-noauth-bar').show();
            jQuery('.cleeng-price').hide();
            jQuery('.cleeng-auth').css('display', 'none');
            if (CleengWidget.cookie('cleeng_user_auth')
                || (typeof CleengAutologin !== 'undefined'
                    && CleengAutologin.wasLoggedIn)
            ) {
                jQuery('.cleeng-firsttime').hide();
                jQuery('.cleeng-nofirsttime').css('display', 'block');
                jQuery('.cleeng-welcome-firsttime').hide();
                jQuery('.cleeng-welcome-nofirsttime').show();
            } else {
                jQuery('.cleeng-firsttime').css('display', 'block');
                jQuery('.cleeng-nofirsttime').hide();
                jQuery('.cleeng-welcome-firsttime').show();
                jQuery('.cleeng-welcome-nofirsttime').hide();
            }
        } else {
            if (parseInt(user.freeContentViews)) {
                jQuery('.cleeng-free-content-views span').text(user.freeContentViews);
            } else {
                jQuery('.cleeng-free-content-views').hide();
            }
            jQuery('.cleeng-username').html(user.name);
            jQuery('.cleeng-auth-bar').show();
            jQuery('.cleeng-price').show();
            jQuery('.cleeng-noauth-bar').hide();
            jQuery('.cleeng-nofirsttime').hide();
            jQuery('.cleeng-firsttime').hide();
            jQuery('.cleeng-auth').css('display', 'block');
            CleengWidget.cookie('cleeng_user_auth', 1, {path: '/'});
        }
    },
    isPopupOpened: function() {
        if (!CleengWidget.popupWindow) {
            return false;
        }
        try {
            if (!CleengWidget.popupWindow.closed) {
                return true;
            } else {
                return false;
            }
        } catch (e) {
            return false;
        }
    },
    ensurePopupIsClosed: function() {
        if (CleengWidget.isPopupOpened()) {
            CleengWidget.popupWindow.close();
        }
        CleengWidget.popupWindow = false;
    },
    pollPopupWindow: function() {
        if (CleengWidget.isPopupOpened()) {
            setTimeout('CleengWidget.pollPopupWindow()', 250);
            return;
        } else {
            CleengWidget.getUserInfo();
        }
    },
    logIn: function() {
        CleengWidget.ensurePopupIsClosed();
        CleengWidget.popupWindow = window.open(Cleeng_PluginPath + 'ajax.php?cleengMode=auth&cleengPopup=1','CleengConfirmationPopUp',
                    'menubar=no,width=607,height=600,toolbar=no,resizable=yes');
        CleengWidget.pollPopupWindow();
    },
    purchaseContent: function(contentId) {
        CleengWidget.ensurePopupIsClosed();
        this.popupWindow = window.open(Cleeng_PluginPath + 'ajax.php?cleengMode=purchase&contentId=' + contentId + '&cleengPopup=1','CleengConfirmationPopUp',
            'menubar=no,width=607,height=600,toolbar=no,resizable=yes');
        CleengWidget.pollPopupWindow();
    },
    subscribe: function(publisherId) {
        CleengWidget.ensurePopupIsClosed();
        this.popupWindow = window.open(Cleeng_PluginPath + 'ajax.php?cleengMode=subscribe&contentId=' + publisherId + '&cleengPopup=1','CleengConfirmationPopUp',
                    'menubar=no,width=607,height=600,toolbar=no,resizable=yes');
        CleengWidget.pollPopupWindow();
    },
    logOut: function() {
        CleengWidget.showLoader();
        jQuery.post(
            Cleeng_PluginPath + 'ajax.php?cleengMode=logout',
            function(resp) {
                CleengWidget.getUserInfo();
            }
        );
    },
    getContentInfo: function(callbackFunction) {
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
                    shortUrl = v.referralUrl;
                } else {
                    shortUrl = v.shortUrl;
                }

                CleengWidget.updateBottomBar(v);

                jQuery('.cleeng-referral-url', noLayerId).text(shortUrl);
                jQuery(layerId).hide();
                jQuery('.cleeng-content', noLayerId).html(v.content);
                jQuery(noLayerId).show();
            } else {
                jQuery(layerId).prev('.cleeng-prompt').show();
                jQuery(noLayerId).hide();
                jQuery(layerId).show();
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
            jQuery('<div/>').addClass('cleeng-overlay').width(jQuery(this).width()).height(jQuery(this).height()).css('position','absolute').css('background-color', 'white').prependTo(this).css('z-index', 1000).fadeTo(0,0.6);
        });
        jQuery('.cleeng-ajax-loader').show();
        CleengWidget.loaderVisile = true;
    },

    hideLoader: function() {
        jQuery('.cleeng-overlay').remove();
        jQuery('.cleeng-ajax-loader').hide();
        CleengWidget.loaderVisile = false;
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