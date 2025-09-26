<?php
/**
 * AJAX Class - Xử lý AJAX requests
 */

if (!defined('ABSPATH')) {
    exit;
}

class RB_Ajax {
    
    /**
     * Constructor
     */
    public function __construct() {
        // Admin AJAX handlers
        add_action('wp_ajax_rb_admin_confirm_booking', array($this, 'admin_confirm_booking'));
        add_action('wp_ajax_rb_admin_cancel_booking', array($this, 'admin_cancel_booking'));
        add_action('wp_ajax_rb_admin_complete_booking', array($this, 'admin_complete_booking'));
        add_action('wp_ajax_rb_admin_delete_booking', array($this, 'admin_delete_booking'));
        add_action('wp_ajax_rb_admin_get_booking', array($this, 'admin_get_booking'));
        add_action('wp_ajax_rb_admin_update_booking', array($this, 'admin_update_booking'));
        
        // Table management AJAX
        add_action('wp_ajax_rb_admin_toggle_table', array($this, 'admin_toggle_table'));
        add_action('wp_ajax_rb_admin_add_table', array($this, 'admin_add_table'));
        add_action('wp_ajax_rb_admin_delete_table', array($this, 'admin_delete_table'));
        
        // Frontend AJAX handlers (already in class-frontend.php but can be extended here)
        add_action('wp_ajax_rb_get_time_slots', array($this, 'get_time_slots'));
        add_action('wp_ajax_nopriv_rb_get_time_slots', array($this, 'get_time_slots'));
    }
    
    /**
     * Admin confirm booking
     */
    public function admin_confirm_booking() {
        // Check permission
        if (!current_user_can('manage_options')) {
            wp_die(__('Unauthorized', 'restaurant-booking'));
        }
        
        // Verify nonce
        if (!check_ajax_referer('rb_admin_nonce', 'nonce', false)) {
            wp_send_json_error(array('message' => __('Security check failed', 'restaurant-booking')));
        }
        
        $booking_id = intval($_POST['booking_id']);
        $table_number = isset($_POST['table_number']) ? intval($_POST['table_number']) : null;
        
        global $rb_booking;
        $result = $rb_booking->confirm_booking($booking_id, $table_number);
        
        if ($result) {
            // Send confirmation email
            $booking = $rb_booking->get_booking($booking_id);
            if ($booking && class_exists('RB_Email')) {
                $email = new RB_Email();
                $email->send_confirmation_email($booking);
            }
            
            wp_send_json_success(array('message' => __('Booking confirmed successfully', 'restaurant-booking')));
        } else {
            wp_send_json_error(array('message' => __('Failed to confirm booking', 'restaurant-booking')));
        }
    }
    
    /**
     * Admin cancel booking
     */
    public function admin_cancel_booking() {
        // Check permission
        if (!current_user_can('manage_options')) {
            wp_die(__('Unauthorized', 'restaurant-booking'));
        }
        
        // Verify nonce
        if (!check_ajax_referer('rb_admin_nonce', 'nonce', false)) {
            wp_send_json_error(array('message' => __('Security check failed', 'restaurant-booking')));
        }
        
        $booking_id = intval($_POST['booking_id']);
        
        global $rb_booking;
        $result = $rb_booking->cancel_booking($booking_id);
        
        if ($result) {
            wp_send_json_success(array('message' => __('Booking cancelled successfully', 'restaurant-booking')));
        } else {
            wp_send_json_error(array('message' => __('Failed to cancel booking', 'restaurant-booking')));
        }
    }
    
    /**
     * Admin complete booking
     */
    public function admin_complete_booking() {
        // Check permission
        if (!current_user_can('manage_options')) {
            wp_die(__('Unauthorized', 'restaurant-booking'));
        }
        
        // Verify nonce
        if (!check_ajax_referer('rb_admin_nonce', 'nonce', false)) {
            wp_send_json_error(array('message' => __('Security check failed', 'restaurant-booking')));
        }
        
        $booking_id = intval($_POST['booking_id']);
        
        global $rb_booking;
        $result = $rb_booking->complete_booking($booking_id);
        
        if ($result) {
            wp_send_json_success(array('message' => __('Booking marked as completed', 'restaurant-booking')));
        } else {
            wp_send_json_error(array('message' => __('Failed to complete booking', 'restaurant-booking')));
        }
    }
    
