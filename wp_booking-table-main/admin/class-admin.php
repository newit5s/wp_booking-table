<?php
/**
 * Admin Class - Quản lý backend
 */

if (!defined('ABSPATH')) {
    exit;
}

class RB_Admin {
    
    /**
     * Constructor
     */
    public function __construct() {
        // Add admin menu
        add_action('admin_menu', array($this, 'add_admin_menu'));
        
        // Handle admin actions
        add_action('admin_init', array($this, 'handle_admin_actions'));
        
        // Add admin notices
        add_action('admin_notices', array($this, 'display_admin_notices'));
    }
    
    /**
     * Add admin menu items
     */
    public function add_admin_menu() {
        // Main menu
        add_menu_page(
            __('Đặt bàn', 'restaurant-booking'),
            __('Đặt bàn', 'restaurant-booking'),
            'manage_options',
            'restaurant-booking',
            array($this, 'display_bookings_page'),
            'dashicons-calendar-alt',
            30
        );
        
        // Submenu - Bookings
        add_submenu_page(
            'restaurant-booking',
            __('Tất cả đặt bàn', 'restaurant-booking'),
            __('Tất cả đặt bàn', 'restaurant-booking'),
            'manage_options',
            'restaurant-booking',
            array($this, 'display_bookings_page')
        );
        
        // Submenu - Tables Management
        add_submenu_page(
            'restaurant-booking',
            __('Quản lý bàn', 'restaurant-booking'),
            __('Quản lý bàn', 'restaurant-booking'),
            'manage_options',
            'rb-tables',
            array($this, 'display_tables_page')
        );
        
        // Submenu - Settings
        add_submenu_page(
            'restaurant-booking',
            __('Cài đặt', 'restaurant-booking'),
            __('Cài đặt', 'restaurant-booking'),
            'manage_options',
            'rb-settings',
            array($this, 'display_settings_page')
        );
        
        // Submenu - Reports
        add_submenu_page(
            'restaurant-booking',
            __('Báo cáo', 'restaurant-booking'),
            __('Báo cáo', 'restaurant-booking'),
            'manage_options',
            'rb-reports',
            array($this, 'display_reports_page')
        );
    }
    
    /**
     * Display bookings page
     */
    public function display_bookings_page() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'rb_bookings';
        
        // Get current tab
        $current_tab = isset($_GET['tab']) ? sanitize_text_field($_GET['tab']) : 'all';
        
        // Get bookings based on tab
        $where_clause = '';
        switch ($current_tab) {
            case 'pending':
                $where_clause = "WHERE status = 'pending'";
                break;
            case 'confirmed':
                $where_clause = "WHERE status = 'confirmed'";
                break;
            case 'cancelled':
                $where_clause = "WHERE status = 'cancelled'";
                break;
            case 'completed':
                $where_clause = "WHERE status = 'completed'";
                break;
        }
        
        $bookings = $wpdb->get_results("SELECT * FROM $table_name $where_clause ORDER BY booking_date DESC, booking_time DESC");
        
        ?>
        <div class="wrap">
            <h1><?php _e('Quản lý đặt bàn', 'restaurant-booking'); ?></h1>
            
