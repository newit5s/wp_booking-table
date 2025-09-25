<?php
/**
 * Plugin Name: Restaurant Booking Manager
 * Plugin URI: https://github.com/newit5s/wp_booking-table
 * Description: Plugin WordPress quản lý đặt bàn nhà hàng hoàn chỉnh với giao diện thân thiện người dùng và quản lý admin chuyên nghiệp.
 * Version: 1.0.0
 * Author: NewIT5s
 * License: GPL v2 or later
 * License URI: http://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: restaurant-booking-manager
 * Domain Path: /languages
 * Requires at least: 5.0
 * Tested up to: 6.4
 * Requires PHP: 7.4
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Define plugin constants
define( 'RBM_VERSION', '1.0.0' );
define( 'RBM_PLUGIN_FILE', __FILE__ );
define( 'RBM_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'RBM_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'RBM_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );

/**
 * Main Plugin Class
 */
class Restaurant_Booking_Manager {
    
    /**
     * Plugin instance
     */
    private static $instance = null;
    
    /**
     * Get plugin instance
     */
    public static function get_instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Constructor
     */
    private function __construct() {
        add_action( 'init', array( $this, 'init' ) );
        register_activation_hook( __FILE__, array( $this, 'activate' ) );
        register_deactivation_hook( __FILE__, array( $this, 'deactivate' ) );
    }
    
    /**
     * Initialize plugin
     */
    public function init() {
        // Load text domain for translations
        load_plugin_textdomain( 'restaurant-booking-manager', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );
        
        // Load required files
        $this->load_dependencies();
        
        // Initialize components
        $this->init_components();
    }
    
    /**
     * Load plugin dependencies
     */
    private function load_dependencies() {
        // Include required files
        if ( file_exists( RBM_PLUGIN_DIR . 'includes/class-database.php' ) ) {
            require_once RBM_PLUGIN_DIR . 'includes/class-database.php';
        }
        
        if ( file_exists( RBM_PLUGIN_DIR . 'includes/class-booking.php' ) ) {
            require_once RBM_PLUGIN_DIR . 'includes/class-booking.php';
        }
        
        if ( file_exists( RBM_PLUGIN_DIR . 'includes/class-ajax.php' ) ) {
            require_once RBM_PLUGIN_DIR . 'includes/class-ajax.php';
        }
        
        if ( file_exists( RBM_PLUGIN_DIR . 'includes/class-email.php' ) ) {
            require_once RBM_PLUGIN_DIR . 'includes/class-email.php';
        }
        
        // Admin files
        if ( is_admin() && file_exists( RBM_PLUGIN_DIR . 'admin/class-admin.php' ) ) {
            require_once RBM_PLUGIN_DIR . 'admin/class-admin.php';
        }
        
        // Frontend files
        if ( ! is_admin() && file_exists( RBM_PLUGIN_DIR . 'public/class-frontend.php' ) ) {
            require_once RBM_PLUGIN_DIR . 'public/class-frontend.php';
        }
    }
    
    /**
     * Initialize plugin components
     */
    private function init_components() {
        // Initialize database
        if ( class_exists( 'RBM_Database' ) ) {
            new RBM_Database();
        }
        
        // Initialize AJAX handlers
        if ( class_exists( 'RBM_Ajax' ) ) {
            new RBM_Ajax();
        }
        
        // Initialize admin
        if ( is_admin() && class_exists( 'RBM_Admin' ) ) {
            new RBM_Admin();
        }
        
        // Initialize frontend
        if ( ! is_admin() && class_exists( 'RBM_Frontend' ) ) {
            new RBM_Frontend();
        }
    }
    
    /**
     * Plugin activation
     */
    public function activate() {
        // Create database tables
        if ( class_exists( 'RBM_Database' ) ) {
            $database = new RBM_Database();
            if ( method_exists( $database, 'create_tables' ) ) {
                $database->create_tables();
            }
        }
        
        // Set default options
        $default_options = array(
            'max_tables' => 20,
            'booking_duration' => 120, // minutes
            'advance_booking_days' => 30,
            'opening_time' => '10:00',
            'closing_time' => '22:00',
            'admin_email' => get_option( 'admin_email' ),
            'confirmation_email' => true,
            'reminder_email' => true,
        );
        
        add_option( 'rbm_settings', $default_options );
        
        // Flush rewrite rules
        flush_rewrite_rules();
    }
    
    /**
     * Plugin deactivation
     */
    public function deactivate() {
        // Clean up temporary data
        wp_clear_scheduled_hook( 'rbm_cleanup_expired_bookings' );
        
        // Flush rewrite rules
        flush_rewrite_rules();
    }
}

/**
 * Initialize the plugin
 */
function rbm_init() {
    return Restaurant_Booking_Manager::get_instance();
}

// Start the plugin
rbm_init();

/**
 * Helper function to get plugin instance
 */
function rbm() {
    return Restaurant_Booking_Manager::get_instance();
}

/**
 * Check if plugin can be activated
 */
function rbm_check_requirements() {
    $php_version = phpversion();
    $wp_version = get_bloginfo( 'version' );
    
    $errors = array();
    
    // Check PHP version
    if ( version_compare( $php_version, '7.4', '<' ) ) {
        $errors[] = sprintf( 
            __( 'Restaurant Booking Manager requires PHP version 7.4 or higher. You are running version %s.', 'restaurant-booking-manager' ), 
            $php_version 
        );
    }
    
    // Check WordPress version
    if ( version_compare( $wp_version, '5.0', '<' ) ) {
        $errors[] = sprintf( 
            __( 'Restaurant Booking Manager requires WordPress version 5.0 or higher. You are running version %s.', 'restaurant-booking-manager' ), 
            $wp_version 
        );
    }
    
    // Check for required PHP extensions
    if ( ! extension_loaded( 'mysqli' ) ) {
        $errors[] = __( 'Restaurant Booking Manager requires the MySQLi PHP extension.', 'restaurant-booking-manager' );
    }
    
    if ( ! empty( $errors ) ) {
        deactivate_plugins( plugin_basename( __FILE__ ) );
        wp_die( 
            '<h1>' . __( 'Plugin Activation Error', 'restaurant-booking-manager' ) . '</h1>' .
            '<p>' . implode( '</p><p>', $errors ) . '</p>' .
            '<p><a href="' . admin_url( 'plugins.php' ) . '">' . __( 'Return to Plugins', 'restaurant-booking-manager' ) . '</a></p>'
        );
    }
}

// Run requirements check on activation
register_activation_hook( __FILE__, 'rbm_check_requirements' );

/**
 * Add settings link to plugin actions
 */
function rbm_plugin_action_links( $links ) {
    $settings_link = '<a href="' . admin_url( 'admin.php?page=restaurant-booking-settings' ) . '">' . __( 'Settings', 'restaurant-booking-manager' ) . '</a>';
    array_unshift( $links, $settings_link );
    return $links;
}
add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), 'rbm_plugin_action_links' );

/**
 * Plugin uninstall hook
 */
register_uninstall_hook( __FILE__, 'rbm_uninstall' );

function rbm_uninstall() {
    // Remove plugin options
    delete_option( 'rbm_settings' );
    
    // Remove database tables (optional - comment out if you want to keep data)
    // global $wpdb;
    // $wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}rb_bookings" );
    // $wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}rb_tables" );
    // $wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}rb_availability" );
}
