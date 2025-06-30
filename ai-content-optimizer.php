<?php
/**
 * Plugin Name: Bulk Meta Optimizer
 * Description: A comprehensive AI-powered content optimization tool for WordPress that generates and optimizes meta titles, descriptions, content, and permalinks for posts, pages, and WooCommerce products.
 * Version: 1.0.3
 * Author: Dapper Dev
 * Author URI: http://bulkmetaoptimizer.com/
 */

/**
 * ────────────────────────────────────────────────────────────────────────────
 * SLM License Verification & Domain Registration
 * ────────────────────────────────────────────────────────────────────────────
 *
 * This plugin uses Software License Manager (SLM) endpoints on bulkmetaoptimizer.com
 * to activate, check, and deactivate licenses tied to the site's domain.
 *
 * 1. Endpoints & Constants
 *    • BMO_SLM_SERVER          = 'https://bulkmetaoptimizer.com'
 *    • BMO_SLM_ITEM            = 'Bulk Meta Optimizer Plugin'
 *    • BMO_SLM_SECRET_VERIFY   = '685361e739ae33.52122910'
 *
 * 2. POST Parameters (all requests)
 *    • slm_action     : 'slm_activate' | 'slm_check' | 'slm_deactivate'
 *    • secret_key     : BMO_SLM_SECRET_VERIFY
 *    • license_key    : (user-entered license string)
 *    • item_reference : BMO_SLM_ITEM
 *    • url            : full site URL (scheme + host), e.g. https://example.com
 *    • domain_name    : host only, e.g. example.com
 *
 * 3. Expected Response
 *    • JSON payload: { "result": "success"|"expired"|"error", "message": "…optional details…" }
 *    • On activate/check, stored in WP option 'bmo_license_status'
 *
 * 4. Gating Logic
 *    • If bmo_license_status !== 'success', plugin hooks and AJAX are disabled
 *    • Admin notice shows "Invalid" or "Expired" messages
 *
 * 5. Hooks & Scheduling
 *    • Activation hook  → slm_activate
 *    • Deactivation hook→ slm_deactivate
 *    • admin_init       → slm_check   (and daily via wp_schedule_event)
 *
 * @see https://bulkmetaoptimizer.com/docs/slm
 * ────────────────────────────────────────────────────────────────────────────
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// ───── SLM License Verification ─────
if ( ! defined( 'BMO_SLM_SERVER' ) ) {
    define( 'BMO_SLM_SERVER', 'https://bulkmetaoptimizer.com' );
}
if ( ! defined( 'BMO_SLM_ITEM' ) ) {
    define( 'BMO_SLM_ITEM', 'Bulk Meta Optimizer Plugin' );
}
if ( ! defined( 'BMO_SLM_SECRET_VERIFY' ) ) {
    define( 'BMO_SLM_SECRET_VERIFY', '685361e739ae33.52122910' );
}
// ───────────────────────────────────

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
            'title_separator' => 'dash', // Default separator
            'excluded_words' => '', // New field for excluded words
        );

        // Default optimization toggles
        $default_toggles = array(
            'optimize_title' => true,
            'optimize_meta' => true,
            'optimize_content' => false, // Default off
            'optimize_slug' => false,
            'preserve_html' => true,
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
        $preserve_prompt = <<<'PROMPT'
Generate HTML output based on the structure provided below, but only update the text content to focus on {PAGE TITLE}. It is crucial to:{
Preserve all shortcodes, CSS, HTML classes, IDs, and structure exactly as they are. This includes all Visual Composer elements, styling, and embedded HTML tags. Do not alter or remove any CSS, HTML classes, or structural elements within the template.
Update only the text content (e.g., headers, body text, and calls to action) to focus clarity, using SEO keywords, service descriptions, and location-based phrases related to {existing content}.
SEO and Content Guidelines:
Ensure headers follow best practices, using one <h1> tag for the main keyword, and <h2> and <h3> tags for secondary keywords relevant to specific services or benefits.
Keep all headers and body text informative and optimized for readability and SEO, maintaining relevance to the new service and location.
Formatting Requirements:
Flattened Structure: Present the final content as a single line without any line breaks or extra spaces to ensure compatibility with WordPress.
Single-String Output: Retain all shortcodes, CSS, classes, and HTML tags exactly as provided, with no extraneous line breaks or spaces.
Template Structure:

{EXISTINGCONTENT OF POST/PAGE/PRODUCT HERE}

Important!: Do not change or omit any CSS, HTML classes, shortcodes, or structural elements. Only update the text to align with the new service and city focus. The output must be a single line of HTML with no added spaces or line breaks.

The text MUST be unique and different from the reference.
PROMPT;

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

        // Admin hooks - always add menu and settings for license management
        if (is_admin()) {
            // Add admin menu (always available for license management)
            add_action('admin_menu', array($this, 'add_admin_menu'));

            // Register settings (always available for license management)
            add_action('admin_init', array($this, 'register_settings'));

            // Check license status before enabling core functionality
            $license_status = get_option('bmo_license_status', 'invalid');
            if ($license_status !== 'success') {
                // If license is not valid, prevent core functionality from loading
                // Admin notices are handled by bmo_maybe_disable_plugin
                return;
            }

            // Core functionality - only if license is valid
            // Enqueue admin scripts and styles
            add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_assets'));

            // Add AJAX handlers
            add_action('wp_ajax_aico_test_api', array($this, 'ajax_test_api'));
            add_action('wp_ajax_aico_generate_content', array($this, 'ajax_generate_content'));
            add_action('wp_ajax_aico_bulk_optimize', array($this, 'ajax_bulk_optimize'));
            add_action('wp_ajax_aico_save_settings', array($this, 'ajax_save_settings'));
            add_action('wp_ajax_aico_build_brand_profile', array($this, 'ajax_build_brand_profile'));
            add_action('wp_ajax_aico_save_brand_profile', array($this, 'ajax_save_brand_profile'));
            add_action('wp_ajax_aico_bulk_optimize_item', array($this, 'ajax_bulk_optimize_item'));

            // Add row actions
            add_filter('post_row_actions', array($this, 'add_row_actions'), 10, 2);
            add_filter('page_row_actions', array($this, 'add_row_actions'), 10, 2);

            // Add product row actions if WooCommerce is active
            if (class_exists('WooCommerce')) {
                add_filter('product_row_actions', array($this, 'add_row_actions'), 10, 2);
            }

            // Add bulk actions
            add_filter('bulk_actions-edit-post', array($this, 'register_bulk_actions'));
            add_filter('bulk_actions-edit-page', array($this, 'register_bulk_actions'));

            // Add product bulk actions if WooCommerce is active
            if (class_exists('WooCommerce')) {
                add_filter('bulk_actions-edit-product', array($this, 'register_bulk_actions'));
                add_filter('handle_bulk_actions-edit-product', array($this, 'handle_bulk_actions'), 10, 3);
            }

            add_filter('handle_bulk_actions-edit-post', array($this, 'handle_bulk_actions'), 10, 3);
            add_filter('handle_bulk_actions-edit-page', array($this, 'handle_bulk_actions'), 10, 3);
            
            // Add bulk action error handler
            add_action('admin_notices', array($this, 'handle_bulk_action_errors'));
        }
    }

    /**
     * Register settings
     */
    public function register_settings() {
        // Register API settings
        register_setting('aico_api_settings', 'aico_openai_api_key');
        register_setting('aico_api_settings', 'aico_openai_model');
        register_setting('aico_api_settings', 'aico_openai_temperature');
        register_setting('aico_api_settings', 'aico_openai_max_tokens');

        // Register advanced settings
        register_setting('aico_advanced_settings', 'aico_custom_css');
        register_setting('aico_advanced_settings', 'aico_debug_mode');

        // Register content settings for each post type
        foreach (array('post', 'page', 'product') as $post_type) {
            register_setting('aico_content_settings', 'aico_' . $post_type . '_settings');
            register_setting(
                'aico_content_settings',
                'aico_' . $post_type . '_title_prompt',
                array('type' => 'string', 'sanitize_callback' => array($this, 'allow_html_prompts'))
            );
            register_setting(
                'aico_content_settings',
                'aico_' . $post_type . '_meta_prompt',
                array('type' => 'string', 'sanitize_callback' => array($this, 'allow_html_prompts'))
            );
            register_setting(
                'aico_content_settings',
                'aico_' . $post_type . '_content_prompt',
                array('type' => 'string', 'sanitize_callback' => array($this, 'allow_html_prompts'))
            );
        }
    }

    /**
     * Add admin menu
     */
    public function add_admin_menu() {
        add_menu_page(
            __('Bulk Meta Optimizer', 'ai-content-optimizer'),
            __('Bulk Meta Optimizer', 'ai-content-optimizer'),
            'manage_options',
            'ai-content-optimizer',
            array($this, 'render_dashboard_page'),
            'dashicons-chart-area',
            30
        );

        add_submenu_page(
            'ai-content-optimizer',
            __('Dashboard', 'ai-content-optimizer'),
            __('Dashboard', 'ai-content-optimizer'),
            'manage_options',
            'ai-content-optimizer',
            array($this, 'render_dashboard_page')
        );

        add_submenu_page(
            'ai-content-optimizer',
            __('Content Settings', 'ai-content-optimizer'),
            __('Content Settings', 'ai-content-optimizer'),
            'manage_options',
            'ai-content-optimizer-settings',
            array($this, 'render_settings_page')
        );

        add_submenu_page(
            'ai-content-optimizer',
            __('Brand Profile', 'ai-content-optimizer'),
            __('Brand Profile', 'ai-content-optimizer'),
            'manage_options',
            'ai-content-optimizer-brand-profile',
            array($this, 'render_brand_profile_page')
        );

        add_submenu_page(
            'ai-content-optimizer',
            __('Bulk Process', 'ai-content-optimizer'),
            __('Bulk Process', 'ai-content-optimizer'),
            'manage_options',
            'ai-content-optimizer-bulk-process',
            array($this, 'render_bulk_process_page')
        );

        add_submenu_page(
            'ai-content-optimizer',
            __('Settings', 'ai-content-optimizer'),
            __('Settings', 'ai-content-optimizer'),
            'manage_options',
            'ai-content-optimizer-advanced',
            array($this, 'render_advanced_page')
        );
    }

    /**
     * Enqueue admin scripts and styles
     */
    public function enqueue_admin_assets($hook) {
        // Only load on our plugin pages or edit screens
        if (strpos($hook, 'ai-content-optimizer') !== false ||
            $hook === 'edit.php' ||
            $hook === 'post.php' ||
            $hook === 'post-new.php') {

            // Enqueue styles
            wp_enqueue_style(
                'ai-content-optimizer-admin',
                plugin_dir_url(__FILE__) . 'assets/css/admin.css',
                array(),
                self::VERSION
            );

            // Enqueue tab-specific styles with higher priority
            wp_enqueue_style(
                'ai-content-optimizer-admin-tabs',
                plugin_dir_url(__FILE__) . 'assets/css/admin-tabs.css',
                array('ai-content-optimizer-admin'),
                self::VERSION . '.' . time() // Force no caching
            );

            // Enqueue scripts
            wp_enqueue_script(
                'ai-content-optimizer-admin',
                plugin_dir_url(__FILE__) . 'assets/js/admin.js',
                array('jquery'),
                self::VERSION,
                true
            );

            // Localize script
            wp_localize_script(
                'ai-content-optimizer-admin',
                'aicoData',
                array(
                    'ajaxUrl' => admin_url('admin-ajax.php'),
                    'nonce' => wp_create_nonce('aico-nonce'),
                    'strings' => array(
                        'generating' => __('Generating...', 'ai-content-optimizer'),
                        'success' => __('Success!', 'ai-content-optimizer'),
                        'error' => __('Error:', 'ai-content-optimizer'),
                        'confirmBulk' => __('Are you sure you want to optimize the selected items? This may take some time.', 'ai-content-optimizer'),
                        'processing' => __('Processing...', 'ai-content-optimizer'),
                    ),
                )
            );
        }

        if ($hook === 'edit.php') {
            wp_enqueue_script(
                'ai-content-optimizer-list',
                plugins_url('assets/js/list.js', __FILE__),
                array('jquery'),
                self::VERSION,
                true
            );

            wp_localize_script(
                'ai-content-optimizer-list',
                'aicoListData',
                array(
                    'ajaxUrl' => admin_url('admin-ajax.php'),
                    'nonce' => wp_create_nonce('aico-nonce'),
                    'strings' => array(
                        'optimizing' => __('Optimizing...', 'ai-content-optimizer'),
                        'success' => __('Optimized!', 'ai-content-optimizer'),
                        'error' => __('Error:', 'ai-content-optimizer'),
                    )
                )
            );
        }
    }

    /**
     * Add row actions
     */
    public function add_row_actions($actions, $post) {
        // Get all public post types
        $valid_post_types = get_post_types(array('public' => true));
        unset($valid_post_types['attachment']);

        // Check if post type is supported
        if (!in_array($post->post_type, $valid_post_types)) {
            return $actions;
        }

        // Add optimize action
        $actions['aico_optimize'] = sprintf(
            '<a href="#" class="aico-optimize" data-post-id="%d" data-nonce="%s">%s</a>',
            $post->ID,
            wp_create_nonce('aico-nonce'),
            __('Optimize with AI', 'ai-content-optimizer')
        );

        return $actions;
    }

    /**
     * Register bulk actions
     */
    public function register_bulk_actions($bulk_actions) {
        $bulk_actions['aico_bulk_optimize'] = __('Optimize with AI', 'ai-content-optimizer');
        return $bulk_actions;
    }

    /**
     * Handle bulk actions
     */
    public function handle_bulk_actions($redirect_to, $doaction, $post_ids) {
        if ($doaction !== 'aico_bulk_optimize') {
            return $redirect_to;
        }
        
        // Check license status
        $license_status = get_option('bmo_license_status', 'invalid');
        if ($license_status !== 'success') {
            return add_query_arg(
                array(
                    'aico_bulk_error' => 'license_required',
                ),
                $redirect_to
            );
        }

        // Check permissions
        if (!current_user_can('edit_posts')) {
            return add_query_arg(
                array(
                    'aico_bulk_error' => 'permission_denied',
                ),
                $redirect_to
            );
        }

        try {
            // Store the post IDs in a transient for processing
            $process_id = uniqid('aico_bulk_');
            set_transient('aico_bulk_process_' . $process_id, array(
                'post_ids' => $post_ids,
                'total' => count($post_ids),
                'processed' => 0,
                'success' => array(),
                'errors' => array(),
                'status' => 'pending'
            ), HOUR_IN_SECONDS);

            // Redirect to a processing page
            return add_query_arg(
                array(
                    'page' => 'ai-content-optimizer-bulk-process',
                    'process_id' => $process_id,
                    'post_type' => isset($_REQUEST['post_type']) ? sanitize_text_field($_REQUEST['post_type']) : 'post'
                ),
                admin_url('admin.php')
            );
        } catch (Exception $e) {
            error_log('Exception in handle_bulk_actions: ' . $e->getMessage());
            return add_query_arg(
                array(
                    'aico_bulk_error' => 'processing_error',
                ),
                $redirect_to
            );
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
        // Get content statistics
        $stats = $this->get_content_statistics();

        // Get recent activity
        $recent_activity = $this->get_recent_activity();

        ?>
        <div class="wrap aico-wrap">
            <h1><?php _e('Bulk Meta Optimizer Dashboard', 'ai-content-optimizer'); ?></h1>

            <div class="aico-dashboard">
                <div class="aico-card aico-stats-card">
                    <h2><?php _e('Content Statistics', 'ai-content-optimizer'); ?></h2>
                    <div class="aico-stats-grid">
                        <?php foreach ($stats as $post_type => $data) :
                            if (!post_type_exists($post_type)) continue;

                            $post_type_obj = get_post_type_object($post_type);
                            $label = $post_type_obj->labels->name;
                            $count = $data['total'];
                            $optimized = $data['optimized'];
                            $percentage = $count > 0 ? round(($optimized / $count) * 100) : 0;
                        ?>
                            <div class="aico-stat-item">
                                <h3><?php echo esc_html($label); ?></h3>
                                <div class="aico-stat-numbers">
                                    <span class="aico-stat-total"><?php echo esc_html($count); ?></span>
                                    <span class="aico-stat-optimized"><?php echo esc_html($optimized); ?></span>
                                    <span class="aico-stat-percentage"><?php echo esc_html($percentage); ?>%</span>
                                </div>
                                <div class="aico-progress-bar">
                                    <div class="aico-progress" style="width: <?php echo esc_attr($percentage); ?>%"></div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <div class="aico-actions">
                        <h2><?php _e('Quick Actions', 'ai-content-optimizer'); ?></h2>
                        <div class="aico-actions-grid">
                            <?php foreach ($stats as $post_type => $data) :
                                if (!post_type_exists($post_type)) continue;

                                $post_type_obj = get_post_type_object($post_type);
                                $label = $post_type_obj->labels->name;
                            ?>
                                <div class="aico-action-item">
                                    <h3><?php printf(__('Optimize %s', 'ai-content-optimizer'), esc_html($label)); ?></h3>
                                    <a href="<?php echo esc_url(admin_url('edit.php?post_type=' . ($post_type === 'post' ? 'post' : $post_type))); ?>" class="button button-secondary"><?php printf(__('View %s', 'ai-content-optimizer'), esc_html($label)); ?></a>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>

                <div class="aico-card aico-activity-card">
                    <h2><?php _e('Recent Activity', 'ai-content-optimizer'); ?></h2>
                    <?php if (empty($recent_activity)) : ?>
                        <p><?php _e('No recent activity.', 'ai-content-optimizer'); ?></p>
                    <?php else : ?>
                        <ul class="aico-activity-list">
                            <?php foreach ($recent_activity as $activity) : ?>
                                <li>
                                    <span class="aico-activity-time"><?php echo esc_html($activity['time']); ?></span>
                                    <span class="aico-activity-text"><?php echo esc_html($activity['text']); ?></span>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                </div>
            </div>
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
        $post_type = isset($_GET['post_type']) ? sanitize_text_field($_GET['post_type']) : 'post';
        
        // Get all public post types including custom ones
        $post_types = get_post_types(array('public' => true), 'objects');
        
        // Remove attachment post type
        unset($post_types['attachment']);
        
        // Validate selected post type
        if (!array_key_exists($post_type, $post_types)) {
            $post_type = 'post';
        }

        // Get settings for all post types
        $post_type_settings = array();
        $post_type_prompts = array();
        
        foreach ($post_types as $pt) {
            $post_type_settings[$pt->name] = get_option('aico_' . $pt->name . '_settings', array());
            $post_type_prompts[$pt->name] = array(
                'title' => get_option('aico_' . $pt->name . '_title_prompt', ''),
                'meta' => get_option('aico_' . $pt->name . '_meta_prompt', ''),
                'content' => get_option('aico_' . $pt->name . '_content_prompt', ''),
            );
        }

        ?>
        <div class="wrap aico-wrap">
            <h1><?php _e('Content Settings', 'ai-content-optimizer'); ?></h1>

            <div class="aico-tabs">
                <div class="aico-tab-nav">
                    <?php foreach ($post_types as $pt) : ?>
                        <a href="#aico-tab-<?php echo esc_attr($pt->name); ?>" 
                           class="aico-tab-link <?php echo $post_type === $pt->name ? 'active' : ''; ?>">
                            <?php echo esc_html($pt->labels->name); ?>
                        </a>
                    <?php endforeach; ?>
                </div>

                <?php foreach ($post_types as $pt) : ?>
                    <div class="aico-tab-content <?php echo $post_type === $pt->name ? 'active' : ''; ?>" 
                         id="aico-tab-<?php echo esc_attr($pt->name); ?>">
                        <form method="post" action="<?php echo esc_url(admin_url('admin-ajax.php')); ?>" 
                              class="aico-settings-form">
                            <input type="hidden" name="action" value="aico_save_settings" />
                            <input type="hidden" name="post_type" value="<?php echo esc_attr($pt->name); ?>" />
                            <?php wp_nonce_field('aico-nonce', 'nonce'); ?>

                            <?php $this->render_post_type_settings_fields(
                                $pt->name,
                                $post_type_settings[$pt->name],
                                $post_type_prompts[$pt->name]['title'],
                                $post_type_prompts[$pt->name]['meta'],
                                $post_type_prompts[$pt->name]['content']
                            ); ?>

                            <p class="submit">
                                <button type="submit" class="button button-primary">
                                    <?php printf(__('Save %s Settings', 'ai-content-optimizer'), 
                                        esc_html($pt->labels->singular_name)); ?>
                                </button>
                            </p>
                        </form>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php
    }

    /**
     * Render post type settings fields
     */
    private function render_post_type_settings_fields($post_type, $settings, $title_prompt, $meta_prompt, $content_prompt) {
        $post_type_obj = get_post_type_object($post_type);
        $post_type_label = $post_type_obj ? $post_type_obj->labels->singular_name : ucfirst($post_type);

        $settings_key = 'aico_' . $post_type . '_settings';
        $settings = get_option($settings_key, array());

        ?>
        <div class="aico-settings-grid">
            <div class="aico-settings-column">
                <div class="aico-card">
                    <h2><?php _e('Optimization Toggles', 'ai-content-optimizer'); ?></h2>

                    <table class="form-table">
                        <tr>
                            <th scope="row"><?php _e('Optimize Title', 'ai-content-optimizer'); ?></th>
                            <td>
                                <label>
                                    <input type="checkbox" name="settings[optimize_title]" value="1" <?php checked(isset($settings['optimize_title']) ? $settings['optimize_title'] : true); ?> />
                                    <?php printf(__('Generate optimized titles for %s', 'ai-content-optimizer'), esc_html($post_type_label)); ?>
                                </label>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><?php _e('Title Separator', 'ai-content-optimizer'); ?></th>
                            <td>
                                <select name="settings[title_separator]">
                                    <?php
                                    $separators = array(
                                        'dash' => __('Dash (-)', 'ai-content-optimizer'),
                                        'pipe' => __('Pipe (|)', 'ai-content-optimizer'),
                                        'colon' => __('Colon (:)', 'ai-content-optimizer'),
                                        'bullet' => __('Bullet (•)', 'ai-content-optimizer'),
                                        'arrow' => __('Arrow (»)', 'ai-content-optimizer'),
                                        'dot' => __('Dot (·)', 'ai-content-optimizer'),
                                        'tilde' => __('Tilde (~)', 'ai-content-optimizer'),
                                    );

                                    $selected_separator = isset($settings['title_separator']) ? $settings['title_separator'] : 'dash';

                                    foreach ($separators as $value => $label) {
                                        printf(
                                            '<option value="%s" %s>%s</option>',
                                            esc_attr($value),
                                            selected($selected_separator, $value, false),
                                            esc_html($label)
                                        );
                                    }
                                    ?>
                                </select>
                                <p class="description"><?php _e('Select the separator to use between title parts', 'ai-content-optimizer'); ?></p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><?php _e('Optimize Meta Description', 'ai-content-optimizer'); ?></th>
                            <td>
                                <label>
                                    <input type="checkbox" name="settings[optimize_meta]" value="1" <?php checked(isset($settings['optimize_meta']) ? $settings['optimize_meta'] : true); ?> />
                                    <?php printf(__('Generate optimized meta descriptions for %s', 'ai-content-optimizer'), esc_html($post_type_label)); ?>
                                </label>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><?php _e('Optimize Content', 'ai-content-optimizer'); ?></th>
                            <td>
                                <label>
                                    <input type="checkbox" name="settings[optimize_content]" value="1" <?php checked(isset($settings['optimize_content']) ? $settings['optimize_content'] : false); ?> />
                                    <?php printf(__('Generate optimized content for %s', 'ai-content-optimizer'), esc_html($post_type_label)); ?>
                                </label>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><?php _e('Optimize Permalink', 'ai-content-optimizer'); ?></th>
                            <td>
                                <label>
                                    <input type="checkbox" name="settings[optimize_slug]" value="1" <?php checked(isset($settings['optimize_slug']) ? $settings['optimize_slug'] : false); ?> />
                                    <?php printf(__('Generate optimized permalinks for %s', 'ai-content-optimizer'), esc_html($post_type_label)); ?>
                                </label>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><?php _e('Preserve HTML', 'ai-content-optimizer'); ?></th>
                            <td>
                                <label>
                                    <input type="checkbox" name="settings[preserve_html]" value="1" <?php checked(isset($settings['preserve_html']) ? $settings['preserve_html'] : true); ?> />
                                    <?php _e('Preserve HTML tags and shortcodes in content', 'ai-content-optimizer'); ?>
                                </label>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><?php _e('Excluded Words', 'ai-content-optimizer'); ?></th>
                            <td>
                                <textarea name="settings[excluded_words]" rows="3" class="large-text"><?php echo esc_textarea(isset($settings['excluded_words']) ? $settings['excluded_words'] : ''); ?></textarea>
                                <p class="description"><?php _e('Enter words to exclude from generated content, one per line. These words will be removed from titles and descriptions.', 'ai-content-optimizer'); ?></p>
                            </td>
                        </tr>
                    </table>
                </div>

                <div class="aico-card">
                    <h2><?php _e('Content Style Options', 'ai-content-optimizer'); ?></h2>

                    <table class="form-table">
                        <tr>
                            <th scope="row"><?php _e('Content Tone', 'ai-content-optimizer'); ?></th>
                            <td>
                                <select name="settings[content_tone]">
                                    <?php
                                    $tones = array(
                                        'professional' => __('Professional', 'ai-content-optimizer'),
                                        'conversational' => __('Conversational', 'ai-content-optimizer'),
                                        'educational' => __('Educational', 'ai-content-optimizer'),
                                        'persuasive' => __('Persuasive', 'ai-content-optimizer'),
                                        'technical' => __('Technical', 'ai-content-optimizer'),
                                        'enthusiastic' => __('Enthusiastic', 'ai-content-optimizer'),
                                    );

                                    $selected_tone = isset($settings['content_tone']) ? $settings['content_tone'] : 'professional';

                                    foreach ($tones as $value => $label) {
                                        printf(
                                            '<option value="%s" %s>%s</option>',
                                            esc_attr($value),
                                            selected($selected_tone, $value, false),
                                            esc_html($label)
                                        );
                                    }
                                    ?>
                                </select>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><?php _e('Target Audience', 'ai-content-optimizer'); ?></th>
                            <td>
                                <select name="settings[target_audience]">
                                    <?php
                                    $audiences = array(
                                        'general' => __('General', 'ai-content-optimizer'),
                                        'beginners' => __('Beginners', 'ai-content-optimizer'),
                                        'intermediate' => __('Intermediate', 'ai-content-optimizer'),
                                        'experts' => __('Experts', 'ai-content-optimizer'),
                                        'business' => __('Business', 'ai-content-optimizer'),
                                        'technical' => __('Technical', 'ai-content-optimizer'),
                                    );

                                    $selected_audience = isset($settings['target_audience']) ? $settings['target_audience'] : 'general';

                                    foreach ($audiences as $value => $label) {
                                        printf(
                                            '<option value="%s" %s>%s</option>',
                                            esc_attr($value),
                                            selected($selected_audience, $value, false),
                                            esc_html($label)
                                        );
                                    }
                                    ?>
                                </select>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><?php _e('Content Focus', 'ai-content-optimizer'); ?></th>
                            <td>
                                <select name="settings[content_focus]">
                                    <?php
                                    $focuses = array(
                                        'benefit-focused' => __('Benefit-Focused', 'ai-content-optimizer'),
                                        'feature-focused' => __('Feature-Focused', 'ai-content-optimizer'),
                                        'problem-solving' => __('Problem-Solving', 'ai-content-optimizer'),
                                        'informational' => __('Informational', 'ai-content-optimizer'),
                                        'storytelling' => __('Storytelling', 'ai-content-optimizer'),
                                    );

                                    $selected_focus = isset($settings['content_focus']) ? $settings['content_focus'] : 'benefit-focused';

                                    foreach ($focuses as $value => $label) {
                                        printf(
                                            '<option value="%s" %s>%s</option>',
                                            esc_attr($value),
                                            selected($selected_focus, $value, false),
                                            esc_html($label)
                                        );
                                    }
                                    ?>
                                </select>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><?php _e('SEO Aggressiveness', 'ai-content-optimizer'); ?></th>
                            <td>
                                <select name="settings[seo_aggressiveness]">
                                    <?php
                                    $aggressiveness = array(
                                        'minimal' => __('Minimal', 'ai-content-optimizer'),
                                        'moderate' => __('Moderate', 'ai-content-optimizer'),
                                        'aggressive' => __('Aggressive', 'ai-content-optimizer'),
                                    );

                                    $selected_aggressiveness = isset($settings['seo_aggressiveness']) ? $settings['seo_aggressiveness'] : 'moderate';

                                    foreach ($aggressiveness as $value => $label) {
                                        printf(
                                            '<option value="%s" %s>%s</option>',
                                            esc_attr($value),
                                            selected($selected_aggressiveness, $value, false),
                                            esc_html($label)
                                        );
                                    }
                                    ?>
                                </select>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><?php _e('Keyword Density', 'ai-content-optimizer'); ?></th>
                            <td>
                                <select name="settings[keyword_density]" id="aico-keyword-density">
                                    <?php
                                    $densities = array(
                                        'minimal' => __('Minimal (0.5-1%)', 'ai-content-optimizer'),
                                        'standard' => __('Standard (1-2%)', 'ai-content-optimizer'),
                                        'high' => __('High (2-3%)', 'ai-content-optimizer'),
                                        'custom' => __('Custom', 'ai-content-optimizer'),
                                    );

                                    $selected_density = isset($settings['keyword_density']) ? $settings['keyword_density'] : 'standard';

                                    foreach ($densities as $value => $label) {
                                        printf(
                                            '<option value="%s" %s>%s</option>',
                                            esc_attr($value),
                                            selected($selected_density, $value, false),
                                            esc_html($label)
                                        );
                                    }
                                    ?>
                                </select>

                                <div id="aico-custom-density-container" style="<?php echo $selected_density === 'custom' ? '' : 'display: none;'; ?>">
                                    <input type="number" name="settings[custom_density]" value="<?php echo esc_attr(isset($settings['custom_density']) ? $settings['custom_density'] : 1.5); ?>" step="0.1" min="0.1" max="5" />
                                    <span>%</span>
                                </div>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><?php _e('Geographic Targeting', 'ai-content-optimizer'); ?></th>
                            <td>
                                <select name="settings[geographic_targeting]" id="aico-geographic-targeting">
                                    <?php
                                    $regions = array(
                                        'global' => __('Global', 'ai-content-optimizer'),
                                        'us' => __('United States', 'ai-content-optimizer'),
                                        'uk' => __('United Kingdom', 'ai-content-optimizer'),
                                        'eu' => __('European Union', 'ai-content-optimizer'),
                                        'asia' => __('Asia', 'ai-content-optimizer'),
                                        'custom' => __('Custom', 'ai-content-optimizer'),
                                    );

                                    $selected_region = isset($settings['geographic_targeting']) ? $settings['geographic_targeting'] : 'global';

                                    foreach ($regions as $value => $label) {
                                        printf(
                                            '<option value="%s" %s>%s</option>',
                                            esc_attr($value),
                                            selected($selected_region, $value, false),
                                            esc_html($label)
                                        );
                                    }
                                    ?>
                                </select>

                                <div id="aico-custom-region-container" style="<?php echo $selected_region === 'custom' ? '' : 'display: none;'; ?>">
                                    <input type="text" name="settings[custom_region]" value="<?php echo esc_attr(isset($settings['custom_region']) ? $settings['custom_region'] : ''); ?>" placeholder="<?php _e('Enter region or country', 'ai-content-optimizer'); ?>" />
                                </div>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><?php _e('Brand Voice', 'ai-content-optimizer'); ?></th>
                            <td>
                                <select name="settings[brand_voice]">
                                    <?php
                                    $voices = array(
                                        'trustworthy' => __('Trustworthy', 'ai-content-optimizer'),
                                        'authoritative' => __('Authoritative', 'ai-content-optimizer'),
                                        'friendly' => __('Friendly', 'ai-content-optimizer'),
                                        'innovative' => __('Innovative', 'ai-content-optimizer'),
                                        'playful' => __('Playful', 'ai-content-optimizer'),
                                        'luxurious' => __('Luxurious', 'ai-content-optimizer'),
                                    );

                                    $selected_voice = isset($settings['brand_voice']) ? $settings['brand_voice'] : 'trustworthy';

                                    foreach ($voices as $value => $label) {
                                        printf(
                                            '<option value="%s" %s>%s</option>',
                                            esc_attr($value),
                                            selected($selected_voice, $value, false),
                                            esc_html($label)
                                        );
                                    }
                                    ?>
                                </select>
                            </td>
                        </tr>
                    </table>
                </div>
            </div>

            <div class="aico-settings-column">
                <div class="aico-card">
                    <h2><?php _e('AI Prompts', 'ai-content-optimizer'); ?></h2>
                    <p class="description"><?php _e('Customize the prompts used to generate content. You can use variables like {content_tone}, {target_audience}, etc.', 'ai-content-optimizer'); ?></p>

                    <table class="form-table">
                        <tr>
                            <th scope="row"><?php _e('Title Prompt', 'ai-content-optimizer'); ?></th>
                            <td>
                                <textarea name="title_prompt" rows="3" class="large-text"><?php echo esc_textarea($title_prompt); ?></textarea>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><?php _e('Meta Description Prompt', 'ai-content-optimizer'); ?></th>
                            <td>
                                <textarea name="meta_prompt" rows="3" class="large-text"><?php echo esc_textarea($meta_prompt); ?></textarea>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><?php _e('Content Prompt', 'ai-content-optimizer'); ?></th>
                            <td>
                                <textarea name="content_prompt" rows="5" class="large-text"><?php echo esc_textarea($content_prompt); ?></textarea>
                            </td>
                        </tr>
                    </table>

                    <div class="aico-prompt-variables">
                        <h3><?php _e('Available Variables', 'ai-content-optimizer'); ?></h3>
                        <ul>
                            <li><code>{content_tone}</code> - <?php _e('Selected content tone', 'ai-content-optimizer'); ?></li>
                            <li><code>{target_audience}</code> - <?php _e('Selected target audience', 'ai-content-optimizer'); ?></li>
                            <li><code>{content_focus}</code> - <?php _e('Selected content focus', 'ai-content-optimizer'); ?></li>
                            <li><code>{seo_aggressiveness}</code> - <?php _e('Selected SEO aggressiveness', 'ai-content-optimizer'); ?></li>
                            <li><code>{keyword_density}</code> - <?php _e('Selected keyword density', 'ai-content-optimizer'); ?></li>
                            <li><code>{geographic_targeting}</code> - <?php _e('Selected geographic targeting', 'ai-content-optimizer'); ?></li>
                            <li><code>{brand_voice}</code> - <?php _e('Selected brand voice', 'ai-content-optimizer'); ?></li>
                        </ul>
                    </div>
                </div>

                <div class="aico-card">
                    <h2><?php _e('Reset to Defaults', 'ai-content-optimizer'); ?></h2>
                    <p><?php _e('Reset all settings and prompts for this post type to default values.', 'ai-content-optimizer'); ?></p>
                    <button type="button" class="button aico-reset-defaults" data-post-type="<?php echo esc_attr($post_type); ?>"><?php _e('Reset to Defaults', 'ai-content-optimizer'); ?></button>
                </div>
            </div>
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
        $api_key = get_option('aico_openai_api_key', '');
        $model = get_option('aico_openai_model', 'gpt-3.5-turbo');
        $temperature = get_option('aico_openai_temperature', 0.7);
        $max_tokens = get_option('aico_openai_max_tokens', 500);

        ?>
        <div class="wrap aico-wrap">
            <h1><?php _e('API Settings', 'ai-content-optimizer'); ?></h1>

            <form method="post" action="options.php">
                <?php settings_fields('aico_api_settings'); ?>

                <div class="aico-card">
                    <h2><?php _e('OpenAI API Settings', 'ai-content-optimizer'); ?></h2>

                    <table class="form-table">
                        <tr>
                            <th scope="row"><?php _e('API Key', 'ai-content-optimizer'); ?></th>
                            <td>
                                <input type="password" name="aico_openai_api_key" value="<?php echo esc_attr($api_key); ?>" class="regular-text" />
                                <p class="description"><?php _e('Enter your OpenAI API key. You can get one from <a href="https://platform.openai.com/account/api-keys" target="_blank">OpenAI API Keys</a>.', 'ai-content-optimizer'); ?></p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><?php _e('Model', 'ai-content-optimizer'); ?></th>
                            <td>
                                <select name="aico_openai_model">
                                    <?php
                                    $models = array(
                                        'gpt-3.5-turbo' => 'GPT-3.5 Turbo',
                                        'gpt-4' => 'GPT-4',
                                        'gpt-4-turbo' => 'GPT-4 Turbo',
                                    );

                                    foreach ($models as $value => $label) {
                                        printf(
                                            '<option value="%s" %s>%s</option>',
                                            esc_attr($value),
                                            selected($model, $value, false),
                                            esc_html($label)
                                        );
                                    }
                                    ?>
                                </select>
                                <p class="description"><?php _e('Select the OpenAI model to use for content generation.', 'ai-content-optimizer'); ?></p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><?php _e('Temperature', 'ai-content-optimizer'); ?></th>
                            <td>
                                <input type="range" name="aico_openai_temperature" value="<?php echo esc_attr($temperature); ?>" min="0" max="1" step="0.1" class="aico-range" />
                                <span class="aico-range-value"><?php echo esc_html($temperature); ?></span>
                                <p class="description"><?php _e('Controls randomness: 0 is more focused and deterministic, 1 is more creative and diverse.', 'ai-content-optimizer'); ?></p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><?php _e('Max Tokens', 'ai-content-optimizer'); ?></th>
                            <td>
                                <input type="number" name="aico_openai_max_tokens" value="<?php echo esc_attr($max_tokens); ?>" min="50" max="4000" step="50" class="small-text" />
                                <p class="description"><?php _e('Maximum number of tokens to generate. Higher values allow for longer content but may increase costs.', 'ai-content-optimizer'); ?></p>
                            </td>
                        </tr>
                    </table>
                </div>

                <div class="aico-card">
                    <h2><?php _e('Test API Connection', 'ai-content-optimizer'); ?></h2>
                    <p><?php _e('Test your API connection to ensure everything is working correctly.', 'ai-content-optimizer'); ?></p>
                    <button type="button" id="aico-test-api-page" class="button button-secondary"><?php _e('Test Connection', 'ai-content-optimizer'); ?></button>
                    <div id="aico-test-result-page"></div>
                </div>

                <?php submit_button(__('Save API Settings', 'ai-content-optimizer')); ?>
            </form>
        </div>
        <?php
    }

    /**
     * Render advanced page
     */
    public function render_advanced_page() {
        $license_status = get_option('bmo_license_status', 'invalid');
        if ($license_status !== 'success') {
            // Show only the license key card (reuse existing code for license key form)
            ?>
            <div class="wrap aico-wrap">
                <h1><?php _e('Settings', 'ai-content-optimizer'); ?></h1>
                <div class="aico-card">
                    <h2><?php _e('License Key', 'ai-content-optimizer'); ?></h2>
                    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                        <input type="hidden" name="action" value="bmo_save_license_key" />
                        <?php wp_nonce_field('bmo_save_license_key', 'bmo_license_nonce'); ?>
                        <table class="form-table">
                            <tr valign="top">
                                <th scope="row"><label for="bmo_license_key"><?php _e('License Key', 'ai-content-optimizer'); ?></label></th>
                                <td>
                                    <input type="text" id="bmo_license_key" name="bmo_license_key" value="<?php echo esc_attr(get_option('bmo_license_key', '')); ?>" class="regular-text" />
                                    <p class="description"><?php _e('Paste your license key here.', 'ai-content-optimizer'); ?></p>
                                </td>
                            </tr>
                        </table>
                        <?php submit_button(__('Save License Key', 'ai-content-optimizer')); ?>
                    </form>
                </div>
            </div>
            <?php
            return;
        }
        $custom_css = get_option('aico_custom_css', '');
        $debug_mode = get_option('aico_debug_mode', false);
        $license_key = get_option('bmo_license_key', '');
        $license_saved = isset($_GET['bmo_license_saved']) ? sanitize_text_field($_GET['bmo_license_saved']) : '';
        $api_key = get_option('aico_openai_api_key', '');
        $model = get_option('aico_openai_model', 'gpt-3.5-turbo');
        $temperature = get_option('aico_openai_temperature', 0.7);
        $max_tokens = get_option('aico_openai_max_tokens', 500);
        ?>
        <div class="wrap aico-wrap">
            <h1><?php _e('Settings', 'ai-content-optimizer'); ?></h1>

            <!-- License Key Card -->
            <div class="aico-card">
                <h2><?php _e('License Key', 'ai-content-optimizer'); ?></h2>
                <?php if ($license_status === 'success') : ?>
                    <div class="notice notice-success inline"><p><?php _e('✔️ License is valid!', 'ai-content-optimizer'); ?></p></div>
                <?php elseif ($license_status === 'expired') : ?>
                    <div class="notice notice-warning inline"><p><?php _e('⚠️ License expired.', 'ai-content-optimizer'); ?></p></div>
                <?php elseif ($license_status === 'invalid') : ?>
                    <div class="notice notice-error inline"><p><?php _e('❌ Invalid license key.', 'ai-content-optimizer'); ?></p></div>
                <?php endif; ?>
                <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                    <input type="hidden" name="action" value="bmo_save_license_key" />
                    <?php wp_nonce_field('bmo_save_license_key', 'bmo_license_nonce'); ?>
                    <table class="form-table">
                        <tr valign="top">
                            <th scope="row"><label for="bmo_license_key"><?php _e('License Key', 'ai-content-optimizer'); ?></label></th>
                            <td>
                                <input type="text" id="bmo_license_key" name="bmo_license_key" value="<?php echo esc_attr($license_key); ?>" class="regular-text" />
                                <p class="description"><?php _e('Paste your license key here.', 'ai-content-optimizer'); ?></p>
                            </td>
                        </tr>
                    </table>
                    <?php submit_button(__('Save License Key', 'ai-content-optimizer')); ?>
                </form>
            </div>

            <!-- API Settings Card -->
            <form method="post" action="options.php">
                <?php settings_fields('aico_api_settings'); ?>
                <div class="aico-card">
                    <h2><?php _e('OpenAI API Settings', 'ai-content-optimizer'); ?></h2>
                    <table class="form-table">
                        <tr>
                            <th scope="row"><?php _e('API Key', 'ai-content-optimizer'); ?></th>
                            <td>
                                <input type="password" name="aico_openai_api_key" value="<?php echo esc_attr($api_key); ?>" class="regular-text" />
                                <p class="description"><?php _e('Enter your OpenAI API key. You can get one from <a href="https://platform.openai.com/account/api-keys" target="_blank">OpenAI API Keys</a>.', 'ai-content-optimizer'); ?></p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><?php _e('Model', 'ai-content-optimizer'); ?></th>
                            <td>
                                <select name="aico_openai_model">
                                    <?php
                                    $models = array(
                                        'gpt-3.5-turbo' => 'GPT-3.5 Turbo',
                                        'gpt-4' => 'GPT-4',
                                        'gpt-4-turbo' => 'GPT-4 Turbo',
                                    );
                                    foreach ($models as $value => $label) {
                                        printf(
                                            '<option value="%s" %s>%s</option>',
                                            esc_attr($value),
                                            selected($model, $value, false),
                                            esc_html($label)
                                        );
                                    }
                                    ?>
                                </select>
                                <p class="description"><?php _e('Select the OpenAI model to use for content generation.', 'ai-content-optimizer'); ?></p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><?php _e('Temperature', 'ai-content-optimizer'); ?></th>
                            <td>
                                <input type="range" name="aico_openai_temperature" value="<?php echo esc_attr($temperature); ?>" min="0" max="1" step="0.1" class="aico-range" />
                                <span class="aico-range-value"><?php echo esc_html($temperature); ?></span>
                                <p class="description"><?php _e('Controls randomness: 0 is more focused and deterministic, 1 is more creative and diverse.', 'ai-content-optimizer'); ?></p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><?php _e('Max Tokens', 'ai-content-optimizer'); ?></th>
                            <td>
                                <input type="number" name="aico_openai_max_tokens" value="<?php echo esc_attr($max_tokens); ?>" min="50" max="4000" step="50" class="small-text" />
                                <p class="description"><?php _e('Maximum number of tokens to generate. Higher values allow for longer content but may increase costs.', 'ai-content-optimizer'); ?></p>
                            </td>
                        </tr>
                    </table>
                </div>
                <div class="aico-card">
                    <h2><?php _e('Test API Connection', 'ai-content-optimizer'); ?></h2>
                    <p><?php _e('Test your API connection to ensure everything is working correctly.', 'ai-content-optimizer'); ?></p>
                    <button type="button" id="aico-test-api-page" class="button button-secondary"><?php _e('Test Connection', 'ai-content-optimizer'); ?></button>
                    <div id="aico-test-result-page"></div>
                </div>
                <?php submit_button(__('Save API Settings', 'ai-content-optimizer')); ?>
            </form>

            <form method="post" action="options.php">
                <?php settings_fields('aico_advanced_settings'); ?>
                <div class="aico-card">
                    <h2><?php _e('Custom CSS', 'ai-content-optimizer'); ?></h2>
                    <table class="form-table">
                        <tr>
                            <th scope="row"><?php _e('Custom CSS', 'ai-content-optimizer'); ?></th>
                            <td>
                                <textarea name="aico_custom_css" rows="10" class="large-text code"><?php echo esc_textarea($custom_css); ?></textarea>
                                <p class="description"><?php _e('Add custom CSS to style the plugin interface.', 'ai-content-optimizer'); ?></p>
                            </td>
                        </tr>
                    </table>
                </div>
                <div class="aico-card">
                    <h2><?php _e('Debug Mode', 'ai-content-optimizer'); ?></h2>
                    <table class="form-table">
                        <tr>
                            <th scope="row"><?php _e('Debug Mode', 'ai-content-optimizer'); ?></th>
                            <td>
                                <label>
                                    <input type="checkbox" name="aico_debug_mode" value="1" <?php checked($debug_mode); ?> />
                                    <?php _e('Enable debug mode', 'ai-content-optimizer'); ?>
                                </label>
                                <p class="description"><?php _e('When enabled, debug information will be logged for troubleshooting.', 'ai-content-optimizer'); ?></p>
                            </td>
                        </tr>
                    </table>
                </div>
                <div class="aico-card">
                    <h2><?php _e('Import/Export Settings', 'ai-content-optimizer'); ?></h2>
                    <div class="aico-import-export">
                        <div class="aico-export">
                            <h3><?php _e('Export Settings', 'ai-content-optimizer'); ?></h3>
                            <p><?php _e('Export all plugin settings to a JSON file.', 'ai-content-optimizer'); ?></p>
                            <button id="aico-export-settings" class="button"><?php _e('Export Settings', 'ai-content-optimizer'); ?></button>
                        </div>
                        <div class="aico-import">
                            <h3><?php _e('Import Settings', 'ai-content-optimizer'); ?></h3>
                            <p><?php _e('Import settings from a previously exported JSON file.', 'ai-content-optimizer'); ?></p>
                            <input type="file" id="aico-import-file" accept=".json" />
                            <button id="aico-import-settings" class="button"><?php _e('Import Settings', 'ai-content-optimizer'); ?></button>
                        </div>
                    </div>
                </div>
                <?php submit_button(__('Save Advanced Settings', 'ai-content-optimizer')); ?>
            </form>
        </div>
        <?php
    }

    /**
     * AJAX save settings
     */
    public function ajax_save_settings() {
        $license_status = get_option('bmo_license_status', 'invalid');
        if ($license_status !== 'success') {
            wp_send_json_error(__('A valid license is required to use this feature.', 'ai-content-optimizer'));
            exit;
        }
        check_ajax_referer('aico-nonce', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('You do not have permission to perform this action.', 'ai-content-optimizer'));
        }

        $post_type = isset($_POST['post_type']) ? sanitize_text_field($_POST['post_type']) : '';
        
        // Validate post type
        $valid_post_types = get_post_types(array('public' => true));
        unset($valid_post_types['attachment']);
        
        if (!in_array($post_type, $valid_post_types)) {
            wp_send_json_error(__('Invalid post type.', 'ai-content-optimizer'));
        }

        $settings = isset($_POST['settings']) ? $_POST['settings'] : array();
        $sanitized_settings = array();
        $sanitized_settings['optimize_title'] = isset($settings['optimize_title']) ? 1 : 0;
        $sanitized_settings['optimize_meta'] = isset($settings['optimize_meta']) ? 1 : 0;
        $sanitized_settings['optimize_content'] = isset($settings['optimize_content']) ? 1 : 0;
        $sanitized_settings['optimize_slug'] = isset($settings['optimize_slug']) ? 1 : 0;
        $sanitized_settings['preserve_html'] = isset($settings['preserve_html']) ? 1 : 0;
        $sanitized_settings['title_separator'] = sanitize_text_field($settings['title_separator'] ?? 'dash');
        $sanitized_settings['excluded_words'] = sanitize_textarea_field($settings['excluded_words'] ?? '');
        $sanitized_settings['content_tone'] = sanitize_text_field($settings['content_tone'] ?? 'professional');
        $sanitized_settings['target_audience'] = sanitize_text_field($settings['target_audience'] ?? 'general');
        $sanitized_settings['content_focus'] = sanitize_text_field($settings['content_focus'] ?? 'benefit-focused');
        $sanitized_settings['seo_aggressiveness'] = sanitize_text_field($settings['seo_aggressiveness'] ?? 'moderate');
        $sanitized_settings['keyword_density'] = sanitize_text_field($settings['keyword_density'] ?? 'standard');
        $sanitized_settings['geographic_targeting'] =sanitize_text_field($settings['geographic_targeting'] ?? 'global');
        $sanitized_settings['brand_voice'] = sanitize_text_field($settings['brand_voice'] ?? 'trustworthy');

        if ($sanitized_settings['keyword_density'] === 'custom') {
            $sanitized_settings['custom_density'] = floatval($settings['custom_density'] ?? 1.5);
        }
        if ($sanitized_settings['geographic_targeting'] === 'custom') {
            $sanitized_settings['custom_region'] = sanitize_text_field($settings['custom_region'] ?? '');
        }

        update_option('aico_' . $post_type . '_settings', $sanitized_settings);

        if (isset($_POST['title_prompt'])) {
            update_option('aico_' . $post_type . '_title_prompt', $this->allow_html_prompts($_POST['title_prompt']));
        }
        if (isset($_POST['meta_prompt'])) {
            update_option('aico_' . $post_type . '_meta_prompt', $this->allow_html_prompts($_POST['meta_prompt']));
        }
        if (isset($_POST['content_prompt'])) {
            update_option('aico_' . $post_type . '_content_prompt', $this->allow_html_prompts($_POST['content_prompt']));
        }

        wp_send_json_success(__('Settings saved successfully!', 'ai-content-optimizer'));
    }

    /**
     * Allow raw HTML in prompt text while keeping the site safe.
     */
    public function allow_html_prompts($value) {
        if (current_user_can('unfiltered_html')) {
            return wp_unslash($value);
        }
        return wp_kses_post(wp_unslash($value));
    }

    /**
     * Get content statistics
     */
    private function get_content_statistics() {
        // Get all public post types
        $post_types = get_post_types(array('public' => true));
        unset($post_types['attachment']);

        $stats = array();
        foreach ($post_types as $post_type) {
            $stats[$post_type] = array('total' => 0, 'optimized' => 0);
            
            if (!post_type_exists($post_type)) continue;
            
            // Get total published posts
            $total_query = new WP_Query(array(
                'post_type' => $post_type,
                'post_status' => 'publish',
                'posts_per_page' => -1,
                'fields' => 'ids',
                'no_found_rows' => true,
                'update_post_meta_cache' => false,
                'update_post_term_cache' => false
            ));
            $stats[$post_type]['total'] = count($total_query->posts);

            // Get optimized posts
            $optimized_query = new WP_Query(array(
                'post_type' => $post_type,
                'post_status' => 'publish',
                'posts_per_page' => -1,
                'fields' => 'ids',
                'no_found_rows' => true,
                'update_post_meta_cache' => false,
                'update_post_term_cache' => false,
                'meta_query' => array(
                    'relation' => 'OR',
                    array(
                        'key' => '_aico_optimized',
                        'compare' => 'EXISTS'
                    ),
                    array(
                        'key' => '_yoast_wpseo_metadesc',
                        'compare' => 'EXISTS',
                        'value' => '',
                        'compare' => '!='
                    ),
                ),
            ));
            $stats[$post_type]['optimized'] = count($optimized_query->posts);
        }

        return $stats;
    }

    /**
     * Get recent activity
     */
    private function get_recent_activity() {
        $activity = array();
        $recent_query = new WP_Query(array(
            'post_type' => array('post', 'page', 'product'),
            'post_status' => 'publish',
            'posts_per_page' => 5,
            'meta_key' => '_aico_optimized_time',
            'orderby' => 'meta_value_num',
            'order' => 'DESC',
            'meta_query' => array(array('key' => '_aico_optimized', 'compare' => 'EXISTS')),
        ));

        if ($recent_query->have_posts()) {
            while ($recent_query->have_posts()) {
                $recent_query->the_post();
                $post_id = get_the_ID();
                $type_label = get_post_type_object(get_post_type())->labels->singular_name;
                $time = get_post_meta($post_id, '_aico_optimized_time', true);
                $activity[] = array(
                    'time' => date_i18n(get_option('date_format') . ' ' . get_option('time_format'), $time),
                    'text' => sprintf(__('Optimized %s: %s', 'ai-content-optimizer'), $type_label, get_the_title()),
                );
            }
            wp_reset_postdata();
        }

        return $activity;
    }

    /**
     * AJAX test API
     */
    public function ajax_test_api() {
        $license_status = get_option('bmo_license_status', 'invalid');
        if ($license_status !== 'success') {
            wp_send_json_error(__('A valid license is required to use this feature.', 'ai-content-optimizer'));
            exit;
        }
        check_ajax_referer('aico-nonce', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('You do not have permission to perform this action.', 'ai-content-optimizer'));
        }
        $api_key = get_option('aico_openai_api_key');
        if (empty($api_key)) {
            wp_send_json_error(__('API key is not set.', 'ai-content-optimizer'));
        }
        $test_prompt = 'Test connection';
        $test_response = $this->call_openai_api_direct($api_key, get_option('aico_openai_model', 'gpt-3.5-turbo'), $test_prompt, 10, 0.4);
        if (is_wp_error($test_response)) {
            wp_send_json_error($test_response->get_error_message());
        }
        wp_send_json_success(__('API connection successful!', 'ai-content-optimizer'));
    }

    /**
     * AJAX generate content
     */
    public function ajax_generate_content() {
        $license_status = get_option('bmo_license_status', 'invalid');
        if ($license_status !== 'success') {
            wp_send_json_error(__('A valid license is required to use this feature.', 'ai-content-optimizer'));
            exit;
        }
        try {
            // Enable error reporting for debugging
            error_reporting(E_ALL);
            ini_set('display_errors', 1);
            
            // Enable error logging
            error_log('Starting ajax_generate_content');

            // Verify nonce first
            if (!check_ajax_referer('aico-nonce', 'nonce', false)) {
                error_log('Nonce verification failed');
                wp_send_json_error('Security check failed');
                return;
            }

            if (!current_user_can('edit_posts')) {
                error_log('User lacks permission');
                wp_send_json_error('You do not have permission to perform this action.');
                return;
            }

            // Get and validate post ID
            $post_id = isset($_POST['post_id']) ? intval($_POST['post_id']) : 0;
            error_log('Received post_id: ' . $post_id);
            
            if (!$post_id) {
                error_log('No post ID provided');
                wp_send_json_error('No post ID provided.');
                return;
            }

            // Verify post exists and get post object
            $post = get_post($post_id);
            if (!$post) {
                error_log('Invalid post ID: ' . $post_id);
                wp_send_json_error('Invalid post ID.');
                return;
            }

            // Get post type and verify it's valid
            $post_type = get_post_type($post);
            error_log('Post type: ' . $post_type);

            // Get all public post types
            $valid_post_types = get_post_types(array('public' => true));
            unset($valid_post_types['attachment']);
            error_log('Valid post types: ' . print_r($valid_post_types, true));

            if (!in_array($post_type, $valid_post_types)) {
                error_log('Unsupported post type: ' . $post_type);
                wp_send_json_error('Unsupported post type: ' . $post_type);
                return;
            }

            // Verify API key is set
            $api_key = get_option('aico_openai_api_key');
            if (empty($api_key)) {
                error_log('OpenAI API key not configured');
                wp_send_json_error('OpenAI API key not configured. Please configure it in the plugin settings.');
                return;
            }

            // Start optimization process
            error_log('Starting post optimization for ID: ' . $post_id);
            
            // Get settings for this post type
            $settings = get_option('aico_' . $post_type . '_settings', array());
            error_log('Settings for post type: ' . print_r($settings, true));

            // Verify we have prompts
            $title_prompt = get_option('aico_' . $post_type . '_title_prompt');
            $meta_prompt = get_option('aico_' . $post_type . '_meta_prompt');
            $content_prompt = get_option('aico_' . $post_type . '_content_prompt');

            if (empty($title_prompt) && empty($meta_prompt) && empty($content_prompt)) {
                error_log('No prompts configured for post type: ' . $post_type);
                wp_send_json_error('No prompts configured for this post type. Please configure prompts in the settings.');
                return;
            }

            // Attempt optimization
            $result = $this->optimize_post($post_id);
            
            if (is_wp_error($result)) {
                error_log('Optimization error: ' . $result->get_error_message());
                wp_send_json_error($result->get_error_message());
                return;
            }

            // Verify post was actually updated
            $updated_post = get_post($post_id);
            if (!$updated_post) {
                error_log('Failed to verify post update');
                wp_send_json_error('Failed to verify post update.');
                return;
            }

            error_log('Post optimization successful');
            wp_send_json_success(array(
                'message' => 'Content optimized successfully!',
                'post_id' => $post_id,
                'post_title' => get_the_title($post_id)
            ));

        } catch (Exception $e) {
            error_log('Exception in ajax_generate_content: ' . $e->getMessage());
            error_log('Exception trace: ' . $e->getTraceAsString());
            wp_send_json_error('Error optimizing content: ' . $e->getMessage());
        }
    }

    /**
     * Call OpenAI API directly
     */
    private function call_openai_api_direct($api_key, $model, $prompt, $max_tokens = 500, $temperature = 0.7) {
        try {
            error_log('Calling OpenAI API with model: ' . $model);
            
            $url = 'https://api.openai.com/v1/chat/completions';
            $headers = array(
                'Authorization' => 'Bearer ' . $api_key,
                'Content-Type' => 'application/json',
            );

            $body = array(
                'model' => $model,
                'messages' => array(
                    array(
                        'role' => 'user',
                        'content' => $prompt
                    )
                ),
                'max_tokens' => intval($max_tokens),
                'temperature' => floatval($temperature),
            );

            error_log('Sending request to OpenAI API');
            
            $response = wp_remote_post($url, array(
                'headers' => $headers,
                'body' => json_encode($body),
                'timeout' => 60,
            ));

            if (is_wp_error($response)) {
                error_log('WP Error in OpenAI API call: ' . $response->get_error_message());
                return new WP_Error('api_error', 'Failed to connect to OpenAI API: ' . $response->get_error_message());
            }

            $response_code = wp_remote_retrieve_response_code($response);
            $response_body = wp_remote_retrieve_body($response);

            error_log('OpenAI API response code: ' . $response_code);
            
            if ($response_code !== 200) {
                error_log('OpenAI API error response: ' . $response_body);
                return new WP_Error('api_error', 'OpenAI API error: ' . $response_body);
            }

            $data = json_decode($response_body, true);
            
            if (json_last_error() !== JSON_ERROR_NONE) {
                error_log('JSON decode error: ' . json_last_error_msg());
                return new WP_Error('json_error', 'Failed to parse API response');
            }

            if (!isset($data['choices'][0]['message']['content'])) {
                error_log('Unexpected API response format: ' . print_r($data, true));
                return new WP_Error('api_error', 'Unexpected API response format');
            }

            error_log('Successfully received response from OpenAI API');
            return trim($data['choices'][0]['message']['content']);

        } catch (Exception $e) {
            error_log('Exception in call_openai_api_direct: ' . $e->getMessage());
            error_log('Exception trace: ' . $e->getTraceAsString());
            return new WP_Error('api_error', 'API call failed: ' . $e->getMessage());
        }
    }

    /**
     * Process generated text
     */
    private function process_generated_text($text, $excluded_words = array()) {
        try {
            if (empty($text)) {
                error_log('Empty text received for processing');
                return '';
            }

            // Remove any markdown formatting
            $text = preg_replace('/[*_~`]/', '', $text);
            
            // Remove any HTML tags if they somehow got in
            $text = wp_strip_all_tags($text);
            
            // Remove any extra whitespace
            $text = trim(preg_replace('/\s+/', ' ', $text));

            // Handle excluded words
            if (!empty($excluded_words)) {
                foreach ($excluded_words as $word) {
                    $word = trim($word);
                    if (!empty($word)) {
                        $text = str_ireplace($word, '', $text);
                    }
                }
            }

            return $text;
        } catch (Exception $e) {
            error_log('Exception in process_generated_text: ' . $e->getMessage());
            return $text; // Return original text if processing fails
        }
    }

    /**
     * AJAX bulk optimize
     */
    public function ajax_bulk_optimize() {
        $license_status = get_option('bmo_license_status', 'invalid');
        if ($license_status !== 'success') {
            wp_send_json_error(__('A valid license is required to use this feature.', 'ai-content-optimizer'));
            exit;
        }
        check_ajax_referer('aico-nonce', 'nonce');

        if (!current_user_can('edit_posts')) {
            wp_send_json_error(__('You do not have permission to perform this action.', 'ai-content-optimizer'));
        }

        $post_ids = isset($_POST['post_ids']) ? array_map('intval', explode(',', $_POST['post_ids'])) : array();
        if (empty($post_ids)) {
            wp_send_json_error(__('No posts selected.', 'ai-content-optimizer'));
        }

        $results = array(
            'success' => array(),
            'error' => array()
        );

        foreach ($post_ids as $post_id) {
            $result = $this->optimize_post($post_id);
            if (is_wp_error($result)) {
                $results['error'][] = array(
                    'id' => $post_id,
                    'message' => $result->get_error_message()
                );
            } else {
                $results['success'][] = $post_id;
            }
        }

        wp_send_json_success($results);
    }

    /**
     * Optimize post
     */
    private function optimize_post($post_id) {
        try {
            error_log('Starting optimize_post for ID: ' . $post_id);
            
            $post = get_post($post_id);
            if (!$post) {
                error_log('Invalid post ID in optimize_post: ' . $post_id);
                return new WP_Error('invalid_post', 'Invalid post ID.');
            }

            // Get all public post types
            $valid_post_types = get_post_types(array('public' => true));
            unset($valid_post_types['attachment']);

            $post_type = $post->post_type;
            error_log('Post type in optimize_post: ' . $post_type);

            if (!in_array($post_type, $valid_post_types)) {
                error_log('Unsupported post type in optimize_post: ' . $post_type);
                return new WP_Error('unsupported_post_type', 'Unsupported post type: ' . $post_type);
            }

            $settings = get_option('aico_' . $post_type . '_settings', array());
            error_log('Settings for post type: ' . print_r($settings, true));

            $optimize_title = isset($settings['optimize_title']) ? (bool)$settings['optimize_title'] : true;
            $optimize_meta = isset($settings['optimize_meta']) ? (bool)$settings['optimize_meta'] : true;
            $optimize_content = isset($settings['optimize_content']) ? (bool)$settings['optimize_content'] : false;
            $optimize_slug = isset($settings['optimize_slug']) ? (bool)$settings['optimize_slug'] : false;
            $preserve_html = isset($settings['preserve_html']) ? (bool)$settings['preserve_html'] : true;
            $excluded_words = isset($settings['excluded_words']) ? $settings['excluded_words'] : '';
            $excluded_words_array = array();
            if (!empty($excluded_words)) {
                $excluded_words_array = array_map('trim', explode("\n", $excluded_words));
            }

            // Get content settings
            $content_tone = isset($settings['content_tone']) ? $settings['content_tone'] : 'professional';
            $target_audience = isset($settings['target_audience']) ? $settings['target_audience'] : 'general';
            $content_focus = isset($settings['content_focus']) ? $settings['content_focus'] : 'benefit-focused';
            $seo_aggressiveness = isset($settings['seo_aggressiveness']) ? $settings['seo_aggressiveness'] : 'moderate';
            $keyword_density = isset($settings['keyword_density']) ? $settings['keyword_density'] : 'standard';
            $geographic_targeting = isset($settings['geographic_targeting']) ? $settings['geographic_targeting'] : 'global';
            $brand_voice = isset($settings['brand_voice']) ? $settings['brand_voice'] : 'trustworthy';

            // Handle custom values
            if ($keyword_density === 'custom' && isset($settings['custom_density'])) {
                $custom_density = floatval($settings['custom_density']);
                $keyword_density_text = "with a custom keyword density of {$custom_density}%";
            } else {
                $density_values = array(
                    'minimal' => '0.5-1%',
                    'standard' => '1-2%',
                    'high' => '2-3%',
                );
                $keyword_density_text = "with a {$keyword_density} keyword density ({$density_values[$keyword_density]})";
            }

            if ($geographic_targeting === 'custom' && isset($settings['custom_region'])) {
                $custom_region = sanitize_text_field($settings['custom_region']);
                $geo_targeting_text = "targeting the {$custom_region} region";
            } else {
                $geo_targeting_text = "with {$geographic_targeting} geographic targeting";
            }

            // Get API settings
            $api_key = get_option('aico_openai_api_key');
            $model = get_option('aico_openai_model', 'gpt-3.5-turbo');
            $temperature = get_option('aico_openai_temperature', 0.7);
            $max_tokens = get_option('aico_openai_max_tokens', 500);

            if (empty($api_key)) {
                error_log('OpenAI API key not configured in optimize_post');
                return new WP_Error('api_key_missing', 'OpenAI API key not configured');
            }

            // Get current content
            $current_title = $post->post_title;
            $current_content = $post->post_content;
            $trimmed_content = mb_substr(wp_strip_all_tags($current_content), 0, 1000);

            // Get prompts
            $title_prompt = get_option('aico_' . $post_type . '_title_prompt');
            $meta_prompt = get_option('aico_' . $post_type . '_meta_prompt');
            $content_prompt = get_option('aico_' . $post_type . '_content_prompt');

            error_log('Retrieved prompts for post type ' . $post_type);
            error_log('Title prompt: ' . $title_prompt);
            error_log('Meta prompt: ' . $meta_prompt);
            error_log('Content prompt: ' . $content_prompt);

            // Replace variables in prompts
            $replacements = array(
                '{content_tone}' => $content_tone,
                '{target_audience}' => $target_audience,
                '{content_focus}' => $content_focus,
                '{seo_aggressiveness}' => $seo_aggressiveness,
                '{keyword_density}' => $keyword_density_text,
                '{geographic_targeting}' => $geo_targeting_text,
                '{brand_voice}' => $brand_voice,
                '{PAGE TITLE}' => $current_title,
                '{EXISTINGCONTENT OF POST/PAGE/PRODUCT HERE}' => $current_content,
                '{existing content}' => $current_content,
            );

            $title_prompt = str_replace(array_keys($replacements), array_values($replacements), $title_prompt);
            $meta_prompt = str_replace(array_keys($replacements), array_values($replacements), $meta_prompt);
            $content_prompt = str_replace(array_keys($replacements), array_values($replacements), $content_prompt);

            $results = array('title' => '', 'meta' => '', 'content' => '', 'slug' => '');
            $updated = false;

            // Generate title
            if ($optimize_title) {
                error_log('Optimizing title');
                $title_full_prompt = $title_prompt . "\n\nCurrent title: {$current_title}\n\nCurrent content excerpt: {$trimmed_content}\n\nPlease provide your response as plain text without any formatting.";
                $title_response = $this->call_openai_api_direct($api_key, $model, $title_full_prompt, 60, 0.4);
                if (is_wp_error($title_response)) {
                    error_log('Title optimization failed: ' . $title_response->get_error_message());
                    return $title_response;
                }
                $results['title'] = $this->process_generated_text($title_response, $excluded_words_array);
                update_post_meta($post_id, '_aico_title_optimized', true);
                $updated = true;
            }

            // Generate meta description
            if ($optimize_meta) {
                error_log('Optimizing meta');
                $meta_full_prompt = $meta_prompt . "\n\nTitle: " . ($results['title'] ? $results['title'] : $current_title) . "\n\nContent excerpt: {$trimmed_content}\n\nPlease provide your response as plain text without any formatting.";
                $meta_response = $this->call_openai_api_direct($api_key, $model, $meta_full_prompt, 160, 0.4);
                if (is_wp_error($meta_response)) {
                    error_log('Meta optimization failed: ' . $meta_response->get_error_message());
                    return $meta_response;
                }
                $results['meta'] = $this->process_generated_text($meta_response, $excluded_words_array);
                update_post_meta($post_id, '_aico_meta_optimized', true);
                $updated = true;
            }

            // Generate content
            if ($optimize_content) {
                error_log('Optimizing content');
                if ($preserve_html) {
                    $original_content = $post->post_content;

                    // Extract HTML tags
                    $html_tags = array();
                    if (preg_match_all('/<[^>]+>/', $original_content, $tag_matches)) {
                        foreach ($tag_matches[0] as $i => $full_tag) {
                            $html_tags["%%TAG_{$i}%%"] = $full_tag;
                        }
                    }

                    // Extract shortcodes
                    $shortcode_tags = array();
                    $pattern = get_shortcode_regex();
                    if (preg_match_all('/'.$pattern.'/s', $original_content, $sc_matches)) {
                        foreach ($sc_matches[0] as $i => $full_shortcode) {
                            $shortcode_tags["%%SC_{$i}%%"] = $full_shortcode;
                        }
                    }

                    // Replace with placeholders
                    $masked = $original_content;
                    if (!empty($html_tags)) {
                        $masked = str_replace(array_values($html_tags), array_keys($html_tags), $masked);
                    }
                    if (!empty($shortcode_tags)) {
                        $masked = str_replace(array_values($shortcode_tags), array_keys($shortcode_tags), $masked);
                    }

                    // Build masked prompt
                    $masked_prompt = $content_prompt . "\n\n";
                    $masked_prompt .= "Below is the existing page content, but ALL HTML tags and WordPress shortcodes\n";
                    $masked_prompt .= "have been replaced by placeholders (%%TAG_x%% or %%SC_y%%).\n";
                    $masked_prompt .= "Please rewrite or improve **only the plain‐text portions**. Do NOT modify or remove\n";
                    $masked_prompt .= "any placeholder. After rewriting, return the entire content with placeholders preserved.\n\n";
                    $masked_prompt .= "Masked Content:\n" . $masked . "\n\n";
                    $masked_prompt .= "Remember: Keep all placeholders (%%TAG_x%%, %%SC_y%%) exactly as‐is.\n";

                    $ai_response = $this->call_openai_api_direct($api_key, $model, $masked_prompt, $max_tokens, $temperature);
                    if (is_wp_error($ai_response)) {
                        error_log('Content optimization failed: ' . $ai_response->get_error_message());
                        return $ai_response;
                    }

                    $optimized_masked_text = $ai_response;
                    if (!empty($html_tags)) {
                        $optimized_masked_text = str_replace(array_keys($html_tags), array_values($html_tags), $optimized_masked_text);
                    }
                    if (!empty($shortcode_tags)) {
                        $optimized_masked_text = str_replace(array_keys($shortcode_tags), array_values($shortcode_tags), $optimized_masked_text);
                    }

                    $results['content'] = $optimized_masked_text;
                } else {
                    // Non-preserve path
                    $content_for_ai = mb_substr(wp_strip_all_tags($current_content), 0, 3000);
                    $plain_prompt = $content_prompt . "\n\nTitle: " . ($results['title'] ? $results['title'] : $current_title) . "\n\nCurrent content: {$content_for_ai}\n\nPlease provide your response in raw HTML format.";
                    $content_response = $this->call_openai_api_direct($api_key, $model, $plain_prompt, $max_tokens, $temperature);
                    if (is_wp_error($content_response)) {
                        error_log('Content optimization failed: ' . $content_response->get_error_message());
                        return $content_response;
                    }
                    $results['content'] = $content_response;
                }
                update_post_meta($post_id, '_aico_content_optimized', true);
                $updated = true;
            }

            // Generate slug
            if ($optimize_slug && !empty($results['title'])) {
                error_log('Optimizing slug');
                $slug = sanitize_title($results['title']);
                // Ensure slug is no longer than 35 characters
                if (strlen($slug) > 35) {
                    // Try to cut at the last hyphen before 35 chars, else hard cut
                    $truncated = substr($slug, 0, 35);
                    $last_hyphen = strrpos($truncated, '-');
                    if ($last_hyphen !== false && $last_hyphen > 0) {
                        $slug = substr($truncated, 0, $last_hyphen);
                    } else {
                        $slug = $truncated;
                    }
                }
                $results['slug'] = $slug;
                update_post_meta($post_id, '_aico_slug_optimized', true);
                $updated = true;
            }

            // Update post
            $update_data = array('ID' => $post_id);
            if (!empty($results['title']) && $results['title'] !== $post->post_title) {
                $update_data['post_title'] = $results['title'];
            }
            if (!empty($results['content']) && $results['content'] !== $post->post_content) {
                $update_data['post_content'] = $results['content'];
            }
            if (!empty($results['slug']) && $results['slug'] !== $post->post_name) {
                $update_data['post_name'] = $results['slug'];
            }
            if (count($update_data) > 1) {
                wp_update_post($update_data);
            }

            // --- Visual Composer compatibility: preserve custom CSS meta fields before update ---
            $vc_custom_css = get_post_meta($post_id, 'post_custom_css', true);
            $vc_shortcodes_css = get_post_meta($post_id, '_wpb_shortcodes_custom_css', true);
            $vc_post_custom_css = get_post_meta($post_id, '_wpb_post_custom_css', true);

            // --- Visual Composer compatibility: restore custom CSS meta if it existed ---
            if ($vc_custom_css !== '' && $vc_custom_css !== false) {
                update_post_meta($post_id, 'post_custom_css', $vc_custom_css);
            }
            if ($vc_shortcodes_css !== '' && $vc_shortcodes_css !== false) {
                update_post_meta($post_id, '_wpb_shortcodes_custom_css', $vc_shortcodes_css);
            }
            if ($vc_post_custom_css !== '' && $vc_post_custom_css !== false) {
                update_post_meta($post_id, '_wpb_post_custom_css', $vc_post_custom_css);
            }

            // Update meta description
            if (!empty($results['meta'])) {
                if (defined('WPSEO_VERSION')) {
                    update_post_meta($post_id, '_yoast_wpseo_metadesc', $results['meta']);
                }
                if (class_exists('AIOSEO')) {
                    update_post_meta($post_id, '_aioseo_description', $results['meta']);
                }
                if (class_exists('RankMath')) {
                    update_post_meta($post_id, 'rank_math_description', $results['meta']);
                }
                update_post_meta($post_id, '_aico_meta_description', $results['meta']);
            }

            // --- Visual Composer compatibility: preserve custom CSS meta ---
            $vc_custom_css = get_post_meta($post_id, 'post_custom_css', true);
            // ... existing code ...

            if ($updated) {
                update_post_meta($post_id, '_aico_optimized', true);
                update_post_meta($post_id, '_aico_optimized_time', time());
                error_log('Post optimization completed successfully');
                return true;
            }

            error_log('No optimization performed - no elements selected for optimization');
            return new WP_Error('no_optimization', 'No elements selected for optimization');

        } catch (Exception $e) {
            error_log('Exception in optimize_post: ' . $e->getMessage());
            return new WP_Error('optimization_error', $e->getMessage());
        }
    }

    /**
     * Add support for custom post types
     */
    public function add_custom_post_type_support() {
        // Check license status before enabling functionality
        $license_status = get_option('bmo_license_status', 'invalid');
        if ($license_status !== 'success') {
            // If license is not valid, prevent custom post type support from loading
            return;
        }

        // Get all public custom post types
        $custom_post_types = get_post_types(array(
            'public'   => true,
            '_builtin' => false,
        ), 'objects');

        // Remove 'product' if it exists as we handle it separately
        if (isset($custom_post_types['product'])) {
            unset($custom_post_types['product']);
        }

        // Get page prompts to use as defaults, or use standard defaults if page prompts don't exist
        $default_title_prompt = __('Write an SEO-optimized title for this {post_type} about {title}. Make it engaging and include the main keyword naturally. Keep it under 60 characters.', 'ai-content-optimizer');
        $default_meta_prompt = __('Write a compelling meta description for this {post_type} about {title}. Include the main keyword and a clear call-to-action. Keep it between 150-160 characters.', 'ai-content-optimizer');
        $default_content_prompt = __('Optimize this content while preserving its structure and meaning. Maintain all HTML tags, shortcodes, and formatting. Focus on improving readability and SEO without changing the core message.', 'ai-content-optimizer');

        $page_title_prompt = get_option('aico_page_title_prompt', $default_title_prompt);
        $page_meta_prompt = get_option('aico_page_meta_prompt', $default_meta_prompt);
        $page_content_prompt = get_option('aico_page_content_prompt', $default_content_prompt);

        // Initialize settings for each custom post type
        foreach ($custom_post_types as $post_type) {
            $settings_key = 'aico_' . $post_type->name . '_settings';
            $title_prompt_key = 'aico_' . $post_type->name . '_title_prompt';
            $meta_prompt_key = 'aico_' . $post_type->name . '_meta_prompt';
            $content_prompt_key = 'aico_' . $post_type->name . '_content_prompt';

            // Initialize settings if they don't exist
            if (!get_option($settings_key)) {
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

                $default_toggles = array(
                    'optimize_title' => true,
                    'optimize_meta' => true,
                    'optimize_content' => false,
                    'optimize_slug' => false,
                    'preserve_html' => true,
                );

                update_option($settings_key, array_merge($default_content_settings, $default_toggles));
            }

            // Always ensure prompts exist
            if (!get_option($title_prompt_key)) {
                update_option($title_prompt_key, str_replace('page', $post_type->name, $page_title_prompt));
            }
            if (!get_option($meta_prompt_key)) {
                update_option($meta_prompt_key, str_replace('page', $post_type->name, $page_meta_prompt));
            }
            if (!get_option($content_prompt_key)) {
                update_option($content_prompt_key, $page_content_prompt);
            }

            // Register settings for the custom post type
            register_setting('aico_content_settings', $settings_key);
            register_setting(
                'aico_content_settings',
                $title_prompt_key,
                array('type' => 'string', 'sanitize_callback' => array($this, 'allow_html_prompts'))
            );
            register_setting(
                'aico_content_settings',
                $meta_prompt_key,
                array('type' => 'string', 'sanitize_callback' => array($this, 'allow_html_prompts'))
            );
            register_setting(
                'aico_content_settings',
                $content_prompt_key,
                array('type' => 'string', 'sanitize_callback' => array($this, 'allow_html_prompts'))
            );

            // Add row actions and bulk actions for the custom post type
            add_filter($post_type->name . '_row_actions', array($this, 'add_row_actions'), 10, 2);
            add_filter('bulk_actions-edit-' . $post_type->name, array($this, 'register_bulk_actions'));
            add_filter('handle_bulk_actions-edit-' . $post_type->name, array($this, 'handle_bulk_actions'), 10, 3);
        }
    }

    /**
     * Render brand profile page
     */
    public function render_brand_profile_page() {
        $license_status = get_option('bmo_license_status', 'invalid');
        if ($license_status !== 'success') {
            echo '<div class="notice notice-error"><p>' . __('A valid license is required to use Bulk Meta Optimizer. Please enter your license key in Advanced Settings.', 'ai-content-optimizer') . '</p></div>';
            return;
        }

        // Get existing brand profile data
        $brand_profile = get_option('aico_brand_profile', array());
        $is_profile_generated = !empty($brand_profile);
        ?>
        <div class="wrap aico-wrap">
            <h1><?php _e('Brand Profile', 'ai-content-optimizer'); ?></h1>
            
            <?php if (!$is_profile_generated) : ?>
                <!-- Build Profile Section -->
                <div class="aico-card">
                    <h2><?php _e('Build My Profile', 'ai-content-optimizer'); ?></h2>
                    <p><?php _e('Generate a brand profile by analyzing your website homepage content using AI. This will create a comprehensive brand profile that includes your overview, target audience, tone, and unique selling points.', 'ai-content-optimizer'); ?></p>
                    
                    <div class="aico-brand-profile-options">
                        <button type="button" id="aico-build-profile" class="button button-primary button-large">
                            <span class="dashicons dashicons-admin-generic"></span>
                            <?php _e('Build My Profile', 'ai-content-optimizer'); ?>
                        </button>
                    </div>
                    
                    <div id="aico-build-profile-result" class="aico-result-area"></div>
                </div>
            <?php else : ?>
                <!-- Edit Profile Section -->
                <div class="aico-card">
                    <h2><?php _e('Edit Brand Profile', 'ai-content-optimizer'); ?></h2>
                    <p><?php _e('Review and edit your brand profile. You can modify any section to better reflect your brand.', 'ai-content-optimizer'); ?></p>
                    
                    <form id="aico-brand-profile-form" class="aico-brand-profile-form">
                        <?php wp_nonce_field('aico_save_brand_profile', 'aico_brand_profile_nonce'); ?>
                        
                        <div class="aico-profile-section">
                            <h3><?php _e('Brand Overview', 'ai-content-optimizer'); ?></h3>
                            <textarea name="brand_overview" rows="4" class="large-text" placeholder="<?php _e('Enter your brand overview...', 'ai-content-optimizer'); ?>"><?php echo esc_textarea($brand_profile['overview'] ?? ''); ?></textarea>
                        </div>
                        
                        <div class="aico-profile-section">
                            <h3><?php _e('Target Audience', 'ai-content-optimizer'); ?></h3>
                            <textarea name="target_audience" rows="3" class="large-text" placeholder="<?php _e('Describe your target audience...', 'ai-content-optimizer'); ?>"><?php echo esc_textarea($brand_profile['target_audience'] ?? ''); ?></textarea>
                        </div>
                        
                        <div class="aico-profile-section">
                            <h3><?php _e('Brand Tone', 'ai-content-optimizer'); ?></h3>
                            <textarea name="brand_tone" rows="3" class="large-text" placeholder="<?php _e('Describe the tone you want to use in your content...', 'ai-content-optimizer'); ?>"><?php echo esc_textarea($brand_profile['tone'] ?? ''); ?></textarea>
                        </div>
                        
                        <div class="aico-profile-section">
                            <h3><?php _e('What Sets Us Apart', 'ai-content-optimizer'); ?></h3>
                            <textarea name="unique_selling_points" rows="4" class="large-text" placeholder="<?php _e('Describe what makes your brand unique...', 'ai-content-optimizer'); ?>"><?php echo esc_textarea($brand_profile['unique_selling_points'] ?? ''); ?></textarea>
                        </div>
                        
                        <div class="aico-profile-actions">
                            <button type="submit" class="button button-primary">
                                <?php _e('Save Profile', 'ai-content-optimizer'); ?>
                            </button>
                            <button type="button" id="aico-rebuild-profile" class="button button-secondary">
                                <?php _e('Rebuild Profile', 'ai-content-optimizer'); ?>
                            </button>
                        </div>
                    </form>
                    
                    <div id="aico-save-profile-result" class="aico-result-area"></div>
                </div>
            <?php endif; ?>
        </div>
        <?php
    }

    /**
     * AJAX build brand profile
     */
    public function ajax_build_brand_profile() {
        $license_status = get_option('bmo_license_status', 'invalid');
        if ($license_status !== 'success') {
            wp_send_json_error(__('A valid license is required to use this feature.', 'ai-content-optimizer'));
            exit;
        }
        check_ajax_referer('aico-nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('You do not have permission to perform this action.', 'ai-content-optimizer'));
        }

        // Get API settings
        $api_key = get_option('aico_openai_api_key');
        $model = get_option('aico_openai_model', 'gpt-3.5-turbo');
        $temperature = get_option('aico_openai_temperature', 0.7);
        $max_tokens = get_option('aico_openai_max_tokens', 1000);

        if (empty($api_key)) {
            wp_send_json_error(__('OpenAI API key not configured.', 'ai-content-optimizer'));
        }

        try {
            // Get homepage content
            $homepage = get_page_by_path('home') ?: get_page_by_path('front-page') ?: get_option('page_on_front') ? get_post(get_option('page_on_front')) : null;
            
            if (!$homepage) {
                // Try to get the first page or post
                $homepage = get_posts(array('numberposts' => 1, 'post_type' => 'page', 'post_status' => 'publish'));
                $homepage = !empty($homepage) ? $homepage[0] : null;
            }

            if (!$homepage) {
                wp_send_json_error(__('Could not find homepage content to analyze.', 'ai-content-optimizer'));
            }

            // Extract content from homepage
            $content = wp_strip_all_tags($homepage->post_content);
            $title = $homepage->post_title;
            
            // Limit content length to avoid token limits
            $content = mb_substr($content, 0, 2000);

            // Create the prompt
            $prompt = "Please review my website homepage and put together a brand profile. It must include a short overview, who our target audience is, the tone we should use for meta data to attract new customers, what sets us apart.\n\n";
            $prompt .= "Website Title: {$title}\n";
            $prompt .= "Website Content: {$content}\n\n";
            $prompt .= "Please provide your response in the following JSON format:\n";
            $prompt .= "{\n";
            $prompt .= "  \"overview\": \"Brief brand overview\",\n";
            $prompt .= "  \"target_audience\": \"Description of target audience\",\n";
            $prompt .= "  \"tone\": \"Recommended tone for content\",\n";
            $prompt .= "  \"unique_selling_points\": \"What sets the brand apart\"\n";
            $prompt .= "}\n\n";
            $prompt .= "Make sure the response is valid JSON format.";

            // Call OpenAI API
            $response = $this->call_openai_api_direct($api_key, $model, $prompt, $max_tokens, $temperature);
            
            if (is_wp_error($response)) {
                wp_send_json_error($response->get_error_message());
            }

            // Try to parse JSON response
            $json_start = strpos($response, '{');
            $json_end = strrpos($response, '}');
            
            if ($json_start !== false && $json_end !== false) {
                $json_string = substr($response, $json_start, $json_end - $json_start + 1);
                $brand_profile = json_decode($json_string, true);
                
                if (json_last_error() === JSON_ERROR_NONE && is_array($brand_profile)) {
                    // Save the brand profile
                    update_option('aico_brand_profile', $brand_profile);
                    wp_send_json_success(array(
                        'message' => __('Brand profile generated successfully!', 'ai-content-optimizer'),
                        'profile' => $brand_profile
                    ));
                }
            }

            // If JSON parsing failed, try to extract sections manually
            $brand_profile = $this->extract_brand_profile_sections($response);
            if ($brand_profile) {
                update_option('aico_brand_profile', $brand_profile);
                wp_send_json_success(array(
                    'message' => __('Brand profile generated successfully!', 'ai-content-optimizer'),
                    'profile' => $brand_profile
                ));
            }

            wp_send_json_error(__('Could not parse the AI response into a valid brand profile.', 'ai-content-optimizer'));

        } catch (Exception $e) {
            wp_send_json_error(__('Error generating brand profile: ', 'ai-content-optimizer') . $e->getMessage());
        }
    }

    /**
     * AJAX save brand profile
     */
    public function ajax_save_brand_profile() {
        $license_status = get_option('bmo_license_status', 'invalid');
        if ($license_status !== 'success') {
            wp_send_json_error(__('A valid license is required to use this feature.', 'ai-content-optimizer'));
            exit;
        }
        check_ajax_referer('aico_save_brand_profile', 'aico_brand_profile_nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('You do not have permission to perform this action.', 'ai-content-optimizer'));
        }

        try {
            $brand_profile = array(
                'overview' => sanitize_textarea_field($_POST['brand_overview'] ?? ''),
                'target_audience' => sanitize_textarea_field($_POST['target_audience'] ?? ''),
                'tone' => sanitize_textarea_field($_POST['brand_tone'] ?? ''),
                'unique_selling_points' => sanitize_textarea_field($_POST['unique_selling_points'] ?? '')
            );

            update_option('aico_brand_profile', $brand_profile);
            wp_send_json_success(__('Brand profile saved successfully!', 'ai-content-optimizer'));

        } catch (Exception $e) {
            wp_send_json_error(__('Error saving brand profile: ', 'ai-content-optimizer') . $e->getMessage());
        }
    }

    /**
     * Extract brand profile sections from AI response
     */
    private function extract_brand_profile_sections($response) {
        $sections = array(
            'overview' => '',
            'target_audience' => '',
            'tone' => '',
            'unique_selling_points' => ''
        );

        // Try to extract sections based on common patterns
        $patterns = array(
            'overview' => array(
                '/overview[:\s]*([^.\n]+)/i',
                '/brand overview[:\s]*([^.\n]+)/i',
                '/about[:\s]*([^.\n]+)/i'
            ),
            'target_audience' => array(
                '/target audience[:\s]*([^.\n]+)/i',
                '/audience[:\s]*([^.\n]+)/i',
                '/who we serve[:\s]*([^.\n]+)/i'
            ),
            'tone' => array(
                '/tone[:\s]*([^.\n]+)/i',
                '/voice[:\s]*([^.\n]+)/i',
                '/style[:\s]*([^.\n]+)/i'
            ),
            'unique_selling_points' => array(
                '/sets us apart[:\s]*([^.\n]+)/i',
                '/unique[:\s]*([^.\n]+)/i',
                '/differentiators[:\s]*([^.\n]+)/i'
            )
        );

        foreach ($patterns as $key => $pattern_list) {
            foreach ($pattern_list as $pattern) {
                if (preg_match($pattern, $response, $matches)) {
                    $sections[$key] = trim($matches[1]);
                    break;
                }
            }
        }

        // If we found at least one section, return the profile
        if (!empty(array_filter($sections))) {
            return $sections;
        }

        return false;
    }

    /**
     * Render bulk process page
     */
    public function render_bulk_process_page() {
        $license_status = get_option('bmo_license_status', 'invalid');
        if ($license_status !== 'success') {
            echo '<div class="notice notice-error"><p>' . __('A valid license is required to use Bulk Meta Optimizer.', 'ai-content-optimizer') . '</p></div>';
            return;
        }

        $process_id = isset($_GET['process_id']) ? sanitize_text_field($_GET['process_id']) : '';
        $post_type = isset($_GET['post_type']) ? sanitize_text_field($_GET['post_type']) : 'post';

        if (empty($process_id)) {
            echo '<div class="notice notice-error"><p>' . __('No bulk process found.', 'ai-content-optimizer') . '</p></div>';
            return;
        }

        $process_data = get_transient('aico_bulk_process_' . $process_id);
        if (!$process_data) {
            echo '<div class="notice notice-error"><p>' . __('Bulk process not found or expired.', 'ai-content-optimizer') . '</p></div>';
            return;
        }

        $post_type_obj = get_post_type_object($post_type);
        $post_type_label = $post_type_obj ? $post_type_obj->labels->name : ucfirst($post_type);
        ?>
        <div class="wrap aico-wrap">
            <h1><?php printf(__('Bulk Optimize %s', 'ai-content-optimizer'), esc_html($post_type_label)); ?></h1>
            
            <div class="aico-card">
                <h2><?php _e('Processing Progress', 'ai-content-optimizer'); ?></h2>
                <p><?php printf(__('Optimizing %d %s items...', 'ai-content-optimizer'), $process_data['total'], strtolower($post_type_label)); ?></p>
                
                <div class="aico-bulk-progress-container">
                    <div class="aico-progress-bar">
                        <div class="aico-progress" id="aico-bulk-progress-bar" style="width: 0%"></div>
                    </div>
                    <div class="aico-progress-text" id="aico-bulk-progress-text">
                        <?php _e('Starting...', 'ai-content-optimizer'); ?>
                    </div>
                    <div class="aico-progress-details" id="aico-bulk-progress-details">
                        <span id="aico-processed-count">0</span> / <span id="aico-total-count"><?php echo esc_html($process_data['total']); ?></span>
                    </div>
                </div>
                
                <div class="aico-bulk-results" id="aico-bulk-results" style="display: none;">
                    <h3><?php _e('Results', 'ai-content-optimizer'); ?></h3>
                    <div class="aico-results-summary">
                        <div class="aico-success-count">
                            <span class="aico-count-label"><?php _e('Successfully Optimized:', 'ai-content-optimizer'); ?></span>
                            <span class="aico-count-value" id="aico-success-count">0</span>
                        </div>
                        <div class="aico-error-count">
                            <span class="aico-count-label"><?php _e('Errors:', 'ai-content-optimizer'); ?></span>
                            <span class="aico-count-value" id="aico-error-count">0</span>
                        </div>
                    </div>
                    <div class="aico-error-list" id="aico-error-list" style="display: none;">
                        <h4><?php _e('Error Details:', 'ai-content-optimizer'); ?></h4>
                        <ul id="aico-error-items"></ul>
                    </div>
                </div>
                
                <div class="aico-bulk-actions" id="aico-bulk-actions" style="display: none;">
                    <a href="<?php echo esc_url(admin_url('edit.php?post_type=' . $post_type)); ?>" class="button button-primary">
                        <?php printf(__('Back to %s', 'ai-content-optimizer'), esc_html($post_type_label)); ?>
                    </a>
                    <button type="button" id="aico-retry-failed" class="button button-secondary" style="display: none;">
                        <?php _e('Retry Failed Items', 'ai-content-optimizer'); ?>
                    </button>
                </div>
            </div>
        </div>

        <script type="text/javascript">
            jQuery(document).ready(function($) {
                var processId = '<?php echo esc_js($process_id); ?>';
                var postType = '<?php echo esc_js($post_type); ?>';
                var totalItems = <?php echo intval($process_data['total']); ?>;
                var processedItems = 0;
                var successCount = 0;
                var errorCount = 0;
                var errors = [];
                
                function updateProgress(processed, total, message) {
                    var percentage = total > 0 ? Math.round((processed / total) * 100) : 0;
                    $('#aico-bulk-progress-bar').css('width', percentage + '%');
                    $('#aico-bulk-progress-text').text(message);
                    $('#aico-processed-count').text(processed);
                }
                
                function processNextItem() {
                    if (processedItems >= totalItems) {
                        // All done
                        updateProgress(totalItems, totalItems, '<?php _e('Completed!', 'ai-content-optimizer'); ?>');
                        showResults();
                        return;
                    }
                    
                    var currentIndex = processedItems;
                    processedItems++;
                    
                    updateProgress(processedItems, totalItems, '<?php _e('Processing item', 'ai-content-optimizer'); ?> ' + processedItems + '...');
                    
                    $.ajax({
                        url: aicoData.ajaxUrl,
                        type: 'POST',
                        data: {
                            action: 'aico_bulk_optimize_item',
                            process_id: processId,
                            current_index: currentIndex,
                            nonce: aicoData.nonce
                        },
                        success: function(response) {
                            if (response.success) {
                                successCount++;
                                if (response.data.error) {
                                    errorCount++;
                                    errors.push({
                                        id: response.data.post_id,
                                        message: response.data.error
                                    });
                                }
                            } else {
                                errorCount++;
                                errors.push({
                                    id: 'unknown',
                                    message: response.data
                                });
                            }
                            
                            // Process next item after a short delay
                            setTimeout(processNextItem, 500);
                        },
                        error: function(xhr, status, error) {
                            errorCount++;
                            errors.push({
                                id: 'unknown',
                                message: error
                            });
                            
                            // Process next item after a short delay
                            setTimeout(processNextItem, 500);
                        }
                    });
                }
                
                function showResults() {
                    $('#aico-bulk-results').show();
                    $('#aico-bulk-actions').show();
                    $('#aico-success-count').text(successCount);
                    $('#aico-error-count').text(errorCount);
                    
                    if (errors.length > 0) {
                        $('#aico-error-list').show();
                        var errorHtml = '';
                        errors.forEach(function(error) {
                            errorHtml += '<li><strong>ID ' + error.id + ':</strong> ' + error.message + '</li>';
                        });
                        $('#aico-error-items').html(errorHtml);
                        $('#aico-retry-failed').show();
                    }
                }
                
                // Start processing
                processNextItem();
                
                // Retry failed items
                $('#aico-retry-failed').on('click', function() {
                    if (errors.length > 0) {
                        // Reset counters
                        processedItems = 0;
                        successCount = 0;
                        errorCount = 0;
                        errors = [];
                        
                        // Hide results and show progress
                        $('#aico-bulk-results').hide();
                        $('#aico-bulk-actions').hide();
                        
                        // Start processing again
                        processNextItem();
                    }
                });
            });
        </script>
        <?php
    }

    /**
     * AJAX bulk optimize item
     */
    public function ajax_bulk_optimize_item() {
        $license_status = get_option('bmo_license_status', 'invalid');
        if ($license_status !== 'success') {
            wp_send_json_error(__('A valid license is required to use this feature.', 'ai-content-optimizer'));
            exit;
        }
        check_ajax_referer('aico-nonce', 'nonce');

        if (!current_user_can('edit_posts')) {
            wp_send_json_error(__('You do not have permission to perform this action.', 'ai-content-optimizer'));
        }

        $process_id = isset($_POST['process_id']) ? sanitize_text_field($_POST['process_id']) : '';
        $current_index = isset($_POST['current_index']) ? intval($_POST['current_index']) : 0;

        if (empty($process_id)) {
            wp_send_json_error(__('Invalid process ID.', 'ai-content-optimizer'));
        }

        $process_data = get_transient('aico_bulk_process_' . $process_id);
        if (!$process_data) {
            wp_send_json_error(__('Bulk process not found or expired.', 'ai-content-optimizer'));
        }

        if ($current_index >= count($process_data['post_ids'])) {
            wp_send_json_error(__('Invalid index.', 'ai-content-optimizer'));
        }

        $post_id = $process_data['post_ids'][$current_index];
        
        try {
            $result = $this->optimize_post($post_id);
            
            if (is_wp_error($result)) {
                wp_send_json_success(array(
                    'post_id' => $post_id,
                    'error' => $result->get_error_message()
                ));
            } else {
                wp_send_json_success(array(
                    'post_id' => $post_id,
                    'success' => true
                ));
            }
        } catch (Exception $e) {
            wp_send_json_success(array(
                'post_id' => $post_id,
                'error' => $e->getMessage()
            ));
        }
    }

    /**
     * Handle bulk action errors
     */
    public function handle_bulk_action_errors() {
        if (isset($_GET['aico_bulk_error'])) {
            $error = sanitize_text_field($_GET['aico_bulk_error']);
            $message = '';
            $type = 'error';
            
            switch ($error) {
                case 'license_required':
                    $message = __('A valid license is required to use bulk optimization.', 'ai-content-optimizer');
                    break;
                case 'permission_denied':
                    $message = __('You do not have permission to perform bulk optimization.', 'ai-content-optimizer');
                    break;
                case 'processing_error':
                    $message = __('An error occurred while processing the bulk operation.', 'ai-content-optimizer');
                    break;
                default:
                    $message = __('An unknown error occurred.', 'ai-content-optimizer');
            }
            
            if ($message) {
                echo '<div class="notice notice-' . $type . ' is-dismissible"><p>' . esc_html($message) . '</p></div>';
            }
        }
    }
}

