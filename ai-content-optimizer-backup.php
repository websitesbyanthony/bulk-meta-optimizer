<?php
/**
 * Plugin Name: Bulk Meta Optimizer
 * Description: A comprehensive AI-powered content optimization tool for WordPress that generates and optimizes meta titles, descriptions, content, and permalinks for posts, pages, and WooCommerce products.
 * Version: 1.0.3
 * Author: Dapper Dev
 * Author URI: http://bulkmetaoptimizer.com/
 */

// Exit if accessed directly.
if (!defined('ABSPATH')) {
    exit;
}

// SLM License Verification Constants
if (!defined('BMO_SLM_SERVER')) {
    define('BMO_SLM_SERVER', 'https://bulkmetaoptimizer.com');
}
if (!defined('BMO_SLM_ITEM')) {
    define('BMO_SLM_ITEM', 'Bulk Meta Optimizer Plugin');
}
if (!defined('BMO_SLM_SECRET_VERIFY')) {
    define('BMO_SLM_SECRET_VERIFY', '685361e739ae33.52122910');
}

require_once __DIR__ . '/restore-defaults.php';

// Initialize the plugin
function ai_content_optimizer_init() {
    AI_Content_Optimizer::get_instance();
}
add_action('plugins_loaded', 'ai_content_optimizer_init');

class AI_Content_Optimizer {

    /**
     * Plugin version
     */
    const VERSION = '1.0.3';

    /**
     * Plugin instance
     */
    private static $instance = null;

    /**
     * Get plugin instance
     */
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Constructor
     */
    private function __construct() {
        // Register activation hook
        register_activation_hook(__FILE__, array($this, 'activate'));

        // Initialize plugin
        $this->init();

        // Always ensure default prompts are set
        $this->set_default_prompts();

        // Add support for custom post types
        add_action('init', array($this, 'add_custom_post_type_support'), 20);
    }

    /**
     * Plugin activation
     */
    public function activate() {
        // Set default options if they don't exist
        $this->set_default_options();
    }

    /**
     * Set default options
     */
    private function set_default_options() {
        // General settings
        if (!get_option('aico_openai_api_key')) {
            update_option('aico_openai_api_key', '');
        }

        // Default content optimization settings
        $default_content_settings = array(
            'content_tone' => 'professional',
            'target_audience' => 'general',
            'content_focus' => 'benefit-focused',
            'seo_aggressiveness' => 'moderate',
            'keyword_density' => 'standard',
            'geographic_targeting' => 'global',
            'brand_voice' => 'trustworthy',
            'title_separator' => 'dash',
            'excluded_words' => '',
        );

        // Default optimization toggles
        $default_toggles = array(
            'optimize_title' => true,
            'optimize_meta' => true,
            'optimize_content' => false,
            'optimize_slug' => false,
            'preserve_html' => true,
            'optimize_category_meta' => true,
            'optimize_tag_meta' => true,
        );

        // Set defaults for each post type
        foreach (array('post', 'page', 'product') as $post_type) {
            $settings_key = 'aico_' . $post_type . '_settings';
            $existing_settings = get_option($settings_key, array());
            if (empty($existing_settings)) {
                update_option($settings_key, array_merge($default_content_settings, $default_toggles));
            } else {
                $existing_settings['optimize_content'] = false;
                update_option($settings_key, $existing_settings);
            }
        }

        // Set default prompts
        $this->set_default_prompts();
    }

