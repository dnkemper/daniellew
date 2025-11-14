/**
 * @file
 *
 * Modal functionality for the AddToCal Augment module.
 */

/**
 * Add modal button to AddToCal button.
 */
(function ($, Drupal) {
  $('[data-type="add_to_cal_modal"]').each(function () {
    let id = $(this).data("target");
    let options = {
      autoOpen: false,
      modal: true,
      width: 550,
      title: 'Add to Calendar'
    };
    let theDialog = $(this).dialog(options);
    $("#" + id).click(function () {
      theDialog.dialog("open");
    });
  });
})(jQuery, Drupal);