    /**
     * Admin delete booking
     */
    public function admin_delete_booking() {
        // Check permission
        if (!current_user_can('manage_options')) {
            wp_die(__('Unauthorized', 'restaurant-booking'));
        }
        
        // Verify nonce
        if (!check_ajax_referer('rb_admin_nonce', 'nonce', false)) {
            wp_send_json_error(array('message' => __('Security check failed', 'restaurant-booking')));
        }
        
        $booking_id = intval($_POST['booking_id']);
        
        global $rb_booking;
        $result = $rb_booking->delete_booking($booking_id);
        
        if ($result) {
            wp_send_json_success(array('message' => __('Booking deleted successfully', 'restaurant-booking')));
        } else {
            wp_send_json_error(array('message' => __('Failed to delete booking', 'restaurant-booking')));
        }
    }
    
    /**
     * Admin get booking details
     */
    public function admin_get_booking() {
        // Check permission
        if (!current_user_can('manage_options')) {
            wp_die(__('Unauthorized', 'restaurant-booking'));
        }
        
        // Verify nonce
        if (!check_ajax_referer('rb_admin_nonce', 'nonce', false)) {
            wp_send_json_error(array('message' => __('Security check failed', 'restaurant-booking')));
        }
        
        $booking_id = intval($_POST['booking_id']);
        
        global $rb_booking;
        $booking = $rb_booking->get_booking($booking_id);
        
        if ($booking) {
            wp_send_json_success(array('booking' => $booking));
        } else {
            wp_send_json_error(array('message' => __('Booking not found', 'restaurant-booking')));
        }
    }
    
    /**
     * Admin update booking
     */
    public function admin_update_booking() {
        // Check permission
        if (!current_user_can('manage_options')) {
            wp_die(__('Unauthorized', 'restaurant-booking'));
        }
        
        // Verify nonce
        if (!check_ajax_referer('rb_admin_nonce', 'nonce', false)) {
            wp_send_json_error(array('message' => __('Security check failed', 'restaurant-booking')));
        }
        
        $booking_id = intval($_POST['booking_id']);
        
        $data = array();
        
        // Collect update data
        if (isset($_POST['customer_name'])) {
            $data['customer_name'] = sanitize_text_field($_POST['customer_name']);
        }
        if (isset($_POST['customer_phone'])) {
            $data['customer_phone'] = sanitize_text_field($_POST['customer_phone']);
        }
        if (isset($_POST['customer_email'])) {
            $data['customer_email'] = sanitize_email($_POST['customer_email']);
        }
        if (isset($_POST['guest_count'])) {
            $data['guest_count'] = intval($_POST['guest_count']);
        }
        if (isset($_POST['booking_date'])) {
            $data['booking_date'] = sanitize_text_field($_POST['booking_date']);
        }
        if (isset($_POST['booking_time'])) {
            $data['booking_time'] = sanitize_text_field($_POST['booking_time']);
        }
        if (isset($_POST['table_number'])) {
            $data['table_number'] = intval($_POST['table_number']);
        }
        if (isset($_POST['status'])) {
            $data['status'] = sanitize_text_field($_POST['status']);
        }
        if (isset($_POST['special_requests'])) {
            $data['special_requests'] = sanitize_textarea_field($_POST['special_requests']);
        }
        
        global $rb_booking;
        $result = $rb_booking->update_booking($booking_id, $data);
        
        if ($result) {
            wp_send_json_success(array('message' => __('Booking updated successfully', 'restaurant-booking')));
        } else {
            wp_send_json_error(array('message' => __('Failed to update booking', 'restaurant-booking')));
        }
    }
    
    /**
     * Admin toggle table availability
     */
    public function admin_toggle_table() {
        // Check permission
        if (!current_user_can('manage_options')) {
            wp_die(__('Unauthorized', 'restaurant-booking'));
        }
        
        // Verify nonce
        if (!check_ajax_referer('rb_admin_nonce', 'nonce', false)) {
            wp_send_json_error(array('message' => __('Security check failed', 'restaurant-booking')));
        }
        
        $table_id = intval($_POST['table_id']);
        $is_available = intval($_POST['is_available']);
        
        global $wpdb;
        $table_name = $wpdb->prefix . 'rb_tables';
        
        $result = $wpdb->update(
            $table_name,
            array('is_available' => $is_available),
            array('id' => $table_id)
        );
        
        if ($result !== false) {
            wp_send_json_success(array('message' => __('Table status updated', 'restaurant-booking')));
        } else {
            wp_send_json_error(array('message' => __('Failed to update table status', 'restaurant-booking')));
        }
    }
    
