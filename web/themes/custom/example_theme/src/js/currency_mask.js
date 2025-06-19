/**
 * @file
 * Handles applying input mask to the phone field.
 */
(function ($, Drupal, once) {

  if ($("#billing-form").length) {
    $("input").inputmask();

    $(document).ajaxComplete(function( event, request, settings ) {
      $("input").inputmask();
    });

  }

})(jQuery, Drupal, once);
