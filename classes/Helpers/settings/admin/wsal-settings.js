var $doc = jQuery(document),
    $window = jQuery(window);

function attachAllDynamicPostsSelects() {
    /** Make Custom Posts sortable and They are using AJAX calls to extract info */

    jQuery('.wsal-custom-posts-selector select').select2({
        width: 'resolve',
        containerCssClass: "s24wp-wrapper",
        ajax: {
            url: ajaxurl, // AJAX URL is predefined in WordPress admin
            dataType: 'json',
            delay: 250, // delay in ms while typing when to perform a AJAX search
            data: function (params) {
                return {
                    q: params.term, // search query
                    action: 'wsal_settings_get_posts' // AJAX action for admin-ajax.php
                };
            },
            processResults: function (data) {
                var options = [];
                if (data) {

                    // data is the array of arrays, and each of them contains ID and the Label of the option
                    jQuery.each(data, function (index, text) { // do not forget that "index" is just auto incremented value
                        options.push({ id: text[0], text: text[1] });
                    });

                }
                return {
                    results: options
                };
            },
            cache: true
        },
        minimumInputLength: 3 // the minimum of symbols to input before perform a search
    });
    jQuery('.wsal-custom-posts-selector select').each(function (index) {
        var selectEl = jQuery(this);
        selectEl.next().children().children().children().sortable({
            containment: 'parent', stop: function (event, ui) {
                ui.item.parent().children('[title]').each(function () {
                    var title = jQuery(this).attr('title');
                    var original = jQuery('option:contains(' + title + ')', selectEl).first();
                    original.detach();
                    selectEl.append(original)
                });
                selectEl.change();
            }
        });
    });
    /** End Custom Posts sortable and They are using AJAX calls to extract info */

}
function attachAllDynamicPostTitlesSelects() {
    /** Make Custom Posts sortable and They are using AJAX calls to extract info */

    jQuery('.wsal-custom-post-titles-selector select').select2({
        width: 'resolve',
        containerCssClass: "s24wp-wrapper",
        ajax: {
            url: ajaxurl, // AJAX URL is predefined in WordPress admin
            dataType: 'json',
            delay: 250, // delay in ms while typing when to perform a AJAX search
            data: function (params) {
                return {
                    q: params.term, // search query
                    action: 'wsal_settings_get_posts_titles' // AJAX action for admin-ajax.php
                };
            },
            processResults: function (data) {
                var options = [];
                if (data) {

                    // data is the array of arrays, and each of them contains ID and the Label of the option
                    jQuery.each(data, function (index, text) { // do not forget that "index" is just auto incremented value
                        options.push({ id: text['id'], text: text['label'] });
                    });

                }
                return {
                    results: options
                };
            },
            cache: true
        },
        minimumInputLength: 3 // the minimum of symbols to input before perform a search
    });
    jQuery('.wsal-custom-post-titles-selector select').each(function (index) {
        var selectEl = jQuery(this);
        selectEl.next().children().children().children().sortable({
            containment: 'parent', stop: function (event, ui) {
                ui.item.parent().children('[title]').each(function () {
                    var title = jQuery(this).attr('title');
                    var original = jQuery('option:contains(' + title + ')', selectEl).first();
                    original.detach();
                    selectEl.append(original)
                });
                selectEl.change();
            }
        });
    });
    /** End Custom Posts sortable and They are using AJAX calls to extract info */

}

function attachAllDynamicPostTypes() {
    jQuery('.wsal-custom-post-selector input:not([type=hidden])').autocomplete({

        source: function (request, response) {
            jQuery.ajax({
                url: ajaxurl,
                dataType: "json",
                data: {
                    q: request.term, // search query
                    action: 'wsal_settings_get_posts', // AJAX action for admin-ajax.php
                    type: 'input'
                },
                success: function (data) {
                    console.log(data);
                    response(data);
                }
            });
        },
        select: function (event, ui) {
            console.log(jQuery(this));
            jQuery(this).parent().find('input:hidden:first').val(ui.item.id);
        },
        minLength: 3,
    });
}

function attachAllDynamicUsersSelects() {
    /** Make Custom Posts sortable and They are using AJAX calls to extract info */

    jQuery('.wsal-custom-users-selector select').select2({
        width: 'resolve',
        containerCssClass: "s24wp-wrapper",
        ajax: {
            url: ajaxurl, // AJAX URL is predefined in WordPress admin
            dataType: 'json',
            delay: 250, // delay in ms while typing when to perform a AJAX search
            data: function (params) {
                return {
                    q: params.term, // search query
                    action: 'wsal_settings_get_users' // AJAX action for admin-ajax.php
                };
            },
            processResults: function (data) {
                var options = [];
                if (data) {

                    // data is the array of arrays, and each of them contains ID and the Label of the option
                    jQuery.each(data, function (index, text) { // do not forget that "index" is just auto incremented value
                        options.push({ id: text['id'], text: text['label'] });
                    });

                }
                return {
                    results: options
                };
            },
            cache: true
        },
        minimumInputLength: 3 // the minimum of symbols to input before perform a search
    });
    jQuery('.wsal-custom-users-selector select').each(function (index) {
        var selectEl = jQuery(this);
        selectEl.next().children().children().children().sortable({
            containment: 'parent', stop: function (event, ui) {
                ui.item.parent().children('[title]').each(function () {
                    var title = jQuery(this).attr('title');
                    var original = jQuery('option:contains(' + title + ')', selectEl).first();
                    original.detach();
                    selectEl.append(original)
                });
                selectEl.change();
            }
        });
    });
    /** End Custom Posts sortable and They are using AJAX calls to extract info */

}

function attachAllDynamicSitesSelects() {
    /** Make Custom Posts sortable and They are using AJAX calls to extract info */

    jQuery('.wsal-custom-sites-selector select').select2({
        width: 'resolve',
        containerCssClass: "s24wp-wrapper",
        ajax: {
            url: ajaxurl, // AJAX URL is predefined in WordPress admin
            dataType: 'json',
            delay: 250, // delay in ms while typing when to perform a AJAX search
            data: function (params) {
                return {
                    q: params.term, // search query
                    action: 'wsal_settings_get_sites' // AJAX action for admin-ajax.php
                };
            },
            processResults: function (data) {
                var options = [];
                if (data) {

                    // data is the array of arrays, and each of them contains ID and the Label of the option
                    jQuery.each(data, function (index, text) { // do not forget that "index" is just auto incremented value
                        options.push({ id: text['id'], text: text['label'] });
                    });

                }
                return {
                    results: options
                };
            },
            cache: true
        },
        minimumInputLength: 3 // the minimum of symbols to input before perform a search
    });
    jQuery('.wsal-custom-users-selector select').each(function (index) {
        var selectEl = jQuery(this);
        selectEl.next().children().children().children().sortable({
            containment: 'parent', stop: function (event, ui) {
                ui.item.parent().children('[title]').each(function () {
                    var title = jQuery(this).attr('title');
                    var original = jQuery('option:contains(' + title + ')', selectEl).first();
                    original.detach();
                    selectEl.append(original)
                });
                selectEl.change();
            }
        });
    });
    /** End Custom Posts sortable and They are using AJAX calls to extract info */

}

