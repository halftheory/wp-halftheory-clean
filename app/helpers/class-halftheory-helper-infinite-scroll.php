<?php
/*
Available filters:
halftheory_helper_infinite_scroll_theme_support
halftheory_helper_infinite_scroll_template
*/

// Exit if accessed directly.
defined('ABSPATH') || exit;

if (!class_exists('Halftheory_Helper_Infinite_Scroll')) :
class Halftheory_Helper_Infinite_Scroll {

	public $container = 'primary';
	public $pagination_selector = '.pagination';
	private static $ajax_action = 'infinite_scroll';

	public function __construct() {
		add_action('after_setup_theme', array($this,'after_setup_theme'), 20);
		add_action('wp_ajax_'.self::$ajax_action, array($this,'wp_ajax_infinite_scroll')); // for logged in user
		add_action('wp_ajax_nopriv_'.self::$ajax_action, array($this,'wp_ajax_infinite_scroll')); // if user not logged in
		add_action('get_footer', array($this,'get_footer'), 20);
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
		if (!isset($_POST)) {
			wp_die();
		}
		if (empty($_POST)) {
			wp_die();
		}
		if (!isset($_POST['page'])) {
			wp_die();
		}
		$args = array('paged' => $_POST['page'], 'post_status' => 'publish,inherit');
		if (isset($_POST['param']) && isset($_POST['value'])) {
			// turn date_query and tax_query into arrays
			if ($_POST['param'] == 'date_query') {
				$args[$_POST['param']] = array(
					'after' => strtotime($_POST['value']),
					'before' => strtotime("+1 day", strtotime($_POST['value'])),
				);
			}
			elseif ($_POST['param'] == 'tax_query') {
				$term = get_term((int)$_POST['value']);
				if (!empty($term) && !is_wp_error($term)) {
					$args[$_POST['param']] = array(
						array(
							'taxonomy' => $term->taxonomy,
							'field' => 'term_id',
							'terms' => (int)$_POST['value']
						)
					);
				}
			}
			else {
				$args[$_POST['param']] = $_POST['value'];
			}
		}

		$posts = query_posts($args);
		if (empty($posts)) {
			wp_reset_query();
			wp_die();
		}
		// copy wp_query conditions from the main query
		if (isset($_POST['conditions'])) {
			global $wp_query;
			foreach ($_POST['conditions'] as $key => $value) {
				if (isset($wp_query->$key)) {
					$wp_query->$key = is_true($value);
				}
			}
		}
		if (Halftheory_Clean::has_helper_plugin()) {
			while (have_posts()) { // Start the loop.
				the_post();
				global $post;
				if ($template = Halftheory_Helper_Plugin::get_template()) {
					$template = apply_filters('halftheory_helper_infinite_scroll_template', $template, $post, $args);
					load_template($template, false);
				}
			} // End the loop.
		}
		wp_reset_query();
		wp_die();
	}

	public function get_footer($name = '') {
		$this->enqueue_scripts();
	}

	/* functions */

	public function enqueue_scripts() {
		if (!$this->print_scripts()) {
			return;
		}
		$handle = self::$ajax_action;
		wp_enqueue_script($handle, get_template_directory_uri().'/app/helpers/infinite-scroll/infinite-scroll.min.js', array('jquery'), Halftheory_Clean::get_theme_version(get_template_directory().'/app/helpers/infinite-scroll/infinite-scroll.min.js'), true);
		// build the data array
		$data = array(
			'action' => self::$ajax_action,
		);
		if (is_author()) {
			$obj = get_queried_object();
			if (!empty($obj)) {
				$data['param'] = 'author';
				$data['value'] = $obj->ID;
			}
		}
		elseif (is_category()) {
			$obj = get_queried_object();
			if (!empty($obj)) {
				$data['param'] = 'cat';
				$data['value'] = $obj->term_id;
			}
		}
		elseif (is_date()) {
			if (is_year()) {
				$data['param'] = 'year';
				$data['value'] = get_the_time("Y");
			}
			elseif (is_month()) {
				$data['param'] = 'm';
				$data['value'] = get_the_time("Ym");
			}
			else {
				$data['param'] = 'date_query';
				$data['value'] = get_the_time("Ymd");
			}
		}
		elseif (is_search()) {
			$data['param'] = 's';
			$data['value'] = get_search_query();
		}
		elseif (is_post_type_archive()) {
			$obj = get_queried_object();
			if (!empty($obj)) {
				$data['param'] = 'post_type';
				$data['value'] = $obj->post_type;
			}
		}
		elseif (is_tag()) {
			$obj = get_queried_object();
			if (!empty($obj)) {
				$data['param'] = 'tag_id';
				$data['value'] = $obj->term_id;
			}
		}
		elseif (is_tax()) {
			$obj = get_queried_object();
			if (!empty($obj)) {
				$data['param'] = 'tax_query';
				$data['value'] = $obj->term_id;
			}
		}
		global $wp_query;
		// get current wp_query conditions
		if (Halftheory_Clean::has_helper_plugin()) {
			$conditions = array();
			foreach (Halftheory_Helper_Plugin::get_template_tags() as $key => $value) {
				if (isset($wp_query->$key)) {
					$conditions[$key] = (int)$wp_query->$key;
				}
			}
			if (!empty($conditions)) {
				$data['conditions'] = $conditions;
			}
		}
		// compile all the js data
		$js_data = array(
			'ajaxurl' => esc_url(admin_url('admin-ajax.php')),
			'data' => $data,
			'max' => $wp_query->max_num_pages,
			'container' => $this->container,
			'pagination_selector' => $this->pagination_selector,
		);
		global $paged;
		if (!empty($paged)) {
			$js_data['paged'] = $paged;
		}
		/*
		if (wp_is_mobile()) {
			$js_data['wp_is_mobile'] = 1;
		}
		*/
		wp_localize_script($handle, $handle, $js_data);
	}

	private function print_scripts() {
		if (is_embed()) {
			return false;
		}
		elseif (is_404()) {
			return false;
		}
		elseif (is_privacy_policy()) {
			return false;
		}		
		elseif (is_singular()) {
			return false;
		}
		if (function_exists('is_login_page')) {
			if (is_login_page()) {
				return false;
			}
		}
		if (function_exists('is_signup_page')) {
			if (is_signup_page()) {
				return false;
			}
		}
		global $wp_query, $paged;
		if ($paged >= $wp_query->max_num_pages) {
			return false;
		}
		return true;
	}
}
endif;
?>