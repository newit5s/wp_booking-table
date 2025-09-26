<?php
/**
 * Database Class - Tạo và quản lý database tables
 */

if (!defined('ABSPATH')) {
    exit;
}

class RB_Database {
    
    private $wpdb;
    private $charset_collate;
    
    /**
     * Constructor
     */
    public function __construct() {
        global $wpdb;
        $this->wpdb = $wpdb;
        $this->charset_collate = $wpdb->get_charset_collate();
    }
    
    /**
     * Create all database tables
     */
    public function create_tables() {
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        
        $this->create_bookings_table();
        $this->create_tables_table();
        $this->create_availability_table();
        $this->insert_default_tables();
    }
    
    /**
     * Create bookings table
     */
    private function create_bookings_table() {
        $table_name = $this->wpdb->prefix . 'rb_bookings';
        
        $sql = "CREATE TABLE IF NOT EXISTS $table_name (
            id int(11) NOT NULL AUTO_INCREMENT,
            customer_name varchar(100) NOT NULL,
            customer_phone varchar(20) NOT NULL,
            customer_email varchar(100) NOT NULL,
            guest_count int(11) NOT NULL,
            booking_date date NOT NULL,
            booking_time time NOT NULL,
            table_number int(11) DEFAULT NULL,
            status varchar(20) DEFAULT 'pending',
            special_requests text DEFAULT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            confirmed_at datetime DEFAULT NULL,
            PRIMARY KEY (id),
            KEY booking_date (booking_date),
            KEY status (status)
        ) $this->charset_collate;";
        
        dbDelta($sql);
    }
    
    /**
     * Create tables table
     */
    private function create_tables_table() {
        $table_name = $this->wpdb->prefix . 'rb_tables';
        
        $sql = "CREATE TABLE IF NOT EXISTS $table_name (
            id int(11) NOT NULL AUTO_INCREMENT,
            table_number int(11) NOT NULL,
            capacity int(11) NOT NULL,
            is_available tinyint(1) DEFAULT 1,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY table_number (table_number)
        ) $this->charset_collate;";
        
        dbDelta($sql);
    }
    
    /**
     * Create availability table
     */
    private function create_availability_table() {
        $table_name = $this->wpdb->prefix . 'rb_availability';
        
        $sql = "CREATE TABLE IF NOT EXISTS $table_name (
            id int(11) NOT NULL AUTO_INCREMENT,
            table_id int(11) NOT NULL,
            booking_date date NOT NULL,
            booking_time time NOT NULL,
            is_occupied tinyint(1) DEFAULT 0,
            booking_id int(11) DEFAULT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY table_date_time (table_id, booking_date, booking_time)
        ) $this->charset_collate;";
        
        dbDelta($sql);
    }
    
    /**
     * Insert default tables
     */
    private function insert_default_tables() {
        $table_name = $this->wpdb->prefix . 'rb_tables';
        
        // Check if tables already exist
        $count = $this->wpdb->get_var("SELECT COUNT(*) FROM $table_name");
        
        if ($count == 0) {
            // Insert 10 default tables
            for ($i = 1; $i <= 10; $i++) {
                $this->wpdb->insert(
                    $table_name,
                    array(
                        'table_number' => $i,
                        'capacity' => ($i <= 4) ? 2 : (($i <= 8) ? 4 : 6),
                        'is_available' => 1,
                        'created_at' => current_time('mysql')
                    ),
                    array('%d', '%d', '%d', '%s')
                );
            }
        }
    }
    
    /**
     * Drop all tables (for uninstall)
     */
    public function drop_tables() {
        $tables = array(
            $this->wpdb->prefix . 'rb_bookings',
            $this->wpdb->prefix . 'rb_tables',
            $this->wpdb->prefix . 'rb_availability'
        );
        
        foreach ($tables as $table) {
            $this->wpdb->query("DROP TABLE IF EXISTS $table");
        }
    }
}