// Handle license key save with SLM check
add_action('admin_post_bmo_save_license_key', function() {
    if ( ! current_user_can('manage_options') ) {
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

    error_log(__METHOD__ . ' → SLM payload: ' . print_r($body, true));

    $response = wp_remote_post(BMO_SLM_SERVER, [
        'body' => $body,
        'timeout' => 15,
        'sslverify' => true,
    ]);

    $data = is_wp_error($response)
          ? ['result' => 'error', 'message' => $response->get_error_message()]
          : json_decode(wp_remote_retrieve_body($response), true);
    
    error_log(__METHOD__ . ' SLM response for activate: ' . print_r($data, true));

    if (!empty($data['result']) && $data['result'] === 'error') {
        if (stripos($data['message'], 'maximum allowable domains') !== false) {
            // a license‐limit violation
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

// License status check function
function bmo_check_license_status() {
    $key = get_option('bmo_license_key', '');
    if (empty($key)) {
        update_option('bmo_license_status', 'invalid');
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

    error_log(__METHOD__ . ' → SLM payload: ' . print_r($body, true));

    $response = wp_remote_post(BMO_SLM_SERVER, [
        'body' => $body,
        'timeout' => 10,
        'sslverify' => true,
    ]);

    $data = json_decode(wp_remote_retrieve_body($response), true);
    if (!empty($data['result'])) {
        update_option('bmo_license_status', $data['result']);
    }
    error_log(__FUNCTION__ . ' SLM response for check: ' . print_r($data, true));
}
add_action('admin_init','bmo_check_license_status');

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

    error_log(__METHOD__ . ' → SLM payload: ' . print_r($body, true));

    wp_remote_post(BMO_SLM_SERVER, [
        'body' => $body,
        'timeout' => 10,
        'sslverify' => true,
    ]);
}
register_deactivation_hook(__FILE__, 'bmo_deactivate_license');

// Schedule daily license check
if ( ! wp_next_scheduled('bmo_daily_license_check') ) {
    wp_schedule_event( time(), 'daily', 'bmo_daily_license_check' );
}
add_action('bmo_daily_license_check','bmo_check_license_status');

// Gate plugin functionality on license status
add_action('plugins_loaded','bmo_maybe_disable_plugin', 5);
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
    add_action('admin_notices', function() use($msg){
        echo "<div class='notice notice-error'><p>{$msg}</p></div>";
    });
    
    // Don't completely stop the plugin - let it load so users can access settings
    // The core functionality is already gated in the init() method
}
