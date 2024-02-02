/**
 * Enforced settings script.
 */
jQuery(document).ready(function ($) {

  $('input[name="enforce_settings_on_subsites"]').on('change', function () {
    const wrapper = $(this).closest('fieldset').find('.postbox')
    const value = $(this).val()
    if ('some' === value) {
      wrapper.slideDown()
    } else {
      wrapper.slideUp()
    }
  })

  $('.js-mwpal-disabled-events').select2({
    data: JSON.parse(mwpal_enforced_settings.events),
    placeholder: mwpal_enforced_settings.selectEvents,
    minimumResultsForSearch: 10,
    multiple: true
  })

  $('input[name="login-page-notification"]').on('change', function () {
    const value = $(this).val()
    const textarea = $('textarea[name="login-page-notification-text"]')
    if (value === 'yes') {
      textarea.removeAttr('disabled')
    } else {
      textarea.attr('disabled', true)
    }
  })
})
