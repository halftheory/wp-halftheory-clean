<?php
// Exit if accessed directly.
defined('ABSPATH') || exit;

if (!class_exists('Halftheory_Helper_Infinite_Scroll')) :
class Halftheory_Helper_Infinite_Scroll {

	public $container = 'primary';
	public $pagination_selector = '.pagination';

	public function __construct() {
		add_action('after_setup_theme', array($this,'after_setup_theme'), 20);
		add_action('wp_ajax_infinite_scroll', array($this,'wp_ajax_infinite_scroll')); // for logged in user
		add_action('wp_ajax_nopriv_infinite_scroll', array($this,'wp_ajax_infinite_scroll')); // if user not logged in
		add_action('wp_footer', array($this,'wp_footer'), 20);
	}

	/* actions */

	public function after_setup_theme() {
		$arr = array(
			'container' => $this->container,
			'footer_widgets' => false,
		);
		$arr = apply_filters('halftheory_helper_infinite_scroll_theme_support', $arr);
		add_theme_support('infinite-scroll', $arr);
	}

	public function wp_ajax_infinite_scroll() {
		if (empty($_POST)) {
			return;
		}
		if (!isset($_POST['page'])) {
			return;
		}
		$args = array('paged' => $_POST['page'], 'post_status' => 'publish');
		if (isset($_POST['param']) && isset($_POST['value'])) {
			// turn date_query and tax_query into arrays
			if ($_POST['param'] == 'date_query') {
				$args[$_POST['param']] = array(
					'after' => strtotime($_POST['value']),
					'before' => strtotime("+1 day", strtotime($_POST['value'])),
				);
			}
			elseif ($_POST['param'] == 'tax_query') {
			}
			else {
				$args[$_POST['param']] = $_POST['value'];
			}
		}
		query_posts($args);
		get_template_part('loop');
		exit;
	}

	public function wp_footer() {
		if (is_singular()) {
			return;
		}
		elseif (is_404()) {
			return;
		}
		if (function_exists('is_login_page')) {
			if (is_login_page()) {
				return;
			}
		}
		if (function_exists('is_signup_page')) {
			if (is_signup_page()) {
				return;
			}
		}
		$data = "";
		$args = array();
		if (is_author()) {
			$args = array('param' => 'author_name', 'value' => get_the_author());
		}
		elseif (is_category()) {
			$arr = get_the_category();
			if (!empty($arr)) {
				$args = array('param' => 'category_name', 'value' => $arr[0]->name);
			}
		}
		elseif (is_date()) {
			if (is_year()) {
				$args = array('param' => 'year', 'value' => get_the_time("Y"));
			}
			elseif (is_month()) {
				$args = array('param' => 'm', 'value' => get_the_time("Ym"));
			}
			else {
				$args = array('param' => 'date_query', 'value' => get_the_time("Ymd"));
			}
		}
		elseif (is_search()) {
			$args = array('param' => 's', 'value' => get_search_query());
		}
		elseif (is_tag()) {
			$arr = get_the_tags();
			if (!empty($arr)) {
				$args = array('param' => 'tag', 'value' => $arr[0]->name);
			}
		}
		elseif (is_tax()) {
			$arr = get_the_taxonomies();
			if (!empty($arr)) {
				#$args = array('param' => 'tax_query', 'value' => ());
			}
		}
		if (!empty($args)) {
			$data = '+"&param='.$args['param'].'&value='.$args['value'].'"';
		}
		global $wp_query;
		?>
<script type="text/javascript">
jQuery(document).ready(function ($) {
	var count = 2;
	var max = <?php echo $wp_query->max_num_pages; ?>;
	var container_bottom = containerBottom();
	$(window).resize(function() {
		if(this.resizeTO) clearTimeout(this.resizeTO);
		this.resizeTO = setTimeout(function() {
			$(this).trigger('resizeEnd');
		}, 500);
	}).bind('resizeEnd', function() {
		if (count > max) {
			return false;
		}
		// fires once every 500ms
		container_bottom = containerBottom();
	});
	$(window).scroll(function() {
		if (count > max) {
			return false;
		}
		if ($(window).scrollTop() >= container_bottom) {
			loadPage(count);
			container_bottom = containerBottom();
			count++;
		}
	});
	function containerBottom() {
		var res;
		if ($("#<?php echo $this->container; ?>").length) {
			res = $("#<?php echo $this->container; ?>").offset().top + $("#<?php echo $this->container; ?>").height() - $(window).height();
		}
		else {
			res = $(document).height() - $(window).height();
		}
		return res;
	}
	function loadPage(pageNumber) {
		$.ajax({
			url: "<?php echo admin_url('admin-ajax.php'); ?>",
			type: 'POST',
			data: "action=infinite_scroll&page="+pageNumber<?php echo $data; ?>,
			success: function(html) {
				if ($("#<?php echo $this->container; ?>").length) {
					if ($("<?php echo $this->pagination_selector; ?>").length) {
						$("<?php echo $this->pagination_selector; ?>:visible").hide('fast');
					}
					$("#<?php echo $this->container; ?>").append(html);
				}
				else {
					$('body').append(html);
				}
			}
		});
		return false;
	}
});
</script><?php
	}

	/* functions */

	public static function loop_action() {
		$res = '';
		if (!wp_doing_ajax()) {
			return $res;
		}
		if (!empty($_POST)) {
			if (isset($_POST['action'])) {
				if ($_POST['action'] == 'infinite_scroll') {
					if (isset($_POST['param']) && isset($_POST['value'])) {
						$res = 'archive';
					}
					else {
						$res = 'posts';
					}
				}
			}
		}
		return $res;
	}
	
}
endif;
?>