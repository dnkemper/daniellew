(function ($, Drupal) {
  'use strict';

  Drupal.behaviors.backToTopButton = {
    attach: function (context, settings) {
      var backToTopButton = document.getElementById("top-scroll");

      window.onscroll = function () {
        scrollFunction();
      };

      function scrollFunction() {
        if (document.body.scrollTop > 20 || document.documentElement.scrollTop > 20) {
          backToTopButton.style.display = "block";
        } else {
          backToTopButton.style.display = "none";
        }
      }

      backToTopButton.addEventListener('click', function () {
        topFunction();
      });

      function topFunction() {
        document.body.scrollTop = 0;
        document.documentElement.scrollTop = 0;
      }
    }
  };

})(jQuery, Drupal);