            <!-- Tabs -->
            <nav class="nav-tab-wrapper">
                <a href="?page=restaurant-booking&tab=all" class="nav-tab <?php echo $current_tab == 'all' ? 'nav-tab-active' : ''; ?>">
                    <?php _e('Tất cả', 'restaurant-booking'); ?>
                </a>
                <a href="?page=restaurant-booking&tab=pending" class="nav-tab <?php echo $current_tab == 'pending' ? 'nav-tab-active' : ''; ?>">
                    <?php _e('Chờ xác nhận', 'restaurant-booking'); ?>
                    <?php
                    $pending_count = $wpdb->get_var("SELECT COUNT(*) FROM $table_name WHERE status = 'pending'");
                    if ($pending_count > 0) {
                        echo '<span class="update-plugins count-' . $pending_count . '"><span class="plugin-count">' . $pending_count . '</span></span>';
                    }
                    ?>
                </a>
                <a href="?page=restaurant-booking&tab=confirmed" class="nav-tab <?php echo $current_tab == 'confirmed' ? 'nav-tab-active' : ''; ?>">
                    <?php _e('Đã xác nhận', 'restaurant-booking'); ?>
                </a>
                <a href="?page=restaurant-booking&tab=cancelled" class="nav-tab <?php echo $current_tab == 'cancelled' ? 'nav-tab-active' : ''; ?>">
                    <?php _e('Đã hủy', 'restaurant-booking'); ?>
                </a>
                <a href="?page=restaurant-booking&tab=completed" class="nav-tab <?php echo $current_tab == 'completed' ? 'nav-tab-active' : ''; ?>">
                    <?php _e('Hoàn thành', 'restaurant-booking'); ?>
                </a>
            </nav>
            
