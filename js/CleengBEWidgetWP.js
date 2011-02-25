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
    saveContentServiceURL : Cleeng_PluginPath+'ajax.php?backendWidget=true&cleengMode=saveContent',
    tempId: 1,

    teserInputWatcher: function() {
        desc = jQuery('#cleeng-ContentForm-Description');
        if (desc.val().length > 110) {
            desc.val(desc.val().substring(0, 110));
        }
        jQuery('#cleeng-ContentForm-DescriptionCharsLeft').html(110 - desc.val().length);
    },
    toggleHasLayerDates: function() {
        if (jQuery('#cleeng-ContentForm-LayerDatesEnabled:checked').length) {
            disabled = false;
        } else {
            disabled = 'disabled';
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
            window.open(Cleeng_PluginPath + 'ajax.php?backendWidget=true&cleengMode=auth&cleengPopup=1','CleengConfirmationPopUp', 'menubar=no,width=607,height=567,toolbar=no');
            return false;
        });
        jQuery('#cleeng-logout').click(function() {
            jQuery.post(
                Cleeng_PluginPath + 'ajax.php?backendWidget=true&cleengMode=logout',
                function(resp) {
                   CleengWidget.getUserInfo();
                }
            );
            return false;
        });
        
        jQuery('#cleeng-ContentForm-Description').bind('keyup', CleengWidget.teserInputWatcher);

        jQuery('#cleeng-ContentForm-LayerDatesEnabled').click(CleengWidget.toggleHasLayerDates);
        jQuery('#cleeng-ContentForm-ReferralProgramEnabled').click(CleengWidget.toggleReferralProgramEnabled);

        jQuery('#cleeng-createContent')
            .click(CleengWidget.createCleengContent)
            .hover(
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
            })
            .mouseup(function(){
                if(! jQuery(this).is('.fg-button-toggleable, .fg-buttonset-single .fg-button,  .fg-buttonset-multi .fg-button') ){
                        jQuery(this).removeClass('ui-state-active');
                }
            });

        CleengWidget.newContentForm = jQuery('#cleeng-contentForm').dialog({
            autoOpen: false,
            height: 365,
            width: 400,
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
            min:0.15,
            max:0.99,
            step:0.01,
            slide: function(event, ui) {
                jQuery('#cleeng-ContentForm-PriceValue').html(ui.value.toFixed(2));
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
                id = jQuery.trim(jQuery(this).parents('li').find('span.cleeng-contentId').text());
                id = id.replace(/\./g, '');
                CleengWidget.editContent(id);
            } else if (jQuery(this).hasClass('cleeng-removeContentLink')) {
                id = jQuery.trim(jQuery(this).parents('li').find('span.cleeng-contentId').text());
                id = id.replace(/\./g, '');
                CleengWidget.removeMarkers(id);
            }
            return false;
        });

        // autologin
        if (typeof CleengAutologin !== 'undefined') {
            if (CleengAutologin.available) {
                jQuery.getJSON(
                    Cleeng_PluginPath+'ajax.php?cleengMode=autologin&id=' + CleengAutologin.id
                        + '&key=' + CleengAutologin.key,
                    function(resp) {
                        if (resp.success) {
                            CleengWidget.getUserInfo(false);
                        }
                    }
                );
            }
        }
        setTimeout(function() {
            CleengWidget.findContent();
        }, 1000);
    },    
    getUserInfo: function() {
        jQuery.getJSON(
            Cleeng_PluginPath+'ajax.php?backendWidget=true&cleengMode=getUserInfo',
            function(resp) {
                CleengWidget.userInfo = resp;
                jQuery('#cleeng-connecting').hide();
                if (!resp || !resp.name) {
                    jQuery('#cleeng-logout').parent().hide();
                    jQuery('#cleeng-login').parent().show();
                    jQuery('#cleeng-auth-options').hide();
                    jQuery('#cleeng-notPublisher').hide();
                } else {
                    jQuery('#cleeng-login').parent().hide();
                    jQuery('#cleeng-logout').parent().show();
                    jQuery('#cleeng-username').html(resp.name);
                    if (resp.accountType != 'publisher') {
                        jQuery('#cleeng-notPublisher').show();
                        jQuery('#cleeng-auth-options').hide();
                    } else {
                        jQuery('#cleeng-notPublisher').hide();
                        jQuery('#cleeng-auth-options').show();
                    }
                }
            }
        );
    },    
    showContentForm: function(content) {
        content.contentId = typeof content.contentId === 'undefined' ? 0 : content.contentId;
        content.price = typeof content.price === 'undefined' ? '0.15' : content.price;
        content.shortDescription = typeof content.shortDescription === 'undefined' ? '' : content.shortDescription.replace(/\\"/g, '"');
        content.referralProgramEnabled = typeof content.referralProgramEnabled === 'undefined' ? 0 : content.referralProgramEnabled;
        content.itemType = typeof content.itemType === 'undefined' ? 'article' : content.itemType;
        content.referralRate = typeof content.referralRate === 'undefined' ? 0.05 : content.referralRate;
        content.hasLayerDates = typeof content.hasLayerDates === 'undefined' ? 0 : content.hasLayerDates;
        content.layerStartDate = typeof content.layerStartDate === 'undefined' ? '' : content.layerStartDate;
        content.layerEndDate = typeof content.layerEndDate === 'undefined' ? '' : content.layerEndDate;
        CleengWidget.newContentForm.contentId = content.contentId;
        jQuery('#cleeng-ContentForm-Description').val(content.shortDescription.replace(/\/"/g, '\"'));
        jQuery('#cleeng-ContentForm-PriceSlider').slider('value', content.price);
        jQuery('#cleeng-ContentForm-LayerStartDate').datetimepicker({dateFormat: 'yy-mm-dd'});
        jQuery('#cleeng-ContentForm-LayerEndDate').datetimepicker({dateFormat: 'yy-mm-dd'});
        jQuery('#cleeng-ContentForm-PriceValue').html(content.price);
        jQuery('#cleeng-ContentForm-ItemType').val(content.itemType);
        jQuery('#cleeng-ContentForm-ReferralRateValue').html(content.referralRate * 100);
        jQuery('#cleeng-ContentForm-ReferralRateSlider').slider('value', content.referralRate * 100);
        jQuery('#cleeng-ContentForm-ReferralProgramEnabled').attr('checked', content.referralProgramEnabled?'checked':null);

        jQuery('#cleeng-ContentForm-LayerStartDate').val(content.layerStartDate);
        jQuery('#cleeng-ContentForm-LayerEndDate').val(content.layerEndDate);
        jQuery('#cleeng-ContentForm-LayerDatesEnabled').attr('checked', content.hasLayerDates?'checked':null);
        CleengWidget.newContentForm.dialog('open');        
    },
    isSelectionValid: function() {
        return !!(jQuery.trim(CleengWidget.getSelectedText()));
    },
    createCleengContent: function() {
        CleengWidget.bookmarkSelection();
        if (CleengWidget.isSelectionValid()) {
            jQuery('#cleeng-contentForm input[type=text]').val('')
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
            CleengWidget.showContentForm({});
        } else {
            jQuery('#cleeng_SelectionError').show('pulsate');
        }
    },
    newContentFormRegisterContent: function() {
        var content = {
            contentId: CleengWidget.newContentForm.contentId,
            pageTitle: jQuery('#title').val() + ' | ' + jQuery('#site-title').html(),
            price: jQuery('#cleeng-ContentForm-PriceSlider').slider('value'),
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
        CleengWidget.replaceSelectedText(CleengWidget.getStartMarker(content) + CleengWidget.getSelectedText() + '[/cleeng_content]');
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
        CleengWidget.findContent();
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
                var description = opening[0].match(/description="(.*?[^\\])"/i);
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
        CleengWidget.findContent();
        return false;
    },   
    // helper functions for accessing editor
    // works for TinyMCE and textarea ("HTML Mode")
    getEditorText: function() {
        if ( typeof tinyMCE != 'undefined' && ( ed = tinyMCE.activeEditor ) && !ed.isHidden() ) {
            text = ed.getContent();
            if (!text && jQuery( edCanvas ).val()) {
                text = jQuery( edCanvas ).val();
            }
            return text;
        } else {
            return jQuery( edCanvas ).val();
        }
    },
    setEditorText: function(text) {
        if ( typeof tinyMCE != 'undefined' && ( ed = tinyMCE.activeEditor ) && !ed.isHidden() ) {
            ed.setContent(text);
        } else {
            jQuery( edCanvas ).val(text);
        }
    },
    bookmarkSelection: function() {
        if ( typeof tinyMCE != 'undefined' && ( ed = tinyMCE.activeEditor ) && !ed.isHidden() ) {
            CleengWidget.editorBookmark = ed.selection.getBookmark();
        } else {
            if (document.selection) {
                CleengWidget.editorBookmark = document.selection.createRange().duplicate();
            }
        }
    },
    getSelectedText: function() {
        if ( typeof tinyMCE != 'undefined' && ( ed = tinyMCE.activeEditor ) && !ed.isHidden() ) {
            ed.focus();
            ed.selection.moveToBookmark(CleengWidget.editorBookmark);
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
    replaceSelectedText: function(replacement) {
        if ( typeof tinyMCE != 'undefined' && ( ed = tinyMCE.activeEditor ) && !ed.isHidden() ) {
            ed.focus();
            ed.selection.moveToBookmark(CleengWidget.editorBookmark);
            ed.selection.setContent(replacement)            
        } else {

            if (document.selection) {
                CleengWidget.editorBookmark.select();
                CleengWidget.editorBookmark.text = replacement;
            } else {
                selStart = edCanvas.selectionStart;
                selEnd = edCanvas.selectionEnd;
                editorText = CleengWidget.getEditorText();
                newContent = editorText.substr(0, selStart)
                             + replacement
                             + editorText.substr(selEnd);
                jQuery(edCanvas).val(newContent);
            }
        }
    }
}
jQuery(CleengWidget.init);
