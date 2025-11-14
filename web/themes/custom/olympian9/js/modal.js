!(function(e) {
  "function" != typeof e.matches &&
    (e.matches =
      e.msMatchesSelector ||
      e.mozMatchesSelector ||
      e.webkitMatchesSelector ||
      function(e) {
        for (
          var t = this,
            o = (t.document || t.ownerDocument).querySelectorAll(e),
            n = 0;
          o[n] && o[n] !== t;

        ) {
          ++n;
        }
        return Boolean(o[n]);
      }), "function" != typeof e.closest &&
    (e.closest = function(e) {
      for (var t = this; t && 1 === t.nodeType; ) {
        if (t.matches(e)) {
          return t;
        }
        t = t.parentNode;
      }
      return null;
    });
})(window.Element.prototype);
document.addEventListener("DOMContentLoaded", function() {
  var modalButtons = document.querySelectorAll(".js-open-modal"),
    overlay = document.querySelector(".js-overlay-modal"),
    closeButtons = document.querySelectorAll(".js-modal-close");
  let adjustIndex = document.querySelector(
    ".column.blog-feed.multipurpose-blog-feed"
  );
  modalButtons.forEach(function(item) {
    item.addEventListener("click", function(e) {
      e.preventDefault();
      var modalId = this.getAttribute("data-modal"),
        modalElem = document.querySelector(
          '.modal[data-modal="' + modalId + '"]'
        );
      modalElem.classList.add("active");
      overlayElem = document.querySelector(
        '.js-overlay-modal[data-modal="' + modalId + '"]'
      );
      overlayElem.classList.add("active");
      if (adjustIndex) {
        adjustIndex.classList.add("background");
      }
      document.body.classList.add("no-scroll");
    }); // end click
  }); // end foreach
  closeButtons.forEach(function(item) {
    item.addEventListener("click", function(e) {
      var parentModal = this.closest(".modal");
      parentModal.classList.remove("active");
      if (adjustIndex) {
        adjustIndex.classList.remove("background");
      }
      document.body.classList.remove("no-scroll");

      // Find the corresponding overlay by data-modal attribute
      var modalId = parentModal.getAttribute("data-modal");
      var parentOverlay = document.querySelector(
        '.js-overlay-modal[data-modal="' + modalId + '"]'
      );

      if (parentOverlay) {
        parentOverlay.classList.remove("active");
      }

      document.querySelectorAll("iframe").forEach(v => {
        v.src = v.src;
      });
      document.querySelectorAll("video").forEach(v => {
        v.pause();
      });
    });
  });

  document.body.addEventListener(
    "keyup",
    function(e) {
      var key = e.keyCode;
      if (key == 27) {
        document.querySelector(".modal.active").classList.remove("active");
        if (adjustIndex) {
          adjustIndex.classList.remove("background");
        }
        document.body.classList.remove("no-scroll");
        document
          .querySelector(".js-overlay-modal.active")
          .classList.remove("active");
      }
    },
    false
  );
  if (overlay) {
    overlay.addEventListener("click", function() {
      document.querySelector(".modal.active").classList.remove("active");
      this.classList.remove("active");
      if (adjustIndex) {
        adjustIndex.classList.remove("background");
      }
      document.body.classList.remove("no-scroll");
      let overlayActive = document.querySelector(".js-overlay-modal.active");
      if (overlayActive) {
        overlayActive.classList.remove("active");
      }

      this.classList.remove("active");
      document.querySelectorAll("iframe").forEach(v => {
        v.src = v.src;
      });
      document.querySelectorAll("video").forEach(v => {
        v.pause();
      });
    });
  }
});
