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
                $this->version = $this->get_plugin_version('9.9.10');
                add_action('current_screen', array( $this, 'current_screen' ));
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

        public function current_screen( $current_screen ) {
            if ( strpos($current_screen->base, 'git-updater') !== false || strpos($current_screen->base, 'github-updater') !== false ) {
                if ( $this->get_helper_plugin(true) ) {
                    add_action('admin_notices', array( $this, 'admin_notices' ));
                    add_action('network_admin_notices', array( $this, 'admin_notices' ));
                }
            }
        }

        public function admin_notices() {
            if ( $hp = $this->get_helper_plugin() ) {
                $hp->admin_notices();
            }
        }

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
                $data = null;
                ob_start();
                if ( $response = rest_do_request($request) ) {
                    $data = $response->get_data();
                }
                $data = ob_get_clean();
                if ( ! empty($data) ) {
                    if ( function_exists('json_to_array') ) {
                        $tmp = json_to_array($data);
                    } else {
                        $tmp = json_decode($data, true);
                    }
                    if ( is_array($tmp) ) {
                        if ( array_key_exists('data', $tmp) ) {
                            $res[ 'Plugin::' . $plugin ] = $tmp;
                        } else {
                            foreach ( $tmp as $value ) {
                                if ( array_key_exists('data', $value) ) {
                                    if ( isset($value['data']['messages']) && ! empty($value['data']['messages']) ) {
                                        $res[ 'Plugin::' . $plugin ] = $value;
                                        break;
                                    }
                                }
                            }
                        }
                    }
                }
            }
            $request->set_param('plugin', null);

            // themes.
            foreach ( $this->get_themes() as $theme ) {
                $request->set_param('theme', $theme);
                $data = null;
                ob_start();
                if ( $response = rest_do_request($request) ) {
                    $data = $response->get_data();
                }
                $data = ob_get_clean();
                if ( ! empty($data) ) {
                    if ( function_exists('json_to_array') ) {
                        $tmp = json_to_array($data);
                    } else {
                        $tmp = json_decode($data, true);
                    }
                    if ( is_array($tmp) ) {
                        if ( array_key_exists('data', $tmp) ) {
                            $res[ 'Theme::' . $theme ] = $tmp;
                        } else {
                            foreach ( $tmp as $value ) {
                                if ( array_key_exists('data', $value) ) {
                                    if ( isset($value['data']['messages']) && ! empty($value['data']['messages']) ) {
                                        $res[ 'Theme::' . $theme ] = $value;
                                        break;
                                    }
                                }
                            }
                        }
                    }
                }
            }

            remove_filter('wp_doing_ajax', array( $this, 'wp_doing_ajax' ));
            remove_filter('wp_die_ajax_handler', array( $this, 'wp_die_ajax_handler' ));

            $this->display_results($res);
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
                $data = null;
                ob_start();
                if ( $response = rest_do_request($request) ) {
                    $data = $response->get_data();
                }
                $data = ob_get_clean();
                if ( ! empty($data) ) {
                    if ( function_exists('json_to_array') ) {
                        $tmp = json_to_array($data);
                    } else {
                        $tmp = json_decode($data, true);
                    }
                    if ( is_array($tmp) ) {
                        if ( array_key_exists('data', $tmp) ) {
                            $res[ 'Plugin::' . $plugin ] = $tmp;
                        } else {
                            foreach ( $tmp as $value ) {
                                if ( array_key_exists('data', $value) ) {
                                    if ( isset($value['data']['messages']) && ! empty($value['data']['messages']) ) {
                                        $res[ 'Plugin::' . $plugin ] = $value;
                                        break;
                                    }
                                }
                            }
                        }
                    }
                }
            }
            $request->set_param('plugin', null);

            // themes.
            foreach ( $this->get_themes() as $theme ) {
                $request->set_param('theme', $theme);
                $data = null;
                ob_start();
                if ( $response = rest_do_request($request) ) {
                    $data = $response->get_data();
                }
                $data = ob_get_clean();
                if ( ! empty($data) ) {
                    if ( function_exists('json_to_array') ) {
                        $tmp = json_to_array($data);
                    } else {
                        $tmp = json_decode($data, true);
                    }
                    if ( is_array($tmp) ) {
                        if ( array_key_exists('data', $tmp) ) {
                            $res[ 'Theme::' . $theme ] = $tmp;
                        } else {
                            foreach ( $tmp as $value ) {
                                if ( array_key_exists('data', $value) ) {
                                    if ( isset($value['data']['messages']) && ! empty($value['data']['messages']) ) {
                                        $res[ 'Theme::' . $theme ] = $value;
                                        break;
                                    }
                                }
                            }
                        }
                    }
                }
            }

            remove_filter('wp_doing_ajax', array( $this, 'wp_doing_ajax' ));
            remove_filter('wp_die_ajax_handler', array( $this, 'wp_die_ajax_handler' ));

            $this->display_results($res);
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
                    $res[ 'Plugin::' . $plugin ] = $str;
                }
            }

            // themes.
            foreach ( $this->get_themes() as $theme ) {
                $url = add_query_arg(array( 'theme' => rawurlencode($theme) ), $api_url);
                if ( $str = $func_get_url($url) ) {
                    $res[ 'Theme::' . $theme ] = $str;
                }
            }

            $this->display_results($res);
        }

        public function wp_doing_ajax() {
            return true;
        }

        public function wp_die_ajax_handler() {
            return '__return_true';
        }

        /* functions */

        private function get_helper_plugin( $load = false ) {
            if ( method_exists('Halftheory_Clean', 'get_helper_plugin') ) {
                if ( $hp = Halftheory_Clean::get_instance()->get_helper_plugin($load) ) {
                    return $hp;
                }
            }
            return false;
        }

        private function get_plugin_version( $version = null ) {
            $locations = array(
                'git-updater/git-updater.php',
                'git-updater/github-updater.php',
                'github-updater/github-updater.php',
            );
            $tmp = null;
            $hp = $this->get_helper_plugin();
            foreach ( $locations as $location ) {
                if ( $hp ) {
                    $tmp = $hp->get_plugin_data_field($location, 'Version');
                } else {
                    $tmp = $this->get_plugin_data_field($location, 'Version');
                }
                if ( $tmp ) {
                    $version = $tmp;
                    break;
                }
            }
            if ( $version ) {
                list($version_major) = explode('.', $version, 1);
                $version = (int) $version_major;
            }
            return $version;
        }

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

        private function display_results( $res ) {
            if ( empty($res) ) {
                return;
            }
            if ( $hp = $this->get_helper_plugin(true) ) {
                foreach ( $res as $key => $value ) {
                    $class = is_array($value) && array_key_exists('success', $value) && ! empty($value['success']) ? 'success' : 'error';
                    $message = '<strong>' . $key . '</strong>';
                    if ( is_array($value) && isset($value['data']['messages']) ) {
                        if ( is_array($value['data']['messages']) ) {
                            $value['data']['messages'] = array_filter(array_values($value['data']['messages']));
                            $k = count($value['data']['messages']) - 1;
                            $message .= ' - ' . $value['data']['messages'][ $k ];
                        } elseif ( is_string($value['data']['messages']) ) {
                            $message .= ' - ' . $value['data']['messages'];
                        }
                    } elseif ( is_string($value) ) {
                        $message .= ' - ' . $value;
                    }
                    $message = rtrim($message, '.') . '.';
                    $hp->admin_notice_add($class, $message);
                }
                $hp->admin_notices_set();
            } else {
                echo "<code>\n";
                print_r($res);
                echo "</code>\n";
                exit;
            }
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
