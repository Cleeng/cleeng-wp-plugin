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
 * Backend JS library
 */

var CleengWidget = {
    // New Content Form pop-up
    newContentForm : {content: {}},
    editorBookmark : {},
    popupWindow: null,
    saveContentServiceURL : Cleeng_PluginPath+'ajax.php?backendWidget=true&cleengMode=saveContent',
    tempId: 1,
    appSecureKey: null,
    contentIds: {},
    protection: null,

    sliderToPrice: [
        0, 0.14, 0.19, 0.24, 0.29, 0.34, 0.39, 0.44, 0.49, 0.54, 0.59, 0.69, 0.79, 0.89, 0.99,
        1.24, 1.49, 1.74, 1.99, 2.24, 2.49, 2.74, 2.99, 3.24, 3.49, 3.99, 4.49, 4.99, 5.49, 5.99,
        6.49, 6.99, 7.49, 7.99, 8.49, 8.99, 9.49, 9.99, 10.49, 10.99, 
	11.99, 12.99, 13.99, 14.99, 15.99, 16.99, 17.99, 18.99, 19.99,
	24.95, 29.95, 34.95, 39.95, 44.95, 49.95, 54.95, 59.95, 64.95, 69.95, 74.95, 79.95, 84.95, 89.95,  94.95, 99.95
    ],    

    teserInputWatcher: function() {
        var desc = jQuery('#cleeng-ContentForm-Description');
        if (desc.val().length > 110) {
            desc.val(desc.val().substring(0, 110));
        }
        jQuery('#cleeng-ContentForm-DescriptionCharsLeft').html(110 - desc.val().length);
    },
    toggleHasLayerDates: function() {
        if (jQuery('#cleeng-ContentForm-LayerDatesEnabled:checked').length) {
            var disabled = false;
        } else {
            var disabled = 'disabled';
        }
        jQuery('#cleeng-ContentForm-LayerStartDate, #cleeng-ContentForm-LayerEndDate')
            .attr('disabled', disabled)
            .datepicker("option", "disabled", disabled);
    },
    toggleReferralProgramEnabled: function() {
        if (jQuery('#cleeng-ContentForm-ReferralProgramEnabled:checked').length) {
            jQuery('#cleeng-ContentForm-ReferralRateSlider').slider("enable");
        } else {
            jQuery('#cleeng-ContentForm-ReferralRateSlider').slider("disable");
        }
    },
    init: function(){

        jQuery(document).ajaxError(function(e, xhr, settings, exception) {
                console.log(e);
                console.log(xhr);
                console.log(settings);
                console.log(exception);
          if (settings.url == CleengWidget.saveContentServiceURL) {
            jQuery(this).text(e.toString());
          }
        });
        
        CleengWidget.getUserInfo();
       
        
        jQuery('#cleeng-login').click(function() {
            CleengWidget.openLoginWindow();
            return false;
        });
        jQuery('#cleeng-logout, #cleeng-logout2').click(function() {
            CleengClient.logOut(function() {
                CleengWidget.getUserInfo();
            });
            return false;
        });
        jQuery('#cleeng-register-publisher, #cleeng-register-publisher2').click(function() {
            CleengClient.registerAsPublisher(CleengWidget.appSecureKey, function(resp) {
                if (resp.token) {
                    jQuery.post(
                        Cleeng_PluginPath + 'ajax.php?cleengMode=savePublisherToken&token=' + encodeURIComponent(resp.token)
                    );
                    CleengWidget.getUserInfo();
                }
            });
            return false;
        });

        if (jQuery('#cleeng-contentForm').length) {
            jQuery('#cleeng-ContentForm-Description').bind('keyup', CleengWidget.teserInputWatcher);

            jQuery('#cleeng-ContentForm-LayerDatesEnabled').click(CleengWidget.toggleHasLayerDates);
            jQuery('#cleeng-ContentForm-ReferralProgramEnabled').click(CleengWidget.toggleReferralProgramEnabled);
            jQuery('#cleeng-createContent')
                .click(function(e) {
                    CleengWidget.createCleengContent();
                    e.stopPropagation();
                    return false;
                }).hover(
                    function(){
                        jQuery(this).addClass('ui-state-hover');
                    },
                    function(){
                        jQuery(this).removeClass('ui-state-hover');
                    }
                ).mousedown(function(){
                    jQuery(this).parents('.fg-buttonset-single:first').find('.fg-button.ui-state-active').removeClass('ui-state-active');
                    if( jQuery(this).is('.ui-state-active.fg-button-toggleable, .fg-buttonset-multi .ui-state-active') ){jQuery(this).removeClass('ui-state-active');}
                    else {jQuery(this).addClass('ui-state-active');}
                }).mouseup(function(){
                    if(! jQuery(this).is('.fg-button-toggleable, .fg-buttonset-single .fg-button,  .fg-buttonset-multi .fg-button') ){
                            jQuery(this).removeClass('ui-state-active');
                    }
                });

            CleengWidget.newContentForm = jQuery('#cleeng-contentForm').dialog({
                autoOpen: false,
                height: 380,
                width: 400,
                closeOnEscape: true,
                modal: true,
                buttons: {
                    'Save markers' : CleengWidget.newContentFormRegisterContent,
                    'Cancel' : function() {
                        jQuery(this).dialog('close');
                    }
                }
            });

            jQuery('#cleeng-ContentForm-PriceSlider').slider({
                animate:false,
                min:0,
                max:66,
                step:1,
                slide: function(event, ui) {
                    jQuery('#cleeng-ContentForm-PriceValue').html(CleengWidget.sliderToPrice[ui.value].toFixed(2));                    
                }
            });
            jQuery('#cleeng-ContentForm-ReferralRateSlider').slider({
                animate:false,
                min:5,
                max:50,
                step:1,
                slide: function(event, ui) {
                    jQuery('#cleeng-ContentForm-ReferralRateValue').html(ui.value);
                }
            });

            jQuery('#cleeng-ContentList a').live('click', function() {
                if (jQuery(this).hasClass('cleeng-editContentLink')) {
                    var id = jQuery.trim(jQuery(this).parents('li').find('span.cleeng-contentId').text());
                    id = id.replace(/\./g, '');
                    CleengWidget.editContent(id);
                } else if (jQuery(this).hasClass('cleeng-removeContentLink')) {
                    var id = jQuery.trim(jQuery(this).parents('li').find('span.cleeng-contentId').text());
                    id = id.replace(/\./g, '');
                    CleengWidget.removeMarkers(id);
                }
                return false;
            });
        }
        setTimeout(function() {
            CleengWidget.findContent();
        }, 1000);

        jQuery('a.cleeng-post').click(function(){
            var contentId = jQuery(this).attr('id').split('-')[2];
            var isCleengContent = jQuery(this).hasClass('cleeng-on')?1:0;

            CleengWidget.setContent(contentId, isCleengContent);
        });


        CleengWidget.setHasDefaultSetup();
        
    },
    setUpPluginDescription: function() {
        
        if (jQuery('#cleeng-for-wordpress .plugin-description a').length) {
            if (CleengWidget.userInfo == null || (CleengWidget.userInfo && CleengWidget.userInfo.accountType != 'publisher')) {
                jQuery('#cleeng-for-wordpress .plugin-description a').click(function(){
                    CleengWidget.openPublisherRegistrationWindow();
                    return false;
                });                                 
            } else if(CleengWidget.userInfo.accountType == 'publisher') {
                var foundin = jQuery('#cleeng-for-wordpress .plugin-description:contains("Activate your account")');
                foundin.html('<p>'+foundin.text()+'</p>')
            }
        }
    },
    openLoginWindow: function() {
        CleengClient.publisherLogIn(CleengWidget.appSecureKey, function(resp) {
            if (resp.token) {
                jQuery.post(
                    Cleeng_PluginPath + 'ajax.php?cleengMode=savePublisherToken&token=' + encodeURIComponent(resp.token)
                );
                CleengWidget.getUserInfo();
            }
        });
    },
    setUpCleengOptions: function() {

        var select = jQuery('#cleeng-options');
        jQuery(select).insertBefore('.tablenav-pages');
        select.show();

        jQuery('#cleeng-options').change(function(){
            CleengWidget.setContents();
        });

    },
    setContents : function() {
        var toCleengContent = jQuery('#cleeng-options select').val();

        CleengWidget.contentIds = CleengWidget.getSelectedIds();
        CleengWidget.protection = toCleengContent;
        if (CleengWidget.isPosibilityToProtect() == false) {
            return false;
        }

        if (CleengWidget.protection != 99) {
            CleengClient.getContentDefaultConditions(function(resp) {
                
                if (resp) {
                    jQuery('#cleeng-option-loader').show();

                    if(CleengWidget.getSelectedIds().length != 0) {
                        jQuery.getJSON(
                            Cleeng_PluginPath+'ajax-set-content.php?contentIds='+CleengWidget.getSelectedIds()+'&protection='+CleengWidget.protection,
                            function(resp) {
                                window.location.reload();
                                return true;
                            }
                        );
                        return true;
                    } else {
                         jQuery( "#cleeng-message-no-selected" ).dialog({
                            modal: true,
                            minWidth: 350,
                            buttons: {
                                Ok: function() {
                                    jQuery( this ).dialog( "close" );
                                }
                            }
                        });

                        jQuery('#cleeng-option-loader').hide();
                    }
                } else {
                    jQuery( "#cleeng-message-no-default-setup" ).dialog({
                        modal: true,
                        minWidth: 350,
                        buttons: {
                            'Set default settings': function() {
                                window.open(CleengClient.getUrl()+'/my-account/settings/single-item-sales/1/anchor/1','mywindow','width=400,height=200,toolbar=yes,location=yes,directories=yes,status=yes,menubar=yes,scrollbars=yes,copyhistory=yes, resizable=yes')
                                jQuery( this ).dialog( "close" );
                            }
                        }
                    });
                    jQuery('button').addClass("ui-button ui-widget ui-state-default ui-corner-all ui-button-text-only");                         
                }
            });

        }
    },
    getSelectedIds: function() {

        var selected = jQuery("input[name='post[]']:checked");

        var object = new Array();
        for(var i in selected){
            var input = selected[i];
            if (input.value != undefined) {
                object.push(input.value);
            }
        }
        return object;
    },
    openPublisherRegistrationWindow : function() {
        
        CleengClient.registerAsPublisher(CleengWidget.appSecureKey, function(resp) {
            if (resp.token) {
                jQuery.post(
                    Cleeng_PluginPath + 'ajax.php?cleengMode=savePublisherToken&token=' + encodeURIComponent(resp.token)
                );
                CleengWidget.getUserInfo();
            }
        });
        return false;        
    },
    isPosibilityToProtect : function() {
        
        if (CleengWidget.userInfo == null) {
            CleengWidget.openLoginWindow();
            return false;
        }
        if( CleengWidget.userInfo.accountType == 'customer' ) {
            CleengWidget.openPublisherRegistrationWindow();
            return false;
        }

        
        if (!CleengWidget.hasDefaultSetup()) {
            jQuery( "#cleeng-message-no-default-setup" ).dialog({
                modal: true,
                minWidth: 350,
                buttons: {
                    'Set default settings': function() {
			window.open(CleengClient.getUrl()+'/my-account/settings/single-item-sales/1/anchor/1','mywindow','width=1024,height=800,toolbar=yes,location=yes,directories=yes,status=yes,menubar=yes,scrollbars=yes,copyhistory=yes, resizable=yes')
                        jQuery( this ).dialog( "close" );
                    }
                }
            });
            jQuery('button').addClass("ui-button ui-widget ui-state-default ui-corner-all ui-button-text-only");
            return false;
        }
        return true;
    },
    setContent: function(contentId, isCleengContent) {

        CleengWidget.contentIds = [contentId];
        
        if (isCleengContent == 1) {
            CleengWidget.protection = 'remove-protection';
        } else {
            CleengWidget.protection = 'add-protection';
        }
                
        if (CleengWidget.isPosibilityToProtect() == false) {
            return false;
        }
        
        CleengClient.getContentDefaultConditions(function(resp) {
            if (resp) {
                var c = jQuery('a#cleeng-post-'+contentId);
                c.attr('class','cleeng-loader');
                jQuery.getJSON(
                    Cleeng_PluginPath+'ajax-set-content.php?contentId='+contentId+'&protection='+CleengWidget.protection,
                    function(resp) {

                        c.attr('class','cleeng-post cleengit cleeng-'+resp.protecting) ;
                        if (resp.protecting == 'on') {
                            c.attr('title',resp.info.symbol+resp.info.price+ "\n"+resp.info.shortDescription);
                        } else {
                            c.attr('title','Protect it!');
                        }

                        return true;
                    }
                );
            } else {
                jQuery( "#cleeng-message-no-default-setup" ).dialog({
                    modal: true,
                    minWidth: 350,
                    buttons: {
                        'Set default settings': function() {
                            window.open(CleengClient.getUrl()+'/my-account/settings/single-item-sales/1/anchor/1','mywindow','width=400,height=200,toolbar=yes,location=yes,directories=yes,status=yes,menubar=yes,scrollbars=yes,copyhistory=yes, resizable=yes')
                            jQuery( this ).dialog( "close" );
                        }
                    }
                });
                jQuery('button').addClass("ui-button ui-widget ui-state-default ui-corner-all ui-button-text-only");                
            }
        });

    },
    showContentFormWithDefaultParams: function() {
        var user = CleengWidget.userInfo;
        if (user) {
        
        if (CleengWidget.isPosibilityToProtect() == false) {
            return false;
        }

        var c = jQuery('a#cleeng-post-'+contentId);
        c.attr('class','cleeng-loader');
        jQuery.getJSON(
            Cleeng_PluginPath+'ajax-set-content.php?contentId='+contentId+'&protection='+CleengWidget.protection,
            function(resp) {

                if ( CleengWidget.hasDefaultSetup() ) {

                    c.attr('class','cleeng-post cleengit cleeng-'+resp.protecting) ;
                    if (resp.protecting == 'on') {
                        c.attr('title',resp.info.symbol+resp.info.price+ "\n"+resp.info.shortDescription);
                    } else {
                        c.attr('title','Protect it!');
                    }

                } else {
                    c.attr('class','cleeng-post cleengit') ;
                }
                return true;
            }
        );
        }
    },
    setHasDefaultSetup: function() {

        //if(CleengWidget.getCookie('hasDefaultSetup') == undefined) {

            CleengClient.getContentDefaultConditions(function(resp){
                if(resp == null){
                    CleengWidget.setCookie('hasDefaultSetup', '0', 1);
                } else {
                    CleengWidget.setCookie('hasDefaultSetup', '1', 1);
                }
            });
        //}

    },
    hasDefaultSetup : function() {
        if(CleengWidget.getCookie('hasDefaultSetup') == undefined) {
            CleengWidget.setHasDefaultSetup();
            setTimeout(function() {
                CleengWidget.hasDefaultSetup();
            }, 1000);
        } else {
            return CleengWidget.getCookie('hasDefaultSetup')==1?true:false;
        }
    },
    showContentFormWithDefaultParams: function() {
        var user = CleengWidget.userInfo;
        if (user) {

          CleengClient.getContentDefaultConditions(function(resp){
              if(resp == null){
                  CleengWidget.showContentForm({});
              } else {
                  var ret = new Object();
                  ret['defaultArticleCoverage'] = resp.defaultArticleCoverage;
                  ret['shortDescription'] = resp.itemDescription;
                  ret['price'] = resp.itemPrice;
                  ret['referralRate'] = resp.referralProgram/100;
                  ret['userId'] = resp.userId;
                  if (resp.referralProgram !=0){
                    ret['referralProgramEnabled'] = 1;
                  }
                  CleengWidget.showContentForm(ret);
              }
         });

        }
    },
    setCookie : function (c_name,value,exdays)
    {
        var exdate=new Date();
        exdate.setDate(exdate.getDate() + exdays);
        var c_value=escape(value) + ((exdays==null) ? "" : "; expires="+exdate.toUTCString());
        document.cookie=c_name + "=" + c_value;
    },
    getCookie : function (c_name)
    {
        var i,x,y,ARRcookies=document.cookie.split(";");
        for (i=0;i<ARRcookies.length;i++)
        {
            x=ARRcookies[i].substr(0,ARRcookies[i].indexOf("="));
            y=ARRcookies[i].substr(ARRcookies[i].indexOf("=")+1);
            x=x.replace(/^\s+|\s+$/g,"");
            if (x==c_name)
            {
                return unescape(y);
            }
        }
    },
    getUserInfo: function() {
        CleengClient.getUserInfo(function(resp) {
            CleengWidget.setUpCleengOptions();
            CleengWidget.userInfo = resp;
            CleengWidget.updateUserInfo();
            CleengWidget.setUpPluginDescription();
        });
    },
    updateUserInfo: function() {
        var user = CleengWidget.userInfo;
        if (jQuery('#cleeng-dashboard-login').length) {

        if (!user || !user.name || !user.publisherAccess) {
            jQuery('#cleeng-connecting').hide();
            jQuery('#cleeng-dashboard-login').show();
            jQuery('#cleeng-dashboard-content').hide();

        } else {
          jQuery('#cleeng-dashboard-login').hide();
          jQuery('#cleeng-connecting').show();  
          CleengClient.getPurchaseSummary(function(resp) {
                var resp = resp.purchaseSummary;
                var data = [
                   {earnings: resp.earnings,  balance: resp.balance, purchases: resp.purchases, conversions: resp.conversions, impressions: resp.impressions}
                ];
                jQuery('#cleeng-connecting').hide();
                jQuery("#cleeng-dashboard-content-template").tmpl(data).appendTo("#cleeng-dashboard-content");

                jQuery('#cleeng-dashboard-login').hide();
                jQuery('#cleeng-dashboard-content').show();
          });

        }


        } else {
            jQuery('#cleeng-connecting').hide();
            if (!user || !user.name || !user.publisherAccess) {
                jQuery('#cleeng-logout').parent().hide();
                jQuery('#cleeng-login').parent().show();
                jQuery('#cleeng-auth-options').hide();
                jQuery('#cleeng-notPublisher').hide();
                jQuery('.cleeng-auth').hide();
                jQuery('.cleeng-noauth').show();
            } else {
                jQuery('#cleeng-login').parent().hide();
                jQuery('#cleeng-logout').parent().show();
                jQuery('.cleeng-currency-symbol').html(user.currencySymbol);
                jQuery('#cleeng-username').html(user.name);
                if (user.accountType != 'publisher') {
                    jQuery('#cleeng-notPublisher').show();
                    jQuery('#cleeng-auth-options').hide();
                } else {
                    jQuery('#cleeng-notPublisher').hide();
                    jQuery('#cleeng-auth-options').show();
                }
                jQuery('.cleeng-auth').show();
                jQuery('.cleeng-noauth').hide();
            }
        }

    },
    showContentForm: function(content) {
        content.contentId = typeof content.contentId === 'undefined' ? 0 : content.contentId;
        content.price = typeof content.price === 'undefined' ? '0.49' : content.price;        
        content.shortDescription = typeof content.shortDescription === 'undefined' ? '' : content.shortDescription.replace(/\\"/g, '"');
        content.referralProgramEnabled = typeof content.referralProgramEnabled === 'undefined' ? 0 : content.referralProgramEnabled;
        content.itemType = typeof content.itemType === 'undefined' ? 'article' : content.itemType;
        content.referralRate = typeof content.referralRate === 'undefined' ? 0.05 : content.referralRate;
        content.hasLayerDates = typeof content.hasLayerDates === 'undefined' ? 0 : content.hasLayerDates;
        content.layerStartDate = typeof content.layerStartDate === 'undefined' ? '' : content.layerStartDate;
        content.layerEndDate = typeof content.layerEndDate === 'undefined' ? '' : content.layerEndDate;

        CleengWidget.newContentForm.contentId = content.contentId;
        jQuery('#cleeng-ContentForm-Description').val(content.shortDescription.replace(/\/"/g, '\"'));
        jQuery('#cleeng-ContentForm-LayerStartDate').datetimepicker({dateFormat: 'yy-mm-dd'});
        jQuery('#cleeng-ContentForm-LayerEndDate').datetimepicker({dateFormat: 'yy-mm-dd'});
        /* lookup for price */        
        for (var i in CleengWidget.sliderToPrice) {
            if (parseFloat(CleengWidget.sliderToPrice[i].toFixed(2)) >= parseFloat(content.price)) {
                break;
            }
        }
        jQuery('#cleeng-ContentForm-PriceValue').html(CleengWidget.sliderToPrice[i].toFixed(2));
        jQuery('#cleeng-ContentForm-PriceSlider').slider('value', i);
        jQuery('#cleeng-ContentForm-ItemType').val(content.itemType);
        var referralRate = Math.round(parseFloat(content.referralRate) * 100);
        
        jQuery('#cleeng-ContentForm-ReferralRateValue').html(referralRate);
        jQuery('#cleeng-ContentForm-ReferralRateSlider').slider('value', referralRate);
        if (content.referralProgramEnabled) {
            jQuery('#cleeng-ContentForm-ReferralRateSlider').slider({disabled: false});
        }
        
        jQuery('#cleeng-ContentForm-LayerStartDate').val(content.layerStartDate);
        jQuery('#cleeng-ContentForm-LayerEndDate').val(content.layerEndDate);

        if (typeof jQuery.prop !== 'undefined') {
            jQuery('#cleeng-ContentForm-ReferralProgramEnabled').prop('checked', content.referralProgramEnabled?'checked':null);
            jQuery('#cleeng-ContentForm-LayerDatesEnabled').prop('checked', content.hasLayerDates?'checked':null);
        } else {
            jQuery('#cleeng-ContentForm-ReferralProgramEnabled').attr('checked', content.referralProgramEnabled?'checked':null);
            jQuery('#cleeng-ContentForm-LayerDatesEnabled').attr('checked', content.hasLayerDates?'checked':null);
        }


        CleengWidget.newContentForm.dialog('open');
    },
    isSelectionValid: function() {
        return !!(jQuery.trim(CleengWidget.getSelectedText()));
    },
    createCleengContent: function() {
        CleengWidget.bookmarkSelection();
        if (CleengWidget.isSelectionValid()) {
            jQuery('#cleeng-contentForm input[type="text"]').val('')
            jQuery('#cleeng_SelectionError').hide();
            var now = new Date();
            jQuery('#cleeng-ContentForm-LayerStartDate').val(
                jQuery.datepicker.formatDate('yy-mm-dd', now)
            );
            now.setDate(now.getDate()+7);
            jQuery('#cleeng-ContentForm-LayerEndDate').val(
                jQuery.datepicker.formatDate('yy-mm-dd', now)
            );
            jQuery('#cleeng-ContentForm-LayerStartDate, #cleeng-ContentForm-LayerEndDate')
                .attr('disabled', 'disabled');
            jQuery('#cleeng-ContentForm-ItemType').val('article');
            jQuery('#cleeng-ContentForm-LayerDatesEnabled').attr('checked', false);
            jQuery('#cleeng-ContentForm-ReferralRateSlider').slider("disable");

            CleengWidget.showContentFormWithDefaultParams();
        } else {
            jQuery('#cleeng_SelectionError').show('pulsate');
        }
    },
    newContentFormRegisterContent: function() {
        var content = {
            contentId: CleengWidget.newContentForm.contentId,
            pageTitle: jQuery('#title').val() + ' | ' + jQuery('#site-title').html(),
            price: CleengWidget.sliderToPrice[jQuery('#cleeng-ContentForm-PriceSlider').slider('value')].toFixed(2),
            shortDescription: jQuery('#cleeng-ContentForm-Description').val()            
        };
        content.itemType = jQuery('#cleeng-ContentForm-ItemType').val();
        if (jQuery('#cleeng-ContentForm-LayerDatesEnabled:checked').length) {
            content.hasLayerDates = 1;
            content.layerStartDate = jQuery('#cleeng-ContentForm-LayerStartDate').val();
            content.layerEndDate = jQuery('#cleeng-ContentForm-LayerEndDate').val();
        }
        if (jQuery('#cleeng-ContentForm-ReferralProgramEnabled:checked').length) {
            content.referralProgramEnabled = 1;
            content.referralRate = jQuery('#cleeng-ContentForm-ReferralRateSlider').slider('value')/100;
        }
        if (!content.contentId) {
            // generate temp. ID
            content.contentId = 't' + CleengWidget.tempId++;
        }
        re = new RegExp('\\[cleeng_content([^\\[\\]]+?)id="' + content.contentId + '"([\\S\\s]*?)\\]([\\S\\s]*?)\\[\\/cleeng_content\\]', 'mi');
        if (CleengWidget.getEditorText().match(re)) {
            CleengWidget.updateStartMarker(content);
        } else {
            CleengWidget.addMarkers(content);
        }
        CleengWidget.newContentForm.dialog('close');
    },    
    addMarkers: function(content) {
        CleengWidget.addMarkersToSelection(CleengWidget.getStartMarker(content), '[/cleeng_content]');
        CleengWidget.findContent();
    },
    getStartMarker: function(content) {
        var startMarker =  '[cleeng_content id=\"'+ content.contentId +'\"';
        startMarker += ' price=\"' + encodeURI(content.price) + '\"';
        startMarker += ' description="' + content.shortDescription.replace(/"/g, '\\"') + '"';

        if (content.referralProgramEnabled && content.referralRate) {
            startMarker += ' referral=\"' + content.referralRate + '\"';
        }
        
        if (content.itemType && content.itemType != 'article') {
            startMarker += ' t=\"' + content.itemType + '\"';
        }

        if (content.hasLayerDates && content.layerStartDate && content.layerEndDate) {
            startMarker += ' ls=\"' + content.layerStartDate + '\" le=\"' + content.layerEndDate + '\"';
        }
        startMarker += ']';
        return startMarker;
    },
    updateStartMarker: function(content) {
        marker = CleengWidget.getStartMarker(content);
        re = new RegExp('\\[cleeng_content([^\\[\\]]+?)id=\\"' + content.contentId + '\\"(.)*?\\]', 'mi');
        editorText = CleengWidget.getEditorText();
        editorText = editorText.replace(re, marker);
        CleengWidget.setEditorText(editorText);        
    },
    findContent: function() {        
        var editorText = CleengWidget.getEditorText();
        
//        var contentElements = editorText.match(/\[cleeng_content([\S\s]+?)\][\S\s]+?\[\/cleeng_content\]/igm);
        var contentElements = editorText.match(/\[cleeng_content([\S\s]+?)\]/gi);
        var contentList = '';
        var i;
        
        if (contentElements) {            
            for (i=0; i<contentElements.length; i++) {

                if (!contentElements[i].match) {
                    continue;
                }
                var id = contentElements[i].match(/id=\"(t{0,1}\d+?)\"/i);                
                if (id && id[1]) {
                    id = id[1];
                    var price = contentElements[i].match(/price=\"(\d+[\.]{1}\d+?)\"/i);
                    if (price && price[1]) {
                        price = price[1];
                    } else {
                        price = 0;
                    }
                    if (id[0] != 't') {
                        parsedId = id.substring(0,3) + '.' + id.substring(3,6) + '.' + id.substring(6,9);
                    } else {
                        parsedId = id;
                    }
                    contentList += '<li>' + (parseInt(i)+1) + '. id: <span class="cleeng-contentId">' + parsedId + '</span> price: ' + price +
                            ' <a class="cleeng-editContentLink" href="#">edit</a> ' +
                            '<a class="cleeng-removeContentLink" href="#">remove</a></li>';
                    if (id[0] == 't') { // temporary ID?
                        CleengWidget.tempId = Math.max(CleengWidget.tempId, parseInt(id.split('t')[1]) + 1);
                    }
                }
            }
        }

        if (contentList != '') {
            jQuery('#cleeng_NoContent').hide();
            jQuery('#cleeng-ContentList').show().find('ul').html(contentList);
        } else {
            jQuery('#cleeng_NoContent').show();
            jQuery('#cleeng-ContentList').hide();
        }
    },
    /**
     * Extract content from post text
     */
    getContentFromEditor: function(contentId) {        
        editorText = CleengWidget.getEditorText();
        re = new RegExp('\\[cleeng_content([^\\[\\]]+?)id=\\"' + contentId + '\\"([\\S\\s]*?)\\]([\\S\\s]*?)\\[\\/cleeng_content\\]', 'mi');
        ct = re.exec(editorText);

        if (ct && ct[0]) {
            var opening = ct[0].match(/\[cleeng_content\s+(.*)?=(.*)?\]/gi);
            window.test = opening[0];
            var id = opening[0].match(/id=\"(.*?)\"/i);
            if (id && id[1]) {
                var content = {
                    contentId: id[1]
                };
                var price = opening[0].match(/price="(.*?)"/i);
                var description = opening[0].match(/description="(.*?[^\\]?)"/i);
                var referral = opening[0].match(/referral="(.*?)"/i);
                var itemType = opening[0].match(/t="(.*?)"/i);
                var ls = opening[0].match(/ls="(.*?)"/i);
                var le = opening[0].match(/le="(.*?)"/i);

                if (price && price[1]) {
                    content.price = price[1];
                } else {
                    content.price = 0;
                }
                if (!itemType) {
                    itemType = 'article';
                }
                content.itemType = itemType;
                if (description && description[1]) {
                    content.shortDescription = description[1];
                } else {
                    content.shortDescription = '';
                }
                if (referral && referral[1]) {
                    content.referralProgramEnabled = 1;
                    content.referralRate = referral[1];
                } else {
                    content.referralProgramEnabled = 0;
                }
                if (ls && ls[1] && le && le[1]) {
                    content.hasLayerDates = 1;
                    content.layerStartDate = ls[1];
                    content.layerEndDate = le[1];
                }

                return content;
            }
        }
        return null;
    },
    editContent: function(id) {
        var content = CleengWidget.getContentFromEditor(id);
        if (!content) {
            return false;
        }
        CleengWidget.showContentForm(content);
        CleengWidget.teserInputWatcher();
        return false;
    },
    /**
     * Remove content markers from source
     */
    removeMarkers: function(contentId) {
        var re = new RegExp('\\[cleeng_content([^\\[\\]]+?)id="' + contentId + '"([\\S\\s]*?)\\]([\\S\\s]*?)\\[\\/cleeng_content\\]', 'i');
        editorText = CleengWidget.getEditorText();
        rpl = re.exec(editorText);
        if (rpl && rpl[0] && rpl[3]) {            
            editorText = editorText.replace(rpl[0], rpl[3])
        }
        CleengWidget.setEditorText(editorText);
        return false;
    },   
    // helper functions for accessing editor
    // works for TinyMCE and textarea ("HTML Mode")
    getEditorText: function() {        
        if (typeof CKEDITOR !== 'undefined' 
            && typeof CKEDITOR.instances !== 'undefined'
            && typeof CKEDITOR.instances.content !== 'undefined'
        ) {
            return CKEDITOR.instances.content.getData().replace(/&quot;/g, '"');
        } else if ( typeof tinyMCE != 'undefined' && ( ed = tinyMCE.activeEditor ) && !ed.isHidden() ) {
            text = ed.getContent();
            if (!text && jQuery( edCanvas ).val()) {
                text = jQuery( edCanvas ).val();
            }
            return text;
        } else {
            if (typeof edCanvas !== 'undefined') {
                return jQuery( edCanvas ).val();
            }
        }
        return '';
    },
    setEditorText: function(text) {
        if (typeof CKEDITOR !== 'undefined'
            && typeof CKEDITOR.instances !== 'undefined'
            && typeof CKEDITOR.instances.content !== 'undefined'
        ) {
            CKEDITOR.instances.content.setData(text, function() {
                CleengWidget.findContent();
            });
            jQuery(CKEDITOR.instances.content.document.body).html(text);
        } else if ( typeof tinyMCE != 'undefined' && ( ed = tinyMCE.activeEditor ) && !ed.isHidden() ) {
            ed.setContent(text);
            CleengWidget.findContent();
        } else {
            jQuery( edCanvas ).val(text);
            CleengWidget.findContent();
        }
    },
    bookmarkSelection: function() {
        if (typeof CKEDITOR !== 'undefined'
            && typeof CKEDITOR.instances !== 'undefined'
            && typeof CKEDITOR.instances.content !== 'undefined'
        ) {
            CKEDITOR.instances.content.focus();
            CleengWidget.editorBookmark = CKEDITOR.instances.content.getSelection().getRanges()[0];
            
        } else if ( typeof tinyMCE != 'undefined' && ( ed = tinyMCE.activeEditor ) && !ed.isHidden() ) {
            CleengWidget.editorBookmark = ed.selection.getRng();            
        } else {
            if (document.selection) {
                CleengWidget.editorBookmark = document.selection.createRange().duplicate();
            }
        }
    },
    getSelectedText: function() {
        if (typeof CKEDITOR !== 'undefined'
            && typeof CKEDITOR.instances !== 'undefined'
            && typeof CKEDITOR.instances.content !== 'undefined'
        ) {
            CKEDITOR.instances.content.focus();
            var selection = CKEDITOR.instances.content.getSelection();            

            if (CKEDITOR.env.ie) {
                selection.unlock(true);
                return selection.getNative().createRange().text;
            } else {
                return "" + selection.getNative();
            }

        } else if ( typeof tinyMCE != 'undefined' && ( ed = tinyMCE.activeEditor ) && !ed.isHidden() ) {
            ed.focus();
            ed.selection.setRng(CleengWidget.editorBookmark);
            return jQuery.trim(ed.selection.getContent());
        } else {
            if (document.selection) {
                CleengWidget.editorBookmark.select();
                return CleengWidget.editorBookmark.text;
            } else {
                selStart = edCanvas.selectionStart;
                selEnd = edCanvas.selectionEnd;
                selLen = selEnd - selStart;
                return jQuery.trim(jQuery(edCanvas).val().substr(selStart, selLen));
            }
        }
    },
    addMarkersToSelection: function(startMarker, endMarker) {
        var startContainer, endContainer;
        if (typeof CKEDITOR !== 'undefined'
            && typeof CKEDITOR.instances !== 'undefined'
            && typeof CKEDITOR.instances.content !== 'undefined'
        ) {
            CKEDITOR.instances.content.focus();
            var range = CleengWidget.editorBookmark;



            if (typeof CleengWidget.editorBookmark.startContainer === 'undefined') { // IE
                CleengWidget.editorBookmark.text = startMarker + CleengWidget.editorBookmark.text + endMarker;
            } else {

                startContainer = range.startContainer.$;
                endContainer = range.endContainer.$;

                if (startContainer.innerHTML) {
                    startContainer.innerHTML = startMarker + startContainer.innerHTML;
                } else {
                    startContainer.nodeValue = startMarker + startContainer.nodeValue;
                }
                if (endContainer.innerHTML) {
                    endContainer.innerHTML = endContainer.innerHTML + endMarker;
                } else {
                    endContainer.nodeValue = endContainer.nodeValue + endMarker;
                }
            }

        } else if ( typeof tinyMCE != 'undefined' && ( ed = tinyMCE.activeEditor ) && !ed.isHidden() ) {
            ed.focus();
            ed.selection.setRng(CleengWidget.editorBookmark);
            if (typeof CleengWidget.editorBookmark.startContainer === 'undefined') { // IE
                CleengWidget.editorBookmark.text = startMarker + CleengWidget.editorBookmark.text + endMarker;
            } else {
                startContainer = CleengWidget.editorBookmark.startContainer;
                endContainer = CleengWidget.editorBookmark.endContainer;

                if (startContainer.innerHTML) {
                    startContainer.innerHTML = startMarker + startContainer.innerHTML;
                } else {
                    startContainer.nodeValue = startMarker + startContainer.nodeValue;
                }
                if (endContainer.innerHTML) {
                    endContainer.innerHTML = endContainer.innerHTML + endMarker;
                } else {
                    endContainer.nodeValue = endContainer.nodeValue + endMarker;
                }
            }
        } else {

            if (document.selection) {
                CleengWidget.editorBookmark.select();
                CleengWidget.editorBookmark.text = startMarker + CleengWidget.getSelectedText() + endMarker;
            } else {
                selStart = edCanvas.selectionStart;
                selEnd = edCanvas.selectionEnd;
                editorText = CleengWidget.getEditorText();
                newContent = editorText.substr(0, selStart)
                             + startMarker + CleengWidget.getSelectedText() + endMarker
                             + editorText.substr(selEnd);
                jQuery(edCanvas).val(newContent);
            }
        }
    }
}
jQuery(CleengWidget.init);



jQuery(function() {
    
  
    
    var advanced = 0;
    jQuery('#cleeng_advanced, #cleeng_advanced2').click(function(){
        if (advanced === 0) {
            jQuery('.cleeng_advanced').show();
            advanced = 1;
            jQuery('#arrow').addClass('bottom');
            location.href="#below";
        } else {
            jQuery('.cleeng_advanced').hide();
            advanced = 0;
            jQuery('#arrow').removeClass('bottom');

        }
        return false;
    });

    jQuery('a.cleeng-facebook, a.cleeng-twitter, a.publisher-account').click(function() {
        if (jQuery(this).hasClass('cleeng-twitter')) {
            width = 1110;
            height = 650;
        }else if(jQuery(this).hasClass('publisher-account')){
            width = 600;
            height = 760;
        } else {
            width = 600;
            height = 400;
        }
        window.open(jQuery(this).attr('href'), 'shareWindow', 'menubar=no,width='
            + width + ',height=' + height + ',toolbar=no,resizable=yes');

        return false;
    });
});
