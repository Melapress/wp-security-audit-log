/******/ (function(modules) { // webpackBootstrap
/******/ 	// The module cache
/******/ 	var installedModules = {};
/******/
/******/ 	// The require function
/******/ 	function __webpack_require__(moduleId) {
/******/
/******/ 		// Check if module is in cache
/******/ 		if(installedModules[moduleId]) {
/******/ 			return installedModules[moduleId].exports;
/******/ 		}
/******/ 		// Create a new module (and put it into the cache)
/******/ 		var module = installedModules[moduleId] = {
/******/ 			i: moduleId,
/******/ 			l: false,
/******/ 			exports: {}
/******/ 		};
/******/
/******/ 		// Execute the module function
/******/ 		modules[moduleId].call(module.exports, module, module.exports, __webpack_require__);
/******/
/******/ 		// Flag the module as loaded
/******/ 		module.l = true;
/******/
/******/ 		// Return the exports of the module
/******/ 		return module.exports;
/******/ 	}
/******/
/******/
/******/ 	// expose the modules object (__webpack_modules__)
/******/ 	__webpack_require__.m = modules;
/******/
/******/ 	// expose the module cache
/******/ 	__webpack_require__.c = installedModules;
/******/
/******/ 	// define getter function for harmony exports
/******/ 	__webpack_require__.d = function(exports, name, getter) {
/******/ 		if(!__webpack_require__.o(exports, name)) {
/******/ 			Object.defineProperty(exports, name, { enumerable: true, get: getter });
/******/ 		}
/******/ 	};
/******/
/******/ 	// define __esModule on exports
/******/ 	__webpack_require__.r = function(exports) {
/******/ 		if(typeof Symbol !== 'undefined' && Symbol.toStringTag) {
/******/ 			Object.defineProperty(exports, Symbol.toStringTag, { value: 'Module' });
/******/ 		}
/******/ 		Object.defineProperty(exports, '__esModule', { value: true });
/******/ 	};
/******/
/******/ 	// create a fake namespace object
/******/ 	// mode & 1: value is a module id, require it
/******/ 	// mode & 2: merge all properties of value into the ns
/******/ 	// mode & 4: return value when already ns object
/******/ 	// mode & 8|1: behave like require
/******/ 	__webpack_require__.t = function(value, mode) {
/******/ 		if(mode & 1) value = __webpack_require__(value);
/******/ 		if(mode & 8) return value;
/******/ 		if((mode & 4) && typeof value === 'object' && value && value.__esModule) return value;
/******/ 		var ns = Object.create(null);
/******/ 		__webpack_require__.r(ns);
/******/ 		Object.defineProperty(ns, 'default', { enumerable: true, value: value });
/******/ 		if(mode & 2 && typeof value != 'string') for(var key in value) __webpack_require__.d(ns, key, function(key) { return value[key]; }.bind(null, key));
/******/ 		return ns;
/******/ 	};
/******/
/******/ 	// getDefaultExport function for compatibility with non-harmony modules
/******/ 	__webpack_require__.n = function(module) {
/******/ 		var getter = module && module.__esModule ?
/******/ 			function getDefault() { return module['default']; } :
/******/ 			function getModuleExports() { return module; };
/******/ 		__webpack_require__.d(getter, 'a', getter);
/******/ 		return getter;
/******/ 	};
/******/
/******/ 	// Object.prototype.hasOwnProperty.call
/******/ 	__webpack_require__.o = function(object, property) { return Object.prototype.hasOwnProperty.call(object, property); };
/******/
/******/ 	// __webpack_public_path__
/******/ 	__webpack_require__.p = "";
/******/
/******/
/******/ 	// Load entry module and return exports
/******/ 	return __webpack_require__(__webpack_require__.s = 0);
/******/ })
/************************************************************************/
/******/ ([
/* 0 */
/***/ (function(module, exports) {

/**
 * Entry Point
 *
 * @since 0.1.0
 */
// Import styles.
// import '../../css/src/styles.scss';
jQuery(document).ready(function () {
  var mwpalLoadEventsResponse = true; // Global variable to check events loading response.
  // select2 for site selection select input.

  if ('activity-log' === scriptData.currentTab) {
    jQuery('.mwp-ssas').select2({
      width: 313
    });
  }
  /**
   * Site events switch handler.
   */


  jQuery('.mwp-ssas').on('change', function () {
    var value = jQuery(this).val();
    jQuery('#mwpal-site-id').val(value);
    jQuery('#audit-log-viewer').submit();
  });
  /**
   * Number of events switch handler.
   */

  jQuery('.mwp-ipps').on('change', function () {
    var value = jQuery(this).val();
    jQuery(this).attr('disabled', true);
    jQuery.post(scriptData.ajaxURL, {
      action: 'set_per_page_events',
      count: value,
      nonce: scriptData.scriptNonce
    }, function () {
      location.reload();
    });
  });

  var append_site_to_the_list = function append_site_to_the_list(site, targetElm, excludedSitesElm) {
    if (excludedSitesElm.find('input[value="' + site.id + '"]').length > 0) {
      //	the site is in the list of excluded sites, no need to add it
      return;
    }

    targetElm.append('<span>' + '<input id="mwpal-wcs-site-' + site.id + '" name="mwpal-wcs[]" value="' + site.id + '" type="checkbox">\n' + '<label for="mwpal-wcs-site-' + site.id + '">' + site.name + '</label>\n' + '</span>');
  };
  /**
   * Refresh WSAL Child Sites.
   */


  jQuery('#mwpal-wsal-sites-refresh').click(function () {
    var leftPane = jQuery('.js-sites-container-left');
    var rightPane = jQuery('.js-sites-container-right');

    if (leftPane.attr('data-list-cleared') != 'yes') {
      leftPane.empty();
      leftPane.attr('data-list-cleared', 'yes');
    }

    var refreshBtn = jQuery(this);
    var refreshMsg = jQuery('#mwpal-wcs-refresh-message');
    refreshBtn.attr('disabled', 'disabled');
    refreshBtn.val(scriptData.refreshing);
    jQuery(refreshMsg).show();
    jQuery.post(scriptData.ajaxURL, {
      action: 'refresh_child_sites',
      nonce: scriptData.scriptNonce,
      mwpal_forced: true,
      mwpal_run_id: scriptData.runId
    }, function (response) {
      console.log(response);

      if (response.success) {
        scriptData.runId = response.data.run_id; // if we are complete then reload the page.

        if (response.data.complete === true) {
          //	re-enable the button
          refreshBtn.removeAttr('disabled');
          refreshBtn.val(refreshBtn.attr('data-title'));
          leftPane.removeAttr('data-list-cleared', 'yes');
          refreshMsg.hide();
        } else {
          // indicate progress by showing a date of last message.
          var d = new Date();
          jQuery(refreshMsg).find('.last-message-time').html(d.getHours() + ':' + d.getMinutes() + ':' + d.getSeconds());
          jQuery(refreshBtn).trigger('click'); //	update the sites lists

          response.data.sites.forEach(function (element) {
            append_site_to_the_list(element, leftPane, rightPane);
          });
        }
      }
    });
  });
  /**
   * Retrieve Logs Manually
   */

  jQuery('#mwpal-wsal-manual-retrieve').click(function () {
    var retrieveBtn = jQuery(this);
    retrieveBtn.attr('disabled', true);
    retrieveBtn.val(scriptData.retrieving);
    jQuery.post(scriptData.ajaxURL, {
      action: 'retrieve_events_manually',
      nonce: scriptData.scriptNonce
    }, function () {
      location.reload();
    });
  });
  /**
   * Add Sites to Active Activity Log.
   */

  jQuery('#mwpal-wcs-add-btn').click(function (e) {
    e.preventDefault();
    var addSites = jQuery('#mwpal-wcs input[type=checkbox]'); // Get checkboxes.

    transferSites('mwpal-wcs', 'mwpal-wcs-al', addSites, 'add-sites');
  });
  /**
   * Remove Sites from Active Activity Log.
   */

  jQuery('#mwpal-wcs-remove-btn').click(function (e) {
    e.preventDefault();
    var removeSites = jQuery('#mwpal-wcs-al input[type=checkbox]'); // Get checkboxes.

    transferSites('mwpal-wcs-al', 'mwpal-wcs', removeSites, 'remove-sites');
  });

  function moveSelectedSites(fromClass, toClass, container, selectedSites, activeWSALSitesElm, activeSites) {
    for (var index = 0; index < selectedSites.length; index++) {
      var spanElement = jQuery('<span></span>');
      var inputElement = jQuery('<input />');
      inputElement.attr('type', 'checkbox');
      var labelElement = jQuery('<label></label>');
      var tempElement = jQuery("#".concat(fromClass, "-site-").concat(selectedSites[index])); // Prepare input element.

      inputElement.attr('name', "".concat(toClass, "[]"));
      inputElement.attr('id', "".concat(toClass, "-site-").concat(selectedSites[index]));
      inputElement.attr('value', tempElement.val()); // Prepare label element.

      labelElement.attr('for', "".concat(toClass, "-site-").concat(selectedSites[index]));
      labelElement.html(tempElement.parent().find('label').text()); // Append the elements together.

      spanElement.append(inputElement);
      spanElement.append(labelElement);
      container.append(spanElement); // Remove the temp element.

      tempElement.parent().remove();
    }

    activeWSALSitesElm.val(activeSites);
  }
  /**
   * Transfer sites in and out of active activity log.
   *
   * @param {string} fromClass     – From HTML class.
   * @param {string} toClass       – To HTML class.
   * @param {array} containerSites – Sites to add/remove.
   * @param {string} action        – Type of action to perform.
   */


  function transferSites(fromClass, toClass, containerSites, action) {
    var selectedSites = [];
    var container = jQuery("#".concat(toClass, " .sites-container"));
    var activeWSALSites = jQuery('#mwpal-wsal-child-sites');

    for (var index = 0; index < containerSites.length; index++) {
      if (jQuery(containerSites[index]).is(':checked')) {
        selectedSites.push(jQuery(containerSites[index]).val());
      }
    } //	skip AJAX if disabled


    var ajaxDisabledAttr = jQuery('#mwpal-wcs-btns').attr('data-disable-ajax');
    var ajaxDisabled = 'yes' == ajaxDisabledAttr;

    if (ajaxDisabled) {
      var activeSitesVal = activeWSALSites.val();
      var activeSites = activeSitesVal.length == 0 ? [] : activeSitesVal.split(',');

      if (action === 'add-sites') {
        activeSites = activeSites.concat(selectedSites);
      } else {
        activeSites = activeSites.filter(function (x) {
          return !selectedSites.includes(x);
        });
      }

      moveSelectedSites(fromClass, toClass, container, selectedSites, activeWSALSites, activeSites);
      return;
    } //	carry on with AJAX if not disabled


    jQuery.ajax({
      type: 'POST',
      url: scriptData.ajaxURL,
      async: true,
      dataType: 'json',
      data: {
        action: 'update_active_wsal_sites',
        nonce: scriptData.scriptNonce,
        transferAction: action,
        activeSites: activeWSALSites.val(),
        requestSites: selectedSites.toString()
      },
      success: function success(data) {
        if (data.success && selectedSites.length) {
          moveSelectedSites(fromClass, toClass, container, selectedSites, activeWSALSites, data.activeSites);
        } else {
          console.log(data.message);
        }
      },
      error: function error(xhr, textStatus, _error) {
        console.log(xhr.statusText);
        console.log(textStatus);
        console.log(_error);
      }
    });
  }
  /**
   * Load Events for Infinite Scroll.
   *
   * @since 1.0.3
   *
   * @param {integer} pageNumber - Log viewer page number.
   */


  function mwpalLoadEvents(pageNumber) {
    jQuery('#mwpal-event-loader').show('fast');
    /*
     * Gets the view type. Defaults to 'list' but could be 'grid'. Only
     * those 2 types are supported. Validation handled server side.
     */

    var view = scriptData.userView;

    if (null === view || view.length < 1) {
      view = 'list';
    }

    jQuery.ajax({
      type: 'POST',
      url: ajaxurl,
      data: {
        action: 'mwpal_infinite_scroll_events',
        mwpal_viewer_security: scriptData.scriptNonce,
        page_number: pageNumber,
        page: scriptData.page,
        'mwpal-site-id': scriptData.siteId,
        orderby: scriptData.orderBy,
        order: scriptData.order,
        'get-events': scriptData.getEvents,
        s: scriptData.searchTerm,
        filters: scriptData.searchFilters,
        view: view
      },
      success: function success(html) {
        jQuery('#mwpal-event-loader').hide('1000');

        if (html) {
          mwpalLoadEventsResponse = true;
          jQuery('#audit-log-viewer #the-list').append(html); // This will be the div where our content will be loaded.
        } else {
          mwpalLoadEventsResponse = false;
          jQuery('#mwpal-auditlog-end').show('fast');
        }
      },
      error: function error(xhr, textStatus, _error2) {
        console.log(xhr.statusText);
        console.log(textStatus);
        console.log(_error2);
      }
    });

    if (mwpalLoadEventsResponse) {
      return pageNumber + 1;
    }

    return 0;
  }
  /**
   * Load events for Infinite Scroll.
   *
   * @since 1.0.3
   */


  if (jQuery('#audit-log-viewer').length > 0 && scriptData.infiniteScroll) {
    var count = 2;
    jQuery(window).scroll(function () {
      if (jQuery(window).scrollTop() === jQuery(document).height() - jQuery(window).height()) {
        if (0 !== count) {
          count = mwpalLoadEvents(count);
        }
      }
    });
  }
  /**
   * Select all events toggle handling code.
   *
   * @since 1.0.4
   */


  jQuery('#mwpal-toggle-events-table>thead>tr>th>:checkbox').change(function () {
    jQuery(this).parents('table:first').find('tbody>tr>th>:checkbox').attr('checked', this.checked);
  });
  /**
   * Events toggle handling code.
   *
   * @since 1.0.4
   */

  jQuery('#mwpal-toggle-events-table>tbody>tr>th>:checkbox').change(function () {
    var allchecked = 0 === jQuery(this).parents('tbody:first').find('th>:checkbox:not(:checked)').length;
    jQuery(this).parents('table:first').find('thead>tr>th:first>:checkbox:first').attr('checked', allchecked);
  });
  jQuery('#purge-trigger').on('click', {}, function () {
    var pruneButton = jQuery(this);
    jQuery(pruneButton).attr("disabled", true);
    jQuery.post(ajaxurl, {
      action: 'mwpal_purge_logs',
      mwp_nonce: scriptData.scriptNonce
    }, 'json').fail(function (error) {
      console.log(error);
    }).success(function (msg) {
      console.log(msg);
      jQuery("#log-purged-popup").modal('show');
      jQuery(pruneButton).attr("disabled", false);
    });
  });
  jQuery('.close-log-purged-popup').on('click', {}, function () {
    jQuery("#log-purged-popup").modal('hide');
  });
});

/***/ })
/******/ ]);