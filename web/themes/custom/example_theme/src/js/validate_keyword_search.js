/**
 * @file
 * Validation of at least 2 letters in keyword field for search before submitting.
 */
(function ($, Drupal, once) {
  Drupal.behaviors.keywordValidateSubmit = {
    attach: function attach(context, settings) {

      // Make all search forms on the page disabled
      // unless at least 2 characters are entered.
      once('KeywordInput', 'form[id^="views-exposed-form-solr-views-page"]', context).forEach(function (form) {
        var keyInput = $(form).find('[id^="edit-keys"].form-control');
        $.each(keyInput, function(k, term) {

          const keyword = $(term)[0];
          $(keyword).after("<span class='error'></span>");
          const keywordError = $('[id^="views-exposed-form-solr-views-page"] span.error');

          // Disable submit until users enters text.
          if ($(form).find('[id^="edit-keys"].form-control').val().length == 0) {
            $(form).find('[id^="edit-submit-solr-views"]').prop("disabled", true);
          }

          keyword.addEventListener("input", (event) => {
            if (keyword.validity.valid) {
              keywordError.textContent = "";
              keywordError.className = "error";
              removeError();
            } else {
              showError();
            }

            // Disable submit if keyword input goes below 2 characters.
            if ($(form).find('[id^="edit-keys"].form-control').val().length == 0) {
              $(form).find('[id^="edit-submit-solr-views"]').prop("disabled", true);
            }
          });

          form.addEventListener("submit", (event) => {
            // if the keyword field is valid, we let the form submit
            if (!keyword.validity.valid) {
              // If it isn't, we display an appropriate error message
              showError();
              // Then we prevent the form from being sent by canceling the event
              event.preventDefault();
            }
          });

          function showError() {
            if (keyword.validity.valueMissing) {
              // If the field is empty,
              // display the following error message.
              keywordError.textContent = "You need to enter a keyword.";
            } else if (keyword.validity.tooShort) {
              // If the data is too short,
              // display the following error message.
              // Commenting out keywordError text for now.
              //$(keywordError).text(`Enter at least ${keyword.minLength} characters to search`);
              $(form).find('[id^="edit-submit-solr-views"]').prop("disabled", true);
            }

            // Set the styling appropriately
            keywordError.className = "error active";
          }

          function removeError() {
            $(form).find('button').prop("disabled", false);
            $(keywordError).text('');
          }
        });

      });

    }
  };
})(jQuery, Drupal, once);