            <!-- Bookings Table -->
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th><?php _e('ID', 'restaurant-booking'); ?></th>
                        <th><?php _e('Khách hàng', 'restaurant-booking'); ?></th>
                        <th><?php _e('Điện thoại', 'restaurant-booking'); ?></th>
                        <th><?php _e('Email', 'restaurant-booking'); ?></th>
                        <th><?php _e('Ngày/Giờ', 'restaurant-booking'); ?></th>
                        <th><?php _e('Số khách', 'restaurant-booking'); ?></th>
                        <th><?php _e('Bàn số', 'restaurant-booking'); ?></th>
                        <th><?php _e('Trạng thái', 'restaurant-booking'); ?></th>
                        <th><?php _e('Hành động', 'restaurant-booking'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($bookings) : ?>
                        <?php foreach ($bookings as $booking) : ?>
                            <tr>
                                <td><?php echo esc_html($booking->id); ?></td>
                                <td><?php echo esc_html($booking->customer_name); ?></td>
                                <td><?php echo esc_html($booking->customer_phone); ?></td>
                                <td><?php echo esc_html($booking->customer_email); ?></td>
                                <td>
                                    <?php echo esc_html(date('d/m/Y', strtotime($booking->booking_date))); ?><br>
                                    <?php echo esc_html($booking->booking_time); ?>
                                </td>
                                <td><?php echo esc_html($booking->guest_count); ?></td>
                                <td><?php echo $booking->table_number ? esc_html($booking->table_number) : '-'; ?></td>
                                <td>
                                    <span class="rb-status rb-status-<?php echo esc_attr($booking->status); ?>">
                                        <?php echo $this->get_status_label($booking->status); ?>
                                    </span>
                                </td>
                                <td>
                                    <?php if ($booking->status == 'pending') : ?>
                                        <a href="?page=restaurant-booking&action=confirm&id=<?php echo $booking->id; ?>&_wpnonce=<?php echo wp_create_nonce('rb_action'); ?>" class="button button-primary button-small">
                                            <?php _e('Xác nhận', 'restaurant-booking'); ?>
                                        </a>
                                        <a href="?page=restaurant-booking&action=cancel&id=<?php echo $booking->id; ?>&_wpnonce=<?php echo wp_create_nonce('rb_action'); ?>" class="button button-small">
                                            <?php _e('Hủy', 'restaurant-booking'); ?>
                                        </a>
                                    <?php elseif ($booking->status == 'confirmed') : ?>
                                        <a href="?page=restaurant-booking&action=complete&id=<?php echo $booking->id; ?>&_wpnonce=<?php echo wp_create_nonce('rb_action'); ?>" class="button button-small">
                                            <?php _e('Hoàn thành', 'restaurant-booking'); ?>
                                        </a>
                                        <a href="?page=restaurant-booking&action=cancel&id=<?php echo $booking->id; ?>&_wpnonce=<?php echo wp_create_nonce('rb_action'); ?>" class="button button-small">
                                            <?php _e('Hủy', 'restaurant-booking'); ?>
                                        </a>
                                    <?php endif; ?>
                                    <a href="?page=restaurant-booking&action=view&id=<?php echo $booking->id; ?>" class="button button-small">
                                        <?php _e('Xem', 'restaurant-booking'); ?>
                                    </a>
                                    <a href="?page=restaurant-booking&action=delete&id=<?php echo $booking->id; ?>&_wpnonce=<?php echo wp_create_nonce('rb_action'); ?>" class="button button-small" onclick="return confirm('<?php _e('Bạn có chắc muốn xóa?', 'restaurant-booking'); ?>')">
                                        <?php _e('Xóa', 'restaurant-booking'); ?>
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else : ?>
                        <tr>
                            <td colspan="9" style="text-align: center;">
                                <?php _e('Không có đặt bàn nào.', 'restaurant-booking'); ?>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        
        <style>
            .rb-status {
                padding: 3px 8px;
                border-radius: 3px;
                font-size: 12px;
                font-weight: 600;
                display: inline-block;
            }
            .rb-status-pending { background: #fef2c0; color: #973d00; }
            .rb-status-confirmed { background: #c6e1c6; color: #2e6e2e; }
            .rb-status-cancelled { background: #f5c6c6; color: #8a0000; }
            .rb-status-completed { background: #d4edda; color: #155724; }
        </style>
        <?php
    }
    
    /**
     * Display tables management page
     */
    public function display_tables_page() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'rb_tables';
        
        // Get all tables
        $tables = $wpdb->get_results("SELECT * FROM $table_name ORDER BY table_number");
        
        // Get settings
        $settings = get_option('rb_settings', array());
        $max_tables = isset($settings['max_tables']) ? $settings['max_tables'] : 20;
        
        ?>
        <div class="wrap">
            <h1><?php _e('Quản lý bàn', 'restaurant-booking'); ?></h1>
            
            <div class="card">
                <h2><?php _e('Thêm bàn mới', 'restaurant-booking'); ?></h2>
                <form method="post" action="">
                    <?php wp_nonce_field('rb_add_table', 'rb_nonce'); ?>
                    <input type="hidden" name="action" value="add_table">
                    <table class="form-table">
                        <tr>
                            <th><label for="table_number"><?php _e('Số bàn', 'restaurant-booking'); ?></label></th>
                            <td>
                                <input type="number" name="table_number" id="table_number" min="1" required class="regular-text">
                            </td>
                        </tr>
                        <tr>
                            <th><label for="capacity"><?php _e('Sức chứa', 'restaurant-booking'); ?></label></th>
                            <td>
                                <input type="number" name="capacity" id="capacity" min="1" max="20" required class="regular-text">
                            </td>
                        </tr>
                    </table>
                    <p class="submit">
                        <button type="submit" class="button button-primary"><?php _e('Thêm bàn', 'restaurant-booking'); ?></button>
                    </p>
                </form>
            </div>
            
            <h2><?php _e('Danh sách bàn', 'restaurant-booking'); ?></h2>
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th><?php _e('Số bàn', 'restaurant-booking'); ?></th>
                        <th><?php _e('Sức chứa', 'restaurant-booking'); ?></th>
                        <th><?php _e('Trạng thái', 'restaurant-booking'); ?></th>
                        <th><?php _e('Hành động', 'restaurant-booking'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($tables) : ?>
                        <?php foreach ($tables as $table) : ?>
                            <tr>
                                <td><?php echo esc_html($table->table_number); ?></td>
                                <td><?php echo esc_html($table->capacity); ?> người</td>
                                <td>
                                    <?php if ($table->is_available) : ?>
                                        <span style="color: green;">✓ <?php _e('Hoạt động', 'restaurant-booking'); ?></span>
                                    <?php else : ?>
                                        <span style="color: red;">✗ <?php _e('Tạm ngưng', 'restaurant-booking'); ?></span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($table->is_available) : ?>
                                        <a href="?page=rb-tables&action=disable_table&id=<?php echo $table->id; ?>&_wpnonce=<?php echo wp_create_nonce('rb_action'); ?>" class="button button-small">
                                            <?php _e('Tạm ngưng', 'restaurant-booking'); ?>
                                        </a>
                                    <?php else : ?>
                                        <a href="?page=rb-tables&action=enable_table&id=<?php echo $table->id; ?>&_wpnonce=<?php echo wp_create_nonce('rb_action'); ?>" class="button button-small">
                                            <?php _e('Kích hoạt', 'restaurant-booking'); ?>
                                        </a>
                                    <?php endif; ?>
                                    <a href="?page=rb-tables&action=delete_table&id=<?php echo $table->id; ?>&_wpnonce=<?php echo wp_create_nonce('rb_action'); ?>" class="button button-small" onclick="return confirm('<?php _e('Bạn có chắc muốn xóa bàn này?', 'restaurant-booking'); ?>')">
                                        <?php _e('Xóa', 'restaurant-booking'); ?>
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else : ?>
                        <tr>
                            <td colspan="4" style="text-align: center;">
                                <?php _e('Chưa có bàn nào. Vui lòng thêm bàn mới.', 'restaurant-booking'); ?>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        <?php
    }
    
    /**
     * Display settings page
     */
    public function display_settings_page() {
        $settings = get_option('rb_settings', array());
        ?>
        <div class="wrap">
            <h1><?php _e('Cài đặt Restaurant Booking', 'restaurant-booking'); ?></h1>
            
            <form method="post" action="">
                <?php wp_nonce_field('rb_save_settings', 'rb_nonce'); ?>
                <input type="hidden" name="action" value="save_settings">
                
                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="max_tables"><?php _e('Số bàn tối đa', 'restaurant-booking'); ?></label>
                        </th>
                        <td>
                            <input type="number" name="rb_settings[max_tables]" id="max_tables" 
                                   value="<?php echo isset($settings['max_tables']) ? esc_attr($settings['max_tables']) : 20; ?>" 
                                   min="1" max="100" class="regular-text">
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label for="opening_time"><?php _e('Giờ mở cửa', 'restaurant-booking'); ?></label>
                        </th>
                        <td>
                            <input type="time" name="rb_settings[opening_time]" id="opening_time" 
                                   value="<?php echo isset($settings['opening_time']) ? esc_attr($settings['opening_time']) : '09:00'; ?>" 
                                   class="regular-text">
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label for="closing_time"><?php _e('Giờ đóng cửa', 'restaurant-booking'); ?></label>
                        </th>
                        <td>
                            <input type="time" name="rb_settings[closing_time]" id="closing_time" 
                                   value="<?php echo isset($settings['closing_time']) ? esc_attr($settings['closing_time']) : '22:00'; ?>" 
                                   class="regular-text">
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label for="time_slot_interval"><?php _e('Khoảng thời gian đặt bàn (phút)', 'restaurant-booking'); ?></label>
                        </th>
                        <td>
                            <select name="rb_settings[time_slot_interval]" id="time_slot_interval">
                                <option value="15" <?php selected(isset($settings['time_slot_interval']) ? $settings['time_slot_interval'] : 30, 15); ?>>15 phút</option>
                                <option value="30" <?php selected(isset($settings['time_slot_interval']) ? $settings['time_slot_interval'] : 30, 30); ?>>30 phút</option>
                                <option value="60" <?php selected(isset($settings['time_slot_interval']) ? $settings['time_slot_interval'] : 30, 60); ?>>60 phút</option>
                            </select>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label for="admin_email"><?php _e('Email nhận thông báo', 'restaurant-booking'); ?></label>
                        </th>
                        <td>
                            <input type="email" name="rb_settings[admin_email]" id="admin_email" 
                                   value="<?php echo isset($settings['admin_email']) ? esc_attr($settings['admin_email']) : get_option('admin_email'); ?>" 
                                   class="regular-text">
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label for="enable_email"><?php _e('Gửi email tự động', 'restaurant-booking'); ?></label>
                        </th>
                        <td>
                            <label>
                                <input type="checkbox" name="rb_settings[enable_email]" id="enable_email" 
                                       value="yes" <?php checked(isset($settings['enable_email']) ? $settings['enable_email'] : 'yes', 'yes'); ?>>
                                <?php _e('Kích hoạt gửi email cho khách hàng', 'restaurant-booking'); ?>
                            </label>
                        </td>
                    </tr>
                </table>
                
                <p class="submit">
                    <button type="submit" class="button button-primary"><?php _e('Lưu cài đặt', 'restaurant-booking'); ?></button>
                </p>
            </form>
        </div>
        <?php
    }
    
    /**
     * Display reports page
     */
    public function display_reports_page() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'rb_bookings';
        
        // Get statistics
        $total_bookings = $wpdb->get_var("SELECT COUNT(*) FROM $table_name");
        $pending_bookings = $wpdb->get_var("SELECT COUNT(*) FROM $table_name WHERE status = 'pending'");
        $confirmed_bookings = $wpdb->get_var("SELECT COUNT(*) FROM $table_name WHERE status = 'confirmed'");
        $completed_bookings = $wpdb->get_var("SELECT COUNT(*) FROM $table_name WHERE status = 'completed'");
        $cancelled_bookings = $wpdb->get_var("SELECT COUNT(*) FROM $table_name WHERE status = 'cancelled'");
        
        // Get today's bookings
        $today = date('Y-m-d');
        $today_bookings = $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $table_name WHERE booking_date = %s", $today));
        
        ?>
        <div class="wrap">
            <h1><?php _e('Báo cáo & Thống kê', 'restaurant-booking'); ?></h1>
            
            <div class="rb-stats-grid">
                <div class="rb-stat-box">
                    <h3><?php _e('Tổng đặt bàn', 'restaurant-booking'); ?></h3>
                    <p class="rb-stat-number"><?php echo $total_bookings; ?></p>
                </div>
                
                <div class="rb-stat-box">
                    <h3><?php _e('Chờ xác nhận', 'restaurant-booking'); ?></h3>
                    <p class="rb-stat-number" style="color: #f39c12;"><?php echo $pending_bookings; ?></p>
                </div>
                
                <div class="rb-stat-box">
                    <h3><?php _e('Đã xác nhận', 'restaurant-booking'); ?></h3>
                    <p class="rb-stat-number" style="color: #27ae60;"><?php echo $confirmed_bookings; ?></p>
                </div>
                
                <div class="rb-stat-box">
                    <h3><?php _e('Hoàn thành', 'restaurant-booking'); ?></h3>
                    <p class="rb-stat-number" style="color: #2ecc71;"><?php echo $completed_bookings; ?></p>
                </div>
                
                <div class="rb-stat-box">
                    <h3><?php _e('Đã hủy', 'restaurant-booking'); ?></h3>
                    <p class="rb-stat-number" style="color: #e74c3c;"><?php echo $cancelled_bookings; ?></p>
                </div>
                
                <div class="rb-stat-box">
                    <h3><?php _e('Đặt bàn hôm nay', 'restaurant-booking'); ?></h3>
                    <p class="rb-stat-number" style="color: #3498db;"><?php echo $today_bookings; ?></p>
                </div>
            </div>
            
            <style>
                .rb-stats-grid {
                    display: grid;
                    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
                    gap: 20px;
                    margin-top: 20px;
                }
                .rb-stat-box {
                    background: #fff;
                    border: 1px solid #ddd;
                    border-radius: 5px;
                    padding: 20px;
                    text-align: center;
                }
                .rb-stat-box h3 {
                    margin: 0 0 10px 0;
                    color: #666;
                    font-size: 14px;
                }
                .rb-stat-number {
                    font-size: 36px;
                    font-weight: bold;
                    margin: 0;
                }
            </style>
        </div>
        <?php
    }
    
    /**
     * Handle admin actions
     */
    public function handle_admin_actions() {
        if (!isset($_GET['_wpnonce']) && !isset($_POST['rb_nonce'])) {
            return;
        }
        
        // Handle GET actions
        if (isset($_GET['action']) && isset($_GET['_wpnonce'])) {
            if (!wp_verify_nonce($_GET['_wpnonce'], 'rb_action')) {
                wp_die('Security check failed');
            }
            
            global $wpdb;
            $action = sanitize_text_field($_GET['action']);
            $id = isset($_GET['id']) ? intval($_GET['id']) : 0;
            
            switch ($action) {
                case 'confirm':
                    $this->confirm_booking($id);
                    break;
                case 'cancel':
                    $this->cancel_booking($id);
                    break;
                case 'complete':
                    $this->complete_booking($id);
                    break;
                case 'delete':
                    $this->delete_booking($id);
                    break;
                case 'enable_table':
                    $this->toggle_table($id, 1);
                    break;
                case 'disable_table':
                    $this->toggle_table($id, 0);
                    break;
                case 'delete_table':
                    $this->delete_table($id);
                    break;
            }
        }
        
        // Handle POST actions
        if (isset($_POST['action']) && isset($_POST['rb_nonce'])) {
            $action = sanitize_text_field($_POST['action']);
            
            switch ($action) {
                case 'save_settings':
                    if (wp_verify_nonce($_POST['rb_nonce'], 'rb_save_settings')) {
                        $this->save_settings();
                    }
                    break;
                case 'add_table':
                    if (wp_verify_nonce($_POST['rb_nonce'], 'rb_add_table')) {
                        $this->add_table();
                    }
                    break;
            }
        }
    }
    
    /**
     * Confirm booking
     */
    private function confirm_booking($id) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'rb_bookings';
        
        $wpdb->update(
            $table_name,
            array(
                'status' => 'confirmed',
                'confirmed_at' => current_time('mysql')
            ),
            array('id' => $id)
        );
        
        // Send confirmation email
        $booking = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_name WHERE id = %d", $id));
        if ($booking && class_exists('RB_Email')) {
            $email = new RB_Email();
            $email->send_confirmation_email($booking);
        }
        
        wp_redirect(admin_url('admin.php?page=restaurant-booking&message=confirmed'));
        exit;
    }
    
    /**
     * Cancel booking
     */
    private function cancel_booking($id) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'rb_bookings';
        
        $wpdb->update(
            $table_name,
            array('status' => 'cancelled'),
            array('id' => $id)
        );
        
        wp_redirect(admin_url('admin.php?page=restaurant-booking&message=cancelled'));
        exit;
    }
    
    /**
     * Complete booking
     */
    private function complete_booking($id) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'rb_bookings';
        
        $wpdb->update(
            $table_name,
            array('status' => 'completed'),
            array('id' => $id)
        );
        
        wp_redirect(admin_url('admin.php?page=restaurant-booking&message=completed'));
        exit;
    }
    
