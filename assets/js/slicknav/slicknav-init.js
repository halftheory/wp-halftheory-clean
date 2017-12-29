jQuery(document).ready(function ($) {
	/** Note: $() will work as an alias for jQuery() inside of this function */
	if (typeof slicknav !== 'undefined' && slicknav.brand !== null) {
		$('#menu').slicknav({
			removeClasses: true,
			brand: slicknav.brand,
			prependTo: 'header'
			/*appendTo: 'header'*/
		});
	}
	else {
		$('#menu').slicknav({
			removeClasses: true,
			prependTo: 'header'
		});
	}
});