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
/******/ 	return __webpack_require__(__webpack_require__.s = 4);
/******/ })
/************************************************************************/
/******/ ({

/***/ 4:
/***/ (function(module, exports) {

/**
 * Reports script.
 */
jQuery(document).ready(function ($) {
  var reportGenerateNotice = '#mwpal-report-generate-response';
  var reportExtendNotice = '#mwpal-extend-report';

  if ('periodic' === reportsData.reportType) {
    var reportSites = '#mwpal-rep-sites';
    var reportSitesOption = '#mwpal-rb-sites-2';
    var reportEvents = '#mwpal-rep-alert-codes';
    var reportEventsOption = '#mwpal-rb-alert-codes';
    var alertGroups = '.mwpal-alert-groups';
    var sendReportNowBtn = '.report-send-now';
    $(reportSites).select2({
      data: JSON.parse(reportsData.sites),
      placeholder: reportsData.selectSites,
      minimumResultsForSearch: 10,
      multiple: true
    }).on('select2-open', function () {
      var selectValue = $(this).val();

      if (!selectValue.length) {
        $(reportSitesOption).prop('checked', true);
      }
    }).on('select2-removed', function () {
      var selectValue = $(this).val();

      if (!selectValue.length) {
        $('#mwpal-rb-sites-1').prop('checked', true);
      }
    }).on('select2-close', function () {
      var selectValue = $(this).val();

      if (!selectValue.length) {
        $('#mwpal-rb-sites-1').prop('checked', true);
      }
    });
    $(reportEvents).select2({
      data: JSON.parse(reportsData.events),
      placeholder: reportsData.selectEvents,
      minimumResultsForSearch: 10,
      multiple: true
    }).on('select2-open', function (event) {
      var selectValue = $(event).val;

      if (selectValue) {
        $(reportEventsOption).prop('checked', true);
        $('#mwpal-rb-groups').prop('checked', false);
      }
    }).on('select2-selecting', function (event) {
      var selectValue = $(event).val;

      if (selectValue.length) {
        $(reportEventsOption).prop('checked', true);
        $('#mwpal-rb-groups').prop('checked', false);
      }
    }).on('select2-removed', function (e) {
      var selectValue = $(this).val();

      if (!selectValue.length) {
        $(reportEventsOption).prop('checked', false); // if none is checked, check the Select All input

        var checked = $('.mwpal-alert-groups:checked');

        if (!checked.length) {
          $('#mwpal-rb-groups').prop('checked', true);
        }
      }
    });

    if ('' !== reportsData.selectedSites) {
      $(reportSitesOption).prop('checked', true);
      $(reportSites).select2('val', reportsData.selectedSites);
    }

    if ('' !== reportsData.selectedEvents) {
      $(reportEventsOption).prop('checked', true);
      $(reportEvents).select2('val', reportsData.selectedEvents);
    }

    $(alertGroups).on('change', function () {
      if ($(this).is(':checked')) {
        $('#mwpal-rb-groups').prop('checked', false);
      } else {
        var checked = $('.mwpal-alert-groups:checked'); // If none is checked, check the Select All input.

        if (!checked.length) {
          var e = $('#mwpal-rep-alert-codes').select2('val');

          if (!e.length) {
            $('#mwpal-rb-groups').prop('checked', true);
            $(reportEventsOption).prop('checked', false);
          }
        }
      }
    });
    $('#mwpal-rb-groups').on('change', function () {
      if ($(this).is(':checked')) {
        // deselect all
        deselectEventGroups(); // Deselect the alert codes checkbox if selected and no alert codes are provided.

        if ($(reportEventsOption).is(':checked')) {
          if (!$('#mwpal-rep-alert-codes').val().length) {
            $(reportEventsOption).prop('checked', false);
          }
        }
      } else {
        $(this).prop('checked', false); // select first

        $('.mwpal-alert-groups').get(0).prop('checked', true);
      }
    });
    $(reportEventsOption).on('change', function () {
      if ($(this).prop('checked')) {
        $('#mwpal-rb-groups').prop('checked', false);
      } else {
        // If none is checked, check the Select All input.
        var checked = $('.mwpal-alert-groups:checked');

        if (!checked.length) {
          $('#mwpal-rb-groups').prop('checked', true);
        }
      }
    });
    $('#mwpal-rep-users, #mwpal-rep-roles, #mwpal-rep-ip-addresses').on('focus', function () {
      var type = this.getAttribute('id').substr(10);
      jQuery("#mwpal-rb-".concat(type, "-2")).prop('checked', true);
    });
    $('#mwpal-rep-users, #mwpal-rep-roles, #mwpal-rep-ip-addresses').focusout(function () {
      if (!jQuery(this).val()) {
        var type = this.getAttribute('id').substr(10);
        jQuery("#mwpal-rb-".concat(type, "-1")).prop('checked', true);
      }
    });
    createDateRangePicker('#mwpal-rep-start-date');
    createDateRangePicker('#mwpal-rep-end-date');
    disableEventGroups(); // Add required to report email and name

    $('input[name="mwpal-periodic"]').click(function () {
      var valid = true;
      $('#mwpal-notif-email').attr('required', true);
      $('#mwpal-notif-name').attr('required', true);
      var reportEmail = $('#mwpal-notif-email').val();
      var reportName = $('#mwpal-notif-name').val();

      if (!validateEmail(reportEmail)) {
        $('#mwpal-notif-email').css('border-color', '#dd3d36');
        valid = false;
      } else {
        $('#mwpal-notif-email').css('border-color', '#ddd');
      }

      if (!reportName.match(/^[A-Za-z0-9_\s\-]{1,32}$/)) {
        $('#mwpal-notif-name').css('border-color', '#dd3d36');
        valid = false;
      } else {
        $('#mwpal-notif-name').css('border-color', '#ddd');
      }

      return valid;
    });
    $('#mwpal-reports').on('submit', function () {
      // Sites.
      var e = $('#mwpal-rep-sites').val();

      if (!$('#mwpal-rb-sites-1').is(':checked')) {
        if (!e.length) {
          alert(reportsData.siteRequired);
          return false;
        }
      } // Users.


      if (!$('#mwpal-rb-users-1').is(':checked')) {
        e = $('#mwpal-rep-users').val();

        if (!e.length) {
          alert(reportsData.userRequired);
          return false;
        }
      } // Roles.


      if (!$('#mwpal-rb-roles-1').is(':checked')) {
        e = $('#mwpal-rep-roles').val();

        if (!e.length) {
          alert(reportsData.roleRequired);
          return false;
        }
      } // IP addresses.


      if (!$('#mwpal-rb-ip-addresses-1').is(':checked')) {
        e = $('#mwpal-rep-ip-addresses').val();

        if (!e.length) {
          alert(reportsData.ipRequired);
          return false;
        }
      } // Event groups.


      if (!$('#mwpal-rb-groups').is(':checked') && !$('.mwpal-alert-groups:checked').length) {
        if (!$('#mwpal-rep-alert-codes').val().length) {
          alert(reportsData.eventRequired);
          return false;
        }
      }

      return true;
    });
    $(sendReportNowBtn).click(function () {
      ajaxSendReportNow($(this));
    });
  }

  var filters = JSON.parse(reportsData.generateFilters);
  var sites = filters ? filters.sites : [];

  if (typeof sites != 'undefined' && 1 === sites.length && -1 !== sites.indexOf('dashboard')) {// Do nothing.
  } else {
    jQuery(reportExtendNotice).show();
  }

  jQuery("".concat(reportExtendNotice, " input[type=button]")).click(function () {
    var isExtend = jQuery(this).data('extend');

    if (!isExtend && reportsData.generateNow) {
      if ('periodic' === reportsData.reportType) {
        jQuery(reportExtendNotice).hide();
        jQuery(reportGenerateNotice).removeAttr('style');
        ajaxGenerateReport(reportsData.generateFilters);
      }
    } else if (isExtend && reportsData.generateNow) {
      if ('periodic' === reportsData.reportType) {
        jQuery(reportExtendNotice).hide();
        jQuery(reportGenerateNotice).removeAttr('style');
        ajaxGenerateReport(reportsData.generateFilters, null, true);
      }
    }
  });
});
/**
 * Deselect alert groups.
 */

function deselectEventGroups() {
  jQuery('.mwpal-alert-groups').each(function () {
    jQuery(this).prop('checked', false);
  });
}
/**
 * Criteria disables all the alert codes.
 */


function disableEventGroups() {
  jQuery('#mwpal-rb-alert-groups').find('input').each(function () {
    jQuery(this).attr('disabled', false);
  });
}
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
      format: reportsData.dateFormat
    }
  });
  jQuery(inputDate).val('').attr('autocomplete', 'off');
}
/**
 * Validate email for reports.
 *
 * @param {string} email Email.
 */


