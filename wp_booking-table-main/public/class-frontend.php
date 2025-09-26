<?php
/**
 * Frontend Class - Xử lý hiển thị frontend và shortcode
 */

if (!defined('ABSPATH')) {
    exit;
}

class RB_Frontend {
    
    /**
     * Constructor
     */
    public function __construct() {
        // Không cần đăng ký shortcode ở đây vì đã đăng ký trong file chính
        // Chỉ cần đăng ký AJAX handlers
        $this->init_ajax_handlers();
    }
    
    /**
     * Initialize AJAX handlers
     */
    private function init_ajax_handlers() {
        // AJAX handlers for logged-in and non-logged-in users
        add_action('wp_ajax_rb_submit_booking', array($this, 'handle_booking_submission'));
        add_action('wp_ajax_nopriv_rb_submit_booking', array($this, 'handle_booking_submission'));
        
        add_action('wp_ajax_rb_check_availability', array($this, 'check_availability'));
        add_action('wp_ajax_nopriv_rb_check_availability', array($this, 'check_availability'));
    }
    
    /**
     * Render booking form shortcode
     */
    public function render_booking_form($atts) {
        // Parse attributes with defaults
        $atts = shortcode_atts(array(
            'title' => 'Đặt bàn nhà hàng',
            'button_text' => 'Đặt bàn ngay',
            'show_button' => 'yes'
        ), $atts, 'restaurant_booking');
        
        // Get settings
        $settings = get_option('rb_settings', array(
            'opening_time' => '09:00',
            'closing_time' => '22:00', 
            'time_slot_interval' => 30
        ));
        
        $opening_time = isset($settings['opening_time']) ? $settings['opening_time'] : '09:00';
        $closing_time = isset($settings['closing_time']) ? $settings['closing_time'] : '22:00';
        $time_interval = isset($settings['time_slot_interval']) ? intval($settings['time_slot_interval']) : 30;
        
        // Generate time slots
        $time_slots = $this->generate_time_slots($opening_time, $closing_time, $time_interval);
        
        // Start output buffering
        ob_start();
        ?>
        <div class="rb-booking-widget">
            <?php if ($atts['show_button'] === 'yes') : ?>
                <button type="button" class="rb-open-modal-btn">
                    <?php echo esc_html($atts['button_text']); ?>
                </button>
            <?php endif; ?>
            
            <!-- Modal Form -->
            <div id="rb-booking-modal" class="rb-modal">
                <div class="rb-modal-content">
                    <span class="rb-close">&times;</span>
                    <h2><?php echo esc_html($atts['title']); ?></h2>
                    
                    <form id="rb-booking-form" class="rb-form">
                        <?php wp_nonce_field('rb_booking_nonce', 'rb_nonce'); ?>
                        
                        <div class="rb-form-row">
                            <div class="rb-form-group">
                                <label for="rb_customer_name">Họ và tên *</label>
                                <input type="text" id="rb_customer_name" name="customer_name" required>
                            </div>
                            
                            <div class="rb-form-group">
                                <label for="rb_customer_phone">Số điện thoại *</label>
                                <input type="tel" id="rb_customer_phone" name="customer_phone" required>
                            </div>
                        </div>
                        
                        <div class="rb-form-row">
                            <div class="rb-form-group">
                                <label for="rb_customer_email">Email *</label>
                                <input type="email" id="rb_customer_email" name="customer_email" required>
                            </div>
                            
                            <div class="rb-form-group">
                                <label for="rb_guest_count">Số lượng khách *</label>
                                <select id="rb_guest_count" name="guest_count" required>
                                    <?php for ($i = 1; $i <= 20; $i++) : ?>
                                        <option value="<?php echo $i; ?>"><?php echo $i; ?> người</option>
                                    <?php endfor; ?>
                                </select>
                            </div>
                        </div>
                        
                        <div class="rb-form-row">
                            <div class="rb-form-group">
                                <label for="rb_booking_date">Ngày đặt bàn *</label>
                                <input type="date" id="rb_booking_date" name="booking_date" 
                                       min="<?php echo date('Y-m-d'); ?>" 
                                       max="<?php echo date('Y-m-d', strtotime('+30 days')); ?>" required>
                            </div>
                            
                            <div class="rb-form-group">
                                <label for="rb_booking_time">Giờ đặt bàn *</label>
                                <select id="rb_booking_time" name="booking_time" required>
                                    <option value="">Chọn giờ</option>
                                    <?php if (!empty($time_slots)) : ?>
                                        <?php foreach ($time_slots as $slot) : ?>
                                            <option value="<?php echo esc_attr($slot); ?>"><?php echo esc_html($slot); ?></option>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </select>
                            </div>
                        </div>
                        
                        <div class="rb-form-group">
                            <label for="rb_special_requests">Yêu cầu đặc biệt</label>
                            <textarea id="rb_special_requests" name="special_requests" rows="3"></textarea>
                        </div>
                        
                        <div class="rb-form-actions">
                            <button type="submit" class="rb-btn-primary">Xác nhận đặt bàn</button>
                            <button type="button" class="rb-btn-cancel rb-close-modal">Hủy</button>
                        </div>
                        
                        <div id="rb-form-message"></div>
                    </form>
                </div>
            </div>
            
            <!-- Inline form (when show_button = no) -->
            <?php if ($atts['show_button'] === 'no') : ?>
                <div class="rb-inline-form">
                    <h3><?php echo esc_html($atts['title']); ?></h3>
                    <form id="rb-booking-form-inline" class="rb-form">
                        <?php wp_nonce_field('rb_booking_nonce', 'rb_nonce_inline'); ?>
                        
                        <div class="rb-form-grid">
                            <div class="rb-form-group">
                                <label for="rb_name_inline">Họ và tên *</label>
                                <input type="text" id="rb_name_inline" name="customer_name" required>
                            </div>
                            
                            <div class="rb-form-group">
                                <label for="rb_phone_inline">Số điện thoại *</label>
                                <input type="tel" id="rb_phone_inline" name="customer_phone" required>
                            </div>
                            
                            <div class="rb-form-group">
                                <label for="rb_email_inline">Email *</label>
                                <input type="email" id="rb_email_inline" name="customer_email" required>
                            </div>
                            
                            <div class="rb-form-group">
                                <label for="rb_guests_inline">Số khách *</label>
                                <select id="rb_guests_inline" name="guest_count" required>
                                    <?php for ($i = 1; $i <= 20; $i++) : ?>
                                        <option value="<?php echo $i; ?>"><?php echo $i; ?> người</option>
                                    <?php endfor; ?>
                                </select>
                            </div>
                            
                            <div class="rb-form-group">
                                <label for="rb_date_inline">Ngày *</label>
                                <input type="date" id="rb_date_inline" name="booking_date" 
                                       min="<?php echo date('Y-m-d'); ?>"
                                       max="<?php echo date('Y-m-d', strtotime('+30 days')); ?>" required>
                            </div>
                            
                            <div class="rb-form-group">
                                <label for="rb_time_inline">Giờ *</label>
                                <select id="rb_time_inline" name="booking_time" required>
                                    <option value="">Chọn giờ</option>
                                    <?php if (!empty($time_slots)) : ?>
                                        <?php foreach ($time_slots as $slot) : ?>
                                            <option value="<?php echo esc_attr($slot); ?>"><?php echo esc_html($slot); ?></option>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </select>
                            </div>
                        </div>
                        
                        <div class="rb-form-group">
                            <label for="rb_requests_inline">Yêu cầu đặc biệt</label>
                            <textarea id="rb_requests_inline" name="special_requests" rows="3"></textarea>
                        </div>
                        
                        <button type="submit" class="rb-btn-primary">Đặt bàn</button>
                        
                        <div id="rb-form-message-inline"></div>
                    </form>
                </div>
            <?php endif; ?>
        </div>
        <?php
        return ob_get_clean();
    }
    
