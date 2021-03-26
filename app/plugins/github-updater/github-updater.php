<?php
// Exit if accessed directly.
defined('ABSPATH') || exit;

// TODO: switch to restful api - http://domain.com/wp-json/github-updater/v1/update/?key=

$theme_plugin = 'Halftheory_Clean_Plugin_GitHub_Updater';

if ( ! class_exists('Halftheory_Clean_Plugin_GitHub_Updater', false) ) :
    final class Halftheory_Clean_Plugin_GitHub_Updater {

        private $plugin_prefix = 'wp-halftheory';

    	public function __construct() {
            if ( is_admin() ) {
                add_action('ghu_refresh_transients', array( $this, 'ghu_refresh_transients' ));
            }
    	}

    	/* actions */

        public function ghu_refresh_transients() {
            // force plugins/themes to update using the RESTful endpoints.

            $func_get_url = function ( $url ) {
                $str = null;
                if ( function_exists('curl_init') ) {
                    $c = @curl_init();
                    // try 'correct' way.
                    curl_setopt($c, CURLOPT_URL, $url);
                    curl_setopt($c, CURLOPT_RETURNTRANSFER, true);
                    curl_setopt($c, CURLOPT_FOLLOWLOCATION, true);
                    curl_setopt($c, CURLOPT_MAXREDIRS, 10);
                    $str = curl_exec($c);
                    // try 'insecure' way.
                    if ( empty($str) ) {
                        curl_setopt($c, CURLOPT_URL, $url);
                        curl_setopt($c, CURLOPT_RETURNTRANSFER, true);
                        curl_setopt($c, CURLOPT_SSL_VERIFYPEER, false);
                        curl_setopt($c, CURLOPT_SSL_VERIFYHOST, 0);
                        curl_setopt($c, CURLOPT_USERAGENT, 'GitHub Updater');
                        $str = curl_exec($c);
                    }
                    curl_close($c);
                }
                if ( empty($str) ) {
                    $cmd = 'wget -v ' . $url . ' >/dev/null 2>&1';
                    @exec($cmd, $str);
                }
                if ( ! empty($str) ) {
                    return $str;
                }
                return false;
            };

            $api_url = add_query_arg(
                array(
                    'action' => rawurlencode('github-updater-update'),
                    'key'    => rawurlencode(get_site_option('github_updater_api_key')),
                ),
                admin_url('admin-ajax.php')
            );

            $res = array();

            // plugins
            // Ensure get_plugins() function is available.
            if ( ! function_exists('get_plugins') && is_readable(ABSPATH . 'wp-admin/includes/plugin.php') ) {
                require_once ABSPATH . 'wp-admin/includes/plugin.php';
            }
            $plugins_all = get_plugins();
            $plugins_all = array_keys($plugins_all);
            $plugins_func = function ( $str ) {
                list($folder, $file) = explode('/', $str, 2);
                return $folder;
            };
            $plugins_all = array_map($plugins_func, $plugins_all);
            if ( ! empty($plugins_all) ) {
                foreach ( $plugins_all as $plugin ) {
                    if ( $this->ghu_refresh_transients_plugin($plugin) === false ) {
                        continue;
                    }
                    $url = add_query_arg(array( 'plugin' => rawurlencode($plugin) ), $api_url);
                    if ( $str = $func_get_url($url) ) {
                        $res[] = $str;
                    }
                }
            }

            // themes
            $themes_all = wp_get_themes( array( 'errors' => null ) );
            $themes_all = array_keys($themes_all);
            if ( ! empty($themes_all) ) {
                foreach ( $themes_all as $theme ) {
                    if ( $this->ghu_refresh_transients_theme($theme) === false ) {
                        continue;
                    }
                    $url = add_query_arg(array( 'theme' => rawurlencode($theme) ), $api_url);
                    if ( $str = $func_get_url($url) ) {
                        $res[] = $str;
                    }
                }
            }

            if ( ! empty($res) ) {
                echo implode("<br />\n", $res);
                exit;
            }
        }

        /* functions */

        private function ghu_refresh_transients_plugin( $plugin ) {
            // condition for updating or ignoring plugin.
            $res = strpos($plugin, $this->plugin_prefix) === false ? false : true;
            return apply_filters('halftheory_ghu_refresh_transients_plugin', $res, $plugin);
        }

        private function ghu_refresh_transients_theme( $theme ) {
            // condition for updating or ignoring theme.
            $res = strpos($theme, $this->plugin_prefix) === false ? false : true;
            return apply_filters('halftheory_ghu_refresh_transients_theme', $res, $theme);
        }
    }
endif;