function validateEmail(email) {
  var atpos = email.indexOf('@');
  var dotpos = email.lastIndexOf('.');

  if (1 > atpos || dotpos < atpos + 2 || dotpos + 2 >= email.length) {
    return false;
  } else {
    return true;
  }
}
/**
 * Generate report AJAX handler.
 *
 * @param {string} filters JSON string of filters.
 * @param {string} nextDate Date for the next report.
 * @param {boolean} liveReport Set to true if report is live from child sites.
 */


function ajaxGenerateReport(filters) {
  var nextDate = arguments.length > 1 && arguments[1] !== undefined ? arguments[1] : null;
  var liveReport = arguments.length > 2 && arguments[2] !== undefined ? arguments[2] : false;
  var reportGenerateNotice = '#mwpal-report-generate-response';
  jQuery.ajax({
    type: 'POST',
    url: reportsData.ajaxUrl,
    async: true,
    dataType: 'json',
    data: {
      action: 'generate_periodic_report',
      security: reportsData.security,
      filters: JSON.parse(filters),
      nextDate: nextDate,
      limit: reportsData.reportsLimit,
      liveReport: liveReport
    },
    success: function success(response) {
      nextDate = response[0];

      if (0 != nextDate) {
        var dateString = nextDate;
        dateString = dateString.split('.');
        var lastDate = new Date(dateString[0] * 1000);
        jQuery("".concat(reportGenerateNotice, " span")).html(' Last day examined: ' + lastDate.toDateString() + ' last day.');
        ajaxGenerateReport(filters, nextDate, liveReport);
      } else {
        jQuery("".concat(reportGenerateNotice, " .mwpal-lds-dual-ring")).hide();

        if (null !== response[1]) {
          jQuery("".concat(reportGenerateNotice, " p")).html(reportsData.processComplete);
          window.setTimeout(function () {
            window.location.href = response[1];
          }, 1000);
        } else {
          jQuery("".concat(reportGenerateNotice, " p")).html(reportsData.noMatchEvents);
        }
      }
    },
    error: function error(xhr, textStatus, _error) {
      jQuery("".concat(reportGenerateNotice, " .mwpal-lds-dual-ring")).hide();
      jQuery("".concat(reportGenerateNotice, " p")).html(textStatus);
      console.log(xhr.statusText);
      console.log(textStatus);
      console.log(_error);
    }
  });
}
/**
 * Manual periodic report send now AJAX handler.
 *
 * @param {object} btn Send now button.
 * @param {mixed} nextDate Next date for the report.
 */


function ajaxSendReportNow(btn) {
  var nextDate = arguments.length > 1 && arguments[1] !== undefined ? arguments[1] : null;
  var reportName = btn.data('report-name');
  btn.attr('disabled', true);
  btn.val(reportsData.sendingReport);
  jQuery.ajax({
    type: 'POST',
    url: reportsData.ajaxUrl,
    async: true,
    dataType: 'json',
    data: {
      action: 'mwpal_send_periodic_report',
      security: reportsData.security,
      reportName: reportName,
      nextDate: nextDate,
      limit: reportsData.reportsLimit
    },
    success: function success(response) {
      nextDate = response;

      if (0 != nextDate) {
        ajaxSendReportNow(name, nextDate);
      } else {
        btn.val(reportsData.reportSent);
      }
    },
    error: function error(xhr, textStatus, _error2) {
      console.log(xhr.statusText);
      console.log(textStatus);
      console.log(_error2);
    }
  });
}

/***/ })

/******/ });