function attachAllDynamicUserTypes() {
    jQuery('.wsal-custom-user-selector input:not([type=hidden])').autocomplete({

        source: function (request, response) {
            jQuery.ajax({
                url: ajaxurl,
                dataType: "json",
                data: {
                    q: request.term, // search query
                    action: 'wsal_get_users', // AJAX action for admin-ajax.php
                    type: 'input'
                },
                success: function (data) {
                    console.log(data);
                    response(data);
                }
            });
        },
        select: function (event, ui) {
            console.log(jQuery(this));
            jQuery(this).parent().find('input:hidden:first').val(ui.item.id);
        },
        minLength: 3,
    });
}

function attachAllDynamicRolesSelects() {
    /** Make Custom Posts sortable and They are using AJAX calls to extract info */

    jQuery('.wsal-custom-roles-selector select').select2({
        width: 'resolve',
        containerCssClass: "s24wp-wrapper",
        ajax: {
            url: ajaxurl, // AJAX URL is predefined in WordPress admin
            dataType: 'json',
            delay: 250, // delay in ms while typing when to perform a AJAX search
            data: function (params) {
                return {
                    q: params.term, // search query
                    action: 'wsal_settings_get_roles' // AJAX action for admin-ajax.php
                };
            },
            processResults: function (data) {
                var options = [];
                if (data) {

                    // data is the array of arrays, and each of them contains ID and the Label of the option
                    jQuery.each(data, function (index, text) { // do not forget that "index" is just auto incremented value
                        options.push({ id: text['id'], text: text['label'] });
                    });

                }
                return {
                    results: options
                };
            },
            cache: true
        },
        minimumInputLength: 3 // the minimum of symbols to input before perform a search
    });
    jQuery('.wsal-custom-roles-selector select').each(function (index) {
        var selectEl = jQuery(this);
        selectEl.next().children().children().children().sortable({
            containment: 'parent', stop: function (event, ui) {
                ui.item.parent().children('[title]').each(function () {
                    var title = jQuery(this).attr('title');
                    var original = jQuery('option:contains(' + title + ')', selectEl).first();
                    original.detach();
                    selectEl.append(original)
                });
                selectEl.change();
            }
        });
    });
    /** End Custom Roles sortable and They are using AJAX calls to extract info */

}

function attachAllDynamicIPsSelects() {
    /** Make Custom Posts sortable and They are using AJAX calls to extract info */

    jQuery('.wsal-custom-ips-selector select').select2({
        width: 'resolve',
        containerCssClass: "s24wp-wrapper",
        ajax: {
            url: ajaxurl, // AJAX URL is predefined in WordPress admin
            dataType: 'json',
            delay: 250, // delay in ms while typing when to perform a AJAX search
            data: function (params) {
                return {
                    q: params.term, // search query
                    action: 'wsal_settings_get_ips' // AJAX action for admin-ajax.php
                };
            },
            processResults: function (data) {
                var options = [];
                if (data) {

                    // data is the array of arrays, and each of them contains ID and the Label of the option
                    jQuery.each(data, function (index, text) { // do not forget that "index" is just auto incremented value
                        options.push({ id: text['id'], text: text['label'] });
                    });

                }
                return {
                    results: options
                };
            },
            cache: true
        },
        minimumInputLength: 3 // the minimum of symbols to input before perform a search
    });
    jQuery('.wsal-custom-ips-selector select').each(function (index) {
        var selectEl = jQuery(this);
        selectEl.next().children().children().children().sortable({
            containment: 'parent', stop: function (event, ui) {
                ui.item.parent().children('[title]').each(function () {
                    var title = jQuery(this).attr('title');
                    var original = jQuery('option:contains(' + title + ')', selectEl).first();
                    original.detach();
                    selectEl.append(original)
                });
                selectEl.change();
            }
        });
    });
    /** End Custom Roles sortable and They are using AJAX calls to extract info */

}

