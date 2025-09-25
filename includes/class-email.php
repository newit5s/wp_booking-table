<?php
/**
 * Email functionality
 */

if (!defined('ABSPATH')) {
    exit;
}

class RB_Email {
    
    public function __construct() {
        // Email hooks can be added here if needed
        add_filter('wp_mail_content_type', array($this, 'set_html_content_type'));
    }
    
    public function set_html_content_type() {
        return 'text/html';
    }
    
    /**
     * Send booking confirmation email to customer
     */
    public static function send_confirmation_email($booking_id) {
        $booking = RB_Database::get_booking_by_id($booking_id);
        
        if (!$booking) {
            return false;
        }
        
        $restaurant_name = get_bloginfo('name');
        $admin_email = get_option('admin_email');
        
        // Email subject
        $subject = sprintf(__('Xác nhận đặt bàn tại %s', 'restaurant-booking'), $restaurant_name);
        
        // Email content
        $message = self::get_confirmation_email_template($booking, $restaurant_name);
        
        // Email headers
        $headers = array(
            'Content-Type: text/html; charset=UTF-8',
            'From: ' . $restaurant_name . ' <' . $admin_email . '>',
            'Reply-To: ' . $admin_email
        );
        
        // Send email
        return wp_mail($booking->customer_email, $subject, $message, $headers);
    }
    
    /**
     * Send notification email to admin about new booking
     */
    public static function send_admin_notification($booking_id) {
        $booking = RB_Database::get_booking_by_id($booking_id);
        
        if (!$booking) {
            return false;
        }
        
        $restaurant_name = get_bloginfo('name');
        $admin_email = get_option('admin_email');
        
        // Email subject
        $subject = sprintf(__('Đặt bàn mới cần xác nhận - %s', 'restaurant-booking'), $restaurant_name);
        
        // Email content
        $message = self::get_admin_notification_template($booking, $restaurant_name);
        
        // Email headers
        $headers = array(
            'Content-Type: text/html; charset=UTF-8',
            'From: ' . $restaurant_name . ' <' . $admin_email . '>'
        );
        
        // Send to admin
        return wp_mail($admin_email, $subject, $message, $headers);
    }
    
    /**
     * Send booking reminder email (for future use)
     */
    public static function send_reminder_email($booking_id) {
        $booking = RB_Database::get_booking_by_id($booking_id);
        
        if (!$booking || $booking->status !== 'confirmed') {
            return false;
        }
        
        $restaurant_name = get_bloginfo('name');
        $admin_email = get_option('admin_email');
        
        // Email subject
        $subject = sprintf(__('Nhắc nhở đặt bàn - %s', 'restaurant-booking'), $restaurant_name);
        
        // Email content
        $message = self::get_reminder_email_template($booking, $restaurant_name);
        
        // Email headers
        $headers = array(
            'Content-Type: text/html; charset=UTF-8',
            'From: ' . $restaurant_name . ' <' . $admin_email . '>',
            'Reply-To: ' . $admin_email
        );
        
        return wp_mail($booking->customer_email, $subject, $message, $headers);
    }
    
