<?php
/**
 * AJAX functionality
 */

if (!defined('ABSPATH')) {
    exit;
}

class RB_Ajax {
    
    public function __construct() {
        // Public AJAX actions (for logged in and non-logged in users)
        add_action('wp_ajax_rb_check_availability', array($this, 'check_availability'));
        add_action('wp_ajax_nopriv_rb_check_availability', array($this, 'check_availability'));
        
        add_action('wp_ajax_rb_create_booking', array($this, 'create_booking'));
        add_action('wp_ajax_nopriv_rb_create_booking', array($this, 'create_booking'));
        
        // Admin AJAX actions
        add_action('wp_ajax_rb_confirm_booking', array($this, 'confirm_booking'));
        add_action('wp_ajax_rb_cancel_booking', array($this, 'cancel_booking'));
        add_action('wp_ajax_rb_complete_booking', array($this, 'complete_booking'));
        add_action('wp_ajax_rb_get_available_tables_for_booking', array($this, 'get_available_tables_for_booking'));
        add_action('wp_ajax_rb_reset_table', array($this, 'reset_table'));
        add_action('wp_ajax_rb_toggle_table', array($this, 'toggle_table'));
    }
    
    public function check_availability() {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'], 'rb_booking_nonce')) {
            wp_die('Security check failed');
        }
        
        $date = sanitize_text_field($_POST['date']);
        $time = sanitize_text_field($_POST['time']);
        $guest_count = intval($_POST['guest_count']);
        
        // Validate inputs
        if (empty($date) || empty($time) || $guest_count < 1) {
            wp_send_json_error('Vui lòng điền đầy đủ thông tin');
        }
        
        // Check if date is not in the past
        if (strtotime($date) < strtotime(date('Y-m-d'))) {
            wp_send_json_error('Không thể đặt bàn cho ngày đã qua');
        }
        
        // Get available tables
        $available_tables = RB_Database::get_available_tables($date, $time, $guest_count);
        
        if (empty($available_tables)) {
            wp_send_json_error('Không có bàn trống cho thời gian này. Vui lòng chọn thời gian khác.');
        }
        
        $tables_html = '';
        foreach ($available_tables as $table) {
            $tables_html .= '<div class="rb-table-option">';
            $tables_html .= '<label>';
            $tables_html .= '<input type="radio" name="selected_table" value="' . $table->table_number . '">';
            $tables_html .= ' Bàn ' . $table->table_number . ' (' . $table->capacity . ' chỗ)';
            $tables_html .= '</label>';
            $tables_html .= '</div>';
        }
        