$doc.ready(function () {

    var $wsalBody = jQuery('body');

    /* DASHBORED COLOR
    ------------------------------------------------------------------------------------------ */
    var brandColor = '#d54e21';
    if ($wsalBody.hasClass('admin-color-blue')) {
        brandColor = '#e1a948';
    }
    else if ($wsalBody.hasClass('admin-color-coffee')) {
        brandColor = '#9ea476';
    }
    else if ($wsalBody.hasClass('admin-color-ectoplasm')) {
        brandColor = '#d46f15';
    }
    else if ($wsalBody.hasClass('admin-color-midnight')) {
        brandColor = '#69a8bb';
    }
    else if ($wsalBody.hasClass('admin-color-ocean')) {
        brandColor = '#aa9d88';
    }
    else if ($wsalBody.hasClass('admin-color-sunrise')) {
        brandColor = '#ccaf0b';
    }

    attachAllDynamicPostsSelects();
    attachAllDynamicPostTitlesSelects();
    attachAllDynamicUsersSelects();
    attachAllDynamicSitesSelects();
    attachAllDynamicPostTypes();
    attachAllDynamicUserTypes();
    attachAllDynamicRolesSelects();
    attachAllDynamicIPsSelects();

    jQuery('.wsal-toggle-option').each(function () {
        var $thisElement = jQuery(this),
            elementType = $thisElement.attr('type'),
            toggleItems = $thisElement.data('wsal-toggle');

        toggleItems = jQuery(toggleItems).hide();

        if (elementType = 'checkbox') {
            if ($thisElement.is(':checked')) {
                toggleItems.slideDown();
            };

            $thisElement.change(function () {
                toggleItems.slideToggle('fast');

                // CodeMirror
                toggleItems.find('.CodeMirror').each(function (i, el) {
                    el.CodeMirror.refresh();
                });

            });
        }
    });

    /* Reset button message
    ------------------------------------------------------------------------------------------ */
    jQuery('#wsal-reset-settings').click(function () {
        var message = jQuery(this).data('message'),
            reset = confirm(message);

        if (!reset) {
            return false;
        }
    });

    /* Sticky Bottom Save Button */
    var lastScrollTop = 0,
        $topSaveButton = jQuery('.wsal-panel-content'),
        $bottomSaveButton = jQuery('.wsal-footer .wsal-save-button');

    stickySaveButton = function () {

        var topSaveOffset = $topSaveButton.offset().top,
            scrollTop = $window.scrollTop(),
            scrollBottom = $doc.height() - scrollTop - $window.height(),
            st = scrollTop;

        if (scrollTop > topSaveOffset && scrollBottom > 105 - $bottomSaveButton.height()) {
            if (st > lastScrollTop) {
                $bottomSaveButton.addClass('sticky-on-down').removeClass('sticky-on-up');

                if (scrollTop > topSaveOffset) {
                    $bottomSaveButton.addClass('sticky-on-down-appear').removeClass('sticky-on-up-disappear');
                }
            }
            else {
                $bottomSaveButton.addClass('sticky-on-up').removeClass('sticky-on-down');

                if (scrollTop < topSaveOffset) {
                    $bottomSaveButton.addClass('sticky-on-up-disappear').removeClass('sticky-on-up-appear');
                }
            }
        }
        else {
            $bottomSaveButton.removeClass('sticky-on-down sticky-on-up sticky-on-down-appear sticky-on-up-disappear');
        }

        lastScrollTop = st;
    }

    if (0 !== $topSaveButton.length) {

        stickySaveButton();

        $window.scroll(function () {
            stickySaveButton();
        });
    }

    /* Blocks Color Picker */
    var wsalBlocksColorsOptions = {
        change: function (event, ui) {
            var newColor = ui.color.toString();
            jQuery(this).closest('.block-item').find('.wsal-block-head').attr('style', 'background-color: ' + newColor).removeClass('block-head-light block-head-dark').addClass('block-head-' + getContrastColor(newColor));
        },
        clear: function () {
            jQuery(this).closest('.block-item').find('.wsal-block-head').attr('style', '').removeClass('block-head-light block-head-dark');
        }
    };

    if (jQuery().wpColorPicker) {
        jQuery('.wsalBlocksColor').wpColorPicker(wsalBlocksColorsOptions);
    }

    /* Toggle open/Close */
    $doc.on('click', '.toggle-section', function () {
        var $thisElement = jQuery(this).closest('.wsal-builder-container');
        $thisElement.find('.wsal-builder-section-inner').slideToggle('fast');
        $thisElement.toggleClass('wsal-section-open');
        return false;
    });

    /* COLOR PICKER */
    if (jQuery().wpColorPicker) {
        wsal_color_picker();
    }

    /* IMAGE UPLOADER PREVIEW */
    jQuery('.wsal-img-path').each(function () {
        wsal_image_uploader_trigger(jQuery(this));
    });

    /* CHECKBOXES */
    var checkInputs = Array.prototype.slice.call(document.querySelectorAll('.wsal-js-switch'));
    checkInputs.forEach(function (html) {
        new Switchery(html, { color: brandColor });
    });

    /*  Sortable  */
    jQuery('.tab-sortable').each(function () {
        wsal_sortable_tabs_trigger(jQuery(this));
    });

    /* DISMISS NOTICES */
    $doc.on('click', '.wsal-notice .notice-dismiss', function () {

        jQuery('#wsal-page-overlay').hide();

        jQuery.ajax({
            url: ajaxurl,
            type: 'post',
            data: {
                pointer: jQuery(this).closest('.wsal-notice').attr('id'),
                action: 'dismiss-wp-pointer',
            },
        });
    });

    /* SAVE PLUGIN SETTINGS
    ------------------------------------------------------------------------------------------ */
    /**
     * On the periodic reports whene there are no records in the report we are generating error message.
     * That error message is using hte same HTML element - so that code checks for existance and if there is
     * such - it doesn't run this logic.
     * Search the code base for $genAlert (JS variable)
     */
    if ( typeof $genAlert === 'undefined' ) {
        var $saveAlert = jQuery('#wsal-saving-settings');
    }

    jQuery('#wsal_form').submit(function (evt) {
        $saveAlert.fadeIn();

        if ( jQuery('#wsal-import-file').length ) {
            // Check if the import field has a file
            var importSettings = jQuery('#wsal-import-file').val();
            if (importSettings.length > 0) {
                return true;
            }
        }

        // Disable all blank fields to reduce the size of the data
        jQuery('form#wsal_form input, form#wsal_form textarea, form#wsal_form select').each(function () {
            if (!jQuery(this).val()) {
                jQuery(this).attr('disabled', true);
            }
        });


        // Serialize the data array
        var data = jQuery(this).serialize();

        // Re-activate the disabled options
        jQuery('form#wsal_form input:disabled, form#wsal_form textarea:disabled, form#wsal_form select:disabled').attr('disabled', false);

        // Add the Overlay layer and reset the saving spinner
        $wsalBody.addClass('has-overlay');
        $saveAlert.removeClass('is-success is-failed');

        jQuery.ajax({
			url : ajaxurl,
			type: 'post',
			data: data,

			error: function( xhr, status, error ){
				if( 'undefined' != typeof xhr.status && xhr.status != 200 ){

					$saveAlert.addClass('is-failed').delay(900);
					$saveAlert.append('<div class="wsal-error-message">'+xhr.responseJSON.data[0].message+'<p><button id="wsal_remove_error" type="button">Close</button></p></div>');

                    jQuery('#wsal_remove_error').click(function(e) {
                        $saveAlert.addClass('is-failed').delay(900).fadeOut(700);
                        $wsalBody.removeClass('has-overlay');
                        jQuery('.wsal-error-message').remove();
                    });
				}
                return false;
			},

			success: function( response ){
                if (response.data == 1) {
                    $saveAlert.addClass('is-success').delay(900).fadeOut(700);
                    setTimeout(function() { $wsalBody.removeClass('has-overlay'); },1200);
                }
                else if (response.data == 2) {
                   location.reload();
                }
                else if ( undefined !== response.data.redirect && response.data.redirect.length) {
                    location.href = decodeURIComponent(response.data.redirect);
                }
                else {
                    $saveAlert.addClass('is-failed').delay(900).fadeOut(700);
                    setTimeout(function () { $wsalBody.removeClass('has-overlay'); }, 1200);
                }
			}
		});

        // Send the Saving Ajax request
        // jQuery.post(
        //     ajaxurl,
        //     data,
            
        //     function (response) {
        //         console.log(response);
        //         if (response.data == 1) {
        //             $saveAlert.addClass('is-success').delay(900).fadeOut(700);
        //             setTimeout(function () { $wsalBody.removeClass('has-overlay'); }, 1200);
        //         }
        //         else if (response.data == 2) {
        //             location.reload();
        //         }
        //         else if ( undefined !== response.data['redirect'] && response.data['redirect'].length) {
        //             location.href = decodeURIComponent(response.data['redirect']);
        //         }
        //         else {
        //             $saveAlert.addClass('is-failed').delay(900).fadeOut(700);
        //             setTimeout(function () { $wsalBody.removeClass('has-overlay'); }, 1200);
        //         }
        //     }
        // );

        return false;
    });


    /* SAVE SETTINGS ALERT */
     /**
     * On the periodic reports whene there are no records in the report we are generating error message.
     * That error message is using hte same HTML element - so that code checks for existance and if there is
     * such - it doesn't run this logic.
     * Search the code base for $genAlert (JS variable)
     */
    if ( typeof $genAlert === 'undefined' ) {
        $saveAlert.fadeOut();
    }
    // jQuery('.wsal-save-button').click(function (evt) {
    //     // jQuery("input:hidden, textarea:hidden, select:hidden").attr("disabled", true);

    //     jQuery('form#wsal_form input').on('invalid', function(e){
    //         alert("Error, please fill all required fields before submitting.");
    //         $saveAlert.fadeOut();

    //         // evt.preventDefault();
    //         // evt.stopPropagation();

    //         // jQuery("input:hidden, textarea:hidden, select:hidden").attr("disabled", false);

    //         return false;
    //     });

    //     $saveAlert.fadeIn();
    // });

    /* SETTINGS PANEL
    ------------------------------------------------------------------------------------------ */
    jQuery('.wsal-panel, .wsal-notice').css({ 'opacity': 1, 'visibility': 'visible' });

    var tabsHeight = jQuery('.wsal-panel-tabs').outerHeight();
    jQuery('.tabs-wrap').hide();
    jQuery('.wsal-panel-tabs ul li:first').addClass('active').show();
    jQuery('.tabs-wrap:first').show();
    jQuery('.wsal-panel-content').css({ minHeight: tabsHeight });

    jQuery('li.wsal-tabs:not(.wsal-not-tab)').click(function () {
        jQuery('.wsal-panel-tabs ul li').removeClass('active');
        jQuery(this).addClass('active');
        jQuery('.tabs-wrap').hide();
        var activeTab = jQuery(this).find('a').attr('href');
        jQuery(activeTab).show();
        jQuery(activeTab).trigger('activated');
        document.location.hash = activeTab + '-target';

        // CodeMirror
        jQuery(activeTab).find('.CodeMirror').each(function (i, el) {
            el.CodeMirror.refresh();
        });

    });

    /* GO TO THE OPENED TAB WITH LOAD */
    var currentTab = window.location.hash.replace('-target', '');
    currentTab = currentTab.replace(/\//g, ''); // avoid issues when the URL contains something like #/campaign/0/contacts

    if (jQuery(currentTab).parent('#wsal_form').length || jQuery(currentTab).parent('#periodic-report-viewer').length || jQuery(currentTab).parent('#saved-reports-viewer').length ) {
        var tabLinkClass = currentTab.replace('#', '.');
        jQuery('.tabs-wrap').hide();
        jQuery('.wsal-panel-tabs ul li').removeClass('active');
        jQuery(currentTab).show();
        jQuery(tabLinkClass).addClass('active');
        jQuery(tabLinkClass).trigger('activated');
    }

    /* DELETE SECTIONS
    ------------------------------------------------------------------------------------------ */
    /* OPTION ITEM */
    $doc.on('click', '.del-item', function () {
        var $thisButton = jQuery(this);

        if ($thisButton.hasClass('del-custom-sidebar')) {
            var option = $thisButton.parent().find('input').val();
            jQuery('#custom-sidebars select').find('option[value="' + option + '"]').remove();
        }

        if ($thisButton.hasClass('del-section')) {
            var widgets = $thisButton.closest('.parent-item').find('.wsal-manage-widgets').data('widgets');
            jQuery('#wrap-' + widgets + ', #' + widgets + '-sidebar-options').remove();
        }

        $thisButton.closest('.parent-item').addClass('removed').fadeOut(function () {
            $thisButton.closest('.parent-item').remove();
        });

        return false;
    });

    /* DELETE PREVIEW IMAGE */
    $doc.on('click', '.del-img', function () {
        var $img = jQuery(this).parent();
        $img.fadeOut('fast', function () {
            $img.hide();
            $img.closest('.option-item').find('.wsal-img-path').attr('value', '');
        });
    });

    /* DELETE PREVIEW IMAGE */
    $doc.on('click', '.del-img-all', function () {
        var $imgLi = jQuery(this).closest('li');
        $imgLi.fadeOut('fast', function () {
            $imgLi.remove();
        });
    });

    jQuery('ul.wsal-options').each(function (index) {
        jQuery(this).find('input:checked').parent().addClass('selected');
    });

    $doc.on('click', 'ul.wsal-options a', function () {
        var $thisBlock = jQuery(this),
            blockID = $thisBlock.closest('ul.wsal-options').attr('id');

        jQuery('#' + blockID).find('li').removeClass('selected');
        jQuery('#' + blockID).find(':radio').removeAttr('checked');
        $thisBlock.parent().find(':radio').trigger('click');
        $thisBlock.parent().addClass('selected');
        $thisBlock.parent().find(':radio').attr('checked', 'checked');
        return false;
    });

});

/* Fire Sortable on the Widgets Tabs
------------------------------------------------------------------------------------------ */
function wsal_sortable_tabs_trigger($thisTabs) {

    $thisTabs.sortable({
        placeholder: 'wsal-state-highlight',

        stop: function (event, ui) {
            var data = '';

            $thisTabs.find('li').each(function () {
                var type = jQuery(this).data('tab');
                data += type + ',';
            });

            $thisTabs.parent().find('.stored-tabs-order').val(data.slice(0, -1));
        }
    });
}

function wsal_image_uploader_trigger($thisElement) {

    var thisElementID = $thisElement.attr('id').replace('#', ''),
        $thisElementParent = $thisElement.closest('.option-item'),
        $thisElementImage = $thisElementParent.find('.img-preview'),
        uploaderTypeStyles = false;

    $thisElement.change(function () {
        $thisElementImage.show();
        $thisElementImage.find('img').attr('src', $thisElement.val());
    });

    if ($thisElement.hasClass('wsal-background-path')) {
        thisElementID = thisElementID.replace('-img', '');
        uploaderTypeStyles = true;
    }

    wsal_set_uploader(thisElementID, uploaderTypeStyles);
}

function wsal_set_uploader(field, styling) {
    var wsal_bg_uploader;

    $doc.on('click', '#upload_' + field + '_button', function (event) {

        event.preventDefault();
        wsal_bg_uploader = wp.media.frames.wsal_bg_uploader = wp.media({
            title: 'Choose Image',
            library: { type: 'image' },
            button: { text: 'Select' },
            multiple: false
        });

        wsal_bg_uploader.on('select', function () {
            var selection = wsal_bg_uploader.state().get('selection');
            selection.map(function (attachment) {

                attachment = attachment.toJSON();

                if (styling) {
                    jQuery('#' + field + '-img').val(attachment.url);
                }

                else {
                    jQuery('#' + field).val(attachment.url);
                }

                jQuery('#' + field + '-preview').show();
                jQuery('#' + field + '-preview img').attr('src', attachment.url);
            });
        });

        wsal_bg_uploader.open();
    });
}

function wsal_color_picker() {
    Color.prototype.toString = function (remove_alpha) {
        if (remove_alpha == 'no-alpha') {
            return this.toCSS('rgba', '1').replace(/\s+/g, '');
        }
        if (this._alpha < 1) {
            return this.toCSS('rgba', this._alpha).replace(/\s+/g, '');
        }
        var hex = parseInt(this._color, 10).toString(16);
        if (this.error) return '';
        if (hex.length < 6) {
            for (var i = 6 - hex.length - 1; i >= 0; i--) {
                hex = '0' + hex;
            }
        }
        return '#' + hex;
    };

    jQuery('.wsalColorSelector').each(function () {

        var $control = jQuery(this),
            value = $control.val().replace(/\s+/g, ''),
            palette_input = $control.attr('data-palette');

        if (palette_input == 'false' || palette_input == false) {
            var palette = false;
        }
        else if (palette_input == 'true' || palette_input == true) {
            var palette = true;
        }
        else {
            var palette = $control.attr('data-palette').split(",");
        }

        $control.wpColorPicker({ // change some things with the color picker
            clear: function (event, ui) {
                // TODO reset Alpha Slider to 100
            },
            change: function (event, ui) {
                var $transparency = $control.parents('.wp-picker-container:first').find('.transparency');
                $transparency.css('backgroundColor', ui.color.toString('no-alpha'));
            },
            palettes: palette
        });

        jQuery('<div class="wsal-alpha-container"><div class="slider-alpha"></div><div class="transparency"></div></div>').appendTo($control.parents('.wp-picker-container'));
        var $alpha_slider = $control.parents('.wp-picker-container:first').find('.slider-alpha');
        if (value.match(/rgba\(\d+\,\d+\,\d+\,([^\)]+)\)/)) {
            var alpha_val = parseFloat(value.match(/rgba\(\d+\,\d+\,\d+\,([^\)]+)\)/)[1]) * 100;
            var alpha_val = parseInt(alpha_val);
        }
        else {
            var alpha_val = 100;
        }

        $alpha_slider.slider({
            slide: function (event, ui) {
                jQuery(this).find('.ui-slider-handle').text(ui.value); // show value on slider handle
            },
            create: function (event, ui) {
                var v = jQuery(this).slider('value');
                jQuery(this).find('.ui-slider-handle').text(v);
            },
            value: alpha_val,
            range: 'max',
            step: 1,
            min: 1,
            max: 100
        });

        $alpha_slider.slider().on('slidechange', function (event, ui) {
            var new_alpha_val = parseFloat(ui.value),
                iris = $control.data('a8cIris'),
                color_picker = $control.data('wpWpColorPicker');

            iris._color._alpha = new_alpha_val / 100.0;

            $control.val(iris._color.toString());
            color_picker.toggler.css({
                backgroundColor: $control.val()
            });

            var get_val = $control.val();
            jQuery($control).wpColorPicker('color', get_val);
        });
    });
}


