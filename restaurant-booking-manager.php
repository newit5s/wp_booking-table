<?php
/**
 * Plugin Name: Restaurant Booking Manager
 * Plugin URI: https://yourwebsite.com/restaurant-booking
 * Description: Plugin quản lý đặt bàn nhà hàng với chức năng đặt bàn online và quản lý admin
 * Version: 1.0.0
 * Author: Your Name
 * License: GPL v2 or later
 * Text Domain: restaurant-booking
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('RB_PLUGIN_URL', plugin_dir_url(__FILE__));
define('RB_PLUGIN_PATH', plugin_dir_path(__FILE__));
define('RB_PLUGIN_VERSION', '1.0.0');

// Main plugin class
class RestaurantBookingManager {
    
    public function __construct() {
        add_action('init', array($this, 'init'));
        register_activation_hook(__FILE__, array($this, 'activate'));
        register_deactivation_hook(__FILE__, array($this, 'deactivate'));
    }
    
    public function init() {
        // Load includes
        $this->load_includes();
        
        // Load admin
        if (is_admin()) {
            $this->load_admin();
        }
        
        // Load frontend
        $this->load_frontend();
        
        // Load assets
        add_action('wp_enqueue_scripts', array($this, 'enqueue_frontend_assets'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_assets'));
    }
    
    private function load_includes() {
        require_once RB_PLUGIN_PATH . 'includes/class-database.php';
        require_once RB_PLUGIN_PATH . 'includes/class-booking.php';
        require_once RB_PLUGIN_PATH . 'includes/class-email.php';
        require_once RB_PLUGIN_PATH . 'includes/class-ajax.php';
        
        // Initialize classes
        new RB_Database();
        new RB_Booking();
        new RB_Email();
        new RB_Ajax();
    }
    
    private function load_admin() {
        require_once RB_PLUGIN_PATH . 'admin/class-admin.php';
        new RB_Admin();
    }
    
    private function load_frontend() {
        require_once RB_PLUGIN_PATH . 'public/class-frontend.php';
        new RB_Frontend();
    }
    
    public function enqueue_frontend_assets() {
        wp_enqueue_style('rb-frontend-style', RB_PLUGIN_URL . 'assets/css/frontend.css', array(), RB_PLUGIN_VERSION);
        wp_enqueue_script('rb-frontend-script', RB_PLUGIN_URL . 'assets/js/frontend.js', array('jquery'), RB_PLUGIN_VERSION, true);
        
        // Localize script for AJAX
        wp_localize_script('rb-frontend-script', 'rb_ajax', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('rb_booking_nonce'),
            'messages' => array(
                'success' => __('Đặt bàn thành công! Chúng tôi sẽ xác nhận qua email.', 'restaurant-booking'),
                'error' => __('Có lỗi xảy ra. Vui lòng thử lại.', 'restaurant-booking')
            )
        ));
    }
    
    public function enqueue_admin_assets() {
        wp_enqueue_style('rb-admin-style', RB_PLUGIN_URL . 'assets/css/admin.css', array(), RB_PLUGIN_VERSION);
        wp_enqueue_script('rb-admin-script', RB_PLUGIN_URL . 'assets/js/admin.js', array('jquery'), RB_PLUGIN_VERSION, true);
        
        wp_localize_script('rb-admin-script', 'rb_admin_ajax', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('rb_admin_nonce')
        ));
    }
    
    public function activate() {
        // Create database tables
        RB_Database::create_tables();
        
        // Set default options
        add_option('rb_max_tables', 20);
        add_option('rb_opening_hours', array(
            'start' => '09:00',
            'end' => '22:00'
        ));
        add_option('rb_booking_duration', 120); // 2 hours default
        
        // Flush rewrite rules
        flush_rewrite_rules();
    }
    
    public function deactivate() {
        // Flush rewrite rules
        flush_rewrite_rules();
    }
}

// Initialize the plugin
new RestaurantBookingManager();
