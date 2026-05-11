<?php
/*
Plugin Name: Complete Maintenance Mode
Plugin URI: https://profiles.wordpress.org/kimopensourcer
Description: Complete maintenance and under construction mode for your website with full customization. All features included.
Author: Kim OpenSourcer
Version: 1.0.2
Requires at least: 5.0
Requires PHP: 7.0
Tested up to: 6.7
License: GPLv2 or later
Text Domain: complete-maintenance-mode
*/

if (!defined('ABSPATH')) { die; }

define('MMF_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('MMF_PLUGIN_URL', plugin_dir_url(__FILE__));
define('MMF_OPTIONS_KEY', 'mmf_options');
define('MMF_META_KEY', 'mmf_meta');

class Complete_Maintenance_Mode
{
    static $version = '1.0.2';

    static function init()
    {
        if (false === self::check_wp_version(4.0)) { return false; }

        if (is_admin()) {
            self::maybe_upgrade();
            add_action('admin_menu', array(__CLASS__, 'admin_menu'));
            add_action('admin_init', array(__CLASS__, 'register_settings'));
            add_filter('plugin_action_links_' . plugin_basename(__FILE__), array(__CLASS__, 'plugin_action_links'));
            add_action('admin_action_mmf_change_status', array(__CLASS__, 'change_status'));
            add_action('admin_action_mmf_reset_settings', array(__CLASS__, 'reset_settings'));
            add_action('admin_action_mmf_dismiss_notice', array(__CLASS__, 'dismiss_notice'));
            add_action('admin_notices', array(__CLASS__, 'admin_notices'));
            add_action('admin_enqueue_scripts', array(__CLASS__, 'admin_enqueue_scripts'), 100, 1);
            // After WP Settings API saves options, redirect back to our page
        } else {
            add_action('wp', array(__CLASS__, 'display_maintenance_page'), 0, 1);
            add_filter('login_message', array(__CLASS__, 'login_message'));
            add_action('do_feed_rss', array(__CLASS__, 'disable_feed'), 0, 1);
            add_action('do_feed_rss2', array(__CLASS__, 'disable_feed'), 0, 1);
            add_action('do_feed_atom', array(__CLASS__, 'disable_feed'), 0, 1);
            add_action('wp_footer', array(__CLASS__, 'whitelisted_notice'));
        }

        add_action('wp_before_admin_bar_render', array(__CLASS__, 'admin_bar'));
        add_action('wp_head', array(__CLASS__, 'admin_bar_style'));
        add_action('admin_head', array(__CLASS__, 'admin_bar_style'));
    }

    static function check_wp_version($min_version)
    {
        if (!version_compare(get_bloginfo('version'), $min_version, '>=')) {
            add_action('admin_notices', array(__CLASS__, 'notice_min_wp_version'));
            return false;
        }
        return true;
    }

    static function notice_min_wp_version()
    {
        echo '<div class="error"><p>Complete Maintenance Mode <b>requires WordPress 4.0</b>. You have ' . esc_html(get_bloginfo('version')) . '. Please update.</p></div>';
    }

    static function maybe_upgrade()
    {
        $meta = self::get_meta();
        if (isset($meta['options_ver']) && $meta['options_ver'] == self::$version) return;
        $meta['options_ver'] = self::$version;
        update_option(MMF_META_KEY, $meta);
    }

    static function get_options()
    {
        $options = get_option(MMF_OPTIONS_KEY, array());
        if (!is_array($options)) $options = array();
        return array_merge(self::default_options(), $options);
    }

    static function get_meta()
    {
        $meta = get_option(MMF_META_KEY, array());
        if (!is_array($meta) || empty($meta)) {
            $meta = array('first_version' => self::$version, 'first_install' => time());
            update_option(MMF_META_KEY, $meta);
        }
        return $meta;
    }

    static function default_options()
    {
        return array(
            'status' => '0',
            'end_date' => '',
            'end_date_enabled' => false,
            'search_engines_noindex' => '1',
            'ga_tracking_id' => '',
            'ga_tracking_enabled' => false,
            'theme' => 'gradient-mesh',
            'custom_css' => '',
            'title' => get_bloginfo('name') . ' - Under Construction',
            'description' => get_bloginfo('description'),
            'heading1' => __('Sorry, we\'re doing some work on the site', 'complete-maintenance-mode'),
            'content' => __('Thank you for being patient. We are doing some work on the site and will be back shortly.', 'complete-maintenance-mode'),
            'social_facebook' => '',
            'social_twitter' => '',
            'social_linkedin' => '',
            'social_youtube' => '',
            'social_vimeo' => '',
            'social_pinterest' => '',
            'social_dribbble' => '',
            'social_behance' => '',
            'social_instagram' => '',
            'social_tumblr' => '',
            'social_vk' => '',
            'social_skype' => '',
            'social_whatsapp' => '',
            'social_telegram' => '',
            'social_email' => '',
            'social_phone' => '',
            'login_button' => '1',
            'linkback' => '1',
            'whitelisted_roles' => array('administrator'),
            'whitelisted_users' => array()
        );
    }

    static function register_settings()
    {
        register_setting(MMF_OPTIONS_KEY, MMF_OPTIONS_KEY, array(__CLASS__, 'sanitize_settings'));
    }

    static function sanitize_settings($options)
    {
        $old_options = self::get_options();

        // Keys that are arrays — skip them in the string foreach
        $array_keys = array('whitelisted_roles', 'whitelisted_users');

        foreach ($options as $key => $value) {
            if (in_array($key, $array_keys)) { continue; }
            if (in_array($key, array('title', 'description', 'heading1', 'content'))) {
                $options[$key] = trim($value);
            } else {
                $options[$key] = trim(sanitize_text_field($value));
            }
        }
        $options['title'] = wp_kses_post($options['title']);
        $options['description'] = wp_kses_post($options['description']);
        $options['heading1'] = wp_kses_post($options['heading1']);
        $options['content'] = wp_kses_post($options['content']);

        // Sanitize social URL fields with esc_url_raw (defense-in-depth)
        $social_url_keys = array(
            'social_facebook', 'social_twitter', 'social_linkedin', 'social_youtube',
            'social_vimeo', 'social_pinterest', 'social_dribbble', 'social_behance',
            'social_instagram', 'social_tumblr', 'social_vk', 'social_telegram'
        );
        foreach ($social_url_keys as $key) {
            if (!empty($options[$key])) {
                $options[$key] = esc_url_raw($options[$key]);
            }
        }

        // Handle array fields
        $options['whitelisted_roles'] = empty($options['whitelisted_roles']) ? array() : array_map('sanitize_text_field', array_values(array_filter((array)$options['whitelisted_roles'])));
        $options['whitelisted_users'] = empty($options['whitelisted_users']) ? array() : array_map('intval', array_values(array_filter((array)$options['whitelisted_users'])));

        // Handle checkbox toggles: when unchecked they are absent from POST
        $options = self::check_var_isset($options, array(
            'status' => '0',
            'linkback' => '0',
            'login_button' => '0',
            'search_engines_noindex' => '0',
            'end_date_enabled' => false,
            'ga_tracking_enabled' => false
        ));

        if (empty($options['end_date_enabled'])) $options['end_date'] = '';
        if (empty($options['ga_tracking_enabled'])) $options['ga_tracking_id'] = '';
        return array_merge($old_options, $options);
    }

    static function check_var_isset($values, $variables)
    {
        foreach ($variables as $key => $value) {
            if (!isset($values[$key])) $values[$key] = $value;
        }
        return $values;
    }

    static function admin_menu()
    {
        add_options_page(__('Complete Maintenance Mode', 'complete-maintenance-mode'), __('Complete Maintenance Mode', 'complete-maintenance-mode'), 'manage_options', 'mmf', array(__CLASS__, 'main_page'));
    }

    static function plugin_action_links($links)
    {
        array_unshift($links, '<a href="' . admin_url('options-general.php?page=mmf') . '">' . __('Settings') . '</a>');
        return $links;
    }

    static function admin_enqueue_scripts($hook)
    {
        if (!self::is_plugin_page()) return;
        wp_enqueue_style('mmf-admin', MMF_PLUGIN_URL . 'css/mmf-admin.css', array(), self::$version);
    }

    static function is_plugin_page()
    {
        $screen = get_current_screen();
        return $screen && $screen->id == 'settings_page_mmf';
    }

    static function display_maintenance_page()
    {
        $options = self::get_options();
        $request_uri = isset($_SERVER['REQUEST_URI']) ? trailingslashit(strtolower(parse_url(esc_url(wp_unslash($_SERVER['REQUEST_URI'])), PHP_URL_PATH))) : '';

        if (defined('DOING_CRON') && DOING_CRON) return false;
        if (defined('DOING_AJAX') && DOING_AJAX) return false;
        if (defined('WP_CLI') && WP_CLI) return false;

        $always_allowed = array('/wp-admin/', '/wp-login.php', '/feed/', '/admin/', '/wp-admin/admin-ajax.php', '/robots.txt');
        foreach ($always_allowed as $allowed) {
            if (strpos($request_uri, $allowed) === 0) return;
        }

        if (!self::is_maintenance_enabled(false) && !isset($_GET['mmf_preview'])) return;

        if (!empty($options['end_date']) && $options['end_date'] != '0000-00-00 00:00') {
            if (strtotime($options['end_date']) < time()) {
                $options['status'] = '0';
                update_option(MMF_OPTIONS_KEY, $options);
                return;
            }
        }

        $is_preview = isset($_GET['mmf_preview']) && is_user_logged_in();

        if (self::is_maintenance_enabled(false) || $is_preview) {
            $protocol = 'HTTP/1.0';
            if (isset($_SERVER['SERVER_PROTOCOL'])) {
                $protocol = sanitize_text_field(wp_unslash($_SERVER['SERVER_PROTOCOL']));
                if (!in_array($protocol, array('HTTP/1.1', 'HTTP/2', 'HTTP/2.0'))) $protocol = 'HTTP/1.0';
            }
            header($protocol . ' 503 Service Unavailable', true, 503);
            if (!empty($options['end_date']) && $options['end_date'] != '0000-00-00 00:00') {
                header('Retry-After: ' . gmdate('D, d M Y H:i:s T', strtotime($options['end_date'])));
            } else {
                header('Retry-After: ' . DAY_IN_SECONDS);
            }
            $theme = $options['theme'];
            // Whitelist check for theme parameter
            $valid_themes = array_keys(self::get_themes());
            if (!empty($_GET['theme']) && in_array($_GET['theme'], $valid_themes)) {
                $theme = $_GET['theme'];
            }
            if (!in_array($theme, $valid_themes)) {
                $theme = 'dark';
            }
            echo self::get_template($theme);
            die();
        }
    }

    static function is_maintenance_enabled($settings_only = false)
    {
        $options = self::get_options();
        if ($settings_only) return !empty($options['status']);
        if (empty($options['status'])) return false;

        $current_user = wp_get_current_user();
        if (!empty($options['whitelisted_roles'])) {
            foreach ((array)$options['whitelisted_roles'] as $role) {
                if (current_user_can($role)) return false;
            }
        }
        if (!empty($options['whitelisted_users']) && in_array($current_user->ID, (array)$options['whitelisted_users'])) {
            return false;
        }
        return true;
    }

    static function disable_feed()
    {
        if (self::is_maintenance_enabled(false)) {
            header('Content-Type: text/xml; charset: UTF-8');
            echo '<?xml version="1.0" encoding="UTF-8"?><status>Service unavailable.</status>';
            exit;
        }
    }

    static function generate_social_icons($options)
    {
        $out = '';
        $socials = array(
            'social_facebook' => '&#xf082;',
            'social_twitter' => '&#xf099;',
            'social_linkedin' => '&#xf0e1;',
            'social_youtube' => '&#xf167;',
            'social_vimeo' => '&#xf27d;',
            'social_pinterest' => '&#xf0d2;',
            'social_dribbble' => '&#xf17d;',
            'social_behance' => '&#xf1b4;',
            'social_instagram' => '&#xf16d;',
            'social_tumblr' => '&#xf173;',
            'social_vk' => '&#x270C;',
            'social_skype' => '&#x1F4DE;',
            'social_whatsapp' => '&#x260E;&#xFE0F;',
            'social_telegram' => '&#x2708;&#xFE0F;',
        );

        foreach ($socials as $key => $icon_class) {
            if (!empty($options[$key])) {
                $url_raw = $options[$key];
                $url_escaped = esc_url($url_raw);
                $name = ucfirst(str_replace('social_', '', $key));
                if ($key === 'social_skype') {
                    $out .= '<a title="' . esc_attr($name) . '" href="skype:' . rawurlencode($url_raw) . '?chat"><span class="mmf-social-icon">' . $icon_class . '</span></a>';
                } elseif ($key === 'social_whatsapp') {
                    $out .= '<a title="' . esc_attr($name) . '" href="https://api.whatsapp.com/send?phone=' . rawurlencode(str_replace('+', '', $url_raw)) . '"><span class="mmf-social-icon">' . $icon_class . '</span></a>';
                } else {
                    $out .= '<a title="' . esc_attr($name) . '" href="' . $url_escaped . '" target="_blank" rel="noopener noreferrer"><span class="mmf-social-icon">' . $icon_class . '</span></a>';
                }
            }
        }
        if (!empty($options['social_email'])) {
            $out .= '<a title="Email" href="mailto:' . esc_attr(sanitize_email($options['social_email'])) . '"><span class="mmf-social-icon">&#x2709;</span></a>';
        }
        if (!empty($options['social_phone'])) {
            $out .= '<a title="Phone" href="tel:' . rawurlencode($options['social_phone']) . '"><span class="mmf-social-icon">&#x260E;</span></a>';
        }
        return $out;
    }

    static function parse_vars($string)
    {
        $vars = array(
            '[site-title]' => get_bloginfo('name'),
            '[site-tagline]' => get_bloginfo('description'),
            '[site-description]' => get_bloginfo('description'),
            '[site-url]' => trailingslashit(get_home_url()),
            '[wp-url]' => trailingslashit(get_site_url()),
            '[site-login-url]' => get_site_url() . '/wp-login.php'
        );
        foreach ($vars as $var => $value) $string = str_ireplace($var, $value, $string);
        return $string;
    }

    static function get_template($theme_id)
    {
        $options = self::get_options();

        $vars = array(
            'version' => self::$version,
            'site-url' => trailingslashit(get_home_url()),
            'wp-url' => trailingslashit(get_site_url()),
            'theme-url' => MMF_PLUGIN_URL . 'themes/' . $theme_id,
            'theme-url-common' => MMF_PLUGIN_URL . 'themes',
            'title' => esc_html(self::parse_vars($options['title'])),
            'generator' => __('Complete Maintenance Mode', 'complete-maintenance-mode'),
            'heading1' => esc_html(self::parse_vars($options['heading1'])),
            'content' => nl2br(self::parse_vars($options['content'])),
            'description' => esc_attr(self::parse_vars($options['description'])),
            'social-icons' => self::generate_social_icons($options),
        );

        $head = '';
        $head .= '<link rel="stylesheet" href="' . MMF_PLUGIN_URL . 'themes/' . $theme_id . '/style.css?v=' . self::$version . '" type="text/css">' . "\n";

        if (!empty($options['ga_tracking_id']) && !empty($options['ga_tracking_enabled'])) {
            $ga_id = sanitize_text_field($options['ga_tracking_id']);
            if (preg_match('/^(G|UA|AW|DC\-|F\-)\-[A-Za-z0-9]+$/', $ga_id)) {
                $head .= "<!-- GA4 -->\n";
                $head .= "<script async src='https://www.googletagmanager.com/gtag/js?id=" . esc_js($ga_id) . "'></script>\n";
                $head .= "<script>window.dataLayer = window.dataLayer || []; function gtag(){dataLayer.push(arguments);} gtag('js', new Date()); gtag('config', '" . esc_js($ga_id) . "');</script>\n";
            }
        }

        if (!empty($options['search_engines_noindex'])) {
            $head .= "<meta name=\"robots\" content=\"noindex, nofollow\">\n";
        }

        if (!empty($options['custom_css'])) {
            $head .= "<style>" . self::sanitize_css($options['custom_css']) . "</style>\n";
        }

        $vars['head'] = $head;

        $footer = '';
        if (!empty($options['linkback'])) {
            $footer .= '<p id="linkback">Complete Maintenance Mode by <a href="https://profiles.wordpress.org/kimopensourcer" target="_blank" rel="noopener noreferrer">Kim OpenSourcer</a></p>';
        }
        if (!empty($options['login_button'])) {
            if (is_user_logged_in()) {
                $footer .= '<div id="login-button"><a href="' . get_site_url() . '/wp-admin/" title="' . esc_attr__('WordPress Admin', 'complete-maintenance-mode') . '">⚡;</a></div>';
            } else {
                $footer .= '<div id="login-button"><a href="' . get_site_url() . '/wp-login.php" title="' . esc_attr__('Login', 'complete-maintenance-mode') . '">⚡;</a></div>';
            }
        }
        $vars['footer'] = $footer;

        $template_file = MMF_PLUGIN_DIR . 'themes/' . $theme_id . '/index.php';
        if (!file_exists($template_file)) $template_file = MMF_PLUGIN_DIR . 'themes/dark/index.php';

        ob_start();
        include $template_file;
        $template = ob_get_clean();

        foreach ($vars as $key => $value) {
            $template = str_replace('[' . $key . ']', $value, $template);
        }
        return $template;
    }

    static function admin_bar_style()
    {
        if (!is_admin_bar_showing() || !current_user_can('administrator')) return;
        echo '<style>
#wpadminbar i.mmf-status-dot-enabled { color: #87c826; }
#wpadminbar i.mmf-status-dot-disabled { color: #ea1919; }
#wpadminbar #mmf-status-wrapper { display: inline; border: 1px solid rgba(240,245,250,.7); padding: 0; margin: 0 0 0 5px; background: rgb(35, 40, 45); }
#wpadminbar .mmf-status-btn { padding: 0 7px; color: #fff; }
#wpadminbar #mmf-status-wrapper.off #mmf-status-off { background: #ea1919;}
#wpadminbar #mmf-status-wrapper.on #mmf-status-on { background: #66b317;}
</style>';
    }

    static function admin_bar()
    {
        global $wp_admin_bar;
        if (!current_user_can('administrator')) return;

        $redirect_url = isset($_SERVER['REQUEST_URI']) ? esc_url(wp_unslash($_SERVER['REQUEST_URI'])) : '';

        if (self::is_maintenance_enabled(true)) {
            $main_label = '🔧 ' . __('Maintenance', 'complete-maintenance-mode') . ' <i class="mmf-status-dot mmf-status-dot-enabled">●</i>';
            $action_url = wp_nonce_url(add_query_arg(array('action' => 'mmf_change_status', 'new_status' => 'disabled', 'redirect' => urlencode($redirect_url)), admin_url('admin.php')), 'mmf_change_status');
            $action = __('Maintenance Mode', 'complete-maintenance-mode') . ' <a href="' . $action_url . '" id="mmf-status-wrapper" class="on"><span class="mmf-status-btn" id="mmf-status-off">OFF</span><span class="mmf-status-btn" id="mmf-status-on">ON</span></a>';
        } else {
            $main_label = '🔧 ' . __('Maintenance', 'complete-maintenance-mode') . ' <i class="mmf-status-dot mmf-status-dot-disabled">●</i>';
            $action_url = wp_nonce_url(add_query_arg(array('action' => 'mmf_change_status', 'new_status' => 'enabled', 'redirect' => urlencode($redirect_url)), admin_url('admin.php')), 'mmf_change_status');
            $action = __('Maintenance Mode', 'complete-maintenance-mode') . ' <a href="' . $action_url . '" id="mmf-status-wrapper" class="off"><span class="mmf-status-btn" id="mmf-status-off">OFF</span><span class="mmf-status-btn" id="mmf-status-on">ON</span></a>';
        }

        $wp_admin_bar->add_menu(array('parent' => '', 'id' => 'complete-maintenance-mode', 'title' => $main_label, 'href' => admin_url('options-general.php?page=mmf')));
        $wp_admin_bar->add_node(array('id' => 'mmf-status', 'title' => $action, 'parent' => 'complete-maintenance-mode'));
        $wp_admin_bar->add_node(array('id' => 'mmf-preview', 'title' => __('Preview', 'complete-maintenance-mode'), 'meta' => array('target' => '_blank'), 'href' => get_home_url() . '/?mmf_preview=1', 'parent' => 'complete-maintenance-mode'));
        $wp_admin_bar->add_node(array('id' => 'mmf-settings', 'title' => __('Settings', 'complete-maintenance-mode'), 'href' => admin_url('options-general.php?page=mmf'), 'parent' => 'complete-maintenance-mode'));
    }

    static function change_status()
    {
        check_admin_referer('mmf_change_status');
        if (!current_user_can('administrator') || empty($_GET['new_status'])) { wp_safe_redirect(admin_url()); exit; }
        $options = self::get_options();
        $options['status'] = sanitize_text_field(wp_unslash($_GET['new_status'])) == 'enabled' ? '1' : '0';
        update_option(MMF_OPTIONS_KEY, $options);
        wp_safe_redirect(isset($_GET['redirect']) ? urldecode($_GET['redirect']) : admin_url());
        exit;
    }

    static function reset_settings()
    {
        check_admin_referer('mmf_reset_settings');
        if (!current_user_can('administrator')) { wp_safe_redirect(admin_url()); exit; }
        update_option(MMF_OPTIONS_KEY, self::default_options());
        wp_safe_redirect(admin_url('options-general.php?page=mmf'));
        exit;
    }

    static function dismiss_notice()
    {
        if (!current_user_can('administrator')) { wp_safe_redirect(admin_url()); exit; }
        check_admin_referer('mmf_dismiss_notice_' . sanitize_key($_GET['notice']));
        wp_safe_redirect(admin_url());
        exit;
    }

    static function login_message($message)
    {
        if (self::is_maintenance_enabled(true)) $message .= '<div class="message"><b>Maintenance Mode</b> is enabled.</div>';
        return $message;
    }

    static function whitelisted_notice()
    {
        if (is_user_logged_in() && self::is_maintenance_enabled(true) && !self::is_maintenance_enabled(false)) {
            $dismiss_url = wp_nonce_url(add_query_arg(array('action' => 'mmf_dismiss_notice', 'notice' => 'whitelisted'), admin_url('admin.php')));
            echo '<div style="position:fixed;top:50px;right:10px;z-index:99999;background:#333;color:#fff;padding:20px;border-radius:8px;max-width:400px;font-size:14px;line-height:1.6;box-shadow:0 4px 12px rgba(0,0,0,0.3);"><a href="' . esc_url($dismiss_url) . '" style="color:#fff;float:right;font-size:24px;line-height:1;text-decoration:none;" title="Dismiss">×</a><strong>Maintenance Mode is enabled.</strong><br>You are whitelisted so you see the normal site.<br><br><a href="' . esc_url(get_home_url()) . '/?mmf_preview=1" style="color:#fff;text-decoration:underline;">Preview Page</a><br><a href="' . esc_url(admin_url('options-general.php?page=mmf')) . '" style="color:#fff;text-decoration:underline;">Configure</a></div>';
        }
    }

    static function admin_notices()
    {
        if (!self::is_plugin_page()) return;
        $options = self::get_options();
        if (!empty($options['end_date']) && $options['end_date'] != '0000-00-00 00:00' && strtotime($options['end_date']) < time()) {
            echo '<div class="notice notice-warning"><p><b>Warning:</b> End date is in the past. Maintenance mode will not be shown. Please update the end date.</p></div>';
        }
    }

    static function checked($value, $current)
    {
        if (!is_array($current)) $current = (array)$current;
        return in_array($value, $current) ? ' checked="checked"' : '';
    }

    static function main_page()
    {
        $options = self::get_options();
        $themes = self::get_themes();
        $roles = wp_roles()->get_names();
        $users = get_users(array('fields' => array('ID', 'user_login', 'display_name')));
        ?>
        <div class="wrap mmf-page">
            <h1><?php _e('Complete Maintenance Mode', 'complete-maintenance-mode'); ?> <span class="mmf-version"><?php echo esc_html(self::$version); ?></span></h1>

            <?php if (isset($_GET['settings-updated'])): ?>
                <div class="notice notice-success is-dismissible"><p><?php _e('Settings saved.', 'complete-maintenance-mode'); ?></p></div>
            <?php endif; ?>

            <form method="post" action="options.php">
                <?php settings_fields(MMF_OPTIONS_KEY); ?>
                <?php // Force redirect back to our settings page after save ?>
                <input type="hidden" name="_wp_http_referer" value="<?php echo esc_url(admin_url('options-general.php?page=mmf&settings-updated=1')); ?>">

                <!-- ===== STATUS ===== -->
                <div class="mmf-card">
                    <div class="mmf-card-head"><h2><?php _e('Status', 'complete-maintenance-mode'); ?></h2></div>
                    <table class="form-table">
                        <tr>
                            <th scope="row"><?php _e('Enable Maintenance Mode', 'complete-maintenance-mode'); ?></th>
                            <td><label><input type="checkbox" name="<?php echo esc_attr(MMF_OPTIONS_KEY); ?>[status]" value="1"<?php echo self::checked('1', $options['status']); ?>> <?php _e('Show maintenance page to visitors', 'complete-maintenance-mode'); ?></label></td>
                        </tr>
                        <tr>
                            <th scope="row"><?php _e('Auto-Disable Date', 'complete-maintenance-mode'); ?></th>
                            <td>
                                <label><input type="checkbox" id="mmf-end-date-enabled" name="<?php echo esc_attr(MMF_OPTIONS_KEY); ?>[end_date_enabled]" value="1"<?php echo self::checked('1', $options['end_date_enabled']); ?>> <?php _e('Disable maintenance mode on a specific date', 'complete-maintenance-mode'); ?></label>
                                <br><br>
                                <input type="datetime-local" id="mmf-end-date" name="<?php echo esc_attr(MMF_OPTIONS_KEY); ?>[end_date]" value="<?php echo esc_attr($options['end_date']); ?>"<?php if (empty($options['end_date_enabled'])) echo ' disabled'; ?>>
                            </td>
                        </tr>
                    </table>
                </div>

                <!-- ===== PAGE CONTENT ===== -->
                <div class="mmf-card">
                    <div class="mmf-card-head"><h2><?php _e('Page Content', 'complete-maintenance-mode'); ?></h2></div>
                    <table class="form-table">
                        <tr>
                            <th scope="row"><?php _e('Page Title', 'complete-maintenance-mode'); ?></th>
                            <td><input type="text" name="<?php echo esc_attr(MMF_OPTIONS_KEY); ?>[title]" value="<?php echo esc_attr($options['title']); ?>" class="regular-text"><p class="description"><?php _e('Variables: [site-title], [site-tagline], [site-url]', 'complete-maintenance-mode'); ?></p></td>
                        </tr>
                        <tr>
                            <th scope="row"><?php _e('Meta Description', 'complete-maintenance-mode'); ?></th>
                            <td><input type="text" name="<?php echo esc_attr(MMF_OPTIONS_KEY); ?>[description]" value="<?php echo esc_attr($options['description']); ?>" class="regular-text"></td>
                        </tr>
                        <tr>
                            <th scope="row"><?php _e('Heading', 'complete-maintenance-mode'); ?></th>
                            <td><input type="text" name="<?php echo esc_attr(MMF_OPTIONS_KEY); ?>[heading1]" value="<?php echo esc_attr($options['heading1']); ?>" class="regular-text"></td>
                        </tr>
                        <tr>
                            <th scope="row"><?php _e('Body Text', 'complete-maintenance-mode'); ?></th>
                            <td><?php wp_editor($options['content'], 'mmf_content', array('textarea_name' => MMF_OPTIONS_KEY . '[content]', 'textarea_rows' => 6, 'media_buttons' => false)); ?></td>
                        </tr>
                    </table>
                </div>

                <!-- ===== THEME ===== -->
                <div class="mmf-card">
                    <div class="mmf-card-head"><h2><?php _e('Theme', 'complete-maintenance-mode'); ?></h2></div>
                    <p><?php _e('Choose the look of the maintenance page.', 'complete-maintenance-mode'); ?></p>
                    <div class="mmf-themes">
                        <?php foreach ($themes as $theme_id => $theme_name):
                            $preview_url = get_home_url() . '/?mmf_preview&theme=' . $theme_id;
                            $thumb_url = MMF_PLUGIN_URL . 'themes/' . $theme_id . '/' . $theme_id . '.png';
                            $has_thumb = file_exists(MMF_PLUGIN_DIR . 'themes/' . $theme_id . '/' . $theme_id . '.png');
                        ?>
                            <div class="mmf-theme<?php if ($options['theme'] == $theme_id) echo ' mmf-active'; ?>">
                                <?php if ($has_thumb): ?>
                                    <a href="<?php echo esc_url($thumb_url); ?>" target="_blank" rel="noopener noreferrer" class="mmf-theme-img-link">
                                        <img src="<?php echo esc_url($thumb_url); ?>" alt="<?php echo esc_attr($theme_name); ?>" class="mmf-theme-img">
                                    </a>
                                <?php endif; ?>
                                <span class="mmf-theme-name"><?php echo esc_html($theme_name); ?></span>
                                <label class="mmf-theme-check">
                                    <input type="radio" name="<?php echo esc_attr(MMF_OPTIONS_KEY); ?>[theme]" value="<?php echo esc_attr($theme_id); ?>"<?php checked($options['theme'], $theme_id); ?>>
                                    <?php _e('Select', 'complete-maintenance-mode'); ?>
                                </label>
                                <a href="<?php echo esc_url($preview_url); ?>" target="_blank" rel="noopener noreferrer" class="button button-secondary"><?php _e('Preview', 'complete-maintenance-mode'); ?></a>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- ===== SOCIAL ===== -->
                <div class="mmf-card">
                    <div class="mmf-card-head"><h2><?php _e('Social Media', 'complete-maintenance-mode'); ?></h2></div>
                    <p><?php _e('Add social links to the maintenance page. Leave empty to hide.', 'complete-maintenance-mode'); ?></p>
                    <table class="form-table">
                        <?php
                        $socials = array(
                            'social_facebook' => array('icon' => '&#xf082;', 'color' => '#1877f3', 'label' => 'Facebook'),
                            'social_twitter' => array('icon' => '&#xf099;', 'color' => '#1da1f2', 'label' => 'Twitter / X'),
                            'social_instagram' => array('icon' => '&#xf16d;', 'color' => '#e4405f', 'label' => 'Instagram'),
                            'social_youtube' => array('icon' => '&#xf167;', 'color' => '#ff0000', 'label' => 'YouTube'),
                            'social_telegram' => array('icon' => '&#x2708;&#xFE0F;', 'color' => '#26a5b4', 'label' => 'Telegram'),
                            'social_linkedin' => array('icon' => '&#xf0e1;', 'color' => '#0a66c2', 'label' => 'LinkedIn'),
                            'social_email' => array('icon' => '&#x2709;', 'color' => '#555', 'label' => 'Email'),
                        );
                        foreach ($socials as $key => $data):
                            $val = isset($options[$key]) ? $options[$key] : '';
                        ?>
                            <tr>
                                <th scope="row"><i class="<?php echo esc_attr($data['icon']); ?>" style="color:<?php echo esc_attr($data['color']); ?>;"></i> <?php echo esc_html($data['label']); ?></th>
                                <td><input type="text" name="<?php echo esc_attr(MMF_OPTIONS_KEY); ?>[<?php echo esc_attr($key); ?>]" value="<?php echo esc_attr($val); ?>" class="regular-text" placeholder="https://"></td>
                            </tr>
                        <?php endforeach; ?>
                    </table>
                </div>

                <!-- ===== ACCESS CONTROL ===== -->
                <div class="mmf-card">
                    <div class="mmf-card-head"><h2><?php _e('Access Control', 'complete-maintenance-mode'); ?></h2></div>
                    <p><?php _e('These users and roles will see the normal site, not the maintenance page.', 'complete-maintenance-mode'); ?></p>
                    <table class="form-table">
                        <tr>
                            <th scope="row"><?php _e('Whitelisted Roles', 'complete-maintenance-mode'); ?></th>
                            <td><div class="mmf-checks">
                                <?php foreach ($roles as $role_id => $role_name): ?>
                                    <label class="mmf-check"><input type="checkbox" name="<?php echo esc_attr(MMF_OPTIONS_KEY); ?>[whitelisted_roles][]" value="<?php echo esc_attr($role_id); ?>"<?php checked(in_array($role_id, (array)$options['whitelisted_roles'])); ?>> <?php echo translate_user_role($role_name); ?></label>
                                <?php endforeach; ?>
                            </div></td>
                        </tr>
                        <tr>
                            <th scope="row"><?php _e('Whitelisted Users', 'complete-maintenance-mode'); ?></th>
                            <td><div class="mmf-checks">
                                <?php foreach ($users as $user): ?>
                                    <label class="mmf-check"><input type="checkbox" name="<?php echo esc_attr(MMF_OPTIONS_KEY); ?>[whitelisted_users][]" value="<?php echo esc_attr($user->ID); ?>"<?php checked(in_array($user->ID, (array)$options['whitelisted_users'])); ?>> <?php echo esc_html($user->display_name); ?></label>
                                <?php endforeach; ?>
                            </div></td>
                        </tr>
                    </table>
                </div>

                <!-- ===== SEO & ANALYTICS ===== -->
                <div class="mmf-card">
                    <div class="mmf-card-head"><h2><?php _e('SEO &amp; Analytics', 'complete-maintenance-mode'); ?></h2></div>
                    <table class="form-table">
                        <tr>
                            <th scope="row"><?php _e('Search Engine Noindex', 'complete-maintenance-mode'); ?></th>
                            <td><label><input type="checkbox" name="<?php echo esc_attr(MMF_OPTIONS_KEY); ?>[search_engines_noindex]" value="1"<?php echo self::checked('1', $options['search_engines_noindex']); ?>> <?php _e('Add noindex, nofollow meta tag (prevents indexing)', 'complete-maintenance-mode'); ?></label></td>
                        </tr>
                        <tr>
                            <th scope="row"><?php _e('Google Analytics 4', 'complete-maintenance-mode'); ?></th>
                            <td>
                                <label><input type="checkbox" id="mmf-ga-enabled" name="<?php echo esc_attr(MMF_OPTIONS_KEY); ?>[ga_tracking_enabled]" value="1"<?php echo self::checked('1', $options['ga_tracking_enabled']); ?>> <?php _e('Enable GA4 tracking on maintenance page', 'complete-maintenance-mode'); ?></label>
                                <br><br>
                                <input type="text" id="mmf-ga-id" name="<?php echo esc_attr(MMF_OPTIONS_KEY); ?>[ga_tracking_id]" value="<?php echo esc_attr($options['ga_tracking_id']); ?>" placeholder="G-XXXXXXXXXX" class="code" style="width:300px;"<?php if (empty($options['ga_tracking_enabled'])) echo ' disabled'; ?>>
                            </td>
                        </tr>
                    </table>
                </div>

                <!-- ===== ADVANCED ===== -->
                <div class="mmf-card">
                    <div class="mmf-card-head"><h2><?php _e('Advanced', 'complete-maintenance-mode'); ?></h2></div>
                    <table class="form-table">
                        <tr>
                            <th scope="row"><?php _e('Login Button', 'complete-maintenance-mode'); ?></th>
                            <td><label><input type="checkbox" name="<?php echo esc_attr(MMF_OPTIONS_KEY); ?>[login_button]" value="1"<?php echo self::checked('1', $options['login_button']); ?>> <?php _e('Show WordPress login button (top-right corner)', 'complete-maintenance-mode'); ?></label></td>
                        </tr>
                        <tr>
                            <th scope="row"><?php _e('Linkback Credit', 'complete-maintenance-mode'); ?></th>
                            <td><label><input type="checkbox" name="<?php echo esc_attr(MMF_OPTIONS_KEY); ?>[linkback]" value="1"<?php echo self::checked('1', $options['linkback']); ?>> <?php _e('Show "Complete Maintenance Mode" credit at bottom', 'complete-maintenance-mode'); ?></label></td>
                        </tr>
                        <tr>
                            <th scope="row"><?php _e('Custom CSS', 'complete-maintenance-mode'); ?></th>
                            <td><textarea name="<?php echo esc_attr(MMF_OPTIONS_KEY); ?>[custom_css]" rows="5" class="large-text code" placeholder="body { /* custom styles */ }"><?php echo esc_textarea($options['custom_css']); ?></textarea><p class="description"><?php _e('Add custom CSS to further style the maintenance page.', 'complete-maintenance-mode'); ?></p></td>
                        </tr>
                        <tr>
                            <th scope="row"><?php _e('Reset', 'complete-maintenance-mode'); ?></th>
                            <td>
                                <?php $reset_url = wp_nonce_url(add_query_arg(array('action' => 'mmf_reset_settings'), admin_url('admin.php'))); ?>
                                <a href="<?php echo esc_url($reset_url); ?>" class="button button-secondary" onclick="return confirm('<?php _e('Reset all settings to defaults?', 'complete-maintenance-mode'); ?>')"><?php _e('Reset to Defaults', 'complete-maintenance-mode'); ?></a>
                            </td>
                        </tr>
                    </table>
                </div>

                <!-- ===== SAVE ===== -->
                <div class="mmf-save">
                    <?php submit_button(__('Save Settings', 'complete-maintenance-mode'), 'primary', 'submit', false); ?>
                </div>

            </form>
            <hr>
            <p class="mmf-credits">Complete Maintenance Mode by <a href="https://profiles.wordpress.org/kimopensourcer" target="_blank" rel="noopener noreferrer">Kim OpenSourcer</a></p>
        </div>

        <style>
        .mmf-page h1 .mmf-version { background:#f0f0f0; padding:2px 8px; border-radius:4px; font-size:12px; font-weight:400; vertical-align:middle; }
        .mmf-card { background:#fff; border:1px solid #c3c4c7; border-radius:8px; margin:20px 0; box-shadow:0 1px 1px rgba(0,0,0,.04); }
        .mmf-card-head { padding:18px 24px; border-bottom:1px solid #f0f0f1; background:#fbfbfc; border-radius:8px 8px 0 0; }
        .mmf-card-head h2 { margin:0; font-size:18px; font-weight:600; }
        .mmf-card table.form-table { border:0; }
        .mmf-card table.form-table th { padding:16px 24px 12px 24px !important; border:0 !important; min-width:170px; vertical-align:top !important; }
        .mmf-card table.form-table td { padding:16px 24px 16px 24px !important; border:0 !important; }
        .mmf-card table.form-table td .description { margin:6px 0 0; color:#666; font-size:13px; }
        .mmf-card table.form-table td .wp-editor-wrap { margin-top:4px; }
        .mmf-card > p,
        .mmf-card > .mmf-themes { padding:20px 24px; }
        .mmf-card > .mmf-themes { margin:0; }
        .mmf-themes { display:grid; grid-template-columns:repeat(auto-fill,minmax(200px,1fr)); gap:12px; }
        .mmf-theme { border:2px solid #c3c4c7; border-radius:8px; padding:14px; display:flex; flex-direction:column; gap:8px; transition:border-color .15s; }
        .mmf-theme.mmf-active { border-color:#2271b1; background:#f0f6fc; }
        .mmf-theme-name { font-weight:600; font-size:14px; }
        .mmf-theme-check { cursor:pointer; font-size:13px; }
        .mmf-checks { display:flex; flex-wrap:wrap; gap:10px 20px; }
        .mmf-check { display:inline-flex; align-items:center; gap:6px; min-width:130px; }
        .mmf-save { padding:24px 0 10px; text-align:center; }
        .mmf-credits { color:#666; font-size:12px; text-align:center; margin-top:10px; }
        /* Theme preview images */
        .mmf-theme-img-link { display:block; margin-bottom:8px; border-radius:6px; overflow:hidden; border:1px solid #c3c4c7; transition:border-color .15s,box-shadow .15s; }
        .mmf-theme:hover .mmf-theme-img-link,
        .mmf-theme.mmf-active .mmf-theme-img-link { border-color:#2271b1; box-shadow:0 0 0 1px #2271b1; }
        .mmf-theme-img { width:100%; height:auto; display:block; cursor:pointer; transition:transform .2s; }
        .mmf-theme:hover .mmf-theme-img { transform:scale(1.03); }
        </style>

        <script>
        jQuery(document).ready(function($){
            // End date toggle
            $('#mmf-end-date-enabled').on('change', function(){
                $('#mmf-end-date').prop('disabled', !this.checked);
            });
            // GA toggle
            $('#mmf-ga-enabled').on('change', function(){
                $('#mmf-ga-id').prop('disabled', !this.checked);
            });
        });
        </script>
        <?php
    }

    static function get_themes()
    {
        return array(
            'gradient-mesh' => __('Gradient Mesh', 'complete-maintenance-mode'),
            'cosmos' => __('Cosmos', 'complete-maintenance-mode'),
            'zen' => __('Elegant Zen', 'complete-maintenance-mode'),
            'emerald' => __('Emerald Luxe', 'complete-maintenance-mode'),
            'neon-glow' => __('Neon Glow', 'complete-maintenance-mode'),
            'light' => __('Light Clean', 'complete-maintenance-mode'),
            'construction' => __('Construction', 'complete-maintenance-mode'),
            'rocket' => __('Rocket Launch', 'complete-maintenance-mode'),
            'dark' => __('Dark Minimal', 'complete-maintenance-mode'),
            'clock' => __('Clock Timer', 'complete-maintenance-mode'),
        );
    }

    static function sanitize_css($css)
    {
        $css = strip_tags($css);
        $css = preg_replace('/javascript[:\s]*/i', '', $css);
        $css = preg_replace('/vbscript[:\s]*/i', '', $css);
        $css = preg_replace('/expression\s*\(/i', '', $css);
        $css = preg_replace('/\beval\s*\(/i', '', $css);
        $css = preg_replace('/url\s*\(/i', '', $css);
        $css = preg_replace('/@import\s/i', '', $css);
        $css = preg_replace('/behavior\s*:/i', '', $css);
        $css = preg_replace('/-moz-binding\s*:/i', '', $css);
        $css = preg_replace('/\bimport\s*\(/i', '', $css);
        $css = preg_replace('/\bwindow\b/i', '', $css);
        $css = preg_replace('/\bdocument\b/i', '', $css);
        $css = preg_replace('/\balert\s*\(/i', '', $css);
        $css = preg_replace('/\bconfirm\s*\(/i', '', $css);
        $css = preg_replace('/\bprompt\s*\(/i', '', $css);
        $css = preg_replace('/\bon\w+\s*=/i', '', $css);
        $css = preg_replace('/<script[^>]*>/i', '', $css);
        $css = preg_replace('/<\/script>/i', '', $css);
        return $css;
    }
}

add_action('plugins_loaded', array('Complete_Maintenance_Mode', 'init'));
