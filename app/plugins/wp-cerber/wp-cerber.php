<?php
// Exit if accessed directly.
defined('ABSPATH') || exit;

$theme_plugin = 'Halftheory_Clean_Plugin_WP_Cerber';

if ( ! class_exists('Halftheory_Clean_Plugin_WP_Cerber', false) ) :
	final class Halftheory_Clean_Plugin_WP_Cerber {

		public function __construct() {
            if ( ! function_exists('cerber_init') ) {
                return;
            }
            add_filter('has_rest_namespace', array( $this, 'has_rest_namespace' ), 10, 2);
		}

		/* actions */

		public function has_rest_namespace( $res, $namespace ) {
            if ( ! $res ) {
                return $res;
            }
            $option = is_multisite() ? get_site_option('cerber-hardening') : get_option('cerber-hardening');
            if ( is_array($option) && isset($option['norest']) && ! empty($option['norest']) && isset($option['restwhite']) && ! empty($option['restwhite']) && is_array($option['restwhite']) ) {
                $res = false;
                if ( in_array($namespace, $option['restwhite'], true) ) {
                    $res = $namespace;
                } elseif ( strpos($namespace, '/') !== false ) {
                    // try to find only first part of the name.
                    list($name) = explode('/', $namespace);
                    if ( in_array($name, $option['restwhite'], true) ) {
                        $res = $namespace;
                    }
                }
            }
            return $res;
		}
	}
endif;