    /**
     * Generate time slots
     */
    private function generate_time_slots($start, $end, $interval) {
        $slots = array();
        
        // Validate inputs
        if (empty($start) || empty($end) || $interval <= 0) {
            return $slots;
        }
        
        $start_time = strtotime($start);
        $end_time = strtotime($end);
        
        if ($start_time === false || $end_time === false) {
            return $slots;
        }
        
        while ($start_time < $end_time) {
            $slots[] = date('H:i', $start_time);
            $start_time += ($interval * 60);
        }
        
        return $slots;
    }
    
    /**
     * Handle booking submission via AJAX
     */
    public function handle_booking_submission() {
        // Verify nonce
        $nonce = isset($_POST['rb_nonce']) ? $_POST['rb_nonce'] : '';
        if (!wp_verify_nonce($nonce, 'rb_booking_nonce')) {
            wp_send_json_error(array('message' => 'Security check failed'));
            wp_die();
        }
        
        // Validate required fields
        $required_fields = array('customer_name', 'customer_phone', 'customer_email', 'guest_count', 'booking_date', 'booking_time');
        
        foreach ($required_fields as $field) {
            if (empty($_POST[$field])) {
                wp_send_json_error(array('message' => 'Vui lòng điền đầy đủ thông tin bắt buộc'));
                wp_die();
            }
        }
        
        // Sanitize input data
        $booking_data = array(
            'customer_name' => sanitize_text_field($_POST['customer_name']),
            'customer_phone' => sanitize_text_field($_POST['customer_phone']),
            'customer_email' => sanitize_email($_POST['customer_email']),
            'guest_count' => intval($_POST['guest_count']),
            'booking_date' => sanitize_text_field($_POST['booking_date']),
            'booking_time' => sanitize_text_field($_POST['booking_time']),
            'special_requests' => isset($_POST['special_requests']) ? sanitize_textarea_field($_POST['special_requests']) : '',
            'status' => 'pending',
            'created_at' => current_time('mysql')
        );
        
        // Validate email
        if (!is_email($booking_data['customer_email'])) {
            wp_send_json_error(array('message' => 'Email không hợp lệ'));
            wp_die();
        }
        
        // Validate phone number
        if (!preg_match('/^[0-9]{10,11}$/', $booking_data['customer_phone'])) {
            wp_send_json_error(array('message' => 'Số điện thoại không hợp lệ'));
            wp_die();
        }
        
        // Validate date
        $booking_date = strtotime($booking_data['booking_date']);
        $today = strtotime(date('Y-m-d'));
        
        if ($booking_date === false || $booking_date < $today) {
            wp_send_json_error(array('message' => 'Ngày đặt bàn không hợp lệ'));
            wp_die();
        }
        
        // Insert booking
        global $wpdb;
        $table_name = $wpdb->prefix . 'rb_bookings';
        
        $result = $wpdb->insert($table_name, $booking_data);
        
        if ($result === false) {
            wp_send_json_error(array('message' => 'Có lỗi xảy ra. Vui lòng thử lại sau.'));
            wp_die();
        }
        
        $booking_id = $wpdb->insert_id;
        
        // Send notification emails
        $this->send_notification_emails($booking_id, $booking_data);
        
        // Success response
        wp_send_json_success(array(
            'message' => 'Đặt bàn thành công! Chúng tôi sẽ liên hệ với bạn sớm để xác nhận.',
            'booking_id' => $booking_id
        ));
        
        wp_die();
    }
    