    /**
     * Set default prompts
     */
    private function set_default_prompts() {
        // Default prompts for post type
        $post_title_prompt = "You are an SEO expert. Write an SEO-friendly post title (60 chars max). Use a {content_tone} tone for a {target_audience} audience. No exclamation marks or quotation marks.";
        $post_meta_prompt = "You are an SEO expert. Write a meta description (160 chars max) for this post. Use a {content_tone} tone for a {target_audience} audience. Keep it concise, no exclamation marks or quotation marks.";

        // Preserve HTML prompt
        $preserve_prompt = "Generate HTML output based on the structure provided below, but only update the text content to focus on {PAGE TITLE}. Preserve all shortcodes, CSS, HTML classes, IDs, and structure exactly as they are. Update only the text content to focus clarity, using SEO keywords, service descriptions, and location-based phrases related to {existing content}. The output must be a single line of HTML with no added spaces or line breaks.";

        // Set defaults for each post type
        add_option('aico_post_title_prompt', $post_title_prompt);
        add_option('aico_post_meta_prompt', $post_meta_prompt);
        add_option('aico_post_content_prompt', $preserve_prompt);

        add_option('aico_page_title_prompt', $post_title_prompt);
        add_option('aico_page_meta_prompt', $post_meta_prompt);
        add_option('aico_page_content_prompt', $preserve_prompt);

        add_option('aico_product_title_prompt', $post_title_prompt);
        add_option('aico_product_meta_prompt', $post_meta_prompt);
        add_option('aico_product_content_prompt', $preserve_prompt);
    }

    /**
     * Initialize plugin
     */
    public function init() {
        // Load text domain for translations
        load_plugin_textdomain('ai-content-optimizer', false, dirname(plugin_basename(__FILE__)) . '/languages');

        // Register admin hooks
        if (is_admin()) {
            add_action('admin_menu', array($this, 'add_admin_menu'));
            add_action('admin_init', array($this, 'register_settings'));
            add_action('admin_notices', array($this, 'admin_notices'));
            add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'));
        }
    }

    /**
     * Add admin menu
     */
    public function add_admin_menu() {
        // Main menu page
        add_menu_page(
            __('Bulk Meta Optimizer', 'ai-content-optimizer'),
            __('Bulk Meta Optimizer', 'ai-content-optimizer'),
            'manage_options',
            'ai-content-optimizer',
            array($this, 'render_dashboard_page'),
            'dashicons-search',
            30
        );

        // Dashboard submenu (same as main page)
        add_submenu_page(
            'ai-content-optimizer',
            __('Dashboard', 'ai-content-optimizer'),
            __('Dashboard', 'ai-content-optimizer'),
            'manage_options',
            'ai-content-optimizer',
            array($this, 'render_dashboard_page')
        );

        // Content Settings submenu
        add_submenu_page(
            'ai-content-optimizer',
            __('Content Settings', 'ai-content-optimizer'),
            __('Content Settings', 'ai-content-optimizer'),
            'manage_options',
            'ai-content-optimizer-settings',
            array($this, 'render_settings_page')
        );

        // Brand Profile submenu
        add_submenu_page(
            'ai-content-optimizer',
            __('Brand Profile', 'ai-content-optimizer'),
            __('Brand Profile', 'ai-content-optimizer'),
            'manage_options',
            'aico-brand-profile',
            'aico_render_brand_profile_page'
        );

        // API Settings submenu
        add_submenu_page(
            'ai-content-optimizer',
            __('API Settings', 'ai-content-optimizer'),
            __('API Settings', 'ai-content-optimizer'),
            'manage_options',
            'ai-content-optimizer-api',
            array($this, 'render_api_page')
        );

        // Advanced Settings submenu
        add_submenu_page(
            'ai-content-optimizer',
            __('Advanced Settings', 'ai-content-optimizer'),
            __('Advanced Settings', 'ai-content-optimizer'),
            'manage_options',
            'ai-content-optimizer-advanced',
            array($this, 'render_advanced_page')
        );
    }

