/**
 * Created by Ivan on 25.02.2017.
 */

var navikgPositionSet;
(function ($) {
    "use strict";
    navikgPositionSet = function (opts) {
        $('.' + opts.css).off('click.krajee').on('click.krajee', function (e, options) {
            options = options || {};

            if (!options.proceed) {
                e.stopPropagation();
                e.preventDefault();

                var $container = $('#' + opts.pjaxContainer);
                var $position = $container.find('.' + opts.cssField);
                var $btn = $(this);
                var href = $btn.attr('href');
                $.ajax({
                    url: href,
                    type: 'post',
                    data: $position.serialize(),
                    success: function (result) {
                        if (opts.pjax) {
                            $.pjax.reload({container: '#' + opts.pjaxContainer});
                        } else {
                            window.reload();
                        }
                    }
                });
            }
        });
    };
})(window.jQuery);