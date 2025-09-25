<?php
/**
 * Main booking functionality
 */

if (!defined('ABSPATH')) {
    exit;
}

class RB_Booking {
    
    public function __construct() {
        // Initialize booking functionality
        add_action('init', array($this, 'init'));
    }
    
    public function init() {
        // Add any initialization code here
    }
    
    /**
     * Validate booking data
     */
    public static function validate_booking_data($data) {
        $errors = array();
        
        // Validate required fields
        if (empty($data['customer_name'])) {
            $errors[] = 'Tên khách hàng là bắt buộc';
        }
        
        if (empty($data['customer_phone'])) {
            $errors[] = 'Số điện thoại là bắt buộc';
        } elseif (!preg_match('/^[0-9]{10}$/', $data['customer_phone'])) {
            $errors[] = 'Số điện thoại không hợp lệ (10 chữ số)';
        }
        
        if (empty($data['customer_email'])) {
            $errors[] = 'Email là bắt buộc';
        } elseif (!is_email($data['customer_email'])) {
            $errors[] = 'Email không hợp lệ';
        }
        
        if (empty($data['guest_count']) || intval($data['guest_count']) < 1) {
            $errors[] = 'Số lượng khách phải ít nhất 1 người';
        } elseif (intval($data['guest_count']) > 20) {
            $errors[] = 'Số lượng khách không được vượt quá 20 người';
        }
        
        if (empty($data['booking_date'])) {
            $errors[] = 'Ngày đặt bàn là bắt buộc';
        } else {
            $booking_date = strtotime($data['booking_date']);
            $today = strtotime(date('Y-m-d'));
            
            if ($booking_date < $today) {
                $errors[] = 'Không thể đặt bàn cho ngày đã qua';
            }
            
            // Check if booking date is too far in the future (e.g., 3 months)
            $max_advance_days = 90;
            if ($booking_date > strtotime("+{$max_advance_days} days")) {
                $errors[] = "Chỉ có thể đặt bàn trước tối đa {$max_advance_days} ngày";
            }
        }
        
        if (empty($data['booking_time'])) {
            $errors[] = 'Giờ đặt bàn là bắt buộc';
        } else {
            $opening_hours = get_option('rb_opening_hours', array('start' => '09:00', 'end' => '22:00'));
            $booking_time = $data['booking_time'];
            
            if ($booking_time < $opening_hours['start'] || $booking_time > $opening_hours['end']) {
                $errors[] = "Giờ đặt bàn phải trong khoảng {$opening_hours['start']} - {$opening_hours['end']}";
            }
        }
        
        return $errors;
    }
    
    /**
     * Check if the restaurant is open on a given date
     */
    public static function is_restaurant_open($date) {
        $day_of_week = date('N', strtotime($date)); // 1 (Monday) to 7 (Sunday)
        
        // Check if it's a holiday or closed day
        $closed_days = get_option('rb_closed_days', array());
        
        if (in_array($day_of_week, $closed_days)) {
            return false;
        }
        
        // Check specific closed dates
        $closed_dates = get_option('rb_closed_dates', array());
        if (in_array($date, $closed_dates)) {
            return false;
        }
        
        return true;
    }
    
    /**
     * Get available time slots for a specific date
     */
    public static function get_available_time_slots($date, $guest_count = 1) {
        if (!self::is_restaurant_open($date)) {
            return array();
        }
        
        $opening_hours = get_option('rb_opening_hours', array('start' => '09:00', 'end' => '22:00'));
        $booking_duration = get_option('rb_booking_duration', 120); // minutes
        
        $start_time = strtotime($opening_hours['start']);
        $end_time = strtotime($opening_hours['end']);
        $slot_duration = 30 * 60; // 30 minutes in seconds
        
        $available_slots = array();
        
        for ($time = $start_time; $time < $end_time; $time += $slot_duration) {
            $time_slot = date('H:i', $time);
            
            // Check if there are available tables for this time slot
            $available_tables = RB_Database::get_available_tables($date, $time_slot, $guest_count);
            
            if (!empty($available_tables)) {
                $available_slots[] = array(
                    'time' => $time_slot,
                    'available_tables' => count($available_tables),
                    'display' => date('H:i', $time)
                );
            }
        }
        
        return $available_slots;
    }
    