    /**
     * Get confirmation email template
     */
    private static function get_confirmation_email_template($booking, $restaurant_name) {
        $booking_date = date('d/m/Y', strtotime($booking->booking_date));
        $booking_time = date('H:i', strtotime($booking->booking_time));
        $site_url = get_site_url();
        
        $template = '
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>Xác nhận đặt bàn</title>
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; margin: 0; padding: 0; background-color: #f4f4f4; }
                .container { max-width: 600px; margin: 0 auto; background-color: #fff; border-radius: 10px; overflow: hidden; box-shadow: 0 0 20px rgba(0,0,0,0.1); }
                .header { background-color: #d35400; color: white; padding: 20px; text-align: center; }
                .header h1 { margin: 0; font-size: 24px; }
                .content { padding: 30px; }
                .booking-details { background-color: #f8f9fa; padding: 20px; border-radius: 5px; margin: 20px 0; }
                .booking-details h3 { margin-top: 0; color: #333; }
                .detail-row { display: flex; justify-content: space-between; margin: 10px 0; padding: 8px 0; border-bottom: 1px solid #eee; }
                .detail-label { font-weight: bold; color: #555; }
                .detail-value { color: #333; }
                .footer { background-color: #2c3e50; color: white; padding: 20px; text-align: center; }
                .footer p { margin: 5px 0; }
                .status-confirmed { background-color: #27ae60; color: white; padding: 5px 15px; border-radius: 20px; display: inline-block; font-size: 14px; font-weight: bold; }
                @media (max-width: 600px) {
                    .container { width: 95%; margin: 10px auto; }
                    .content { padding: 20px; }
                    .detail-row { flex-direction: column; }
                }
            </style>
        </head>
        <body>
            <div class="container">
                <div class="header">
                    <h1>🍽️ ' . esc_html($restaurant_name) . '</h1>
                </div>
                
                <div class="content">
                    <h2>Chào ' . esc_html($booking->customer_name) . ',</h2>
                    
                    <p>Cảm ơn bạn đã chọn <strong>' . esc_html($restaurant_name) . '</strong>!</p>
                    
                    <p>Chúng tôi xin xác nhận đặt bàn của bạn đã được <span class="status-confirmed">XÁC NHẬN</span></p>
                    
                    <div class="booking-details">
                        <h3>📋 Thông tin đặt bàn</h3>
                        
                        <div class="detail-row">
                            <span class="detail-label">📅 Ngày:</span>
                            <span class="detail-value">' . $booking_date . '</span>
                        </div>
                        
                        <div class="detail-row">
                            <span class="detail-label">🕐 Giờ:</span>
                            <span class="detail-value">' . $booking_time . '</span>
                        </div>
                        
                        <div class="detail-row">
                            <span class="detail-label">👥 Số khách:</span>
                            <span class="detail-value">' . $booking->guest_count . ' người</span>
                        </div>';
                        
        if ($booking->table_number) {
            $template .= '
                        <div class="detail-row">
                            <span class="detail-label">🪑 Bàn số:</span>
                            <span class="detail-value">' . $booking->table_number . '</span>
                        </div>';
        }
        
        $template .= '
                        <div class="detail-row">
                            <span class="detail-label">📧 Email:</span>
                            <span class="detail-value">' . esc_html($booking->customer_email) . '</span>
                        </div>
                        
                        <div class="detail-row">
                            <span class="detail-label">📱 Điện thoại:</span>
                            <span class="detail-value">' . esc_html($booking->customer_phone) . '</span>
                        </div>';
                        
        if ($booking->special_requests) {
            $template .= '
                        <div class="detail-row">
                            <span class="detail-label">📝 Yêu cầu đặc biệt:</span>
                            <span class="detail-value">' . esc_html($booking->special_requests) . '</span>
                        </div>';
        }
        
        $template .= '
                    </div>
                    
                    <div style="background-color: #e8f5e8; padding: 15px; border-radius: 5px; margin: 20px 0;">
                        <p style="margin: 0; color: #27ae60;"><strong>✅ Lưu ý quan trọng:</strong></p>
                        <ul style="margin: 10px 0; color: #555;">
                            <li>Vui lòng đến đúng giờ đã đặt</li>
                            <li>Nếu có thay đổi, vui lòng liên hệ trước 2 tiếng</li>
                            <li>Bàn sẽ được giữ trong 15 phút kể từ giờ đặt</li>
                        </ul>
                    </div>
                    
                    <p>Nếu bạn cần hỗ trợ hoặc thay đổi thông tin, vui lòng liên hệ với chúng tôi.</p>
                    
                    <p>Chúng tôi rất mong được phục vụ bạn!</p>
                    
                    <p style="margin-top: 30px;">
                        <strong>Trân trọng,</strong><br>
                        <strong>' . esc_html($restaurant_name) . '</strong>
                    </p>
                </div>
                
                <div class="footer">
                    <p><strong>' . esc_html($restaurant_name) . '</strong></p>
                    <p>🌐 Website: <a href="' . esc_url($site_url) . '" style="color: #ecf0f1;">' . esc_url($site_url) . '</a></p>
                    <p style="font-size: 12px; margin-top: 15px;">Email này được gửi tự động, vui lòng không reply.</p>
                </div>
            </div>
        </body>
        </html>';
        
        return $template;
    }
    
    /**
     * Get admin notification email template
     */
    private static function get_admin_notification_template($booking, $restaurant_name) {
        $booking_date = date('d/m/Y', strtotime($booking->booking_date));
        $booking_time = date('H:i', strtotime($booking->booking_time));
        $admin_url = admin_url('admin.php?page=restaurant-booking');
        
        $template = '
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>Đặt bàn mới</title>
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; margin: 0; padding: 0; background-color: #f4f4f4; }
                .container { max-width: 600px; margin: 0 auto; background-color: #fff; border-radius: 10px; overflow: hidden; box-shadow: 0 0 20px rgba(0,0,0,0.1); }
                .header { background-color: #3498db; color: white; padding: 20px; text-align: center; }
                .header h1 { margin: 0; font-size: 24px; }
                .content { padding: 30px; }
                .booking-details { background-color: #f8f9fa; padding: 20px; border-radius: 5px; margin: 20px 0; }
                .booking-details h3 { margin-top: 0; color: #333; }
                .detail-row { display: flex; justify-content: space-between; margin: 10px 0; padding: 8px 0; border-bottom: 1px solid #eee; }
                .detail-label { font-weight: bold; color: #555; }
                .detail-value { color: #333; }
                .action-button { display: inline-block; background-color: #27ae60; color: white; padding: 12px 25px; text-decoration: none; border-radius: 5px; margin: 10px 5px; font-weight: bold; }
                .action-button:hover { background-color: #2ecc71; }
                .urgent { background-color: #e74c3c; color: white; padding: 10px; border-radius: 5px; margin: 15px 0; text-align: center; }
            </style>
        </head>
        <body>
            <div class="container">
                <div class="header">
                    <h1>🔔 Đặt bàn mới</h1>
                </div>
                
                <div class="content">
                    <div class="urgent">
                        <strong>⚡ CẦN XÁC NHẬN NGAY</strong>
                    </div>
                    
                    <p>Có đặt bàn mới cần được xử lý tại <strong>' . esc_html($restaurant_name) . '</strong></p>
                    
                    <div class="booking-details">
                        <h3>👤 Thông tin khách hàng</h3>
                        
                        <div class="detail-row">
                            <span class="detail-label">Tên:</span>
                            <span class="detail-value">' . esc_html($booking->customer_name) . '</span>
                        </div>
                        
                        <div class="detail-row">
                            <span class="detail-label">Điện thoại:</span>
                            <span class="detail-value">' . esc_html($booking->customer_phone) . '</span>
                        </div>
                        
                        <div class="detail-row">
                            <span class="detail-label">Email:</span>
                            <span class="detail-value">' . esc_html($booking->customer_email) . '</span>
                        </div>
                        
                        <div class="detail-row">
                            <span class="detail-label">Ngày đặt:</span>
                            <span class="detail-value">' . $booking_date . '</span>
                        </div>
                        
                        <div class="detail-row">
                            <span class="detail-label">Giờ đặt:</span>
                            <span class="detail-value">' . $booking_time . '</span>
                        </div>
                        
                        <div class="detail-row">
                            <span class="detail-label">Số khách:</span>
                            <span class="detail-value">' . $booking->guest_count . ' người</span>
                        </div>';
                        
        if ($booking->special_requests) {
            $template .= '
                        <div class="detail-row">
                            <span class="detail-label">Yêu cầu đặc biệt:</span>
                            <span class="detail-value">' . esc_html($booking->special_requests) . '</span>
                        </div>';
        }
        
        $template .= '
                    </div>
                    
                    <div style="text-align: center; margin: 30px 0;">
                        <a href="' . esc_url($admin_url) . '" class="action-button">
                            🎯 Xác nhận đặt bàn ngay
                        </a>
                    </div>
                    
                    <p style="font-size: 14px; color: #666; text-align: center;">
                        Đặt bàn ID: #' . $booking->id . ' | Được tạo lúc: ' . date('d/m/Y H:i', strtotime($booking->created_at)) . '
                    </p>
                </div>
            </div>
        </body>
        </html>';
        
        return $template;
    }
    
    /**
     * Get reminder email template
     */
    private static function get_reminder_email_template($booking, $restaurant_name) {
        $booking_date = date('d/m/Y', strtotime($booking->booking_date));
        $booking_time = date('H:i', strtotime($booking->booking_time));
        
        $template = '
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="UTF-8">
            <title>Nhắc nhở đặt bàn</title>
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; margin: 0; padding: 0; background-color: #f4f4f4; }
                .container { max-width: 600px; margin: 0 auto; background-color: #fff; border-radius: 10px; overflow: hidden; }
                .header { background-color: #f39c12; color: white; padding: 20px; text-align: center; }
                .content { padding: 30px; }
                .reminder-box { background-color: #fff3cd; border-left: 4px solid #f39c12; padding: 15px; margin: 20px 0; }
            </style>
        </head>
        <body>
            <div class="container">
                <div class="header">
                    <h1>⏰ Nhắc nhở đặt bàn</h1>
                </div>
                
                <div class="content">
                    <p>Chào ' . esc_html($booking->customer_name) . ',</p>
                    
                    <div class="reminder-box">
                        <p><strong>🔔 Nhắc nhở:</strong> Bạn có lịch đặt bàn vào <strong>' . $booking_date . '</strong> lúc <strong>' . $booking_time . '</strong> tại ' . esc_html($restaurant_name) . '.</p>
                    </div>
                    
                    <p>Chúng tôi rất mong được đón tiếp bạn!</p>
                    
                    <p>Trân trọng,<br>' . esc_html($restaurant_name) . '</p>
                </div>
            </div>
        </body>
        </html>';
        
        return $template;
    }
}
