(function ($, Drupal, once) {

  'use strict';

  Drupal.behaviors.selectButton = {
    attach: function (context) {

      var $loadContainer = $('.js-load-container', context);
      var $detailContainer = $('.js-detail-container', context);

      function loadDetails() {
        $(once('selectButton', '.js-load-detail', context)).on('click', function (e) {
          var url = $(this).attr('data-fancybox-href');

          $.fancybox({
            autoSize: true,
            autoCenter: false,
            href: url + ' .js-detail',
            type: 'ajax',
            tpl: {
              closeBtn: '<a title="Close" class="fancybox-item fancybox-close" href="javascript:;"><svg viewBox="0 0 50 50"><use xlink:href="#close"></use></svg></a><a title="Close" class="fancybox-item fancybox-close button" href="javascript:;">Close</a>'
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

      function loadMoreClicks() {
        var $loadMore = $(once('selectButton', '.js-load-more', context));

        $loadMore.on('click', function (e) {
          $(this).fadeTo(400, 0, function () {
            $('<div class="spinner"></div>').hide().appendTo('.load-more').fadeIn(400);
            $(this).parent().parent($loadContainer).append('<div class="load-wrap flex"></div>');

            var url = $(this).attr('href');
            var $last = $('.load-wrap:last', $loadContainer);

            $last.fadeTo(0, 0).load(url + ' .js-load-container  > *', function () {
              $loadMore.parent().remove();
              $('.load-wrap .card').unwrap();
              $(this).fadeTo(400, 1);
              loadMoreClicks();
            });
          });

          e.preventDefault();
        });
      }
      loadMoreClicks();

      // Only want to run lazy load 3 times, then stop
      var lazyLoadCalls = 0;

      function lazyLoadMore() {
        var $loadMore = $(once('selectButton', '.js-load-more', context));

        $loadMore.on('inview', function (event, isInView) {
          if (isInView && lazyLoadCalls < 3) {
            $(this).fadeTo(0, 0, function () {
              $('<div class="spinner"></div>').hide().appendTo('.load-more').fadeIn(400);
              $(this).parent().parent($loadContainer).append('<div class="load-wrap flex"></div>');

              var url = $(this).attr('href');
              var $last = $('.load-wrap:last', $loadContainer);

              $last.fadeTo(0, 0).load(url + ' .js-load-container  > *', function () {
                $loadMore.parent().remove();
                $('.load-wrap .card').unwrap();
                $(this).fadeTo(400, 1);
                loadMoreClicks();
                lazyLoadMore();
              });
            });

            lazyLoadCalls++;
          } else {
            // do nothing
          }
        });
      }
      lazyLoadMore();

      // Add other functions similarly...

    }
  };

})(jQuery, Drupal, once);
