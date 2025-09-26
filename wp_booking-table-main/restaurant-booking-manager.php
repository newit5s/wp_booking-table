<?php
/**
 * Plugin Name: Restaurant Booking Manager
 * Plugin URI: https://github.com/newit5s/wp_booking-table
 * Description: Plugin quản lý đặt bàn nhà hàng hoàn chỉnh với giao diện thân thiện
 * Version: 1.0.0
 * Author: NewIT5S
 * Author URI: https://github.com/newit5s
 * License: GPL v2 or later
 * Text Domain: restaurant-booking
 * Domain Path: /languages
 */

// Ngăn truy cập trực tiếp
if (!defined('ABSPATH')) {
    exit;
}

// Define constants
define('RB_VERSION', '1.0.0');
define('RB_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('RB_PLUGIN_URL', plugin_dir_url(__FILE__));
define('RB_PLUGIN_BASENAME', plugin_basename(__FILE__));

/**
 * Activation Hook - Tạo database tables
 */
register_activation_hook(__FILE__, 'rb_activate_plugin');
function rb_activate_plugin() {
    // Load database class và tạo tables
    require_once RB_PLUGIN_DIR . 'includes/class-database.php';
    $database = new RB_Database();
    $database->create_tables();
    
    // Set default options
    add_option('rb_settings', array(
        'max_tables' => 20,
        'opening_time' => '09:00',
        'closing_time' => '22:00',
        'time_slot_interval' => 30,
        'admin_email' => get_option('admin_email'),
        'enable_email' => 'yes'
    ));
    
    // Flush rewrite rules
    flush_rewrite_rules();
}

/**
 * Deactivation Hook
 */
register_deactivation_hook(__FILE__, 'rb_deactivate_plugin');
function rb_deactivate_plugin() {
    flush_rewrite_rules();
}

/**
 * Load plugin textdomain
 */
add_action('plugins_loaded', 'rb_load_textdomain');
function rb_load_textdomain() {
    load_plugin_textdomain('restaurant-booking', false, dirname(RB_PLUGIN_BASENAME) . '/languages');
}

/**
 * Initialize Plugin
 */
add_action('plugins_loaded', 'rb_init_plugin');
function rb_init_plugin() {
    // Load required files
    require_once RB_PLUGIN_DIR . 'includes/class-database.php';
    require_once RB_PLUGIN_DIR . 'includes/class-booking.php';
    require_once RB_PLUGIN_DIR . 'includes/class-ajax.php';
    require_once RB_PLUGIN_DIR . 'includes/class-email.php';
    
    // Initialize Database
    global $rb_database;
    $rb_database = new RB_Database();
    
    // Initialize Booking Handler
    global $rb_booking;
    $rb_booking = new RB_Booking();
    
    // Initialize AJAX handlers
    new RB_Ajax();
    
    // Initialize Email handler
    global $rb_email;
    $rb_email = new RB_Email();
    
    // Load Admin area
    if (is_admin()) {
        require_once RB_PLUGIN_DIR . 'admin/class-admin.php';
        new RB_Admin();
    }
    
    // Load Frontend
    if (!is_admin()) {
        require_once RB_PLUGIN_DIR . 'public/class-frontend.php';
        new RB_Frontend();
    }
}

/**
 * Enqueue admin scripts and styles
 */
add_action('admin_enqueue_scripts', 'rb_admin_enqueue_scripts');
function rb_admin_enqueue_scripts($hook) {
    // Only load on our plugin pages
    if (strpos($hook, 'restaurant-booking') !== false || strpos($hook, 'rb-') !== false) {
        wp_enqueue_style('rb-admin-css', RB_PLUGIN_URL . 'assets/css/admin.css', array(), RB_VERSION);
        wp_enqueue_script('rb-admin-js', RB_PLUGIN_URL . 'assets/js/admin.js', array('jquery'), RB_VERSION, true);
        
        // Localize script
        wp_localize_script('rb-admin-js', 'rb_ajax', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('rb_admin_nonce')
        ));
    }
}

/**
 * Enqueue frontend scripts and styles
 */
add_action('wp_enqueue_scripts', 'rb_frontend_enqueue_scripts');
function rb_frontend_enqueue_scripts() {
    wp_enqueue_style('rb-frontend-css', RB_PLUGIN_URL . 'assets/css/frontend.css', array(), RB_VERSION);
    wp_enqueue_script('rb-frontend-js', RB_PLUGIN_URL . 'assets/js/frontend.js', array('jquery'), RB_VERSION, true);
    
    // Localize script for AJAX
    wp_localize_script('rb-frontend-js', 'rb_ajax', array(
        'ajax_url' => admin_url('admin-ajax.php'),
        'nonce' => wp_create_nonce('rb_frontend_nonce'),
        'loading_text' => __('Đang xử lý...', 'restaurant-booking'),
        'error_text' => __('Có lỗi xảy ra. Vui lòng thử lại.', 'restaurant-booking')
    ));
}

/**
 * Register shortcode
 */
add_shortcode('restaurant_booking', 'rb_booking_shortcode');
function rb_booking_shortcode($atts) {
    // Load frontend class if not loaded
    if (!class_exists('RB_Frontend')) {
        require_once RB_PLUGIN_DIR . 'public/class-frontend.php';
    }
    
    $frontend = new RB_Frontend();
    return $frontend->render_booking_form($atts);
}

/**
 * Add plugin action links
 */
add_filter('plugin_action_links_' . RB_PLUGIN_BASENAME, 'rb_plugin_action_links');
function rb_plugin_action_links($links) {
    $settings_link = '<a href="' . admin_url('admin.php?page=rb-settings') . '">' . __('Cài đặt', 'restaurant-booking') . '</a>';
    array_unshift($links, $settings_link);
    return $links;
}

/**
 * Check plugin dependencies
 */
add_action('admin_notices', 'rb_check_dependencies');
function rb_check_dependencies() {
    // Check PHP version
    if (version_compare(PHP_VERSION, '7.0', '<')) {
        ?>
        <div class="notice notice-error">
            <p><?php _e('Restaurant Booking Manager yêu cầu PHP version 7.0 trở lên.', 'restaurant-booking'); ?></p>
        </div>
        <?php
    }
    
    // Check if tables exist
    global $wpdb;
    $table_name = $wpdb->prefix . 'rb_bookings';
    if ($wpdb->get_var("SHOW TABLES LIKE '$table_name'") != $table_name) {
        ?>
        <div class="notice notice-warning is-dismissible">
            <p><?php _e('Restaurant Booking Manager: Database tables chưa được tạo. Vui lòng deactivate và activate lại plugin.', 'restaurant-booking'); ?></p>
        </div>
        <?php
    }
}