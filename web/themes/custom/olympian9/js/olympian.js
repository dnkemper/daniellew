(function($, Drupal) {
  $(document).ready(function() {
    var slickPrev =
      '<div class="slick-prev"><svg><use xlink:href="#arrow-prev"></use></svg></div>';
    var slickNext =
      '<div class="slick-next"><svg><use xlink:href="#arrow-next"></use></svg></div>';
    $(".dot-slideshow").slick({
      arrows: false,
      dots: true,
      fade: true,
      infinite: true,
      autoplay: true,
      autoplaySpeed: 4000,
      speed: 500
    });
    $(".slider-for").slick({
      slidesToShow: 1,
      slidesToScroll: 1,
      arrows: false,
      focusOnSelect: true,
      draggable: true,
      fade: true,
      asNavFor: ".slider-nav"
    });
    $(".slider-nav").slick({
      slidesToShow: 5,
      draggable: true,
      slidesToScroll: 1,
      asNavFor: ".slider-for",
      dots: true,
      centerMode: true,
      focusOnSelect: true
    });
    $(".announcement-slideshow").slick({
      arrows: false,
      dots: true,
      fade: true,
      infinite: true,
      autoplay: true,
      autoplaySpeed: 7000,
      speed: 500
    });
    $(".event-slideshow").slick({
      arrows: false,
      dots: true,
      fade: true,
      infinite: true,
      autoplay: true,
      autoplaySpeed: 4000,
      speed: 500,
      appendDots: $(".dots")
    });

    function slickify() {
      $(".non-mobile-slideshow").slick({
        arrows: false,
        dots: true,
        fade: true,
        infinite: true,
        autoplay: true,
        autoplaySpeed: 5000,
        speed: 500,
        responsive: [
          {
            breakpoint: 1030,
            settings: "unslick"
          }
        ]
      });
    }

    slickify();
    $(window).resize(function() {
      var $windowWidth = $(window).width();
      if ($windowWidth > 1029) {
        slickify();
      }
    });
    $(".text-slideshow").slick({
      arrows: false,
      dots: false,
      infinite: true,
      autoplay: true,
      autoplaySpeed: 2500,
      speed: 500,
      fade: true
    });
    $(".home-slideshow").slick({
      arrows: false,
      dots: false,
      infinite: true,
      autoplay: true,
      autoplaySpeed: 10000,
      pauseOnHover: false,
      speed: 900,
      fade: true
    });
    $(".featured-carousel").slick({
      infinite: true,
      dots: true,
      arrows: true,
      prevArrow:
        "<button type='button' class='slick-prev pull-left'><img src='https://physics.wustl.edu/sites/all/themes/olympian/images/prev.png'></button>",
      nextArrow:
        "<button type='button' class='slick-next pull-right'><img src='https://physics.wustl.edu/sites/all/themes/olympian/images/next.png'</button>",
      slidesToShow: 3,
      slidesToScroll: 3,
      responsive: [
        {
          breakpoint: 1200,
          settings: {
            slidesToShow: 1,
            slidesToScroll: 1
          }
        }
      ]
    });
    $(".posts-feed-slideshow").slick({
      arrows: true,
      infinite: false,
      autoplay: false,
      speed: 500,
      fade: true,
      nextArrow: slickNext,
      prevArrow: slickPrev,
      appendArrows: $(".arrows")
    });
    $(".about-slideshow").slick({
      arrows: true,
      nextArrow: slickNext,
      prevArrow: slickPrev,
      dots: true,
      infinite: false,
      autoplay: false,
      speed: 500,
      fade: true,
      appendDots: $(".about-dots"),
      customPaging: function(slider, i) {
        var nav = $(slider.$slides[i]).data("nav");
        var show = $(slider.$slides[i]).data("data-show-date");
        return (
          '<div data-date="' +
          nav +
          '" class="' +
          show +
          '" onclick="event.preventDefault();">' +
          nav +
          "</div>"
        );
      },
      responsive: [
        {
          breakpoint: 768,
          settings: {
            slidesToShow: 1,
            dots: false
          }
        },
        {
          breakpoint: 1030,
          settings: {
            slidesToShow: 1,
            dots: true,
            arrows: false
          }
        }
      ]
    });
    $(".arrow-slideshow").slick({
      arrows: true,
      dots: false,
      infinite: true,
      autoplaySpeed: 5000,
      speed: 500,
      fade: true,
      nextArrow: slickNext,
      prevArrow: slickPrev
    });
    $(".more-posts-slideshow").slick({
      arrows: true,
      dots: false,
      infinite: false,
      speed: 500,
      slidesToShow: 2,
      slidesToScroll: 1,
      nextArrow: slickNext,
      prevArrow: slickPrev,
      responsive: [
        {
          breakpoint: 767,
          settings: {
            slidesToShow: 1,
            dots: true,
            arrows: false
          }
        }
      ]
    });
    $(".slideshow-nav").slick({
      arrows: true,
      slidesToShow: 7,
      slidesToScroll: 1,
      variableWidth: true,
      dots: false,
      infinite: true,
      autoplay: false,
      focusOnSelect: true,
      draggable: true,
      speed: 500,
      centerMode: true,
      asNavFor: ".slideshow-content",
      nextArrow: slickNext,
      prevArrow: slickPrev,
      responsive: [
        {
          breakpoint: 1600,
          settings: {
            slidesToShow: 5
          }
        },
        {
          breakpoint: 1029,
          settings: {
            slidesToShow: 3
          }
        },
        {
          breakpoint: 599,
          settings: {
            slidesToShow: 1,
            centerMode: false,
            variableWidth: false
          }
        }
      ]
    });
    dotTimeline();
    $(".about-slideshow").on("breakpoint", function(event, slick, breakpoint) {
      dotTimeline();
    });

    function dotTimeline() {
      $(".about-timeline ul.slick-dots").each(function() {
        //if there is only 1 visible slide it gets centered
        var $childCount = $(this).children("li:has(a)").length;
        //handle formatting & show
        if ($childCount === 1) {
          $(this).css("justify-content", "space-around");
          $(this).parent(".about-timeline").show();
        } else if ($childCount > 1) {
          $(this).css("justify-content", "space-between");
          //align visible children accordingly.
          $(this).find("li:has(a):first").css("justify-content", "left");
          $(this).find("li:has(a):last").css("justify-content", "right");
          $(this).parent(".about-timeline").show();
        }
      });
    }

    $(".slideshow-content").slick({
      arrows: false,
      slidesToShow: 1,
      slidesToScroll: 1,
      dots: false,
      infinite: true,
      autoplay: false,
      speed: 500,
      fade: true
    });
    $(".history-slideshow").slick({
      arrows: true,
      dots: true,
      infinite: true,
      speed: 500,
      slidesToShow: 2,
      slidesToScroll: 1,
      nextArrow: slickNext,
      prevArrow: slickPrev,
      appendDots: $(".history-timeline"),
      customPaging: function(slider, i) {
        //on the first iteration we add the hide-timeline class
        var date = $(slider.$slides[i]).data("date");
        var show = $(slider.$slides[i]).data("show-date");
        //if any of the timeline dates are set to show then we remove the hide-timeline class we added
        if (show === "show") {
          return (
            '<a href="#" data-date="' +
            date +
            '" class="' +
            show +
            '" onclick="event.preventDefault();">' +
            date +
            "</a>"
          );
        } else {
          return null;
        }
      },
      responsive: [
        {
          breakpoint: 768,
          settings: {
            slidesToShow: 1,
            dots: false
          }
        },
        {
          breakpoint: 1030,
          settings: {
            slidesToShow: 1,
            dots: true
          }
        }
      ]
    });
    $(".selective-tweets-content").slick({
      arrows: false,
      dots: true,
      fade: true,
      infinite: true,
      autoplay: true,
      autoplaySpeed: 4000,
      speed: 500
    });
    historyTimeline();
    $(".history-slideshow").on("breakpoint", function(
      event,
      slick,
      breakpoint
    ) {
      historyTimeline();
    });

    function historyTimeline() {
      $(".history-timeline ul.slick-dots").each(function() {
        //if there is only 1 visible slide it gets centered
        var $childCount = $(this).children("li:has(a)").length;
        //handle formatting & show
        if ($childCount === 1) {
          $(this).css("justify-content", "space-around");
          $(this).parent(".history-timeline").show();
        } else if ($childCount > 1) {
          $(this).css("justify-content", "space-between");
          //align visible children accordingly.
          $(this).find("li:has(a):first").css("justify-content", "left");
          $(this).find("li:has(a):last").css("justify-content", "right");
          $(this).parent(".history-timeline").show();
        }
      });
    }

    $(".slide-title").each(function() {
      var $slide = $(this).parent();
      if ($slide.attr("aria-describedby") != undefined) {
        $(this).attr("id", $slide.attr("aria-describedby"));
      }
    });
  });
  // Toggle Alpha
  $(".js-toggle-alpha").click(function() {
    if (!$(this).closest(".toggle-wrap").hasClass("show")) {
      $(this)
        .closest(".toggle-wrap")
        .toggleClass("show")
        .find(".togglee")
        .slideToggle("fast");
      return false;
    }
  });
  $(function() {
    //run on document.ready
    $("#select1").change(function() {
      //this occurs when select 1 changes
      $("#select2").val($(this).val());
    });
  });
  $(document).ready(function() {
    var $radio = $('input[type="radio"]'),
      $checkbox = $('input[type="checkbox"]'),
      $form = $(".form-container");
    $form.each(function() {
      $('input[type="checkbox"],input[type="radio"]').change(function() {
        var id = $(this).attr("id"),
          $label = $('label[for="' + id + '"]');
        if ($(this).is(":checked")) {
          $label.addClass("checked");
        } else {
          $label.removeClass("checked");
        }
      });
    });
  });
  //toggle for intro table of contents section
  if ($(".js-toc").length) {
    var topofDiv = $(".js-toc").offset().top; //gets offset of header
    var height = $(".js-toc").outerHeight(); //gets height of header

    $(window).scroll(function() {
      if ($(window).scrollTop() > topofDiv + height) {
        $(".js-toc").addClass("scrolled-past").removeClass("expanded");
      } else {
        $(".js-toc").removeClass("scrolled-past").addClass("expanded");
      }
    });

    $(document).ready(function() {
      $(".js-toc-toggle").click(function() {
        $(".js-toc").toggleClass("expanded");
      });
    });
  }

  function filtering(element) {
    var title = element.getAttribute("category-value");
    var divElement =
      '<div class="category-term"><a class="cancel back" href="/browse">Back</a> Results for: ' +
      title +
      "</div>";
    $(".category-term").html(divElement);
  }

  function filteringCat(element) {
    var title = element.getAttribute("category-value");
    var divElement =
      '<div class="category-term"><a class="cancel back" href="/resources">Back</a> Results for: ' +
      title +
      "</div>";
    $(".category-term").html(divElement);
  }

  $(document).ready(function() {
    $(".inactive").click(function() {
      return false;
    });

    // Change 1
    var $loadContainer = $(".js-load-container"),
      $detailContainer = $(".js-detail-container");

    function loadDetails() {
      $(document).on("click", ".js-load-detail", function(e) {
        var url = $(this).attr("data-fancybox-href");
        $.fancybox({
          autoSize: true,
          autoCenter: false,
          href: url + " .js-detail",
          type: "ajax",
          tpl: {
            closeBtn:
              '<a title="Close" class="fancybox-item fancybox-close no-text" href="javascript:;"><svg viewBox="0 0 50 50">Close<use xlink:href="#close"></use></svg></a><a title="Close" class="fancybox-item fancybox-close button" href="javascript:;">Close</a>'
          },
          helpers: {
            overlay: {
              locked: true
            }
          }
        });
        e.preventDefault();
      });
    }

    loadDetails();
  });
  $(document).ready(function() {
    checkSize();
    $(window).resize(checkSize);

    function checkSize() {
      if ($(".sr .reveal").css("visibility") == "hidden") {
        sr.reveal(
          ".reveal, .filtered article, .profile-feed .post, .search-results .result"
        );
        sr.reveal(
          ".podcasts .heading img",
          {
            duration: 800,
            distance: "25px"
          },
          400
        );
      }
    }
  });

  (function(Drupal, once) {
    Drupal.behaviors.courseFilter = {
      attach(context) {
        const elements = once("courseFilter", ".course", context);
        // `elements` is always an Array.
        elements.forEach(courseCallback);
      }
    };

    // The parameters are reversed in the callback between jQuery `.each` method
    // and the native `.forEach` array method.
    function courseCallback(value, index) {
      $(value).click(function() {
        if ($(value).closest(".course-wrap").hasClass("show")) {
          $(value)
            .closest(".course-wrap")
            .removeClass("show")
            .find(".course-toggle")
            .slideUp("fast");
          $("#primary-nav").removeClass("move");
        } else {
          $("#primary-nav .course-wrap")
            .removeClass("show")
            .find(".course-toggle")
            .slideUp("fast");
          $("#primary-nav").removeClass("move");
          $(value)
            .closest(".course-wrap")
            .addClass("show")
            .find(".course-toggle")
            .slideDown("fast");
        }
      });
    }
  })(Drupal, once);

  // Toggle Filter
  (function(Drupal, once) {
    Drupal.behaviors.toggleFilter = {
      attach(context) {
        const elements = once("toggleFilter", ".toggle", context);
        // `elements` is always an Array.
        elements.forEach(toggleCallback);
      }
    };

    // The parameters are reversed in the callback between jQuery `.each` method
    // and the native `.forEach` array method.
    function toggleCallback(value, index) {
      $(value).click(function() {
        if ($(value).closest(".toggle-wrap").hasClass("show")) {
          $(value)
            .closest(".toggle-wrap")
            .removeClass("show")
            .find(".togglee")
            .slideUp("fast");
          $("#primary-nav").removeClass("move");
        } else {
          $("#primary-nav .toggle-wrap")
            .removeClass("show")
            .find(".togglee")
            .slideUp("fast");
          $("#primary-nav").removeClass("move");
          $(value)
            .closest(".toggle-wrap")
            .addClass("show")
            .find(".togglee")
            .slideDown("fast");
        }
      });
    }
  })(Drupal, once);

  // Toggle Filter
  (function(Drupal, once) {
    Drupal.behaviors.toggleAlphaFilter = {
      attach(context) {
        const elements = once("toggleAlphaFilter", ".toggle", context);
        // `elements` is always an Array.
        elements.forEach(toggleAlphaCallback);
      }
    };

    // The parameters are reversed in the callback between jQuery `.each` method
    // and the native `.forEach` array method.
    function toggleAlphaCallback(value, index) {
      $(value).click(function() {
        if ($(value).closest(".toggle-alpha-wrap").hasClass("show")) {
          $(value)
            .closest(".toggle-alpha-wrap")
            .removeClass("show")
            .find(".togglee")
            .slideDown("fast");
          $("#primary-nav").removeClass("move");
        } else {
          $("#primary-nav .toggle-alpha-wrap")
            .removeClass("show")
            .find(".togglee")
            .slideDown("fast");
          $("#primary-nav").removeClass("move");
          $(value)
            .closest(".toggle-alpha-wrap")
            .addClass("show")
            .find(".togglee")
            .slideUp("fast");
        }
      });
    }
  })(Drupal, once);

  (function(Drupal, once) {
    Drupal.behaviors.toggleDateFilter = {
      attach(context) {
        const elements = once("toggleDateFilter", ".toggle-date", context);
        // `elements` is always an Array.
        elements.forEach(toggleDateCallback);
      }
    };

    // The parameters are reversed in the callback between jQuery `.each` method
    // and the native `.forEach` array method.
    function toggleDateCallback(value, index) {
      $(value).click(function() {
        if ($(value).closest(".toggle-wrap").hasClass("show")) {
          $(value)
            .closest(".toggle-wrap")
            .removeClass("show")
            .find(".togglee-date")
            .slideUp("fast");
          $("#date-nav").removeClass("move");
        } else {
          $("#date-nav .toggle-wrap")
            .removeClass("show")
            .find(".togglee-date")
            .slideUp("fast");
          $("#date-nav").removeClass("move");
          $(value)
            .closest(".toggle-wrap")
            .addClass("show")
            .find(".togglee-date")
            .slideDown("fast");
        }
      });
    }
  })(Drupal, once);

  $(document).ready(function() {
    $("#main-nav-toggle").click(function() {
      $("html").toggleClass("takeover");
      $("#header").toggleClass("show");
    });

    // Toggle Main Search
    $("#main-search-toggle").click(function() {
      $("html").addClass("takeover");
      $("#header").addClass("show");
      //setTimeout(function() { $('#main-nav .search input[name="text"]').focus() }, 5000);
      setTimeout(function() {
        $("#site-search").focus();
      }, 250);
      //console.log("focusing olympian");
    });

    // Accessiblity work-arounds
    $("#main-nav-toggle").mouseup(function() {
      this.blur();
    });

    $(".toggle").each(function() {
      $(this).click(function() {
        if ($(".toggle-date").hasClass("active")) {
          $(".toggle-date")
            .removeClass("active")
            .closest(".toggle-wrap")
            .find(".togglee-date")
            .slideToggle("fast");
        }
        return false;
      });
    });

    // Date Toggle
    $(".toggle-date").click(function() {
      if ($(this).hasClass("inactive")) {
        // Do nothing
      } else {
        if ($(this).closest(".toggle-wrap").hasClass("show")) {
          $(this)
            .closest(".toggle-wrap")
            .removeClass("show")
            .find(".togglee")
            .slideUp("fast");
        }
        $(this)
          .toggleClass("active")
          .closest(".toggle-wrap")
          .find(".togglee-date")
          .slideToggle("fast");
      }
      return false;
    });

    if (window.matchMedia("(min-width: 1200px)").matches) {
      $(".second-level-wrap .toggle-wrap > a:eq(0)").each(function() {
        $(this).click(function() {
          $(this).closest(".toggle-wrap").toggleClass("show");
          $("#primary-nav").toggleClass("move");
          return false;
        });
      });
    }
    $(".course-toggle").each(function() {
      $(this).click(function() {
        $(this)
          .closest(".toggle-wrap")
          .toggleClass("show")
          .find(".course-togglee")
          .slideToggle("fast");
      });
    });
    $(".categories a.active").click(function() {
      $(this).closest(".categories").toggleClass("show");
      return false;
    });
    $(".js-toggle-alpha").click(function() {
      // Opening
      if (!$(this).closest(".toggle-wrap").hasClass("show")) {
        $(this)
          .closest(".toggle-wrap")
          .toggleClass("show")
          .find(".togglee")
          .slideToggle("fast");
        return false;

        // Closing
      } else {
        $(this)
          .closest(".toggle-wrap")
          .toggleClass("show")
          .find(".togglee")
          .slideToggle("fast");
        // Reset Alpha Filters
        $(".filtered-alpha input").removeClass("active");
        //Run Filtersearch only when closing AND removing actives
        filterSearch();
        return false;
      }
    });

    function goBack() {
      $(".back").click(function() {
        var host = document.location.hostname;
        if (document.referrer.indexOf(host) >= 0) {
          parent.history.back();
        } else {
          window.location.href = "/";
        }
        return false;
      });
    }

    goBack();
    $(".tab-nav .tab-heading").each(function() {
      $(this).click(function() {
        $(this).addClass("active").siblings().removeClass("active");
        $(this)
          .closest(".tabs")
          .find(".tab-content .tab")
          .eq($(this).index())
          .addClass("active")
          .siblings()
          .removeClass("active");
        return false;
      });
    });

    // This js-scroll click handler isn't really necessary, since its animation is set at zero duration.
    // Without the handler, an in-page anchor link will still work and move focus down to the section.
    // I'll leave it here in case an animation effect (with non-zero duration) is desired later.
    $(".js-scroll").on("click", function() {
      var section = $(this).attr("href");
      if (section !== undefined) {
        $("html, body").animate(
          {
            scrollTop: $(section).offset().top
          },
          0
        );
      }
    });

    var $more = $(".js-more");
    if ($more.length <= 0) {
      return false;
    }
    $more.click(function(e) {
      $(this).parent().toggleClass("show-all");
      e.preventDefault();
    });
  });
  $(window).scroll(function() {
    var scroll = $(window).scrollTop();
    var header = $("#header").offset().top;
    if (scroll >= 1) {
      $("body").addClass("scroll");
    } else {
      $("body").removeClass("scroll");
    }
    if (scroll > header) {
      $("#header").addClass("fixed");
    } else {
      $("#header").removeClass("fixed");
    }
  });

  (function(factory) {
    var registeredInModuleLoader = false;
    if (typeof define === "function" && define.amd) {
      define(factory);
      registeredInModuleLoader = true;
    }
    if (typeof exports === "object") {
      module.exports = factory();
      registeredInModuleLoader = true;
    }
    if (!registeredInModuleLoader) {
      var OldCookies = window.Cookies;
      var api = (window.Cookies = factory());
      api.noConflict = function() {
        window.Cookies = OldCookies;
        return api;
      };
    }
  })(function() {
    function extend() {
      var i = 0;
      var result = {};
      for (; i < arguments.length; i++) {
        var attributes = arguments[i];
        for (var key in attributes) {
          result[key] = attributes[key];
        }
      }
      return result;
    }

    function init(converter) {
      function api(key, value, attributes) {
        var result;
        if (typeof document === "undefined") {
          return;
        }
        if (arguments.length > 1) {
          attributes = extend(
            {
              path: "/"
            },
            api.defaults,
            attributes
          );
          if (typeof attributes.expires === "number") {
            var expires = new Date();
            expires.setMilliseconds(
              expires.getMilliseconds() + attributes.expires * 864e5
            );
            attributes.expires = expires;
          }
          try {
            result = JSON.stringify(value);
            if (/^[\{\[]/.test(result)) {
              value = result;
            }
          } catch (e) {}
          if (!converter.write) {
            value = encodeURIComponent(String(value)).replace(
              /%(23|24|26|2B|3A|3C|3E|3D|2F|3F|40|5B|5D|5E|60|7B|7D|7C)/g,
              decodeURIComponent
            );
          } else {
            value = converter.write(value, key);
          }
          key = encodeURIComponent(String(key));
          key = key.replace(/%(23|24|26|2B|5E|60|7C)/g, decodeURIComponent);
          key = key.replace(/[\(\)]/g, escape);
          return (document.cookie = [
            key,
            "=",
            value,
            attributes.expires
              ? "; expires=" + attributes.expires.toUTCString()
              : "",
            attributes.path ? "; path=" + attributes.path : "",
            attributes.domain ? "; domain=" + attributes.domain : "",
            attributes.secure ? "; secure" : ""
          ].join(""));
        }
        if (!key) {
          result = {};
        }
        var cookies = document.cookie ? document.cookie.split("; ") : [];
        var rdecode = /(%[0-9A-Z]{2})+/g;
        var i = 0;
        for (; i < cookies.length; i++) {
          var parts = cookies[i].split("=");
          var cookie = parts.slice(1).join("=");
          if (cookie.charAt(0) === '"') {
            cookie = cookie.slice(1, -1);
          }
          try {
            var name = parts[0].replace(rdecode, decodeURIComponent);
            cookie = converter.read
              ? converter.read(cookie, name)
              : converter(cookie, name) ||
                cookie.replace(rdecode, decodeURIComponent);
            if (this.json) {
              try {
                cookie = JSON.parse(cookie);
              } catch (e) {}
            }
            if (key === name) {
              result = cookie;
              break;
            }
            if (!key) {
              result[name] = cookie;
            }
          } catch (e) {}
        }
        return result;
      }

      api.set = api;
      api.get = function(key) {
        return api.call(api, key);
      };
      api.getJSON = function() {
        return api.apply(
          {
            json: true
          },
          [].slice.call(arguments)
        );
      };
      api.defaults = {};
      api.remove = function(key, attributes) {
        api(
          key,
          "",
          extend(attributes, {
            expires: -1
          })
        );
      };
      api.withConverter = init;
      return api;
    }

    return init(function() {});
  });
})(jQuery, Drupal);
