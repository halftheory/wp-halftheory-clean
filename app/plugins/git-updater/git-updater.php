<?php
// Exit if accessed directly.
defined('ABSPATH') || exit;

$theme_plugin = 'Halftheory_Clean_Plugin_Git_Updater';

if ( ! class_exists('Halftheory_Clean_Plugin_Git_Updater', false) ) :
    final class Halftheory_Clean_Plugin_Git_Updater {

        private $plugin_prefix = 'wp-halftheory';
        private $version = 9;

        public function __construct() {
            if ( is_admin() && current_user_can('manage_options') ) {
                $version = '9.9.10';
                $locations = array(
                    'git-updater/git-updater.php',
                    'git-updater/github-updater.php',
                    'github-updater/github-updater.php',
                );
                foreach ( $locations as $location ) {
                    if ( $tmp = $this->get_plugin_data_field($location, 'Version') ) {
                        $version = $tmp;
                        break;
                    }
                }
                list($version_major) = explode('.', $version, 1);
                $this->version = (int) $version_major;
                // load different actions.
                if ( $this->version >= 10 ) {
                    add_action('gu_refresh_transients', array( $this, 'refresh_transients_v10' ));
                    add_filter('gu_disable_wpcron', '__return_true');
                } elseif ( $this->version === 9 ) {
                    add_action('ghu_refresh_transients', array( $this, 'refresh_transients_v9' ));
                    add_filter('github_updater_disable_wpcron', '__return_true');
                } elseif ( $this->version <= 8 ) {
                    add_action('ghu_refresh_transients', array( $this, 'refresh_transients_v8' ));
                    add_filter('github_updater_disable_wpcron', '__return_true');
                }
            }
        }

        /* actions */

        public function refresh_transients_v10() {
            if ( ! in_array('git-updater/v1', rest_get_server()->get_namespaces(), true) ) {
                return;
            }
            $res = array();
            // force plugins/themes to update using the RESTful endpoints.
            $request = WP_REST_Request::from_url(get_rest_url() . 'git-updater/v1/update');
            $request->set_param('key', get_site_option('git_updater_api_key')); // 'pro' plugin - not tested!

            // only way to prevent 'wp_die' and 'wp_send_json_success' from exiting is to force ajax and collect the response in a buffer.
            add_filter('wp_doing_ajax', array( $this, 'wp_doing_ajax' ));
            add_filter('wp_die_ajax_handler', array( $this, 'wp_die_ajax_handler' ));

            // plugins.
            foreach ( $this->get_plugins() as $plugin ) {
                $request->set_param('plugin', $plugin);
                $tmp = null;
                ob_start();
                if ( $response = rest_do_request($request) ) {
                    $data = $response->get_data();
                }
                $tmp = ob_get_clean();
                if ( ! empty($tmp) ) {
                    $res[] = is_string($tmp) ? $tmp : wp_json_encode($tmp);
                }
            }
            $request->set_param('plugin', null);

            // themes.
            foreach ( $this->get_themes() as $theme ) {
                $request->set_param('theme', $theme);
                $tmp = null;
                ob_start();
                if ( $response = rest_do_request($request) ) {
                    $data = $response->get_data();
                }
                $tmp = ob_get_clean();
                if ( ! empty($tmp) ) {
                    $res[] = is_string($tmp) ? $tmp : wp_json_encode($tmp);
                }
            }

            remove_filter('wp_doing_ajax', array( $this, 'wp_doing_ajax' ));
            remove_filter('wp_die_ajax_handler', array( $this, 'wp_die_ajax_handler' ));

            if ( ! empty($res) ) {
                $res = array_map('esc_html', $res);
                echo implode("<br />\n", $res);
                exit;
            }
        }

        public function refresh_transients_v9() {
            if ( ! in_array('github-updater/v1', rest_get_server()->get_namespaces(), true) ) {
                return;
            }
            $res = array();
            // force plugins/themes to update using the RESTful endpoints.
            $request = WP_REST_Request::from_url(get_rest_url() . 'github-updater/v1/update');
            $request->set_param('key', get_site_option('github_updater_api_key'));

            // only way to prevent 'wp_die' and 'wp_send_json_success' from exiting is to force ajax and collect the response in a buffer.
            add_filter('wp_doing_ajax', array( $this, 'wp_doing_ajax' ));
            add_filter('wp_die_ajax_handler', array( $this, 'wp_die_ajax_handler' ));

            // plugins.
            foreach ( $this->get_plugins() as $plugin ) {
                $request->set_param('plugin', $plugin);
                $tmp = null;
                ob_start();
                if ( $response = rest_do_request($request) ) {
                    $data = $response->get_data();
                }
                $tmp = ob_get_clean();
                if ( ! empty($tmp) ) {
                    $res[] = is_string($tmp) ? $tmp : wp_json_encode($tmp);
                }
            }
            $request->set_param('plugin', null);

            // themes.
            foreach ( $this->get_themes() as $theme ) {
                $request->set_param('theme', $theme);
                $tmp = null;
                ob_start();
                if ( $response = rest_do_request($request) ) {
                    $data = $response->get_data();
                }
                $tmp = ob_get_clean();
                if ( ! empty($tmp) ) {
                    $res[] = is_string($tmp) ? $tmp : wp_json_encode($tmp);
                }
            }

            remove_filter('wp_doing_ajax', array( $this, 'wp_doing_ajax' ));
            remove_filter('wp_die_ajax_handler', array( $this, 'wp_die_ajax_handler' ));

            if ( ! empty($res) ) {
                $res = array_map('esc_html', $res);
                echo implode("<br />\n", $res);
                exit;
            }
        }

        public function refresh_transients_v8() {
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
                if ( empty($str) ) {
                    return false;
                }
                return $str;
            };

            $api_url = add_query_arg(
                array(
                    'action' => rawurlencode('github-updater-update'),
                    'key'    => rawurlencode(get_site_option('github_updater_api_key')),
                ),
                admin_url('admin-ajax.php')
            );

            $res = array();

            // plugins.
            foreach ( $this->get_plugins() as $plugin ) {
                $url = add_query_arg(array( 'plugin' => rawurlencode($plugin) ), $api_url);
                if ( $str = $func_get_url($url) ) {
                    $res[] = $str;
                }
            }

            // themes.
            foreach ( $this->get_themes() as $theme ) {
                $url = add_query_arg(array( 'theme' => rawurlencode($theme) ), $api_url);
                if ( $str = $func_get_url($url) ) {
                    $res[] = $str;
                }
            }

            if ( ! empty($res) ) {
                $res = array_map('esc_html', $res);
                echo implode("<br />\n", $res);
                exit;
            }
        }

        public function wp_doing_ajax() {
            return true;
        }

        public function wp_die_ajax_handler() {
            return '__return_true';
        }

        /* functions */

        private function get_plugins() {
            // ensure get_plugins() function is available.
            if ( ! function_exists('get_plugins') && is_readable(ABSPATH . 'wp-admin/includes/plugin.php') ) {
                require_once ABSPATH . 'wp-admin/includes/plugin.php';
            }
            $plugins = get_plugins();
            if ( empty($plugins) ) {
                return array();
            }
            $plugins = array_keys($plugins);
            $plugins_func = function ( $str ) {
                list($folder, $file) = explode('/', $str, 2);
                return $folder;
            };
            $plugins = array_map($plugins_func, $plugins);
            foreach ( $plugins as $key => $plugin ) {
                if ( $this->ghu_refresh_transients_plugin($plugin) === false ) {
                    unset($plugins[ $key ]);
                }
            }
            return $plugins;
        }

        private function get_themes() {
            $themes = wp_get_themes( array( 'errors' => null ) );
            if ( empty($themes) ) {
                return array();
            }
            $themes = array_keys($themes);
            foreach ( $themes as $key => $theme ) {
                if ( $this->ghu_refresh_transients_theme($theme) === false ) {
                    unset($themes[ $key ]);
                }
            }
            return $themes;
        }

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

        /* copied from class-halftheory-helper-plugin.php */
        private function get_plugin_data_field( $plugin_file = null, $field = null ) {
            if ( empty($plugin_file) || empty($field) ) {
                return null; // better to return null rather than false - better for wp_enqueue_scripts etc.
            }
            if ( strpos($plugin_file, WP_PLUGIN_DIR) === false && strpos($plugin_file, WPMU_PLUGIN_DIR) === false ) {
                $plugin_file = WP_PLUGIN_DIR . '/' . ltrim($plugin_file, '/ ');
            }
            if ( ! file_exists($plugin_file) ) {
                return null;
            }
            if ( ! function_exists('get_plugin_data') && is_readable(ABSPATH . 'wp-admin/includes/plugin.php') ) {
                require_once ABSPATH . 'wp-admin/includes/plugin.php';
            }
            $plugin_data = get_plugin_data($plugin_file);
            if ( ! is_array($plugin_data) ) {
                return null;
            }
            if ( ! isset($plugin_data[ $field ]) ) {
                return null;
            }
            return $plugin_data[ $field ];
        }
    }
endif;
