(function($, undefined) {
	/** Note: $() will work as an alias for jQuery() inside of this function */

	var galleryCarousel = function() {
		if (typeof $.fn.slick !== 'function') {
			return;
		}
		if (typeof gallery_carousel !== 'object') {
			return;
		}
		for (var key of Object.keys(gallery_carousel)) {
			if (typeof gallery_carousel[key] !== 'object') {
				continue;
			}
			if (! $('#'+key).length) {
				continue;
			}
			$('#'+key).first().slick(gallery_carousel[key]);
		}
	};

	//document.ready
	$(document).ready(function() {
		galleryCarousel();
	});//document.ready

})(jQuery);