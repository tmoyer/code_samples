(function ($, Drupal, drupalSettings) {
  Drupal.behaviors.iframeLogin = {
    attach: function () {
      var iframe_src = $('#rc-embed').attr('src');
      if (iframe_src.endsWith('?resumeToken=')) {
        if (window.location.href.indexOf('reload')==-1) {
          var params = new window.URLSearchParams(window.location.search);
          var token = params.get('resumeToken');
          window.location.replace(window.location.href + '?reload');
        }
      }
    }
  };
})(jQuery, Drupal, drupalSettings);
