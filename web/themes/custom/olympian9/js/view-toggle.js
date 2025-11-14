(function (Drupal) {
  'use strict';
  // @todo attach Drupal behaviors and improve this js for main nav
var acc = document.getElementById("active-list-icon-tab");
var i;

for (i = 0; i < acc.length; i++) {
  acc[i].addEventListener("click", function() {
    this.classList.toggle("active");
    var panel = this.nextElementSibling;
    if (panel.style.display === "block") {
      panel.style.display = "none";
      this.setAttribute("aria-expanded", "false");
      this.setAttribute("aria-selected", "false");
      this.setAttribute("aria-hidden", "true");
      panel.setAttribute("aria-hidden", "true");
      panel.setAttribute("aria-expanded", "false");
      panel.setAttribute("aria-selected", "false");
    } else {
      panel.style.display = "block";
      this.setAttribute("aria-expanded", "true");
      this.setAttribute("aria-selected", "true");
      this.setAttribute("aria-hidden", "false");
      panel.setAttribute("aria-selected", "true");
      panel.setAttribute("aria-expanded", "true");
      panel.setAttribute("aria-hidden", "false");
    }
  });
}
})(Drupal);
