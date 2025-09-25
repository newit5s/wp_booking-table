<?php
/**
 * Database management class
 */

if (!defined('ABSPATH')) {
    exit;
}

class RB_Database {
    
    public function __construct() {
        // Constructor
    }
    
    public static function create_tables() {
        global $wpdb;
        
        $charset_collate = $wpdb->get_charset_collate();
        
        // Bookings table
        $table_bookings = $wpdb->prefix . 'restaurant_bookings';
        
        $sql_bookings = "CREATE TABLE $table_bookings (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
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
        ) $charset_collate;";
        
        // Table management
        $table_management = $wpdb->prefix . 'restaurant_tables';
        
        $sql_tables = "CREATE TABLE $table_management (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            table_number int(11) NOT NULL,
            capacity int(11) NOT NULL,
            is_available tinyint(1) DEFAULT 1,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY table_number (table_number)
        ) $charset_collate;";
        
        // Table availability by date/time
        $table_availability = $wpdb->prefix . 'restaurant_table_availability';
        
        $sql_availability = "CREATE TABLE $table_availability (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            table_id int(11) NOT NULL,
            booking_date date NOT NULL,
            booking_time time NOT NULL,
            is_occupied tinyint(1) DEFAULT 0,
            booking_id int(11) DEFAULT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY table_date_time (table_id, booking_date, booking_time),
            KEY booking_id (booking_id)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql_bookings);
        dbDelta($sql_tables);
        dbDelta($sql_availability);
        
        // Insert default tables if none exist
        self::insert_default_tables();
    }
    
    private static function insert_default_tables() {
        global $wpdb;
        
        $table_management = $wpdb->prefix . 'restaurant_tables';
        
        // Check if tables already exist
        $existing_tables = $wpdb->get_var("SELECT COUNT(*) FROM $table_management");
        
        if ($existing_tables == 0) {
            // Insert default tables
            $default_tables = array(
                array('table_number' => 1, 'capacity' => 2),
                array('table_number' => 2, 'capacity' => 2),
                array('table_number' => 3, 'capacity' => 4),
                array('table_number' => 4, 'capacity' => 4),
                array('table_number' => 5, 'capacity' => 4),
                array('table_number' => 6, 'capacity' => 6),
                array('table_number' => 7, 'capacity' => 6),
                array('table_number' => 8, 'capacity' => 8),
            );
            
            foreach ($default_tables as $table) {
                $wpdb->insert(
                    $table_management,
                    $table,
                    array('%d', '%d')
                );
            }
        }
    }
    
    public static function get_available_tables($date, $time, $guest_count) {
        global $wpdb;
        
        $table_management = $wpdb->prefix . 'restaurant_tables';
        $table_availability = $wpdb->prefix . 'restaurant_table_availability';
        
        // Get tables that can accommodate the guest count
        $sql = "SELECT t.* FROM $table_management t 
                WHERE t.capacity >= %d 
                AND t.is_available = 1
                AND t.id NOT IN (
                    SELECT ta.table_id FROM $table_availability ta 
                    WHERE ta.booking_date = %s 
                    AND ta.booking_time = %s 
                    AND ta.is_occupied = 1
                )
                ORDER BY t.capacity ASC";
        
        return $wpdb->get_results($wpdb->prepare($sql, $guest_count, $date, $time));
    }
    
    public static function create_booking($data) {
        global $wpdb;
        
        $table_bookings = $wpdb->prefix . 'restaurant_bookings';
        
        $result = $wpdb->insert(
            $table_bookings,
            array(
                'customer_name' => sanitize_text_field($data['customer_name']),
                'customer_phone' => sanitize_text_field($data['customer_phone']),
                'customer_email' => sanitize_email($data['customer_email']),
                'guest_count' => intval($data['guest_count']),
                'booking_date' => sanitize_text_field($data['booking_date']),
                'booking_time' => sanitize_text_field($data['booking_time']),
                'special_requests' => sanitize_textarea_field($data['special_requests']),
                'status' => 'pending'
            ),
            array('%s', '%s', '%s', '%d', '%s', '%s', '%s', '%s')
        );
        
        if ($result !== false) {
            return $wpdb->insert_id;
        }
        
        return false;
    }
    
    public static function get_bookings($status = 'all', $limit = 50) {
        global $wpdb;
        
        $table_bookings = $wpdb->prefix . 'restaurant_bookings';
        
        $sql = "SELECT * FROM $table_bookings";
        
        if ($status !== 'all') {
            $sql .= $wpdb->prepare(" WHERE status = %s", $status);
        }
        
        $sql .= " ORDER BY booking_date DESC, booking_time DESC LIMIT %d";
        
        return $wpdb->get_results($wpdb->prepare($sql, $limit));
    }
    
    public static function update_booking_status($booking_id, $status, $table_number = null) {
        global $wpdb;
        
        $table_bookings = $wpdb->prefix . 'restaurant_bookings';
        
        $update_data = array(
            'status' => $status
        );
        
        $format = array('%s');
        
        if ($status === 'confirmed') {
            $update_data['confirmed_at'] = current_time('mysql');
            $format[] = '%s';
            
            if ($table_number) {
                $update_data['table_number'] = intval($table_number);
                $format[] = '%d';
                
                // Mark table as occupied
                self::mark_table_occupied($booking_id, $table_number);
            }
        }
        
        return $wpdb->update(
            $table_bookings,
            $update_data,
            array('id' => $booking_id),
            $format,
            array('%d')
        );
    }
    
    private static function mark_table_occupied($booking_id, $table_number) {
        global $wpdb;
        
        // Get booking details
        $booking = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}restaurant_bookings WHERE id = %d", 
            $booking_id
        ));
        
        if ($booking) {
            // Get table ID
            $table_id = $wpdb->get_var($wpdb->prepare(
                "SELECT id FROM {$wpdb->prefix}restaurant_tables WHERE table_number = %d", 
                $table_number
            ));
            
            if ($table_id) {
                // Insert or update availability record
                $wpdb->replace(
                    $wpdb->prefix . 'restaurant_table_availability',
                    array(
                        'table_id' => $table_id,
                        'booking_date' => $booking->booking_date,
                        'booking_time' => $booking->booking_time,
                        'is_occupied' => 1,
                        'booking_id' => $booking_id
                    ),
                    array('%d', '%s', '%s', '%d', '%d')
                );
            }
        }
    }
    
    public static function get_booking_by_id($booking_id) {
        global $wpdb;
        
        $table_bookings = $wpdb->prefix . 'restaurant_bookings';
        
        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table_bookings WHERE id = %d", 
            $booking_id
        ));
    }
}