    /**
     * Delete booking
     */
    private function delete_booking($id) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'rb_bookings';
        
        $wpdb->delete($table_name, array('id' => $id));
        
        wp_redirect(admin_url('admin.php?page=restaurant-booking&message=deleted'));
        exit;
    }
    
    /**
     * Toggle table availability
     */
    private function toggle_table($id, $status) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'rb_tables';
        
        $wpdb->update(
            $table_name,
            array('is_available' => $status),
            array('id' => $id)
        );
        
        wp_redirect(admin_url('admin.php?page=rb-tables&message=updated'));
        exit;
    }
    
    /**
     * Delete table
     */
    private function delete_table($id) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'rb_tables';
        
        $wpdb->delete($table_name, array('id' => $id));
        
        wp_redirect(admin_url('admin.php?page=rb-tables&message=deleted'));
        exit;
    }
    
    /**
     * Add new table
     */
    private function add_table() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'rb_tables';
        
        $table_number = intval($_POST['table_number']);
        $capacity = intval($_POST['capacity']);
        
        // Check if table number exists
        $exists = $wpdb->get_var($wpdb->prepare("SELECT id FROM $table_name WHERE table_number = %d", $table_number));
        
        if ($exists) {
            wp_redirect(admin_url('admin.php?page=rb-tables&message=exists'));
            exit;
        }
        
        $wpdb->insert(
            $table_name,
            array(
                'table_number' => $table_number,
                'capacity' => $capacity,
                'is_available' => 1,
                'created_at' => current_time('mysql')
            )
        );
        
        wp_redirect(admin_url('admin.php?page=rb-tables&message=added'));
        exit;
    }
    
    /**
     * Save settings
     */
    private function save_settings() {
        $settings = isset($_POST['rb_settings']) ? $_POST['rb_settings'] : array();
        
        // Sanitize settings
        $clean_settings = array(
            'max_tables' => isset($settings['max_tables']) ? intval($settings['max_tables']) : 20,
            'opening_time' => isset($settings['opening_time']) ? sanitize_text_field($settings['opening_time']) : '09:00',
            'closing_time' => isset($settings['closing_time']) ? sanitize_text_field($settings['closing_time']) : '22:00',
            'time_slot_interval' => isset($settings['time_slot_interval']) ? intval($settings['time_slot_interval']) : 30,
            'admin_email' => isset($settings['admin_email']) ? sanitize_email($settings['admin_email']) : get_option('admin_email'),
            'enable_email' => isset($settings['enable_email']) ? 'yes' : 'no'
        );
        
        update_option('rb_settings', $clean_settings);
        
        wp_redirect(admin_url('admin.php?page=rb-settings&message=saved'));
        exit;
    }
    
    /**
     * Display admin notices
     */
    public function display_admin_notices() {
        if (!isset($_GET['message'])) {
            return;
        }
        
        $message = sanitize_text_field($_GET['message']);
        $text = '';
        $type = 'success';
        
        switch ($message) {
            case 'confirmed':
                $text = __('Đặt bàn đã được xác nhận.', 'restaurant-booking');
                break;
            case 'cancelled':
                $text = __('Đặt bàn đã được hủy.', 'restaurant-booking');
                break;
            case 'completed':
                $text = __('Đặt bàn đã hoàn thành.', 'restaurant-booking');
                break;
            case 'deleted':
                $text = __('Đã xóa thành công.', 'restaurant-booking');
                break;
            case 'saved':
                $text = __('Cài đặt đã được lưu.', 'restaurant-booking');
                break;
            case 'added':
                $text = __('Đã thêm bàn mới.', 'restaurant-booking');
                break;
            case 'exists':
                $text = __('Số bàn này đã tồn tại.', 'restaurant-booking');
                $type = 'error';
                break;
        }
        
        if ($text) {
            ?>
            <div class="notice notice-<?php echo $type; ?> is-dismissible">
                <p><?php echo $text; ?></p>
            </div>
            <?php
        }
    }
    
    /**
     * Get status label
     */
    private function get_status_label($status) {
        $labels = array(
            'pending' => __('Chờ xác nhận', 'restaurant-booking'),
            'confirmed' => __('Đã xác nhận', 'restaurant-booking'),
            'cancelled' => __('Đã hủy', 'restaurant-booking'),
            'completed' => __('Hoàn thành', 'restaurant-booking')
        );
        
        return isset($labels[$status]) ? $labels[$status] : $status;
    }
}