(function($, undefined) {
if (typeof infinite_scroll === 'object') {
	/** Note: $() will work as an alias for jQuery() inside of this function */

	var update = true;
	var container_bottom;
	var loaderId = 'infinite-scroll-loader';

	/* functions */

	function containerBottom() {
		var res;
		if ($("#"+infinite_scroll.container).length) {
			res = $("#"+infinite_scroll.container).offset().top + $("#"+infinite_scroll.container).outerHeight() - $(window).height();
		}
		else {
			res = $(document).height() - $(window).height();
		}
		return parseInt(res,10);
	}

	function loadPage(pageNumber) {
		update = false;
		var data = { page: pageNumber };
		$.extend(data, infinite_scroll.data);
		$.post({
			url: infinite_scroll.ajaxurl,
			data: $.param(data),
			success: function(html) {
				loaderCreate();
				$("#"+loaderId).fadeIn('fast',function(){
					if ($("#"+infinite_scroll.container).length) {
						if ($(infinite_scroll.pagination_selector).length) {
							$(infinite_scroll.pagination_selector+":visible").hide('fast');
						}
						$("#"+infinite_scroll.container).append(html).append(function() { container_bottom = containerBottom(); update = true; });
					}
					else {
						$('body').append(html).append(function() { container_bottom = containerBottom(); update = true; });
					}
				}).fadeOut('normal');
			}
		});
		return false;
	}

	function loaderCreate() {
		if ($("#"+loaderId).length) {
			return;
		}
		$('body').append('<div id="'+loaderId+'"><img alt="Loading..." src="'+infinite_scroll.loader+'" /></div>');
		loaderRotate();
	}

	function loaderRotate() {
		var angle = 0;
		setInterval(function(){
			angle+=3;
			$("#"+loaderId+" img").rotate(angle);
		},10);
	}

	//document.ready
	$(document).ready(function($) {
		var count = 2;
		if (infinite_scroll.paged) {
			count = parseInt(infinite_scroll.paged,10) + 1;
		}

		//window.resize
		$(window).resize(function() {
			if(this.resizeTO) clearTimeout(this.resizeTO);
			this.resizeTO = setTimeout(function() {
				$(this).trigger('resizeEnd');
			}, 500);
		}).bind('resizeEnd', function() {
			if (count > infinite_scroll.max) {
				return false;
			}
			// fires once every 500ms if resized
			container_bottom = containerBottom();
			return true;
		});//window.resize

		//window.scroll
		$(window).scroll(function() {
			if(this.resizeTO) clearTimeout(this.resizeTO);
			this.resizeTO = setTimeout(function() {
				$(this).trigger('scrollEnd');
			}, 250);
		}).bind('scrollEnd', function() {
			if (count > infinite_scroll.max) {
				return false;
			}
			if (!update) {
				return false;
			}
			if ($(window).scrollTop() >= container_bottom) {
				loadPage(count);
				count++;
			}
			else {
				container_bottom = containerBottom();
			}
			return true;
		});//window.scroll

	});//document.ready

	//window.load
	$(window).on('load',function() {
		container_bottom = containerBottom();
	});//window.load

}
})(jQuery);
