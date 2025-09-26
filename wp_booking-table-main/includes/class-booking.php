<?php
/**
 * Booking Class - Xử lý logic đặt bàn
 */

if (!defined('ABSPATH')) {
    exit;
}

class RB_Booking {
    
    private $wpdb;
    
    /**
     * Constructor
     */
    public function __construct() {
        global $wpdb;
        $this->wpdb = $wpdb;
    }
    
    /**
     * Get booking by ID
     */
    public function get_booking($booking_id) {
        $table_name = $this->wpdb->prefix . 'rb_bookings';
        return $this->wpdb->get_row($this->wpdb->prepare(
            "SELECT * FROM $table_name WHERE id = %d",
            $booking_id
        ));
    }
    
    /**
     * Get all bookings
     */
    public function get_bookings($args = array()) {
        $defaults = array(
            'status' => '',
            'date' => '',
            'limit' => -1,
            'offset' => 0,
            'orderby' => 'booking_date',
            'order' => 'DESC'
        );
        
        $args = wp_parse_args($args, $defaults);
        $table_name = $this->wpdb->prefix . 'rb_bookings';
        
        $where_clauses = array('1=1');
        
        if (!empty($args['status'])) {
            $where_clauses[] = $this->wpdb->prepare("status = %s", $args['status']);
        }
        
        if (!empty($args['date'])) {
            $where_clauses[] = $this->wpdb->prepare("booking_date = %s", $args['date']);
        }
        
        $where = implode(' AND ', $where_clauses);
        $orderby = sanitize_sql_orderby($args['orderby'] . ' ' . $args['order']);
        
        $sql = "SELECT * FROM $table_name WHERE $where ORDER BY $orderby";
        
        if ($args['limit'] > 0) {
            $sql .= $this->wpdb->prepare(" LIMIT %d OFFSET %d", $args['limit'], $args['offset']);
        }
        
        return $this->wpdb->get_results($sql);
    }
    
    /**
     * Create new booking
     */
    public function create_booking($data) {
        $table_name = $this->wpdb->prefix . 'rb_bookings';
        
        $defaults = array(
            'status' => 'pending',
            'created_at' => current_time('mysql'),
            'table_number' => null
        );
        
        $data = wp_parse_args($data, $defaults);
        
        // Validate required fields
        $required = array('customer_name', 'customer_phone', 'customer_email', 'guest_count', 'booking_date', 'booking_time');
        
        foreach ($required as $field) {
            if (empty($data[$field])) {
                return new WP_Error('missing_field', sprintf(__('Field %s is required', 'restaurant-booking'), $field));
            }
        }
        
        // Insert booking
        $result = $this->wpdb->insert($table_name, $data);
        
        if ($result === false) {
            return new WP_Error('db_error', __('Could not create booking', 'restaurant-booking'));
        }
        
        return $this->wpdb->insert_id;
    }
    
    /**
     * Update booking
     */
    public function update_booking($booking_id, $data) {
        $table_name = $this->wpdb->prefix . 'rb_bookings';
        
        $result = $this->wpdb->update(
            $table_name,
            $data,
            array('id' => $booking_id)
        );
        
        return $result !== false;
    }
    
    /**
     * Delete booking
     */
    public function delete_booking($booking_id) {
        $table_name = $this->wpdb->prefix . 'rb_bookings';
        
        return $this->wpdb->delete(
            $table_name,
            array('id' => $booking_id)
        );
    }
    
    /**
     * Confirm booking
     */
    public function confirm_booking($booking_id, $table_number = null) {
        $data = array(
            'status' => 'confirmed',
            'confirmed_at' => current_time('mysql')
        );
        
        if ($table_number) {
            $data['table_number'] = $table_number;
        }
        
        return $this->update_booking($booking_id, $data);
    }
    
    /**
     * Cancel booking
     */
    public function cancel_booking($booking_id) {
        return $this->update_booking($booking_id, array(
            'status' => 'cancelled'
        ));
    }
    
    /**
     * Complete booking
     */
    public function complete_booking($booking_id) {
        return $this->update_booking($booking_id, array(
            'status' => 'completed'
        ));
    }
    