        wp_send_json_success(array(
            'tables_html' => $tables_html,
            'message' => 'Tìm thấy ' . count($available_tables) . ' bàn trống'
        ));
    }
    
    public function create_booking() {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'], 'rb_booking_nonce')) {
            wp_die('Security check failed');
        }
        
        // Validate required fields
        $required_fields = array('customer_name', 'customer_phone', 'customer_email', 'guest_count', 'booking_date', 'booking_time');
        
        foreach ($required_fields as $field) {
            if (empty($_POST[$field])) {
                wp_send_json_error('Vui lòng điền đầy đủ thông tin bắt buộc');
            }
        }
        
        // Sanitize data
        $booking_data = array(
            'customer_name' => sanitize_text_field($_POST['customer_name']),
            'customer_phone' => sanitize_text_field($_POST['customer_phone']),
            'customer_email' => sanitize_email($_POST['customer_email']),
            'guest_count' => intval($_POST['guest_count']),
            'booking_date' => sanitize_text_field($_POST['booking_date']),
            'booking_time' => sanitize_text_field($_POST['booking_time']),
            'special_requests' => sanitize_textarea_field($_POST['special_requests'])
        );
        
        // Validate email
        if (!is_email($booking_data['customer_email'])) {
            wp_send_json_error('Email không hợp lệ');
        }
        
        // Create booking
        $booking_id = RB_Database::create_booking($booking_data);
        
        if ($booking_id) {
            // Send notification email to admin
            $this->send_admin_notification($booking_id);
            
            wp_send_json_success(array(
                'message' => 'Đặt bàn thành công! Chúng tôi sẽ liên hệ xác nhận trong thời gian sớm nhất.',
                'booking_id' => $booking_id
            ));
        } else {
            wp_send_json_error('Có lỗi xảy ra khi đặt bàn. Vui lòng thử lại.');
        }
    }
    
    public function confirm_booking() {
        if (!current_user_can('manage_options')) {
            wp_die('Insufficient permissions');
        }
        
        if (!wp_verify_nonce($_POST['nonce'], 'rb_admin_nonce')) {
            wp_die('Security check failed');
        }
        
        $booking_id = intval($_POST['booking_id']);
        $table_number = intval($_POST['table_number']);
        
        if (!$booking_id || !$table_number) {
            wp_send_json_error('Thiếu thông tin bắt buộc');
        }
        
        $result = RB_Database::update_booking_status($booking_id, 'confirmed', $table_number);
        
        if ($result !== false) {
            // Send confirmation email to customer
            $this->send_confirmation_email($booking_id);
            
            wp_send_json_success('Đặt bàn đã được xác nhận và email đã được gửi');
        } else {
            wp_send_json_error('Có lỗi xảy ra khi xác nhận đặt bàn');
        }
    }
    
    public function cancel_booking() {
        if (!current_user_can('manage_options')) {
            wp_die('Insufficient permissions');
        }
        
        if (!wp_verify_nonce($_POST['nonce'], 'rb_admin_nonce')) {
            wp_die('Security check failed');
        }
        
        $booking_id = intval($_POST['booking_id']);
        
        $result = RB_Database::update_booking_status($booking_id, 'cancelled');
        
        if ($result !== false) {
            wp_send_json_success('Đặt bàn đã được hủy');
        } else {
            wp_send_json_error('Có lỗi xảy ra khi hủy đặt bàn');
        }
    }
    
    public function complete_booking() {
        if (!current_user_can('manage_options')) {
            wp_die('Insufficient permissions');
        }
        
        if (!wp_verify_nonce($_POST['nonce'], 'rb_admin_nonce')) {
            wp_die('Security check failed');
        }
        
        $booking_id = intval($_POST['booking_id']);
        
        $result = RB_Database::update_booking_status($booking_id, 'completed');
        
        if ($result !== false) {
            // Free up the table
            $this->free_table($booking_id);
            
            wp_send_json_success('Đặt bàn đã hoàn thành');
        } else {
            wp_send_json_error('Có lỗi xảy ra');
        }
    }
    
    public function get_available_tables_for_booking() {
        if (!current_user_can('manage_options')) {
            wp_die('Insufficient permissions');
        }
        
        $booking_id = intval($_POST['booking_id']);
        $booking = RB_Database::get_booking_by_id($booking_id);
        
        if (!$booking) {
            wp_send_json_error('Không tìm thấy đặt bàn');
        }
        
        $available_tables = RB_Database::get_available_tables(
            $booking->booking_date, 
            $booking->booking_time, 
            $booking->guest_count
        );
        
        $tables_html = '';
        foreach ($available_tables as $table) {
            $tables_html .= '<label class="rb-table-choice">';
            $tables_html .= '<input type="radio" name="table_number" value="' . $table->table_number . '" required>';
            $tables_html .= ' Bàn ' . $table->table_number . ' (' . $table->capacity . ' chỗ)';
            $tables_html .= '</label>';
        }
        
        wp_send_json_success(array(
            'tables_html' => $tables_html,
            'booking_info' => $booking
        ));
    }
    
    public function reset_table() {
        if (!current_user_can('manage_options')) {
            wp_die('Insufficient permissions');
        }
        
        global $wpdb;
        $table_id = intval($_POST['table_id']);
        $today = date('Y-m-d');
        
        // Clear today's bookings for this table
        $wpdb->update(
            $wpdb->prefix . 'restaurant_table_availability',
            array('is_occupied' => 0, 'booking_id' => null),
            array('table_id' => $table_id, 'booking_date' => $today),
            array('%d', '%d'),
            array('%d', '%s')
        );
        
        wp_send_json_success('Bàn đã được reset');
    }
    
    public function toggle_table() {
        if (!current_user_can('manage_options')) {
            wp_die('Insufficient permissions');
        }
        
        global $wpdb;
        $table_id = intval($_POST['table_id']);
        $current_status = intval($_POST['current_status']);
        $new_status = $current_status ? 0 : 1;
        
        $wpdb->update(
            $wpdb->prefix . 'restaurant_tables',
            array('is_available' => $new_status),
            array('id' => $table_id),
            array('%d'),
            array('%d')
        );
        
        $status_text = $new_status ? 'kích hoạt' : 'tạm ngưng';
        wp_send_json_success("Bàn đã được {$status_text}");
    }
    
    private function send_admin_notification($booking_id) {
        $booking = RB_Database::get_booking_by_id($booking_id);
        if (!$booking) return;
        
        $admin_email = get_option('admin_email');
        $subject = 'Đặt bàn mới cần xác nhận - ' . get_bloginfo('name');
        
        $message = "Có đặt bàn mới cần xác nhận:\n\n";
        $message .= "Khách hàng: " . $booking->customer_name . "\n";
        $message .= "Điện thoại: " . $booking->customer_phone . "\n";
        $message .= "Email: " . $booking->customer_email . "\n";
        $message .= "Số khách: " . $booking->guest_count . "\n";
        $message .= "Ngày: " . date('d/m/Y', strtotime($booking->booking_date)) . "\n";
        $message .= "Giờ: " . date('H:i', strtotime($booking->booking_time)) . "\n";
        
        if ($booking->special_requests) {
            $message .= "Yêu cầu đặc biệt: " . $booking->special_requests . "\n";
        }
        
        $message .= "\nVui lòng vào admin để xác nhận: " . admin_url('admin.php?page=restaurant-booking');
        
        wp_mail($admin_email, $subject, $message);
    }
    
    private function send_confirmation_email($booking_id) {
        $booking = RB_Database::get_booking_by_id($booking_id);
        if (!$booking) return;
        
        $subject = 'Xác nhận đặt bàn - ' . get_bloginfo('name');
        
        $message = "Chào " . $booking->customer_name . ",\n\n";
        $message .= "Đặt bàn của bạn đã được xác nhận!\n\n";
        $message .= "Thông tin đặt bàn:\n";
        $message .= "- Ngày: " . date('d/m/Y', strtotime($booking->booking_date)) . "\n";
        $message .= "- Giờ: " . date('H:i', strtotime($booking->booking_time)) . "\n";
        $message .= "- Số khách: " . $booking->guest_count . "\n";
        
        if ($booking->table_number) {
            $message .= "- Bàn số: " . $booking->table_number . "\n";
        }
        
        $message .= "\nCảm ơn bạn đã chọn " . get_bloginfo('name') . "!";
        
        wp_mail($booking->customer_email, $subject, $message);
    }
    
    private function free_table($booking_id) {
        global $wpdb;
        
        $wpdb->update(
            $wpdb->prefix . 'restaurant_table_availability',
            array('is_occupied' => 0),
            array('booking_id' => $booking_id),
            array('%d'),
            array('%d')
        );
    }
}