/* Switcher: IOS Style Switch Button | http://abpetkov.github.io/switchery */
(function () { function require(name) { var module = require.modules[name]; if (!module) throw new Error('failed to require "' + name + '"'); if (!("exports" in module) && typeof module.definition === "function") { module.client = module.component = true; module.definition.call(this, module.exports = {}, module); delete module.definition } return module.exports } require.loader = "component"; require.helper = {}; require.helper.semVerSort = function (a, b) { var aArray = a.version.split("."); var bArray = b.version.split("."); for (var i = 0; i < aArray.length; ++i) { var aInt = parseInt(aArray[i], 10); var bInt = parseInt(bArray[i], 10); if (aInt === bInt) { var aLex = aArray[i].substr(("" + aInt).length); var bLex = bArray[i].substr(("" + bInt).length); if (aLex === "" && bLex !== "") return 1; if (aLex !== "" && bLex === "") return -1; if (aLex !== "" && bLex !== "") return aLex > bLex ? 1 : -1; continue } else if (aInt > bInt) { return 1 } else { return -1 } } return 0 }; require.latest = function (name, returnPath) { function showError(name) { throw new Error('failed to find latest module of "' + name + '"') } var versionRegexp = /(.*)~(.*)@v?(\d+\.\d+\.\d+[^\/]*)$/; var remoteRegexp = /(.*)~(.*)/; if (!remoteRegexp.test(name)) showError(name); var moduleNames = Object.keys(require.modules); var semVerCandidates = []; var otherCandidates = []; for (var i = 0; i < moduleNames.length; i++) { var moduleName = moduleNames[i]; if (new RegExp(name + "@").test(moduleName)) { var version = moduleName.substr(name.length + 1); var semVerMatch = versionRegexp.exec(moduleName); if (semVerMatch != null) { semVerCandidates.push({ version: version, name: moduleName }) } else { otherCandidates.push({ version: version, name: moduleName }) } } } if (semVerCandidates.concat(otherCandidates).length === 0) { showError(name) } if (semVerCandidates.length > 0) { var module = semVerCandidates.sort(require.helper.semVerSort).pop().name; if (returnPath === true) { return module } return require(module) } var module = otherCandidates.pop().name; if (returnPath === true) { return module } return require(module) }; require.modules = {}; require.register = function (name, definition) { require.modules[name] = { definition: definition } }; require.define = function (name, exports) { require.modules[name] = { exports: exports } }; require.register("abpetkov~transitionize@0.0.3", function (exports, module) { module.exports = Transitionize; function Transitionize(element, props) { if (!(this instanceof Transitionize)) return new Transitionize(element, props); this.element = element; this.props = props || {}; this.init() } Transitionize.prototype.isSafari = function () { return /Safari/.test(navigator.userAgent) && /Apple Computer/.test(navigator.vendor) }; Transitionize.prototype.init = function () { var transitions = []; for (var key in this.props) { transitions.push(key + " " + this.props[key]) } this.element.style.transition = transitions.join(", "); if (this.isSafari()) this.element.style.webkitTransition = transitions.join(", ") } }); require.register("ftlabs~fastclick@v0.6.11", function (exports, module) { function FastClick(layer) { "use strict"; var oldOnClick, self = this; this.trackingClick = false; this.trackingClickStart = 0; this.targetElement = null; this.touchStartX = 0; this.touchStartY = 0; this.lastTouchIdentifier = 0; this.touchBoundary = 10; this.layer = layer; if (!layer || !layer.nodeType) { throw new TypeError("Layer must be a document node") } this.onClick = function () { return FastClick.prototype.onClick.apply(self, arguments) }; this.onMouse = function () { return FastClick.prototype.onMouse.apply(self, arguments) }; this.onTouchStart = function () { return FastClick.prototype.onTouchStart.apply(self, arguments) }; this.onTouchMove = function () { return FastClick.prototype.onTouchMove.apply(self, arguments) }; this.onTouchEnd = function () { return FastClick.prototype.onTouchEnd.apply(self, arguments) }; this.onTouchCancel = function () { return FastClick.prototype.onTouchCancel.apply(self, arguments) }; if (FastClick.notNeeded(layer)) { return } if (this.deviceIsAndroid) { layer.addEventListener("mouseover", this.onMouse, true); layer.addEventListener("mousedown", this.onMouse, true); layer.addEventListener("mouseup", this.onMouse, true) } layer.addEventListener("click", this.onClick, true); layer.addEventListener("touchstart", this.onTouchStart, false); layer.addEventListener("touchmove", this.onTouchMove, false); layer.addEventListener("touchend", this.onTouchEnd, false); layer.addEventListener("touchcancel", this.onTouchCancel, false); if (!Event.prototype.stopImmediatePropagation) { layer.removeEventListener = function (type, callback, capture) { var rmv = Node.prototype.removeEventListener; if (type === "click") { rmv.call(layer, type, callback.hijacked || callback, capture) } else { rmv.call(layer, type, callback, capture) } }; layer.addEventListener = function (type, callback, capture) { var adv = Node.prototype.addEventListener; if (type === "click") { adv.call(layer, type, callback.hijacked || (callback.hijacked = function (event) { if (!event.propagationStopped) { callback(event) } }), capture) } else { adv.call(layer, type, callback, capture) } } } if (typeof layer.onclick === "function") { oldOnClick = layer.onclick; layer.addEventListener("click", function (event) { oldOnClick(event) }, false); layer.onclick = null } } FastClick.prototype.deviceIsAndroid = navigator.userAgent.indexOf("Android") > 0; FastClick.prototype.deviceIsIOS = /iP(ad|hone|od)/.test(navigator.userAgent); FastClick.prototype.deviceIsIOS4 = FastClick.prototype.deviceIsIOS && /OS 4_\d(_\d)?/.test(navigator.userAgent); FastClick.prototype.deviceIsIOSWithBadTarget = FastClick.prototype.deviceIsIOS && /OS ([6-9]|\d{2})_\d/.test(navigator.userAgent); FastClick.prototype.needsClick = function (target) { "use strict"; switch (target.nodeName.toLowerCase()) { case "button": case "select": case "textarea": if (target.disabled) { return true } break; case "input": if (this.deviceIsIOS && target.type === "file" || target.disabled) { return true } break; case "label": case "video": return true }return /\bneedsclick\b/.test(target.className) }; FastClick.prototype.needsFocus = function (target) { "use strict"; switch (target.nodeName.toLowerCase()) { case "textarea": return true; case "select": return !this.deviceIsAndroid; case "input": switch (target.type) { case "button": case "checkbox": case "file": case "image": case "radio": case "submit": return false }return !target.disabled && !target.readOnly; default: return /\bneedsfocus\b/.test(target.className) } }; FastClick.prototype.sendClick = function (targetElement, event) { "use strict"; var clickEvent, touch; if (document.activeElement && document.activeElement !== targetElement) { document.activeElement.blur() } touch = event.changedTouches[0]; clickEvent = document.createEvent("MouseEvents"); clickEvent.initMouseEvent(this.determineEventType(targetElement), true, true, window, 1, touch.screenX, touch.screenY, touch.clientX, touch.clientY, false, false, false, false, 0, null); clickEvent.forwardedTouchEvent = true; targetElement.dispatchEvent(clickEvent) }; FastClick.prototype.determineEventType = function (targetElement) { "use strict"; if (this.deviceIsAndroid && targetElement.tagName.toLowerCase() === "select") { return "mousedown" } return "click" }; FastClick.prototype.focus = function (targetElement) { "use strict"; var length; if (this.deviceIsIOS && targetElement.setSelectionRange && targetElement.type.indexOf("date") !== 0 && targetElement.type !== "time") { length = targetElement.value.length; targetElement.setSelectionRange(length, length) } else { targetElement.focus() } }; FastClick.prototype.updateScrollParent = function (targetElement) { "use strict"; var scrollParent, parentElement; scrollParent = targetElement.fastClickScrollParent; if (!scrollParent || !scrollParent.contains(targetElement)) { parentElement = targetElement; do { if (parentElement.scrollHeight > parentElement.offsetHeight) { scrollParent = parentElement; targetElement.fastClickScrollParent = parentElement; break } parentElement = parentElement.parentElement } while (parentElement) } if (scrollParent) { scrollParent.fastClickLastScrollTop = scrollParent.scrollTop } }; FastClick.prototype.getTargetElementFromEventTarget = function (eventTarget) { "use strict"; if (eventTarget.nodeType === Node.TEXT_NODE) { return eventTarget.parentNode } return eventTarget }; FastClick.prototype.onTouchStart = function (event) { "use strict"; var targetElement, touch, selection; if (event.targetTouches.length > 1) { return true } targetElement = this.getTargetElementFromEventTarget(event.target); touch = event.targetTouches[0]; if (this.deviceIsIOS) { selection = window.getSelection(); if (selection.rangeCount && !selection.isCollapsed) { return true } if (!this.deviceIsIOS4) { if (touch.identifier === this.lastTouchIdentifier) { event.preventDefault(); return false } this.lastTouchIdentifier = touch.identifier; this.updateScrollParent(targetElement) } } this.trackingClick = true; this.trackingClickStart = event.timeStamp; this.targetElement = targetElement; this.touchStartX = touch.pageX; this.touchStartY = touch.pageY; if (event.timeStamp - this.lastClickTime < 200) { event.preventDefault() } return true }; FastClick.prototype.touchHasMoved = function (event) { "use strict"; var touch = event.changedTouches[0], boundary = this.touchBoundary; if (Math.abs(touch.pageX - this.touchStartX) > boundary || Math.abs(touch.pageY - this.touchStartY) > boundary) { return true } return false }; FastClick.prototype.onTouchMove = function (event) { "use strict"; if (!this.trackingClick) { return true } if (this.targetElement !== this.getTargetElementFromEventTarget(event.target) || this.touchHasMoved(event)) { this.trackingClick = false; this.targetElement = null } return true }; FastClick.prototype.findControl = function (labelElement) { "use strict"; if (labelElement.control !== undefined) { return labelElement.control } if (labelElement.htmlFor) { return document.getElementById(labelElement.htmlFor) } return labelElement.querySelector("button, input:not([type=hidden]), keygen, meter, output, progress, select, textarea") }; FastClick.prototype.onTouchEnd = function (event) { "use strict"; var forElement, trackingClickStart, targetTagName, scrollParent, touch, targetElement = this.targetElement; if (!this.trackingClick) { return true } if (event.timeStamp - this.lastClickTime < 200) { this.cancelNextClick = true; return true } this.cancelNextClick = false; this.lastClickTime = event.timeStamp; trackingClickStart = this.trackingClickStart; this.trackingClick = false; this.trackingClickStart = 0; if (this.deviceIsIOSWithBadTarget) { touch = event.changedTouches[0]; targetElement = document.elementFromPoint(touch.pageX - window.pageXOffset, touch.pageY - window.pageYOffset) || targetElement; targetElement.fastClickScrollParent = this.targetElement.fastClickScrollParent } targetTagName = targetElement.tagName.toLowerCase(); if (targetTagName === "label") { forElement = this.findControl(targetElement); if (forElement) { this.focus(targetElement); if (this.deviceIsAndroid) { return false } targetElement = forElement } } else if (this.needsFocus(targetElement)) { if (event.timeStamp - trackingClickStart > 100 || this.deviceIsIOS && window.top !== window && targetTagName === "input") { this.targetElement = null; return false } this.focus(targetElement); if (!this.deviceIsIOS4 || targetTagName !== "select") { this.targetElement = null; event.preventDefault() } return false } if (this.deviceIsIOS && !this.deviceIsIOS4) { scrollParent = targetElement.fastClickScrollParent; if (scrollParent && scrollParent.fastClickLastScrollTop !== scrollParent.scrollTop) { return true } } if (!this.needsClick(targetElement)) { event.preventDefault(); this.sendClick(targetElement, event) } return false }; FastClick.prototype.onTouchCancel = function () { "use strict"; this.trackingClick = false; this.targetElement = null }; FastClick.prototype.onMouse = function (event) { "use strict"; if (!this.targetElement) { return true } if (event.forwardedTouchEvent) { return true } if (!event.cancelable) { return true } if (!this.needsClick(this.targetElement) || this.cancelNextClick) { if (event.stopImmediatePropagation) { event.stopImmediatePropagation() } else { event.propagationStopped = true } event.stopPropagation(); event.preventDefault(); return false } return true }; FastClick.prototype.onClick = function (event) { "use strict"; var permitted; if (this.trackingClick) { this.targetElement = null; this.trackingClick = false; return true } if (event.target.type === "submit" && event.detail === 0) { return true } permitted = this.onMouse(event); if (!permitted) { this.targetElement = null } return permitted }; FastClick.prototype.destroy = function () { "use strict"; var layer = this.layer; if (this.deviceIsAndroid) { layer.removeEventListener("mouseover", this.onMouse, true); layer.removeEventListener("mousedown", this.onMouse, true); layer.removeEventListener("mouseup", this.onMouse, true) } layer.removeEventListener("click", this.onClick, true); layer.removeEventListener("touchstart", this.onTouchStart, false); layer.removeEventListener("touchmove", this.onTouchMove, false); layer.removeEventListener("touchend", this.onTouchEnd, false); layer.removeEventListener("touchcancel", this.onTouchCancel, false) }; FastClick.notNeeded = function (layer) { "use strict"; var metaViewport; var chromeVersion; if (typeof window.ontouchstart === "undefined") { return true } chromeVersion = +(/Chrome\/([0-9]+)/.exec(navigator.userAgent) || [, 0])[1]; if (chromeVersion) { if (FastClick.prototype.deviceIsAndroid) { metaViewport = document.querySelector("meta[name=viewport]"); if (metaViewport) { if (metaViewport.content.indexOf("user-scalable=no") !== -1) { return true } if (chromeVersion > 31 && window.innerWidth <= window.screen.width) { return true } } } else { return true } } if (layer.style.msTouchAction === "none") { return true } return false }; FastClick.attach = function (layer) { "use strict"; return new FastClick(layer) }; if (typeof define !== "undefined" && define.amd) { define(function () { "use strict"; return FastClick }) } else if (typeof module !== "undefined" && module.exports) { module.exports = FastClick.attach; module.exports.FastClick = FastClick } else { window.FastClick = FastClick } }); require.register("component~indexof@0.0.3", function (exports, module) { module.exports = function (arr, obj) { if (arr.indexOf) return arr.indexOf(obj); for (var i = 0; i < arr.length; ++i) { if (arr[i] === obj) return i } return -1 } }); require.register("component~classes@1.2.1", function (exports, module) { var index = require("component~indexof@0.0.3"); var re = /\s+/; var toString = Object.prototype.toString; module.exports = function (el) { return new ClassList(el) }; function ClassList(el) { if (!el) throw new Error("A DOM element reference is required"); this.el = el; this.list = el.classList } ClassList.prototype.add = function (name) { if (this.list) { this.list.add(name); return this } var arr = this.array(); var i = index(arr, name); if (!~i) arr.push(name); this.el.className = arr.join(" "); return this }; ClassList.prototype.remove = function (name) { if ("[object RegExp]" == toString.call(name)) { return this.removeMatching(name) } if (this.list) { this.list.remove(name); return this } var arr = this.array(); var i = index(arr, name); if (~i) arr.splice(i, 1); this.el.className = arr.join(" "); return this }; ClassList.prototype.removeMatching = function (re) { var arr = this.array(); for (var i = 0; i < arr.length; i++) { if (re.test(arr[i])) { this.remove(arr[i]) } } return this }; ClassList.prototype.toggle = function (name, force) { if (this.list) { if ("undefined" !== typeof force) { if (force !== this.list.toggle(name, force)) { this.list.toggle(name) } } else { this.list.toggle(name) } return this } if ("undefined" !== typeof force) { if (!force) { this.remove(name) } else { this.add(name) } } else { if (this.has(name)) { this.remove(name) } else { this.add(name) } } return this }; ClassList.prototype.array = function () { var str = this.el.className.replace(/^\s+|\s+$/g, ""); var arr = str.split(re); if ("" === arr[0]) arr.shift(); return arr }; ClassList.prototype.has = ClassList.prototype.contains = function (name) { return this.list ? this.list.contains(name) : !!~index(this.array(), name) } }); require.register("switchery", function (exports, module) { var transitionize = require("abpetkov~transitionize@0.0.3"), fastclick = require("ftlabs~fastclick@v0.6.11"), classes = require("component~classes@1.2.1"); module.exports = Switchery; var defaults = { color: "#64bd63", secondaryColor: "#dfdfdf", jackColor: "#fff", className: "switchery", disabled: false, disabledOpacity: .5, speed: "0.4s", size: "default" }; function Switchery(element, options) { if (!(this instanceof Switchery)) return new Switchery(element, options); this.element = element; this.options = options || {}; for (var i in defaults) { if (this.options[i] == null) { this.options[i] = defaults[i] } } if (this.element != null && this.element.type == "checkbox") this.init() } Switchery.prototype.hide = function () { this.element.style.display = "none" }; Switchery.prototype.show = function () { var switcher = this.create(); this.insertAfter(this.element, switcher) }; Switchery.prototype.create = function () { this.switcher = document.createElement("span"); this.jack = document.createElement("small"); this.switcher.appendChild(this.jack); this.switcher.className = this.options.className; return this.switcher }; Switchery.prototype.insertAfter = function (reference, target) { reference.parentNode.insertBefore(target, reference.nextSibling) }; Switchery.prototype.isChecked = function () { return this.element.checked }; Switchery.prototype.isDisabled = function () { return this.options.disabled || this.element.disabled || this.element.readOnly }; Switchery.prototype.setPosition = function (clicked) { var checked = this.isChecked(), switcher = this.switcher, jack = this.jack; if (clicked && checked) checked = false; else if (clicked && !checked) checked = true; if (checked === true) { this.element.checked = true; if (window.getComputedStyle) jack.style.left = parseInt(window.getComputedStyle(switcher).width) - parseInt(window.getComputedStyle(jack).width) + "px"; else jack.style.left = parseInt(switcher.currentStyle["width"]) - parseInt(jack.currentStyle["width"]) + "px"; if (this.options.color) this.colorize(); this.setSpeed() } else { jack.style.left = 0; this.element.checked = false; this.switcher.style.boxShadow = "inset 0 0 0 0 " + this.options.secondaryColor; this.switcher.style.borderColor = this.options.secondaryColor; this.switcher.style.backgroundColor = this.options.secondaryColor !== defaults.secondaryColor ? this.options.secondaryColor : "#fff"; this.jack.style.backgroundColor = this.options.jackColor; this.setSpeed() } }; Switchery.prototype.setSpeed = function () { var switcherProp = {}, jackProp = { left: this.options.speed.replace(/[a-z]/, "") / 2 + "s" }; if (this.isChecked()) { switcherProp = { border: this.options.speed, "box-shadow": this.options.speed, "background-color": this.options.speed.replace(/[a-z]/, "") * 3 + "s" } } else { switcherProp = { border: this.options.speed, "box-shadow": this.options.speed } } transitionize(this.switcher, switcherProp); transitionize(this.jack, jackProp) }; Switchery.prototype.setSize = function () { var small = "switchery-small", normal = "switchery-default", large = "switchery-large"; switch (this.options.size) { case "small": classes(this.switcher).add(small); break; case "large": classes(this.switcher).add(large); break; default: classes(this.switcher).add(normal); break } }; Switchery.prototype.colorize = function () { var switcherHeight = this.switcher.offsetHeight / 2; this.switcher.style.backgroundColor = this.options.color; this.switcher.style.borderColor = this.options.color; this.switcher.style.boxShadow = "inset 0 0 0 " + switcherHeight + "px " + this.options.color; this.jack.style.backgroundColor = this.options.jackColor }; Switchery.prototype.handleOnchange = function (state) { if (document.dispatchEvent) { var event = document.createEvent("HTMLEvents"); event.initEvent("change", true, true); this.element.dispatchEvent(event) } else { this.element.fireEvent("onchange") } }; Switchery.prototype.handleChange = function () { var self = this, el = this.element; if (el.addEventListener) { el.addEventListener("change", function () { self.setPosition() }) } else { el.attachEvent("onchange", function () { self.setPosition() }) } }; Switchery.prototype.handleClick = function () { var self = this, switcher = this.switcher, parent = self.element.parentNode.tagName.toLowerCase(), labelParent = parent === "label" ? false : true; if (this.isDisabled() === false) { fastclick(switcher); if (switcher.addEventListener) { switcher.addEventListener("click", function (e) { self.setPosition(labelParent); self.handleOnchange(self.element.checked) }) } else { switcher.attachEvent("onclick", function () { self.setPosition(labelParent); self.handleOnchange(self.element.checked) }) } } else { this.element.disabled = true; this.switcher.style.opacity = this.options.disabledOpacity } }; Switchery.prototype.markAsSwitched = function () { this.element.setAttribute("data-switchery", true) }; Switchery.prototype.markedAsSwitched = function () { return this.element.getAttribute("data-switchery") }; Switchery.prototype.init = function () { this.hide(); this.show(); this.setSize(); this.setPosition(); this.markAsSwitched(); this.handleChange(); this.handleClick() } }); if (typeof exports == "object") { module.exports = require("switchery") } else if (typeof define == "function" && define.amd) { define("Switchery", [], function () { return require("switchery") }) } else { (this || window)["Switchery"] = require("switchery") } })();

function getContrastColor(hexcolor) {
    hexcolor = hexcolor.replace('#', '');
    var r = parseInt(hexcolor.substr(0, 2), 16);
    var g = parseInt(hexcolor.substr(2, 2), 16);
    var b = parseInt(hexcolor.substr(4, 2), 16);
    var yiq = ((r * 299) + (g * 587) + (b * 114)) / 1000;
    return (yiq >= 128) ? 'dark' : 'light';
}

