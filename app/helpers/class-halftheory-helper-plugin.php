<?php
// Exit if accessed directly.
defined('ABSPATH') || exit;

if (!class_exists('Halftheory_Helper_Plugin')) :
class Halftheory_Helper_Plugin {

	public static $plugin_basename;
	public static $prefix;
	private $options = array();

	public function __construct($plugin_basename = '', $prefix = '') {
		$this->init($plugin_basename, $prefix);
	}

	public function init($plugin_basename = '', $prefix = '') {
		if (isset($this->plugin_is_network)) {
			unset($this->plugin_is_network);
		}
		if (!empty($plugin_basename)) {
			self::$plugin_basename = $plugin_basename;
		}
		if (!empty($prefix)) {
			self::$prefix = $prefix;
		}
		$this->options = array();
	}

	private function is_plugin_network() {
		if (isset($this->plugin_is_network)) {
			return $this->plugin_is_network;
		}
		$res = false;
		if (is_multisite()) {
			if (!function_exists('is_plugin_active_for_network')) {
				@require_once(ABSPATH.'/wp-admin/includes/plugin.php');
			}
			if (function_exists('is_plugin_active_for_network')) {
				if (is_plugin_active_for_network(self::$plugin_basename)) {
					$res = true;
				}
			}
		}
		$this->plugin_is_network = $res;
		return $res;
	}

	// options
	private function get_option_name($name = '', $is_network = null) {
		if (empty($name)) {
			$name = self::$prefix;
		}
		if (is_null($is_network)) {
			$is_network = $this->is_plugin_network();
		}
		if ($is_network) {
			$name = substr($name, 0, 255);
		}
		else {
			$name = substr($name, 0, 191);
		}
		return $name;
	}
	public function get_option($name = '', $key = '', $default = array()) {
		$name = $this->get_option_name($name);
		if (!isset($this->options[$name])) {
			if ($this->is_plugin_network()) {
				$option = get_site_option($name, array());
			}
			else {
				$option = get_option($name, array());
			}
			$this->options[$name] = $option;
		}
		if (!empty($key) && is_array($this->options[$name])) {
			if (array_key_exists($key, $this->options[$name])) {
				return $this->options[$name][$key];
			}
			return $default;
		}
		return $this->options[$name];
	}
	public function update_option($name = '', $value) {
		$name = $this->get_option_name($name);
		if ($this->is_plugin_network()) {
			$bool = update_site_option($name, $value);
		}
		else {
			$bool = update_option($name, $value);
		}
		if ($bool !== false) {
			$this->options[$name] = $value;
		}
		return $bool;
	}
	public function delete_option($name = '') {
		$name = $this->get_option_name($name);
		if ($this->is_plugin_network()) {
			$bool = delete_site_option($name);
		}
		else {
			$bool = delete_option($name);
		}
		if ($bool !== false && isset($this->options[$name])) {
			unset($this->options[$name]);
		}
		return $bool;
	}
	public function delete_option_uninstall($name = '') {
		$name_single = $this->get_option_name($name, false);
		global $wpdb;
		if (is_multisite()) {
			$name_network = $this->get_option_name($name, true);
			$wpdb->query("DELETE FROM $wpdb->sitemeta WHERE meta_key LIKE '".$name_network."%'");
			$current_blog_id = get_current_blog_id();
			$sites = get_sites();
			foreach ($sites as $key => $value) {
				switch_to_blog($value->blog_id);
				$wpdb->query("DELETE FROM $wpdb->options WHERE option_name LIKE '".$name_single."%'");
			}
			switch_to_blog($current_blog_id);
		}
		else {
			$wpdb->query("DELETE FROM $wpdb->options WHERE option_name LIKE '".$name_single."%'");
		}
	}

	// transients
	private function get_transient_name($name = '', $is_network = null) {
		if (empty($name)) {
			$name = self::$prefix;
		}
		if (is_null($is_network)) {
			$is_network = $this->is_plugin_network();
		}
		if ($is_network) {
			$name = substr($name, 0, 167);
		}
		else {
			$name = substr($name, 0, 172);
		}
		return $name;
	}
	public function get_transient($transient = '') {
		$transient = $this->get_transient_name($transient);
		if ($this->is_plugin_network()) {
			$value = get_site_transient($transient);
		}
		else {
			$value = get_transient($transient);
		}
		return $value;
	}
	public function set_transient($transient = '', $value, $expiration = 0) {
		$transient = $this->get_transient_name($transient);
		if (is_string($expiration)) {
			$expiration = strtotime('+'.trim($expiration, " -+")) - time();
			if (!$expiration || $expiration < 0) {
				$expiration = 0;
			}
		}
		if ($this->is_plugin_network()) {
			$bool = set_site_transient($transient, $value, $expiration);
		}
		else {
			$bool = set_transient($transient, $value, $expiration);
		}
		return $bool;
	}
	public function delete_transient($transient = '') {
		$transient = $this->get_transient_name($transient);
		if ($this->is_plugin_network()) {
			$bool = delete_site_transient($transient);
		}
		else {
			$bool = delete_transient($transient);
		}
		return $bool;
	}
	public function delete_transient_uninstall($transient = '') {
		$transient_single = $this->get_transient_name($transient, false);
		global $wpdb;
		if (is_multisite()) {
			$transient_network = $this->get_transient_name($transient, true);
			$wpdb->query("DELETE FROM $wpdb->sitemeta WHERE meta_key LIKE '_site_transient_".$transient_network."%' OR meta_key LIKE '_site_transient_timeout_".$transient_network."%'");
			$current_blog_id = get_current_blog_id();
			$sites = get_sites();
			foreach ($sites as $key => $value) {
				switch_to_blog($value->blog_id);
				$wpdb->query("DELETE FROM $wpdb->options WHERE option_name LIKE '_transient_".$transient_single."%' OR option_name LIKE '_transient_timeout_".$transient_single."%'");
			}
			switch_to_blog($current_blog_id);
		}
		else {
			$wpdb->query("DELETE FROM $wpdb->options WHERE option_name LIKE '_transient_".$transient_single."%' OR option_name LIKE '_transient_timeout_".$transient_single."%'");
		}
	}

	// postmeta
	private function get_postmeta_name($name = '') {
		if (empty($name)) {
			$name = self::$prefix;
		}
		$name = substr($name, 0, 255);
		return $name;
	}
	public function delete_postmeta_uninstall($name = '') {
		$name = $this->get_postmeta_name($name);
		global $wpdb;
		if (is_multisite()) {
			$current_blog_id = get_current_blog_id();
			$sites = get_sites();
			foreach ($sites as $key => $value) {
				switch_to_blog($value->blog_id);
				$wpdb->query("DELETE FROM $wpdb->postmeta WHERE meta_key LIKE '".$name."%'");
			}
			switch_to_blog($current_blog_id);
		}
		else {
			$wpdb->query("DELETE FROM $wpdb->postmeta WHERE meta_key LIKE '".$name."%'");
		}
	}

	// usermeta
	private function get_usermeta_name($name = '') {
		if (empty($name)) {
			$name = self::$prefix;
		}
		$name = substr($name, 0, 255);
		return $name;
	}
	public function delete_usermeta_uninstall($name = '') {
		$name = $this->get_usermeta_name($name);
		global $wpdb;
		$wpdb->query("DELETE FROM $wpdb->usermeta WHERE meta_key LIKE '".$name."%'");
	}

}
endif;
?>