    /**
     * Register settings
     */
    public function register_settings() {
        // Register OpenAI API settings
        register_setting('aico_api_settings', 'aico_openai_api_key');
        register_setting('aico_api_settings', 'aico_openai_model');
        register_setting('aico_api_settings', 'aico_openai_temperature');
        register_setting('aico_api_settings', 'aico_openai_max_tokens');

        // Register license settings
        register_setting('bmo_license_settings', 'bmo_license_key');

        // Register content settings for each post type
        $post_types = get_post_types(array('public' => true), 'names');
        foreach ($post_types as $post_type) {
            if ($post_type === 'attachment') continue;
            
            register_setting('aico_' . $post_type . '_settings', 'aico_' . $post_type . '_settings');
            register_setting('aico_' . $post_type . '_prompts', 'aico_' . $post_type . '_title_prompt');
            register_setting('aico_' . $post_type . '_prompts', 'aico_' . $post_type . '_meta_prompt');
            register_setting('aico_' . $post_type . '_prompts', 'aico_' . $post_type . '_content_prompt');
        }
    }

    /**
     * Admin notices
     */
    public function admin_notices() {
        // Show bulk optimization notice
        if (isset($_GET['aico_bulk_notice'])) {
            echo '<div class="notice notice-success is-dismissible"><p>' . __('Bulk optimization completed successfully!', 'ai-content-optimizer') . '</p></div>';
        }

        // Show license status notices
        $license_status = get_option('bmo_license_status', 'invalid');
        if ($license_status !== 'success' && isset($_GET['page']) && strpos($_GET['page'], 'ai-content-optimizer') !== false) {
            $message = '';
            switch ($license_status) {
                case 'expired':
                    $message = __('Your license has expired. Please renew to continue using Bulk Meta Optimizer.', 'ai-content-optimizer');
                    break;
                case 'limit_reached':
                    $message = __('You\'ve reached the maximum number of activations for this license.', 'ai-content-optimizer');
                    break;
                default:
                    $message = __('A valid license is required to use Bulk Meta Optimizer. Please enter your license key in Advanced Settings.', 'ai-content-optimizer');
                    break;
            }
            echo '<div class="notice notice-error"><p>' . esc_html($message) . '</p></div>';
        }
    }

