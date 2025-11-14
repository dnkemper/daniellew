(function (Drupal, once, $) {
  var FORM_ID = '#views-exposed-form-faculty-block-grid2'; // Update with your form ID

  function getUrlParameter(name) {
    var params = new URLSearchParams(window.location.search);
    return params.get(name);
  }

  function submitViaDrupalAjax(formSelector) {
    var form = document.querySelector(formSelector);
    if (!form) return;

    var $submit = $(form).find(':input[type="submit"],button[type="submit"]').first();
    if ($submit.length) {
      $submit.trigger('click');
    } else {
      form.requestSubmit ? form.requestSubmit() : form.submit();
    }
  }

  Drupal.behaviors.autoFilterByCat = {
    attach: function attach(context) {
      once('auto-cat-filter', FORM_ID, context).forEach(function (form) {
        // Find the checked radio button for 'cat'
        var checkedRadio = form.querySelector('input[name="cat"]:checked');

        if (checkedRadio) {
          var selectedValue = checkedRadio.value;
          var currentCatParam = getUrlParameter('cat');

          // If the URL doesn't have the cat parameter or it's different, update it
          if (currentCatParam !== selectedValue) {
            // Option 1: Add to URL and reload (preserves other params)
            var url = new URL(window.location.href);
            url.searchParams.set('cat', selectedValue);
            window.location.href = url.toString();

            // Option 2: Submit via AJAX (uncomment if you prefer this)
            // submitViaDrupalAjax(FORM_ID);
          }
        }
      });
    }
  };
})(window.Drupal, window.once, window.jQuery);
