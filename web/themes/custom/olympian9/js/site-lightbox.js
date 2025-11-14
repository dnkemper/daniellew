(function ($, Drupal) {
  'use strict';

  Drupal.behaviors.lightbox = {
    attach: function (context, settings) {
      var $lbHtml = $('.js-lightbox', context),
        $lbRSVP = $('.js-rsvp', context),
        $lbGallery = $('.js-gallery', context),
        $lbVideo = $('.js-video', context),
        $lbAjax = $('.js-ajax', context),
        lbmargin = $(window).width() < 768 ? [50, 0, 50, 0] : 90;

      var url = $(this).attr('data-fancybox-href');

      $lbVideo.fancybox({
        margin: lbmargin,
        padding: 0,
        autoSize: true,
        nextEffect: 'fade',
        openEffect: 'fade',
        prevEffect: 'fade',
        tpl: {
          closeBtn: '<a title="Close" class="fancybox-item fancybox-close" href="javascript:;"><svg viewBox="0 0 50 50"><use xlink:href="#close"></use></svg></a>'
        },
        helpers: {
          title: {
            type: 'outside'
          },
          overlay: {
            locked: false
          }
        }
      });

      $(document, context).on('click', '.js-lightbox', function () {
        var url = $(this).attr('data-fancybox-href');
        $.fancybox({
          href: url,
          autoSize: true,
          autoCenter: false,
          maxWidth: '1200',
          openEffect: 'fade',
          tpl: {
            closeBtn: '<a title="Close" class="fancybox-item fancybox-close" href="javascript:;"><svg viewBox="0 0 50 50"><use xlink:href="#close"></use></svg></a>'
          },
          helpers: {
            overlay: {
              locked: true
            }
          }
        });
      });

      $lbGallery.fancybox({
        href: url,
        padding: 0,
        autoSize: true,
        autoCenter: false,
        maxWidth: '1300',
        openEffect: 'fade',
        wrapCSS: 'fancybox-gallery',
        arrows: true,
        tpl: {
          closeBtn: '<a title="Close" class="fancybox-item fancybox-close" href="javascript:;"><svg viewBox="0 0 50 50"><use xlink:href="#close"></use></svg></a>',
          prev: '<a title="Previous" class="fancybox-nav fancybox-prev" href="javascript:;"><span><svg viewBox="0 0 50 50"><use xlink:href="#arrow-prev"></use></svg></span></a>',
          next: '<a title="Next" class="fancybox-nav fancybox-next" href="javascript:;"><span><svg viewBox="0 0 50 50"><use xlink:href="#arrow-next"></use></svg></span></a>'
        },
        helpers: {
          overlay: {
            locked: true
          }
        }
      });

      $lbRSVP.fancybox({
        href: url,
        padding: 0,
        autoSize: true,
        autoCenter: false,
        maxWidth: '845',
        openEffect: 'fade',
        wrapCSS: 'fancybox-rsvp',
        tpl: {
          closeBtn: '<a title="Close" class="fancybox-item fancybox-close" href="javascript:;"><svg viewBox="0 0 50 50"><use xlink:href="#close"></use></svg></a>'
        },
        helpers: {
          overlay: {
            locked: true
          }
        }
      });

      $lbAjax.fancybox({
        type: 'ajax',
        padding: 0,
        autoSize: true,
        autoCenter: false,
        maxWidth: '1200',
        openEffect: 'fade',
        tpl: {
          closeBtn: '<a title="Close" class="fancybox-item fancybox-close" href="javascript:;"><svg viewBox="0 0 50 50"><use xlink:href="#close"></use></svg></a><a title="Close" class="fancybox-item fancybox-close button" href="javascript:;">Close</a>'
        },
        helpers: {
          title: {
            type: 'outside'
          },
          overlay: {
            locked: true
          }
        }
      });
    }
  };
})(jQuery, Drupal);
