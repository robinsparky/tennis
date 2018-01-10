(function() {
    var currentSrc, oldSrc, imgEl;
    var showPicSrc = function() {
        oldSrc = currentSrc;
        imgEl = document.getElementById('picimg');
        currentSrc = imgEl.currentSrc || imgEl.src;

        if (typeof oldSrc === 'undefined' || oldSrc !== currentSrc) {
            document.getElementById('logger').innerHTML = currentSrc;
        }
    };

    // You may wish to debounce resize if you have performance concerns
    window.addEventListener('resize', showPicSrc);
    window.addEventListener('load', showPicSrc);
})(window);

//Use full size Woocommerce images
jQuery(document).on('click', '.thumbnails .zoom', function() {
    var photo_fullsize = jQuery(this).find('img').attr('src').replace('-100x132', '');
    jQuery('.woocommerce-main-image img').attr('src', photo_fullsize);
    return false;
});

//or....
(function($) {
    $('.thumbnails .zoom').on('click', function(event) {
        event.preventDefault();

        var $img = $(this).find('img'),
            $src = $img.attr('src'),
            search = /^([-]?)([\d]{2,4})((\s*)(x)(\s*))([\d]{2,4})$/;

        // Regex .test() is fast, so we use it *before* we actually do something
        if (search.test($src)) {
            var $url = $src.replace(search, '');
            $('.class-of-the-thumbnail-image').attr('src', $url);
        }
    });
})(jQuery);