    /**
     * Admin add table
     */
    public function admin_add_table() {
        // Check permission
        if (!current_user_can('manage_options')) {
            wp_die(__('Unauthorized', 'restaurant-booking'));
        }
        
        // Verify nonce
        if (!check_ajax_referer('rb_admin_nonce', 'nonce', false)) {
            wp_send_json_error(array('message' => __('Security check failed', 'restaurant-booking')));
        }
        
        $table_number = intval($_POST['table_number']);
        $capacity = intval($_POST['capacity']);
        
        global $wpdb;
        $table_name = $wpdb->prefix . 'rb_tables';
        
        // Check if table number already exists
        $exists = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $table_name WHERE table_number = %d",
            $table_number
        ));
        
        if ($exists) {
            wp_send_json_error(array('message' => __('Table number already exists', 'restaurant-booking')));
        }
        
        $result = $wpdb->insert(
            $table_name,
            array(
                'table_number' => $table_number,
                'capacity' => $capacity,
                'is_available' => 1,
                'created_at' => current_time('mysql')
            )
        );
        
        if ($result) {
            wp_send_json_success(array(
                'message' => __('Table added successfully', 'restaurant-booking'),
                'table_id' => $wpdb->insert_id
            ));
        } else {
            wp_send_json_error(array('message' => __('Failed to add table', 'restaurant-booking')));
        }
    }
    
    /**
     * Admin delete table
     */
    public function admin_delete_table() {
        // Check permission
        if (!current_user_can('manage_options')) {
            wp_die(__('Unauthorized', 'restaurant-booking'));
        }
        
        // Verify nonce
        if (!check_ajax_referer('rb_admin_nonce', 'nonce', false)) {
            wp_send_json_error(array('message' => __('Security check failed', 'restaurant-booking')));
        }
        
        $table_id = intval($_POST['table_id']);
        
        global $wpdb;
        $tables_table = $wpdb->prefix . 'rb_tables';
        $bookings_table = $wpdb->prefix . 'rb_bookings';
        
        // Check if table has any active bookings
        $table = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $tables_table WHERE id = %d",
            $table_id
        ));
        
        if ($table) {
            $active_bookings = $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM $bookings_table 
                WHERE table_number = %d 
                AND status IN ('pending', 'confirmed')
                AND booking_date >= CURDATE()",
                $table->table_number
            ));
            
            if ($active_bookings > 0) {
                wp_send_json_error(array('message' => __('Cannot delete table with active bookings', 'restaurant-booking')));
            }
            
            $result = $wpdb->delete($tables_table, array('id' => $table_id));
            
            if ($result) {
                wp_send_json_success(array('message' => __('Table deleted successfully', 'restaurant-booking')));
            } else {
                wp_send_json_error(array('message' => __('Failed to delete table', 'restaurant-booking')));
            }
        } else {
            wp_send_json_error(array('message' => __('Table not found', 'restaurant-booking')));
        }
    }
    
    /**
     * Get available time slots for a date
     */
    public function get_time_slots() {
        // Verify nonce
        if (!check_ajax_referer('rb_frontend_nonce', 'nonce', false)) {
            wp_send_json_error(array('message' => __('Security check failed', 'restaurant-booking')));
        }
        
        $date = sanitize_text_field($_POST['date']);
        $guest_count = intval($_POST['guest_count']);
        
        // Get settings
        $settings = get_option('rb_settings', array());
        $opening_time = isset($settings['opening_time']) ? $settings['opening_time'] : '09:00';
        $closing_time = isset($settings['closing_time']) ? $settings['closing_time'] : '22:00';
        $time_interval = isset($settings['time_slot_interval']) ? $settings['time_slot_interval'] : 30;
        
        // Generate all time slots
        $all_slots = array();
        $start_time = strtotime($opening_time);
        $end_time = strtotime($closing_time);
        
        while ($start_time < $end_time) {
            $all_slots[] = date('H:i', $start_time);
            $start_time += ($time_interval * 60);
        }
        
        // Check availability for each slot
        global $rb_booking;
        $available_slots = array();
        
        foreach ($all_slots as $slot) {
            if ($rb_booking->is_time_slot_available($date, $slot, $guest_count)) {
                $available_slots[] = $slot;
            }
        }
        
        wp_send_json_success(array(
            'slots' => $available_slots,
            'message' => count($available_slots) > 0 ? 
                __('Available time slots found', 'restaurant-booking') : 
                __('No available time slots for this date', 'restaurant-booking')
        ));
    }
}