    /**
     * Calculate table occupancy for a given date
     */
    public static function get_table_occupancy($date) {
        global $wpdb;
        
        $table_availability = $wpdb->prefix . 'restaurant_table_availability';
        $restaurant_tables = $wpdb->prefix . 'restaurant_tables';
        
        // Get total number of tables
        $total_tables = $wpdb->get_var("SELECT COUNT(*) FROM {$restaurant_tables} WHERE is_available = 1");
        
        // Get occupied tables for the date
        $occupied_tables = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(DISTINCT table_id) FROM {$table_availability} 
             WHERE booking_date = %s AND is_occupied = 1",
            $date
        ));
        
        $occupancy_rate = $total_tables > 0 ? ($occupied_tables / $total_tables) * 100 : 0;
        
        return array(
            'total_tables' => $total_tables,
            'occupied_tables' => $occupied_tables,
            'available_tables' => $total_tables - $occupied_tables,
            'occupancy_rate' => round($occupancy_rate, 2)
        );
    }
    
    /**
     * Get booking statistics
     */
    public static function get_booking_statistics($start_date = null, $end_date = null) {
        global $wpdb;
        
        if (!$start_date) {
            $start_date = date('Y-m-01'); // First day of current month
        }
        
        if (!$end_date) {
            $end_date = date('Y-m-t'); // Last day of current month
        }
        
        $bookings_table = $wpdb->prefix . 'restaurant_bookings';
        
        // Total bookings
        $total_bookings = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$bookings_table} 
             WHERE booking_date BETWEEN %s AND %s",
            $start_date, $end_date
        ));
        
        // Confirmed bookings
        $confirmed_bookings = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$bookings_table} 
             WHERE booking_date BETWEEN %s AND %s AND status = 'confirmed'",
            $start_date, $end_date
        ));
        
        // Pending bookings
        $pending_bookings = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$bookings_table} 
             WHERE booking_date BETWEEN %s AND %s AND status = 'pending'",
            $start_date, $end_date
        ));
        
        // Cancelled bookings
        $cancelled_bookings = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$bookings_table} 
             WHERE booking_date BETWEEN %s AND %s AND status = 'cancelled'",
            $start_date, $end_date
        ));
        
        // Average guests per booking
        $avg_guests = $wpdb->get_var($wpdb->prepare(
            "SELECT AVG(guest_count) FROM {$bookings_table} 
             WHERE booking_date BETWEEN %s AND %s AND status != 'cancelled'",
            $start_date, $end_date
        ));
        
        // Most popular time slots
        $popular_times = $wpdb->get_results($wpdb->prepare(
            "SELECT booking_time, COUNT(*) as count 
             FROM {$bookings_table} 
             WHERE booking_date BETWEEN %s AND %s AND status = 'confirmed'
             GROUP BY booking_time 
             ORDER BY count DESC 
             LIMIT 5",
            $start_date, $end_date
        ));
        
        return array(
            'period' => array(
                'start' => $start_date,
                'end' => $end_date
            ),
            'total_bookings' => intval($total_bookings),
            'confirmed_bookings' => intval($confirmed_bookings),
            'pending_bookings' => intval($pending_bookings),
            'cancelled_bookings' => intval($cancelled_bookings),
            'confirmation_rate' => $total_bookings > 0 ? round(($confirmed_bookings / $total_bookings) * 100, 2) : 0,
            'avg_guests_per_booking' => round($avg_guests, 1),
            'popular_times' => $popular_times
        );
    }
    
    /**
     * Clean up expired pending bookings
     */
    public static function cleanup_expired_bookings() {
        global $wpdb;
        
        $bookings_table = $wpdb->prefix . 'restaurant_bookings';
        $availability_table = $wpdb->prefix . 'restaurant_table_availability';
        
        // Get expired pending bookings (older than 2 hours from booking time)
        $expired_bookings = $wpdb->get_results($wpdb->prepare(
            "SELECT id, booking_date, booking_time FROM {$bookings_table} 
             WHERE status = 'pending' 
             AND CONCAT(booking_date, ' ', booking_time) < %s",
            date('Y-m-d H:i:s', strtotime('-2 hours'))
        ));
        
        foreach ($expired_bookings as $booking) {
            // Update booking status to expired
            $wpdb->update(
                $bookings_table,
                array('status' => 'expired'),
                array('id' => $booking->id),
                array('%s'),
                array('%d')
            );
            
            // Free up any reserved tables
            $wpdb->update(
                $availability_table,
                array('is_occupied' => 0, 'booking_id' => null),
                array('booking_id' => $booking->id),
                array('%d', '%d'),
                array('%d')
            );
        }
        
        return count($expired_bookings);
    }
    
    /**
     * Get upcoming bookings for today
     */
    public static function get_todays_bookings() {
        $today = date('Y-m-d');
        $bookings = RB_Database::get_bookings('confirmed');
        
        $todays_bookings = array_filter($bookings, function($booking) use ($today) {
            return $booking->booking_date === $today;
        });
        
        // Sort by booking time
        usort($todays_bookings, function($a, $b) {
            return strcmp($a->booking_time, $b->booking_time);
        });
        
        return $todays_bookings;
    }
    
    /**
     * Get booking conflicts (double bookings)
     */
    public static function get_booking_conflicts($date = null) {
        global $wpdb;
        
        if (!$date) {
            $date = date('Y-m-d');
        }
        
        $availability_table = $wpdb->prefix . 'restaurant_table_availability';
        $bookings_table = $wpdb->prefix . 'restaurant_bookings';
        
        // Find tables with multiple bookings at the same time
        $conflicts = $wpdb->get_results($wpdb->prepare(
            "SELECT table_id, booking_date, booking_time, COUNT(*) as booking_count
             FROM {$availability_table} 
             WHERE booking_date = %s AND is_occupied = 1
             GROUP BY table_id, booking_date, booking_time
             HAVING booking_count > 1",
            $date
        ));
        
        return $conflicts;
    }
    
    /**
     * Send booking reminders for tomorrow's bookings
     */
    public static function send_booking_reminders() {
        $tomorrow = date('Y-m-d', strtotime('+1 day'));
        
        global $wpdb;
        $bookings_table = $wpdb->prefix . 'restaurant_bookings';
        
        $tomorrow_bookings = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$bookings_table} 
             WHERE booking_date = %s AND status = 'confirmed'",
            $tomorrow
        ));
        
        $sent_count = 0;
        foreach ($tomorrow_bookings as $booking) {
            if (RB_Email::send_reminder_email($booking->id)) {
                $sent_count++;
            }
        }
        
        return $sent_count;
    }
    
    /**
     * Auto-confirm bookings if enabled
     */
    public static function auto_confirm_booking($booking_id) {
        $auto_confirm = get_option('rb_auto_confirm_bookings', false);
        
        if (!$auto_confirm) {
            return false;
        }
        
        $booking = RB_Database::get_booking_by_id($booking_id);
        if (!$booking) {
            return false;
        }
        
        // Get the best available table
        $available_tables = RB_Database::get_available_tables(
            $booking->booking_date,
            $booking->booking_time,
            $booking->guest_count
        );
        
        if (empty($available_tables)) {
            return false;
        }
        
        // Select the smallest suitable table
        $selected_table = $available_tables[0];
        
        // Confirm the booking
        $result = RB_Database::update_booking_status($booking_id, 'confirmed', $selected_table->table_number);
        
        if ($result !== false) {
            // Send confirmation email
            RB_Email::send_confirmation_email($booking_id);
            return true;
        }
        
        return false;
    }
    
    /**
     * Check table availability for a specific time range
     */
    public static function check_table_availability_range($table_id, $date, $start_time, $end_time) {
        global $wpdb;
        
        $availability_table = $wpdb->prefix . 'restaurant_table_availability';
        
        $conflicts = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$availability_table}
             WHERE table_id = %d 
             AND booking_date = %s 
             AND booking_time BETWEEN %s AND %s
             AND is_occupied = 1",
            $table_id, $date, $start_time, $end_time
        ));
        
        return $conflicts == 0;
    }
    
    /**
     * Get restaurant capacity for a specific date
     */
    public static function get_restaurant_capacity($date) {
        global $wpdb;
        
        $tables_table = $wpdb->prefix . 'restaurant_tables';
        
        $capacity = $wpdb->get_var($wpdb->prepare(
            "SELECT SUM(capacity) FROM {$tables_table} 
             WHERE is_available = 1"
        ));
        
        return intval($capacity);
    }
}
