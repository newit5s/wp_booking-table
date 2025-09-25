<?php
/**
 * Admin functionality
 */

if (!defined('ABSPATH')) {
    exit;
}

class RB_Admin {
    
    public function __construct() {
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_init', array($this, 'admin_init'));
    }
    
    public function add_admin_menu() {
        add_menu_page(
            'Quản lý đặt bàn',
            'Đặt bàn',
            'manage_options',
            'restaurant-booking',
            array($this, 'admin_page'),
            'dashicons-calendar-alt',
            30
        );
        
        add_submenu_page(
            'restaurant-booking',
            'Đặt bàn',
            'Đặt bàn',
            'manage_options',
            'restaurant-booking',
            array($this, 'admin_page')
        );
        
        add_submenu_page(
            'restaurant-booking',
            'Quản lý bàn',
            'Quản lý bàn',
            'manage_options',
            'restaurant-tables',
            array($this, 'tables_page')
        );
        
        add_submenu_page(
            'restaurant-booking',
            'Cài đặt',
            'Cài đặt',
            'manage_options',
            'restaurant-settings',
            array($this, 'settings_page')
        );
    }
    
    public function admin_init() {
        // Register settings
        register_setting('rb_settings', 'rb_max_tables');
        register_setting('rb_settings', 'rb_opening_hours');
        register_setting('rb_settings', 'rb_booking_duration');
    }
    
    public function admin_page() {
        $active_tab = isset($_GET['tab']) ? $_GET['tab'] : 'pending';
        ?>
        <div class="wrap">
            <h1>Quản lý đặt bàn</h1>
            
            <nav class="nav-tab-wrapper">
                <a href="?page=restaurant-booking&tab=pending" class="nav-tab <?php echo $active_tab == 'pending' ? 'nav-tab-active' : ''; ?>">
                    Chờ xác nhận (<?php echo $this->get_bookings_count('pending'); ?>)
                </a>
                <a href="?page=restaurant-booking&tab=confirmed" class="nav-tab <?php echo $active_tab == 'confirmed' ? 'nav-tab-active' : ''; ?>">
                    Đã xác nhận (<?php echo $this->get_bookings_count('confirmed'); ?>)
                </a>
                <a href="?page=restaurant-booking&tab=cancelled" class="nav-tab <?php echo $active_tab == 'cancelled' ? 'nav-tab-active' : ''; ?>">
                    Đã hủy (<?php echo $this->get_bookings_count('cancelled'); ?>)
                </a>
            </nav>
            
            <div class="tab-content">
                <?php $this->display_bookings_table($active_tab); ?>
            </div>
        </div>
        <?php
    }
    
