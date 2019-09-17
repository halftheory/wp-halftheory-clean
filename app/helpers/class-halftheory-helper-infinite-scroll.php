<?php
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
		if (empty($_POST)) {
			exit;
		}
		if (!isset($_POST['page'])) {
			exit;
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
		query_posts($args);
		get_template_part('loop');
		exit;
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
		wp_enqueue_script($handle, get_template_directory_uri().'/app/helpers/infinite-scroll/infinite-scroll.min.js', array('jquery'), null, true);
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
		// compile all the js data
		global $wp_query;
		$js_data = array(
			'ajaxurl' => esc_url(admin_url('admin-ajax.php')),
			'data' => $data,
			'max' => $wp_query->max_num_pages,
			'container' => $this->container,
			'pagination_selector' => $this->pagination_selector,
		);
		if (wp_is_mobile()) {
			$js_data['wp_is_mobile'] = 1;
		}
		wp_localize_script($handle, $handle, $js_data);
	}

	private function print_scripts() {
		if (is_singular()) {
			return false;
		}
		elseif (is_404()) {
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
		return true;
	}

	public static function loop_action() {
		$res = '';
		if (!wp_doing_ajax()) {
			return $res;
		}
		if (!empty($_POST)) {
			if (isset($_POST['action'])) {
				if ($_POST['action'] == self::$ajax_action) {
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