    /**
     * Enqueue admin scripts and styles
     */
    public function enqueue_admin_scripts($hook) {
        // Only load on our plugin pages
        if (strpos($hook, 'ai-content-optimizer') === false && strpos($hook, 'aico-brand-profile') === false) {
            return;
        }

        // Enqueue styles
        wp_enqueue_style(
            'aico-admin-css',
            plugin_dir_url(__FILE__) . 'assets/css/admin.css',
            array(),
            self::VERSION
        );

        wp_enqueue_style(
            'aico-admin-tabs-css',
            plugin_dir_url(__FILE__) . 'assets/css/admin-tabs.css',
            array(),
            self::VERSION
        );

        // Enqueue scripts
        wp_enqueue_script(
            'aico-admin-js',
            plugin_dir_url(__FILE__) . 'assets/js/admin.js',
            array('jquery'),
            self::VERSION,
            true
        );

        // Localize script for AJAX
        wp_localize_script('aico-admin-js', 'aico_ajax', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('aico-nonce'),
            'strings' => array(
                'optimizing' => __('Optimizing...', 'ai-content-optimizer'),
                'success' => __('Success!', 'ai-content-optimizer'),
                'error' => __('Error occurred', 'ai-content-optimizer'),
                'confirm_bulk' => __('Are you sure you want to optimize all selected items?', 'ai-content-optimizer'),
            )
        ));
    }

    /**
     * Add support for custom post types
     */
    public function add_custom_post_type_support() {
        try {
            // Get taxonomy from current screen
            $taxonomy = 'category';
            if (!empty($_REQUEST['taxonomy'])) {
                $taxonomy = sanitize_text_field($_REQUEST['taxonomy']);
            }

            // Store the term IDs for processing
            // Removed problematic line with undefined variable
            update_option('aico_bulk_taxonomy_type', $taxonomy);

            // Redirect back to the list page with a success notice
            return add_query_arg(
                array(
                    'taxonomy' => $taxonomy,
                    'aico_bulk_taxonomy_notice' => '1',
                ),
                admin_url('edit-tags.php')
            );
        } catch (Exception $e) {
            return $redirect_to;
        }
    }

    /**
     * Render dashboard page
     */
    public function render_dashboard_page() {
        $license_status = get_option('bmo_license_status', 'invalid');
        if ($license_status !== 'success') {
            echo '<div class="notice notice-error"><p>' . __('A valid license is required to use Bulk Meta Optimizer. Please enter your license key in Advanced Settings.', 'ai-content-optimizer') . '</p></div>';
            return;
        }
        ?>
        <div class="wrap aico-wrap">
            <h1><?php _e('Bulk Meta Optimizer Dashboard', 'ai-content-optimizer'); ?></h1>
            <p><?php _e('Welcome to Bulk Meta Optimizer! This plugin helps you optimize your content with AI.', 'ai-content-optimizer'); ?></p>
        </div>
        <?php
    }

    /**
     * Render settings page
     */
    public function render_settings_page() {
        $license_status = get_option('bmo_license_status', 'invalid');
        if ($license_status !== 'success') {
            echo '<div class="notice notice-error"><p>' . __('A valid license is required to use Bulk Meta Optimizer. Please enter your license key in Advanced Settings.', 'ai-content-optimizer') . '</p></div>';
            return;
        }
        ?>
        <div class="wrap aico-wrap">
            <h1><?php _e('Content Settings', 'ai-content-optimizer'); ?></h1>
            <p><?php _e('Configure your content optimization settings here.', 'ai-content-optimizer'); ?></p>
        </div>
        <?php
    }

    /**
     * Render API page
     */
    public function render_api_page() {
        $license_status = get_option('bmo_license_status', 'invalid');
        if ($license_status !== 'success') {
            echo '<div class="notice notice-error"><p>' . __('A valid license is required to use Bulk Meta Optimizer. Please enter your license key in Advanced Settings.', 'ai-content-optimizer') . '</p></div>';
            return;
        }
        ?>
        <div class="wrap aico-wrap">
            <h1><?php _e('API Settings', 'ai-content-optimizer'); ?></h1>
            <p><?php _e('Configure your OpenAI API settings here.', 'ai-content-optimizer'); ?></p>
        </div>
        <?php
    }

    /**
     * Render advanced page
     */
    public function render_advanced_page() {
        ?>
        <div class="wrap aico-wrap">
            <h1><?php _e('Advanced Settings', 'ai-content-optimizer'); ?></h1>
            <p><?php _e('Configure advanced settings and license key here.', 'ai-content-optimizer'); ?></p>
        </div>
        <?php
    }

}

// Handle license key save with SLM check
add_action('admin_post_bmo_save_license_key', function() {
    if (!current_user_can('manage_options')) {
        wp_die(__('You do not have permission to perform this action.', 'ai-content-optimizer'));
    }
    check_admin_referer('bmo_save_license_key', 'bmo_license_nonce');

    $key = sanitize_text_field($_POST['bmo_license_key'] ?? '');
    update_option('bmo_license_key', $key);

    $parsed = parse_url(home_url());
    $full_url = $parsed['scheme'] . '://' . $parsed['host'];
    $host_only = $parsed['host'];

    $body = [
        'slm_action'        => 'slm_activate',
        'secret_key'        => BMO_SLM_SECRET_VERIFY,
        'license_key'       => $key,
        'item_reference'    => BMO_SLM_ITEM,
        'url'               => $full_url,
        'domain_name'       => $host_only,
        'registered_domain' => $full_url,
    ];

    $response = wp_remote_post(BMO_SLM_SERVER, [
        'body' => $body,
        'timeout' => 15,
        'sslverify' => true,
    ]);

    $data = is_wp_error($response)
          ? ['result' => 'error', 'message' => $response->get_error_message()]
          : json_decode(wp_remote_retrieve_body($response), true);

    if (!empty($data['result']) && $data['result'] === 'error') {
        if (stripos($data['message'], 'maximum allowable domains') !== false) {
            wp_redirect(add_query_arg('bmo_license_status', 'limit_reached', admin_url('admin.php?page=ai-content-optimizer-advanced')));
            exit;
        }
    }

    if (!empty($data['result']) && $data['result'] === 'success') {
        $status = 'success';
    } elseif (!empty($data['message']) && stripos($data['message'], 'expired') !== false) {
        $status = 'expired';
    } else {
        $status = 'invalid';
    }

    wp_redirect(add_query_arg(
        'bmo_license_status',
        $status,
        admin_url('admin.php?page=ai-content-optimizer-advanced')
    ));
    exit;
});

