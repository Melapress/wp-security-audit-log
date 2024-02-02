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
/******/ 	return __webpack_require__(__webpack_require__.s = 3);
/******/ })
/************************************************************************/
/******/ ({

/***/ 3:
/***/ (function(module, exports) {

/**
 * Search.
 */
window.mwpalSearch = function (window) {
  var searchObj = window;
  var attachEvents = [];
  /**
   * Attach event to search object.
   */

  searchObj.attach = function (callBack) {
    if ('undefined' === typeof callBack) {
      for (var i = 0; i < attachEvents.length; i++) {
        attachEvents[i](); // Execute callbacks.
      }
    } else {
      attachEvents.push(callBack); // Add callbacks.
    }
  };
  /**
   * Initialize search object.
   */


  searchObj.attach(function () {
    searchObj.list = '#mwpal-search-list';
    searchObj.activeFilters = [];
    searchObj.clearSearchBtn = '#mwpal-clear-search';
    searchObj.textSearchId = '#mwpal-search-box-search-input';
    jQuery(searchObj.textSearchId).keypress(function (event) {
      if (13 === event.which) {
        jQuery('#audit-log-viewer').submit();
      }

      jQuery(searchObj.clearSearchBtn).removeAttr('disabled');
    });

    if (' ' !== jQuery(searchObj.textSearchId).val()) {
      jQuery(searchObj.clearSearchBtn).removeAttr('disabled');
    }
  });
  /**
   * Add filter.
   */

  searchObj.addFilter = function (text) {
    var customList = arguments.length > 1 && arguments[1] !== undefined ? arguments[1] : false;
    var filter = text.split(':');
    var filtersList;

    if (!customList) {
      filtersList = jQuery(searchObj.list);
      searchObj.activeFilters.push(text);
    } else {
      filtersList = jQuery(customList);
    }

    if ('from' === filter[0] || 'to' === filter[0] || 'on' === filter[0]) {
      if (!searchObj.checkDate(filter[1])) {
        // Validation date format.
        return;
      }
    }

    if (!jQuery('input[name="filters[]"][value="' + text + '"]').length) {
      filtersList.append(jQuery('<span/>').append(jQuery('<input type="text" name="filters[]"/>').val(text), jQuery('<a href="javascript:;" title="' + searchScriptData.remove + '">&times;</a></span>').click(function () {
        jQuery(this).parents('span:first').fadeOut('fast', function () {
          jQuery(this).remove();
          searchObj.countFilters(jQuery(filtersList));
        });
      })));
      jQuery(searchObj.clearSearchBtn).removeAttr('disabled');
    }

    searchObj.countFilters(jQuery(filtersList));
  };
  /**
   * Update filter count.
   */


  searchObj.countFilters = function () {
    var customList = arguments.length > 0 && arguments[0] !== undefined ? arguments[0] : false;
    var filtersList = false === customList ? jQuery(searchObj.list) : jQuery(customList);
    var count = filtersList.find('>span').length;

    if (0 === count) {
      filtersList.addClass('no-filters');
    } else {
      filtersList.removeClass('no-filters');
    }
  };
  /**
   * Check date.
   *
   * @param {string} value Date value.
   */


  searchObj.checkDate = function (value) {
    var regularExp;

    if ('MM-DD-YYYY' == searchScriptData.dateFormat || 'DD-MM-YYYY' == searchScriptData.dateFormat) {
      regularExp = /^(\d{1,2})-(\d{1,2})-(\d{4})$/; // Regular expression to match date format mm-dd-yyyy or dd-mm-yyyy.
    } else {
      regularExp = /^(\d{4})-(\d{1,2})-(\d{1,2})$/; // Regular expression to match date format yyyy-mm-dd.
    }

    if ('' != value && !value.match(regularExp)) {
      return false;
    }

    return true;
  };
  /**
   * Clear search text and filters.
   */


  searchObj.clearSearch = function () {
    jQuery(searchObj.list).empty();
    jQuery(searchObj.textSearchId).removeAttr('value');
  };

  return searchObj;
}(window);

jQuery(document).ready(function ($) {
  var liveSearchNotice = '#mwpal-live-search-notice';
  var liveSearchNoticeDismiss = "".concat(liveSearchNotice, " .dismiss");
  var liveSearchNoticeSaveDismiss = "".concat(liveSearchNotice, " .dismiss-save");
  window.mwpalSearch.attach();
  createDateRangePicker('#mwpal_search_widget_from');
  createDateRangePicker('#mwpal_search_widget_to');
  createDateRangePicker('#mwpal_search_widget_on');
  $('.mwpal-add-button').click(function (event) {
    event.preventDefault();
    var filterInput = $(this).parent().find('input');
    addToFilterList(filterInput);
  });
  $('.mwpal_widget_select_single').change(function () {
    var filterInput = $(this);
    var filterPrefix = filterInput.data('prefix');

    if (!filterInput.val()) {
      return;
    }

    window.mwpalSearch.addFilter("".concat(filterPrefix, ":").concat(filterInput.val()));
  });
  $(window.mwpalSearch.clearSearchBtn).click(function (event) {
    event.preventDefault();
    window.mwpalSearch.clearSearch(); // Get URL.

    var locationURL = window.location.href;
    var searchStr = searchScriptData.extensionName;
    var searchIndex = locationURL.search(searchStr); // Search for wsal-auditlog value in URL.

    searchIndex += searchStr.length; // Add the length of the searched string to the index.

    window.location.href = locationURL.substr(0, searchIndex); // Redirect.
  });
  $(liveSearchNoticeDismiss).click(function () {
    $(liveSearchNotice).hide();
  });
  $(liveSearchNoticeSaveDismiss).click(function () {
    var requestUrl = "".concat(searchScriptData.adminAjax, "?action=mwpal_dismiss_live_search_notice&security=").concat(searchScriptData.security);
    var requestParams = {
      method: 'GET'
    };
    fetch(requestUrl, requestParams).then(function (response) {
      return response.json();
    }).then(function (data) {
      if (data) {
        $(liveSearchNotice).hide();
      }
    })["catch"](function (error) {
      console.log(error);
    });
  }); // show/hide the filter box.

  jQuery('#filter-container-toggle').click(function (event) {
    var button = jQuery(this);
    var filterContainter = jQuery('#almwp-filters-container');
    jQuery(button.parent().parent()).addClass('filters-opened');
    button.html(searchScriptData.filterBtnOpen);
    filterContainter.slideToggle('600', function () {
      if (jQuery(filterContainter).is(':visible')) {
        jQuery(button).addClass('active');
      } else {
        jQuery(button).removeClass('active');
        jQuery(button.parent().parent()).removeClass('filters-opened');
        button.html(searchScriptData.filterBtnClose);
      }
    });
  }); // hide all the groupped inputs.

  jQuery('.almwp-filter-group-inputs .filter-wrap').hide(); // bind a change function to these select inputs.

  jQuery('.almwp-filter-group-select select').change(function () {
    var options = jQuery(this).children();
    var selected = jQuery(this).children('option:selected').val();
    jQuery(options).each(function () {
      var item = jQuery(this).val();

      if (item === selected) {
        jQuery('.almwp-filter-wrap-' + selected).show();
      } else {
        jQuery('.almwp-filter-wrap-' + item).hide();
      }
    });
    jQuery('.wsal-filter-wrap-' + selected).show();
  }); // fire the a change on each of the input group selects.

  jQuery('.almwp-filter-group-select select').each(function () {
    jQuery(this).change();
  });
  var submitButton = document.getElementById('almwp-search-submit');
  submitButton.outerHTML = submitButton.outerHTML.replace(/^\<input/, '<button') + submitButton.value + '</button>';
  jQuery('#almwp-search-submit').addClass('dashicons-before dashicons-search');
  jQuery('#almwp-search-submit').attr('type', 'submit');
  $('#mwpal-search-box-search-input').focus(function () {
    $('#almwp-search-submit').addClass('active');
  }).blur(function () {
    $('#almwp-search-submit').removeClass('active');
  }); // Delay the filter change checker so it doesn't fire when initial filters
  // are loaded in.

  var filterNoticeDelay = window.setTimeout(function () {
    var filterNoticeSessionClosed = false; // Fire on change of the filters area.

    jQuery('body').on('DOMSubtreeModified', '.mwpal-search-filters-list', function () {
      var filterNoticeZone = jQuery('.almwp-filter-notice-zone');

      if (filterNoticeSessionClosed !== false || $(filterNoticeZone).is(':visible')) {
        return;
      }

      jQuery('.almwp-notice-message').html(searchScriptData.filterChangeMsg);
      jQuery(filterNoticeZone).addClass('notice notice-error almwp-admin-notice is-dismissible');
      jQuery(filterNoticeZone).slideDown();
    });
    jQuery('.almwp-filter-notice-zone .notice-dismiss').click(function () {
      jQuery(this).parent().slideUp();
      filterNoticeSessionClosed = true;
    });
    jQuery('#almwp-filter-notice-permanant-dismiss').click(function () {
      jQuery.ajax({
        url: ajaxurl,
        type: 'POST',
        data: {
          notice: 'search-filters-changed',
          action: 'mwpal_user_notice_dismissed',
          nonce: searchScriptData.security
        },
        dataType: 'json'
      });
      jQuery(this).parent().parent().slideUp();
      filterNoticeSessionClosed = true;
    });
  }, 500);
});
/**
 * Create date calendar selector.
 *
 * @param {string} inputDate Date element ID.
 */

function createDateRangePicker(inputDate) {
  jQuery(inputDate).daterangepicker({
    singleDatePicker: true,
    showDropdowns: true,
    minDate: '2000-01-01',
    maxDate: moment().format('YYYY-MM-DD'),
    locale: {
      format: searchScriptData.dateFormat
    }
  });
  jQuery(inputDate).val('').attr('autocomplete', 'off');
}
/**
 * Add filter to filters list.
 *
 * @param {element} filterInput Filter input element.
 */


function addToFilterList(filterInput) {
  var filterValue = filterInput.val();
  var filterPrefix = filterInput.data('prefix');

  if (0 === filterValue.length) {
    return;
  }

  window.mwpalSearch.addFilter("".concat(filterPrefix, ":").concat(filterValue));
  filterInput.removeAttr('value');
}

/***/ })

/******/ });