    private function get_bookings_count($status) {
        global $wpdb;
        return $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}restaurant_bookings WHERE status = %s",
            $status
        ));
    }
    
    private function display_bookings_table($status) {
        $bookings = RB_Database::get_bookings($status);
        ?>
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Khách hàng</th>
                    <th>Liên hệ</th>
                    <th>Số khách</th>
                    <th>Ngày & Giờ</th>
                    <th>Bàn số</th>
                    <th>Trạng thái</th>
                    <th>Thao tác</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($bookings)): ?>
                <tr>
                    <td colspan="8">Không có đặt bàn nào.</td>
                </tr>
                <?php else: ?>
                    <?php foreach ($bookings as $booking): ?>
                    <tr>
                        <td><?php echo $booking->id; ?></td>
                        <td>
                            <strong><?php echo esc_html($booking->customer_name); ?></strong>
                            <?php if ($booking->special_requests): ?>
                                <br><small><em><?php echo esc_html($booking->special_requests); ?></em></small>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php echo esc_html($booking->customer_phone); ?><br>
                            <a href="mailto:<?php echo esc_attr($booking->customer_email); ?>">
                                <?php echo esc_html($booking->customer_email); ?>
                            </a>
                        </td>
                        <td><?php echo $booking->guest_count; ?> người</td>
                        <td>
                            <?php echo date('d/m/Y', strtotime($booking->booking_date)); ?><br>
                            <?php echo date('H:i', strtotime($booking->booking_time)); ?>
                        </td>
                        <td>
                            <?php if ($booking->table_number): ?>
                                Bàn <?php echo $booking->table_number; ?>
                            <?php else: ?>
                                <span class="dashicons dashicons-minus"></span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <span class="rb-status rb-status-<?php echo $booking->status; ?>">
                                <?php echo $this->get_status_label($booking->status); ?>
                            </span>
                        </td>
                        <td>
                            <?php if ($booking->status == 'pending'): ?>
                                <button class="button button-primary rb-confirm-booking" data-booking-id="<?php echo $booking->id; ?>">
                                    Xác nhận
                                </button>
                                <button class="button rb-cancel-booking" data-booking-id="<?php echo $booking->id; ?>">
                                    Hủy
                                </button>
                            <?php elseif ($booking->status == 'confirmed'): ?>
                                <button class="button rb-complete-booking" data-booking-id="<?php echo $booking->id; ?>">
                                    Hoàn thành
                                </button>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
        
        <!-- Confirm booking modal -->
        <div id="rb-confirm-modal" class="rb-admin-modal" style="display:none;">
            <div class="rb-admin-modal-content">
                <h3>Xác nhận đặt bàn</h3>
                <form id="rb-confirm-form">
                    <input type="hidden" id="confirm-booking-id" name="booking_id">
                    <p><strong>Chọn bàn cho khách hàng:</strong></p>
                    <div id="available-tables-list">
                        <!-- Will be populated by AJAX -->
                    </div>
                    <div class="rb-modal-actions">
                        <button type="submit" class="button button-primary">Xác nhận</button>
                        <button type="button" class="button rb-modal-close">Hủy</button>
                    </div>
                </form>
            </div>
        </div>
        <?php
    }
    
    private function get_status_label($status) {
        $labels = array(
            'pending' => 'Chờ xác nhận',
            'confirmed' => 'Đã xác nhận',
            'completed' => 'Hoàn thành',
            'cancelled' => 'Đã hủy'
        );
        
        return isset($labels[$status]) ? $labels[$status] : $status;
    }
    
    public function tables_page() {
        ?>
        <div class="wrap">
            <h1>Quản lý bàn</h1>
            
            <div class="rb-tables-grid">
                <?php
                global $wpdb;
                $tables = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}restaurant_tables ORDER BY table_number");
                
                foreach ($tables as $table):
                    $today = date('Y-m-d');
                    $occupied_slots = $wpdb->get_results($wpdb->prepare(
                        "SELECT booking_time FROM {$wpdb->prefix}restaurant_table_availability 
                         WHERE table_id = %d AND booking_date = %s AND is_occupied = 1",
                        $table->id, $today
                    ));
                ?>
                <div class="rb-table-card">
                    <div class="rb-table-header">
                        <h3>Bàn <?php echo $table->table_number; ?></h3>
                        <span class="rb-table-capacity"><?php echo $table->capacity; ?> chỗ</span>
                    </div>
                    
                    <div class="rb-table-status">
                        <?php if ($table->is_available): ?>
                            <span class="rb-status-available">Hoạt động</span>
                        <?php else: ?>
                            <span class="rb-status-unavailable">Tạm ngưng</span>
                        <?php endif; ?>
                    </div>
                    
                    <div class="rb-table-schedule">
                        <h4>Lịch hôm nay (<?php echo date('d/m/Y'); ?>):</h4>
                        <?php if (empty($occupied_slots)): ?>
                            <p class="rb-no-bookings">Không có đặt bàn</p>
                        <?php else: ?>
                            <ul class="rb-booking-times">
                                <?php foreach ($occupied_slots as $slot): ?>
                                    <li><?php echo date('H:i', strtotime($slot->booking_time)); ?></li>
                                <?php endforeach; ?>
                            </ul>
                        <?php endif; ?>
                    </div>
                    
                    <div class="rb-table-actions">
                        <button class="button rb-reset-table" data-table-id="<?php echo $table->id; ?>">
                            Reset bàn
                        </button>
                        <button class="button rb-toggle-table" data-table-id="<?php echo $table->id; ?>" data-status="<?php echo $table->is_available; ?>">
                            <?php echo $table->is_available ? 'Tạm ngưng' : 'Kích hoạt'; ?>
                        </button>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php
    }
    
    public function settings_page() {
        if (isset($_POST['submit'])) {
            update_option('rb_max_tables', intval($_POST['rb_max_tables']));
            update_option('rb_opening_hours', array(
                'start' => sanitize_text_field($_POST['opening_start']),
                'end' => sanitize_text_field($_POST['opening_end'])
            ));
            update_option('rb_booking_duration', intval($_POST['rb_booking_duration']));
            
            echo '<div class="notice notice-success"><p>Cài đặt đã được lưu!</p></div>';
        }
        
        $max_tables = get_option('rb_max_tables', 20);
        $opening_hours = get_option('rb_opening_hours', array('start' => '09:00', 'end' => '22:00'));
        $booking_duration = get_option('rb_booking_duration', 120);
        ?>
        <div class="wrap">
            <h1>Cài đặt đặt bàn</h1>
            
            <form method="post" action="">
                <table class="form-table">
                    <tr>
                        <th scope="row">Số bàn tối đa</th>
                        <td>
                            <input type="number" name="rb_max_tables" value="<?php echo esc_attr($max_tables); ?>" min="1" max="100">
                            <p class="description">Số lượng bàn tối đa trong nhà hàng</p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">Giờ mở cửa</th>
                        <td>
                            <input type="time" name="opening_start" value="<?php echo esc_attr($opening_hours['start']); ?>">
                            đến
                            <input type="time" name="opening_end" value="<?php echo esc_attr($opening_hours['end']); ?>">
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">Thời gian đặt bàn (phút)</th>
                        <td>
                            <input type="number" name="rb_booking_duration" value="<?php echo esc_attr($booking_duration); ?>" min="30" max="480" step="30">
                            <p class="description">Thời gian một lượt đặt bàn (mặc định 120 phút)</p>
                        </td>
                    </tr>
                </table>
                
                <?php submit_button('Lưu cài đặt'); ?>
            </form>
        </div>
        <?php
    }
}
