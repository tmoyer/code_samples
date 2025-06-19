(function ($) {
  $(document).ready(function() {
    // Create the <select> element with options
    var selectOptions = 'Inquiring About: <div class="select-dropdown"><select id="redirectSelect" name="redirectSelect">' +
                          '<option value="">Please Select One</option>' +
                          '<option value="ask-us-question">Ask Us a Question</option>' +
                          '<option value="submit-tip">Submit a Tip</option>' +
                          '<option value="employment-inquiry">Employment Inquiry</option>' +
                          '<option value="lost-found-inquiry">Lost & Found Inquiry</option>' +
                          '<option value="special-event-inquiry">Special Event Inquiry</option>' +
                         '</select></div>';

    // Find the element with id example_theme_paragraph--937
    var targetElement = $('#example_theme_paragraph--937');

    // Find the second <p> element inside targetElement
    var secondParagraph = targetElement.find('p:nth-child(1)');

    // Insert the <select> element before the second <p> element
    secondParagraph.before(selectOptions);
    
    // Handle change event of the <select> element
    $('#redirectSelect').change(function() {
      var selectedOption = $(this).val();
      if (selectedOption) {
        window.location.href = '/contact-us/' + selectedOption;
      }
    });
	});
})(jQuery);
