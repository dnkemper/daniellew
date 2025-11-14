/**
 * @file
 * artsci global scripts. Attached to every page.
 */

(function ($, Drupal, once) {
    Drupal.behaviors.artsci = {
      attach: function (context, setting) {
        $(once('artsci', 'body', context)).each(function () {
          console.log(
            'This is an Arts & Sciences',
            setting.artsci.version,
            'site.',
            'For more information, please visit https://artsci.washu.edu.'
          );
        });
      }
    };
  })(jQuery, Drupal, once);
