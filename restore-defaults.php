<?php
// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Registers AJAX handler for resetting defaults.
 */
add_action( 'wp_ajax_aico_reset_defaults', 'aico_handle_reset_defaults' );

/**
 * Reset all settings & prompts for a given post type back to defaults.
 */
function aico_handle_reset_defaults() {
    // Check nonce:
    if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( $_POST['nonce'] ), 'aico-nonce' ) ) {
        wp_send_json_error( __( 'Invalid nonce.', 'ai-content-optimizer' ), 400 );
    }

    // Check capability:
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_send_json_error( __( 'Insufficient permissions.', 'ai-content-optimizer' ), 403 );
    }

    // Validate post_type:
    if ( empty( $_POST['post_type'] ) ) {
        wp_send_json_error( __( 'Missing post type.', 'ai-content-optimizer' ), 400 );
    }
    $post_type = sanitize_text_field( $_POST['post_type'] );
    
    // Get all public post types
    $valid_post_types = get_post_types(array('public' => true));
    unset($valid_post_types['attachment']);
    
    if (!in_array($post_type, $valid_post_types)) {
        wp_send_json_error( __( 'Invalid post type.', 'ai-content-optimizer' ), 400 );
    }

    // 1) Rebuild default toggles & style options exactly as in main plugin:
    $default_content_settings = array(
        'content_tone'         => 'professional',
        'target_audience'      => 'general',
        'content_focus'        => 'benefit-focused',
        'seo_aggressiveness'   => 'moderate',
        'keyword_density'      => 'standard',
        'geographic_targeting' => 'global',
        'brand_voice'          => 'trustworthy',
        'title_separator'      => 'dash',
        'excluded_words'       => '',
    );
    $default_toggles = array(
        'optimize_title'   => true,
        'optimize_meta'    => true,
        'optimize_content' => false,
        'optimize_slug'    => false,
        'preserve_html'    => true,
    );
    $combined_defaults = array_merge( $default_content_settings, $default_toggles );

    // 2) Reset settings for the specified post type
    update_option( 'aico_' . $post_type . '_settings', $combined_defaults );

    // 3) Reset prompts for the specified post type
    if ($post_type === 'page' || $post_type === 'post' || $post_type === 'product') {
        // Use standard defaults for built-in post types
        $default_title_prompt = __( 'Write an SEO-optimized title for this {post_type} about {title}. Make it engaging and include the main keyword naturally. Keep it under 60 characters.', 'ai-content-optimizer' );
        $default_meta_prompt = __( 'Write a compelling meta description for this {post_type} about {title}. Include the main keyword and a clear call-to-action. Keep it between 150-160 characters.', 'ai-content-optimizer' );
        $default_content_prompt = __( 'Optimize this content while preserving its structure and meaning. Maintain all HTML tags, shortcodes, and formatting. Focus on improving readability and SEO without changing the core message.', 'ai-content-optimizer' );

        update_option( 'aico_' . $post_type . '_title_prompt', str_replace('{post_type}', $post_type, $default_title_prompt) );
        update_option( 'aico_' . $post_type . '_meta_prompt', str_replace('{post_type}', $post_type, $default_meta_prompt) );
        update_option( 'aico_' . $post_type . '_content_prompt', $default_content_prompt );
    } else {
        // Use page prompts as defaults for custom post types
        $page_title_prompt = get_option('aico_page_title_prompt', '');
        $page_meta_prompt = get_option('aico_page_meta_prompt', '');
        $page_content_prompt = get_option('aico_page_content_prompt', '');

        update_option( 'aico_' . $post_type . '_title_prompt', str_replace('page', $post_type, $page_title_prompt) );
        update_option( 'aico_' . $post_type . '_meta_prompt', str_replace('page', $post_type, $page_meta_prompt) );
        update_option( 'aico_' . $post_type . '_content_prompt', $page_content_prompt );
    }

    wp_send_json_success( sprintf( 
        __( 'Successfully reset all settings and prompts for %s to defaults.', 'ai-content-optimizer' ),
        $post_type
    ) );
}
