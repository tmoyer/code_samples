/*
 * @file
 * Scroll to top after ajax calls on solr_views.
 */
(function ($, Drupal, once) {

    Drupal.behaviors.paginationScrollToTop = {
    attach: function (context, settings) {
      let solr_view = $('.view-id-solr_views');
      if ($(window).width() >= 992 && context == solr_view[0]) {
        $(document).ajaxSuccess(function () {
          $("html, body").animate({
            scrollTop: 430,
          }, 0);
        });
      }
    }
  };

})(jQuery, Drupal, once);