// License status check function with caching
function bmo_check_license_status() {
    // Check if we have a recent cache (within 24 hours)
    $last_check = get_option('bmo_license_last_check', 0);
    $current_time = time();
    $cache_duration = 24 * 60 * 60; // 24 hours in seconds
    
    // If we checked recently, don't check again
    if (($current_time - $last_check) < $cache_duration) {
        return;
    }
    
    $key = get_option('bmo_license_key', '');
    if (empty($key)) {
        update_option('bmo_license_status', 'invalid');
        update_option('bmo_license_last_check', $current_time);
        return;
    }

    $parsed = parse_url(home_url());
    $full_url = $parsed['scheme'] . '://' . $parsed['host'];
    $host_only = $parsed['host'];

    $body = [
        'slm_action'        => 'slm_check',
        'secret_key'        => BMO_SLM_SECRET_VERIFY,
        'license_key'       => $key,
        'item_reference'    => BMO_SLM_ITEM,
        'url'               => $full_url,
        'domain_name'       => $host_only,
        'registered_domain' => $full_url,
    ];

    $response = wp_remote_post(BMO_SLM_SERVER, [
        'body' => $body,
        'timeout' => 10,
        'sslverify' => true,
    ]);

    $data = json_decode(wp_remote_retrieve_body($response), true);
    if (!empty($data['result'])) {
        update_option('bmo_license_status', $data['result']);
    }
    
    // Update the last check time
    update_option('bmo_license_last_check', $current_time);
}

// Force license check (for manual checks)
function bmo_force_license_check() {
    // Clear the cache to force a fresh check
    delete_option('bmo_license_last_check');
    bmo_check_license_status();
}

// Only run the cached check on admin_init, not the forced check
add_action('admin_init', 'bmo_check_license_status');

// Deactivation hook
function bmo_deactivate_license() {
    $key = get_option('bmo_license_key', '');
    if (empty($key)) {
        return;
    }

    $parsed = parse_url(home_url());
    $full_url = $parsed['scheme'] . '://' . $parsed['host'];
    $host_only = $parsed['host'];

    $body = [
        'slm_action'        => 'slm_deactivate',
        'secret_key'        => BMO_SLM_SECRET_VERIFY,
        'license_key'       => $key,
        'item_reference'    => BMO_SLM_ITEM,
        'url'               => $full_url,
        'domain_name'       => $host_only,
        'registered_domain' => $full_url,
    ];

    wp_remote_post(BMO_SLM_SERVER, [
        'body' => $body,
        'timeout' => 10,
        'sslverify' => true,
    ]);
}
register_deactivation_hook(__FILE__, 'bmo_deactivate_license');

// Schedule daily license check
if (!wp_next_scheduled('bmo_daily_license_check')) {
    wp_schedule_event(time(), 'daily', 'bmo_daily_license_check');
}
add_action('bmo_daily_license_check', 'bmo_check_license_status');