    /**
     * Check if time slot is available
     */
    public function is_time_slot_available($date, $time, $guest_count, $exclude_booking_id = null) {
        $tables_table = $this->wpdb->prefix . 'rb_tables';
        $bookings_table = $this->wpdb->prefix . 'rb_bookings';
        
        // Build exclude clause
        $exclude_clause = '';
        if ($exclude_booking_id) {
            $exclude_clause = $this->wpdb->prepare(" AND id != %d", $exclude_booking_id);
        }
        
        // Get available tables count
        $total_tables = $this->wpdb->get_var("SELECT COUNT(*) FROM $tables_table WHERE is_available = 1");
        
        // Get booked tables count for this time slot
        $booked_tables = $this->wpdb->get_var($this->wpdb->prepare(
            "SELECT COUNT(DISTINCT table_number) FROM $bookings_table 
            WHERE booking_date = %s 
            AND booking_time = %s 
            AND status IN ('pending', 'confirmed')
            $exclude_clause",
            $date,
            $time
        ));
        
        // Check if there are available tables
        $available_tables = $total_tables - $booked_tables;
        
        // Also check if any table has enough capacity
        $suitable_tables = $this->wpdb->get_var($this->wpdb->prepare(
            "SELECT COUNT(*) FROM $tables_table t
            WHERE t.is_available = 1
            AND t.capacity >= %d
            AND t.table_number NOT IN (
                SELECT table_number FROM $bookings_table
                WHERE booking_date = %s
                AND booking_time = %s
                AND status IN ('pending', 'confirmed')
                $exclude_clause
            )",
            $guest_count,
            $date,
            $time
        ));
        
        return $suitable_tables > 0;
    }
    
    /**
     * Get available tables for a time slot
     */
    public function get_available_tables($date, $time, $guest_count) {
        $tables_table = $this->wpdb->prefix . 'rb_tables';
        $bookings_table = $this->wpdb->prefix . 'rb_bookings';
        
        return $this->wpdb->get_results($this->wpdb->prepare(
            "SELECT t.* FROM $tables_table t
            WHERE t.is_available = 1
            AND t.capacity >= %d
            AND t.table_number NOT IN (
                SELECT table_number FROM $bookings_table
                WHERE booking_date = %s
                AND booking_time = %s
                AND status IN ('pending', 'confirmed')
            )
            ORDER BY t.capacity ASC",
            $guest_count,
            $date,
            $time
        ));
    }
    
    /**
     * Auto assign table to booking
     */
    public function auto_assign_table($booking_id) {
        $booking = $this->get_booking($booking_id);
        
        if (!$booking) {
            return false;
        }
        
        // Get available tables
        $available_tables = $this->get_available_tables(
            $booking->booking_date,
            $booking->booking_time,
            $booking->guest_count
        );
        
        if (empty($available_tables)) {
            return false;
        }
        
        // Assign the smallest suitable table
        $table = $available_tables[0];
        
        return $this->update_booking($booking_id, array(
            'table_number' => $table->table_number
        ));
    }
    
    /**
     * Get booking statistics
     */
    public function get_statistics($period = 'all') {
        $table_name = $this->wpdb->prefix . 'rb_bookings';
        $stats = array();
        
        // Base WHERE clause
        $where = "1=1";
        
        switch ($period) {
            case 'today':
                $where = $this->wpdb->prepare("booking_date = %s", date('Y-m-d'));
                break;
            case 'week':
                $where = "booking_date >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)";
                break;
            case 'month':
                $where = "booking_date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)";
                break;
        }
        
        // Get total bookings
        $stats['total'] = $this->wpdb->get_var("SELECT COUNT(*) FROM $table_name WHERE $where");
        
        // Get bookings by status
        $statuses = array('pending', 'confirmed', 'cancelled', 'completed');
        
        foreach ($statuses as $status) {
            $stats[$status] = $this->wpdb->get_var($this->wpdb->prepare(
                "SELECT COUNT(*) FROM $table_name WHERE $where AND status = %s",
                $status
            ));
        }
        
        // Get average guests
        $stats['avg_guests'] = $this->wpdb->get_var("SELECT AVG(guest_count) FROM $table_name WHERE $where");
        
        return $stats;
    }
}