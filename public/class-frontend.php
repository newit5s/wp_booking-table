<?php
/**
 * Frontend functionality
 */

if (!defined('ABSPATH')) {
    exit;
}

class RB_Frontend {
    
    public function __construct() {
        add_shortcode('restaurant_booking', array($this, 'booking_form_shortcode'));
        add_action('wp_footer', array($this, 'add_booking_modal'));
    }
    
    public function booking_form_shortcode($atts) {
        $atts = shortcode_atts(array(
            'title' => 'Đặt bàn ngay',
            'button_text' => 'Đặt bàn'
        ), $atts);
        
        ob_start();
        ?>
        <div class="rb-booking-widget">
            <div class="rb-booking-header">
                <h3><?php echo esc_html($atts['title']); ?></h3>
            </div>
            <button type="button" class="rb-booking-btn" data-toggle="rb-modal">
                <?php echo esc_html($atts['button_text']); ?>
            </button>
        </div>
        <?php
        return ob_get_clean();
    }
    
    public function add_booking_modal() {
        if (!is_admin()) {
            ?>
            <div id="rb-booking-modal" class="rb-modal">
                <div class="rb-modal-content">
                    <div class="rb-modal-header">
                        <h3>Đặt bàn</h3>
                        <span class="rb-close">&times;</span>
                    </div>
                    <div class="rb-modal-body">
                        <form id="rb-booking-form">
                            <div class="rb-form-row">
                                <div class="rb-form-group">
                                    <label for="customer_name">Họ và tên *</label>
                                    <input type="text" id="customer_name" name="customer_name" required>
                                </div>
                                <div class="rb-form-group">
                                    <label for="customer_phone">Số điện thoại *</label>
                                    <input type="tel" id="customer_phone" name="customer_phone" required>
                                </div>
                            </div>
                            
                            <div class="rb-form-row">
                                <div class="rb-form-group">
                                    <label for="customer_email">Email *</label>
                                    <input type="email" id="customer_email" name="customer_email" required>
                                </div>
                                <div class="rb-form-group">
                                    <label for="guest_count">Số lượng khách *</label>
                                    <select id="guest_count" name="guest_count" required>
                                        <option value="">Chọn số khách</option>
                                        <?php for($i = 1; $i <= 12; $i++): ?>
                                            <option value="<?php echo $i; ?>"><?php echo $i; ?> <?php echo $i == 1 ? 'người' : 'người'; ?></option>
                                        <?php endfor; ?>
                                    </select>
                                </div>
                            </div>
                            
                            <div class="rb-form-row">
                                <div class="rb-form-group">
                                    <label for="booking_date">Ngày đặt bàn *</label>
                                    <input type="date" id="booking_date" name="booking_date" required min="<?php echo date('Y-m-d'); ?>">
                                </div>
                                <div class="rb-form-group">
                                    <label for="booking_time">Giờ đặt bàn *</label>
                                    <select id="booking_time" name="booking_time" required>
                                        <option value="">Chọn giờ</option>
                                        <?php
                                        $start_time = strtotime('09:00');
                                        $end_time = strtotime('21:00');
                                        
                                        for($time = $start_time; $time <= $end_time; $time += 1800) { // 30 minutes interval
                                            $formatted_time = date('H:i', $time);
                                            echo "<option value='{$formatted_time}'>{$formatted_time}</option>";
                                        }
                                        ?>
                                    </select>
                                </div>
                            </div>
                            
                            <div class="rb-form-group">
                                <label for="special_requests">Yêu cầu đặc biệt</label>
                                <textarea id="special_requests" name="special_requests" rows="3" placeholder="Ghi chú thêm về đặt bàn..."></textarea>
                            </div>
                            
                            <div class="rb-available-tables" id="rb-available-tables" style="display:none;">
                                <h4>Bàn trống:</h4>
                                <div class="rb-tables-list" id="rb-tables-list">
                                    <!-- Available tables will be loaded here -->
                                </div>
                            </div>
                            
                            <div class="rb-form-actions">
                                <button type="button" class="rb-btn rb-btn-secondary" id="rb-check-availability">
                                    Kiểm tra bàn trống
                                </button>
                                <button type="submit" class="rb-btn rb-btn-primary" disabled>
                                    Đặt bàn
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
            
            <div class="rb-loading" id="rb-loading" style="display:none;">
                <div class="rb-spinner"></div>
                <p>Đang xử lý...</p>
            </div>
            
            <div class="rb-message" id="rb-message" style="display:none;">
                <div class="rb-message-content">
                    <span class="rb-message-text"></span>
                    <button class="rb-message-close">&times;</button>
                </div>
            </div>
            <?php
        }
    }
}
