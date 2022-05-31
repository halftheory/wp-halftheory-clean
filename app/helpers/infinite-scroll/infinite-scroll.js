(function($, undefined) {
if (typeof infinite_scroll === 'object') {
	/** Note: $() will work as an alias for jQuery() inside of this function */

	var update = true;
	var container_bottom;
	var loaderId = 'infinite-scroll-loader';

	/* functions */

	function containerBottom() {
		var res;
		if (infinite_scroll.container && $("#"+infinite_scroll.container).length) {
			res = $("#"+infinite_scroll.container).offset().top + $("#"+infinite_scroll.container).outerHeight() - $(window).height();
		}
		else {
			res = $(document).height() - $(window).height();
		}
		return parseInt(res,10);
	}

	function loadPage(pageNumber) {
		update = false;
		loaderCreate();
		$("#"+loaderId).fadeIn('fast');
		var data = { page: pageNumber };
		$.extend(data, infinite_scroll.data);
		$.post(infinite_scroll.ajaxurl, $.param(data))
			.done(function(html) {
					var containerElem, paginationLastNode;
					// get the container.
					if (infinite_scroll.container && $("#"+infinite_scroll.container).length) {
						containerElem = $("#"+infinite_scroll.container).first();
					}
					else {
						containerElem = $("body").first();
					}
					// get the pagination.
					if (infinite_scroll.pagination_selector && $(infinite_scroll.pagination_selector).length) {
						$(infinite_scroll.pagination_selector+":visible").hide('fast');
						if (infinite_scroll.container && $("#"+infinite_scroll.container).length) {
							paginationLastNode = $("#"+infinite_scroll.container+" > "+infinite_scroll.pagination_selector).last();
						}
						else {
							paginationLastNode = $("body > "+infinite_scroll.pagination_selector).last();
						}
						if (!paginationLastNode.length || !paginationLastNode.is(':last-child')) {
							paginationLastNode = false;
						}
					}
					// insert or append.
					if (paginationLastNode.length) {
						paginationLastNode.before(html).before(function() {
							if (infinite_scroll.pagination_more && pageNumber < infinite_scroll.max) {
								paginationLastNode.html(infinite_scroll.pagination_more).show('fast');
							}
						});
					}
					else {
						containerElem.append(html);
					}
					container_bottom = containerBottom();
			})
			.always(function() {
				$("#"+loaderId).fadeOut('normal');
				update = true;
			});
	}

	function loaderCreate() {
		if ($("#"+loaderId).length) {
			return;
		}
		$("body").append('<div id="'+loaderId+'"><img alt="Loading..." src="'+infinite_scroll.loader+'" /></div>');
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
		}).bind('resizeEnd', function(event) {
			if (count > infinite_scroll.max) {
				$(this).unbind(event);
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
		}).bind('scrollEnd', function(event) {
			if (count > infinite_scroll.max) {
				$(this).unbind(event);
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

		$("body").on('click', "#infinite-scroll-more", function(event){
			if (count > infinite_scroll.max) {
				$(this).unbind(event);
				return false;
			}
			if (!update) {
				return false;
			}
			loadPage(count);
			count++;
			return false;
		});

	});//document.ready

	//window.load
	$(window).on('load',function() {
		container_bottom = containerBottom();
	});//window.load

}
})(jQuery);