    /**
     * Check table availability
     */
    public function check_availability() {
        // Verify nonce
        if (!check_ajax_referer('rb_frontend_nonce', 'nonce', false)) {
            wp_send_json_error(array('message' => 'Security check failed'));
            wp_die();
        }
        
        $date = sanitize_text_field($_POST['date']);
        $time = sanitize_text_field($_POST['time']);
        $guests = intval($_POST['guests']);
        
        global $wpdb;
        $tables_table = $wpdb->prefix . 'rb_tables';
        $bookings_table = $wpdb->prefix . 'rb_bookings';
        
        // Get available tables
        $query = $wpdb->prepare("
            SELECT t.* FROM $tables_table t
            WHERE t.is_available = 1
            AND t.capacity >= %d
            AND t.table_number NOT IN (
                SELECT table_number FROM $bookings_table
                WHERE booking_date = %s
                AND booking_time = %s
                AND status IN ('pending', 'confirmed')
                AND table_number IS NOT NULL
            )
            ORDER BY t.capacity ASC
        ", $guests, $date, $time);
        
        $available_tables = $wpdb->get_results($query);
        
        if ($available_tables && count($available_tables) > 0) {
            $message = sprintf('Có %d bàn trống phù hợp cho %d khách', count($available_tables), $guests);
            wp_send_json_success(array(
                'available' => true,
                'message' => $message,
                'tables' => $available_tables
            ));
        } else {
            wp_send_json_success(array(
                'available' => false,
                'message' => 'Không có bàn trống vào thời gian này. Vui lòng chọn thời gian khác.'
            ));
        }
        
        wp_die();
    }
    
    /**
     * Send notification emails
     */
    private function send_notification_emails($booking_id, $booking_data) {
        $settings = get_option('rb_settings', array());
        
        if (!isset($settings['enable_email']) || $settings['enable_email'] !== 'yes') {
            return;
        }
        
        // Send to customer
        $customer_subject = sprintf('[%s] Xác nhận đặt bàn', get_bloginfo('name'));
        $customer_message = $this->get_customer_email_template($booking_data);
        
        wp_mail(
            $booking_data['customer_email'],
            $customer_subject,
            $customer_message,
            array('Content-Type: text/html; charset=UTF-8')
        );
        
        // Send to admin
        $admin_email = isset($settings['admin_email']) ? $settings['admin_email'] : get_option('admin_email');
        $admin_subject = sprintf('[%s] Đặt bàn mới', get_bloginfo('name'));
        $admin_message = $this->get_admin_email_template($booking_data);
        
        wp_mail(
            $admin_email,
            $admin_subject,
            $admin_message,
            array('Content-Type: text/html; charset=UTF-8')
        );
    }
    
    /**
     * Get customer email template
     */
    private function get_customer_email_template($booking) {
        $date = date_i18n('d/m/Y', strtotime($booking['booking_date']));
        $blog_name = get_bloginfo('name');
        
        $message = '<div style="font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto;">';
        $message .= '<h2 style="color: #333;">Xác nhận đặt bàn</h2>';
        $message .= '<p>Xin chào ' . esc_html($booking['customer_name']) . ',</p>';
        $message .= '<p>Cảm ơn bạn đã đặt bàn tại nhà hàng của chúng tôi. Dưới đây là thông tin đặt bàn của bạn:</p>';
        $message .= '<table style="width: 100%; border-collapse: collapse;">';
        $message .= '<tr><td style="padding: 10px; border: 1px solid #ddd;"><strong>Ngày:</strong></td>';
        $message .= '<td style="padding: 10px; border: 1px solid #ddd;">' . $date . '</td></tr>';
        $message .= '<tr><td style="padding: 10px; border: 1px solid #ddd;"><strong>Giờ:</strong></td>';
        $message .= '<td style="padding: 10px; border: 1px solid #ddd;">' . esc_html($booking['booking_time']) . '</td></tr>';
        $message .= '<tr><td style="padding: 10px; border: 1px solid #ddd;"><strong>Số khách:</strong></td>';
        $message .= '<td style="padding: 10px; border: 1px solid #ddd;">' . esc_html($booking['guest_count']) . ' người</td></tr>';
        
        if (!empty($booking['special_requests'])) {
            $message .= '<tr><td style="padding: 10px; border: 1px solid #ddd;"><strong>Yêu cầu:</strong></td>';
            $message .= '<td style="padding: 10px; border: 1px solid #ddd;">' . esc_html($booking['special_requests']) . '</td></tr>';
        }
        
        $message .= '</table>';
        $message .= '<p>Chúng tôi sẽ liên hệ với bạn sớm để xác nhận.</p>';
        $message .= '<p>Trân trọng,<br>' . $blog_name . '</p>';
        $message .= '</div>';
        
        return $message;
    }
    
    /**
     * Get admin email template  
     */
    private function get_admin_email_template($booking) {
        $date = date_i18n('d/m/Y', strtotime($booking['booking_date']));
        $admin_url = admin_url('admin.php?page=restaurant-booking');
        
        $message = '<div style="font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto;">';
        $message .= '<h2 style="color: #333;">Đặt bàn mới</h2>';
        $message .= '<p>Có một đặt bàn mới từ website:</p>';
        $message .= '<table style="width: 100%; border-collapse: collapse;">';
        $message .= '<tr><td style="padding: 10px; border: 1px solid #ddd;"><strong>Khách hàng:</strong></td>';
        $message .= '<td style="padding: 10px; border: 1px solid #ddd;">' . esc_html($booking['customer_name']) . '</td></tr>';
        $message .= '<tr><td style="padding: 10px; border: 1px solid #ddd;"><strong>Điện thoại:</strong></td>';
        $message .= '<td style="padding: 10px; border: 1px solid #ddd;">' . esc_html($booking['customer_phone']) . '</td></tr>';
        $message .= '<tr><td style="padding: 10px; border: 1px solid #ddd;"><strong>Email:</strong></td>';
        $message .= '<td style="padding: 10px; border: 1px solid #ddd;">' . esc_html($booking['customer_email']) . '</td></tr>';
        $message .= '<tr><td style="padding: 10px; border: 1px solid #ddd;"><strong>Ngày:</strong></td>';
        $message .= '<td style="padding: 10px; border: 1px solid #ddd;">' . $date . '</td></tr>';
        $message .= '<tr><td style="padding: 10px; border: 1px solid #ddd;"><strong>Giờ:</strong></td>';
        $message .= '<td style="padding: 10px; border: 1px solid #ddd;">' . esc_html($booking['booking_time']) . '</td></tr>';
        $message .= '<tr><td style="padding: 10px; border: 1px solid #ddd;"><strong>Số khách:</strong></td>';
        $message .= '<td style="padding: 10px; border: 1px solid #ddd;">' . esc_html($booking['guest_count']) . ' người</td></tr>';
        
        if (!empty($booking['special_requests'])) {
            $message .= '<tr><td style="padding: 10px; border: 1px solid #ddd;"><strong>Yêu cầu:</strong></td>';
            $message .= '<td style="padding: 10px; border: 1px solid #ddd;">' . esc_html($booking['special_requests']) . '</td></tr>';
        }
        
        $message .= '</table>';
        $message .= '<p><a href="' . $admin_url . '">Xem trong Admin</a></p>';
        $message .= '</div>';
        
        return $message;
    }
}