<?php
// Exit if accessed directly.
defined('ABSPATH') || exit;

if (!class_exists('Halftheory_Helper_Infinite_Scroll')) :
class Halftheory_Helper_Infinite_Scroll {

	public $container = 'primary';

	public function __construct() {
		add_action('after_setup_theme', array($this,'after_setup_theme'), 20);
		add_action('wp_ajax_infinite_scroll', array($this,'wp_ajax_infinite_scroll')); // for logged in user
		add_action('wp_ajax_nopriv_infinite_scroll', array($this,'wp_ajax_infinite_scroll')); // if user not logged in
		add_action('wp_footer', array($this,'wp_footer'), 20);
	}

	/* actions */

	public function after_setup_theme() {
		add_theme_support('infinite-scroll', array(
			'container' => $this->container,
			'footer_widgets' => false,
		));
		// ability to change array?
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
			$args[$_POST['param']] = $_POST['value'];
		}
		// turn date_query and tax_query into arrays
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
			#$args = array('param' => 'date_query', 'value' => get_the_time("Ymd"));
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
	var total = <?php echo $wp_query->max_num_pages; ?>;
	$(window).scroll(function () {
		if ($(window).scrollTop() == $(document).height() - $(window).height()) {
			if (count > total) {
				return false;
			}
			else {
				loadPage(count);
			}
			count++;
		}
	});
	function loadPage(pageNumber) {
		$.ajax({
			url: "<?php echo admin_url('admin-ajax.php'); ?>",
			type: 'POST',
			data: "action=infinite_scroll&page="+pageNumber<?php echo $data; ?>,
			success: function(html) {
				$("#<?php echo $this->container; ?>").append(html);
			}
		});
		return false;
	}
});
</script><?php
	}
	
}
endif;
?>