// Gate plugin functionality on license status
add_action('plugins_loaded', 'bmo_maybe_disable_plugin', 5);
function bmo_maybe_disable_plugin() {
    $status = get_option('bmo_license_status', 'invalid');
    
    if ($status === 'success') {
        return; // Plugin should be enabled
    } elseif ($status === 'expired') {
        $msg = 'Your license has expired. Please renew to continue using Bulk Meta Optimizer.';
    } elseif ($status === 'limit_reached') {
        $msg = 'You\'ve reached the maximum number of activations for this license.';
    } else {
        $msg = 'A valid license is required to use Bulk Meta Optimizer. Please enter your license key in Settings.';
    }
    
    // show an admin notice
    add_action('admin_notices', function() use($msg) {
        echo "<div class='notice notice-error'><p>{$msg}</p></div>";
    });
}

// AJAX handler for manual license check
add_action('wp_ajax_bmo_manual_license_check', function() {
    if (!current_user_can('manage_options')) {
        wp_send_json_error('Insufficient permissions');
    }
    
    check_ajax_referer('aico-nonce', 'nonce');
    
    // Force a fresh license check
    bmo_force_license_check();
    
    $status = get_option('bmo_license_status', 'invalid');
    $last_check = get_option('bmo_license_last_check', 0);
    
    $response = array(
        'status' => $status,
        'last_check' => $last_check ? date('Y-m-d H:i:s', $last_check) : 'Never',
        'message' => ''
    );
    
    switch ($status) {
        case 'success':
            $response['message'] = 'License is valid and active.';
            break;
        case 'expired':
            $response['message'] = 'License has expired. Please renew your license.';
            break;
        case 'invalid':
            $response['message'] = 'Invalid license key. Please check your license key.';
            break;
        default:
            $response['message'] = 'Unknown license status.';
            break;
    }
    
    wp_send_json_success($response);
});

// Brand profile functions
function aico_get_brand_profile() {
    return get_option('aico_brand_profile', []);
}

function aico_get_brand_prefix() {
    $brand = aico_get_brand_profile();
    if (empty($brand) || !is_array($brand)) return '';
    
    $prefix = '';
    if (!empty($brand['overview'])) {
        $prefix .= "Company Overview: {$brand['overview']}\n\n";
    }
    if (!empty($brand['name'])) {
        $prefix .= "Brand: {$brand['name']}\n";
    }
    if (!empty($brand['tagline'])) {
        $prefix .= "Tagline: {$brand['tagline']}\n";
    }
    if (!empty($brand['tone'])) {
        $prefix .= "Tone: {$brand['tone']}\n";
    }
    if (!empty($brand['keywords'])) {
        $prefix .= "Keywords: " . implode(', ', (array)$brand['keywords']) . "\n";
    }
    if (!empty($brand['audience'])) {
        $prefix .= "Audience: {$brand['audience']}\n";
    }
    if (!empty($brand['banned'])) {
        $prefix .= "Banned: " . implode(', ', (array)$brand['banned']) . "\n";
    }
    
    return trim($prefix) . "\n\n";
}

function aico_render_brand_profile_page() {
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && check_admin_referer('aico_save_brand_profile')) {
        $profile = [
            'name'     => sanitize_text_field($_POST['brand_name'] ?? ''),
            'tagline'  => sanitize_text_field($_POST['brand_tagline'] ?? ''),
            'tone'     => sanitize_text_field($_POST['brand_tone'] ?? ''),
            'keywords' => array_filter(array_map('trim', explode(',', $_POST['brand_keywords'] ?? ''))),
            'audience' => sanitize_text_field($_POST['brand_audience'] ?? ''),
            'banned'   => array_filter(array_map('trim', explode(',', $_POST['brand_banned'] ?? ''))),
            'overview' => sanitize_textarea_field($_POST['brand_overview'] ?? ''),
        ];
        update_option('aico_brand_profile', $profile);
        echo '<div class="updated"><p>Brand profile saved!</p></div>';
    }
    $profile = aico_get_brand_profile();
    ?>
    <div class="wrap aico-wrap">
        <h1><?php _e('Brand Profile', 'ai-content-optimizer'); ?></h1>
        <p><?php _e('Configure your brand profile for AI content generation.', 'ai-content-optimizer'); ?></p>
    </div>
    <?php
} 